<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Employee\StoreEmployeeBeneficiaryRequest;
use App\Http\Requests\Employee\UpdateEmployeeBeneficiaryRequest;
use App\Http\Resources\EmployeeBeneficiaryResource;
use App\Models\EmployeeBeneficiary;
use App\Services\EmployeeBeneficiaryService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Employee Beneficiaries', description: 'API Endpoints for Employee Beneficiary management')]
/**
 * Handles CRUD operations for employee beneficiary records.
 */
class EmployeeBeneficiaryController extends BaseApiController
{
    public function __construct(
        private readonly EmployeeBeneficiaryService $employeeBeneficiaryService,
    ) {}

    /**
     * Get all employee beneficiaries.
     */
    #[OA\Get(
        path: '/employee-beneficiaries',
        summary: 'Get all employee beneficiaries',
        tags: ['Employee Beneficiaries'],
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
                            items: new OA\Items(ref: '#/components/schemas/EmployeeBeneficiary')
                        ),
                        new OA\Property(property: 'message', type: 'string', example: 'Employee beneficiaries retrieved successfully'),
                    ]
                )
            ),
        ]
    )]
    public function index(): JsonResponse
    {
        $beneficiaries = $this->employeeBeneficiaryService->list();

        return EmployeeBeneficiaryResource::collection($beneficiaries)
            ->additional(['success' => true, 'message' => 'Employee beneficiaries retrieved successfully'])
            ->response();
    }

    /**
     * Get an employee beneficiary by ID.
     */
    #[OA\Get(
        path: '/employee-beneficiaries/{id}',
        summary: 'Get employee beneficiary by ID',
        tags: ['Employee Beneficiaries'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Employee beneficiary ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'Employee beneficiary not found'),
        ]
    )]
    public function show(EmployeeBeneficiary $employeeBeneficiary): JsonResponse
    {
        $employeeBeneficiary = $this->employeeBeneficiaryService->show($employeeBeneficiary);

        return EmployeeBeneficiaryResource::make($employeeBeneficiary)
            ->additional(['success' => true, 'message' => 'Employee beneficiary retrieved successfully'])
            ->response();
    }

    /**
     * Create a new employee beneficiary.
     */
    #[OA\Post(
        path: '/employee-beneficiaries',
        summary: 'Create a new employee beneficiary',
        tags: ['Employee Beneficiaries'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'employee_id', type: 'integer', example: 1),
                    new OA\Property(property: 'beneficiary_name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'beneficiary_relationship', type: 'string', example: 'spouse'),
                    new OA\Property(property: 'phone_number', type: 'string', example: '+1234567890'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Employee beneficiary created successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreEmployeeBeneficiaryRequest $request): JsonResponse
    {
        $beneficiary = $this->employeeBeneficiaryService->create($request->validated());

        return EmployeeBeneficiaryResource::make($beneficiary)
            ->additional(['success' => true, 'message' => 'Employee beneficiary created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an employee beneficiary.
     */
    #[OA\Put(
        path: '/employee-beneficiaries/{id}',
        summary: 'Update an employee beneficiary',
        tags: ['Employee Beneficiaries'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Employee beneficiary ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'employee_id', type: 'integer', example: 1),
                    new OA\Property(property: 'beneficiary_name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'beneficiary_relationship', type: 'string', example: 'spouse'),
                    new OA\Property(property: 'phone_number', type: 'string', example: '+1234567890'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Employee beneficiary updated successfully'),
            new OA\Response(response: 404, description: 'Employee beneficiary not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateEmployeeBeneficiaryRequest $request, EmployeeBeneficiary $employeeBeneficiary): JsonResponse
    {
        $employeeBeneficiary = $this->employeeBeneficiaryService->update($employeeBeneficiary, $request->validated());

        return EmployeeBeneficiaryResource::make($employeeBeneficiary)
            ->additional(['success' => true, 'message' => 'Employee beneficiary updated successfully'])
            ->response();
    }

    /**
     * Delete an employee beneficiary.
     */
    #[OA\Delete(
        path: '/employee-beneficiaries/{id}',
        summary: 'Delete an employee beneficiary',
        tags: ['Employee Beneficiaries'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'Employee beneficiary ID',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Employee beneficiary deleted successfully'),
            new OA\Response(response: 404, description: 'Employee beneficiary not found'),
        ]
    )]
    public function destroy(EmployeeBeneficiary $employeeBeneficiary): JsonResponse
    {
        $this->employeeBeneficiaryService->delete($employeeBeneficiary);

        return $this->successResponse(null, 'Employee beneficiary deleted successfully');
    }
}
