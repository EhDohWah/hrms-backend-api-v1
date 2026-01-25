<?php

namespace App\Observers;

use App\Models\Employment;
use App\Services\FundingAllocationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class EmploymentObserver
{
    protected FundingAllocationService $fundingService;

    public function __construct(FundingAllocationService $fundingService)
    {
        $this->fundingService = $fundingService;
    }

    /**
     * Handle the Employment "creating" event.
     */
    public function creating(Employment $employment): void
    {
        // Get employee by ID since relationship might not be loaded during creating event
        $employee = \App\Models\Employee::find($employment->employee_id);

        if ($employee) {
            // Validate employment dates don't overlap with existing employments
            $dateValidation = $this->fundingService->validateEmploymentDates(
                $employee,
                Carbon::parse($employment->start_date),
                $employment->end_probation_date ? Carbon::parse($employment->end_probation_date) : null
            );

            if (! $dateValidation['valid']) {
                throw new \InvalidArgumentException($dateValidation['message']);
            }
        }

        // Validate probation dates
        $this->validateProbationDates($employment);

        // Validate salary amounts
        $this->validateSalaryAmounts($employment);

        Log::info('Employment validation passed for employee ID: '.$employment->employee_id);
    }

    /**
     * Handle the Employment "created" event.
     */
    public function created(Employment $employment): void
    {
        Log::info('Employment created successfully', [
            'employment_id' => $employment->id,
            'employee_id' => $employment->employee_id,
            'start_date' => $employment->start_date,
            'pass_probation_salary' => $employment->pass_probation_salary,
        ]);
    }

    /**
     * Handle the Employment "updating" event.
     */
    public function updating(Employment $employment): void
    {
        // Only validate dates if they're being changed
        if ($employment->isDirty(['start_date', 'end_probation_date'])) {
            $dateValidation = $this->fundingService->validateEmploymentDates(
                $employment->employee,
                Carbon::parse($employment->start_date),
                $employment->end_probation_date ? Carbon::parse($employment->end_probation_date) : null,
                $employment->id // Exclude current employment from validation
            );

            if (! $dateValidation['valid']) {
                throw new \InvalidArgumentException($dateValidation['message']);
            }
        }

        // Validate probation dates if changed
        if ($employment->isDirty(['pass_probation_date', 'start_date'])) {
            $this->validateProbationDates($employment);
        }

        // Validate salary amounts if changed
        if ($employment->isDirty(['pass_probation_salary', 'probation_salary'])) {
            $this->validateSalaryAmounts($employment);
        }

        // Check if critical changes affect existing funding allocations
        if ($employment->isDirty(['start_date', 'end_probation_date', 'department_id', 'position_id'])) {
            $this->validateFundingAllocationImpact($employment);
        }
    }

    /**
     * Handle the Employment "updated" event.
     */
    public function updated(Employment $employment): void
    {
        $changes = $employment->getChanges();
        unset($changes['updated_at'], $changes['updated_by']);

        if (! empty($changes)) {
            Log::info('Employment updated', [
                'employment_id' => $employment->id,
                'changes' => array_keys($changes),
                'updated_by' => $employment->updated_by,
            ]);

            // Update related funding allocations if dates changed
            if (isset($changes['start_date']) || isset($changes['end_probation_date'])) {
                $this->updateRelatedAllocations($employment);
            }
        }
    }

    /**
     * Handle the Employment "deleting" event.
     */
    public function deleting(Employment $employment): void
    {
        // Check if employment has active funding allocations
        $activeAllocations = $employment->employeeFundingAllocations()
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>', now());
            })
            ->count();

        if ($activeAllocations > 0) {
            throw new \InvalidArgumentException(
                'Cannot delete employment with active funding allocations. Please end or transfer allocations first.'
            );
        }

        // Check if employment has payroll records
        $payrollCount = $employment->payrolls()->count();
        if ($payrollCount > 0) {
            throw new \InvalidArgumentException(
                'Cannot delete employment with existing payroll records. Consider marking as ended instead.'
            );
        }

        Log::warning('Employment deletion initiated', [
            'employment_id' => $employment->id,
            'employee_id' => $employment->employee_id,
        ]);
    }

    /**
     * Handle the Employment "deleted" event.
     */
    public function deleted(Employment $employment): void
    {
        Log::info('Employment deleted', [
            'employment_id' => $employment->id,
            'employee_id' => $employment->employee_id,
        ]);
    }

    /**
     * Validate probation dates are logical
     */
    private function validateProbationDates(Employment $employment): void
    {
        if (! $employment->pass_probation_date) {
            return; // No probation date to validate
        }

        $startDate = Carbon::parse($employment->start_date);
        $probationDate = Carbon::parse($employment->pass_probation_date);

        // Probation pass date must be after start date (probation period ends after employment begins)
        if ($probationDate->lte($startDate)) {
            throw new \InvalidArgumentException(
                'Pass probation date must be after employment start date'
            );
        }

        // Probation pass date should typically be within 6 months of start date (flexible for exceptional cases)
        if ($probationDate->gt($startDate->copy()->addMonths(6))) {
            throw new \InvalidArgumentException(
                'Pass probation date should typically be within 6 months of employment start date'
            );
        }

        // If employment has end date, probation should be before end date
        if ($employment->end_probation_date) {
            $endDate = Carbon::parse($employment->end_probation_date);
            if ($probationDate->gte($endDate)) {
                throw new \InvalidArgumentException(
                    'Pass probation date must be before employment end date'
                );
            }
        }
    }

    /**
     * Validate salary amounts are reasonable
     */
    private function validateSalaryAmounts(Employment $employment): void
    {
        // Pass probation salary must be positive
        if ($employment->pass_probation_salary <= 0) {
            throw new \InvalidArgumentException('Pass probation salary must be greater than zero');
        }

        // Position salary should be reasonable (not too high)
        $maxSalary = 1000000; // Reasonable maximum monthly salary

        if ($employment->pass_probation_salary > $maxSalary) {
            throw new \InvalidArgumentException(
                "Pass probation salary ({$employment->pass_probation_salary}) exceeds maximum threshold ({$maxSalary}). Please verify amount."
            );
        }

        // Probation salary validation
        if ($employment->probation_salary !== null) {
            if ($employment->probation_salary <= 0) {
                throw new \InvalidArgumentException('Probation salary must be greater than zero');
            }

            // Probation salary should typically be less than or equal to pass probation salary
            if ($employment->probation_salary > $employment->pass_probation_salary) {
                Log::warning('Probation salary is higher than pass probation salary', [
                    'employment_id' => $employment->id ?? 'new',
                    'probation_salary' => $employment->probation_salary,
                    'pass_probation_salary' => $employment->pass_probation_salary,
                ]);
            }
        }
    }

    /**
     * Validate how employment changes affect existing funding allocations
     */
    private function validateFundingAllocationImpact(Employment $employment): void
    {
        $activeAllocations = $employment->employeeFundingAllocations()
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>', now());
            })
            ->count();

        if ($activeAllocations === 0) {
            return; // No active allocations to impact
        }

        $changes = $employment->getDirty();

        // If start date is moved forward, warn about potential allocation issues
        if (isset($changes['start_date'])) {
            $oldStartDate = Carbon::parse($employment->getOriginal('start_date'));
            $newStartDate = Carbon::parse($changes['start_date']);

            if ($newStartDate->gt($oldStartDate)) {
                Log::warning('Employment start date moved forward with active allocations', [
                    'employment_id' => $employment->id,
                    'old_start_date' => $oldStartDate->format('Y-m-d'),
                    'new_start_date' => $newStartDate->format('Y-m-d'),
                    'active_allocations' => $activeAllocations,
                ]);
            }
        }

        // If end date is moved backward, check if it affects allocations
        if (isset($changes['end_probation_date']) && $changes['end_probation_date']) {
            $newEndDate = Carbon::parse($changes['end_probation_date']);

            $affectedAllocations = $employment->employeeFundingAllocations()
                ->where(function ($query) use ($newEndDate) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>', $newEndDate);
                })
                ->count();

            if ($affectedAllocations > 0) {
                Log::warning('Employment end date may affect funding allocations', [
                    'employment_id' => $employment->id,
                    'new_end_date' => $newEndDate->format('Y-m-d'),
                    'affected_allocations' => $affectedAllocations,
                ]);
            }
        }
    }

    /**
     * Update related funding allocations when employment dates change
     */
    private function updateRelatedAllocations(Employment $employment): void
    {
        $changes = $employment->getChanges();

        if (! isset($changes['start_date']) && ! isset($changes['end_probation_date'])) {
            return;
        }

        $allocationsToUpdate = $employment->employeeFundingAllocations()
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>', now());
            })
            ->get();

        foreach ($allocationsToUpdate as $allocation) {
            $updateData = [];

            // Update start date if employment start date changed
            if (isset($changes['start_date'])) {
                $updateData['start_date'] = $employment->start_date;
            }

            // Update end date if employment end date changed
            if (isset($changes['end_probation_date'])) {
                $updateData['end_date'] = $employment->end_probation_date;
            }

            if (! empty($updateData)) {
                $updateData['updated_by'] = $employment->updated_by ?? 'system';
                $allocation->update($updateData);

                Log::info('Updated funding allocation dates', [
                    'allocation_id' => $allocation->id,
                    'employment_id' => $employment->id,
                    'updates' => array_keys($updateData),
                ]);
            }
        }
    }
}
