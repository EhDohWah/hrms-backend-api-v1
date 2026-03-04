<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreEmployeeLanguageRequest;
use App\Http\Requests\UpdateEmployeeLanguageRequest;
use App\Http\Resources\EmployeeLanguageResource;
use App\Models\EmployeeLanguage;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Handles CRUD operations for employee language records.
 */
#[OA\Tag(name: 'Employee Language', description: 'API Endpoints for Employee Language')]
class EmployeeLanguageController extends BaseApiController
{
    #[OA\Get(
        path: '/api/employee-language',
        tags: ['Employee Language'],
        summary: 'Get list of employee language records',
        description: 'Returns list of employee language records',
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/EmployeeLanguage')
                )
            ),
        ]
    )]
    /**
     * Retrieve all employee language records.
     */
    public function index(): JsonResponse
    {
        $languages = EmployeeLanguage::orderBy('created_at', 'desc')->get();

        return EmployeeLanguageResource::collection($languages)
            ->additional(['success' => true, 'message' => 'Employee languages retrieved successfully'])
            ->response();
    }

    #[OA\Post(
        path: '/api/employee-language',
        tags: ['Employee Language'],
        summary: 'Store new employee language record',
        description: 'Stores a new employee language record and returns it',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/EmployeeLanguage')
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Successful operation',
                content: new OA\JsonContent(ref: '#/components/schemas/EmployeeLanguage')
            ),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Store a new employee language record.
     */
    public function store(StoreEmployeeLanguageRequest $request): JsonResponse
    {
        $employeeLanguage = EmployeeLanguage::create($request->validated());

        return EmployeeLanguageResource::make($employeeLanguage)
            ->additional(['success' => true, 'message' => 'Employee language created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/api/employee-language/{id}',
        tags: ['Employee Language'],
        summary: 'Get employee language record by ID',
        description: 'Returns a single employee language record',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Employee language ID',
                required: true,
                in: 'path',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(ref: '#/components/schemas/EmployeeLanguage')
            ),
            new OA\Response(response: 404, description: 'Record not found'),
        ]
    )]
    /**
     * Retrieve a specific employee language record.
     */
    public function show(EmployeeLanguage $employeeLanguage): JsonResponse
    {
        return EmployeeLanguageResource::make($employeeLanguage)
            ->additional(['success' => true, 'message' => 'Employee language retrieved successfully'])
            ->response();
    }

    #[OA\Put(
        path: '/api/employee-language/{id}',
        tags: ['Employee Language'],
        summary: 'Update existing employee language record',
        description: 'Updates an existing employee language record and returns it',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Employee language ID',
                required: true,
                in: 'path',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/EmployeeLanguage')
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful operation',
                content: new OA\JsonContent(ref: '#/components/schemas/EmployeeLanguage')
            ),
            new OA\Response(response: 404, description: 'Record not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Update an existing employee language record.
     */
    public function update(UpdateEmployeeLanguageRequest $request, EmployeeLanguage $employeeLanguage): JsonResponse
    {
        $employeeLanguage->update($request->validated());

        return EmployeeLanguageResource::make($employeeLanguage)
            ->additional(['success' => true, 'message' => 'Employee language updated successfully'])
            ->response();
    }

    #[OA\Delete(
        path: '/api/employee-language/{id}',
        tags: ['Employee Language'],
        summary: 'Delete employee language record',
        description: 'Deletes an employee language record',
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'Employee language ID',
                required: true,
                in: 'path',
                schema: new OA\Schema(type: 'integer')
            ),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'Record not found'),
        ]
    )]
    /**
     * Delete an employee language record.
     */
    public function destroy(EmployeeLanguage $employeeLanguage): JsonResponse
    {
        $employeeLanguage->delete();

        return $this->successResponse(null, 'Employee language deleted successfully');
    }
}
