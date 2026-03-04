<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\User\UpdateEmailRequest;
use App\Http\Requests\User\UpdatePasswordRequest;
use App\Http\Requests\User\UpdateProfilePictureRequest;
use App\Http\Requests\User\UpdateUsernameRequest;
use App\Http\Resources\UserResource;
use App\Services\UserProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

/**
 * Handles authenticated user's own profile operations.
 */
#[OA\Tag(name: 'User Profile', description: 'API Endpoints for managing the authenticated user\'s own profile')]
class UserController extends BaseApiController
{
    public function __construct(
        private readonly UserProfileService $userProfileService,
    ) {}

    /**
     * Get the authenticated user with roles and permissions.
     */
    #[OA\Get(
        path: '/user',
        summary: 'Get authenticated user profile',
        tags: ['User Profile'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'User retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function me(): JsonResponse
    {
        $user = Auth::user();
        $user->load('roles', 'permissions');

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => UserResource::make($user),
        ]);
    }

    /**
     * Get current user's permissions in simplified read/edit format.
     */
    #[OA\Get(
        path: '/me/permissions',
        summary: 'Get current user permissions',
        tags: ['User Profile'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Permissions retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function myPermissions(): JsonResponse
    {
        $permissions = $this->userProfileService->myPermissions(Auth::user());

        return response()->json([
            'success' => true,
            'message' => 'Permissions retrieved successfully',
            'data' => $permissions,
        ]);
    }

    /**
     * Update user profile picture.
     */
    #[OA\Post(
        path: '/user/profile-picture',
        summary: 'Update profile picture',
        tags: ['User Profile'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['profile_picture'],
                    properties: [
                        new OA\Property(property: 'profile_picture', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profile picture updated successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateProfilePicture(UpdateProfilePictureRequest $request): JsonResponse
    {
        $data = $this->userProfileService->updateProfilePicture($request->file('profile_picture'));

        return response()->json([
            'success' => true,
            'message' => 'Profile picture updated successfully',
            'data' => $data,
        ]);
    }

    /**
     * Update user name.
     */
    #[OA\Post(
        path: '/user/username',
        summary: 'Update username',
        tags: ['User Profile'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Username updated successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateUsername(UpdateUsernameRequest $request): JsonResponse
    {
        $data = $this->userProfileService->updateUsername($request->validated()['name']);

        return response()->json([
            'success' => true,
            'message' => 'Username updated successfully',
            'data' => $data,
        ]);
    }

    /**
     * Update user email.
     */
    #[OA\Post(
        path: '/user/email',
        summary: 'Update email address',
        tags: ['User Profile'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Email updated successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateEmail(UpdateEmailRequest $request): JsonResponse
    {
        $data = $this->userProfileService->updateEmail($request->validated()['email']);

        return response()->json([
            'success' => true,
            'message' => 'Email updated successfully',
            'data' => $data,
        ]);
    }

    /**
     * Update user password.
     */
    #[OA\Post(
        path: '/user/password',
        summary: 'Update password',
        tags: ['User Profile'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['current_password', 'new_password', 'confirm_password'],
                properties: [
                    new OA\Property(property: 'current_password', type: 'string'),
                    new OA\Property(property: 'new_password', type: 'string'),
                    new OA\Property(property: 'confirm_password', type: 'string'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Password updated successfully'),
            new OA\Response(response: 400, description: 'Current password is incorrect'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->userProfileService->updatePassword($validated['current_password'], $validated['new_password']);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully',
        ]);
    }
}
