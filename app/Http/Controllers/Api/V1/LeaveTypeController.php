<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\BatchSafeDeleteRequest;
use App\Http\Requests\LeaveType\IndexLeaveTypeRequest;
use App\Http\Requests\LeaveType\StoreLeaveTypeRequest;
use App\Http\Requests\LeaveType\UpdateLeaveTypeRequest;
use App\Http\Resources\LeaveTypeResource;
use App\Models\LeaveType;
use App\Services\LeaveTypeService;
use Illuminate\Http\JsonResponse;

/**
 * Manages leave type CRUD operations.
 */
class LeaveTypeController extends BaseApiController
{
    public function __construct(
        private readonly LeaveTypeService $leaveTypeService,
    ) {}

    /**
     * List leave types with search and pagination.
     */
    public function index(IndexLeaveTypeRequest $request): JsonResponse
    {
        $leaveTypes = $this->leaveTypeService->list($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Leave types retrieved successfully',
            'data' => LeaveTypeResource::collection($leaveTypes->items()),
            'pagination' => [
                'current_page' => $leaveTypes->currentPage(),
                'per_page' => $leaveTypes->perPage(),
                'total' => $leaveTypes->total(),
                'last_page' => $leaveTypes->lastPage(),
            ],
        ]);
    }

    /**
     * Get all leave types for dropdown selection (non-paginated).
     */
    public function options(): JsonResponse
    {
        $leaveTypes = $this->leaveTypeService->options();

        return response()->json([
            'success' => true,
            'message' => 'Leave types retrieved successfully',
            'data' => LeaveTypeResource::collection($leaveTypes),
            'total' => $leaveTypes->count(),
        ]);
    }

    /**
     * Create a new leave type and auto-apply to all employees.
     */
    public function store(StoreLeaveTypeRequest $request): JsonResponse
    {
        $result = $this->leaveTypeService->create($request->validated());

        return LeaveTypeResource::make($result['leave_type'])
            ->additional([
                'success' => true,
                'message' => "Leave type created successfully and applied to {$result['balances_created']} employees",
                'balances_created' => $result['balances_created'],
            ])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update a leave type.
     */
    public function update(UpdateLeaveTypeRequest $request, LeaveType $leaveType): JsonResponse
    {
        $leaveType = $this->leaveTypeService->update($leaveType, $request->validated());

        return LeaveTypeResource::make($leaveType)
            ->additional(['success' => true, 'message' => 'Leave type updated successfully'])
            ->response();
    }

    /**
     * Delete a leave type if not in use.
     */
    public function destroy(LeaveType $leaveType): JsonResponse
    {
        $this->leaveTypeService->delete($leaveType);

        return $this->successResponse(null, 'Leave type deleted successfully');
    }

    /**
     * Batch delete multiple leave types.
     */
    public function destroyBatch(BatchSafeDeleteRequest $request): JsonResponse
    {
        $succeeded = [];
        $failed = [];

        foreach ($request->validated()['ids'] as $id) {
            try {
                $record = \App\Models\LeaveType::findOrFail($id);
                $this->leaveTypeService->delete($record);
                $succeeded[] = ['id' => $id];
            } catch (\Exception $e) {
                $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        $failureCount = count($failed);
        $successCount = count($succeeded);

        return response()->json([
            'success' => $failureCount === 0,
            'message' => "{$successCount} leave type(s) deleted"
                .($failureCount > 0 ? ", {$failureCount} failed" : ''),
            'data' => ['succeeded' => $succeeded, 'failed' => $failed],
        ], $failureCount === 0 ? 200 : 207);
    }
}
