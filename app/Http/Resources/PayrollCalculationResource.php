<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollCalculationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Basic salary information
            'gross_salary' => $this->resource['gross_salary'],
            'total_income' => $this->resource['total_income'],
            'net_salary' => $this->resource['net_salary'],

            // Tax information
            'taxable_income' => $this->resource['taxable_income'],
            'income_tax' => $this->resource['income_tax'],
            'tax_year' => $this->resource['tax_year'],

            // Deductions breakdown
            'deductions' => [
                'personal_allowance' => $this->resource['deductions']['personal_allowance'],
                'spouse_allowance' => $this->resource['deductions']['spouse_allowance'],
                'child_allowance' => $this->resource['deductions']['child_allowance'],
                'personal_expenses' => $this->resource['deductions']['personal_expenses'],
                'provident_fund' => $this->resource['deductions']['provident_fund'],
                'additional_deductions' => $this->resource['deductions']['additional_deductions'],
                'total_deductions' => $this->resource['deductions']['total_deductions'],
            ],

            // Social security breakdown
            'social_security' => [
                'employee_contribution' => $this->resource['social_security']['employee_contribution'],
                'employer_contribution' => $this->resource['social_security']['employer_contribution'],
                'total_contribution' => $this->resource['social_security']['total_contribution'],
            ],

            // Tax calculation breakdown
            'tax_breakdown' => $this->resource['tax_breakdown'],

            // Formatted values for display
            'formatted' => [
                'gross_salary' => '฿'.number_format($this->resource['gross_salary'], 2),
                'total_income' => '฿'.number_format($this->resource['total_income'], 2),
                'net_salary' => '฿'.number_format($this->resource['net_salary'], 2),
                'income_tax' => '฿'.number_format($this->resource['income_tax'], 2),
                'total_deductions' => '฿'.number_format($this->resource['deductions']['total_deductions'], 2),
                'employee_ss_contribution' => '฿'.number_format($this->resource['social_security']['employee_contribution'], 2),
            ],

            // Summary ratios
            'ratios' => [
                'tax_rate' => $this->getTaxRate(),
                'deduction_rate' => $this->getDeductionRate(),
                'net_rate' => $this->getNetRate(),
                'ss_rate' => $this->getSocialSecurityRate(),
            ],

            // Calculation metadata
            'calculation_date' => $this->resource['calculation_date'],
            'calculation_summary' => $this->getCalculationSummary(),
        ];
    }

    /**
     * Calculate effective tax rate
     */
    private function getTaxRate(): float
    {
        $totalIncome = $this->resource['total_income'];
        if ($totalIncome > 0) {
            return round(($this->resource['income_tax'] / $totalIncome) * 100, 2);
        }

        return 0;
    }

    /**
     * Calculate deduction rate
     */
    private function getDeductionRate(): float
    {
        $totalIncome = $this->resource['total_income'];
        if ($totalIncome > 0) {
            return round(($this->resource['deductions']['total_deductions'] / ($totalIncome * 12)) * 100, 2);
        }

        return 0;
    }

    /**
     * Calculate net salary rate
     */
    private function getNetRate(): float
    {
        $totalIncome = $this->resource['total_income'];
        if ($totalIncome > 0) {
            return round(($this->resource['net_salary'] / $totalIncome) * 100, 2);
        }

        return 0;
    }

    /**
     * Calculate social security rate
     */
    private function getSocialSecurityRate(): float
    {
        $grossSalary = $this->resource['gross_salary'];
        if ($grossSalary > 0) {
            return round(($this->resource['social_security']['employee_contribution'] / $grossSalary) * 100, 2);
        }

        return 0;
    }

    /**
     * Get calculation summary
     */
    private function getCalculationSummary(): array
    {
        return [
            'total_cost_to_employer' => $this->resource['total_income'] + $this->resource['social_security']['employer_contribution'],
            'total_employee_deductions' => $this->resource['income_tax'] + $this->resource['social_security']['employee_contribution'],
            'take_home_percentage' => $this->getNetRate(),
            'effective_tax_rate' => $this->getTaxRate(),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'currency' => 'THB',
                'calculation_method' => 'Progressive tax system',
                'frequency' => 'monthly',
                'notes' => [
                    'Tax calculations are based on annual income with monthly withholding',
                    'Social security contributions are matched by employer',
                    'Deductions are applied to reduce taxable income',
                ],
            ],
        ];
    }
}
