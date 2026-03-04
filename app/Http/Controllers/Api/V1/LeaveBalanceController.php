<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\LeaveBalance\IndexLeaveBalanceRequest;
use App\Http\Requests\LeaveBalance\ShowLeaveBalanceRequest;
use App\Http\Requests\LeaveBalance\StoreLeaveBalanceRequest;
use App\Http\Requests\LeaveBalance\UpdateLeaveBalanceRequest;
use App\Http\Resources\LeaveBalanceResource;
use App\Services\LeaveBalanceService;
use Illuminate\Http\JsonResponse;

/**
 * Manages leave balance CRUD operations.
 */
class LeaveBalanceController extends BaseApiController
{
    public function __construct(
        private readonly LeaveBalanceService $leaveBalanceService,
    ) {}

    /**
     * List leave balances with filtering, sorting, and pagination.
     */
    public function index(IndexLeaveBalanceRequest $request): JsonResponse
    {
        $result = $this->leaveBalanceService->list($request->validated());
        $leaveBalances = $result['leave_balances'];

        return response()->json([
            'success' => true,
            'message' => 'Leave balances retrieved successfully',
            'data' => LeaveBalanceResource::collection($leaveBalances->items()),
            'pagination' => [
                'current_page' => $leaveBalances->currentPage(),
                'per_page' => $leaveBalances->perPage(),
                'total' => $leaveBalances->total(),
                'last_page' => $leaveBalances->lastPage(),
                'from' => $leaveBalances->firstItem(),
                'to' => $leaveBalances->lastItem(),
                'has_more_pages' => $leaveBalances->hasMorePages(),
            ],
        ]);
    }

    /**
     * Get leave balance for a specific employee and leave type.
     */
    public function show(string $employeeId, string $leaveTypeId, ShowLeaveBalanceRequest $request): JsonResponse
    {
        $data = $this->leaveBalanceService->show((int) $employeeId, (int) $leaveTypeId, $request->validated());

        if ($data === null) {
            return response()->json([
                'success' => false,
                'message' => 'Leave balance not found for this employee and leave type',
            ], 404);
        }

        return $this->successResponse($data, 'Leave balance retrieved successfully');
    }

    /**
     * Create a new leave balance.
     */
    public function store(StoreLeaveBalanceRequest $request): JsonResponse
    {
        $result = $this->leaveBalanceService->store($request->validated());

        if (! $result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Leave balance created successfully',
            'data' => $result['data'],
        ], 201);
    }

    /**
     * Update a leave balance with automatic remaining_days calculation.
     */
    public function update(UpdateLeaveBalanceRequest $request, string $id): JsonResponse
    {
        $leaveBalance = $this->leaveBalanceService->update((int) $id, $request->validated());

        return $this->successResponse($leaveBalance, 'Leave balance updated successfully');
    }
}
