<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\BatchSafeDeleteRequest;
use App\Http\Requests\IndexDepartmentRequest;
use App\Http\Requests\OptionsDepartmentRequest;
use App\Http\Requests\PositionsDepartmentRequest;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\UpdateDepartmentRequest;
use App\Http\Resources\DepartmentDetailResource;
use App\Http\Resources\DepartmentResource;
use App\Http\Resources\PositionResource;
use App\Models\Department;
use App\Services\DepartmentService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Departments', description: 'API Endpoints for Department management')]
class DepartmentController extends BaseApiController
{
    public function __construct(
        private readonly DepartmentService $departmentService,
    ) {}

    #[OA\Get(
        path: '/departments/options',
        summary: 'Get department options (lightweight)',
        description: 'Returns minimal department list for dropdowns',
        operationId: 'getDepartmentOptions',
        security: [['bearerAuth' => []]],
        tags: ['Departments']
    )]
    #[OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 1000, default: 200))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function options(OptionsDepartmentRequest $request): JsonResponse
    {
        $departments = $this->departmentService->options($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Department options retrieved successfully',
            'data' => $departments,
        ]);
    }

    #[OA\Get(
        path: '/departments',
        summary: 'Get all departments',
        description: 'Returns a paginated list of departments with optional filtering and search',
        operationId: 'getDepartments',
        security: [['bearerAuth' => []]],
        tags: ['Departments']
    )]
    #[OA\Parameter(name: 'search', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    public function index(IndexDepartmentRequest $request): JsonResponse
    {
        $paginator = $this->departmentService->list($request->validated());

        return DepartmentResource::collection($paginator)
            ->additional(['success' => true, 'message' => 'Departments retrieved successfully'])
            ->response();
    }

    #[OA\Post(
        path: '/departments',
        summary: 'Create a new department',
        description: 'Creates a new department and returns it',
        operationId: 'storeDepartment',
        security: [['bearerAuth' => []]],
        tags: ['Departments']
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Information Technology'),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'is_active', type: 'boolean', default: true),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Department created successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function store(StoreDepartmentRequest $request): JsonResponse
    {
        $department = $this->departmentService->create($request->validated());

        return DepartmentResource::make($department)
            ->additional(['success' => true, 'message' => 'Department created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Get(
        path: '/departments/{id}',
        summary: 'Get a specific department',
        description: 'Returns a specific department by ID with detailed information',
        operationId: 'getDepartment',
        security: [['bearerAuth' => []]],
        tags: ['Departments']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Department not found')]
    public function show(Department $department): JsonResponse
    {
        $department = $this->departmentService->show($department);

        return DepartmentDetailResource::make($department)
            ->additional(['success' => true, 'message' => 'Department retrieved successfully'])
            ->response();
    }

    #[OA\Put(
        path: '/departments/{id}',
        summary: 'Update a department',
        description: 'Updates a department and returns it',
        operationId: 'updateDepartment',
        security: [['bearerAuth' => []]],
        tags: ['Departments']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'name', type: 'string', maxLength: 255),
                new OA\Property(property: 'description', type: 'string', nullable: true),
                new OA\Property(property: 'is_active', type: 'boolean'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Department updated successfully')]
    #[OA\Response(response: 404, description: 'Department not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function update(UpdateDepartmentRequest $request, Department $department): JsonResponse
    {
        $department = $this->departmentService->update($department, $request->validated());

        return DepartmentResource::make($department)
            ->additional(['success' => true, 'message' => 'Department updated successfully'])
            ->response();
    }

    #[OA\Delete(
        path: '/departments/{id}',
        summary: 'Delete a department',
        description: 'Deletes a department (soft delete if has positions, hard delete if empty)',
        operationId: 'deleteDepartment',
        security: [['bearerAuth' => []]],
        tags: ['Departments']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Department deleted successfully')]
    #[OA\Response(response: 404, description: 'Department not found')]
    #[OA\Response(response: 422, description: 'Cannot delete department with active positions')]
    public function destroy(Department $department): JsonResponse
    {
        $this->departmentService->delete($department);

        return $this->successResponse(null, 'Department moved to recycle bin');
    }

    #[OA\Get(
        path: '/departments/{id}/positions',
        summary: 'Get all positions in a department',
        description: 'Returns all positions within a specific department',
        operationId: 'getDepartmentPositions',
        security: [['bearerAuth' => []]],
        tags: ['Departments']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'is_active', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'is_manager', in: 'query', required: false, schema: new OA\Schema(type: 'boolean'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Department not found')]
    public function positions(PositionsDepartmentRequest $request, Department $department): JsonResponse
    {
        $positions = $this->departmentService->positions($department, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Department positions retrieved successfully',
            'data' => PositionResource::collection($positions),
        ]);
    }

    #[OA\Get(
        path: '/departments/{id}/managers',
        summary: 'Get all managers in a department',
        description: 'Returns all manager positions within a specific department',
        operationId: 'getDepartmentManagers',
        security: [['bearerAuth' => []]],
        tags: ['Departments']
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Successful operation')]
    #[OA\Response(response: 404, description: 'Department not found')]
    public function managers(Department $department): JsonResponse
    {
        $managers = $this->departmentService->managers($department);

        return response()->json([
            'success' => true,
            'message' => 'Department managers retrieved successfully',
            'data' => PositionResource::collection($managers),
        ]);
    }

    /**
     * Batch delete multiple departments.
     */
    public function destroyBatch(BatchSafeDeleteRequest $request): JsonResponse
    {
        $succeeded = [];
        $failed = [];

        foreach ($request->validated()['ids'] as $id) {
            try {
                $record = \App\Models\Department::findOrFail($id);
                $this->departmentService->delete($record);
                $succeeded[] = ['id' => $id];
            } catch (\Exception $e) {
                $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        $failureCount = count($failed);
        $successCount = count($succeeded);

        return response()->json([
            'success' => $failureCount === 0,
            'message' => "{$successCount} department(s) deleted"
                .($failureCount > 0 ? ", {$failureCount} failed" : ''),
            'data' => ['succeeded' => $succeeded, 'failed' => $failed],
        ], $failureCount === 0 ? 200 : 207);
    }
}
