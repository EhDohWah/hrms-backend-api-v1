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
                $employment->end_date ? Carbon::parse($employment->end_date) : null
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
            'position_salary' => $employment->position_salary,
        ]);
    }

    /**
     * Handle the Employment "updating" event.
     */
    public function updating(Employment $employment): void
    {
        // Only validate dates if they're being changed
        if ($employment->isDirty(['start_date', 'end_date'])) {
            $dateValidation = $this->fundingService->validateEmploymentDates(
                $employment->employee,
                Carbon::parse($employment->start_date),
                $employment->end_date ? Carbon::parse($employment->end_date) : null,
                $employment->id // Exclude current employment from validation
            );

            if (! $dateValidation['valid']) {
                throw new \InvalidArgumentException($dateValidation['message']);
            }
        }

        // Validate probation dates if changed
        if ($employment->isDirty(['probation_pass_date', 'start_date'])) {
            $this->validateProbationDates($employment);
        }

        // Validate salary amounts if changed
        if ($employment->isDirty(['position_salary', 'probation_salary'])) {
            $this->validateSalaryAmounts($employment);
        }

        // Check if critical changes affect existing funding allocations
        if ($employment->isDirty(['start_date', 'end_date', 'department_position_id'])) {
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
            if (isset($changes['start_date']) || isset($changes['end_date'])) {
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
        if (! $employment->probation_pass_date) {
            return; // No probation date to validate
        }

        $startDate = Carbon::parse($employment->start_date);
        $probationDate = Carbon::parse($employment->probation_pass_date);

        // Probation pass date must be before start date (probation comes first, then employment)
        if ($probationDate->gte($startDate)) {
            throw new \InvalidArgumentException(
                'Probation pass date must be before employment start date'
            );
        }

        // Probation pass date should not be too far in the past (e.g., more than 1 year before employment)
        if ($probationDate->lt($startDate->copy()->subYear())) {
            throw new \InvalidArgumentException(
                'Probation pass date cannot be more than 1 year before employment start date'
            );
        }

        // If employment has end date, probation should be before both start and end date
        if ($employment->end_date) {
            $endDate = Carbon::parse($employment->end_date);
            // This validation is already covered by the probation < start date check
            // since start date < end date, probation will automatically be < end date
        }
    }

    /**
     * Validate salary amounts are reasonable
     */
    private function validateSalaryAmounts(Employment $employment): void
    {
        // Position salary must be positive
        if ($employment->position_salary <= 0) {
            throw new \InvalidArgumentException('Position salary must be greater than zero');
        }

        // Position salary should be reasonable (not too high)
        $maxSalary = 1000000; // Reasonable maximum monthly salary

        if ($employment->position_salary > $maxSalary) {
            throw new \InvalidArgumentException(
                "Position salary ({$employment->position_salary}) exceeds maximum threshold ({$maxSalary}). Please verify amount."
            );
        }

        // Probation salary validation
        if ($employment->probation_salary !== null) {
            if ($employment->probation_salary <= 0) {
                throw new \InvalidArgumentException('Probation salary must be greater than zero');
            }

            // Probation salary should typically be less than or equal to position salary
            if ($employment->probation_salary > $employment->position_salary) {
                Log::warning('Probation salary is higher than position salary', [
                    'employment_id' => $employment->id ?? 'new',
                    'probation_salary' => $employment->probation_salary,
                    'position_salary' => $employment->position_salary,
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
        if (isset($changes['end_date']) && $changes['end_date']) {
            $newEndDate = Carbon::parse($changes['end_date']);

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

        if (! isset($changes['start_date']) && ! isset($changes['end_date'])) {
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
            if (isset($changes['end_date'])) {
                $updateData['end_date'] = $employment->end_date;
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
