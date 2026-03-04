<?php

namespace App\Jobs;

use App\Events\PayrollBulkProgress;
use App\Models\BulkPayrollBatch;
use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
use App\Models\Employment;
use App\Models\Payroll;
use App\Services\PayrollService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Process Bulk Payroll Creation Job
 *
 * Processes multiple employee payrolls with real-time WebSocket progress tracking
 * Following proven patterns from bulk-creation product import system
 */
class ProcessBulkPayroll implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Configuration constants
    private const BATCH_SIZE = 10; // Insert every 10 payrolls

    private const BROADCAST_EVERY_N_PAYROLLS = 10; // Broadcast every 10 payrolls

    public int $timeout = 3600; // 1 hour timeout for large batches

    public int $tries = 1; // Do not retry to avoid duplicate payrolls

    protected int $batchId;

    protected string $payPeriodDate;

    protected array $employmentIds;

    /**
     * Create a new job instance.
     *
     * @param  string  $payPeriodDate  Full date in Y-m-d format (e.g. 2025-10-25)
     */
    public function __construct(int $batchId, string $payPeriodDate, array $employmentIds)
    {
        $this->batchId = $batchId;
        $this->payPeriodDate = $payPeriodDate;
        $this->employmentIds = $employmentIds;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("ProcessBulkPayroll: Starting batch {$this->batchId}");

        $batch = BulkPayrollBatch::findOrFail($this->batchId);

        try {
            // Update status to processing
            $batch->update(['status' => 'processing']);

            // Initialize counters
            $processedCount = 0;
            $successfulCount = 0;
            $failedCount = 0;
            $advancesCreated = 0;
            $errors = [];
            $payrollBuffer = []; // For batch inserts
            $itemsSinceLastBroadcast = 0;

            // Parse pay period date (full date, e.g. 2025-10-25)
            $payPeriodDate = Carbon::parse($this->payPeriodDate);
            $payPeriodStart = $payPeriodDate->copy()->startOfMonth();

            // Load all employments with relationships
            $employments = Employment::with([
                'employee',
                'employee.employeeChildren',  // For tax allowances (child deduction)
                'department',
                'position',
            ])->whereIn('id', $this->employmentIds)->get();

            // Load employeeFundingAllocations with their relationships separately
            $employeeIds = $employments->pluck('employee.id')->filter()->unique();

            if ($employeeIds->isNotEmpty()) {
                $allocations = EmployeeFundingAllocation::whereIn('employee_id', $employeeIds)
                    ->where('status', \App\Enums\FundingAllocationStatus::Active)
                    ->with(['grantItem.grant'])
                    ->get()
                    ->groupBy('employee_id');

                // Attach allocations to employees
                foreach ($employments as $employment) {
                    if ($employment->employee && isset($allocations[$employment->employee->id])) {
                        $employment->employee->setRelation('employeeFundingAllocations', $allocations[$employment->employee->id]);
                    } else {
                        $employment->employee->setRelation('employeeFundingAllocations', collect([]));
                    }
                }
            }

            // December: load historical (inactive/closed) allocations that have YTD payroll records
            // These need 13th-month-only payroll records even though they're no longer active
            $historicalAllocations = collect([]);
            $isDecember = $payPeriodDate->month === 12;

            if ($isDecember && $employeeIds->isNotEmpty()) {
                $historicalAllocationIds = DB::table('payrolls')
                    ->join('employee_funding_allocations', 'payrolls.employee_funding_allocation_id', '=', 'employee_funding_allocations.id')
                    ->whereIn('employee_funding_allocations.employee_id', $employeeIds->toArray())
                    ->whereIn('employee_funding_allocations.status', ['inactive', 'closed'])
                    ->whereYear('payrolls.pay_period_date', $payPeriodDate->year)
                    ->distinct()
                    ->pluck('payrolls.employee_funding_allocation_id');

                if ($historicalAllocationIds->isNotEmpty()) {
                    $historicalAllocations = EmployeeFundingAllocation::whereIn('id', $historicalAllocationIds)
                        ->with(['grantItem.grant'])
                        ->get()
                        ->groupBy('employee_id');
                }
            }

            // Calculate total payrolls (sum of all allocations across all employments)
            $totalPayrolls = 0;
            foreach ($employments as $employment) {
                $totalPayrolls += $employment->employee->employeeFundingAllocations->count();
            }

            // Include historical allocations in total count for December
            if ($isDecember) {
                foreach ($historicalAllocations as $employeeId => $allocs) {
                    $totalPayrolls += $allocs->count();
                }
            }

            // Update batch with totals
            $batch->update([
                'total_employees' => $employments->count(),
                'total_payrolls' => $totalPayrolls,
            ]);

            // Initialize PayrollService
            $payrollService = new PayrollService($payPeriodDate->year);

            // Process each employment
            foreach ($employments as $employment) {
                $employee = $employment->employee;

                if (! $employee) {
                    $errors[] = [
                        'employment_id' => $employment->id,
                        'employee' => 'Unknown',
                        'allocation' => 'N/A',
                        'error' => 'Employment has no linked employee',
                    ];
                    $failedCount++;

                    continue;
                }

                // Set inverse relationship to prevent lazy loading violation
                // (calculateAllocationPayroll accesses $employee->employment)
                $employee->setRelation('employment', $employment);

                $allocations = $employee->employeeFundingAllocations;

                if ($allocations->isEmpty()) {
                    $errors[] = [
                        'employment_id' => $employment->id,
                        'employee' => $employee->full_name_en ?? 'Unknown',
                        'allocation' => 'N/A',
                        'error' => 'Employee has no active funding allocations',
                    ];
                    $failedCount++;

                    continue;
                }

                // Determine which allocation bears the tax (highest FTE)
                $taxAllocationId = $payrollService->determineTaxAllocationId($allocations);

                // Process each funding allocation for this employee
                foreach ($allocations as $allocation) {
                    $allocationLabel = $this->getAllocationLabel($allocation);

                    try {
                        // Skip if payroll already exists for this allocation and period
                        $existingPayroll = Payroll::where('employment_id', $employment->id)
                            ->where('employee_funding_allocation_id', $allocation->id)
                            ->whereYear('pay_period_date', $payPeriodDate->year)
                            ->whereMonth('pay_period_date', $payPeriodDate->month)
                            ->exists();

                        if ($existingPayroll) {
                            Log::info("ProcessBulkPayroll: Skipping duplicate for employment {$employment->id}, allocation {$allocation->id}, period {$payPeriodDate->format('Y-m')}");
                            $processedCount++;
                            $itemsSinceLastBroadcast++;

                            continue;
                        }

                        // Tax goes to one allocation only (highest FTE)
                        $isTaxAllocation = ($allocation->id === $taxAllocationId);

                        // Calculate payroll for this specific allocation
                        $payrollData = $payrollService->calculateAllocationPayrollForController(
                            $employee,
                            $allocation,
                            $payPeriodDate,
                            $isTaxAllocation
                        );

                        // Prepare payroll record data
                        $payrollRecord = $this->preparePayrollRecord(
                            $employment,
                            $allocation,
                            $payrollData,
                            $payPeriodDate
                        );

                        $payrollBuffer[] = $payrollRecord;
                        $successfulCount++;

                        // Check if we need to insert batch
                        if (count($payrollBuffer) >= self::BATCH_SIZE) {
                            $insertedPayrolls = $this->insertPayrollBatch($payrollBuffer);
                            $payrollBuffer = []; // Clear buffer

                            // Create inter-organization advances for inserted payrolls
                            $advancesCreated += $this->createAdvancesForPayrolls($insertedPayrolls, $payrollService, $payPeriodDate);
                        }
                    } catch (\Exception $e) {
                        Log::error("ProcessBulkPayroll: Error processing allocation for employee {$employee->id}: ".$e->getMessage());

                        $errors[] = [
                            'employment_id' => $employment->id,
                            'employee' => $employee->full_name_en ?? 'Unknown',
                            'allocation' => $allocationLabel,
                            'error' => $e->getMessage(),
                        ];
                        $failedCount++;
                    }

                    // Increment counters
                    $processedCount++;
                    $itemsSinceLastBroadcast++;

                    // Broadcast progress every N payrolls
                    if ($itemsSinceLastBroadcast >= self::BROADCAST_EVERY_N_PAYROLLS && $processedCount !== $totalPayrolls) {
                        $this->broadcastProgress(
                            $batch,
                            $processedCount,
                            $totalPayrolls,
                            $successfulCount,
                            $failedCount,
                            $advancesCreated,
                            $employee->full_name_en ?? 'Unknown',
                            $allocationLabel
                        );

                        $itemsSinceLastBroadcast = 0;
                    }
                }

                // December: create 13th-month-only records for historical allocations
                if ($isDecember && isset($historicalAllocations[$employee->id])) {
                    foreach ($historicalAllocations[$employee->id] as $histAllocation) {
                        $histAllocationLabel = $this->getAllocationLabel($histAllocation).' (historical)';

                        try {
                            // Skip if payroll already exists for this historical allocation
                            $existingHistPayroll = Payroll::where('employment_id', $employment->id)
                                ->where('employee_funding_allocation_id', $histAllocation->id)
                                ->whereYear('pay_period_date', $payPeriodDate->year)
                                ->whereMonth('pay_period_date', $payPeriodDate->month)
                                ->exists();

                            if ($existingHistPayroll) {
                                $processedCount++;
                                $itemsSinceLastBroadcast++;

                                continue;
                            }

                            $histPayrollData = $payrollService->calculateHistoricalAllocation13thMonth(
                                $employee,
                                $histAllocation,
                                $payPeriodDate
                            );

                            // Skip if no 13th month owed (already paid, no YTD, etc.)
                            if ($histPayrollData === null) {
                                $processedCount++;
                                $itemsSinceLastBroadcast++;

                                continue;
                            }

                            $payrollRecord = $this->preparePayrollRecord(
                                $employment,
                                $histAllocation,
                                $histPayrollData,
                                $payPeriodDate
                            );
                            $payrollRecord['notes'] = '13th month salary - historical allocation';

                            $payrollBuffer[] = $payrollRecord;
                            $successfulCount++;

                            if (count($payrollBuffer) >= self::BATCH_SIZE) {
                                $insertedPayrolls = $this->insertPayrollBatch($payrollBuffer);
                                $payrollBuffer = [];
                                $advancesCreated += $this->createAdvancesForPayrolls($insertedPayrolls, $payrollService, $payPeriodDate);
                            }
                        } catch (\Exception $e) {
                            Log::error("ProcessBulkPayroll: Error processing historical allocation for employee {$employee->id}: ".$e->getMessage());

                            $errors[] = [
                                'employment_id' => $employment->id,
                                'employee' => $employee->full_name_en ?? 'Unknown',
                                'allocation' => $histAllocationLabel,
                                'error' => $e->getMessage(),
                            ];
                            $failedCount++;
                        }

                        $processedCount++;
                        $itemsSinceLastBroadcast++;

                        if ($itemsSinceLastBroadcast >= self::BROADCAST_EVERY_N_PAYROLLS && $processedCount !== $totalPayrolls) {
                            $this->broadcastProgress(
                                $batch,
                                $processedCount,
                                $totalPayrolls,
                                $successfulCount,
                                $failedCount,
                                $advancesCreated,
                                $employee->full_name_en ?? 'Unknown',
                                $histAllocationLabel
                            );

                            $itemsSinceLastBroadcast = 0;
                        }
                    }
                }
            }

            // Insert remaining payrolls in buffer
            if (! empty($payrollBuffer)) {
                $insertedPayrolls = $this->insertPayrollBatch($payrollBuffer);
                $advancesCreated += $this->createAdvancesForPayrolls($insertedPayrolls, $payrollService, $payPeriodDate);
            }

            // Update batch with final results
            $batch->update([
                'status' => 'completed',
                'processed_payrolls' => $processedCount,
                'successful_payrolls' => $successfulCount,
                'failed_payrolls' => $failedCount,
                'advances_created' => $advancesCreated,
                'errors' => $errors,
                'current_employee' => null,
                'current_allocation' => null,
                'summary' => [
                    'total_employees' => $employments->count(),
                    'total_payrolls' => $totalPayrolls,
                    'successful' => $successfulCount,
                    'failed' => $failedCount,
                    'advances_created' => $advancesCreated,
                    'completed_at' => now()->toDateTimeString(),
                ],
            ]);

            // Broadcast final completion (not at last item to prevent flash)
            broadcast(new PayrollBulkProgress(
                $this->batchId,
                $processedCount,
                $totalPayrolls,
                'completed',
                null,
                null,
                [
                    'successful' => $successfulCount,
                    'failed' => $failedCount,
                    'advances_created' => $advancesCreated,
                ]
            ));

            Log::info("ProcessBulkPayroll: Completed batch {$this->batchId} - Success: {$successfulCount}, Failed: {$failedCount}");
        } catch (\Exception $e) {
            Log::error("ProcessBulkPayroll: Fatal error in batch {$this->batchId}: ".$e->getMessage());

            $batch->update([
                'status' => 'failed',
                'errors' => [['error' => 'Fatal error: '.$e->getMessage()]],
            ]);

            broadcast(new PayrollBulkProgress(
                $this->batchId,
                0,
                0,
                'failed',
                null,
                null,
                ['successful' => 0, 'failed' => 0, 'advances_created' => 0]
            ));

            throw $e;
        }
    }

    /**
     * Prepare payroll record data structure
     */
    private function preparePayrollRecord(Employment $employment, $allocation, array $payrollData, Carbon $payPeriodDate): array
    {
        $calculations = $payrollData['calculations'];

        return [
            'employment_id' => $employment->id,
            'employee_funding_allocation_id' => $allocation->id,
            'pay_period_date' => $payPeriodDate->format('Y-m-d'),
            'gross_salary' => $calculations['gross_salary'],
            'gross_salary_by_FTE' => $calculations['gross_salary_by_fte'], // Maps to uppercase column
            'retroactive_adjustment' => $calculations['retroactive_adjustment'] ?? 0,
            'thirteen_month_salary' => $calculations['thirteen_month_salary'],
            'thirteen_month_salary_accured' => $calculations['thirteen_month_salary_accured'],
            'pvd' => $calculations['pvd'],
            'saving_fund' => $calculations['saving_fund'],
            'employer_social_security' => $calculations['employer_social_security'],
            'employee_social_security' => $calculations['employee_social_security'],
            'employer_health_welfare' => $calculations['employer_health_welfare'],
            'employee_health_welfare' => $calculations['employee_health_welfare'],
            'tax' => $calculations['income_tax'], // Maps income_tax to tax column
            'net_salary' => $calculations['net_salary'],
            'total_salary' => $calculations['total_salary'],
            'total_pvd' => $calculations['total_pvd'],
            'total_saving_fund' => $calculations['total_saving_fund'],
            'salary_bonus' => $calculations['salary_bonus'],
            'total_income' => $calculations['total_income'],
            'employer_contribution' => $calculations['employer_contribution'],
            'total_deduction' => $calculations['total_deduction'],
            'notes' => null,
        ];
    }

    /**
     * Insert batch of payrolls into database
     */
    private function insertPayrollBatch(array $payrollRecords): array
    {
        DB::beginTransaction();

        try {
            $insertedPayrolls = [];

            foreach ($payrollRecords as $record) {
                $payroll = Payroll::create($record);
                $insertedPayrolls[] = $payroll;
            }

            DB::commit();

            return $insertedPayrolls;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Create inter-organization advances for payrolls if needed
     */
    private function createAdvancesForPayrolls(array $payrolls, PayrollService $payrollService, Carbon $payPeriodDate): int
    {
        $advancesCount = 0;

        foreach ($payrolls as $payroll) {
            $payroll->load(['employment.employee', 'employeeFundingAllocation.grantItem.grant']);

            $employee = $payroll->employment->employee;
            $allocation = $payroll->employeeFundingAllocation;

            try {
                $advance = $payrollService->createInterOrganizationAdvanceIfNeeded($employee, $allocation, $payroll, $payPeriodDate);

                if ($advance) {
                    $advancesCount++;
                }
            } catch (\Exception $e) {
                Log::warning("ProcessBulkPayroll: Could not create advance for payroll {$payroll->id}: ".$e->getMessage());
            }
        }

        return $advancesCount;
    }

    /**
     * Get allocation label for display
     */
    private function getAllocationLabel($allocation): string
    {
        $label = '';
        $loe = $allocation->fte ?? 0;
        $loePercentage = ($loe * 100);

        if ($allocation->grantItem && $allocation->grantItem->grant) {
            $grantCode = $allocation->grantItem->grant->code ?? 'Unknown';
            $positionTitle = $allocation->grantItem->grant_position ?? 'Position';
            $label = "{$grantCode} - {$positionTitle} ({$loePercentage}%)";
        } else {
            $label = "Allocation ({$loePercentage}%)";
        }

        return $label;
    }

    /**
     * Broadcast progress update
     */
    private function broadcastProgress(
        BulkPayrollBatch $batch,
        int $processed,
        int $total,
        int $successful,
        int $failed,
        int $advancesCreated,
        string $employeeName,
        string $allocationLabel
    ): void {
        // Update batch record
        $batch->update([
            'processed_payrolls' => $processed,
            'successful_payrolls' => $successful,
            'failed_payrolls' => $failed,
            'advances_created' => $advancesCreated,
            'current_employee' => $employeeName,
            'current_allocation' => $allocationLabel,
        ]);

        // Broadcast via WebSocket
        broadcast(new PayrollBulkProgress(
            $this->batchId,
            $processed,
            $total,
            'processing',
            $employeeName,
            $allocationLabel,
            [
                'successful' => $successful,
                'failed' => $failed,
                'advances_created' => $advancesCreated,
            ]
        ));
    }
}
