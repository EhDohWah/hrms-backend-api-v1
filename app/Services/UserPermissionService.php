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
            $hasEdit = in_array("{$module->name}.edit", $userPermissions);

            $permissionsByModule[$module->name] = [
                'read' => $hasRead,
                'edit' => $hasEdit,
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

            if ($access['read']) {
                $permissions[] = $module->read_permission;
            }

            if ($access['edit']) {
                $permissions[] = "{$moduleName}.edit";
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
        $noAccessCount = 0;

        foreach ($modules as $module) {
            $hasRead = in_array($module->read_permission, $userPermissions);
            $hasEdit = in_array("{$module->name}.edit", $userPermissions);

            if (! $hasRead && ! $hasEdit) {
                $noAccessCount++;
            } elseif ($hasRead && ! $hasEdit) {
                $readOnlyCount++;
            } elseif ($hasRead && $hasEdit) {
                $fullAccessCount++;
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
                'read_only' => $readOnlyCount,
                'no_access' => $noAccessCount,
                'total_permissions' => count($userPermissions),
            ],
        ];
    }
}
