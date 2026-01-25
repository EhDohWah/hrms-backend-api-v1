<?php

namespace App\Http\Controllers\Api;

use App\Events\UserProfileUpdated;
use App\Http\Controllers\Controller;
use App\Models\Module;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

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
 */
class UserController extends Controller
{
    /**
     * Update user profile picture.
     */
    #[OA\Post(
        path: '/user/profile-picture',
        summary: 'Update user profile picture',
        security: [['bearerAuth' => []]],
        tags: ['Users']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['profile_picture'],
                properties: [
                    new OA\Property(property: 'profile_picture', type: 'string', format: 'binary', description: 'Profile picture file'),
                ]
            )
        )
    )]
    #[OA\Response(response: 200, description: 'Profile picture updated successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
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

        $fullUrl = Storage::disk('public')->url($path);

        // Broadcast profile update event for real-time UI updates
        event(new UserProfileUpdated($user->id, 'profile_picture', [
            'profile_picture' => $path,
            'profile_picture_url' => $fullUrl,
        ]));

        Log::info('Profile picture updated', ['user_id' => $user->id, 'path' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Profile picture updated successfully',
            'data' => [
                'profile_picture' => $path,
                'url' => $fullUrl,
            ],
        ], 200);
    }

    /**
     * Update user name.
     */
    #[OA\Post(
        path: '/user/username',
        summary: 'Update user name',
        security: [['bearerAuth' => []]],
        tags: ['Users']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Username updated successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function updateUsername(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        try {
            $user = Auth::user();
            $oldName = $user->name;
            $user->name = $request->name;
            $user->save();

            // Broadcast profile update event for real-time UI updates
            event(new UserProfileUpdated($user->id, 'name', [
                'name' => $user->name,
                'old_name' => $oldName,
            ]));

            Log::info('Username updated', ['user_id' => $user->id, 'old_name' => $oldName, 'new_name' => $user->name]);

            return response()->json([
                'success' => true,
                'message' => 'Username updated successfully',
                'data' => [
                    'name' => $user->name,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Username update failed', ['user_id' => Auth::id(), 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update username: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update user email.
     */
    #[OA\Post(
        path: '/user/email',
        summary: 'Update user email',
        security: [['bearerAuth' => []]],
        tags: ['Users']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email'],
            properties: [
                new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Email updated successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
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
            $oldEmail = $user->email;
            $user->email = $request->email;
            $user->save();

            // Broadcast profile update event for real-time UI updates
            event(new UserProfileUpdated($user->id, 'email', [
                'email' => $user->email,
            ]));

            Log::info('Email updated', ['user_id' => $user->id, 'old_email' => $oldEmail, 'new_email' => $user->email]);

            return response()->json([
                'success' => true,
                'message' => 'Email updated successfully',
                'data' => [
                    'email' => $user->email,
                ],
            ], 200);
        } catch (\Exception $e) {
            Log::error('Email update failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update email: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update user password.
     */
    #[OA\Post(
        path: '/user/password',
        summary: 'Update user password',
        security: [['bearerAuth' => []]],
        tags: ['Users']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['current_password', 'new_password', 'confirm_password'],
            properties: [
                new OA\Property(property: 'current_password', type: 'string', format: 'password', example: 'current123'),
                new OA\Property(property: 'new_password', type: 'string', format: 'password', example: 'new123'),
                new OA\Property(property: 'confirm_password', type: 'string', format: 'password', example: 'new123'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Password updated successfully')]
    #[OA\Response(response: 400, description: 'Current password is incorrect')]
    #[OA\Response(response: 422, description: 'Validation error')]
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

            // Broadcast profile update event (no sensitive data)
            event(new UserProfileUpdated($user->id, 'password', [
                'password_changed_at' => now()->toIso8601String(),
            ]));

            Log::info('Password updated', ['user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Password update failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update password: '.$e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the authenticated user with roles and permissions.
     */
    #[OA\Get(
        path: '/user/user',
        summary: 'Get authenticated user details',
        description: "Returns the authenticated user's basic details along with their roles and permissions.",
        operationId: 'me',
        security: [['bearerAuth' => []]],
        tags: ['Users']
    )]
    #[OA\Response(response: 200, description: 'User details retrieved successfully')]
    #[OA\Response(response: 401, description: 'Unauthenticated')]
    public function me(Request $request)
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
     */
    #[OA\Get(
        path: '/api/v1/me/permissions',
        summary: "Get current user's module permissions",
        description: "Retrieve current user's permissions organized by module with Read/Edit flags",
        operationId: 'myPermissions',
        security: [['bearerAuth' => []]],
        tags: ['Users']
    )]
    #[OA\Response(response: 200, description: 'Permissions retrieved successfully')]
    #[OA\Response(response: 401, description: 'Unauthenticated')]
    public function myPermissions(Request $request): JsonResponse
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
