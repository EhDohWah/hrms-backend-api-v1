<?php

namespace App\Services;

use App\Enums\FundingAllocationStatus;
use App\Jobs\ProcessBulkPayroll;
use App\Models\BulkPayrollBatch;
use App\Models\Employment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing bulk payroll operations.
 *
 * Provides functionality to:
 * - Preview bulk payroll calculations (dry-run) before committing
 * - Create and dispatch bulk payroll processing batches
 * - Track batch processing status and progress
 * - Generate error reports for failed batch items
 *
 * Bulk payroll creates individual payroll records for all matching employees
 * based on their funding allocations. Each employee may have multiple allocations
 * across different grants, resulting in one payroll record per allocation.
 *
 * Inter-organization advances are automatically detected when an employee's
 * home organization differs from the grant's funding organization.
 */
class BulkPayrollService
{
    public function __construct(
        private readonly PayrollService $payrollService,
    ) {}

    /**
     * Preview bulk payroll creation (dry-run).
     *
     * Simulates payroll generation for all matching employments without
     * persisting any data. Returns summary totals, per-employee breakdowns,
     * and warnings for missing data or calculation errors.
     *
     * @param  string  $payPeriodDateStr  Pay period date string (e.g., '2025-12-01')
     * @param  array  $filters  Filters: subsidiaries[]
     * @param  bool  $includeDetails  If true, includes per-employee allocation breakdowns
     * @return array Preview result with summary, warnings, and optional employee details
     */
    public function preview(string $payPeriodDateStr, array $filters, bool $includeDetails): array
    {
        // Parse the pay period date string into a Carbon instance
        $payPeriodDate = Carbon::parse($payPeriodDateStr);

        // Fetch all employments matching the filters with eager-loaded relationships
        $employments = $this->fetchEmploymentsForPreview($filters, $payPeriodDate);

        // Initialize running totals for the preview summary
        $totals = ['employees' => $employments->count(), 'payrolls' => 0, 'gross' => 0, 'net' => 0, 'advances' => 0];
        $warnings = [];
        $employeeDetails = [];

        // Create a fresh PayrollService instance scoped to the pay period's fiscal year
        $payrollCalc = new PayrollService($payPeriodDate->year);

        // Process each employment: calculate payroll for all their funding allocations
        foreach ($employments as $employment) {
            $result = $this->processEmploymentForPreview($employment, $payrollCalc, $payPeriodDate, $includeDetails);

            // Collect any warnings (missing data, calculation errors, etc.)
            $warnings = array_merge($warnings, $result['warnings']);

            // Skip employees with no linked employee record or no funding allocations
            if ($result['skipped']) {
                continue;
            }

            // Accumulate totals across all processed employees
            $totals['payrolls'] += $result['payroll_count'];
            $totals['gross'] += $result['total_gross'];
            $totals['net'] += $result['total_net'];
            $totals['advances'] += $result['advances_count'];

            // Collect detailed per-employee data when requested
            if ($includeDetails) {
                $employeeDetails[] = $result['employee_data'];
            }
        }

        // Assemble and return the final preview response
        return $this->buildPreviewResult($totals, $warnings, $payPeriodDate, $filters, $includeDetails, $employeeDetails);
    }

    /**
     * Fetch employments with eager-loaded relations for preview.
     *
     * Loads employee, department, position, and active funding allocations.
     * Only allocations with status=Active are included.
     *
     * @param  array  $filters  Filters: subsidiaries[]
     * @param  Carbon  $payPeriodDate  Pay period date (any day in the target month)
     * @return \Illuminate\Database\Eloquent\Collection Collection of Employment models
     */
    private function fetchEmploymentsForPreview(array $filters, Carbon $payPeriodDate): \Illuminate\Database\Eloquent\Collection
    {
        $payPeriodStart = $payPeriodDate->copy()->startOfMonth();
        $isDecember = $payPeriodDate->month === 12;

        return $this->buildEmploymentQuery($filters, $payPeriodStart)
            ->with([
                'employee',
                'employee.employeeChildren',  // For tax allowances (child deduction)
                'department',
                'position',
                // In December, load all statuses so historical allocations get 13th month
                'employee.employeeFundingAllocations' => function ($q) use ($isDecember) {
                    if ($isDecember) {
                        $q->whereIn('status', [
                            \App\Enums\FundingAllocationStatus::Active,
                            \App\Enums\FundingAllocationStatus::Inactive,
                            \App\Enums\FundingAllocationStatus::Closed,
                        ]);
                    } else {
                        $q->where('status', \App\Enums\FundingAllocationStatus::Active);
                    }
                },
                'employee.employeeFundingAllocations.grantItem.grant',
            ])->get();
    }

    /**
     * Process a single employment for preview.
     *
     * Validates the employment has a linked employee and active funding allocations,
     * then calculates payroll for each allocation. Returns aggregated totals,
     * warnings, and optional detailed breakdown per allocation.
     *
     * @param  mixed  $employment  Employment model with eager-loaded relations
     * @param  PayrollService  $payrollCalc  PayrollService instance for calculations
     * @param  Carbon  $payPeriodDate  Pay period date
     * @param  bool  $includeDetails  Whether to include per-allocation breakdowns
     * @return array Result with: skipped, warnings, payroll_count, total_gross, total_net, advances_count, employee_data
     */
    private function processEmploymentForPreview($employment, PayrollService $payrollCalc, Carbon $payPeriodDate, bool $includeDetails): array
    {
        $warnings = [];
        $employee = $employment->employee;

        // Guard: skip if employment has no linked employee record
        if (! $employee) {
            $warnings[] = "Employment ID {$employment->id} has no linked employee";

            return ['skipped' => true, 'warnings' => $warnings];
        }

        // Set inverse relationship to prevent lazy loading violation
        // (calculateAllocationPayroll accesses $employee->employment)
        $employee->setRelation('employment', $employment);

        // Get the employee's funding allocations (already filtered to this pay period by eager loading)
        $allocations = $employee->employeeFundingAllocations;

        // Guard: skip if employee has no active funding allocations for this period
        if ($allocations->isEmpty()) {
            $warnings[] = "Employee {$employee->first_name_en} {$employee->last_name_en} has no active funding allocations";

            return ['skipped' => true, 'warnings' => $warnings];
        }

        // Warn (but don't skip) if probation pass date is missing — affects salary determination
        // Skip warning if probation_required is false (no probation needed, so no pass date expected)
        if ($employment->probation_required !== false && ! $employment->pass_probation_date) {
            $warnings[] = "Employee {$employee->first_name_en} {$employee->last_name_en} is missing probation pass date";
        }

        // Initialize the employee-level data structure for the preview response
        $employeeData = [
            'employment_id' => $employment->id,
            'staff_id' => $employee->staff_id,
            'name' => $employee->first_name_en.' '.$employee->last_name_en,
            'employee_status' => $employee->status,
            'organization' => $employee->organization,
            'department' => $employment->department->name ?? 'N/A',
            'position' => $employment->position->title ?? 'N/A',
            'start_date' => $employment->start_date,
            'pass_probation_date' => $employment->pass_probation_date,
            'probation_salary' => $employment->probation_salary,
            'pass_probation_salary' => $employment->pass_probation_salary,
            'allocations' => [],          // Per-allocation breakdowns (populated if includeDetails)
            'total_gross' => 0,           // Sum of gross salary across all allocations
            'total_net' => 0,             // Sum of net salary across all allocations
            'allocation_count' => 0,      // Number of successfully calculated allocations
            'has_warnings' => false,       // Flag if any allocation had calculation errors
        ];

        // Running totals for this employee across all allocations
        $payrollCount = 0;    // Number of payroll records that would be created
        $totalGross = 0;      // Accumulated gross salary (by FTE)
        $totalNet = 0;        // Accumulated net salary
        $advancesCount = 0;   // Number of inter-organization advances needed

        // Split allocations into active vs historical (inactive/closed) for December
        $isDecember = $payPeriodDate->month === 12;
        $activeAllocations = $allocations->filter(fn ($a) => $a->status === FundingAllocationStatus::Active);
        $historicalAllocations = $isDecember
            ? $allocations->filter(fn ($a) => in_array($a->status, [FundingAllocationStatus::Inactive, FundingAllocationStatus::Closed]))
            : collect([]);

        // Determine which allocation bears the tax (highest FTE)
        $taxAllocationId = $payrollCalc->determineTaxAllocationId($activeAllocations);

        // Calculate payroll for each active funding allocation
        foreach ($activeAllocations as $allocation) {
            try {
                // Tax goes to one allocation only (highest FTE)
                $isTaxAllocation = ($allocation->id === $taxAllocationId);

                // Run the full payroll calculation pipeline for this allocation
                $payrollData = $payrollCalc->calculateAllocationPayrollForController($employee, $allocation, $payPeriodDate, $isTaxAllocation);

                $payrollCount++;

                // Extract key salary figures from the calculation result
                $grossSalary = $payrollData['calculations']['gross_salary'];             // Full monthly gross salary
                $grossSalaryByFte = $payrollData['calculations']['gross_salary_by_fte']; // Gross salary adjusted by FTE and days worked
                $netSalary = $payrollData['calculations']['net_salary'];                 // Net salary after all deductions

                // Accumulate totals
                $totalGross += $grossSalaryByFte;
                $totalNet += $netSalary;

                // Check if this allocation requires an inter-organization advance
                // (employee's home org differs from the grant's funding org)
                $needsAdvance = $this->needsInterOrganizationAdvance($employee, $allocation);
                if ($needsAdvance) {
                    $advancesCount++;
                }

                // Build detailed allocation breakdown when requested
                if ($includeDetails) {
                    $employeeData['allocations'][] = $this->buildAllocationDetail(
                        $allocation, $payrollData['calculations'], $grossSalary, $netSalary, $needsAdvance, $employee,
                        $payrollData['calculation_breakdown'] ?? null
                    );
                }

                // Update employee-level totals
                $employeeData['total_gross'] += $grossSalaryByFte;
                $employeeData['total_net'] += $netSalary;
                $employeeData['allocation_count']++;
            } catch (\Exception $e) {
                // Log warning but continue processing other allocations
                $warnings[] = "Error calculating payroll for {$employee->first_name_en} {$employee->last_name_en} (Allocation ID: {$allocation->id}): {$e->getMessage()}";
                $employeeData['has_warnings'] = true;
            }
        }

        // December: calculate 13th-month-only records for historical allocations
        foreach ($historicalAllocations as $histAllocation) {
            try {
                $histPayrollData = $payrollCalc->calculateHistoricalAllocation13thMonth(
                    $employee,
                    $histAllocation,
                    $payPeriodDate
                );

                // Skip if no 13th month owed
                if ($histPayrollData === null) {
                    continue;
                }

                $payrollCount++;
                $histNetSalary = $histPayrollData['calculations']['net_salary'];
                $totalNet += $histNetSalary;

                $needsAdvance = $this->needsInterOrganizationAdvance($employee, $histAllocation);
                if ($needsAdvance) {
                    $advancesCount++;
                }

                if ($includeDetails) {
                    $employeeData['allocations'][] = $this->buildAllocationDetail(
                        $histAllocation, $histPayrollData['calculations'], 0, $histNetSalary, $needsAdvance, $employee,
                        $histPayrollData['calculation_breakdown'] ?? null
                    );
                }

                $employeeData['total_net'] += $histNetSalary;
                $employeeData['allocation_count']++;
            } catch (\Exception $e) {
                $warnings[] = "Error calculating historical 13th month for {$employee->first_name_en} {$employee->last_name_en} (Allocation ID: {$histAllocation->id}): {$e->getMessage()}";
                $employeeData['has_warnings'] = true;
            }
        }

        // Round final employee totals to integers for display
        if ($includeDetails) {
            $employeeData['total_gross'] = round($employeeData['total_gross']);
            $employeeData['total_net'] = round($employeeData['total_net']);
        }

        return [
            'skipped' => false,
            'warnings' => $warnings,
            'payroll_count' => $payrollCount,       // Total payroll records for this employee
            'total_gross' => $totalGross,            // Sum of gross salary by FTE
            'total_net' => $totalNet,                // Sum of net salary
            'advances_count' => $advancesCount,      // Inter-org advances needed
            'employee_data' => $employeeData,        // Full employee data with optional details
        ];
    }

    /**
     * Build the detailed allocation breakdown for a single funding allocation.
     *
     * Constructs a comprehensive breakdown including grant info, FTE, salary figures,
     * all deductions (PVD, saving fund, social security, health welfare, tax),
     * employer contributions, income additions (13th month, retroactive, bonus),
     * and inter-organization advance information.
     *
     * @param  mixed  $allocation  EmployeeFundingAllocation model
     * @param  array  $calc  Calculation results from PayrollService
     * @param  float  $grossSalary  Full monthly gross salary (before FTE)
     * @param  float  $netSalary  Net salary after deductions
     * @param  bool  $needsAdvance  Whether inter-org advance is needed
     * @param  mixed  $employee  Employee model
     * @param  array|null  $calculationBreakdown  Optional step-by-step calculation debug data
     * @return array Detailed allocation breakdown
     */
    private function buildAllocationDetail($allocation, array $calc, float $grossSalary, float $netSalary, bool $needsAdvance, $employee, ?array $calculationBreakdown = null): array
    {
        $detail = [
            // --- Grant & Allocation Info ---
            'allocation_id' => $allocation->id,
            'grant_name' => $allocation->grantItem->grant->name ?? 'N/A',
            'grant_code' => $allocation->grantItem->grant->code ?? 'N/A',
            'grant_organization' => $allocation->grantItem->grant->organization ?? 'N/A',
            'fte' => round($allocation->fte, 4),  // Full-Time Equivalent (e.g., 0.5 = 50%)

            // --- Salary Figures ---
            'gross_salary' => round($grossSalary),                       // Full monthly gross (before FTE adjustment)
            'gross_salary_by_fte' => round($calc['gross_salary_by_FTE']), // Gross salary × FTE × (days worked / 30)

            // --- Employee Deductions ---
            'deductions' => [
                'pvd' => round($calc['pvd']),                             // Provident Fund (employee portion)
                'saving_fund' => round($calc['saving_fund']),             // Saving Fund (employee portion)
                'employee_ss' => round($calc['employee_social_security']), // Social Security (5%, capped at ฿875)
                'employee_hw' => round($calc['employee_health_welfare']),  // Health & Welfare (tiered by salary & nationality)
                'tax' => round($calc['tax']),                             // Income tax (progressive brackets)
                'total' => round($calc['total_deduction']),               // Sum of all employee deductions
            ],

            // --- Employer Contributions ---
            'contributions' => [
                'pvd_employer' => round($calc['pvd_employer'] ?? 0),           // Provident Fund (employer match)
                'saving_fund_employer' => round($calc['saving_fund_employer'] ?? 0), // Saving Fund (employer match)
                'employer_ss' => round($calc['employer_social_security']),     // Social Security (employer portion, 5%, capped at ฿875)
                'employer_hw' => round($calc['employer_health_welfare']),      // Health & Welfare (employer portion)
                'total' => round($calc['employer_contribution']),              // Sum of all employer contributions
            ],

            // --- Income Additions ---
            'income_additions' => [
                'thirteen_month' => round($calc['thirteen_month_salary']),            // 13th month salary (December payout)
                // 'thirteen_month_accrued' => round($calc['thirteen_month_salary_accured']), // Disabled — accrual projection not needed for now
                'retroactive_adjustment' => round($calc['retroactive_adjustment'] ?? 0),  // Deferred salary from previous month (start day >= 16)
                'salary_bonus' => round($calc['salary_bonus'] ?? 0),                      // Annual salary increase bonus
            ],

            // --- Summary Totals ---
            'total_salary' => round($calc['total_salary']),   // Total cost to company (gross + employer contributions)
            'total_income' => round($calc['total_income']),   // Total employee income (gross + additions)
            'net_salary' => round($netSalary),                // Final take-home pay (income - deductions)
            'total_pvd' => round($calc['total_pvd']),         // Combined PVD (employee + employer)
            'total_saving_fund' => round($calc['total_saving_fund']), // Combined Saving Fund (employee + employer)

            // --- Inter-Organization Advance ---
            'needs_advance' => $needsAdvance,  // True if grant org ≠ employee org
            'advance_from' => $needsAdvance ? $allocation->grantItem->grant->organization ?? 'N/A' : null,  // Funding source org
            'advance_to' => $needsAdvance ? $employee->organization : null,  // Employee's home org (receives advance)
        ];

        // Append step-by-step calculation debug breakdown when available
        if ($calculationBreakdown !== null) {
            $detail['calculation_breakdown'] = $calculationBreakdown;
        }

        return $detail;
    }

    /**
     * Assemble the final preview response array.
     *
     * Combines summary totals, warnings, filter info, and optional
     * per-employee details into the API response structure.
     *
     * @param  array  $totals  Aggregated totals: employees, payrolls, gross, net, advances
     * @param  array  $warnings  List of warning messages
     * @param  Carbon  $payPeriodDate  Pay period date
     * @param  array  $filters  Applied filters
     * @param  bool  $includeDetails  Whether detailed employee data is included
     * @param  array  $employeeDetails  Per-employee breakdown data
     * @return array Final preview response
     */
    private function buildPreviewResult(array $totals, array $warnings, Carbon $payPeriodDate, array $filters, bool $includeDetails, array $employeeDetails): array
    {
        $result = [
            // Summary totals for the entire batch
            'summary' => [
                'total_employees' => $totals['employees'],             // Total employees matching filters
                'total_payrolls' => $totals['payrolls'],               // Total payroll records to be created
                'total_gross_salary' => round($totals['gross']),    // Sum of all gross salaries (by FTE)
                'total_net_salary' => round($totals['net']),        // Sum of all net salaries
                'advances_needed' => $totals['advances'],              // Number of inter-org advances needed
            ],
            'warnings' => $warnings,                                    // List of warnings (missing data, errors)
            'pay_period_date' => $payPeriodDate->format('Y-m-d'),       // The target pay period
            'filters_applied' => $filters,                              // Filters that were applied
            'detailed' => $includeDetails,                              // Whether detail mode was requested
        ];

        // Include per-employee breakdowns when detail mode is enabled
        if ($includeDetails) {
            $result['employees'] = $employeeDetails;             // Array of employee payroll details
            $result['employee_count'] = count($employeeDetails); // Count of employees with valid payroll data
        }

        return $result;
    }

    /**
     * Create bulk payroll batch and dispatch processing job.
     *
     * Queries all matching employments, creates a BulkPayrollBatch record,
     * and dispatches the ProcessBulkPayroll job for async background processing.
     * Aborts with 422 if no employments match the given filters.
     *
     * @param  string  $payPeriodDateStr  Pay period date string (e.g., '2025-12-01')
     * @param  array  $filters  Filters: subsidiaries[]
     * @return array Batch info: batch_id, pay_period_date, total_employees, status
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 422 if no matching employments
     */
    public function createBatch(string $payPeriodDateStr, array $filters): array
    {
        // Parse pay period and determine the start of the month for querying
        $payPeriodDate = Carbon::parse($payPeriodDateStr);
        $payPeriodStart = $payPeriodDate->copy()->startOfMonth();

        // Build the filtered employment query and get all matching employment IDs
        $query = $this->buildEmploymentQuery($filters, $payPeriodStart);
        $employmentIds = $query->pluck('id')->toArray();

        // Abort if no employments match the filters
        if (empty($employmentIds)) {
            abort(422, 'No employments found matching the filters');
        }

        // Create the batch record to track processing progress
        $batch = BulkPayrollBatch::create([
            'pay_period' => $payPeriodDate->format('Y-m-d'),      // Target pay period
            'filters' => $filters,                                  // Filters used (stored for reference)
            'total_employees' => count($employmentIds),             // Total employees to process
            'total_payrolls' => 0,                                  // Will be updated as processing runs
            'status' => 'pending',                                  // Initial status before job starts
            'created_by' => Auth::id(),                             // User who initiated the batch
        ]);

        // Dispatch the async job to process all employments in the background
        ProcessBulkPayroll::dispatch($batch->id, $payPeriodDate->format('Y-m-d'), $employmentIds);

        Log::info("BulkPayrollService: Created batch {$batch->id} with ".count($employmentIds).' employments');

        // Return batch info for the client to poll status
        return [
            'batch_id' => $batch->id,
            'pay_period_date' => $payPeriodDate->format('Y-m-d'),
            'total_employees' => count($employmentIds),
            'status' => 'pending',
        ];
    }

    /**
     * Get batch processing status and progress.
     *
     * Returns real-time progress including processed/total counts,
     * percentage complete, current employee being processed, and error stats.
     * Aborts with 403 if the current user is not authorized to view this batch.
     *
     * @param  BulkPayrollBatch  $batch  The batch to check status for
     * @return array Status info with progress, stats, and error counts
     */
    public function getStatus(BulkPayrollBatch $batch): array
    {
        // Verify the current user has permission to view this batch
        $this->authorizeBatchAccess($batch);

        return [
            'batch_id' => $batch->id,
            'pay_period' => $batch->pay_period,
            'status' => $batch->status,                             // pending, processing, completed, failed
            'processed' => $batch->processed_payrolls,              // Number of payrolls processed so far
            'total' => $batch->total_payrolls,                      // Total payrolls to process
            'progress_percentage' => $batch->progress_percentage,   // 0-100 completion percentage
            'current_employee' => $batch->current_employee,         // Name of employee currently being processed
            'current_allocation' => $batch->current_allocation,     // Current allocation being processed
            'stats' => [
                'successful' => $batch->successful_payrolls,        // Successfully created payrolls
                'failed' => $batch->failed_payrolls,                // Failed payroll calculations
                'advances_created' => $batch->advances_created,     // Inter-org advances created
            ],
            'has_errors' => $batch->hasErrors(),                    // Whether any errors occurred
            'error_count' => $batch->error_count,                   // Total number of errors
            'created_at' => $batch->created_at->toDateTimeString(),
            'updated_at' => $batch->updated_at->toDateTimeString(),
        ];
    }

    /**
     * Get error report as CSV for a batch.
     *
     * Generates a downloadable CSV containing details of all failed payroll
     * calculations in the batch: employment ID, employee name, allocation info,
     * and the error message.
     * Aborts with 403 if unauthorized, 404 if no errors exist.
     *
     * @param  BulkPayrollBatch  $batch  The batch to generate error report for
     * @return array CSV string and suggested filename
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 if unauthorized, 404 if no errors
     */
    public function getErrorReport(BulkPayrollBatch $batch): array
    {
        // Verify the current user has permission to view this batch
        $this->authorizeBatchAccess($batch);

        // Only generate report if the batch actually has errors
        if (! $batch->hasErrors()) {
            abort(404, 'No errors found for this batch');
        }

        // Build CSV content with header row
        $csv = "Employment ID,Employee,Allocation,Error\n";

        // Append each error as a CSV row with quoted fields to handle special characters
        foreach ($batch->errors as $error) {
            $csv .= '"'.($error['employment_id'] ?? 'N/A').'",';
            $csv .= '"'.($error['employee'] ?? 'Unknown').'",';
            $csv .= '"'.($error['allocation'] ?? 'N/A').'",';
            $csv .= '"'.($error['error'] ?? 'Unknown error').'"'."\n";
        }

        return [
            'csv' => $csv,
            'filename' => "bulk_payroll_errors_{$batch->id}_{$batch->pay_period}.csv",
        ];
    }

    /**
     * Check if the current user is authorized to access this batch.
     *
     * Access is granted if the user created the batch OR has the
     * 'employee_salary.edit' permission (HR admin/payroll manager).
     * Aborts with 403 if neither condition is met.
     *
     * @param  BulkPayrollBatch  $batch  The batch to check access for
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 403 if unauthorized
     */
    private function authorizeBatchAccess(BulkPayrollBatch $batch): void
    {
        // Allow access if user is the batch creator OR has payroll edit permission
        if ($batch->created_by !== Auth::id() && ! Auth::user()->can('employee_salary.edit')) {
            abort(403, 'Unauthorized');
        }
    }

    /**
     * Build employment query with filters.
     *
     * Constructs an Eloquent query for Employment records that are active
     * during the given pay period. Includes:
     * - Active employees (no end_date)
     * - Employees who resigned mid-month (end_date falls within the pay period)
     *
     * Supports filtering by:
     * - subsidiaries: employee's organization (e.g., 'SMRU', 'BHF')
     *
     * @param  array  $filters  Filters: subsidiaries[]
     * @param  Carbon  $payPeriodDate  First day of the pay period month
     * @return \Illuminate\Database\Eloquent\Builder Query builder (not yet executed)
     */
    private function buildEmploymentQuery(array $filters, Carbon $payPeriodDate)
    {
        // Last day of the pay period month
        $payPeriodEnd = $payPeriodDate->copy()->endOfMonth();

        // Base query: include active employees (no end_date) OR employees whose
        // end_date falls within this pay period month (resignation mid-month → partial pay)
        $query = Employment::query()->where(function ($q) use ($payPeriodDate, $payPeriodEnd) {
            $q->whereNull('end_date')
                ->orWhereBetween('end_date', [$payPeriodDate, $payPeriodEnd]);
        });

        // Filter by subsidiary/organization (e.g., 'SMRU', 'BHF')
        if (! empty($filters['subsidiaries'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->whereIn('organization', $filters['subsidiaries']);
            });
        }

        return $query;
    }

    /**
     * Check if an inter-organization advance is needed.
     *
     * An advance is required when the grant's funding organization differs
     * from the employee's home organization. For example, if an employee
     * belongs to 'BIOPHICS' but is funded by a 'MORU' grant, an advance
     * must be created from MORU to BIOPHICS.
     *
     * @param  mixed  $employee  Employee model (has 'organization' field)
     * @param  mixed  $allocation  EmployeeFundingAllocation model (has grantItem.grant relationship)
     * @return bool True if organizations differ and an advance is needed
     */
    private function needsInterOrganizationAdvance($employee, $allocation): bool
    {
        // Ensure the grant relationship chain is loaded before comparing
        if ($allocation->grantItem && $allocation->grantItem->grant) {
            // Advance needed when employee org ≠ grant funding org
            return $employee->organization !== $allocation->grantItem->grant->organization;
        }

        // If grant data is missing, no advance can be determined
        return false;
    }
}
