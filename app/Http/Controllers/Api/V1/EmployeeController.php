<?php

namespace App\Http\Controllers\Api\V1;

use App\Exports\EmployeesExport;
use App\Exports\EmployeeTemplateExport;
use App\Http\Requests\BatchSafeDeleteRequest;
use App\Http\Requests\Employee\AttachGrantItemRequest;
use App\Http\Requests\Employee\ExportEmployeesRequest;
use App\Http\Requests\Employee\FilterEmployeesRequest;
use App\Http\Requests\Employee\FullUpdateEmployeeRequest;
use App\Http\Requests\Employee\ListEmployeesRequest;
use App\Http\Requests\Employee\UploadProfilePictureRequest;
use App\Http\Requests\ShowEmployeeRequest;
use App\Http\Requests\StoreEmployeeRequest;
use App\Http\Requests\UpdateEmployeeBankRequest;
use App\Http\Requests\UpdateEmployeeFamilyRequest;
use App\Http\Requests\UpdateEmployeePersonalRequest;
use App\Http\Requests\UpdateEmployeeRequest;
use App\Http\Requests\UploadEmployeeImportRequest;
use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Services\EmployeeDataService;
use Illuminate\Http\JsonResponse;
use Maatwebsite\Excel\Facades\Excel;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Handles CRUD operations and management actions for employee records.
 */
#[OA\Tag(name: 'Employees', description: 'API Endpoints for Employee management')]
class EmployeeController extends BaseApiController
{
    public function __construct(
        private readonly EmployeeDataService $employeeService,
    ) {}

    #[OA\Get(
        path: '/employees',
        summary: 'Get all employees with advanced filtering and sorting',
        description: 'Returns a paginated list of employees with comprehensive filtering, sorting capabilities and statistics',
        operationId: 'getEmployees',
        security: [['bearerAuth' => []]],
        tags: ['Employees'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'search', in: 'query', description: 'Search by staff ID, first name, last name, or full name', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'filter_organization', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'filter_status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'sort_order', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function index(ListEmployeesRequest $request): JsonResponse
    {
        $result = $this->employeeService->list($request->validated());
        $paginator = $result['paginator'];

        return response()->json([
            'success' => true,
            'message' => 'Employees retrieved successfully',
            'data' => EmployeeResource::collection($paginator->items()),
            'statistics' => $result['statistics'],
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
            'filters' => ['applied_filters' => $result['applied_filters']],
        ]);
    }

    #[OA\Get(
        path: '/employees/{employee}',
        summary: 'Get employee details',
        description: 'Returns employee details by ID with related data',
        operationId: 'getEmployeeDetailsById',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employee', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'Employee not found'),
        ]
    )]
    public function show(Employee $employee): JsonResponse
    {
        $employee = $this->employeeService->show($employee);

        return response()->json([
            'success' => true,
            'message' => 'Employee retrieved successfully',
            'data' => $employee,
        ]);
    }

    #[OA\Post(
        path: '/employees',
        summary: 'Create a new employee',
        description: 'Creates a new employee with optional identifications and beneficiaries',
        operationId: 'createEmployee',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/Employee')),
        responses: [
            new OA\Response(response: 201, description: 'Employee created successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreEmployeeRequest $request): JsonResponse
    {
        $employee = $this->employeeService->store($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employee created successfully',
            'data' => $employee,
        ], 201);
    }

    #[OA\Put(
        path: '/employees/{employee}',
        summary: 'Update employee details',
        description: 'Updates an existing employee record with the provided information',
        operationId: 'updateEmployee',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employee', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/Employee')),
        responses: [
            new OA\Response(response: 200, description: 'Employee updated successfully'),
            new OA\Response(response: 404, description: 'Employee not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(FullUpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $employee = $this->employeeService->fullUpdate($employee, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employee updated successfully',
            'data' => $employee,
        ]);
    }

    #[OA\Delete(
        path: '/employees/{employee}',
        summary: 'Delete an employee',
        description: 'Soft-deletes an employee by ID',
        operationId: 'deleteEmployee',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employee', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Employee deleted successfully'),
            new OA\Response(response: 404, description: 'Employee not found'),
            new OA\Response(response: 422, description: 'Deletion blocked'),
        ]
    )]
    public function destroy(Employee $employee): JsonResponse
    {
        $this->employeeService->destroy($employee);

        return $this->successResponse(null, 'Employee moved to recycle bin');
    }

    #[OA\Delete(
        path: '/employees/batch/{ids}',
        summary: 'Delete multiple employees by their IDs',
        description: 'Deletes multiple employees and their related records based on the provided IDs',
        operationId: 'deleteSelectedEmployees',
        security: [['bearerAuth' => []]],
        tags: ['Employees'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['ids'],
                properties: [
                    new OA\Property(property: 'ids', type: 'array', items: new OA\Items(type: 'integer')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Employees deleted successfully'),
            new OA\Response(response: 207, description: 'Partial success'),
        ]
    )]
    public function destroyBatch(BatchSafeDeleteRequest $request): JsonResponse
    {
        $result = $this->employeeService->destroyBatch($request->validated()['ids']);

        $successCount = count($result['succeeded']);
        $failureCount = count($result['failed']);

        return response()->json([
            'success' => $failureCount === 0,
            'message' => "{$successCount} employee(s) moved to recycle bin"
                .($failureCount > 0 ? ", {$failureCount} failed" : ''),
            'succeeded' => $result['succeeded'],
            'failed' => $result['failed'],
        ], $failureCount === 0 ? 200 : 207);
    }

    #[OA\Get(
        path: '/employees/tree-search',
        summary: 'Get all employees for tree search',
        description: 'Returns a list of all employees organized by organization for tree-based search',
        operationId: 'searchForOrgTree',
        security: [['bearerAuth' => []]],
        tags: ['Employees'],
        responses: [new OA\Response(response: 200, description: 'Successful operation')]
    )]
    public function searchForOrgTree(): JsonResponse
    {
        $treeData = $this->employeeService->searchForOrgTree();

        return response()->json([
            'success' => true,
            'message' => 'Employees retrieved successfully',
            'data' => $treeData,
        ]);
    }

    #[OA\Get(
        path: '/employees/staff-id/{staff_id}',
        summary: 'Get employee by staff ID',
        description: 'Returns employee(s) by staff ID with related data',
        operationId: 'getEmployeeByStaffId',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'staff_id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'Employee not found'),
        ]
    )]
    public function showByStaffId(ShowEmployeeRequest $request, string $staff_id): JsonResponse
    {
        $employees = $this->employeeService->showByStaffId($staff_id);

        return response()->json([
            'success' => true,
            'message' => 'Employee(s) retrieved successfully',
            'data' => EmployeeResource::collection($employees),
        ]);
    }

    #[OA\Get(
        path: '/employees/filter',
        summary: 'Filter employees by criteria',
        description: 'Returns employees filtered by the provided criteria',
        operationId: 'filterEmployees',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'staff_id', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'organization', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Filtered employees retrieved successfully'),
            new OA\Response(response: 404, description: 'No employees found'),
        ]
    )]
    public function filterEmployees(FilterEmployeesRequest $request): JsonResponse
    {
        $employees = $this->employeeService->filterEmployees($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employees retrieved successfully',
            'data' => $employees,
        ]);
    }

    #[OA\Get(
        path: '/employees/site-records',
        summary: 'Get Site records',
        description: 'Returns a list of all work locations/sites',
        operationId: 'getSiteRecords',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        responses: [new OA\Response(response: 200, description: 'Successful operation')]
    )]
    public function siteRecords(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Site records retrieved successfully',
            'data' => $this->employeeService->getSiteRecords(),
        ]);
    }

    #[OA\Put(
        path: '/employees/{employee}/basic-information',
        summary: 'Update employee basic information',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employee', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/Employee')),
        responses: [
            new OA\Response(response: 200, description: 'Employee basic information updated successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Employee not found'),
        ]
    )]
    public function updateBasicInfo(UpdateEmployeeRequest $request, Employee $employee): JsonResponse
    {
        $employee = $this->employeeService->updateBasicInfo($employee, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employee basic information updated successfully',
            'data' => $employee,
        ]);
    }

    #[OA\Put(
        path: '/employees/{employee}/personal-information',
        summary: 'Update employee personal information',
        description: 'Update the personal information of an employee, including identification and languages',
        operationId: 'updatePersonalInfo',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employee', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/Employee')),
        responses: [
            new OA\Response(response: 200, description: 'Employee personal information updated successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 404, description: 'Employee not found'),
        ]
    )]
    public function updatePersonalInfo(UpdateEmployeePersonalRequest $request, Employee $employee): JsonResponse
    {
        $validated = $request->validated();
        $languages = $validated['languages'] ?? null;

        $employee = $this->employeeService->updatePersonalInfo($employee, $validated, $languages);

        return response()->json([
            'success' => true,
            'message' => 'Employee personal information updated successfully',
            'data' => $employee,
        ]);
    }

    #[OA\Put(
        path: '/employees/{employee}/family-information',
        summary: 'Update employee family information',
        description: 'Update the family information of an employee including parents and emergency contact',
        operationId: 'updateFamilyInfo',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employee', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: false, content: new OA\JsonContent(ref: '#/components/schemas/Employee')),
        responses: [
            new OA\Response(response: 200, description: 'Employee family information updated successfully'),
            new OA\Response(response: 404, description: 'Employee not found'),
        ]
    )]
    public function updateFamilyInfo(UpdateEmployeeFamilyRequest $request, Employee $employee): JsonResponse
    {
        $data = $this->employeeService->updateFamilyInfo($employee, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employee family information updated successfully',
            'data' => $data,
        ]);
    }

    #[OA\Put(
        path: '/employees/{employee}/bank-information',
        summary: 'Update employee bank information',
        description: 'Updates bank details for a specific employee',
        operationId: 'updateBankInfo',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employee', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/Employee')),
        responses: [
            new OA\Response(response: 200, description: 'Bank information updated successfully'),
            new OA\Response(response: 404, description: 'Employee not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function updateBankInfo(UpdateEmployeeBankRequest $request, Employee $employee): JsonResponse
    {
        $bankInfo = $this->employeeService->updateBankInfo($employee, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Bank information updated successfully',
            'data' => $bankInfo,
        ]);
    }

    #[OA\Post(
        path: '/employees/{employee}/profile-picture',
        summary: 'Upload profile picture',
        description: 'Upload a profile picture for an employee',
        operationId: 'uploadProfilePictureEmployee',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employee', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['profile_picture'],
                    properties: [
                        new OA\Property(property: 'profile_picture', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Profile picture uploaded successfully'),
            new OA\Response(response: 404, description: 'Employee not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function uploadProfilePicture(UploadProfilePictureRequest $request, Employee $employee): JsonResponse
    {
        $data = $this->employeeService->uploadProfilePicture($employee, $request->file('profile_picture'));

        return response()->json([
            'success' => true,
            'message' => 'Profile picture uploaded successfully',
            'data' => $data,
        ]);
    }

    #[OA\Post(
        path: '/employees/upload',
        summary: 'Upload employee data from Excel file',
        description: 'Upload an Excel file where each row contains data for creating an employee record',
        operationId: 'uploadEmployeeData',
        security: [['bearerAuth' => []]],
        tags: ['Employees'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Employee data uploaded successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function uploadEmployeeData(UploadEmployeeImportRequest $request): JsonResponse
    {
        $result = $this->employeeService->uploadEmployeeData($request->file('file'));

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => $result['data'],
        ]);
    }

    #[OA\Get(
        path: '/employees/import-status/{importId}',
        summary: 'Get import job status',
        description: 'Returns the status and statistics of an employee import job',
        operationId: 'getImportStatus',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'importId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Import status retrieved successfully'),
            new OA\Response(response: 404, description: 'Import not found'),
        ]
    )]
    public function importStatus(string $importId): JsonResponse
    {
        $stats = $this->employeeService->importStatus($importId);

        return response()->json([
            'success' => true,
            'message' => 'Import status retrieved successfully',
            'data' => $stats,
        ]);
    }

    #[OA\Post(
        path: '/employees/{employee}/grant-items',
        summary: 'Attach a grant item to an employee',
        operationId: 'attachGrantItemToEmployee',
        tags: ['Employees'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent),
        responses: [
            new OA\Response(response: 201, description: 'Grant item attached successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function attachGrantItem(AttachGrantItemRequest $request): JsonResponse
    {
        $employee = $this->employeeService->attachGrantItem($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employee grant-item added successfully',
            'data' => $employee,
        ], 201);
    }

    #[OA\Get(
        path: '/downloads/employee-template',
        summary: 'Download Employee Import Excel Template',
        description: 'Downloads an Excel template file with headers, validation rules, and sample data for employee bulk import.',
        operationId: 'downloadEmployeeTemplate',
        security: [['bearerAuth' => []]],
        tags: ['Employees'],
        responses: [new OA\Response(response: 200, description: 'Excel template file download')]
    )]
    public function downloadEmployeeTemplate(): BinaryFileResponse
    {
        $export = new EmployeeTemplateExport;
        $tempFile = $export->generate();

        return response()->download($tempFile, $export->getFilename(), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-cache, must-revalidate',
        ])->deleteFileAfterSend(true);
    }

    #[OA\Get(
        path: '/employees/export',
        summary: 'Export employees to Excel',
        description: 'Export employees to Excel file with optional filtering by organization and status.',
        operationId: 'exportEmployees',
        security: [['bearerAuth' => []]],
        tags: ['Employees'],
        parameters: [
            new OA\Parameter(name: 'organization', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['SMRU', 'BHF'])),
            new OA\Parameter(name: 'status', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['Expats (Local)', 'Local ID Staff', 'Local non ID Staff'])),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Excel file download'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function exportEmployees(ExportEmployeesRequest $request): BinaryFileResponse
    {
        $validated = $request->validated();
        $export = new EmployeesExport($validated['organization'] ?? null, $validated['status'] ?? null);

        $filename = 'employees';
        if ($validated['organization'] ?? null) {
            $filename .= '_'.$validated['organization'];
        }
        if ($validated['status'] ?? null) {
            $filename .= '_'.str_replace(' ', '_', $validated['status']);
        }
        $filename .= '_'.date('Y-m-d_His').'.xlsx';

        return Excel::download($export, $filename);
    }
}
