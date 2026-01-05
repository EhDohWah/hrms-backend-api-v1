<?php

namespace App\Http\Controllers\Api;

use App\Events\UserPermissionsUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\Module;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * @OA\Tag(
 *     name="Admin",
 *     description="Admin API Endpoints"
 * )
 */
class AdminController extends Controller
{
    /***
     *
     * User section
     *
     */

    /**
     * Display a listing of users.
     *
     * @OA\Get(
     *     path="/admin/users",
     *     summary="Get list of users",
     *     description="Returns a list of all users with their roles and permissions",
     *     operationId="indexUsers",
     *     tags={"Admin"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="array",
     *
     *             @OA\Items(
     *
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *                 @OA\Property(property="last_login_at", type="string", format="date-time"),
     *                 @OA\Property(
     *                     property="roles",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="Admin")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="permissions",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="user.read")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Forbidden")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        // Pagination parameters
        $perPage = $request->input('per_page', 15);
        $page = $request->input('page', 1);

        // Search parameter
        $search = $request->input('search');

        // Filter parameters
        $role = $request->input('role');
        $status = $request->input('status');

        // Sorting parameters
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        // Validate sort order
        $sortOrder = in_array(strtolower($sortOrder), ['asc', 'desc']) ? strtolower($sortOrder) : 'desc';

        // Build query
        $query = User::with(['roles', 'permissions']);

        // Apply search
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filter by role
        if ($role) {
            $query->whereHas('roles', function ($q) use ($role) {
                $q->where('name', $role);
            });
        }

        // Filter by status
        if ($status) {
            $query->where('status', $status);
        }

        // Apply sorting
        $allowedSortFields = ['name', 'email', 'status', 'created_at', 'updated_at', 'last_login_at'];
        if (in_array($sortBy, $allowedSortFields)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Paginate results
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => UserResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
                'from' => $users->firstItem(),
                'to' => $users->lastItem(),
            ],
            'links' => [
                'first' => $users->url(1),
                'last' => $users->url($users->lastPage()),
                'prev' => $users->previousPageUrl(),
                'next' => $users->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Store a newly created user in storage.
     *
     * @OA\Post(
     *     path="/admin/users",
     *     summary="Create new user",
     *     tags={"Admin"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *                 required={"name","email","password","password_confirmation","role"},
     *
     *                 @OA\Property(
     *                     property="name",
     *                     type="string",
     *                     example="John Doe"
     *                 ),
     *                 @OA\Property(
     *                     property="email",
     *                     type="string",
     *                     format="email",
     *                     example="john@example.com"
     *                 ),
     *                 @OA\Property(
     *                     property="password",
     *                     type="string",
     *                     format="password",
     *                     example="password123"
     *                 ),
     *                 @OA\Property(
     *                     property="password_confirmation",
     *                     type="string",
     *                     format="password",
     *                     example="password123"
     *                 ),
     *                 @OA\Property(
     *                     property="role",
     *                     type="string",
     *                     example="employee",
     *                     enum={"admin", "hr-manager", "hr-assistant", "employee"}
     *                 ),
     *                 @OA\Property(
     *                     property="permissions",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="user.read"),
     *                     description="Array of permission strings"
     *                 ),
     *
     *                 @OA\Property(
     *                     property="profile_picture",
     *                     type="string",
     *                     format="binary",
     *                     description="Optional profile picture file"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="User created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="user",
     *                     type="object",
     *                     ref="#/components/schemas/User"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error creating user"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="error", type="string")
     *             )
     *         )
     *     )
     * )
     *
     * Expected payload:
     * - name (string)               : Full name
     * - email (string)              : User's email address
     * - password (string)           : Password (must match password_confirmation)
     * - password_confirmation (string)
     * - role (string)               : Role (e.g. admin, hr-manager, hr-assistant, employee)
     * - permissions (array, optional): Array of permission strings
     *   Example: ["grant.read", "employee.read", "employment.read"]
     * - profile_picture (file, optional): User's profile picture image
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(StoreUserRequest $request)
    {
        // Validation is handled by StoreUserRequest
        $validated = $request->validated();

        // Start a database transaction.
        DB::beginTransaction();

        try {
            // If a profile picture file is provided, store it on the public disk.
            if ($request->hasFile('profile_picture')) {
                $path = $request->file('profile_picture')->store('profile_pictures', 'public');
                $validated['profile_picture'] = $path;
            }

            // Create the user record with hashed password.
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'profile_picture' => $validated['profile_picture'] ?? null,
                'created_by' => auth()->user()->id,
            ]);

            // Assign role using Spatie's role package.
            $user->assignRole($validated['role']);

            // Handle permissions based on role and format provided
            // Admin and HR Manager get full access to all modules
            if (in_array($validated['role'], ['admin', 'hr-manager'])) {
                $allModulePermissions = $this->getAllModulePermissions();
                $user->syncPermissions($allModulePermissions);
                Log::info("Auto-assigned all module permissions to user {$user->id} with role {$validated['role']}");
            } else {
                // For other roles, use the permissions from the request
                $permissions = $this->extractPermissions($validated);
                if (! empty($permissions)) {
                    $user->syncPermissions($permissions);
                }
            }

            // Commit the transaction if all is well.
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => new UserResource($user->load('roles', 'permissions')),
            ], 201);
        } catch (\Exception $e) {
            // Rollback the transaction if something goes wrong.
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error creating user',
                'data' => [
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Display the specified user.
     *
     * @OA\Get(
     *     path="/admin/users/{id}",
     *     summary="Get single user by ID",
     *     tags={"Admin"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="User retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function show($id)
    {
        $user = User::with(['roles', 'permissions'])->find($id);

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Update a user's roles, permissions and password if provided.
     *
     * @OA\Put(
     *     path="/admin/users/{id}",
     *     summary="Update user roles, permissions and password",
     *     tags={"Admin"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="application/json",
     *
     *             @OA\Schema(
     *                 type="object",
     *
     *                 @OA\Property(
     *                     property="role",
     *                     type="string",
     *                     example="employee",
     *                     enum={"admin", "hr-manager", "hr-assistant", "employee"},
     *                     description="User role"
     *                 ),
     *                 @OA\Property(
     *                     property="permissions",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="user.read"),
     *                     description="Array of permission strings"
     *                 ),
     *
     *                 @OA\Property(
     *                     property="password",
     *                     type="string",
     *                     description="New password (optional)"
     *                 ),
     *                 @OA\Property(
     *                     property="password_confirmation",
     *                     type="string",
     *                     description="Confirm new password"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(UpdateUserRequest $request, $id)
    {
        // Find the user by ID
        $user = User::find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Validation is handled by UpdateUserRequest
        $validated = $request->validated();

        // Start a database transaction
        DB::beginTransaction();

        try {
            // Update user's role if provided
            if (isset($validated['role'])) {
                // Remove current roles
                $user->roles()->detach();
                // Assign new role
                $user->assignRole($validated['role']);

                // Handle permissions based on role
                // Admin and HR Manager get full access to all modules
                if (in_array($validated['role'], ['admin', 'hr-manager'])) {
                    $allModulePermissions = $this->getAllModulePermissions();
                    $user->syncPermissions($allModulePermissions);
                    Log::info("Auto-assigned all module permissions to user {$user->id} with updated role {$validated['role']}");
                } else {
                    // For other roles, use the permissions from the request
                    $permissions = $this->extractPermissions($validated);
                    if (! empty($permissions)) {
                        $user->syncPermissions($permissions);
                    }
                }
            } else {
                // If role is not being updated, just sync permissions normally
                $permissions = $this->extractPermissions($validated);
                if (! empty($permissions)) {
                    $user->syncPermissions($permissions);
                }
            }

            // Update password if provided
            if (isset($validated['password'])) {
                $user->password = Hash::make($validated['password']);
                $user->save();
            }

            // Commit the transaction
            DB::commit();

            // Load the updated roles and permissions
            $user->load('roles', 'permissions');

            // Broadcast permission update event for real-time frontend sync
            // This allows the affected user to refresh their permissions without re-login
            $adminName = auth()->user()->name ?? 'System';
            event(new UserPermissionsUpdated($user->id, $adminName, 'Role or permissions updated by admin'));

            Log::info('UserPermissionsUpdated event dispatched', [
                'target_user_id' => $user->id,
                'updated_by' => $adminName,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => new UserResource($user),
            ]);
        } catch (\Exception $e) {
            // Rollback the transaction if something goes wrong
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error updating user',
                'data' => [
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Delete a user.
     *
     * @OA\Delete(
     *     path="/admin/users/{id}",
     *     summary="Delete user",
     *     tags={"Admin"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="User deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function destroy(Request $request, $id)
    {
        $user = User::find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Start a database transaction
        DB::beginTransaction();

        try {
            // Remove all roles and permissions for this specific user
            // This detaches the roles and permissions from the user in the pivot tables
            $user->roles()->detach();
            $user->permissions()->detach();

            // Delete the user
            $user->delete();

            // Commit the transaction
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User deleted successfully',
                'data' => ['message' => 'User and their role/permission associations deleted successfully'],
            ]);
        } catch (\Exception $e) {
            // Rollback the transaction if something goes wrong
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Error deleting user',
                'data' => [
                    'error' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get all roles.
     *
     * @OA\Get(
     *     path="/admin/roles",
     *     summary="Get all roles",
     *     tags={"Admin"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Roles retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Roles retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="admin"),
     *                     @OA\Property(property="guard_name", type="string", example="web"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getRoles()
    {
        $roles = Role::query()->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Roles retrieved successfully',
            'data' => RoleResource::collection($roles),
        ]);
    }

    /**
     * Get all permissions.
     *
     * @OA\Get(
     *     path="/admin/permissions",
     *     summary="Get all permissions",
     *     tags={"Admin"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Permissions retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Permissions retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="user.read"),
     *                     @OA\Property(property="guard_name", type="string", example="web"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getPermissions()
    {
        $permissions = Permission::query()->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'message' => 'Permissions retrieved successfully',
            'data' => PermissionResource::collection($permissions),
        ]);
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
            // Load all active modules from database
            $modules = Module::active()->get()->keyBy('name');

            foreach ($validated['modules'] as $moduleName => $access) {
                $module = $modules->get($moduleName);

                if (! $module) {
                    Log::warning("Module not found: {$moduleName}");

                    continue;
                }

                // Add read permission if checked
                if (isset($access['read']) && $access['read']) {
                    $desiredPermissions[] = $module->read_permission;
                }

                // Add edit permission if checked
                if (isset($access['edit']) && $access['edit']) {
                    $desiredPermissions[] = "{$moduleName}.edit";
                }
            }
        }
        // Handle legacy permissions array format (backward compatibility)
        elseif (isset($validated['permissions']) && is_array($validated['permissions'])) {
            $desiredPermissions = $validated['permissions'];
        }

        // Only return permissions that actually exist in the database
        // This prevents "There is no permission named X" errors
        if (empty($desiredPermissions)) {
            return [];
        }

        $existingPermissions = Permission::whereIn('name', $desiredPermissions)
            ->pluck('name')
            ->toArray();

        // Log any missing permissions for debugging
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
     * Get all module permissions (read and edit) for privileged roles.
     *
     * Used for Admin and HR Manager roles who get full access to all modules.
     * Only returns permissions that actually exist in the database.
     *
     * @return array Array of all module permission strings
     */
    private function getAllModulePermissions(): array
    {
        $desiredPermissions = [];
        $modules = Module::active()->get();

        foreach ($modules as $module) {
            // Add read permission
            $desiredPermissions[] = $module->read_permission;
            // Add edit permission
            $desiredPermissions[] = "{$module->name}.edit";
        }

        // Only return permissions that actually exist in the database
        // This prevents "There is no permission named X" errors
        $existingPermissions = Permission::whereIn('name', $desiredPermissions)
            ->pluck('name')
            ->toArray();

        return array_unique($existingPermissions);
    }
}
