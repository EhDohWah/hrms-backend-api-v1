<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\FundingAllocationStatus;
use App\Http\Requests\IndexEmploymentRequest;
use App\Http\Requests\SearchByStaffIdRequest;
use App\Http\Requests\StoreEmploymentRequest;
use App\Http\Requests\UpdateEmploymentRequest;
use App\Http\Requests\UpdateProbationStatusRequest;
use App\Http\Requests\UploadEmploymentRequest;
use App\Http\Resources\EmployeeFundingAllocationResource;
use App\Http\Resources\EmploymentResource;
use App\Models\EmployeeFundingAllocation;
use App\Models\Employment;
use App\Services\EmploymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Handles CRUD operations and probation management for employment records.
 */
#[OA\Tag(name: 'Employments', description: 'API Endpoints for managing employee employment records')]
class EmploymentController extends BaseApiController
{
    public function __construct(
        private readonly EmploymentService $employmentService,
    ) {}

    #[OA\Get(
        path: '/employments',
        summary: 'Get employment records with advanced filtering and pagination',
        description: 'Returns a paginated list of employment records with filtering by organization and work location',
        operationId: 'getEmployments',
        security: [['bearerAuth' => []]],
        tags: ['Employments']
    )]
    #[OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'per_page', in: 'query', required: false, schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'filter_organization', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'sort_by', in: 'query', required: false, schema: new OA\Schema(type: 'string'))]
    #[OA\Response(response: 200, description: 'Employments retrieved successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
    #[OA\Response(response: 500, description: 'Server error')]
    /**
     * List employment records with filtering and pagination.
     */
    public function index(IndexEmploymentRequest $request): JsonResponse
    {
        $result = $this->employmentService->list($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employments retrieved successfully',
            'data' => EmploymentResource::collection($result->paginator->items()),
            'pagination' => $this->paginationMeta($result->paginator),
            'filters' => ['applied_filters' => $result->appliedFilters],
        ]);
    }

    #[OA\Get(
        path: '/employments/search/staff-id/{staffId}',
        summary: 'Search employment records by staff ID',
        description: 'Returns employment records for a specific employee identified by their staff ID. Includes all related information like employee details, department position, work location, and funding allocations',
        operationId: 'searchEmploymentByStaffId',
        tags: ['Employments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'staffId', in: 'path', required: true, description: 'Staff ID of the employee to search for', schema: new OA\Schema(type: 'string', example: 'EMP001')),
            new OA\Parameter(name: 'include_inactive', in: 'query', required: false, description: 'Include inactive/ended employment records', schema: new OA\Schema(type: 'boolean', example: false, default: false)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Employment records found successfully'),
            new OA\Response(response: 404, description: 'Employee not found'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    /**
     * Search employment records by staff ID.
     */
    public function searchByStaffId(SearchByStaffIdRequest $request, string $staffId): JsonResponse
    {
        $result = $this->employmentService->searchByStaffId(
            $staffId,
            $request->validated()['include_inactive'] ?? false
        );

        return response()->json([
            'success' => true,
            'message' => "Employment records found for staff ID: {$staffId}",
            'data' => EmploymentResource::collection($result['employments']),
            'employee_summary' => $result['employee_summary'],
            'statistics' => $result['statistics'],
        ]);
    }

    #[OA\Post(
        path: '/employments',
        operationId: 'createEmployment',
        tags: ['Employments'],
        summary: 'Create employment record (optionally with funding allocations)',
        description: 'Creates an employment record. Funding allocations are optional and can be added separately via the EmployeeFundingAllocation API. If allocations are provided, they will be created together with the employment.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/Employment')
        ),
        responses: [
            new OA\Response(response: 201, description: 'Employment created successfully'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Create a new employment record with optional funding allocations.
     */
    public function store(StoreEmploymentRequest $request): JsonResponse
    {
        $employment = $this->employmentService->create($request->validated());

        return EmploymentResource::make($employment)
            ->additional(['success' => true, 'message' => 'Employment created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    #[OA\Post(
        path: '/employments/upload',
        summary: 'Upload employment data from Excel file',
        description: 'Upload an Excel file containing employment records. The import is processed asynchronously in the background with chunk processing and duplicate checking. Existing employments will be updated, new ones will be created',
        tags: ['Employments'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    properties: [
                        new OA\Property(property: 'file', type: 'string', format: 'binary', description: 'Excel file to upload (xlsx, xls, csv)'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(response: 202, description: 'Employment data import started successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
            new OA\Response(response: 500, description: 'Import failed'),
        ]
    )]
    /**
     * Upload employment data from an Excel file.
     */
    public function upload(UploadEmploymentRequest $request): JsonResponse
    {
        $result = $this->employmentService->uploadEmployments($request->file('file'));

        return response()->json([
            'success' => true,
            'message' => 'Employment import started successfully. You will receive a notification when the import is complete.',
            'data' => $result,
        ], 202);
    }

    #[OA\Get(
        path: '/downloads/employment-template',
        summary: 'Download employment import template',
        description: 'Downloads an Excel template for bulk employment import with validation rules and sample data',
        operationId: 'downloadEmploymentTemplate',
        tags: ['Employments'],
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Template file downloaded successfully'),
            new OA\Response(response: 500, description: 'Failed to generate template'),
        ]
    )]
    /**
     * Download the employment import Excel template.
     */
    public function downloadEmploymentTemplate(): BinaryFileResponse
    {
        $result = $this->employmentService->downloadTemplate();

        return response()->download($result['file'], $result['filename'], [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$result['filename'].'"',
            'Cache-Control' => 'max-age=0',
        ])->deleteFileAfterSend(true);
    }

    #[OA\Get(
        path: '/employments/{id}',
        summary: 'Get employment record by ID',
        description: 'Returns a specific employment record by ID',
        operationId: 'getEmployment',
        tags: ['Employments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID of employment record to return', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'Employment not found'),
        ]
    )]
    /**
     * Retrieve a specific employment record by ID.
     */
    public function show(Employment $employment): JsonResponse
    {
        $employment = $this->employmentService->show($employment);

        return EmploymentResource::make($employment)
            ->additional(['success' => true, 'message' => 'Employment retrieved successfully'])
            ->response();
    }

    #[OA\Post(
        path: '/employments/{id}/complete-probation',
        summary: 'Complete probation period manually',
        description: 'Manually triggers probation completion, updating funding allocations from probation_salary to pass_probation_salary',
        operationId: 'completeProbation',
        tags: ['Employments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID of employment record', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Probation completed successfully'),
            new OA\Response(response: 404, description: 'Employment not found'),
            new OA\Response(response: 400, description: 'Invalid request or probation already completed'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 403, description: 'Unauthorized'),
        ]
    )]
    /**
     * Manually complete probation and update funding allocations.
     */
    public function completeProbation(Employment $employment): JsonResponse
    {
        $result = $this->employmentService->completeProbation($employment);

        return response()->json([
            'success' => true,
            'message' => 'Probation completed successfully and funding allocations updated',
            'data' => [
                'employment' => new EmploymentResource($result['employment']),
                'updated_allocations' => $result['updated_allocations'],
            ],
        ]);
    }

    #[OA\Post(
        path: '/employments/{id}/probation-status',
        summary: 'Update probation status for an employment',
        description: 'Allows HR to manually mark probation as passed or failed with optional reason/notes',
        operationId: 'updateProbationStatus',
        tags: ['Employments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Employment ID', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['action'],
                properties: [
                    new OA\Property(property: 'action', type: 'string', enum: ['passed', 'failed']),
                    new OA\Property(property: 'decision_date', type: 'string', format: 'date', nullable: true),
                    new OA\Property(property: 'reason', type: 'string', nullable: true),
                    new OA\Property(property: 'notes', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Probation status updated successfully'),
            new OA\Response(response: 404, description: 'Employment not found'),
            new OA\Response(response: 422, description: 'Unable to update probation status'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    /**
     * Update the probation status (passed/failed) for an employment.
     */
    public function updateProbationStatus(UpdateProbationStatusRequest $request, Employment $employment): JsonResponse
    {
        $result = $this->employmentService->updateProbationStatus($employment, $request->validated());

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'employment' => new EmploymentResource($result['employment']),
                'probation_history' => $result['probation_history'],
            ],
        ]);
    }

    #[OA\Put(
        path: '/employments/{id}',
        operationId: 'updateEmployment',
        tags: ['Employments'],
        summary: 'Update employment record',
        description: 'Updates an employment record',
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID of employment record to update', schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/Employment')
        ),
        responses: [
            new OA\Response(response: 200, description: 'Employment updated successfully'),
            new OA\Response(response: 400, description: 'Bad request'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
            new OA\Response(response: 404, description: 'Employment not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    /**
     * Update an existing employment record.
     */
    public function update(UpdateEmploymentRequest $request, Employment $employment): JsonResponse
    {
        $result = $this->employmentService->update($employment, $request->validated());

        $message = $result->earlyTermination
            ? 'Employment terminated during probation. Allocations marked as inactive.'
            : 'Employment updated successfully';

        return EmploymentResource::make($result->employment)
            ->additional(['success' => true, 'message' => $message])
            ->response();
    }

    #[OA\Delete(
        path: '/employments/{id}',
        summary: 'Delete an employment record',
        description: 'Deletes an employment record by ID',
        operationId: 'deleteEmployment',
        tags: ['Employments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID of employment record to delete', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Employment deleted successfully'),
            new OA\Response(response: 404, description: 'Employment not found'),
        ]
    )]
    /**
     * Delete an employment record.
     */
    public function destroy(Employment $employment): JsonResponse
    {
        $this->employmentService->delete($employment);

        return $this->successResponse(null, 'Employment deleted successfully');
    }

    #[OA\Get(
        path: '/employments/{id}/probation-history',
        summary: 'Get probation history for employment',
        description: 'Returns probation records history including extensions, passed/failed events',
        operationId: 'getProbationHistory',
        tags: ['Employments'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Employment ID', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Probation history retrieved successfully'),
            new OA\Response(response: 404, description: 'Employment not found'),
            new OA\Response(response: 500, description: 'Server error'),
        ]
    )]
    /**
     * Retrieve the probation history for an employment record.
     */
    public function probationHistory(Employment $employment): JsonResponse
    {
        $history = $this->employmentService->getProbationHistory($employment);

        return response()->json([
            'success' => true,
            'message' => 'Probation history retrieved successfully',
            'data' => $history,
        ]);
    }

    /**
     * Get all funding allocations for an employment (Active + Inactive).
     * Closed allocations are excluded — they are system-managed replacements.
     */
    public function fundingAllocations(Employment $employment): JsonResponse
    {
        $allocations = EmployeeFundingAllocation::with([
            'grantItem:id,grant_id,grant_position,budgetline_code',
            'grantItem.grant:id,name,code',
        ])
            ->where('employment_id', $employment->id)
            ->whereIn('status', [FundingAllocationStatus::Active, FundingAllocationStatus::Inactive])
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->get();

        return $this->successResponse([
            'funding_allocations' => EmployeeFundingAllocationResource::collection($allocations),
        ], 'Funding allocations retrieved successfully');
    }

    private function paginationMeta(LengthAwarePaginator $p): array
    {
        return [
            'current_page' => $p->currentPage(),
            'per_page' => $p->perPage(),
            'total' => $p->total(),
            'last_page' => $p->lastPage(),
            'from' => $p->firstItem(),
            'to' => $p->lastItem(),
            'has_more_pages' => $p->hasMorePages(),
        ];
    }
}
