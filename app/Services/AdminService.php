<?php

namespace App\Services;

use App\Events\UserPermissionsUpdated;
use App\Exceptions\BusinessRuleException;
use App\Models\Module;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminService
{
    /**
     * Get paginated list of users with filtering and sorting.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = User::with(['roles', 'permissions']);

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', "%{$filters['search']}%")
                    ->orWhere('email', 'like', "%{$filters['search']}%");
            });
        }

        if (isset($filters['role'])) {
            $query->whereHas('roles', fn ($q) => $q->where('name', $filters['role']));
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $query->orderBy($filters['sort_by'], $filters['sort_order']);

        return $query->paginate($filters['per_page']);
    }

    /**
     * Get a single user with roles and permissions.
     */
    public function show(User $user): User
    {
        return $user->load(['roles', 'permissions']);
    }

    /**
     * Create a new user with role and permissions.
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            // Handle file upload if an UploadedFile instance is present
            $profilePicturePath = null;
            if (isset($data['profile_picture']) && $data['profile_picture'] instanceof UploadedFile) {
                $profilePicturePath = $data['profile_picture']->store('profile_pictures', 'public');
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'profile_picture' => $profilePicturePath,
                'created_by' => Auth::id(),
            ]);

            $user->assignRole($data['role']);
            $this->syncPermissionsForRole($user, $data);

            return $user->load(['roles', 'permissions']);
        });
    }

    /**
     * Update a user's role, permissions, and/or password.
     *
     * Broadcasts UserPermissionsUpdated event after successful update.
     */
    public function update(User $user, array $data): User
    {
        $this->guardAdminModification($user);

        // Run role/permission/password updates inside transaction
        DB::transaction(function () use ($user, $data) {
            if (isset($data['role'])) {
                $user->roles()->detach();
                $user->assignRole($data['role']);
                $this->syncPermissionsForRole($user, $data);
            } else {
                // If role is not being updated, just sync permissions normally
                $permissions = $this->extractPermissions($data);
                if (! empty($permissions)) {
                    $user->syncPermissions($permissions);
                }
            }

            if (isset($data['password'])) {
                $user->password = Hash::make($data['password']);
                $user->save();
            }
        });

        $user->load(['roles', 'permissions']);

        // Broadcast AFTER transaction commit (don't rollback DB on broadcast failure)
        $adminName = Auth::user()->name ?? 'System';
        event(new UserPermissionsUpdated($user->id, $adminName, 'Role or permissions updated by admin'));

        Log::info('UserPermissionsUpdated event dispatched', [
            'target_user_id' => $user->id,
            'updated_by' => $adminName,
        ]);

        return $user;
    }

    /**
     * Delete a user and clean up role/permission associations.
     */
    public function delete(User $user): void
    {
        $this->guardAdminModification($user);

        DB::transaction(function () use ($user) {
            $user->roles()->detach();
            $user->permissions()->detach();
            $user->delete();
        });
    }

    /**
     * Get all roles.
     */
    public function roles(): Collection
    {
        return Role::query()->orderBy('name')->get();
    }

    /**
     * Get all permissions.
     */
    public function allPermissions(): Collection
    {
        return Permission::query()->orderBy('name')->get();
    }

    /**
     * Guard against non-admin users modifying admin accounts.
     *
     * @throws BusinessRuleException
     */
    private function guardAdminModification(User $user): void
    {
        if ($user->hasRole('admin') && ! Auth::user()->hasRole('admin')) {
            throw new BusinessRuleException(
                'You do not have permission to modify an administrator.',
                403
            );
        }
    }

    /**
     * Sync permissions based on the assigned role.
     *
     * Admin → admin-only modules; HR Manager → all modules; Others → from request.
     */
    private function syncPermissionsForRole(User $user, array $data): void
    {
        if ($data['role'] === 'admin') {
            $adminPermissions = $this->getAdminModulePermissions();
            $user->syncPermissions($adminPermissions);
            Log::info("Auto-assigned admin permissions to user {$user->id}");
        } elseif ($data['role'] === 'hr-manager') {
            $allModulePermissions = $this->getAllModulePermissions();
            $user->syncPermissions($allModulePermissions);
            Log::info("Auto-assigned all module permissions to user {$user->id} with role hr-manager");
        } else {
            $permissions = $this->extractPermissions($data);
            if (! empty($permissions)) {
                $user->syncPermissions($permissions);
            }
        }
    }

    /**
     * Extract permissions from validated data.
     * Supports both new modules format (Read/Edit) and legacy permissions array.
     *
     * @return array Array of permission strings
     */
    private function extractPermissions(array $validated): array
    {
        $desiredPermissions = [];

        // Handle new modules format with Read/Edit checkboxes
        if (isset($validated['modules']) && is_array($validated['modules'])) {
            $modules = Module::active()->get()->keyBy('name');

            foreach ($validated['modules'] as $moduleName => $access) {
                $module = $modules->get($moduleName);

                if (! $module) {
                    Log::warning("Module not found: {$moduleName}");

                    continue;
                }

                if (isset($access['read']) && $access['read']) {
                    $desiredPermissions[] = $module->read_permission;
                }

                if (isset($access['create']) && $access['create']) {
                    $desiredPermissions[] = "{$moduleName}.create";
                }

                if (isset($access['update']) && $access['update']) {
                    $desiredPermissions[] = "{$moduleName}.update";
                }

                if (isset($access['delete']) && $access['delete']) {
                    $desiredPermissions[] = "{$moduleName}.delete";
                }
            }
        }
        // Handle legacy permissions array format (backward compatibility)
        elseif (isset($validated['permissions']) && is_array($validated['permissions'])) {
            $desiredPermissions = $validated['permissions'];
        }

        if (empty($desiredPermissions)) {
            return [];
        }

        // Only return permissions that actually exist in the database
        $existingPermissions = Permission::whereIn('name', $desiredPermissions)
            ->pluck('name')
            ->toArray();

        $missingPermissions = array_diff($desiredPermissions, $existingPermissions);
        if (! empty($missingPermissions)) {
            Log::warning('Requested permissions do not exist in database', [
                'missing' => $missingPermissions,
                'requested' => $desiredPermissions,
            ]);
        }

        return array_unique($existingPermissions);
    }

    /**
     * Get admin-only module permissions (administration modules).
     *
     * @return array Array of admin permission strings
     */
    private function getAdminModulePermissions(): array
    {
        $adminModules = [
            'dashboard',
            'lookup_list',
            'sites',
            'departments',
            'positions',
            'section_departments',
            'users',
            'roles',
            'file_uploads',
            'recycle_bin_list',
        ];

        $desiredPermissions = [];
        foreach ($adminModules as $module) {
            $desiredPermissions[] = "{$module}.read";
            $desiredPermissions[] = "{$module}.create";
            $desiredPermissions[] = "{$module}.update";
            $desiredPermissions[] = "{$module}.delete";
        }

        $existingPermissions = Permission::whereIn('name', $desiredPermissions)
            ->pluck('name')
            ->toArray();

        return array_unique($existingPermissions);
    }

    /**
     * Get all module permissions (read and edit) for HR Manager role.
     *
     * @return array Array of all module permission strings
     */
    private function getAllModulePermissions(): array
    {
        $desiredPermissions = [];
        $modules = Module::active()->get();

        foreach ($modules as $module) {
            $desiredPermissions[] = $module->read_permission;
            $desiredPermissions[] = "{$module->name}.create";
            $desiredPermissions[] = "{$module->name}.update";
            $desiredPermissions[] = "{$module->name}.delete";
        }

        $existingPermissions = Permission::whereIn('name', $desiredPermissions)
            ->pluck('name')
            ->toArray();

        return array_unique($existingPermissions);
    }
}
