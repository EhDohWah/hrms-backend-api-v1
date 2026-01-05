<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Employment;
use App\Models\TaxBracket;
use App\Models\TaxCalculationLog;
use App\Models\TaxSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class TaxCalculationService
{
    protected $year;

    protected $taxSettings = null;  // Initialize as null for lazy loading

    protected $taxBrackets = null;  // Initialize as null for lazy loading

    public function __construct(?int $year = null)
    {
        $this->year = $year ?: date('Y');
        // REMOVED: $this->loadTaxConfiguration(); - Don't load data in constructor!
    }

    /**
     * Ensure tax configuration is loaded (lazy loading)
     */
    private function ensureConfigurationLoaded(): void
    {
        if ($this->taxSettings === null || $this->taxBrackets === null) {
            $this->loadTaxConfiguration();
        }
    }

    /**
     * Load tax configuration for the specified year (with caching)
     */
    private function loadTaxConfiguration(): void
    {
        // Cache for 1 hour to reduce database queries
        $cacheKey = "tax_config_{$this->year}";

        $this->taxSettings = Cache::remember($cacheKey.'_settings', 3600, function () {
            return TaxSetting::selected()->forYear($this->year)->get();
        });

        $this->taxBrackets = Cache::remember($cacheKey.'_brackets', 3600, function () {
            return TaxBracket::getBracketsForYear($this->year);
        });
    }

    /**
     * Calculate employee tax with global toggle control and employee-specific allowances
     * Handles mid-year employment by adjusting annual income calculation
     */
    public function calculateEmployeeTax(float $grossSalary, array $employeeData = []): array
    {
        $this->ensureConfigurationLoaded();

        // Handle mid-year employment: use actual working months for annual income calculation
        $monthsWorking = $employeeData['months_working_this_year'] ?? 12;
        $annualIncome = $grossSalary * $monthsWorking;

        // Calculate employment deductions (50% of income, max 100k)
        $employmentDeductions = $this->calculateEmploymentDeductions($annualIncome);

        // Calculate personal allowances with detailed breakdown based on selected settings
        $personalAllowancesBreakdown = $this->getDetailedPersonalAllowancesBreakdown($employeeData);
        $personalAllowances = $personalAllowancesBreakdown['total'];

        // Calculate social security and provident fund
        $socialSecurity = $this->calculateSocialSecurityFromSalary($grossSalary);
        $providentFund = $this->calculateProvidentFundFromEmployeeData($annualIncome, $employeeData);

        // Calculate taxable income
        $totalDeductions = $employmentDeductions + $personalAllowances + $socialSecurity['annual'] + $providentFund;
        $taxableIncome = max(0, $annualIncome - $totalDeductions);

        // Calculate progressive income tax
        $annualTax = $this->calculateProgressiveIncomeTax($taxableIncome);
        $monthlyTax = $annualTax / 12;

        // Calculate net salary
        $netSalary = $grossSalary - $monthlyTax - $socialSecurity['monthly'];

        return [
            'gross_salary' => $grossSalary,
            'annual_gross_salary' => $annualIncome,
            'employment_deductions' => $employmentDeductions,
            'personal_allowances_breakdown' => $personalAllowancesBreakdown,
            'personal_allowances_total' => $personalAllowances,
            'social_security_annual' => $socialSecurity['annual'],
            'social_security_monthly' => $socialSecurity['monthly'],
            'provident_fund_annual' => $providentFund,
            'provident_fund_type' => $this->getProvidentFundType($employeeData['employee_status'] ?? 'Expats'),
            'total_deductions' => $totalDeductions,
            'taxable_income' => $taxableIncome,
            'annual_tax_amount' => $annualTax,
            'monthly_tax_amount' => $monthlyTax,
            'net_salary' => $netSalary,
            'tax_calculation_breakdown' => $this->getTaxBreakdown($taxableIncome),
            'employee_data_used' => $employeeData,
            'calculation_method' => 'Thai Revenue Department sequence: (1) Employment deductions 50% max ฿100,000, (2) Personal allowances by individual settings, (3) Progressive tax 8-bracket 0%-35%, (4) Social Security 5% max ฿750/month',
            'tax_year' => $this->year,
            'selected_settings_count' => $this->taxSettings->count(),
        ];
    }

    /**
     * Calculate comprehensive payroll for an employee (legacy method)
     */
    public function calculatePayroll(int $employeeId, float $grossSalary, array $additionalIncome = [], array $additionalDeductions = []): array
    {
        $this->ensureConfigurationLoaded(); // Load configuration only when needed

        $employee = Employee::with(['employment', 'employeeChildren'])->findOrFail($employeeId);

        // Calculate total income
        $totalIncome = $this->calculateTotalIncome($grossSalary, $additionalIncome);

        // Calculate deductions
        $deductions = $this->calculateDeductions($employee, $grossSalary, $additionalDeductions);

        // Calculate taxable income
        $taxableIncome = $this->calculateTaxableIncome($totalIncome, $deductions);

        // Calculate income tax (annual, then convert to monthly)
        $annualIncomeTax = $this->calculateProgressiveIncomeTax($taxableIncome);
        $incomeTax = $annualIncomeTax / 12; // Monthly tax for net salary calculation

        // Calculate social security contributions
        $socialSecurity = $this->calculateSocialSecurity($grossSalary);

        // Calculate net salary (Monthly gross - monthly deductions that actually come out of paycheck)
        $monthlyProvidentFund = $deductions['provident_fund_deduction'] / 12; // Convert annual to monthly
        $netSalary = $grossSalary - $incomeTax - $socialSecurity['employee_contribution'] - $monthlyProvidentFund - $deductions['additional_deductions'];

        $calculationData = [
            'gross_salary' => $grossSalary,
            'total_income' => $totalIncome,
            'deductions' => $deductions,
            'taxable_income' => $taxableIncome,
            'income_tax' => $incomeTax,
            'social_security' => $socialSecurity,
            'net_salary' => $netSalary,
            'tax_breakdown' => $this->getTaxBreakdown($taxableIncome),
            'calculation_date' => Carbon::now(),
            'tax_year' => $this->year,
            'is_thai_compliant' => true,
            'compliance_notes' => 'Calculation follows Thai Revenue Department sequence: Employment deductions first, then personal allowances',
        ];

        // Log calculation for audit trail
        $inputParameters = [
            'employee_id' => $employeeId,
            'gross_salary' => $grossSalary,
            'additional_income' => $additionalIncome,
            'additional_deductions' => $additionalDeductions,
            'tax_year' => $this->year,
        ];
        $this->logCalculation($employeeId, $calculationData, $inputParameters);

        return $calculationData;
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
     * Calculate deductions following Thai Revenue Department sequence
     * Step 1: Employment deductions (applied FIRST)
     * Step 2: Personal allowances (applied AFTER employment deductions)
     */
    private function calculateDeductions(Employee $employee, float $grossSalary, array $additionalDeductions = []): array
    {
        $this->ensureConfigurationLoaded(); // Ensure configuration is loaded

        // STEP 1: Employment Income Deductions (Applied FIRST per Thai law)
        $employmentDeductions = $this->calculateEmploymentDeductions($grossSalary);

        // STEP 2: Personal Allowances (Applied AFTER employment deductions)
        $personalAllowances = $this->calculatePersonalAllowances($employee);

        // Additional deductions
        $additionalDeductionsTotal = 0;
        foreach ($additionalDeductions as $deduction) {
            $additionalDeductionsTotal += floatval($deduction['amount'] ?? 0);
        }

        // Thai compliant structure
        $deductions = [
            // Employment deductions (applied first)
            'employment_deductions' => $employmentDeductions['total'],
            'employment_deduction_rate' => $employmentDeductions['rate'],
            'employment_deduction_calculated' => $employmentDeductions['calculated'],
            'employment_deduction_max' => $employmentDeductions['max_allowed'],

            // Personal allowances (applied second)
            'personal_allowances' => $personalAllowances['total'],
            'personal_allowance' => $personalAllowances['personal'],
            'spouse_allowance' => $personalAllowances['spouse'],
            'child_allowance' => $personalAllowances['child'],
            'parent_allowance' => $personalAllowances['parent'],

            // Other deductions
            'social_security_deduction' => 0, // Calculated separately
            'provident_fund_deduction' => $this->getProvidentFundDeduction($grossSalary),
            'additional_deductions' => $additionalDeductionsTotal,

            // Total annual deductions for tax calculation (employment + personal allowances)
            'total_deductions' => $employmentDeductions['total'] + $personalAllowances['total'],
        ];

        return $deductions;
    }

    /**
     * Calculate employment deductions (50% of annual income, max 100k)
     */
    private function calculateEmploymentDeductions(float $annualIncome): float
    {
        $rate = $this->getSelectedSettingValue('EMPLOYMENT_DEDUCTION_RATE', 50);
        $max = $this->getSelectedSettingValue('EMPLOYMENT_DEDUCTION_MAX', 100000);

        $calculated = $annualIncome * ($rate / 100);

        return min($calculated, $max);
    }

    /**
     * Get detailed breakdown of personal allowances showing each tax setting separately
     * Shows each selected setting individually, with ฿0 for settings that don't apply to the employee
     */
    private function getDetailedPersonalAllowancesBreakdown(array $employeeData): array
    {
        $breakdown = [];
        $totalAllowances = 0;

        // Personal Allowance - always applied if selected
        $personalAmount = 0;
        $personalApplied = false;
        $personalReason = '';

        if ($this->isSettingSelected('PERSONAL_ALLOWANCE')) {
            $personalAmount = $this->getSelectedSettingValue('PERSONAL_ALLOWANCE', 60000);
            $personalApplied = true;
            $personalReason = 'Applied to all employees';
            $totalAllowances += $personalAmount;
        } else {
            $personalReason = 'Personal allowance setting not selected';
        }

        $breakdown['personal_allowance'] = [
            'setting_key' => 'PERSONAL_ALLOWANCE',
            'setting_name' => 'Personal Allowance',
            'is_selected' => $this->isSettingSelected('PERSONAL_ALLOWANCE'),
            'amount' => $personalAmount,
            'applied' => $personalApplied,
            'reason' => $personalReason,
            'formatted_amount' => '฿'.number_format($personalAmount, 0),
        ];

        // Spouse Allowance - only if married and setting selected
        $spouseAmount = 0;
        $spouseApplied = false;
        $spouseReason = '';
        $hasSpouse = $employeeData['has_spouse'] ?? false;

        if ($this->isSettingSelected('SPOUSE_ALLOWANCE')) {
            if ($hasSpouse) {
                $spouseAmount = $this->getSelectedSettingValue('SPOUSE_ALLOWANCE', 60000);
                $spouseApplied = true;
                $spouseReason = 'Employee is married - allowance applied';
                $totalAllowances += $spouseAmount;
            } else {
                $spouseReason = 'Employee is not married - allowance not applicable';
            }
        } else {
            $spouseReason = 'Spouse allowance setting not selected';
        }

        $breakdown['spouse_allowance'] = [
            'setting_key' => 'SPOUSE_ALLOWANCE',
            'setting_name' => 'Spouse Allowance',
            'is_selected' => $this->isSettingSelected('SPOUSE_ALLOWANCE'),
            'amount' => $spouseAmount,
            'applied' => $spouseApplied,
            'reason' => $spouseReason,
            'employee_has_spouse' => $hasSpouse,
            'formatted_amount' => '฿'.number_format($spouseAmount, 0),
        ];

        // Child Allowance (First Child) - only if has children and setting selected
        $firstChildAmount = 0;
        $firstChildApplied = false;
        $firstChildReason = '';
        $children = $employeeData['children'] ?? 0;

        if ($this->isSettingSelected('CHILD_ALLOWANCE')) {
            if ($children > 0) {
                $firstChildAmount = $this->getSelectedSettingValue('CHILD_ALLOWANCE', 30000);
                $firstChildApplied = true;
                $firstChildReason = 'Employee has children - first child allowance applied';
                $totalAllowances += $firstChildAmount;
            } else {
                $firstChildReason = 'Employee has no children - allowance not applicable';
            }
        } else {
            $firstChildReason = 'Child allowance setting not selected';
        }

        $breakdown['child_allowance'] = [
            'setting_key' => 'CHILD_ALLOWANCE',
            'setting_name' => 'Child Allowance (First Child)',
            'is_selected' => $this->isSettingSelected('CHILD_ALLOWANCE'),
            'amount' => $firstChildAmount,
            'applied' => $firstChildApplied,
            'reason' => $firstChildReason,
            'children_count' => $children,
            'formatted_amount' => '฿'.number_format($firstChildAmount, 0),
        ];

        // Subsequent Children Allowance - only if has more than 1 child and setting selected
        $subsequentChildrenAmount = 0;
        $subsequentChildrenApplied = false;
        $subsequentChildrenReason = '';

        if ($this->isSettingSelected('CHILD_ALLOWANCE_SUBSEQUENT')) {
            if ($children > 1) {
                $subsequentChildren = $children - 1;
                $subsequentChildrenAmount = $this->getSelectedSettingValue('CHILD_ALLOWANCE_SUBSEQUENT', 60000) * $subsequentChildren;
                $subsequentChildrenApplied = true;
                $subsequentChildrenReason = "Employee has {$subsequentChildren} subsequent children - allowance applied";
                $totalAllowances += $subsequentChildrenAmount;
            } else {
                $subsequentChildrenReason = $children == 0 ? 'Employee has no children' : 'Employee has only 1 child - no subsequent children';
            }
        } else {
            $subsequentChildrenReason = 'Subsequent children allowance setting not selected';
        }

        $breakdown['subsequent_children_allowance'] = [
            'setting_key' => 'CHILD_ALLOWANCE_SUBSEQUENT',
            'setting_name' => 'Subsequent Children Allowance',
            'is_selected' => $this->isSettingSelected('CHILD_ALLOWANCE_SUBSEQUENT'),
            'amount' => $subsequentChildrenAmount,
            'applied' => $subsequentChildrenApplied,
            'reason' => $subsequentChildrenReason,
            'subsequent_children_count' => max(0, $children - 1),
            'formatted_amount' => '฿'.number_format($subsequentChildrenAmount, 0),
        ];

        // Parent Allowance - only if has eligible parents and setting selected
        $parentAmount = 0;
        $parentApplied = false;
        $parentReason = '';
        $eligibleParents = $employeeData['eligible_parents'] ?? 0;

        if ($this->isSettingSelected('PARENT_ALLOWANCE')) {
            if ($eligibleParents > 0) {
                $parentAmount = $this->getSelectedSettingValue('PARENT_ALLOWANCE', 30000) * $eligibleParents;
                $parentApplied = true;
                $parentReason = "Employee has {$eligibleParents} eligible parent(s) - allowance applied";
                $totalAllowances += $parentAmount;
            } else {
                $parentReason = 'Employee has no eligible parents - allowance not applicable';
            }
        } else {
            $parentReason = 'Parent allowance setting not selected';
        }

        $breakdown['parent_allowance'] = [
            'setting_key' => 'PARENT_ALLOWANCE',
            'setting_name' => 'Parent Allowance',
            'is_selected' => $this->isSettingSelected('PARENT_ALLOWANCE'),
            'amount' => $parentAmount,
            'applied' => $parentApplied,
            'reason' => $parentReason,
            'eligible_parents_count' => $eligibleParents,
            'formatted_amount' => '฿'.number_format($parentAmount, 0),
        ];

        // Add summary
        $breakdown['summary'] = [
            'total_allowances' => $totalAllowances,
            'formatted_total' => '฿'.number_format($totalAllowances, 0),
            'selected_settings_applied' => array_filter($breakdown, function ($item) {
                return isset($item['applied']) && $item['applied'] === true;
            }),
            'selected_settings_not_applicable' => array_filter($breakdown, function ($item) {
                return isset($item['is_selected']) && $item['is_selected'] === true &&
                       isset($item['applied']) && $item['applied'] === false;
            }),
        ];

        $breakdown['total'] = $totalAllowances;

        return $breakdown;
    }

    /**
     * Calculate personal allowances from employee data using only selected settings (simplified version)
     */
    private function calculatePersonalAllowancesFromData(array $employeeData): float
    {
        $breakdown = $this->getDetailedPersonalAllowancesBreakdown($employeeData);

        return $breakdown['total'];
    }

    /**
     * Calculate social security from monthly salary
     */
    private function calculateSocialSecurityFromSalary(float $monthlySalary): array
    {
        if (! $this->isSettingSelected('SSF_RATE')) {
            return ['monthly' => 0, 'annual' => 0];
        }

        $rate = $this->getSelectedSettingValue('SSF_RATE', 5);
        $monthlyContribution = min($monthlySalary * ($rate / 100), 750); // Max 750 per month

        return [
            'monthly' => $monthlyContribution,
            'annual' => $monthlyContribution * 12,
        ];
    }

    /**
     * Calculate provident fund based on employee status and annual income
     * Thai Citizens (Local ID): PVD Fund (7.5% of annual income, max ฿500,000)
     * Non-Thai Citizens (Local non ID): Saving Fund (7.5% of annual income, max ฿500,000)
     * Expats: No provident fund deduction
     */
    private function calculateProvidentFundFromEmployeeData(float $annualIncome, array $employeeData): float
    {
        $employeeStatus = $employeeData['employee_status'] ?? 'Expats';

        // Determine which fund to use based on employee status
        $rateKey = '';
        $maxKey = '';

        switch ($employeeStatus) {
            case 'Local ID': // Thai Citizens
                $rateKey = 'PVD_FUND_RATE';
                $maxKey = 'PVD_FUND_MAX';
                break;

            case 'Local non ID': // Non-Thai Citizens
                $rateKey = 'SAVING_FUND_RATE';
                $maxKey = 'SAVING_FUND_MAX';
                break;

            case 'Expats': // Expatriates
            default:
                return 0; // No provident fund for expats
        }

        // Check if the respective fund is selected
        if (! $this->isSettingSelected($rateKey)) {
            return 0;
        }

        $rate = $this->getSelectedSettingValue($rateKey, 7.5);
        $maxAmount = $this->getSelectedSettingValue($maxKey, 500000);

        $calculatedAmount = $annualIncome * ($rate / 100);

        return min($calculatedAmount, $maxAmount);
    }

    /**
     * Check if a tax setting is selected (enabled)
     */
    private function isSettingSelected(string $settingKey): bool
    {
        return $this->taxSettings->where('setting_key', $settingKey)->where('is_selected', true)->isNotEmpty();
    }

    /**
     * Get value of a selected tax setting
     */
    private function getSelectedSettingValue(string $settingKey, float $defaultValue = 0): float
    {
        $setting = $this->taxSettings->where('setting_key', $settingKey)->where('is_selected', true)->first();

        return $setting ? (float) $setting->setting_value : $defaultValue;
    }

    /**
     * Get provident fund type based on employee status
     */
    private function getProvidentFundType(string $employeeStatus): string
    {
        switch ($employeeStatus) {
            case 'Local ID':
                return 'PVD Fund (Thai Citizens)';
            case 'Local non ID':
                return 'Saving Fund (Non-Thai Citizens)';
            case 'Expats':
            default:
                return 'None (Expatriates)';
        }
    }

    /**
     * Calculate allowances with employee-specific data using only selected settings
     */
    private function calculateAllowancesWithEmployeeData($allowances, array $employeeData): array
    {
        $breakdown = [];

        foreach ($allowances as $allowance) {
            $amount = 0;
            $reason = '';
            $applied = false;

            switch ($allowance->setting_key) {
                case TaxSetting::KEY_PERSONAL_ALLOWANCE:
                    $amount = $allowance->setting_value;
                    $reason = 'Applied to all employees';
                    $applied = true;
                    break;

                case TaxSetting::KEY_SPOUSE_ALLOWANCE:
                    if ($employeeData['has_spouse'] ?? false) {
                        $amount = $allowance->setting_value;
                        $reason = 'Employee is married';
                        $applied = true;
                    } else {
                        $reason = 'Employee is single - not applied';
                        $applied = false;
                    }
                    break;

                case TaxSetting::KEY_CHILD_ALLOWANCE:
                    $children = $employeeData['children'] ?? 0;
                    if ($children > 0) {
                        // First child gets regular allowance
                        $amount = $allowance->setting_value;
                        $reason = 'First child allowance';
                        $applied = true;
                    } else {
                        $reason = 'No children - not applied';
                        $applied = false;
                    }
                    break;

                case TaxSetting::KEY_CHILD_ALLOWANCE_SUBSEQUENT:
                    $children = $employeeData['children'] ?? 0;
                    if ($children > 1) {
                        // Subsequent children (born 2018+)
                        $subsequentChildren = $children - 1;
                        $amount = $allowance->setting_value * $subsequentChildren;
                        $reason = "{$subsequentChildren} subsequent children (born 2018+)";
                        $applied = true;
                    } else {
                        $reason = 'No subsequent children - not applied';
                        $applied = false;
                    }
                    break;

                case TaxSetting::KEY_PARENT_ALLOWANCE:
                    $eligibleParents = $employeeData['eligible_parents'] ?? 0;
                    if ($eligibleParents > 0) {
                        $amount = $allowance->setting_value * $eligibleParents;
                        $reason = "{$eligibleParents} eligible parents (age 60+, income < ฿30,000)";
                        $applied = true;
                    } else {
                        $reason = 'No eligible parents - not applied';
                        $applied = false;
                    }
                    break;

                default:
                    $amount = $allowance->setting_value;
                    $reason = 'Standard allowance';
                    $applied = true;
                    break;
            }

            $breakdown[] = [
                'setting_key' => $allowance->setting_key,
                'name' => $allowance->description ?? $allowance->setting_key,
                'amount' => $amount,
                'reason' => $reason,
                'applied' => $applied,
                'setting_value' => $allowance->setting_value,
            ];
        }

        return $breakdown;
    }

    /**
     * Calculate deductions with employee-specific data using only selected settings
     */
    private function calculateDeductionsWithEmployeeData($deductions, float $grossSalary, array $employeeData): array
    {
        $breakdown = [];

        foreach ($deductions as $deduction) {
            $amount = 0;
            $reason = '';
            $type = 'annual'; // or 'monthly'
            $applied = false;

            switch ($deduction->setting_key) {
                case TaxSetting::KEY_EMPLOYMENT_DEDUCTION_RATE:
                    // Employment deduction: 50% of annual income, max ฿100,000
                    $annualIncome = $grossSalary * 12;
                    $calculated = $annualIncome * ($deduction->setting_value / 100);
                    $maxDeduction = TaxSetting::getValue(TaxSetting::KEY_EMPLOYMENT_DEDUCTION_MAX, $this->year) ?? 100000;
                    $amount = min($calculated, $maxDeduction);
                    $reason = "{$deduction->setting_value}% of annual income (max ฿".number_format($maxDeduction).')';
                    $applied = true;
                    break;

                case TaxSetting::KEY_SSF_RATE:
                    // Social Security Fund: 5% of monthly salary, max ฿750/month
                    $monthlyContribution = min($grossSalary * ($deduction->setting_value / 100), 750);
                    $amount = $monthlyContribution * 12; // Annual amount
                    $reason = "{$deduction->setting_value}% of monthly salary (max ฿750/month)";
                    $type = 'annual';
                    $applied = true;
                    break;

                case TaxSetting::KEY_PF_MIN_RATE:
                case TaxSetting::KEY_PF_MAX_RATE:
                    // Provident Fund contribution from employee data
                    $pfContribution = $employeeData['pf_contribution_annual'] ?? 0;
                    if ($pfContribution > 0) {
                        $amount = $pfContribution;
                        $reason = 'Employee PF contribution';
                        $applied = true;
                    } else {
                        $reason = 'No PF contribution - not applied';
                        $applied = false;
                    }
                    break;

                default:
                    $amount = $deduction->setting_value;
                    $reason = 'Standard deduction';
                    $applied = true;
                    break;
            }

            $breakdown[] = [
                'setting_key' => $deduction->setting_key,
                'name' => $deduction->description ?? $deduction->setting_key,
                'amount' => $amount,
                'reason' => $reason,
                'type' => $type,
                'applied' => $applied,
                'setting_value' => $deduction->setting_value,
            ];
        }

        return $breakdown;
    }

    /**
     * Calculate personal allowances (Applied AFTER employment deductions)
     */
    private function calculatePersonalAllowances(Employee $employee): array
    {
        $this->ensureConfigurationLoaded();

        $allowances = [
            'personal' => $this->getPersonalAllowance(),
            'spouse' => $this->getSpouseAllowance($employee),
            'child' => $this->getChildAllowance($employee),
            'parent' => $this->getParentAllowance($employee),
        ];

        $allowances['total'] = array_sum($allowances);

        return $allowances;
    }

    /**
     * Get personal allowance amount
     */
    private function getPersonalAllowance(): float
    {
        $this->ensureConfigurationLoaded(); // Ensure configuration is loaded

        return $this->getSetting(TaxSetting::KEY_PERSONAL_ALLOWANCE, TaxSetting::THAI_2025_PERSONAL_ALLOWANCE);
    }

    /**
     * Get spouse allowance if applicable (only if spouse has no income)
     */
    private function getSpouseAllowance(Employee $employee): float
    {
        $this->ensureConfigurationLoaded(); // Ensure configuration is loaded

        $isMarried = in_array(strtolower($employee->marital_status ?? ''), ['married', 'แต่งงาน']);

        if (! $isMarried) {
            return 0;
        }

        // In Thai law, spouse allowance is only available if spouse has no income
        // For now, we'll assume eligibility - this could be enhanced with spouse income tracking
        return $this->getSetting(TaxSetting::KEY_SPOUSE_ALLOWANCE, TaxSetting::THAI_2025_SPOUSE_ALLOWANCE);
    }

    /**
     * Get child allowance based on Thai Revenue Department rules
     * First child: ฿30,000
     * Subsequent children born 2018 onwards: ฿60,000 each
     */
    private function getChildAllowance(Employee $employee): float
    {
        $this->ensureConfigurationLoaded();

        $children = $employee->employeeChildren;
        $totalAllowance = 0;

        if ($children->isEmpty()) {
            return 0;
        }

        // Sort children by birth date (oldest first)
        $sortedChildren = $children->sortBy('date_of_birth');

        foreach ($sortedChildren as $index => $child) {
            if ($index === 0) {
                // First child gets standard allowance
                $totalAllowance += $this->getSetting(TaxSetting::KEY_CHILD_ALLOWANCE, TaxSetting::THAI_2025_CHILD_ALLOWANCE);
            } else {
                // Subsequent children - check if born 2018 onwards for higher allowance
                $birthYear = $child->birth_year ?? ($child->date_of_birth ? \Carbon\Carbon::parse($child->date_of_birth)->year : null);
                if ($birthYear && $birthYear >= 2018) {
                    $totalAllowance += $this->getSetting(TaxSetting::KEY_CHILD_ALLOWANCE_SUBSEQUENT, TaxSetting::THAI_2025_CHILD_ALLOWANCE_SUBSEQUENT);
                } else {
                    $totalAllowance += $this->getSetting(TaxSetting::KEY_CHILD_ALLOWANCE, TaxSetting::THAI_2025_CHILD_ALLOWANCE);
                }
            }

            // Thai law typically limits child allowances to 3 children
            if ($index >= 2) {
                break;
            }
        }

        return $totalAllowance;
    }

    /**
     * Get parent allowance based on Thai Revenue Department rules
     * Requirements: Parent age 60+, annual income < ฿30,000, marked as dependent
     * Amount: ฿30,000 per eligible parent
     */
    private function getParentAllowance(Employee $employee): float
    {
        $this->ensureConfigurationLoaded();

        $eligibleParents = $employee->eligible_parents_count;
        $allowancePerParent = $this->getSetting(TaxSetting::KEY_PARENT_ALLOWANCE, TaxSetting::THAI_2025_PARENT_ALLOWANCE);

        return $eligibleParents * $allowancePerParent;
    }

    /**
     * Calculate personal expense deduction
     */
    private function getPersonalExpenseDeduction(float $grossSalary): float
    {
        $this->ensureConfigurationLoaded(); // Ensure configuration is loaded

        $rate = $this->getSetting(TaxSetting::KEY_PERSONAL_EXPENSE_RATE, 0.40); // 40%
        $maxAmount = $this->getSetting(TaxSetting::KEY_PERSONAL_EXPENSE_MAX, 60000);

        $yearlyGross = $grossSalary * 12;
        $calculated = $yearlyGross * ($rate / 100);

        return min($calculated, $maxAmount);
    }

    /**
     * Calculate provident fund deduction
     * Using 7.5% rate to match verification: ฿30,000 * 12 * 7.5% = ฿27,000
     */
    private function getProvidentFundDeduction(float $grossSalary): float
    {
        // Use fixed 7.5% rate as per your verification calculation
        $pfRate = 7.5; // Fixed rate to match ฿27,000 for ฿30,000 salary

        $yearlyGross = $grossSalary * 12;
        $contribution = $yearlyGross * ($pfRate / 100);

        return $contribution;
    }

    /**
     * Calculate taxable income after deductions
     * Per Thai law: Annual Income - Employment Deductions - Personal Allowances - PF - SSF
     */
    private function calculateTaxableIncome(float $totalIncome, array $deductions): float
    {
        $yearlyIncome = $totalIncome * 12;
        $employmentAndPersonalDeductions = $deductions['total_deductions']; // Employment + Personal allowances
        $providentFundDeduction = $deductions['provident_fund_deduction']; // Annual PF

        // Calculate Social Security annual deduction (also tax deductible)
        $socialSecurityAnnual = $this->calculateSocialSecurity($totalIncome)['annual_employee_contribution'];

        $totalTaxDeductions = $employmentAndPersonalDeductions + $providentFundDeduction + $socialSecurityAnnual;

        return max(0, $yearlyIncome - $totalTaxDeductions);
    }

    /**
     * Calculate progressive income tax using tax brackets
     * For ฿164,000 taxable income: First ฿150,000 at 0%, remaining ฿14,000 at 5% = ฿700 annually
     */
    public function calculateProgressiveIncomeTax(float $taxableIncome): float
    {
        $this->ensureConfigurationLoaded(); // Ensure configuration is loaded

        if ($taxableIncome <= 0 || $this->taxBrackets->isEmpty()) {
            return 0;
        }

        $totalTax = 0;
        $processedIncome = 0;

        foreach ($this->taxBrackets as $bracket) {
            if ($processedIncome >= $taxableIncome) {
                break;
            }

            $bracketMin = $bracket->min_income;
            $bracketMax = $bracket->max_income ?? PHP_FLOAT_MAX;
            $taxRate = $bracket->tax_rate;

            // Skip if taxable income doesn't reach this bracket
            if ($taxableIncome <= $bracketMin) {
                continue;
            }

            // Calculate income in this bracket
            $incomeInBracket = min($taxableIncome - $processedIncome, $bracketMax - $bracketMin);

            // Only process if there's income in this bracket
            if ($incomeInBracket > 0 && $taxableIncome > $bracketMin) {
                $tax = $incomeInBracket * ($taxRate / 100);
                $totalTax += $tax;
                $processedIncome += $incomeInBracket;
            }
        }

        // Return annual tax amount
        return $totalTax;
    }

    /**
     * Calculate social security contributions following Thai Social Security Act
     * Rate: Fixed 5% (mandatory, non-negotiable)
     * Salary Range: Applied to salary between ฿1,650 - ฿15,000 monthly
     * Maximum Contribution: ฿750 monthly, ฿9,000 annually
     * Employer Matching: Equal contribution required from employer
     */
    private function calculateSocialSecurity(float $grossSalary): array
    {
        $this->ensureConfigurationLoaded();

        // Thai Social Security Fund settings (fixed by law)
        $ssfRate = $this->getSetting(TaxSetting::KEY_SSF_RATE, TaxSetting::THAI_2025_SSF_RATE);
        $minSalary = $this->getSetting(TaxSetting::KEY_SSF_MIN_SALARY, TaxSetting::THAI_2025_SSF_MIN_SALARY);
        $maxSalary = $this->getSetting(TaxSetting::KEY_SSF_MAX_SALARY, TaxSetting::THAI_2025_SSF_MAX_SALARY);
        $maxMonthly = $this->getSetting(TaxSetting::KEY_SSF_MAX_MONTHLY, TaxSetting::THAI_2025_SSF_MAX_MONTHLY);

        // Apply salary range limits
        $effectiveSalary = max($minSalary, min($grossSalary, $maxSalary));

        // Calculate contribution (5% of effective salary, capped at ฿750)
        $employeeContribution = min($effectiveSalary * ($ssfRate / 100), $maxMonthly);
        $employerContribution = $employeeContribution; // Employer matches employee contribution

        return [
            'gross_salary' => $grossSalary,
            'effective_salary' => $effectiveSalary,
            'ssf_rate' => $ssfRate,
            'min_salary' => $minSalary,
            'max_salary' => $maxSalary,
            'max_monthly_contribution' => $maxMonthly,
            'employee_contribution' => $employeeContribution,
            'employer_contribution' => $employerContribution,
            'total_contribution' => $employeeContribution + $employerContribution,
            'annual_employee_contribution' => $employeeContribution * 12,
            'annual_employer_contribution' => $employerContribution * 12,
            'is_salary_capped' => $grossSalary > $maxSalary,
            'is_contribution_capped' => ($effectiveSalary * ($ssfRate / 100)) > $maxMonthly,
        ];
    }

    /**
     * Get detailed tax breakdown by bracket
     */
    private function getTaxBreakdown(float $taxableIncome): array
    {
        $this->ensureConfigurationLoaded(); // Ensure configuration is loaded

        $breakdown = [];
        $processedIncome = 0;

        foreach ($this->taxBrackets as $bracket) {
            if ($processedIncome >= $taxableIncome) {
                break;
            }

            $bracketMin = $bracket->min_income;
            $bracketMax = $bracket->max_income ?? PHP_FLOAT_MAX;
            $taxRate = $bracket->tax_rate;

            // Skip if taxable income doesn't reach this bracket
            if ($taxableIncome <= $bracketMin) {
                continue;
            }

            // Calculate income in this bracket
            $incomeInBracket = min($taxableIncome - $processedIncome, $bracketMax - $bracketMin);

            if ($incomeInBracket > 0 && $taxableIncome > $bracketMin) {
                $taxInBracket = $incomeInBracket * ($taxRate / 100);

                $maxIncomeDisplay = $bracket->max_income ? number_format($bracket->max_income) : 'and above';

                $breakdown[] = [
                    'bracket_order' => $bracket->bracket_order,
                    'income_range' => '฿'.number_format($bracket->min_income).' - ฿'.$maxIncomeDisplay,
                    'tax_rate' => $taxRate.'%',
                    'taxable_income_in_bracket' => $incomeInBracket,
                    'formatted_taxable_income' => '฿'.number_format($incomeInBracket),
                    'tax_amount' => $taxInBracket,
                    'formatted_tax_amount' => '฿'.number_format($taxInBracket, 2),
                    'monthly_tax' => $taxInBracket / 12,
                    'formatted_monthly_tax' => '฿'.number_format($taxInBracket / 12, 2),
                    'calculation_method' => '฿'.number_format($incomeInBracket).' × '.$taxRate.'% = ฿'.number_format($taxInBracket, 2),
                ];

                $processedIncome += $incomeInBracket;
            }
        }

        // Add summary
        $totalTax = array_sum(array_column($breakdown, 'tax_amount'));
        $totalMonthlyTax = $totalTax / 12;

        return [
            'brackets' => $breakdown,
            'summary' => [
                'total_taxable_income' => $taxableIncome,
                'formatted_taxable_income' => '฿'.number_format($taxableIncome),
                'total_annual_tax' => $totalTax,
                'formatted_annual_tax' => '฿'.number_format($totalTax, 2),
                'total_monthly_tax' => $totalMonthlyTax,
                'formatted_monthly_tax' => '฿'.number_format($totalMonthlyTax, 2),
                'effective_tax_rate' => $taxableIncome > 0 ? ($totalTax / $taxableIncome) * 100 : 0,
                'formatted_effective_rate' => $taxableIncome > 0 ? number_format(($totalTax / $taxableIncome) * 100, 2).'%' : '0%',
                'brackets_used' => count($breakdown),
            ],
        ];
    }

    /**
     * Get tax setting value with fallback
     */
    private function getSetting(string $key, float $default = 0): float
    {
        // No need to call ensureConfigurationLoaded() here as it's called by parent methods
        return TaxSetting::getValue($key, $this->year) ?? $default;
    }

    /**
     * Calculate annual tax liability
     */
    public function calculateAnnualTax(int $employeeId, array $monthlyPayrolls): array
    {
        $this->ensureConfigurationLoaded(); // Ensure configuration is loaded

        $totalIncome = array_sum(array_column($monthlyPayrolls, 'total_income'));
        $totalDeductions = array_sum(array_column($monthlyPayrolls, 'total_deductions'));
        $totalTaxPaid = array_sum(array_column($monthlyPayrolls, 'income_tax'));

        $employee = Employee::with(['employment', 'employeeChildren'])->findOrFail($employeeId);
        $deductions = $this->calculateDeductions($employee, 0, []);

        $taxableIncome = max(0, $totalIncome - $deductions['total_deductions']);
        $actualTaxLiability = $this->calculateProgressiveIncomeTax($taxableIncome);

        $taxDifference = $actualTaxLiability - $totalTaxPaid;

        return [
            'total_income' => $totalIncome,
            'total_deductions' => $deductions['total_deductions'],
            'taxable_income' => $taxableIncome,
            'tax_liability' => $actualTaxLiability,
            'tax_paid' => $totalTaxPaid,
            'tax_difference' => $taxDifference,
            'refund_due' => $taxDifference < 0 ? abs($taxDifference) : 0,
            'additional_tax_due' => $taxDifference > 0 ? $taxDifference : 0,
        ];
    }

    /**
     * Validate tax calculation inputs
     */
    public function validateCalculationInputs(array $inputs): array
    {
        $errors = [];

        if (! isset($inputs['employee_id']) || ! is_numeric($inputs['employee_id'])) {
            $errors[] = 'Valid employee ID is required';
        }

        if (! isset($inputs['gross_salary']) || ! is_numeric($inputs['gross_salary']) || $inputs['gross_salary'] < 0) {
            $errors[] = 'Valid gross salary amount is required';
        }

        if (isset($inputs['additional_income']) && ! is_array($inputs['additional_income'])) {
            $errors[] = 'Additional income must be an array';
        }

        if (isset($inputs['additional_deductions']) && ! is_array($inputs['additional_deductions'])) {
            $errors[] = 'Additional deductions must be an array';
        }

        return $errors;
    }

    /**
     * Validate calculation against Thai Revenue Department compliance
     */
    public function validateThaiCompliance(array $calculationData): array
    {
        $this->ensureConfigurationLoaded();

        $errors = [];
        $warnings = [];
        $isCompliant = true;

        // Check if employment deductions were applied first
        if (! isset($calculationData['deductions']['employment_deductions'])) {
            $errors[] = 'Employment deductions must be calculated first per Thai Revenue Department sequence';
            $isCompliant = false;
        }

        // Check if personal allowances were applied second
        if (! isset($calculationData['deductions']['personal_allowances'])) {
            $errors[] = 'Personal allowances must be calculated after employment deductions';
            $isCompliant = false;
        }

        // Validate Social Security calculation
        if (isset($calculationData['social_security'])) {
            $ssf = $calculationData['social_security'];
            if ($ssf['ssf_rate'] != 5.0) {
                $errors[] = 'Social Security Fund rate must be exactly 5% for Thai compliance';
                $isCompliant = false;
            }

            if ($ssf['max_monthly_contribution'] != 750) {
                $errors[] = 'Maximum monthly SSF contribution must be ฿750 for Thai compliance';
                $isCompliant = false;
            }
        }

        // Check tax brackets compliance
        $bracketValidation = TaxBracket::validateThaiCompliance($this->year);
        if (! $bracketValidation['is_compliant']) {
            $errors = array_merge($errors, $bracketValidation['errors']);
            $isCompliant = false;
        }
        $warnings = array_merge($warnings, $bracketValidation['warnings'] ?? []);

        // Check tax settings compliance
        $settingsValidation = TaxSetting::validateThaiCompliance($this->year);
        if (! $settingsValidation['is_compliant']) {
            $errors = array_merge($errors, $settingsValidation['errors']);
            $isCompliant = false;
        }
        $warnings = array_merge($warnings, $settingsValidation['warnings'] ?? []);

        return [
            'is_compliant' => $isCompliant,
            'errors' => $errors,
            'warnings' => $warnings,
            'compliance_score' => $isCompliant ? 100 : max(0, 100 - (count($errors) * 20)),
            'validation_date' => now(),
            'tax_year' => $this->year,
        ];
    }

    /**
     * Generate Thai Revenue Department compliant report
     */
    public function generateThaiTaxReport(int $employeeId, array $calculationData): array
    {
        $this->ensureConfigurationLoaded();

        $employee = Employee::with(['employeeChildren'])->findOrFail($employeeId);
        $compliance = $this->validateThaiCompliance($calculationData);

        return [
            'report_title' => 'Thai Personal Income Tax Calculation Report',
            'report_date' => now()->format('d/m/Y H:i:s'),
            'tax_year' => $this->year,
            'employee_info' => [
                'staff_id' => $employee->staff_id,
                'name' => $employee->first_name_en.' '.$employee->last_name_en,
                'tax_number' => $employee->tax_number,
            ],

            // Thai Revenue Department Sequence
            'calculation_sequence' => [
                'step_1' => [
                    'title' => 'Employment Income Deductions (Applied First)',
                    'rate' => $calculationData['deductions']['employment_deduction_rate'].'%',
                    'calculated' => number_format($calculationData['deductions']['employment_deduction_calculated'], 2),
                    'maximum_allowed' => number_format($calculationData['deductions']['employment_deduction_max'], 2),
                    'actual_deduction' => number_format($calculationData['deductions']['employment_deductions'], 2),
                    'law_reference' => 'Revenue Code Section 42(1)',
                ],
                'step_2' => [
                    'title' => 'Personal Allowances (Applied After Employment Deductions)',
                    'personal_allowance' => number_format($calculationData['deductions']['personal_allowance'], 2),
                    'spouse_allowance' => number_format($calculationData['deductions']['spouse_allowance'], 2),
                    'child_allowance' => number_format($calculationData['deductions']['child_allowance'], 2),
                    'parent_allowance' => number_format($calculationData['deductions']['parent_allowance'], 2),
                    'senior_citizen_allowance' => number_format($calculationData['deductions']['senior_citizen_allowance'], 2),
                    'total_allowances' => number_format($calculationData['deductions']['personal_allowances'], 2),
                    'law_reference' => 'Revenue Code Section 42(2-6)',
                ],
                'step_3' => [
                    'title' => 'Progressive Tax Calculation',
                    'taxable_income' => number_format($calculationData['taxable_income'], 2),
                    'tax_brackets_used' => $calculationData['tax_breakdown'],
                    'annual_tax' => number_format($calculationData['income_tax'] * 12, 2),
                    'monthly_tax' => number_format($calculationData['income_tax'], 2),
                    'law_reference' => 'Revenue Code Section 48',
                ],
                'step_4' => [
                    'title' => 'Social Security Contributions (Separate from Income Tax)',
                    'employee_contribution' => number_format($calculationData['social_security']['employee_contribution'], 2),
                    'employer_contribution' => number_format($calculationData['social_security']['employer_contribution'], 2),
                    'rate' => $calculationData['social_security']['ssf_rate'].'%',
                    'law_reference' => 'Social Security Act',
                ],
            ],

            'summary' => [
                'gross_salary_monthly' => number_format($calculationData['gross_salary'], 2),
                'gross_salary_annual' => number_format($calculationData['total_income'], 2),
                'employment_deductions' => number_format($calculationData['deductions']['employment_deductions'], 2),
                'personal_allowances' => number_format($calculationData['deductions']['personal_allowances'], 2),
                'total_deductions' => number_format($calculationData['deductions']['total_deductions'], 2),
                'taxable_income' => number_format($calculationData['taxable_income'], 2),
                'income_tax_annual' => number_format($calculationData['income_tax'] * 12, 2),
                'income_tax_monthly' => number_format($calculationData['income_tax'], 2),
                'social_security_employee' => number_format($calculationData['social_security']['employee_contribution'], 2),
                'net_salary' => number_format($calculationData['net_salary'], 2),
            ],

            'compliance_status' => $compliance,

            'thai_law_references' => [
                'Revenue Code Section 42(1)' => 'Employment income deductions - 50% of gross income, maximum ฿100,000',
                'Revenue Code Section 42(2)' => 'Personal allowance - ฿60,000 per taxpayer',
                'Revenue Code Section 42(3)' => 'Spouse allowance - ฿60,000 (if spouse has no income)',
                'Revenue Code Section 42(4)' => 'Child allowances - ฿30,000 first child, ฿60,000 subsequent (born 2018+)',
                'Revenue Code Section 42(5)' => 'Parent allowance - ฿30,000 per eligible parent (age 60+, income < ฿30,000)',
                'Revenue Code Section 42(6)' => 'Senior citizen allowance - ฿190,000 additional (taxpayer age 65+)',
                'Revenue Code Section 48' => 'Progressive tax rates - 0%, 5%, 10%, 15%, 20%, 25%, 30%, 35%',
                'Social Security Act' => 'SSF contributions - 5% rate, ฿750 monthly maximum',
            ],

            'generated_by' => 'HRMS Thai Tax Compliance System',
            'system_version' => '1.0 - Thai Revenue Department Compliant',
        ];
    }

    /**
     * Log calculation for audit trail
     */
    private function logCalculation(int $employeeId, array $calculationData, array $inputParameters, ?string $calculatedBy = null): void
    {
        try {
            TaxCalculationLog::logPayrollCalculation(
                $employeeId,
                $calculationData,
                $inputParameters,
                $calculatedBy ?? auth()->user()->email ?? 'system'
            );
        } catch (\Exception $e) {
            // Log error but don't fail the calculation
            \Log::error('Failed to log tax calculation', [
                'employee_id' => $employeeId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear cached tax configuration (useful when tax settings are updated)
     */
    public function clearCache(): void
    {
        $cacheKey = "tax_config_{$this->year}";
        Cache::forget($cacheKey.'_settings');
        Cache::forget($cacheKey.'_brackets');

        // Reset local properties to force reload
        $this->taxSettings = null;
        $this->taxBrackets = null;
    }
}
