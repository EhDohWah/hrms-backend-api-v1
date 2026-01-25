<?php

namespace App\Services;

use App\Models\EmployeeFundingAllocation;
use App\Models\Employment;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling probation period transitions and allocation lifecycle management
 */
class ProbationTransitionService
{
    public function __construct(
        private readonly EmployeeFundingAllocationService $employeeFundingAllocationService
    ) {}

    /**
     * Process all probation transitions for employments ready for transition
     * This should be run daily via scheduled task
     */
    public function processTransitions(): array
    {
        $results = [
            'processed' => 0,
            'failed' => 0,
            'skipped' => 0,
            'details' => [],
        ];

        try {
            // Find all employments that are ready for transition today
            $employments = Employment::whereNotNull('pass_probation_date')
                ->whereDate('pass_probation_date', Carbon::today())
                ->whereNull('end_probation_date')
                ->where(function ($query) {
                    $query->whereNull('probation_status')
                        ->orWhere('probation_status', 'ongoing')
                        ->orWhere('probation_status', 'extended');
                })
                ->with('activeAllocations')
                ->get();

            foreach ($employments as $employment) {
                try {
                    $result = $this->transitionEmploymentAllocations($employment);

                    if ($result['success']) {
                        $results['processed']++;
                    } else {
                        $results['failed']++;
                    }

                    $results['details'][] = $result;
                } catch (\Exception $e) {
                    $results['failed']++;
                    $results['details'][] = [
                        'success' => false,
                        'employment_id' => $employment->id,
                        'message' => 'Exception: '.$e->getMessage(),
                    ];

                    Log::error('Probation transition failed', [
                        'employment_id' => $employment->id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            Log::info('Probation transitions completed', $results);

            return $results;
        } catch (\Exception $e) {
            Log::error('Probation transition process failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'processed' => 0,
                'failed' => 0,
                'skipped' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Wrapper for transitionEmploymentAllocations with optional date context.
     */
    public function handleProbationCompletion(Employment $employment, ?Carbon $transitionDate = null): array
    {
        return $this->transitionEmploymentAllocations($employment, $transitionDate);
    }

    /**
     * Transition a single employment's allocations from probation to pass probation
     */
    public function transitionEmploymentAllocations(Employment $employment, ?Carbon $transitionDate = null): array
    {
        try {
            DB::beginTransaction();

            $changedBy = Auth::user()?->name ?? 'system';
            $transitionDate ??= Carbon::today();
            $yesterday = $transitionDate->copy()->subDay();

            // Get all active allocations
            $activeAllocations = $employment->activeAllocations()->get();

            if ($activeAllocations->isEmpty()) {
                DB::rollBack();

                return [
                    'success' => false,
                    'employment_id' => $employment->id,
                    'message' => 'No active allocations found',
                ];
            }

            $newAllocations = [];

            foreach ($activeAllocations as $allocation) {
                // Mark existing allocation as historical
                $allocation->update([
                    'status' => 'historical',
                    'end_date' => $yesterday,
                    'updated_by' => $changedBy,
                ]);

                $salaryContext = $this->employeeFundingAllocationService->deriveSalaryContext(
                    $employment,
                    (float) $allocation->fte,
                    $transitionDate
                );

                // Create new active allocation with pass_probation_salary
                $newAllocation = EmployeeFundingAllocation::create(array_merge([
                    'employee_id' => $allocation->employee_id,
                    'employment_id' => $allocation->employment_id,
                    'grant_item_id' => $allocation->grant_item_id,
                    'grant_id' => $allocation->grant_id,
                    'fte' => $allocation->fte,
                    'allocation_type' => $allocation->allocation_type,
                    'status' => 'active',
                    'start_date' => $transitionDate,
                    'end_date' => null,
                    'created_by' => $changedBy,
                    'updated_by' => $changedBy,
                ], $salaryContext));

                $newAllocations[] = $newAllocation->id;
            }

            // Create probation record for passed status
            app(ProbationRecordService::class)->markAsPassed(
                $employment,
                sprintf(
                    'Transition date: %s. %d allocations marked historical, %d new active allocations created.',
                    $transitionDate->format('Y-m-d'),
                    $activeAllocations->count(),
                    count($newAllocations)
                )
            );

            // NOTE: markAsPassed() already updates employment probation_status to 'passed'

            // Create employment history entry
            $employment->addHistoryEntry(
                reason: 'Probation period completed - transitioned to pass_probation_salary',
                notes: sprintf(
                    'Transition date: %s. %d allocations marked historical, %d new active allocations created.',
                    $transitionDate->format('Y-m-d'),
                    $activeAllocations->count(),
                    count($newAllocations)
                ),
                changedBy: $changedBy
            );

            DB::commit();

            Log::info('Probation transition completed', [
                'employment_id' => $employment->id,
                'employee_id' => $employment->employee_id,
                'historical_allocations' => $activeAllocations->pluck('id')->toArray(),
                'new_allocations' => $newAllocations,
                'changed_by' => $changedBy,
                'transition_date' => $transitionDate->format('Y-m-d'),
            ]);

            return [
                'success' => true,
                'employment_id' => $employment->id,
                'message' => 'Probation transition completed successfully',
                'historical_count' => $activeAllocations->count(),
                'new_count' => count($newAllocations),
                'employment' => $employment->fresh(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error transitioning employment allocations', [
                'employment_id' => $employment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'employment_id' => $employment->id,
                'message' => 'Error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Handle early termination - when employment ends before probation completion
     */
    public function handleEarlyTermination(Employment $employment): array
    {
        try {
            DB::beginTransaction();

            $changedBy = Auth::user()?->name ?? 'system';

            // Get all active allocations
            $activeAllocations = $employment->activeAllocations()->get();

            if ($activeAllocations->isEmpty()) {
                DB::rollBack();

                return [
                    'success' => false,
                    'employment_id' => $employment->id,
                    'message' => 'No active allocations found',
                ];
            }

            // Mark all active allocations as terminated
            foreach ($activeAllocations as $allocation) {
                $allocation->update([
                    'status' => 'terminated',
                    'end_date' => $employment->end_probation_date,
                    'updated_by' => $changedBy,
                ]);
            }

            // Create probation record for failed status
            app(ProbationRecordService::class)->markAsFailed(
                $employment,
                'Employment terminated during probation period',
                sprintf(
                    'Termination date: %s. %d allocations marked as terminated. Probation was not completed.',
                    $employment->end_probation_date->format('Y-m-d'),
                    $activeAllocations->count()
                )
            );

            // NOTE: markAsFailed() already updates employment probation_status to 'failed'

            // Create employment history entry
            $employment->addHistoryEntry(
                reason: 'Employment terminated during probation period',
                notes: sprintf(
                    'Termination date: %s. %d allocations marked as terminated. Probation was not completed.',
                    $employment->end_probation_date->format('Y-m-d'),
                    $activeAllocations->count()
                ),
                changedBy: $changedBy
            );

            DB::commit();

            Log::info('Early termination processed', [
                'employment_id' => $employment->id,
                'employee_id' => $employment->employee_id,
                'end_date' => $employment->end_probation_date->format('Y-m-d'),
                'terminated_allocations' => $activeAllocations->pluck('id')->toArray(),
                'changed_by' => $changedBy,
            ]);

            return [
                'success' => true,
                'employment_id' => $employment->id,
                'message' => 'Early termination processed successfully',
                'terminated_count' => $activeAllocations->count(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error processing early termination', [
                'employment_id' => $employment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'employment_id' => $employment->id,
                'message' => 'Error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Handle manual probation failure decisions without requiring employment termination flow.
     */
    public function handleManualProbationFailure(
        Employment $employment,
        Carbon $decisionDate,
        ?string $reason = null,
        ?string $notes = null
    ): array {
        try {
            DB::beginTransaction();

            $changedBy = Auth::user()?->name ?? 'system';
            $activeAllocations = $employment->activeAllocations()->get();

            if ($activeAllocations->isEmpty()) {
                DB::rollBack();

                return [
                    'success' => false,
                    'employment_id' => $employment->id,
                    'message' => 'No active allocations found',
                ];
            }

            // Ensure employment end_probation_date reflects the failure decision
            if (! $employment->end_probation_date || $employment->end_probation_date->lt($decisionDate)) {
                $employment->end_probation_date = $decisionDate;
                $employment->save();
            }

            foreach ($activeAllocations as $allocation) {
                $allocation->update([
                    'status' => 'terminated',
                    'end_date' => $decisionDate,
                    'updated_by' => $changedBy,
                ]);
            }

            $defaultReason = $reason ?? 'Probation marked as failed by HR';
            $defaultNotes = $notes ?? sprintf(
                'Decision date: %s. %d allocations marked as terminated.',
                $decisionDate->format('Y-m-d'),
                $activeAllocations->count()
            );

            app(ProbationRecordService::class)->markAsFailed(
                $employment,
                $defaultReason,
                $defaultNotes
            );

            $employment->addHistoryEntry(
                reason: $defaultReason,
                notes: $defaultNotes,
                changedBy: $changedBy
            );

            DB::commit();

            Log::info('Manual probation failure processed', [
                'employment_id' => $employment->id,
                'employee_id' => $employment->employee_id,
                'decision_date' => $decisionDate->format('Y-m-d'),
                'terminated_allocations' => $activeAllocations->pluck('id')->toArray(),
                'changed_by' => $changedBy,
            ]);

            return [
                'success' => true,
                'employment_id' => $employment->id,
                'message' => 'Probation marked as failed successfully',
                'employment' => $employment->fresh(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error processing manual probation failure', [
                'employment_id' => $employment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'employment_id' => $employment->id,
                'message' => 'Error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Handle probation extension - when pass_probation_date is changed to a future date
     */
    public function handleProbationExtension(
        Employment $employment,
        string $oldDate,
        string $newDate,
        ?string $reason = null,
        ?string $notes = null
    ): array {
        try {
            DB::beginTransaction();

            $changedBy = Auth::user()?->name ?? 'system';

            // Create probation extension record
            $defaultReason = $reason ?? sprintf(
                'Probation date changed from %s to %s',
                Carbon::parse($oldDate)->format('Y-m-d'),
                Carbon::parse($newDate)->format('Y-m-d')
            );

            $defaultNotes = $notes ?? 'Active allocations remain unchanged.';

            app(ProbationRecordService::class)->createExtensionRecord(
                $employment,
                Carbon::parse($newDate),
                $defaultReason,
                $defaultNotes
            );

            // NOTE: createExtensionRecord() already updates employment probation_status to 'extended'
            // and pass_probation_date to new date

            // Create employment history entry
            $employment->addHistoryEntry(
                reason: 'Probation period extended',
                notes: sprintf(
                    'Probation date changed from %s to %s. Active allocations remain unchanged.',
                    Carbon::parse($oldDate)->format('Y-m-d'),
                    Carbon::parse($newDate)->format('Y-m-d')
                ),
                changedBy: $changedBy
            );

            DB::commit();

            Log::info('Probation extension processed', [
                'employment_id' => $employment->id,
                'employee_id' => $employment->employee_id,
                'old_date' => $oldDate,
                'new_date' => $newDate,
                'changed_by' => $changedBy,
            ]);

            return [
                'success' => true,
                'employment_id' => $employment->id,
                'message' => 'Probation extension processed successfully',
                'old_date' => $oldDate,
                'new_date' => $newDate,
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Error processing probation extension', [
                'employment_id' => $employment->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'employment_id' => $employment->id,
                'message' => 'Error: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Calculate working days for first month when employee starts mid-month
     * Uses standardized 30-day month approach
     */
    public function calculateWorkingDays(Carbon $startDate): int
    {
        if ($startDate->day === 1) {
            return 30;
        }

        return 31 - $startDate->day;
    }

    /**
     * Calculate pro-rated salary for transition month
     */
    public function calculateProRatedSalary(Employment $employment, Carbon $payrollMonth): float
    {
        if (! $employment->pass_probation_date) {
            return $employment->getCurrentSalary();
        }

        $passProbationDate = Carbon::parse($employment->pass_probation_date);
        $isTransitionMonth = $passProbationDate->isSameMonth($payrollMonth);

        if (! $isTransitionMonth) {
            if ($payrollMonth->lt($passProbationDate->startOfMonth())) {
                return (float) ($employment->probation_salary ?? $employment->pass_probation_salary);
            } else {
                return (float) $employment->pass_probation_salary;
            }
        }

        if (! $employment->probation_salary) {
            return (float) $employment->pass_probation_salary;
        }

        $dailyProbationRate = $employment->probation_salary / 30;
        $dailyRegularRate = $employment->pass_probation_salary / 30;

        $probationDays = $passProbationDate->day - 1;
        $regularDays = 30 - $probationDays;

        $probationAmount = $probationDays * $dailyProbationRate;
        $regularAmount = $regularDays * $dailyRegularRate;

        return round($probationAmount + $regularAmount, 2);
    }

    /**
     * Calculate first month salary for new hire starting mid-month
     */
    public function calculateFirstMonthSalary(Employment $employment): float
    {
        $startDate = Carbon::parse($employment->start_date);
        $workingDays = $this->calculateWorkingDays($startDate);
        $applicableSalary = $employment->probation_salary ?? $employment->pass_probation_salary;
        $dailyRate = $applicableSalary / 30;

        return round($dailyRate * $workingDays, 2);
    }

    /**
     * Auto-calculate pass_probation_date based on start_date
     */
    public function calculatePassProbationDate(Carbon $startDate, int $months = 3): Carbon
    {
        return $startDate->copy()->addMonths($months);
    }

    /**
     * Check if a payroll month includes probation transition
     */
    public function isTransitionMonth(Employment $employment, Carbon $payrollMonth): bool
    {
        if (! $employment->pass_probation_date) {
            return false;
        }

        $passProbationDate = Carbon::parse($employment->pass_probation_date);

        return $passProbationDate->isSameMonth($payrollMonth);
    }

    /**
     * Check if employee started mid-month in a given month
     */
    public function startedMidMonthIn(Employment $employment, Carbon $month): bool
    {
        $startDate = Carbon::parse($employment->start_date);

        return $startDate->isSameMonth($month) && $startDate->day > 1;
    }
}
