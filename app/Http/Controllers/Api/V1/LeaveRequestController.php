<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\BatchSafeDeleteRequest;
use App\Http\Requests\CalculateDaysLeaveRequestRequest;
use App\Http\Requests\CheckOverlapLeaveRequestRequest;
use App\Http\Requests\IndexLeaveRequestRequest;
use App\Http\Requests\StoreLeaveRequestRequest;
use App\Http\Requests\UpdateLeaveRequestRequest;
use App\Http\Resources\LeaveRequestResource;
use App\Models\LeaveRequest;
use App\Services\LeaveRequestService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

/**
 * LeaveRequestController
 *
 * Manages leave request CRUD operations.
 *
 * Standard RESTful Methods:
 * - index()   : List all leave requests with filtering
 * - show()    : Get single leave request by ID
 * - store()   : Create new leave request
 * - update()  : Update leave request
 * - destroy() : Delete leave request
 * - calculateDays() : Preview working days for a date range
 * - checkOverlap()  : Check for overlapping leave requests
 *
 * Related Controllers:
 * - LeaveTypeController       : For managing leave types
 * - LeaveBalanceController    : For managing leave balances
 * - HolidayController         : For managing organization holidays
 * - LeaveCalculationController: For working day calculations
 */
#[OA\Tag(
    name: 'Leave Requests',
    description: 'API Endpoints for managing leave requests'
)]
class LeaveRequestController extends BaseApiController
{
    public function __construct(
        private readonly LeaveRequestService $leaveRequestService,
    ) {}

    /**
     * Display a listing of leave requests with filtering and sorting
     */
    #[OA\Get(
        path: '/leave-requests',
        summary: 'Get paginated leave requests with advanced filtering',
        tags: ['Leave Requests'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(name: 'page', in: 'query', description: 'Page number', schema: new OA\Schema(type: 'integer', minimum: 1))]
    #[OA\Parameter(name: 'per_page', in: 'query', description: 'Items per page', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100))]
    #[OA\Parameter(name: 'search', in: 'query', description: 'Search by staff ID or employee name', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'from', in: 'query', description: 'Start date filter', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'to', in: 'query', description: 'End date filter', schema: new OA\Schema(type: 'string', format: 'date'))]
    #[OA\Parameter(name: 'leave_types', in: 'query', description: 'Comma-separated leave type IDs', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'status', in: 'query', description: 'Request status', schema: new OA\Schema(type: 'string', enum: ['pending', 'approved', 'declined', 'cancelled']))]
    #[OA\Parameter(name: 'supervisor_approved', in: 'query', description: 'Filter by supervisor approval status', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'hr_site_admin_approved', in: 'query', description: 'Filter by HR/Site Admin approval status', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'sort_by', in: 'query', description: 'Sort option', schema: new OA\Schema(type: 'string', enum: ['recently_added', 'ascending', 'descending', 'last_month', 'last_7_days']))]
    #[OA\Response(
        response: 200,
        description: 'Leave requests retrieved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Leave requests retrieved successfully'),
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/LeaveRequest')),
                new OA\Property(property: 'meta', type: 'object'),
                new OA\Property(property: 'statistics', type: 'object'),
            ]
        )
    )]
    public function index(IndexLeaveRequestRequest $request): JsonResponse
    {
        $paginator = $this->leaveRequestService->list($request->validated());

        return LeaveRequestResource::collection($paginator)
            ->additional([
                'success' => true,
                'message' => 'Leave requests retrieved successfully',
                'statistics' => $this->leaveRequestService->getStatistics(),
            ])
            ->response();
    }

    /**
     * Display the specified leave request with full relationships
     */
    #[OA\Get(
        path: '/leave-requests/{id}',
        summary: 'Get a specific leave request',
        tags: ['Leave Requests'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(
        response: 200,
        description: 'Leave request retrieved successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Leave request retrieved successfully'),
                new OA\Property(property: 'data', ref: '#/components/schemas/LeaveRequest'),
            ]
        )
    )]
    #[OA\Response(response: 404, description: 'Leave request not found')]
    public function show(LeaveRequest $leaveRequest): JsonResponse
    {
        $leaveRequest = $this->leaveRequestService->show($leaveRequest);

        return LeaveRequestResource::make($leaveRequest)
            ->additional(['success' => true, 'message' => 'Leave request retrieved successfully'])
            ->response();
    }

    /**
     * Store a newly created leave request with multiple leave types
     */
    #[OA\Post(
        path: '/leave-requests',
        summary: 'Create a new leave request with multiple leave types',
        description: 'Create a leave request that can include multiple leave types in a single submission.',
        tags: ['Leave Requests'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['employee_id', 'start_date', 'end_date', 'items'],
            properties: [
                new OA\Property(property: 'employee_id', type: 'integer', example: 123),
                new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2025-01-15'),
                new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2025-01-17'),
                new OA\Property(property: 'reason', type: 'string', example: 'Family emergency'),
                new OA\Property(property: 'status', type: 'string', enum: ['pending', 'approved', 'declined'], example: 'pending'),
                new OA\Property(
                    property: 'items',
                    type: 'array',
                    items: new OA\Items(
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'leave_type_id', type: 'integer', example: 1),
                            new OA\Property(property: 'days', type: 'number', format: 'float', example: 2),
                        ]
                    )
                ),
                new OA\Property(property: 'supervisor_approved', type: 'boolean', example: false),
                new OA\Property(property: 'supervisor_approved_date', type: 'string', format: 'date'),
                new OA\Property(property: 'hr_site_admin_approved', type: 'boolean', example: false),
                new OA\Property(property: 'hr_site_admin_approved_date', type: 'string', format: 'date'),
                new OA\Property(property: 'attachment_notes', type: 'string'),
            ]
        )
    )]
    #[OA\Response(response: 201, description: 'Leave request created successfully')]
    #[OA\Response(response: 422, description: 'Validation error')]
    #[OA\Response(response: 400, description: 'Insufficient leave balance')]
    public function store(StoreLeaveRequestRequest $request): JsonResponse
    {
        $leaveRequest = $this->leaveRequestService->create($request->validated());

        return LeaveRequestResource::make($leaveRequest)
            ->additional(['success' => true, 'message' => 'Leave request created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update the specified leave request with multiple leave types and approval information
     */
    #[OA\Put(
        path: '/leave-requests/{id}',
        summary: 'Update a leave request',
        tags: ['Leave Requests'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Leave request updated successfully')]
    #[OA\Response(response: 404, description: 'Leave request not found')]
    #[OA\Response(response: 422, description: 'Validation error')]
    #[OA\Response(response: 400, description: 'Insufficient leave balance')]
    public function update(UpdateLeaveRequestRequest $request, LeaveRequest $leaveRequest): JsonResponse
    {
        $leaveRequest = $this->leaveRequestService->update($leaveRequest, $request->validated());

        return LeaveRequestResource::make($leaveRequest)
            ->additional(['success' => true, 'message' => 'Leave request updated successfully'])
            ->response();
    }

    /**
     * Remove the specified leave request with balance restoration
     */
    #[OA\Delete(
        path: '/leave-requests/{id}',
        summary: 'Delete a leave request',
        description: 'Deletes a leave request and restores leave balance if the request was approved.',
        tags: ['Leave Requests'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: 'Leave request deleted successfully')]
    #[OA\Response(response: 404, description: 'Leave request not found')]
    public function destroy(LeaveRequest $leaveRequest): JsonResponse
    {
        $this->leaveRequestService->delete($leaveRequest);

        return $this->successResponse(null, 'Leave request deleted successfully');
    }

    /**
     * Calculate working days for a date range (preview before submission).
     */
    #[OA\Post(
        path: '/leave-requests/calculate-days',
        summary: 'Calculate working days for leave date range',
        description: 'Returns the number of working days (excluding weekends and holidays) for the given date range.',
        tags: ['Leave Requests'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['start_date', 'end_date'],
            properties: [
                new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2025-01-15'),
                new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2025-01-22'),
                new OA\Property(property: 'detailed', type: 'boolean', example: false, description: 'Include detailed breakdown of excluded dates'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Working days calculated successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'working_days', type: 'integer', example: 5),
                        new OA\Property(property: 'total_calendar_days', type: 'integer', example: 8),
                    ]
                ),
            ]
        )
    )]
    public function calculateDays(CalculateDaysLeaveRequestRequest $request): JsonResponse
    {
        $result = $this->leaveRequestService->calculateDays($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Working days calculated successfully',
            'data' => $result,
        ]);
    }

    /**
     * Check for overlapping leave requests before submission (preview)
     */
    #[OA\Post(
        path: '/leave-requests/check-overlap',
        summary: 'Check for overlapping leave requests',
        description: 'Checks if the given date range overlaps with any existing pending or approved leave requests.',
        tags: ['Leave Requests'],
        security: [['bearerAuth' => []]],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['employee_id', 'start_date', 'end_date'],
            properties: [
                new OA\Property(property: 'employee_id', type: 'integer', example: 123),
                new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2025-01-15'),
                new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2025-01-17'),
                new OA\Property(property: 'exclude_request_id', type: 'integer', example: null, description: 'Request ID to exclude (for updates)'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Overlap check completed',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'data',
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'has_overlap', type: 'boolean', example: false),
                        new OA\Property(property: 'conflicts', type: 'array', items: new OA\Items(type: 'object')),
                        new OA\Property(property: 'message', type: 'string'),
                    ]
                ),
            ]
        )
    )]
    public function checkOverlap(CheckOverlapLeaveRequestRequest $request): JsonResponse
    {
        $overlapCheck = $this->leaveRequestService->checkOverlap($request->validated());

        return response()->json([
            'success' => true,
            'message' => $overlapCheck['has_overlap'] ? 'Overlapping requests found' : 'No overlapping requests',
            'data' => $overlapCheck,
        ]);
    }

    /**
     * Batch delete multiple leave requests.
     */
    public function destroyBatch(BatchSafeDeleteRequest $request): JsonResponse
    {
        $succeeded = [];
        $failed = [];

        foreach ($request->validated()['ids'] as $id) {
            try {
                $record = \App\Models\LeaveRequest::findOrFail($id);
                $this->leaveRequestService->delete($record);
                $succeeded[] = ['id' => $id];
            } catch (\Exception $e) {
                $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        $failureCount = count($failed);
        $successCount = count($succeeded);

        return response()->json([
            'success' => $failureCount === 0,
            'message' => "{$successCount} leave request(s) deleted"
                .($failureCount > 0 ? ", {$failureCount} failed" : ''),
            'data' => ['succeeded' => $succeeded, 'failed' => $failed],
        ], $failureCount === 0 ? 200 : 207);
    }
}
