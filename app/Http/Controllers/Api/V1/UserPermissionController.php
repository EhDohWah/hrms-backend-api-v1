<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\UserPermission\UpdateUserPermissionsRequest;
use App\Models\User;
use App\Services\UserPermissionService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Handles simplified permission management with Read/Edit checkbox logic.
 */
#[OA\Tag(name: 'User Permissions', description: 'API Endpoints for User Permission management')]
class UserPermissionController extends BaseApiController
{
    public function __construct(
        private readonly UserPermissionService $userPermissionService,
    ) {}

    #[OA\Get(
        path: '/admin/user-permissions/{user}',
        summary: 'Get user permissions grouped by module with Read/Edit flags',
        operationId: 'getUserPermissions',
        security: [['bearerAuth' => []]],
        tags: ['User Permissions'],
        parameters: [
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function show(User $user): JsonResponse
    {
        $data = $this->userPermissionService->show($user);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    #[OA\Put(
        path: '/admin/user-permissions/{user}',
        summary: 'Update user permissions based on Read/Edit checkboxes',
        operationId: 'updateUserPermissions',
        security: [['bearerAuth' => []]],
        tags: ['User Permissions'],
        parameters: [
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(
            required: ['modules'],
            properties: [
                new OA\Property(property: 'modules', type: 'object'),
            ]
        )),
        responses: [
            new OA\Response(response: 200, description: 'Permissions updated successfully'),
            new OA\Response(response: 403, description: 'Cannot modify admin permissions'),
            new OA\Response(response: 404, description: 'User not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateUserPermissions(UpdateUserPermissionsRequest $request, User $user): JsonResponse
    {
        $data = $this->userPermissionService->updatePermissions($user, $request->validated()['modules']);

        return response()->json([
            'success' => true,
            'message' => 'User permissions updated successfully',
            'data' => $data,
        ]);
    }

    #[OA\Get(
        path: '/admin/user-permissions/{user}/summary',
        summary: 'Get permissions summary for a user',
        operationId: 'getUserPermissionsSummary',
        security: [['bearerAuth' => []]],
        tags: ['User Permissions'],
        parameters: [
            new OA\Parameter(name: 'user', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'User not found'),
        ]
    )]
    public function summary(User $user): JsonResponse
    {
        $data = $this->userPermissionService->summary($user);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }
}
