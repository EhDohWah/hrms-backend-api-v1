<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\BatchSafeDeleteRequest;
use App\Http\Requests\BulkPayroll\StoreBulkPayrollRequest;
use App\Http\Requests\Payroll\BudgetHistoryRequest;
use App\Http\Requests\Payroll\IndexPayrollRequest;
use App\Http\Requests\Payroll\PreviewBulkPayrollRequest;
use App\Http\Requests\Payroll\SearchPayrollRequest;
use App\Http\Requests\Payroll\UpdatePayrollRequest;
use App\Http\Requests\Payroll\UploadPayrollRequest;
use App\Http\Resources\PayrollResource;
use App\Models\BulkPayrollBatch;
use App\Models\Payroll;
use App\Services\BulkPayrollService;
use App\Services\PayrollService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PayrollController extends BaseApiController
{
    public function __construct(
        private readonly PayrollService $payrollService,
        private readonly BulkPayrollService $bulkPayrollService,
    ) {}

    // =========================================================================
    // CRUD
    // =========================================================================

    /**
     * List payrolls with pagination, filtering, and search.
     */
    public function index(IndexPayrollRequest $request): JsonResponse
    {
        $result = $this->payrollService->list($request->validated());

        return PayrollResource::collection($result['paginator'])
            ->additional([
                'success' => true,
                'message' => 'Payrolls retrieved successfully',
                'filters' => [
                    'applied_filters' => $result['applied_filters'],
                ],
            ])
            ->response();
    }

    /**
     * Search payroll records by employee details.
     */
    public function search(SearchPayrollRequest $request): JsonResponse
    {
        $result = $this->payrollService->search($request->validated());

        if (! $result) {
            $validated = $request->validated();
            $searchTerm = $validated['search'] ?? $validated['staff_id'];

            return $this->errorResponse(
                "No payroll records found for search term: {$searchTerm}",
                404
            );
        }

        return PayrollResource::collection($result['paginator'])
            ->additional([
                'success' => true,
                'message' => 'Payroll records found successfully',
                'search_term' => $result['search_term'],
                'employee_info' => $result['employee_info'],
            ])
            ->response();
    }

    /**
     * Get a specific payroll.
     */
    public function show(Payroll $payroll): JsonResponse
    {
        $payroll->load('employment.employee');

        return PayrollResource::make($payroll)
            ->additional(['success' => true, 'message' => 'Payroll retrieved successfully'])
            ->response();
    }

    /**
     * Update an existing payroll record.
     */
    public function update(UpdatePayrollRequest $request, Payroll $payroll): JsonResponse
    {
        $payroll = $this->payrollService->update($payroll, $request->validated());

        return PayrollResource::make($payroll)
            ->additional(['success' => true, 'message' => 'Payroll updated successfully'])
            ->response();
    }

    /**
     * Delete a payroll record (soft delete).
     */
    public function destroy(Payroll $payroll): JsonResponse
    {
        $this->payrollService->destroy($payroll);

        return $this->successResponse(null, 'Payroll moved to recycle bin');
    }

    // =========================================================================
    // BULK CREATION (merged from BulkPayrollController)
    // =========================================================================

    /**
     * Preview bulk payroll creation (dry-run).
     */
    public function bulkPreview(PreviewBulkPayrollRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->bulkPayrollService->preview(
            $validated['pay_period_date'],
            $validated['filters'] ?? [],
            $validated['detailed'] ?? true,
        );

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Create bulk payroll batch and dispatch processing job.
     */
    public function bulkStore(StoreBulkPayrollRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->bulkPayrollService->createBatch(
            $validated['pay_period_date'],
            $validated['filters'] ?? [],
        );

        return response()->json([
            'success' => true,
            'message' => 'Bulk payroll batch created successfully',
            'data' => $result,
        ], 201);
    }

    /**
     * Get batch status (HTTP polling fallback).
     */
    public function bulkStatus(BulkPayrollBatch $batch): JsonResponse
    {
        $result = $this->bulkPayrollService->getStatus($batch);

        return response()->json([
            'success' => true,
            'data' => $result,
        ]);
    }

    /**
     * Download error report as CSV.
     */
    public function bulkDownloadErrors(BulkPayrollBatch $batch): Response
    {
        $result = $this->bulkPayrollService->getErrorReport($batch);

        return response($result['csv'], 200)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', "attachment; filename=\"{$result['filename']}\"");
    }

    // =========================================================================
    // REPORTS & TOOLS
    // =========================================================================

    /**
     * Get tax summary for a payroll.
     */
    public function taxSummary(Payroll $payroll): JsonResponse
    {
        $result = $this->payrollService->taxSummary($payroll);

        return $this->successResponse($result, 'Tax summary retrieved successfully');
    }

    /**
     * Get budget history for grant-centric view.
     */
    public function budgetHistory(BudgetHistoryRequest $request): JsonResponse
    {
        $result = $this->payrollService->budgetHistory($request->validated());

        return $this->successResponse([
            'data' => $result['data'],
            'pagination' => $result['pagination'],
            'date_range' => $result['date_range'],
        ], 'Budget history retrieved successfully');
    }

    /**
     * Upload payroll data from Excel file.
     */
    public function upload(UploadPayrollRequest $request): JsonResponse
    {
        $importId = $this->payrollService->upload($request->file('file'), $request->user()->id);

        return $this->successResponse(
            ['import_id' => $importId],
            'Payroll import started successfully. You will be notified when complete.',
            202
        );
    }

    /**
     * Download payroll import template.
     */
    public function downloadTemplate(): BinaryFileResponse
    {
        return $this->payrollService->downloadTemplate();
    }

    /**
     * Download employee funding allocations reference file.
     */
    public function downloadEmployeeFundingAllocationsReference(): BinaryFileResponse
    {
        return $this->payrollService->downloadAllocationsReference();
    }

    /**
     * Batch delete multiple payroll records.
     */
    public function destroyBatch(BatchSafeDeleteRequest $request): JsonResponse
    {
        $succeeded = [];
        $failed = [];

        foreach ($request->validated()['ids'] as $id) {
            try {
                $record = \App\Models\Payroll::findOrFail($id);
                $this->payrollService->destroy($record);
                $succeeded[] = ['id' => $id];
            } catch (\Exception $e) {
                $failed[] = ['id' => $id, 'reason' => $e->getMessage()];
            }
        }

        $failureCount = count($failed);
        $successCount = count($succeeded);

        return response()->json([
            'success' => $failureCount === 0,
            'message' => "{$successCount} payroll(s) deleted"
                .($failureCount > 0 ? ", {$failureCount} failed" : ''),
            'data' => ['succeeded' => $succeeded, 'failed' => $failed],
        ], $failureCount === 0 ? 200 : 207);
    }
}
