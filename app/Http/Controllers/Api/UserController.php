<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * UserController - Handles authenticated user's own profile operations.
 *
 * This controller is for users managing their OWN profile data:
 * - Profile picture upload
 * - Username update
 * - Email update
 * - Password change
 * - Get current user info
 * - Get current user's module permissions
 *
 * Note: Admin operations (managing OTHER users) are in AdminController.
 *
 * @OA\Tag(
 *     name="Users",
 *     description="API Endpoints for authenticated user profile management"
 * )
 */
class UserController extends Controller
{
    /**
     * Update user profile picture.
     *
     * @OA\Post(
     *     path="/user/profile-picture",
     *     summary="Update user profile picture",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *                 required={"profile_picture"},
     *
     *                 @OA\Property(
     *                     property="profile_picture",
     *                     type="string",
     *                     format="binary",
     *                     description="Profile picture file"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Profile picture updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Profile picture updated successfully"),
     *             @OA\Property(property="profile_picture", type="string", example="profile_pictures/image.jpg")
     *         )
     *     ),
     *
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
                'url' => Storage::disk('public')->url($path),
            ],
        ], 200);
    }

    /**
     * Update user name.
     *
     * @OA\Post(
     *     path="/user/username",
     *     summary="Update user name",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name"},
     *
     *             @OA\Property(property="name", type="string", example="John Doe")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Username updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Username updated successfully"),
     *             @OA\Property(property="name", type="string", example="John Doe")
     *         )
     *     ),
     *
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
                'name' => $user->name,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update username: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update user email.
     *
     * @OA\Post(
     *     path="/user/email",
     *     summary="Update user email",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"email"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Email updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Email updated successfully"),
     *             @OA\Property(property="email", type="string", example="john@example.com")
     *         )
     *     ),
     *
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
                'email' => $user->email,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update email: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update user password.
     *
     * @OA\Post(
     *     path="/user/password",
     *     summary="Update user password",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"current_password", "new_password", "confirm_password"},
     *
     *             @OA\Property(property="current_password", type="string", format="password", example="current123"),
     *             @OA\Property(property="new_password", type="string", format="password", example="new123"),
     *             @OA\Property(property="confirm_password", type="string", format="password", example="new123")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Password updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="message", type="string", example="Password updated successfully")
     *         )
     *     ),
     *
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
            'new_password' => 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
            'confirm_password' => 'required|string|same:new_password',
        ], [
            'new_password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
        ]);

        $user = Auth::user();

        // Check if current password is correct
        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 400);
        }

        try {
            // Update password
            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update password: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the authenticated user with roles and permissions.
     *
     * @OA\Get(
     *     path="/user/user",
     *     summary="Get authenticated user details",
     *     description="Returns the authenticated user's basic details along with their roles and permissions.",
     *     operationId="getUser",
     *     tags={"Users"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="User details retrieved successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *             required={"id", "name", "email", "roles", "permissions"},
     *
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
     *
     *                 @OA\Items(
     *                     type="string",
     *                     example="Admin",
     *                     description="A role name"
     *                 )
     *             ),
     *
     *             @OA\Property(
     *                 property="permissions",
     *                 type="array",
     *                 description="List of permissions granted to the user",
     *
     *                 @OA\Items(
     *                     type="string",
     *                     example="user.read",
     *                     description="A permission identifier"
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - The request does not have valid authentication credentials",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
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
     * Get current user's permissions in simplified read/edit format.
     *
     * Returns permissions organized by module with read and edit flags.
     * Only includes modules the user has access to.
     *
     * @OA\Get(
     *     path="/api/v1/me/permissions",
     *     summary="Get current user's module permissions",
     *     description="Retrieve current user's permissions organized by module with Read/Edit flags",
     *     operationId="getMyPermissions",
     *     tags={"Users"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Permissions retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user_management", type="object",
     *                     @OA\Property(property="read", type="boolean", example=true),
     *                     @OA\Property(property="edit", type="boolean", example=true),
     *                     @OA\Property(property="display_name", type="string", example="User Management"),
     *                     @OA\Property(property="category", type="string", example="Administration"),
     *                     @OA\Property(property="icon", type="string", example="users"),
     *                     @OA\Property(property="route", type="string", example="/user-management/users")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function getMyPermissions(Request $request): JsonResponse
    {
        $user = $request->user();
        $modules = Module::active()->ordered()->get();

        $permissions = [];

        foreach ($modules as $module) {
            $hasRead = $user->can($module->read_permission);

            // Extract permission prefix from read_permission (e.g., 'admin.read' => 'admin')
            $permissionPrefix = str_replace('.read', '', $module->read_permission);
            $hasEdit = $user->can("{$permissionPrefix}.edit");

            // Only include modules the user has access to
            if ($hasRead || $hasEdit) {
                $permissions[$module->name] = [
                    'read' => $hasRead,
                    'edit' => $hasEdit,
                    'display_name' => $module->display_name,
                    'category' => $module->category,
                    'icon' => $module->icon,
                    'route' => $module->route,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'data' => $permissions,
        ]);
    }
}
