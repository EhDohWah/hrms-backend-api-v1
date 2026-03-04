<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Http\Resources\RoleResource;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use Spatie\Permission\Models\Role;

/**
 * Handles CRUD operations for organizational roles.
 */
#[OA\Tag(name: 'Roles', description: 'API Endpoints for managing organizational roles')]
class RoleController extends BaseApiController
{
    public function __construct(
        private readonly RoleService $roleService,
    ) {}

    /**
     * List all roles.
     */
    #[OA\Get(
        path: '/admin/roles',
        summary: 'List all roles',
        tags: ['Roles'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Roles retrieved successfully'),
        ]
    )]
    public function index(): JsonResponse
    {
        $roles = $this->roleService->list();

        return RoleResource::collection($roles)
            ->additional(['success' => true, 'message' => 'Roles retrieved successfully'])
            ->response();
    }

    /**
     * Get single role with details.
     */
    #[OA\Get(
        path: '/admin/roles/{role}',
        summary: 'Get a specific role',
        tags: ['Roles'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, description: 'Role ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Role retrieved successfully'),
            new OA\Response(response: 404, description: 'Role not found'),
        ]
    )]
    public function show(Role $role): JsonResponse
    {
        $role = $this->roleService->show($role);

        return RoleResource::make($role)
            ->additional(['success' => true, 'message' => 'Role retrieved successfully'])
            ->response();
    }

    /**
     * Create a new role.
     */
    #[OA\Post(
        path: '/admin/roles',
        summary: 'Create a new role',
        tags: ['Roles'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'payroll-specialist'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Role created successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->roleService->store($request->validated());

        return RoleResource::make($role)
            ->additional(['success' => true, 'message' => 'Role created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a role name.
     */
    #[OA\Put(
        path: '/admin/roles/{role}',
        summary: 'Update a role',
        tags: ['Roles'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, description: 'Role ID', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'payroll-lead'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Role updated successfully'),
            new OA\Response(response: 403, description: 'Cannot modify protected role'),
            new OA\Response(response: 404, description: 'Role not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $role = $this->roleService->update($role, $request->validated());

        return RoleResource::make($role)
            ->additional(['success' => true, 'message' => 'Role updated successfully'])
            ->response();
    }

    /**
     * Delete a role with safety checks.
     */
    #[OA\Delete(
        path: '/admin/roles/{role}',
        summary: 'Delete a role',
        tags: ['Roles'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'role', in: 'path', required: true, description: 'Role ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Role deleted successfully'),
            new OA\Response(response: 403, description: 'Cannot delete protected role'),
            new OA\Response(response: 404, description: 'Role not found'),
            new OA\Response(response: 422, description: 'Role has assigned users'),
        ]
    )]
    public function destroy(Role $role): JsonResponse
    {
        $this->roleService->destroy($role);

        return $this->successResponse(null, 'Role deleted successfully');
    }

    /**
     * Get roles for dropdown selection.
     */
    #[OA\Get(
        path: '/admin/roles/options',
        summary: 'Get role options for dropdowns',
        tags: ['Roles'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Role options retrieved successfully'),
        ]
    )]
    public function options(): JsonResponse
    {
        $roles = $this->roleService->options();

        return response()->json([
            'success' => true,
            'message' => 'Role options retrieved successfully',
            'data' => $roles,
        ]);
    }
}
