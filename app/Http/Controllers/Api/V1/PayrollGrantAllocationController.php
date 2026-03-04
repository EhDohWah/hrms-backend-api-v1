<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StorePayrollGrantAllocationRequest;
use App\Http\Requests\UpdatePayrollGrantAllocationRequest;
use App\Http\Resources\PayrollGrantAllocationResource;
use App\Models\PayrollGrantAllocation;
use Illuminate\Http\JsonResponse;

/**
 * Manages payroll grant allocation snapshot records.
 *
 * These records capture a point-in-time snapshot of funding allocations
 * when a payroll is created, used for budget history reporting.
 */
class PayrollGrantAllocationController extends BaseApiController
{
    /**
     * List all payroll grant allocations.
     */
    public function index(): JsonResponse
    {
        $allocations = PayrollGrantAllocation::with('fundingAllocation', 'grantItem')->get();

        return $this->successResponse(
            PayrollGrantAllocationResource::collection($allocations),
            'Payroll grant allocations retrieved successfully'
        );
    }

    /**
     * Show a single payroll grant allocation.
     */
    public function show(PayrollGrantAllocation $allocation): JsonResponse
    {
        $allocation->load('payroll', 'fundingAllocation', 'grantItem');

        return PayrollGrantAllocationResource::make($allocation)
            ->additional(['success' => true, 'message' => 'Payroll grant allocation retrieved successfully'])
            ->response();
    }

    /**
     * Create a new payroll grant allocation.
     */
    public function store(StorePayrollGrantAllocationRequest $request): JsonResponse
    {
        $allocation = PayrollGrantAllocation::create($request->validated());

        return PayrollGrantAllocationResource::make($allocation->load('fundingAllocation', 'grantItem'))
            ->additional(['success' => true, 'message' => 'Payroll grant allocation created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an existing payroll grant allocation.
     */
    public function update(UpdatePayrollGrantAllocationRequest $request, PayrollGrantAllocation $allocation): JsonResponse
    {
        $allocation->update($request->validated());

        return PayrollGrantAllocationResource::make($allocation->fresh(['fundingAllocation', 'grantItem']))
            ->additional(['success' => true, 'message' => 'Payroll grant allocation updated successfully'])
            ->response();
    }

    /**
     * Delete a payroll grant allocation.
     */
    public function destroy(PayrollGrantAllocation $allocation): JsonResponse
    {
        $allocation->delete();

        return $this->successResponse(null, 'Payroll grant allocation deleted successfully');
    }
}
