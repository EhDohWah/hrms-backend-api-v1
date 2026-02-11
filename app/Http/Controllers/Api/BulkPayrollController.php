<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessBulkPayroll;
use App\Models\BulkPayrollBatch;
use App\Models\Employment;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Bulk Payroll Controller
 *
 * Handles bulk payroll creation with real-time progress tracking
 *
 * @OA\Tag(name="Bulk Payroll", description="Bulk payroll creation endpoints")
 */
class BulkPayrollController extends Controller
{
    /**
     * Preview bulk payroll creation (dry-run)
     *
     * @OA\Post(
     *     path="/api/v1/payrolls/bulk/preview",
     *     tags={"Bulk Payroll"},
     *     summary="Preview bulk payroll creation with detailed employee breakdown",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"pay_period"},
     *
     *             @OA\Property(property="pay_period", type="string", example="2025-10"),
     *             @OA\Property(property="detailed", type="boolean", example=true, description="Include detailed employee breakdown (default: true)"),
     *             @OA\Property(property="filters", type="object",
     *                 @OA\Property(property="subsidiaries", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="departments", type="array", @OA\Items(type="integer")),
     *                 @OA\Property(property="grants", type="array", @OA\Items(type="integer"))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Preview data with detailed employee breakdown returned",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="summary", type="object",
     *                     @OA\Property(property="total_employees", type="integer", example=50),
     *                     @OA\Property(property="total_payrolls", type="integer", example=75),
     *                     @OA\Property(property="total_gross_salary", type="string", example="1250000.00"),
     *                     @OA\Property(property="total_net_salary", type="string", example="950000.00"),
     *                     @OA\Property(property="advances_needed", type="integer", example=5)
     *                 ),
     *                 @OA\Property(property="employees", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="warnings", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="pay_period", type="string", example="2025-10")
     *             )
     *         )
     *     )
     * )
     */
    public function preview(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pay_period' => 'required|string|date_format:Y-m',
            'filters' => 'nullable|array',
            'filters.subsidiaries' => 'nullable|array',
            'filters.departments' => 'nullable|array',
            'filters.grants' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $payPeriod = $request->pay_period;
            $filters = $request->filters ?? [];
            $payPeriodDate = Carbon::createFromFormat('Y-m', $payPeriod)->startOfMonth();
            $payPeriodEnd = $payPeriodDate->copy()->endOfMonth();
            $includeDetails = $request->input('detailed', true); // Default to detailed view

            // Build query with filters
            $query = $this->buildEmploymentQuery($filters, $payPeriodDate);

            $employments = $query->with([
                'employee',
                'department',
                'position',
                'employee.employeeFundingAllocations' => function ($q) use ($payPeriodDate, $payPeriodEnd) {
                    $q->where(function ($query) use ($payPeriodDate, $payPeriodEnd) {
                        // Allocation must have started on or before end of pay period month
                        // and must not have ended before the start of pay period month
                        $query->where('start_date', '<=', $payPeriodEnd)
                            ->where(function ($subQ) use ($payPeriodDate) {
                                $subQ->whereNull('end_date')
                                    ->orWhere('end_date', '>=', $payPeriodDate);
                            });
                    });
                },
                'employee.employeeFundingAllocations.grantItem.grant',
            ])->get();

            // Calculate totals and detailed breakdown
            $totalEmployees = $employments->count();
            $totalPayrolls = 0;
            $totalGrossSalary = 0;
            $totalNetSalary = 0;
            $advancesNeeded = 0;
            $warnings = [];
            $employeeDetails = [];

            $payrollService = new PayrollService(Carbon::parse($payPeriodDate)->year);

            foreach ($employments as $employment) {
                $employee = $employment->employee;

                if (! $employee) {
                    $warnings[] = "Employment ID {$employment->id} has no linked employee";

                    continue;
                }

                $allocations = $employee->employeeFundingAllocations;

                if ($allocations->isEmpty()) {
                    $warnings[] = "Employee {$employee->first_name_en} {$employee->last_name_en} has no active funding allocations";

                    continue;
                }

                // Check for missing probation pass date
                if (! $employment->probation_pass_date) {
                    $warnings[] = "Employee {$employee->first_name_en} {$employee->last_name_en} is missing probation pass date";
                }

                // Initialize employee data structure
                $employeeData = [
                    'employment_id' => $employment->id,
                    'staff_id' => $employee->staff_id,
                    'name' => $employee->first_name_en.' '.$employee->last_name_en,
                    'organization' => $employee->organization,
                    'department' => $employment->department->name ?? 'N/A',
                    'position' => $employment->position->title ?? 'N/A',
                    'allocations' => [],
                    'total_gross' => 0,
                    'total_net' => 0,
                    'allocation_count' => 0,
                    'has_warnings' => false,
                ];

                foreach ($allocations as $allocation) {
                    try {
                        // Dry-run calculation (no save)
                        $payrollData = $payrollService->calculateAllocationPayrollForController($employee, $allocation, $payPeriodDate);

                        $totalPayrolls++;
                        $grossSalary = $payrollData['calculations']['gross_salary'];
                        $grossSalaryByFte = $payrollData['calculations']['gross_salary_by_fte'];
                        $netSalary = $payrollData['calculations']['net_salary'];
                        $totalGrossSalary += $grossSalaryByFte; // Sum FTE-adjusted salary, not base
                        $totalNetSalary += $netSalary;

                        // Check if advance needed
                        $needsAdvance = $this->needsInterOrganizationAdvance($employee, $allocation);
                        if ($needsAdvance) {
                            $advancesNeeded++;
                        }

                        // Build detailed allocation data if requested
                        if ($includeDetails) {
                            $allocationDetail = [
                                'allocation_id' => $allocation->id,
                                'grant_name' => $allocation->grantItem->grant->name ?? 'N/A',
                                'grant_code' => $allocation->grantItem->grant->code ?? 'N/A',
                                'grant_organization' => $allocation->grantItem->grant->organization ?? 'N/A',
                                'fte' => round($allocation->fte, 4),
                                // Salary fields (raw numbers — frontend handles formatting)
                                'gross_salary' => round($grossSalary, 2),
                                'gross_salary_by_fte' => round($payrollData['calculations']['gross_salary_by_FTE'], 2),
                                // Deductions breakdown
                                'deductions' => [
                                    'tax' => round($payrollData['calculations']['tax'], 2),
                                    'employee_ss' => round($payrollData['calculations']['employee_social_security'], 2),
                                    'employee_hw' => round($payrollData['calculations']['employee_health_welfare'], 2),
                                    'total' => round($payrollData['calculations']['total_deduction'], 2),
                                ],
                                // Employer contributions
                                'contributions' => [
                                    'pvd' => round($payrollData['calculations']['pvd'], 2),
                                    'saving_fund' => round($payrollData['calculations']['saving_fund'], 2),
                                    'employer_ss' => round($payrollData['calculations']['employer_social_security'], 2),
                                    'employer_hw' => round($payrollData['calculations']['employer_health_welfare'], 2),
                                    'total' => round($payrollData['calculations']['employer_contribution'], 2),
                                ],
                                // Income additions
                                'income_additions' => [
                                    'thirteen_month' => round($payrollData['calculations']['thirteen_month_salary'], 2),
                                    'thirteen_month_accrued' => round($payrollData['calculations']['thirteen_month_salary_accured'], 2),
                                    'compensation_refund' => round($payrollData['calculations']['compensation_refund'], 2),
                                    'salary_bonus' => round($payrollData['calculations']['salary_bonus'] ?? 0, 2),
                                ],
                                // Totals
                                'total_salary' => round($payrollData['calculations']['total_salary'], 2),
                                'total_income' => round($payrollData['calculations']['total_income'], 2),
                                'net_salary' => round($netSalary, 2),
                                // Advance info
                                'needs_advance' => $needsAdvance,
                                'advance_from' => $needsAdvance ? $allocation->grantItem->grant->organization ?? 'N/A' : null,
                                'advance_to' => $needsAdvance ? $employee->organization : null,
                            ];

                            $employeeData['allocations'][] = $allocationDetail;
                        }

                        $employeeData['total_gross'] += $grossSalaryByFte;
                        $employeeData['total_net'] += $netSalary;
                        $employeeData['allocation_count']++;
                    } catch (\Exception $e) {
                        $warnings[] = "Error calculating payroll for {$employee->first_name_en} {$employee->last_name_en} (Allocation ID: {$allocation->id}): {$e->getMessage()}";
                        $employeeData['has_warnings'] = true;
                    }
                }

                // Round employee totals (raw numbers — frontend handles formatting)
                if ($includeDetails) {
                    $employeeData['total_gross'] = round($employeeData['total_gross'], 2);
                    $employeeData['total_net'] = round($employeeData['total_net'], 2);
                    $employeeDetails[] = $employeeData;
                }
            }

            $response = [
                'success' => true,
                'data' => [
                    // Summary statistics
                    'summary' => [
                        'total_employees' => $totalEmployees,
                        'total_payrolls' => $totalPayrolls,
                        'total_gross_salary' => round($totalGrossSalary, 2),
                        'total_net_salary' => round($totalNetSalary, 2),
                        'advances_needed' => $advancesNeeded,
                    ],
                    // Warnings
                    'warnings' => $warnings,
                    // Metadata
                    'pay_period' => $payPeriod,
                    'filters_applied' => $filters,
                    'detailed' => $includeDetails,
                ],
            ];

            // Add detailed employee breakdown if requested
            if ($includeDetails) {
                $response['data']['employees'] = $employeeDetails;
                $response['data']['employee_count'] = count($employeeDetails);
            }

            return response()->json($response);
        } catch (\Exception $e) {
            Log::error('BulkPayrollController@preview error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate preview',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create bulk payroll batch and dispatch job
     *
     * @OA\Post(
     *     path="/api/v1/payrolls/bulk/create",
     *     tags={"Bulk Payroll"},
     *     summary="Create bulk payroll batch",
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"pay_period"},
     *
     *             @OA\Property(property="pay_period", type="string", example="2025-10"),
     *             @OA\Property(property="filters", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(response=201, description="Batch created successfully")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'pay_period' => 'required|string|date_format:Y-m',
            'filters' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $payPeriod = $request->pay_period;
            $filters = $request->filters ?? [];
            $payPeriodDate = Carbon::createFromFormat('Y-m', $payPeriod)->startOfMonth();

            // Build query with filters
            $query = $this->buildEmploymentQuery($filters, $payPeriodDate);
            $employmentIds = $query->pluck('id')->toArray();

            if (empty($employmentIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No employments found matching the filters',
                ], 422);
            }

            // Create batch record
            $batch = BulkPayrollBatch::create([
                'pay_period' => $payPeriod,
                'filters' => $filters,
                'total_employees' => count($employmentIds),
                'total_payrolls' => 0, // Will be calculated in job
                'status' => 'pending',
                'created_by' => Auth::id(),
            ]);

            // Dispatch job
            ProcessBulkPayroll::dispatch($batch->id, $payPeriod, $employmentIds);

            Log::info("BulkPayrollController: Created batch {$batch->id} with ".count($employmentIds).' employments');

            return response()->json([
                'success' => true,
                'message' => 'Bulk payroll batch created successfully',
                'data' => [
                    'batch_id' => $batch->id,
                    'pay_period' => $payPeriod,
                    'total_employees' => count($employmentIds),
                    'status' => 'pending',
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('BulkPayrollController@create error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create bulk payroll batch',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get batch status (HTTP polling fallback)
     *
     * @OA\Get(
     *     path="/api/v1/payrolls/bulk/status/{batchId}",
     *     tags={"Bulk Payroll"},
     *     summary="Get batch status",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="batchId", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Batch status returned")
     * )
     */
    public function status(int $batchId): JsonResponse
    {
        try {
            $batch = BulkPayrollBatch::findOrFail($batchId);

            // Authorization: only creator or users with employee_salary.edit permission can view
            if ($batch->created_by !== Auth::id() && ! Auth::user()->can('employee_salary.edit')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'batch_id' => $batch->id,
                    'pay_period' => $batch->pay_period,
                    'status' => $batch->status,
                    'processed' => $batch->processed_payrolls,
                    'total' => $batch->total_payrolls,
                    'progress_percentage' => $batch->progress_percentage,
                    'current_employee' => $batch->current_employee,
                    'current_allocation' => $batch->current_allocation,
                    'stats' => [
                        'successful' => $batch->successful_payrolls,
                        'failed' => $batch->failed_payrolls,
                        'advances_created' => $batch->advances_created,
                    ],
                    'has_errors' => $batch->hasErrors(),
                    'error_count' => $batch->error_count,
                    'created_at' => $batch->created_at->toDateTimeString(),
                    'updated_at' => $batch->updated_at->toDateTimeString(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('BulkPayrollController@status error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get batch status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download error report as CSV
     *
     * @OA\Get(
     *     path="/api/v1/payrolls/bulk/errors/{batchId}",
     *     tags={"Bulk Payroll"},
     *     summary="Download batch error report",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="batchId", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="CSV file download")
     * )
     */
    public function downloadErrors(int $batchId)
    {
        try {
            $batch = BulkPayrollBatch::findOrFail($batchId);

            // Authorization
            if ($batch->created_by !== Auth::id() && ! Auth::user()->can('employee_salary.edit')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized',
                ], 403);
            }

            if (! $batch->hasErrors()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No errors found for this batch',
                ], 404);
            }

            // Generate CSV
            $csv = "Employment ID,Employee,Allocation,Error\n";
            foreach ($batch->errors as $error) {
                $csv .= '"'.($error['employment_id'] ?? 'N/A').'",';
                $csv .= '"'.($error['employee'] ?? 'Unknown').'",';
                $csv .= '"'.($error['allocation'] ?? 'N/A').'",';
                $csv .= '"'.($error['error'] ?? 'Unknown error').'"'."\n";
            }

            $fileName = "bulk_payroll_errors_{$batchId}_{$batch->pay_period}.csv";

            return response($csv, 200)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', "attachment; filename=\"{$fileName}\"");
        } catch (\Exception $e) {
            Log::error('BulkPayrollController@downloadErrors error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to download error report',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Build employment query with filters
     */
    private function buildEmploymentQuery(array $filters, Carbon $payPeriodDate)
    {
        $query = Employment::query()->where('status', true);

        // Filter by subsidiaries
        if (! empty($filters['subsidiaries'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->whereIn('organization', $filters['subsidiaries']);
            });
        }

        // Filter by departments
        if (! empty($filters['departments'])) {
            $query->whereIn('department_id', $filters['departments']);
        }

        // Filter by grants (through funding allocations)
        if (! empty($filters['grants'])) {
            $payPeriodEnd = $payPeriodDate->copy()->endOfMonth();
            $query->whereHas('employee.employeeFundingAllocations', function ($q) use ($filters, $payPeriodDate, $payPeriodEnd) {
                $q->where(function ($dateQ) use ($payPeriodDate, $payPeriodEnd) {
                    $dateQ->where('start_date', '<=', $payPeriodEnd)
                        ->where(function ($endQ) use ($payPeriodDate) {
                            $endQ->whereNull('end_date')
                                ->orWhere('end_date', '>=', $payPeriodDate);
                        });
                })
                    ->whereHas('grantItem.grant', function ($grantQ) use ($filters) {
                        $grantQ->whereIn('id', $filters['grants']);
                    });
            });
        }

        return $query;
    }

    /**
     * Check if inter-organization advance is needed
     */
    private function needsInterOrganizationAdvance($employee, $allocation): bool
    {
        $employeeOrganization = $employee->organization;

        if ($allocation->grantItem && $allocation->grantItem->grant) {
            $fundOrganization = $allocation->grantItem->grant->organization;
        } else {
            return false;
        }

        return $employeeOrganization !== $fundOrganization;
    }
}
