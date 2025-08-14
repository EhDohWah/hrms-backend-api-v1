<?php

namespace App\Services;

use App\Models\TaxBracket;
use App\Models\TaxSetting;
use App\Models\Employee;
use App\Models\Employment;
use Carbon\Carbon;

class TaxCalculationService
{
    protected $year;
    protected $taxSettings;
    protected $taxBrackets;

    public function __construct(int $year = null)
    {
        $this->year = $year ?: date('Y');
        $this->loadTaxConfiguration();
    }

    /**
     * Load tax configuration for the specified year
     */
    private function loadTaxConfiguration(): void
    {
        $this->taxSettings = TaxSetting::getSettingsForYear($this->year);
        $this->taxBrackets = TaxBracket::getBracketsForYear($this->year);
    }

    /**
     * Calculate comprehensive payroll for an employee
     */
    public function calculatePayroll(int $employeeId, float $grossSalary, array $additionalIncome = [], array $additionalDeductions = []): array
    {
        $employee = Employee::with(['employment', 'employeeChildren'])->findOrFail($employeeId);
        
        // Calculate total income
        $totalIncome = $this->calculateTotalIncome($grossSalary, $additionalIncome);
        
        // Calculate deductions
        $deductions = $this->calculateDeductions($employee, $grossSalary, $additionalDeductions);
        
        // Calculate taxable income
        $taxableIncome = $this->calculateTaxableIncome($totalIncome, $deductions);
        
        // Calculate income tax
        $incomeTax = $this->calculateProgressiveIncomeTax($taxableIncome);
        
        // Calculate social security contributions
        $socialSecurity = $this->calculateSocialSecurity($grossSalary);
        
        // Calculate net salary
        $netSalary = $totalIncome - $deductions['total_deductions'] - $incomeTax - $socialSecurity['employee_contribution'];
        
        return [
            'gross_salary' => $grossSalary,
            'total_income' => $totalIncome,
            'deductions' => $deductions,
            'taxable_income' => $taxableIncome,
            'income_tax' => $incomeTax,
            'social_security' => $socialSecurity,
            'net_salary' => $netSalary,
            'tax_breakdown' => $this->getTaxBreakdown($taxableIncome),
            'calculation_date' => Carbon::now(),
            'tax_year' => $this->year
        ];
    }

    /**
     * Calculate total income including bonuses and allowances
     */
    private function calculateTotalIncome(float $grossSalary, array $additionalIncome = []): float
    {
        $total = $grossSalary;
        
        foreach ($additionalIncome as $income) {
            $total += floatval($income['amount'] ?? 0);
        }
        
        return $total;
    }

    /**
     * Calculate all applicable deductions
     */
    private function calculateDeductions(Employee $employee, float $grossSalary, array $additionalDeductions = []): array
    {
        $deductions = [
            'personal_allowance' => $this->getPersonalAllowance(),
            'spouse_allowance' => $this->getSpouseAllowance($employee),
            'child_allowance' => $this->getChildAllowance($employee),
            'personal_expenses' => $this->getPersonalExpenseDeduction($grossSalary),
            'provident_fund' => $this->getProvidentFundDeduction($grossSalary),
            'additional_deductions' => 0
        ];

        // Add additional deductions
        foreach ($additionalDeductions as $deduction) {
            $deductions['additional_deductions'] += floatval($deduction['amount'] ?? 0);
        }

        $deductions['total_deductions'] = array_sum($deductions);
        
        return $deductions;
    }

    /**
     * Get personal allowance amount
     */
    private function getPersonalAllowance(): float
    {
        return $this->getSetting(TaxSetting::KEY_PERSONAL_ALLOWANCE, 60000);
    }

    /**
     * Get spouse allowance if applicable
     */
    private function getSpouseAllowance(Employee $employee): float
    {
        $isMarried = in_array(strtolower($employee->marital_status ?? ''), ['married', 'แต่งงาน']);
        
        if (!$isMarried) {
            return 0;
        }
        
        return $this->getSetting(TaxSetting::KEY_SPOUSE_ALLOWANCE, 60000);
    }

    /**
     * Get child allowance based on number of children
     */
    private function getChildAllowance(Employee $employee): float
    {
        $numberOfChildren = $employee->employeeChildren->count();
        $allowancePerChild = $this->getSetting(TaxSetting::KEY_CHILD_ALLOWANCE, 30000);
        
        // Typically limited to 3 children for tax purposes
        $eligibleChildren = min($numberOfChildren, 3);
        
        return $eligibleChildren * $allowancePerChild;
    }

    /**
     * Calculate personal expense deduction
     */
    private function getPersonalExpenseDeduction(float $grossSalary): float
    {
        $rate = $this->getSetting(TaxSetting::KEY_PERSONAL_EXPENSE_RATE, 0.40); // 40%
        $maxAmount = $this->getSetting(TaxSetting::KEY_PERSONAL_EXPENSE_MAX, 60000);
        
        $yearlyGross = $grossSalary * 12;
        $calculated = $yearlyGross * ($rate / 100);
        
        return min($calculated, $maxAmount);
    }

    /**
     * Calculate provident fund deduction
     */
    private function getProvidentFundDeduction(float $grossSalary): float
    {
        $minRate = $this->getSetting(TaxSetting::KEY_PF_MIN_RATE, 3); // 3%
        $maxRate = $this->getSetting(TaxSetting::KEY_PF_MAX_RATE, 15); // 15%
        
        $yearlyGross = $grossSalary * 12;
        $minContribution = $yearlyGross * ($minRate / 100);
        $maxContribution = $yearlyGross * ($maxRate / 100);
        
        // Use minimum rate for automatic calculation
        return $minContribution;
    }

    /**
     * Calculate taxable income after deductions
     */
    private function calculateTaxableIncome(float $totalIncome, array $deductions): float
    {
        $yearlyIncome = $totalIncome * 12;
        $totalDeductions = $deductions['total_deductions'];
        
        return max(0, $yearlyIncome - $totalDeductions);
    }

    /**
     * Calculate progressive income tax using tax brackets
     */
    public function calculateProgressiveIncomeTax(float $taxableIncome): float
    {
        if ($taxableIncome <= 0 || $this->taxBrackets->isEmpty()) {
            return 0;
        }

        $totalTax = 0;
        $remainingIncome = $taxableIncome;

        foreach ($this->taxBrackets as $bracket) {
            if ($remainingIncome <= 0) {
                break;
            }

            $bracketMin = $bracket->min_income;
            $bracketMax = $bracket->max_income ?? PHP_FLOAT_MAX;
            $taxRate = $bracket->tax_rate;

            // Calculate taxable amount in this bracket
            $taxableInBracket = min($remainingIncome, $bracketMax - $bracketMin);
            
            // Only tax income above the bracket minimum
            if ($taxableIncome > $bracketMin) {
                $taxableInBracket = min($remainingIncome, $bracketMax - max($bracketMin, $taxableIncome - $remainingIncome));
                $tax = $taxableInBracket * ($taxRate / 100);
                $totalTax += $tax;
            }

            $remainingIncome -= $taxableInBracket;
        }

        // Return monthly tax amount
        return $totalTax / 12;
    }

    /**
     * Calculate social security contributions
     */
    private function calculateSocialSecurity(float $grossSalary): array
    {
        $ssfRate = $this->getSetting(TaxSetting::KEY_SSF_RATE, 5); // 5%
        $maxMonthly = $this->getSetting(TaxSetting::KEY_SSF_MAX_MONTHLY, 750);
        
        $employeeContribution = min($grossSalary * ($ssfRate / 100), $maxMonthly);
        $employerContribution = $employeeContribution; // Employer matches employee contribution
        
        return [
            'employee_contribution' => $employeeContribution,
            'employer_contribution' => $employerContribution,
            'total_contribution' => $employeeContribution + $employerContribution
        ];
    }

    /**
     * Get detailed tax breakdown by bracket
     */
    private function getTaxBreakdown(float $taxableIncome): array
    {
        $breakdown = [];
        $remainingIncome = $taxableIncome;
        $cumulativeIncome = 0;

        foreach ($this->taxBrackets as $bracket) {
            if ($remainingIncome <= 0) {
                break;
            }

            $bracketMin = $bracket->min_income;
            $bracketMax = $bracket->max_income ?? PHP_FLOAT_MAX;
            $taxRate = $bracket->tax_rate;

            if ($taxableIncome > $bracketMin) {
                $incomeInBracket = min($remainingIncome, $bracketMax - max($bracketMin, $cumulativeIncome));
                $taxInBracket = $incomeInBracket * ($taxRate / 100);

                $breakdown[] = [
                    'bracket_order' => $bracket->bracket_order,
                    'income_range' => $bracket->income_range,
                    'tax_rate' => $bracket->formatted_rate,
                    'taxable_income' => $incomeInBracket,
                    'tax_amount' => $taxInBracket,
                    'monthly_tax' => $taxInBracket / 12
                ];

                $cumulativeIncome += $incomeInBracket;
                $remainingIncome -= $incomeInBracket;
            }
        }

        return $breakdown;
    }

    /**
     * Get tax setting value with fallback
     */
    private function getSetting(string $key, float $default = 0): float
    {
        return TaxSetting::getValue($key, $this->year) ?? $default;
    }

    /**
     * Calculate annual tax liability
     */
    public function calculateAnnualTax(int $employeeId, array $monthlyPayrolls): array
    {
        $totalIncome = array_sum(array_column($monthlyPayrolls, 'total_income'));
        $totalDeductions = array_sum(array_column($monthlyPayrolls, 'total_deductions'));
        $totalTaxPaid = array_sum(array_column($monthlyPayrolls, 'income_tax'));
        
        $employee = Employee::with(['employment', 'employeeChildren'])->findOrFail($employeeId);
        $deductions = $this->calculateDeductions($employee, 0, []);
        
        $taxableIncome = max(0, $totalIncome - $deductions['total_deductions']);
        $actualTaxLiability = $this->calculateProgressiveIncomeTax($taxableIncome) * 12;
        
        $taxDifference = $actualTaxLiability - $totalTaxPaid;
        
        return [
            'total_income' => $totalIncome,
            'total_deductions' => $deductions['total_deductions'],
            'taxable_income' => $taxableIncome,
            'tax_liability' => $actualTaxLiability,
            'tax_paid' => $totalTaxPaid,
            'tax_difference' => $taxDifference,
            'refund_due' => $taxDifference < 0 ? abs($taxDifference) : 0,
            'additional_tax_due' => $taxDifference > 0 ? $taxDifference : 0
        ];
    }

    /**
     * Validate tax calculation inputs
     */
    public function validateCalculationInputs(array $inputs): array
    {
        $errors = [];

        if (!isset($inputs['employee_id']) || !is_numeric($inputs['employee_id'])) {
            $errors[] = 'Valid employee ID is required';
        }

        if (!isset($inputs['gross_salary']) || !is_numeric($inputs['gross_salary']) || $inputs['gross_salary'] < 0) {
            $errors[] = 'Valid gross salary amount is required';
        }

        if (isset($inputs['additional_income']) && !is_array($inputs['additional_income'])) {
            $errors[] = 'Additional income must be an array';
        }

        if (isset($inputs['additional_deductions']) && !is_array($inputs['additional_deductions'])) {
            $errors[] = 'Additional deductions must be an array';
        }

        return $errors;
    }
}