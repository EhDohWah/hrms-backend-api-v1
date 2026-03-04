<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreEmployeeChildRequest;
use App\Http\Requests\UpdateEmployeeChildRequest;
use App\Http\Resources\EmployeeChildResource;
use App\Models\EmployeeChild;
use App\Services\EmployeeChildService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Handles CRUD operations for employee children records.
 */
#[OA\Tag(name: 'Employee Children', description: 'API Endpoints for Employee Children management')]
class EmployeeChildrenController extends BaseApiController
{
    public function __construct(
        private readonly EmployeeChildService $employeeChildService
    ) {}

    /**
     * Retrieve all employee children records.
     */
    #[OA\Get(
        path: '/employee-children',
        summary: 'Get all employee children',
        tags: ['Employee Children'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/EmployeeChild')
                        ),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee children retrieved successfully'),
                    ]
                )
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        $children = $this->employeeChildService->listAll();

        return $this->successResponse(
            EmployeeChildResource::collection($children),
            'Employee children retrieved successfully'
        );
    }

    /**
     * Store a new employee child record.
     */
    #[OA\Post(
        path: '/employee-children',
        summary: 'Create a new employee child',
        tags: ['Employee Children'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'employee_id', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'date_of_birth', type: 'string', format: 'date', example: '2020-01-01'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Employee child created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/EmployeeChild'),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee child created successfully'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation error'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function store(StoreEmployeeChildRequest $request): JsonResponse
    {
        $employeeChild = $this->employeeChildService->create(
            $request->validated(),
            $request->user()
        );

        return EmployeeChildResource::make($employeeChild)
            ->additional(['success' => true, 'message' => 'Employee child created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Retrieve a specific employee child by ID.
     */
    #[OA\Get(
        path: '/employee-children/{employeeChild}',
        summary: 'Get employee child by ID',
        tags: ['Employee Children'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'employeeChild',
                in: 'path',
                required: true,
                description: 'Employee child ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/EmployeeChild'),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee child retrieved successfully'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Employee child not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee child not found'),
                    ]
                )
            ),
        ]
    )]
    public function show(EmployeeChild $employeeChild): JsonResponse
    {
        $employeeChild = $this->employeeChildService->getWithEmployee($employeeChild);

        return EmployeeChildResource::make($employeeChild)
            ->additional(['success' => true, 'message' => 'Employee child retrieved successfully'])
            ->response();
    }

    /**
     * Update an existing employee child record.
     */
    #[OA\Put(
        path: '/employee-children/{employeeChild}',
        summary: 'Update an employee child',
        tags: ['Employee Children'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'employeeChild',
                in: 'path',
                required: true,
                description: 'Employee child ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'employee_id', type: 'integer', example: 1),
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'date_of_birth', type: 'string', format: 'date', example: '2020-01-01'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Employee child updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'data', ref: '#/components/schemas/EmployeeChild'),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee child updated successfully'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Employee child not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee child not found'),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation error'),
                        new OA\Property(property: 'errors', type: 'object'),
                    ]
                )
            ),
        ]
    )]
    public function update(UpdateEmployeeChildRequest $request, EmployeeChild $employeeChild): JsonResponse
    {
        $employeeChild = $this->employeeChildService->update(
            $employeeChild,
            $request->validated(),
            $request->user()
        );

        return EmployeeChildResource::make($employeeChild)
            ->additional(['success' => true, 'message' => 'Employee child updated successfully'])
            ->response();
    }

    /**
     * Delete an employee child record.
     */
    #[OA\Delete(
        path: '/employee-children/{employeeChild}',
        summary: 'Delete an employee child',
        tags: ['Employee Children'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'employeeChild',
                in: 'path',
                required: true,
                description: 'Employee child ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Employee child deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee child deleted successfully'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Employee child not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee child not found'),
                    ]
                )
            ),
        ]
    )]
    public function destroy(Request $request, EmployeeChild $employeeChild): JsonResponse
    {
        $this->employeeChildService->delete($employeeChild, $request->user());

        return $this->successResponse(
            null,
            'Employee child deleted successfully'
        );
    }
}
