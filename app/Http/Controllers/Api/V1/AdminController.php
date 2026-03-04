<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\IndexUserRequest;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\PermissionResource;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Manages admin operations for users, roles, and permissions.
 */
class AdminController extends BaseApiController
{
    public function __construct(
        private readonly AdminService $adminService,
    ) {}

    /***
     *
     * User section
     *
     */

    #[OA\Get(
        path: '/admin/users',
        summary: 'Get list of users',
        description: 'Returns a list of all users with their roles and permissions',
        operationId: 'indexUsers',
        security: [['bearerAuth' => []]],
        tags: ['Admin']
    )]
    #[OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'role', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'created_at'))]
    #[OA\Parameter(name: 'sort_order', in: 'query', required: false, schema: new OA\Schema(type: 'string', default: 'desc', enum: ['asc', 'desc']))]
    #[OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15))]
    #[OA\Response(
        response: 200,
        description: 'Successful operation',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                properties: [
                    new OA\Property(property: 'id', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'last_login_at', type: 'string', format: 'date-time'),
                ]
            )
        )
    )]
    #[OA\Response(response: 401, description: 'Unauthenticated')]
    #[OA\Response(response: 403, description: 'Forbidden')]
    public function index(IndexUserRequest $request): JsonResponse
    {
        $paginator = $this->adminService->list($request->validated());

        return UserResource::collection($paginator)
            ->additional(['success' => true, 'message' => 'Users retrieved successfully'])
            ->response();
    }

    #[OA\Post(
        path: '/admin/users',
        summary: 'Create new user',
        security: [['bearerAuth' => []]],
        tags: ['Admin']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\MediaType(
            mediaType: 'multipart/form-data',
            schema: new OA\Schema(
                required: ['name', 'email', 'password', 'password_confirmation', 'role'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
                    new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password123'),
                    new OA\Property(property: 'role', type: 'string', example: 'employee', enum: ['admin', 'hr-manager', 'hr-assistant', 'employee']),
                    new OA\Property(property: 'profile_picture', type: 'string', format: 'binary', description: 'Optional profile picture file'),
                ]
            )
        )
    )]
    #[OA\Response(response: 201, description: 'User created successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->adminService->create($request->validated());

        return UserResource::make($user)
            ->additional(['success' => true, 'message' => 'User created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/admin/users/{id}',
        summary: 'Get single user by ID',
        security: [['bearerAuth' => []]],
        tags: ['Admin']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, description: 'User ID', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'User retrieved successfully')]
    #[OA\Response(response: 404, description: 'User not found')]
    public function show(User $user): JsonResponse
    {
        $user = $this->adminService->show($user);

        return UserResource::make($user)
            ->additional(['success' => true, 'message' => 'User retrieved successfully'])
            ->response();
    }

    #[OA\Put(
        path: '/admin/users/{id}',
        summary: 'Update user roles, permissions and password',
        security: [['bearerAuth' => []]],
        tags: ['Admin']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, description: 'User ID', schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'role', type: 'string', example: 'employee', enum: ['admin', 'hr-manager', 'hr-assistant', 'employee']),
                new OA\Property(property: 'password', type: 'string', description: 'New password (optional)'),
                new OA\Property(property: 'password_confirmation', type: 'string', description: 'Confirm new password'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'User updated successfully')]
    #[OA\Response(response: 404, description: 'User not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $user = $this->adminService->update($user, $request->validated());

        return UserResource::make($user)
            ->additional(['success' => true, 'message' => 'User updated successfully'])
            ->response();
    }

    #[OA\Delete(
        path: '/admin/users/{id}',
        summary: 'Delete user',
        security: [['bearerAuth' => []]],
        tags: ['Admin']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, description: 'User ID', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'User deleted successfully')]
    #[OA\Response(response: 404, description: 'User not found')]
    public function destroy(User $user): JsonResponse
    {
        $this->adminService->delete($user);

        return $this->successResponse(null, 'User deleted successfully');
    }

    /**
     * Legacy role/permission list endpoints.
     */
    #[OA\Get(
        path: '/admin/all-roles',
        summary: 'Get all roles',
        security: [['bearerAuth' => []]],
        tags: ['Admin']
    )]
    #[OA\Response(response: 200, description: 'Roles retrieved successfully')]
    public function roles(): JsonResponse
    {
        $roles = $this->adminService->roles();

        return response()->json([
            'success' => true,
            'message' => 'Roles retrieved successfully',
            'data' => RoleResource::collection($roles),
        ]);
    }

    #[OA\Get(
        path: '/admin/permissions',
        summary: 'Get all permissions',
        security: [['bearerAuth' => []]],
        tags: ['Admin']
    )]
    #[OA\Response(response: 200, description: 'Permissions retrieved successfully')]
    public function permissions(): JsonResponse
    {
        $permissions = $this->adminService->allPermissions();

        return response()->json([
            'success' => true,
            'message' => 'Permissions retrieved successfully',
            'data' => PermissionResource::collection($permissions),
        ]);
    }
}
