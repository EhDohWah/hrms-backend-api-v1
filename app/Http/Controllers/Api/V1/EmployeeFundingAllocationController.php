<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\FundingAllocation\BatchUpdateAllocationsRequest;
use App\Http\Requests\FundingAllocation\BulkDeactivateAllocationsRequest;
use App\Http\Requests\FundingAllocation\CalculatePreviewRequest;
use App\Http\Requests\FundingAllocation\IndexFundingAllocationRequest;
use App\Http\Requests\FundingAllocation\StoreFundingAllocationRequest;
use App\Http\Requests\FundingAllocation\UpdateEmployeeAllocationsRequest;
use App\Http\Requests\FundingAllocation\UpdateFundingAllocationRequest;
use App\Http\Requests\FundingAllocation\UploadFundingAllocationRequest;
use App\Http\Resources\EmployeeFundingAllocationResource;
use App\Models\EmployeeFundingAllocation;
use App\Services\EmployeeFundingAllocationService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EmployeeFundingAllocationController extends BaseApiController
{
    public function __construct(
        private readonly EmployeeFundingAllocationService $service
    ) {}

    /**
     * List employee funding allocations with optional filters.
     */
    public function index(IndexFundingAllocationRequest $request): JsonResponse
    {
        $result = $this->service->list($request->validated());

        return EmployeeFundingAllocationResource::collection($result['paginator'])
            ->additional([
                'success' => true,
                'message' => 'Employee funding allocations retrieved successfully',
            ])
            ->response();
    }

    /**
     * Get allocations by grant item ID.
     */
    public function byGrantItem(int $grantItemId): JsonResponse
    {
        $result = $this->service->byGrantItem($grantItemId);

        return $this->successResponse([
            'allocations' => EmployeeFundingAllocationResource::collection($result['allocations']),
            'total_allocations' => $result['total_allocations'],
            'active_allocations' => $result['active_allocations'],
        ], 'Employee funding allocations retrieved successfully');
    }

    /**
     * Show a single allocation.
     */
    public function show(EmployeeFundingAllocation $allocation): JsonResponse
    {
        $allocation->load([
            'employee:id,staff_id,first_name_en,last_name_en',
            'employment:id,start_date,end_probation_date,department_id,position_id',
            'employment.department:id,name',
            'employment.position:id,title',
            'grantItem.grant:id,name,code',
        ]);

        return EmployeeFundingAllocationResource::make($allocation)
            ->additional(['success' => true, 'message' => 'Employee funding allocation retrieved successfully'])
            ->response();
    }

    /**
     * Create funding allocations with smart FTE validation.
     */
    public function store(StoreFundingAllocationRequest $request): JsonResponse
    {
        $result = $this->service->store($request->validated(), $request->user());

        $response = [
            'allocations' => EmployeeFundingAllocationResource::collection($result['allocations']),
            'total_created' => $result['total_created'],
            'salary_info' => $result['salary_info'],
        ];

        if ($result['warnings']) {
            $response['warnings'] = $result['warnings'];
        }

        return $this->createdResponse($response, 'Employee funding allocations created successfully');
    }

    /**
     * Calculate allocation amount preview (no persistence).
     */
    public function calculatePreview(CalculatePreviewRequest $request): JsonResponse
    {
        $result = $this->service->calculatePreview($request->validated());

        return $this->successResponse($result, 'Allocation preview calculated successfully');
    }

    /**
     * Update a single funding allocation.
     */
    public function update(UpdateFundingAllocationRequest $request, EmployeeFundingAllocation $allocation): JsonResponse
    {
        $allocation = $this->service->update($allocation, $request->validated(), $request->user());

        return EmployeeFundingAllocationResource::make($allocation)
            ->additional(['success' => true, 'message' => 'Employee funding allocation updated successfully'])
            ->response();
    }

    /**
     * Delete a funding allocation.
     */
    public function destroy(EmployeeFundingAllocation $allocation): JsonResponse
    {
        $this->service->destroy($allocation, request()->user());

        return $this->successResponse(null, 'Employee funding allocation deactivated successfully');
    }

    /**
     * Batch update: atomically process updates, creates, and deletes.
     */
    public function batchUpdate(BatchUpdateAllocationsRequest $request): JsonResponse
    {
        $result = $this->service->batchUpdate($request->validated(), $request->user());

        return $this->successResponse([
            'allocations' => EmployeeFundingAllocationResource::collection($result['allocations']),
            'summary' => $result['summary'],
        ], 'Allocations updated successfully');
    }

    /**
     * Get all active allocations for a specific employee.
     */
    public function employeeAllocations(int $employeeId): JsonResponse
    {
        $result = $this->service->employeeAllocations($employeeId);

        return $this->successResponse([
            'employee' => $result['employee'],
            'total_allocations' => $result['total_allocations'],
            'total_effort' => $result['total_effort'],
            'allocations' => EmployeeFundingAllocationResource::collection($result['allocations']),
        ], 'Employee funding allocations retrieved successfully');
    }

    /**
     * Get grant structure for allocation forms.
     */
    public function grantStructure(): JsonResponse
    {
        $structure = $this->service->grantStructure();

        return $this->successResponse($structure, 'Grant structure retrieved successfully');
    }

    /**
     * Bulk deactivate allocations.
     */
    public function bulkDeactivate(BulkDeactivateAllocationsRequest $request): JsonResponse
    {
        $updatedCount = $this->service->bulkDeactivate($request->validated()['allocation_ids'], $request->user());

        return $this->successResponse(
            ['deactivated_count' => $updatedCount],
            'Employee funding allocations deactivated successfully'
        );
    }

    /**
     * Replace all active allocations for an employee/employment.
     */
    public function updateEmployeeAllocations(UpdateEmployeeAllocationsRequest $request, int $employeeId): JsonResponse
    {
        $result = $this->service->updateEmployeeAllocations($employeeId, $request->validated(), $request->user());

        return $this->successResponse([
            'allocations' => EmployeeFundingAllocationResource::collection($result['allocations']),
            'total_created' => $result['total_created'],
        ], 'Employee funding allocations updated successfully');
    }

    /**
     * Upload funding allocation data from Excel file.
     */
    public function upload(UploadFundingAllocationRequest $request): JsonResponse
    {
        $result = $this->service->upload($request->file('file'), $request->user()->id);

        return $this->successResponse(
            $result,
            'Employee funding allocation import started successfully. You will receive a notification when the import is complete.',
            202
        );
    }

    /**
     * Download grant items reference file.
     */
    public function downloadGrantItemsReference(): BinaryFileResponse
    {
        return $this->service->downloadGrantItemsReference();
    }

    /**
     * Download funding allocation import template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return $this->service->downloadTemplate();
    }
}
