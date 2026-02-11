<?php

namespace App\Http\Controllers\Api;

use App\Events\UserPermissionsUpdated;
use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

/**
 * UserPermissionController
 *
 * Handles simplified permission management with Read/Edit checkbox logic.
 * Admin and HR Manager can easily manage user permissions per module.
 */
class UserPermissionController extends Controller
{
    /**
     * Get user permissions grouped by module with Read/Edit flags.
     */
    #[OA\Get(
        path: '/api/v1/admin/user-permissions/{userId}',
        summary: 'Get user module permissions',
        description: 'Retrieve user permissions organized by module with Read/Edit flags',
        operationId: 'showUserPermissions',
        security: [['bearerAuth' => []]],
        tags: ['User Permissions']
    )]
    #[OA\Parameter(name: 'userId', in: 'path', required: true, description: 'User ID', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Permissions retrieved successfully')]
    #[OA\Response(response: 404, description: 'User not found')]
    #[OA\Response(response: 401, description: 'Unauthenticated')]
    public function show(int $userId): JsonResponse
    {
        // Find user with permissions
        $user = User::with('roles')->findOrFail($userId);
        $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();

        // Get all active modules
        $modules = Module::active()->ordered()->get();

        $permissionsByModule = [];

        foreach ($modules as $module) {
            // Check if user has read permission
            $hasRead = in_array($module->read_permission, $userPermissions);

            // Check if user has edit permission (simplified from array to single permission)
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

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name'),
                ],
                'modules' => $permissionsByModule,
            ],
        ]);
    }

    /**
     * Update user permissions based on Read/Edit checkboxes.
     */
    #[OA\Put(
        path: '/api/v1/admin/user-permissions/{userId}',
        summary: 'Update user module permissions',
        description: 'Update user permissions using Read/Edit checkboxes per module',
        operationId: 'updateUserPermissions',
        security: [['bearerAuth' => []]],
        tags: ['User Permissions']
    )]
    #[OA\Parameter(name: 'userId', in: 'path', required: true, description: 'User ID', schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'modules', type: 'object')]))]
    #[OA\Response(response: 200, description: 'Permissions updated successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
    #[OA\Response(response: 404, description: 'User not found')]
    public function updateUserPermissions(Request $request, int $userId): JsonResponse
    {
        // Validate request
        $validated = $request->validate([
            'modules' => 'required|array',
            'modules.*.read' => 'required|boolean',
            'modules.*.edit' => 'required|boolean',
        ]);

        // Find user
        $user = User::findOrFail($userId);

        // Guard: only admins can modify other admin users' permissions
        if ($user->hasRole('admin') && ! auth()->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to modify an administrator\'s permissions.',
            ], 403);
        }

        // Get all modules indexed by name
        $modules = Module::active()->get()->keyBy('name');

        $permissions = [];

        foreach ($request->modules as $moduleName => $access) {
            $module = $modules->get($moduleName);

            if (! $module) {
                Log::warning("Module not found: {$moduleName}");

                continue;
            }

            // Add read permission if checked
            if ($access['read']) {
                $permissions[] = $module->read_permission;
            }

            // Add edit permission if checked (simplified to single permission)
            if ($access['edit']) {
                $permissions[] = "{$moduleName}.edit";
            }
        }

        // Remove duplicates and sync permissions
        $permissions = array_unique($permissions);

        DB::beginTransaction();
        try {
            $user->syncPermissions($permissions);

            Log::info('User permissions updated', [
                'user_id' => $userId,
                'updated_by' => auth()->id(),
                'permissions_count' => count($permissions),
            ]);

            DB::commit();

            // Broadcast permission update event for real-time frontend sync
            $adminName = auth()->user()->name ?? 'System';
            event(new UserPermissionsUpdated($userId, $adminName, 'Permissions updated via permission manager'));

            return response()->json([
                'success' => true,
                'message' => 'User permissions updated successfully',
                'data' => [
                    'user' => $user->fresh()->load('permissions'),
                    'permissions_count' => count($permissions),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error updating user permissions', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error updating user permissions',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get permissions summary for a user.
     */
    #[OA\Get(
        path: '/api/v1/admin/user-permissions/{userId}/summary',
        summary: 'Get user permissions summary',
        description: "Get a summary of user's permission status",
        operationId: 'getUserPermissionsSummary',
        security: [['bearerAuth' => []]],
        tags: ['User Permissions']
    )]
    #[OA\Parameter(name: 'userId', in: 'path', required: true, description: 'User ID', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Summary retrieved successfully')]
    public function summary(int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
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

        return response()->json([
            'success' => true,
            'data' => [
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
            ],
        ]);
    }
}
