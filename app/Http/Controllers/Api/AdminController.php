<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
        $users = User::with(['roles', 'permissions'])->get();

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => $users,
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
    public function store(Request $request)
    {
        // Check if email already exists
        if (User::where('email', $request->email)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Email already exists in the database',
            ], 422);
        }

        // Check if name already exists
        if (User::where('name', $request->name)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Name already exists in the database',
            ], 422);
        }

        // If "permissions" is provided as a comma-separated string, convert it to an array.
        if ($request->has('permissions') && ! is_array($request->input('permissions'))) {
            $permissionsString = $request->input('permissions');
            // Explode the string by comma and trim each permission.
            $permissionsArray = array_map('trim', explode(',', $permissionsString));
            $request->merge(['permissions' => $permissionsArray]);
        }

        // Validate incoming request data.
        $validationRules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
            'password_confirmation' => 'required|string',
            'role' => 'required|string|in:admin,hr-manager,hr-assistant,employee',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
            'profile_picture' => 'nullable|image|max:2048', // 2MB max file size
        ];

        // Add custom error message for regex validation
        $validated = $request->validate($validationRules, [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
        ]);

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

            // If permissions are provided as an array, sync them.
            if (isset($validated['permissions']) && is_array($validated['permissions'])) {
                $user->syncPermissions($validated['permissions']);
            } else {
                // Optionally, assign default permissions based on the role.
                // $this->assignDefaultPermissions($user, $validated['role']);
            }

            // Commit the transaction if all is well.
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User created successfully',
                'data' => [
                    'user' => $user->load('roles', 'permissions'),
                ],
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
    public function update(Request $request, $id)
    {
        // Find the user by ID
        $user = User::find($id);
        if (! $user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Validate inputs for role, permissions and password
        $validationRules = [
            'role' => 'nullable|string|in:admin,hr-manager,hr-assistant,employee',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ];

        // Add password validation rules if password is provided
        if ($request->filled('password')) {
            $validationRules['password'] = 'required|string|min:8|confirmed';
            $validationRules['password_confirmation'] = 'required|string';

            // Additional password strength requirements
            $validationRules['password'] .= '|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/';

            // Add custom error message for regex validation
            $request->validate($validationRules, [
                'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            ]);
        }

        $validated = $request->validate($validationRules);

        // Start a database transaction
        DB::beginTransaction();

        try {
            // Update user's role if provided
            if (isset($validated['role'])) {
                // Remove current roles
                $user->roles()->detach();
                // Assign new role
                $user->assignRole($validated['role']);
            }

            // Update user's permissions if provided
            if (isset($validated['permissions']) && is_array($validated['permissions'])) {
                $user->syncPermissions($validated['permissions']);
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

            return response()->json([
                'success' => true,
                'message' => 'User updated successfully',
                'data' => $user,
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
}
