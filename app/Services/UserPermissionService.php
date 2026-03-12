<?php

namespace App\Services;

use App\Events\UserPermissionsUpdated;
use App\Models\Module;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserPermissionService
{
    /**
     * Get user permissions grouped by module with Read/Edit flags.
     */
    public function show(User $user): array
    {
        $user->load('roles');
        $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();

        $modules = Module::active()->ordered()->get();

        $permissionsByModule = [];

        foreach ($modules as $module) {
            $hasRead = in_array($module->read_permission, $userPermissions);
            $hasCreate = in_array("{$module->name}.create", $userPermissions);
            $hasUpdate = in_array("{$module->name}.update", $userPermissions);
            $hasDelete = in_array("{$module->name}.delete", $userPermissions);

            $permissionsByModule[$module->name] = [
                'read' => $hasRead,
                'create' => $hasCreate,
                'update' => $hasUpdate,
                'delete' => $hasDelete,
                'display_name' => $module->display_name,
                'category' => $module->category,
                'icon' => $module->icon,
                'order' => $module->order,
            ];
        }

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->pluck('name'),
            ],
            'modules' => $permissionsByModule,
        ];
    }

    /**
     * Update user permissions based on Read/Edit checkboxes.
     */
    public function updatePermissions(User $user, array $moduleData): array
    {
        if ($user->hasRole('admin') && ! Auth::user()->hasRole('admin')) {
            abort(403, 'You do not have permission to modify an administrator\'s permissions.');
        }

        $modules = Module::active()->get()->keyBy('name');
        $permissions = [];

        foreach ($moduleData as $moduleName => $access) {
            $module = $modules->get($moduleName);

            if (! $module) {
                Log::warning("Module not found: {$moduleName}");

                continue;
            }

            if ($access['read'] ?? false) {
                $permissions[] = $module->read_permission;
            }

            if ($access['create'] ?? false) {
                $permissions[] = "{$moduleName}.create";
            }

            if ($access['update'] ?? false) {
                $permissions[] = "{$moduleName}.update";
            }

            if ($access['delete'] ?? false) {
                $permissions[] = "{$moduleName}.delete";
            }
        }

        $permissions = array_unique($permissions);

        $user->syncPermissions($permissions);

        Log::info('User permissions updated', [
            'user_id' => $user->id,
            'updated_by' => Auth::id(),
            'permissions_count' => count($permissions),
        ]);

        try {
            $adminName = Auth::user()->name ?? 'System';
            event(new UserPermissionsUpdated($user->id, $adminName, 'Permissions updated via permission manager'));
        } catch (\Exception $broadcastException) {
            Log::warning('Failed to broadcast permission update event', [
                'user_id' => $user->id,
                'error' => $broadcastException->getMessage(),
            ]);
        }

        return [
            'user' => $user->fresh()->load('permissions'),
            'permissions_count' => count($permissions),
        ];
    }

    /**
     * Get permissions summary for a user.
     */
    public function summary(User $user): array
    {
        $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();
        $modules = Module::active()->get();

        $totalModules = $modules->count();
        $readOnlyCount = 0;
        $fullAccessCount = 0;
        $partialAccessCount = 0;
        $noAccessCount = 0;

        foreach ($modules as $module) {
            $hasRead = in_array($module->read_permission, $userPermissions);
            $hasCreate = in_array("{$module->name}.create", $userPermissions);
            $hasUpdate = in_array("{$module->name}.update", $userPermissions);
            $hasDelete = in_array("{$module->name}.delete", $userPermissions);

            $hasAnyWrite = $hasCreate || $hasUpdate || $hasDelete;
            $hasAllWrite = $hasCreate && $hasUpdate && $hasDelete;

            if (! $hasRead && ! $hasAnyWrite) {
                $noAccessCount++;
            } elseif ($hasRead && $hasAllWrite) {
                $fullAccessCount++;
            } elseif ($hasRead && ! $hasAnyWrite) {
                $readOnlyCount++;
            } else {
                $partialAccessCount++;
            }
        }

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'summary' => [
                'total_modules' => $totalModules,
                'full_access' => $fullAccessCount,
                'partial_access' => $partialAccessCount,
                'read_only' => $readOnlyCount,
                'no_access' => $noAccessCount,
                'total_permissions' => count($userPermissions),
            ],
        ];
    }
}
