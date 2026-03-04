<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreEmployeeEducationRequest;
use App\Http\Requests\UpdateEmployeeEducationRequest;
use App\Http\Resources\EmployeeEducationResource;
use App\Models\EmployeeEducation;
use App\Services\EmployeeEducationService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Employee Education', description: 'API Endpoints for Employee Education')]
/**
 * Handles CRUD operations for employee education records.
 */
class EmployeeEducationController extends BaseApiController
{
    public function __construct(
        private readonly EmployeeEducationService $employeeEducationService,
    ) {}

    /**
     * Retrieve all employee education records.
     */
    #[OA\Get(
        path: '/employee-education',
        summary: 'Get list of employee education records',
        tags: ['Employee Education'],
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
                            items: new OA\Items(ref: '#/components/schemas/EmployeeEducation')
                        ),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee education records retrieved successfully'),
                    ]
                )
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        $educations = $this->employeeEducationService->list();

        return EmployeeEducationResource::collection($educations)
            ->additional(['success' => true, 'message' => 'Employee education records retrieved successfully'])
            ->response();
    }

    /**
     * Retrieve a specific employee education record.
     */
    #[OA\Get(
        path: '/employee-education/{id}',
        summary: 'Get employee education record by ID',
        tags: ['Employee Education'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Employee education ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'Record not found'),
        ]
    )]
    public function show(EmployeeEducation $employeeEducation): JsonResponse
    {
        $employeeEducation = $this->employeeEducationService->show($employeeEducation);

        return EmployeeEducationResource::make($employeeEducation)
            ->additional(['success' => true, 'message' => 'Employee education record retrieved successfully'])
            ->response();
    }

    /**
     * Store a new employee education record.
     */
    #[OA\Post(
        path: '/employee-education',
        summary: 'Store new employee education record',
        tags: ['Employee Education'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['employee_id', 'school_name', 'degree', 'start_date', 'end_date'],
                properties: [
                    new OA\Property(property: 'employee_id', type: 'integer', example: 1),
                    new OA\Property(property: 'school_name', type: 'string', maxLength: 100, example: 'Harvard University'),
                    new OA\Property(property: 'degree', type: 'string', maxLength: 100, example: 'Bachelor of Science in Computer Science'),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2018-09-01'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2022-06-30'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Employee education record created successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreEmployeeEducationRequest $request): JsonResponse
    {
        $employeeEducation = $this->employeeEducationService->create($request->validated());

        return EmployeeEducationResource::make($employeeEducation)
            ->additional(['success' => true, 'message' => 'Employee education record created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an existing employee education record.
     */
    #[OA\Put(
        path: '/employee-education/{id}',
        summary: 'Update employee education record',
        tags: ['Employee Education'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Employee education ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'employee_id', type: 'integer', example: 1),
                    new OA\Property(property: 'school_name', type: 'string', maxLength: 100, example: 'Harvard University'),
                    new OA\Property(property: 'degree', type: 'string', maxLength: 100, example: 'Bachelor of Science in Computer Science'),
                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2018-09-01'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2022-06-30'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Employee education record updated successfully'),
            new OA\Response(response: 404, description: 'Record not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateEmployeeEducationRequest $request, EmployeeEducation $employeeEducation): JsonResponse
    {
        $employeeEducation = $this->employeeEducationService->update($employeeEducation, $request->validated());

        return EmployeeEducationResource::make($employeeEducation)
            ->additional(['success' => true, 'message' => 'Employee education record updated successfully'])
            ->response();
    }

    /**
     * Delete an employee education record.
     */
    #[OA\Delete(
        path: '/employee-education/{id}',
        summary: 'Delete employee education record',
        tags: ['Employee Education'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Employee education ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Employee education record deleted successfully'),
            new OA\Response(response: 404, description: 'Record not found'),
        ]
    )]
    public function destroy(EmployeeEducation $employeeEducation): JsonResponse
    {
        $this->employeeEducationService->delete($employeeEducation);

        return $this->successResponse(null, 'Employee education record deleted successfully');
    }
}
