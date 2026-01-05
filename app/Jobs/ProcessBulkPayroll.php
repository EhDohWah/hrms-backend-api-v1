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

    protected string $payPeriod;

    protected array $employmentIds;

    /**
     * Create a new job instance.
     */
    public function __construct(int $batchId, string $payPeriod, array $employmentIds)
    {
        $this->batchId = $batchId;
        $this->payPeriod = $payPeriod;
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

            // Parse pay period to get first day of month
            $payPeriodDate = Carbon::createFromFormat('Y-m', $this->payPeriod)->startOfMonth();

            // Load all employments with relationships
            $employments = Employment::with([
                'employee',
                'department',
                'position',
            ])->whereIn('id', $this->employmentIds)->get();

            // Load employeeFundingAllocations with their relationships separately
            $employeeIds = $employments->pluck('employee.id')->filter()->unique();

            if ($employeeIds->isNotEmpty()) {
                $allocations = EmployeeFundingAllocation::whereIn('employee_id', $employeeIds)
                    ->where(function ($q) use ($payPeriodDate) {
                        $q->where('start_date', '<=', $payPeriodDate)
                            ->where(function ($subQ) use ($payPeriodDate) {
                                $subQ->whereNull('end_date')
                                    ->orWhere('end_date', '>=', $payPeriodDate);
                            });
                    })
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

            // Calculate total payrolls (sum of all allocations across all employments)
            $totalPayrolls = 0;
            foreach ($employments as $employment) {
                $totalPayrolls += $employment->employee->employeeFundingAllocations->count();
            }

            // Update batch with totals
            $batch->update([
                'total_employees' => $employments->count(),
                'total_payrolls' => $totalPayrolls,
            ]);

            // Initialize PayrollService
            $payrollService = new PayrollService(Carbon::parse($payPeriodDate)->year);

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

                // Process each funding allocation for this employee
                foreach ($allocations as $allocation) {
                    $allocationLabel = $this->getAllocationLabel($allocation);

                    try {
                        // Calculate payroll for this specific allocation
                        $payrollData = $payrollService->calculateAllocationPayrollForController(
                            $employee,
                            $allocation,
                            $payPeriodDate
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
            'pay_period_date' => $payPeriodDate->startOfMonth(),
            'gross_salary' => $calculations['gross_salary'],
            'gross_salary_by_FTE' => $calculations['gross_salary_by_fte'], // Maps to uppercase column
            'compensation_refund' => $calculations['compensation_refund'],
            'thirteen_month_salary' => $calculations['thirteenth_month_salary'], // Using underscored key
            'thirteen_month_salary_accured' => $calculations['thirteenth_month_salary'], // Same value
            'pvd' => $calculations['pvd'],
            'saving_fund' => $calculations['saving_fund'],
            'employer_social_security' => $calculations['employer_social_security'],
            'employee_social_security' => $calculations['employee_social_security'],
            'employer_health_welfare' => $calculations['employer_health_welfare'],
            'employee_health_welfare' => $calculations['employee_health_welfare'],
            'tax' => $calculations['income_tax'], // Maps income_tax to tax column
            'net_salary' => $calculations['net_salary'],
            'total_salary' => $calculations['total_salary'],
            'total_pvd' => $calculations['pvd'], // Total PVD (employee portion)
            'total_saving_fund' => $calculations['saving_fund'], // Total saving fund
            'salary_bonus' => 0, // Default to 0
            'total_income' => $calculations['total_income'] ?? ($calculations['gross_salary'] + $calculations['compensation_refund'] + $calculations['thirteenth_month_salary']),
            'employer_contribution' => $calculations['employer_contribution'] ?? ($calculations['employer_social_security'] + $calculations['employer_health_welfare']),
            'total_deduction' => $calculations['total_deduction'] ?? ($calculations['pvd'] + $calculations['saving_fund'] + $calculations['employee_social_security'] + $calculations['employee_health_welfare'] + $calculations['income_tax']),
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
