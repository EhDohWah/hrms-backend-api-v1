<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreEmployeeIdentificationRequest;
use App\Http\Requests\UpdateEmployeeIdentificationRequest;
use App\Http\Resources\EmployeeIdentificationResource;
use App\Models\EmployeeIdentification;
use App\Services\EmployeeIdentificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Employee Identifications', description: 'API Endpoints for Employee Identification management')]
class EmployeeIdentificationController extends BaseApiController
{
    public function __construct(
        private readonly EmployeeIdentificationService $identificationService
    ) {}

    #[OA\Get(
        path: '/employee-identifications',
        summary: 'Get all identifications for an employee',
        tags: ['Employee Identifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employee_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $request->validate(['employee_id' => 'required|integer|exists:employees,id']);

        $identifications = $this->identificationService->listByEmployee(
            $request->integer('employee_id')
        );

        return $this->successResponse(
            EmployeeIdentificationResource::collection($identifications),
            'Employee identifications retrieved successfully'
        );
    }

    #[OA\Get(
        path: '/employee-identifications/{employeeIdentification}',
        summary: 'Get identification by ID',
        tags: ['Employee Identifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employeeIdentification', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(EmployeeIdentification $employeeIdentification): JsonResponse
    {
        $identification = $this->identificationService->show($employeeIdentification);

        return EmployeeIdentificationResource::make($identification)
            ->additional(['success' => true, 'message' => 'Employee identification retrieved successfully'])
            ->response();
    }

    #[OA\Post(
        path: '/employee-identifications',
        summary: 'Create a new employee identification',
        tags: ['Employee Identifications'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/EmployeeIdentification')),
        responses: [
            new OA\Response(response: 201, description: 'Created successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreEmployeeIdentificationRequest $request): JsonResponse
    {
        $identification = $this->identificationService->create($request->validated());

        return EmployeeIdentificationResource::make($identification)
            ->additional(['success' => true, 'message' => 'Employee identification created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Put(
        path: '/employee-identifications/{employeeIdentification}',
        summary: 'Update an employee identification',
        tags: ['Employee Identifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employeeIdentification', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [
            new OA\Response(response: 200, description: 'Updated successfully'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateEmployeeIdentificationRequest $request, EmployeeIdentification $employeeIdentification): JsonResponse
    {
        $identification = $this->identificationService->update(
            $employeeIdentification,
            $request->validated()
        );

        return EmployeeIdentificationResource::make($identification)
            ->additional(['success' => true, 'message' => 'Employee identification updated successfully'])
            ->response();
    }

    #[OA\Delete(
        path: '/employee-identifications/{employeeIdentification}',
        summary: 'Delete an employee identification',
        tags: ['Employee Identifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employeeIdentification', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted successfully'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Cannot delete only identification'),
        ]
    )]
    public function destroy(EmployeeIdentification $employeeIdentification): JsonResponse
    {
        $result = $this->identificationService->delete($employeeIdentification);

        if (! $result['success']) {
            return $this->errorResponse($result['message'], 422);
        }

        return $this->successResponse(null, 'Employee identification deleted successfully');
    }

    #[OA\Patch(
        path: '/employee-identifications/{employeeIdentification}/set-primary',
        summary: 'Set identification as primary',
        tags: ['Employee Identifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employeeIdentification', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Primary set successfully'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function setPrimary(EmployeeIdentification $employeeIdentification): JsonResponse
    {
        $identification = $this->identificationService->setPrimary($employeeIdentification);

        return EmployeeIdentificationResource::make($identification)
            ->additional(['success' => true, 'message' => 'Primary identification updated successfully'])
            ->response();
    }
}
