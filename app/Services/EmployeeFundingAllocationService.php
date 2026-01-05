<?php

namespace App\Services;

use App\Models\EmployeeFundingAllocation;
use App\Models\Employment;
use Carbon\Carbon;
use InvalidArgumentException;

class EmployeeFundingAllocationService
{
    /**
     * Determine salary metadata for an allocation context.
     */
    public function deriveSalaryContext(
        Employment $employment,
        float $fte,
        ?Carbon $effectiveDate = null
    ): array {
        if (is_null($employment->pass_probation_salary) && is_null($employment->probation_salary)) {
            throw new InvalidArgumentException('Employment must define a salary before allocations can be created.');
        }

        $effectiveDate ??= $employment->start_date instanceof Carbon
            ? $employment->start_date
            : Carbon::parse($employment->start_date ?? Carbon::now());

        $salaryType = $employment->getSalaryTypeForDate($effectiveDate);
        $salaryAmount = $employment->getSalaryAmountForDate($effectiveDate);

        return [
            'salary_type' => $salaryType,
            'allocated_amount' => round($salaryAmount * $fte, 2),
        ];
    }

    /**
     * Apply salary metadata directly to an allocation instance.
     */
    public function applySalaryContext(
        EmployeeFundingAllocation $allocation,
        ?Carbon $effectiveDate = null
    ): EmployeeFundingAllocation {
        $employment = $allocation->employment ?? $allocation->employment()->first();

        if (! $employment) {
            throw new InvalidArgumentException('EmployeeFundingAllocation must belong to an employment.');
        }

        if (! $effectiveDate) {
            $effectiveDate = $allocation->start_date
                ? Carbon::parse($allocation->start_date)
                : ($employment->start_date instanceof Carbon ? $employment->start_date : Carbon::now());
        }

        $allocation->fill(
            $this->deriveSalaryContext($employment, (float) $allocation->fte, $effectiveDate)
        );

        return $allocation;
    }
}
