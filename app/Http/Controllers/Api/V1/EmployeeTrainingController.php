<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\EmployeeTraining\AttendanceListRequest;
use App\Http\Requests\EmployeeTraining\EmployeeSummaryRequest;
use App\Http\Requests\EmployeeTraining\IndexEmployeeTrainingRequest;
use App\Http\Requests\EmployeeTraining\StoreEmployeeTrainingRequest;
use App\Http\Requests\EmployeeTraining\UpdateEmployeeTrainingRequest;
use App\Http\Resources\EmployeeTrainingResource;
use App\Models\Employee;
use App\Models\EmployeeTraining;
use App\Models\Training;
use App\Services\EmployeeTrainingService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * Handles CRUD operations and reporting for employee training records.
 */
#[OA\Tag(name: 'Employee Trainings', description: 'API Endpoints for Employee Training management')]
class EmployeeTrainingController extends BaseApiController
{
    public function __construct(
        private readonly EmployeeTrainingService $employeeTrainingService,
    ) {}

    #[OA\Get(
        path: '/employee-trainings',
        summary: 'List employee training records with filtering, sorting, and pagination',
        operationId: 'getEmployeeTrainings',
        security: [['bearerAuth' => []]],
        tags: ['Employee Trainings'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'filter_training_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'filter_employee_id', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'filter_status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort_order', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function index(IndexEmployeeTrainingRequest $request): JsonResponse
    {
        $result = $this->employeeTrainingService->list($request->validated());
        $employeeTrainings = $result['employee_trainings'];

        return response()->json([
            'success' => true,
            'message' => 'Employee training records retrieved successfully',
            'data' => EmployeeTrainingResource::collection($employeeTrainings->items()),
            'pagination' => [
                'current_page' => $employeeTrainings->currentPage(),
                'per_page' => $employeeTrainings->perPage(),
                'total' => $employeeTrainings->total(),
                'last_page' => $employeeTrainings->lastPage(),
                'from' => $employeeTrainings->firstItem(),
                'to' => $employeeTrainings->lastItem(),
                'has_more_pages' => $employeeTrainings->hasMorePages(),
            ],
            'filters' => [
                'applied_filters' => $result['applied_filters'],
            ],
        ]);
    }

    #[OA\Post(
        path: '/employee-trainings',
        summary: 'Create a new employee training record',
        operationId: 'createEmployeeTraining',
        security: [['bearerAuth' => []]],
        tags: ['Employee Trainings'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/EmployeeTraining')),
        responses: [
            new OA\Response(response: 201, description: 'Employee training record created successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreEmployeeTrainingRequest $request): JsonResponse
    {
        $employeeTraining = $this->employeeTrainingService->store($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employee training record created successfully',
            'data' => $employeeTraining,
        ], 201);
    }

    #[OA\Get(
        path: '/employee-trainings/{employeeTraining}',
        summary: 'Get a specific employee training record',
        operationId: 'getEmployeeTraining',
        security: [['bearerAuth' => []]],
        tags: ['Employee Trainings'],
        parameters: [
            new OA\Parameter(name: 'employeeTraining', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'Employee training record not found'),
        ]
    )]
    public function show(EmployeeTraining $employeeTraining): JsonResponse
    {
        $employeeTraining = $this->employeeTrainingService->show($employeeTraining);

        return response()->json([
            'success' => true,
            'message' => 'Employee training record retrieved successfully',
            'data' => $employeeTraining,
        ]);
    }

    #[OA\Put(
        path: '/employee-trainings/{employeeTraining}',
        summary: 'Update an employee training record',
        operationId: 'updateEmployeeTraining',
        security: [['bearerAuth' => []]],
        tags: ['Employee Trainings'],
        parameters: [
            new OA\Parameter(name: 'employeeTraining', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/EmployeeTraining')),
        responses: [
            new OA\Response(response: 200, description: 'Employee training record updated successfully'),
            new OA\Response(response: 404, description: 'Employee training record not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateEmployeeTrainingRequest $request, EmployeeTraining $employeeTraining): JsonResponse
    {
        $employeeTraining = $this->employeeTrainingService->update($employeeTraining, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employee training record updated successfully',
            'data' => $employeeTraining,
        ]);
    }

    #[OA\Delete(
        path: '/employee-trainings/{employeeTraining}',
        summary: 'Delete an employee training record',
        operationId: 'deleteEmployeeTraining',
        security: [['bearerAuth' => []]],
        tags: ['Employee Trainings'],
        parameters: [
            new OA\Parameter(name: 'employeeTraining', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Employee training record deleted successfully'),
            new OA\Response(response: 404, description: 'Employee training record not found'),
        ]
    )]
    public function destroy(EmployeeTraining $employeeTraining): JsonResponse
    {
        $this->employeeTrainingService->destroy($employeeTraining);

        return $this->successResponse(null, 'Employee training record deleted successfully');
    }

    #[OA\Get(
        path: '/employee-trainings/employee/{employee}/summary',
        summary: 'Get training summary for a specific employee',
        operationId: 'getEmployeeTrainingSummary',
        security: [['bearerAuth' => []]],
        tags: ['Employee Trainings'],
        parameters: [
            new OA\Parameter(name: 'employee', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'date_from', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'date_to', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Employee training summary retrieved successfully'),
            new OA\Response(response: 404, description: 'Employee not found'),
        ]
    )]
    public function employeeSummary(EmployeeSummaryRequest $request, Employee $employee): JsonResponse
    {
        $data = $this->employeeTrainingService->employeeSummary($employee, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employee training summary retrieved successfully',
            'data' => $data,
        ]);
    }

    #[OA\Get(
        path: '/employee-trainings/training/{training}/attendance',
        summary: 'Get attendance list for a specific training',
        operationId: 'getTrainingAttendanceList',
        security: [['bearerAuth' => []]],
        tags: ['Employee Trainings'],
        parameters: [
            new OA\Parameter(name: 'training', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'status_filter', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['Completed', 'In Progress', 'Pending'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Training attendance list retrieved successfully'),
            new OA\Response(response: 404, description: 'Training not found'),
        ]
    )]
    public function attendanceList(AttendanceListRequest $request, Training $training): JsonResponse
    {
        $data = $this->employeeTrainingService->attendanceList($training, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Training attendance list retrieved successfully',
            'data' => $data,
        ]);
    }
}
