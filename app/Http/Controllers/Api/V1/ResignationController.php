<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\AcknowledgeResignationRequest;
use App\Http\Requests\BatchSafeDeleteRequest;
use App\Http\Requests\IndexResignationRequest;
use App\Http\Requests\SearchEmployeeResignationRequest;
use App\Http\Requests\StoreResignationRequest;
use App\Http\Requests\UpdateResignationRequest;
use App\Http\Resources\ResignationResource;
use App\Models\Resignation;
use App\Services\ResignationService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * ResignationController
 *
 * Manages employee resignation CRUD operations and workflow.
 *
 * Standard RESTful Methods:
 * - index()   : List all resignations with filtering
 * - show()    : Get single resignation by ID
 * - store()   : Create new resignation
 * - update()  : Update resignation
 * - destroy() : Soft delete resignation
 * - acknowledge() : Acknowledge or reject a resignation
 * - searchEmployees() : Search employees for resignation assignment
 * - generateRecommendationLetter() : Generate PDF recommendation letter
 */
#[OA\Tag(
    name: 'Resignations',
    description: 'Employee resignation management endpoints'
)]
class ResignationController extends BaseApiController
{
    public function __construct(
        private readonly ResignationService $resignationService,
    ) {}

    /**
     * Display a listing of resignations with advanced filtering and pagination.
     */
    #[OA\Get(
        path: '/api/v1/resignations',
        summary: 'Get list of resignations',
        description: 'Returns paginated list of resignations with advanced filtering, search capabilities',
        operationId: 'getResignations',
        tags: ['Resignations'],
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1), description: 'Page number')]
    #[OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100), description: 'Items per page')]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Search by employee name, staff ID, or reason')]
    #[OA\Parameter(name: 'acknowledgement_status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['Pending', 'Acknowledged', 'Rejected']), description: 'Filter by acknowledgement status')]
    #[OA\Parameter(name: 'department_id', in: 'query', schema: new OA\Schema(type: 'integer'), description: 'Filter by department ID')]
    #[OA\Parameter(name: 'reason', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Filter by resignation reason')]
    #[OA\Parameter(name: 'sort_by', in: 'query', schema: new OA\Schema(type: 'string', enum: ['resignation_date', 'last_working_date', 'acknowledgement_status', 'created_at']), description: 'Sort field')]
    #[OA\Parameter(name: 'sort_order', in: 'query', schema: new OA\Schema(type: 'string', enum: ['asc', 'desc']), description: 'Sort order')]
    #[OA\Response(
        response: 200,
        description: 'Resignations retrieved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Resignations retrieved successfully'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Resignation')),
                new OA\Property(property: 'meta', type: 'object'),
            ]
        )
    )]
    public function index(IndexResignationRequest $request): JsonResponse
    {
        $paginator = $this->resignationService->list($request->validated());

        return ResignationResource::collection($paginator)
            ->additional([
                'success' => true,
                'message' => 'Resignations retrieved successfully',
            ])
            ->response();
    }

    /**
     * Display the specified resignation with full relationships.
     */
    #[OA\Get(
        path: '/api/v1/resignations/{id}',
        summary: 'Get resignation by ID',
        description: 'Returns detailed information about a specific resignation',
        operationId: 'getResignation',
        tags: ['Resignations'],
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Resignation retrieved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Resignation retrieved successfully'),
                new OA\Property(property: 'data', ref: '#/components/schemas/Resignation'),
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Resignation not found')]
    public function show(Resignation $resignation): JsonResponse
    {
        $resignation = $this->resignationService->show($resignation);

        return ResignationResource::make($resignation)
            ->additional(['success' => true, 'message' => 'Resignation retrieved successfully'])
            ->response();
    }

    /**
     * Store a newly created resignation.
     */
    #[OA\Post(
        path: '/api/v1/resignations',
        summary: 'Create a new resignation',
        description: 'Creates a new resignation record with automatic employee data population',
        operationId: 'storeResignation',
        tags: ['Resignations'],
        security: [['bearerAuth' => []]]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['employee_id', 'resignation_date', 'last_working_date', 'reason'],
            properties: [
                new OA\Property(property: 'employee_id', type: 'integer', example: 1),
                new OA\Property(property: 'department_id', type: 'integer', example: 5, description: 'Auto-populated if not provided'),
                new OA\Property(property: 'position_id', type: 'integer', example: 12, description: 'Auto-populated if not provided'),
                new OA\Property(property: 'resignation_date', type: 'string', format: 'date', example: '2024-02-01'),
                new OA\Property(property: 'last_working_date', type: 'string', format: 'date', example: '2024-02-29'),
                new OA\Property(property: 'reason', type: 'string', maxLength: 50, example: 'Career Advancement'),
                new OA\Property(property: 'reason_details', type: 'string', example: 'Accepted a better position'),
                new OA\Property(property: 'acknowledgement_status', type: 'string', enum: ['Pending', 'Acknowledged', 'Rejected'], example: 'Pending'),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Resignation created successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function store(StoreResignationRequest $request): JsonResponse
    {
        $resignation = $this->resignationService->create($request->validated());

        return ResignationResource::make($resignation)
            ->additional(['success' => true, 'message' => 'Resignation created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update the specified resignation.
     */
    #[OA\Put(
        path: '/api/v1/resignations/{id}',
        summary: 'Update resignation',
        description: 'Updates an existing resignation record',
        operationId: 'updateResignation',
        tags: ['Resignations'],
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Resignation updated successfully')]
    #[OA\Response(response: 404, description: 'Resignation not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function update(UpdateResignationRequest $request, Resignation $resignation): JsonResponse
    {
        $resignation = $this->resignationService->update($resignation, $request->validated());

        return ResignationResource::make($resignation)
            ->additional(['success' => true, 'message' => 'Resignation updated successfully'])
            ->response();
    }

    /**
     * Remove the specified resignation (soft delete).
     */
    #[OA\Delete(
        path: '/api/v1/resignations/{id}',
        summary: 'Delete resignation',
        description: 'Soft deletes a resignation record',
        operationId: 'deleteResignation',
        tags: ['Resignations'],
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Resignation deleted successfully')]
    #[OA\Response(response: 404, description: 'Resignation not found')]
    public function destroy(Resignation $resignation): JsonResponse
    {
        $this->resignationService->delete($resignation);

        return $this->successResponse(null, 'Resignation deleted successfully');
    }

    /**
     * Acknowledge or reject a resignation.
     */
    #[OA\Put(
        path: '/api/v1/resignations/{id}/acknowledge',
        summary: 'Acknowledge or reject resignation',
        description: 'Updates resignation status to acknowledged or rejected with user tracking',
        operationId: 'acknowledgeResignation',
        tags: ['Resignations'],
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['action'],
            properties: [
                new OA\Property(property: 'action', type: 'string', enum: ['acknowledge', 'reject'], example: 'acknowledge'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Resignation acknowledged/rejected successfully')]
    #[OA\Response(response: 400, description: 'Only pending resignations can be acknowledged or rejected')]
    #[OA\Response(response: 404, description: 'Resignation not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function acknowledge(AcknowledgeResignationRequest $request, Resignation $resignation): JsonResponse
    {
        $action = $request->validated()['action'];
        $resignation = $this->resignationService->acknowledge($resignation, $action);

        $message = $action === 'acknowledge'
            ? 'Resignation acknowledged successfully'
            : 'Resignation rejected successfully';

        return ResignationResource::make($resignation)
            ->additional(['success' => true, 'message' => $message])
            ->response();
    }

    /**
     * Search employees for resignation assignment.
     */
    #[OA\Get(
        path: '/api/v1/resignations/search-employees',
        summary: 'Search employees for resignation',
        description: 'Search employees by name or staff ID for resignation assignment',
        operationId: 'searchEmployeesForResignation',
        tags: ['Resignations'],
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string'), description: 'Search by name or staff ID')]
    #[OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 50), description: 'Max results')]
    #[OA\Response(
        response: 200,
        description: 'Employees found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Employees found'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
            ]
        )
    )]
    public function searchEmployees(SearchEmployeeResignationRequest $request): JsonResponse
    {
        $employees = $this->resignationService->searchEmployees($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Employees found',
            'data' => $employees,
        ]);
    }

    /**
     * Generate a recommendation letter PDF for a resigned employee.
     */
    #[OA\Get(
        path: '/api/v1/resignations/{id}/recommendation-letter',
        summary: 'Generate recommendation letter PDF',
        description: 'Generates a recommendation letter PDF for acknowledged resignations',
        operationId: 'generateRecommendationLetter',
        tags: ['Resignations'],
        security: [['bearerAuth' => []]]
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'PDF generated successfully',
        content: new OA\MediaType(
            mediaType: 'application/pdf',
            schema: new OA\Schema(type: 'string', format: 'binary')
        )
    )]
    #[OA\Response(response: 400, description: 'Resignation not acknowledged')]
    #[OA\Response(response: 404, description: 'Resignation not found')]
    public function generateRecommendationLetter(Resignation $resignation)
    {
        $pdfResponse = $this->resignationService->generateRecommendationLetter($resignation);

        return $pdfResponse
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Batch delete multiple resignations.
     */
    public function destroyBatch(BatchSafeDeleteRequest $request): JsonResponse
    {
        $succeeded = [];
        $failed = [];

        foreach ($request->validated()['ids'] as $id) {
            try {
                $record = \App\Models\Resignation::findOrFail($id);
                $this->resignationService->delete($record);
                $succeeded[] = ['id' => $id];
            } catch (\Exception $e) {
                $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        $failureCount = count($failed);
        $successCount = count($succeeded);

        return response()->json([
            'success' => $failureCount === 0,
            'message' => "{$successCount} resignation(s) deleted"
                .($failureCount > 0 ? ", {$failureCount} failed" : ''),
            'data' => ['succeeded' => $succeeded, 'failed' => $failed],
        ], $failureCount === 0 ? 200 : 207);
    }
}
