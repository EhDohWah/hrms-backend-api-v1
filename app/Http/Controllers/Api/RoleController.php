<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Role Management Controller
 *
 * Handles CRUD operations for organizational roles.
 * Protected system roles (admin, hr-manager) cannot be modified or deleted.
 */
class RoleController extends Controller
{
    /**
     * List all roles with user counts
     */
    public function index(Request $request)
    {
        $roles = Role::query()
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Roles retrieved successfully',
            'data' => RoleResource::collection($roles),
        ]);
    }

    /**
     * Get single role with details
     */
    public function show($id)
    {
        $role = Role::withCount('users')->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Role retrieved successfully',
            'data' => new RoleResource($role),
        ]);
    }

    /**
     * Create new organizational role
     */
    public function store(StoreRoleRequest $request)
    {
        $validated = $request->validated();

        DB::beginTransaction();

        try {
            $role = Role::create([
                'name' => $validated['name'],
                'guard_name' => 'web',
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully',
                'data' => new RoleResource($role),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error creating role',
                'data' => ['error' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Update role name
     */
    public function update(UpdateRoleRequest $request, $id)
    {
        $role = Role::findOrFail($id);
        $validated = $request->validated();

        // Prevent editing protected system roles
        if (in_array($role->name, ['admin', 'hr-manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot modify protected system role',
            ], 403);
        }

        DB::beginTransaction();

        try {
            $role->update([
                'name' => $validated['name'],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role updated successfully',
                'data' => new RoleResource($role),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error updating role',
                'data' => ['error' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Delete role (with safety checks)
     */
    public function destroy($id)
    {
        $role = Role::findOrFail($id);

        // Prevent deleting protected system roles
        if (in_array($role->name, ['admin', 'hr-manager'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete protected system role',
            ], 403);
        }

        // Count users with this role using direct DB query to avoid Spatie relationship issues
        $usersCount = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->count();

        // Prevent deleting roles with users
        if ($usersCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete role. {$usersCount} user(s) are assigned to this role. Please reassign users before deleting.",
            ], 422);
        }

        DB::beginTransaction();

        try {
            $role->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Role deleted successfully',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error deleting role',
                'data' => ['error' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * Get list of roles for dropdown (simple format)
     */
    public function options()
    {
        $roles = Role::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(function ($role) {
                return [
                    'value' => $role->name,
                    'label' => $this->getDisplayName($role->name),
                    'is_protected' => in_array($role->name, ['admin', 'hr-manager']),
                ];
            });

        return response()->json([
            'success' => true,
            'message' => 'Role options retrieved successfully',
            'data' => $roles,
        ]);
    }

    /**
     * Convert role slug to display name
     */
    private function getDisplayName(string $name): string
    {
        return match ($name) {
            'admin' => 'System Administrator',
            'hr-manager' => 'HR Manager',
            'hr-assistant-senior' => 'Senior HR Assistant',
            'hr-assistant' => 'HR Assistant',
            'hr-assistant-junior-senior' => 'Senior HR Junior Assistant',
            'hr-assistant-junior' => 'HR Junior Assistant',
            default => ucwords(str_replace('-', ' ', $name)),
        };
    }
}
