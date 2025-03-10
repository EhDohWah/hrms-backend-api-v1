<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Users",
 *     description="API Endpoints for user management"
 * )
 */
class UserController extends Controller
{
    /**
     * Display a listing of users.
     *
     * @OA\Get(
     *     path="/users",
     *     summary="Get list of users",
     *     description="Returns a list of all users with their roles and permissions",
     *     operationId="indexUsers",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *                 @OA\Property(property="last_login_at", type="string", format="date-time"),
     *                 @OA\Property(
     *                     property="roles",
     *                     type="array",
     *                     @OA\Items(type="string", example="Admin")
     *                 ),
     *                 @OA\Property(
     *                     property="permissions",
     *                     type="array",
     *                     @OA\Items(type="string", example="user.read")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Forbidden")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $users = User::all();
        return response()->json($users);
    }

    /**
     * Display a single user.
     *
     * @OA\Get(
     *     path="/users/{id}",
     *     summary="Get user by ID",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="User not found"
     *     )
     * )
     */
    public function show(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->load('permissions', 'roles');
        return response()->json($user);
    }

    /**
     * Update a user.
     *
     * @OA\Put(
     *     path="/users/{id}",
     *     summary="Update existing user",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 type="object",
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
     *                     property="profile_picture",
     *                     type="string",
     *                     format="binary",
     *                     description="Optional profile picture file"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="User updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/User")
     *     ),
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
        $user = User::find($id);
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

         // Validate inputs, including an optional profile_picture file upload
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email,'.$user->id,
            'password'        => 'nullable|string|min:8',
            'profile_picture' => 'nullable|image|max:2048', // image file, max 2MB
        ]);

        // If a profile_picture is provided, store it on the public disk
        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');

            // The file will be stored in storage/app/public/profile_pictures
            // and accessible via public/storage/profile_pictures
            $path = $file->store('profile_pictures', 'public');

            // Update validated data with the path to the stored file
            $validated['profile_picture'] = $path;
        }

        // If a new password is provided, hash it; otherwise, remove it from the update data
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }


        $user->update($validated);

        return response()->json($user);
    }

    /**
     * Delete a user.
     *
     * @OA\Delete(
     *     path="/users/{id}",
     *     summary="Delete user",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="User ID",
     *         @OA\Schema(type="integer")
     *     ),
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
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $user->delete();
        return response()->json(['message' => 'User deleted successfully']);
    }

    /**
     * Create a new user.
     *
     * @OA\Post(
     *     path="/users",
     *     summary="Create new user",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"name","email","password","role"},
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
     *                     property="role",
     *                     type="string",
     *                     example="employee",
     *                     enum={"admin", "hr-manager", "hr-assistant", "employee"}
     *                 ),
     *                 @OA\Property(
     *                     property="profile_picture",
     *                     type="string",
     *                     format="binary",
     *                     description="Optional profile picture file"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="User created successfully"),
     *             @OA\Property(
     *                 property="user",
     *                 type="object",
     *                 ref="#/components/schemas/User"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Error creating user"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        // 1) Validate input
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'email'           => 'required|email|unique:users,email',
            'password'        => 'required|string|min:8',
            'role'            => 'required|string|in:admin,hr-manager,hr-assistant,employee',
            'profile_picture' => 'nullable|image|max:2048', // optional image, max 2MB
        ]);

        // 2) Wrap everything in a transaction for data integrity
        DB::beginTransaction();

        // If a profile picture file is provided, store it on the public disk
        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            // This will store the file in storage/app/public/profile_pictures
            // and return a relative path like "profile_pictures/unique_filename.jpg"
            $path = $file->store('profile_pictures', 'public');
            $validated['profile_picture'] = $path;
        }

        try {
            // Create the user
            $user = User::create([
                'name'            => $validated['name'],
                'email'           => $validated['email'],
                'password'        => Hash::make($validated['password']),
                'profile_picture' => $validated['profile_picture'] ?? null,
                'created_by'      => auth()->user()->name ?? 'system',
                'updated_by'      => auth()->user()->name ?? 'system',
                'last_login_at'   => null,
            ]);

            // Assign the role (make sure the role exists in DB/Spatie)
            $user->assignRole($validated['role']);

            // 3) If custom permissions are provided, sync them. Otherwise use defaults.
            if (!empty($validated['permissions'])) {
                $user->syncPermissions($validated['permissions']);
            } else {
                $this->assignDefaultPermissions($user, $validated['role']);
            }

            DB::commit(); // Commit the transaction

            return response()->json([
                'message' => 'User created successfully',
                'user'    => $user->load('roles', 'permissions'),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack(); // Revert DB changes on error

            return response()->json([
                'message' => 'Error creating user',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update user profile picture.
     *
     * @OA\Post(
     *     path="/profile-picture",
     *     summary="Update user profile picture",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 required={"profile_picture"},
     *                 @OA\Property(
     *                     property="profile_picture",
     *                     type="string",
     *                     format="binary",
     *                     description="Profile picture file"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile picture updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Profile picture updated successfully"),
     *             @OA\Property(property="profile_picture", type="string", example="profile_pictures/image.jpg")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function updateProfilePicture(Request $request)
    {
        $request->validate([
            'profile_picture' => 'required|image|max:2048', // Required image, max 2MB
        ]);

        $user = Auth::user();

        // Delete old profile picture if exists
        if ($user->profile_picture) {
            Storage::disk('public')->delete($user->profile_picture);
        }

        // Store new profile picture
        $path = $request->file('profile_picture')->store('profile_pictures', 'public');

        // Update user record
        $user->profile_picture = $path;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile picture updated successfully',
            'data' => [
                'profile_picture' => $path,
                'url' => Storage::disk('public')->url($path)
            ]
        ], 200);
    }

    /**
     * Update user name.
     *
     * @OA\Put(
     *     path="/username",
     *     summary="Update user name",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="John Doe")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Username updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Username updated successfully"),
     *             @OA\Property(property="name", type="string", example="John Doe")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function updateUsername(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            $user = Auth::user();
            $user->name = $request->name;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Username updated successfully',
                'name' => $user->name
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update username: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user email.
     *
     * @OA\Put(
     *     path="/email",
     *     summary="Update user email",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Email updated successfully"),
     *             @OA\Property(property="email", type="string", example="john@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function updateEmail(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'email' => [
                'required',
                'email',
                Rule::unique('users')->ignore($user->id),
            ],
        ]);

        try {
            $user->email = $request->email;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Email updated successfully',
                'email' => $user->email
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update email: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user password.
     *
     * @OA\Put(
     *     path="/password",
     *     summary="Update user password",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"current_password", "new_password", "confirm_password"},
     *             @OA\Property(property="current_password", type="string", format="password", example="current123"),
     *             @OA\Property(property="new_password", type="string", format="password", example="new123"),
     *             @OA\Property(property="confirm_password", type="string", format="password", example="new123")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Password updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Password updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Current password is incorrect"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8',
            'confirm_password' => 'required|string|same:new_password',
        ]);

        $user = Auth::user();

        // Check if current password is correct
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        try {
            // Update password
            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update password: ' . $e->getMessage()
            ], 500);
        }
    }

     /**
     * Get the authenticated user with roles and permissions.
     *
     * @OA\Get(
     *     path="/user",
     *     summary="Get authenticated user details",
     *     description="Returns the authenticated user's basic details along with their roles and permissions.",
     *     operationId="getUser",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User details retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             required={"id", "name", "email", "roles", "permissions"},
     *             @OA\Property(
     *                 property="id",
     *                 type="integer",
     *                 example=1,
     *                 description="Unique identifier for the user"
     *             ),
     *             @OA\Property(
     *                 property="name",
     *                 type="string",
     *                 example="John Doe",
     *                 description="Full name of the user"
     *             ),
     *             @OA\Property(
     *                 property="email",
     *                 type="string",
     *                 format="email",
     *                 example="john@example.com",
     *                 description="Email address of the user"
     *             ),
     *             @OA\Property(
     *                 property="last_login_at",
     *                 type="string",
     *                 format="date-time",
     *                 nullable=true,
     *                 description="The date and time when the user last logged in"
     *             ),
     *             @OA\Property(
     *                 property="roles",
     *                 type="array",
     *                 description="List of roles assigned to the user",
     *                 @OA\Items(
     *                     type="string",
     *                     example="Admin",
     *                     description="A role name"
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="permissions",
     *                 type="array",
     *                 description="List of permissions granted to the user",
     *                 @OA\Items(
     *                     type="string",
     *                     example="user.read",
     *                     description="A permission identifier"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - The request does not have valid authentication credentials",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(
     *                 property="message",
     *                 type="string",
     *                 example="Unauthenticated"
     *             )
     *         )
     *     )
     * )
     */
    public function getUser(Request $request)
    {
        $user = $request->user();
        $user->load('roles', 'permissions');

        return response()->json($user);
    }


    /**
     * Assigns default permissions to a user based on their role.
     *
     * @param  \App\Models\User  $user
     * @param  string            $role
     */
    private function assignDefaultPermissions(User $user, string $role)
    {
        switch ($role) {
            case 'employee':
                $user->syncPermissions([
                    'user.read',
                    'user.update',
                    'attendance.create',
                    'attendance.read',
                    'travel_request.create',
                    'travel_request.read',
                    'leave_request.create',
                    'leave_request.read',
                ]);
                break;

            case 'admin':
            case 'hr-manager':
            case 'hr-assistant':
                // Assign all permissions
                $user->syncPermissions(Permission::all());
                break;
        }
    }
}
