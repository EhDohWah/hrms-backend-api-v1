<?php

namespace App\Services;

use App\Models\BenefitSetting;
use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
use App\Models\Employment;
use App\Models\InterOrganizationAdvance;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollService
{
    protected TaxCalculationService $taxService;

    public function __construct(?int $taxYear = null)
    {
        $this->taxService = new TaxCalculationService($taxYear ?? date('Y'));
    }

    /**
     * Process complete payroll for an employee including inter-organization advances
     */
    public function processEmployeePayroll(Employee $employee, Carbon $payPeriodDate, bool $savePayroll = true): array
    {
        try {
            DB::beginTransaction();

            // Load employee with all necessary relationships
            $employee->load([
                'employment',
                'employment.departmentPosition',
                'employment.workLocation',
                'employeeFundingAllocations' => function ($query) use ($payPeriodDate) {
                    $query->where(function ($q) use ($payPeriodDate) {
                        $q->where('start_date', '<=', $payPeriodDate)
                            ->where(function ($subQ) use ($payPeriodDate) {
                                $subQ->whereNull('end_date')
                                    ->orWhere('end_date', '>=', $payPeriodDate);
                            });
                    });
                },
                'employeeFundingAllocations.grantItem.grant',
                'employeeFundingAllocations.grant',
                'employeeChildren',
            ]);

            if (! $employee->employment) {
                throw new \Exception('Employee has no active employment record');
            }

            if ($employee->employeeFundingAllocations->isEmpty()) {
                throw new \Exception('Employee has no active funding allocations');
            }

            $payrollRecords = [];
            $interSubsidiaryAdvances = [];
            $totalNetSalary = 0;

            // Process each funding allocation
            foreach ($employee->employeeFundingAllocations as $allocation) {
                $payrollData = $this->calculateAllocationPayroll($employee, $allocation, $payPeriodDate);

                if ($savePayroll) {
                    $payroll = $this->createPayrollRecord($employee->employment, $allocation, $payrollData, $payPeriodDate);
                    $payrollRecords[] = $payroll;

                    // Check if inter-organization advance is needed
                    $advance = $this->createInterOrganizationAdvanceIfNeeded($employee, $allocation, $payroll, $payPeriodDate);
                    if ($advance) {
                        $interSubsidiaryAdvances[] = $advance;
                    }
                }

                $totalNetSalary += $payrollData['calculations']['net_salary'];
            }

            DB::commit();

            $result = [
                'success' => true,
                'employee' => $employee,
                'pay_period_date' => $payPeriodDate->format('Y-m-d'),
                'total_net_salary' => $totalNetSalary,
                'allocation_count' => $employee->employeeFundingAllocations->count(),
            ];

            if ($savePayroll) {
                $result['payroll_records'] = $payrollRecords;
                $result['inter_organization_advances'] = $interSubsidiaryAdvances;
                $result['summary'] = [
                    'payrolls_created' => count($payrollRecords),
                    'advances_created' => count($interSubsidiaryAdvances),
                    'total_advance_amount' => collect($interSubsidiaryAdvances)->sum('amount'),
                ];
            }

            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payroll processing failed', [
                'employee_id' => $employee->id,
                'pay_period_date' => $payPeriodDate->format('Y-m-d'),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Preview inter-organization advances that would be created for an employee
     */
    public function previewInterOrganizationAdvances(Employee $employee, Carbon $payPeriodDate): array
    {
        // Load employee with all necessary relationships
        $employee->load([
            'employment',
            'employeeFundingAllocations' => function ($query) use ($payPeriodDate) {
                $query->where(function ($q) use ($payPeriodDate) {
                    $q->where('start_date', '<=', $payPeriodDate)
                        ->where(function ($subQ) use ($payPeriodDate) {
                            $subQ->whereNull('end_date')
                                ->orWhere('end_date', '>=', $payPeriodDate);
                        });
                });
            },
            'employeeFundingAllocations.grantItem.grant',
            'employeeFundingAllocations.grant',
        ]);

        if (! $employee->employment) {
            return ['advances_needed' => false, 'message' => 'Employee has no active employment record'];
        }

        if ($employee->employeeFundingAllocations->isEmpty()) {
            return ['advances_needed' => false, 'message' => 'Employee has no active funding allocations'];
        }

        $advancePreviews = [];
        $totalAdvanceAmount = 0;

        foreach ($employee->employeeFundingAllocations as $allocation) {
            $projectGrant = $this->getFundingGrant($allocation);
            if (! $projectGrant) {
                continue;
            }

            $fundingOrganization = $projectGrant->organization;
            $employeeOrganization = $employee->organization;

            // Check if advance is needed
            if ($fundingOrganization !== $employeeOrganization) {
                $hubGrant = \App\Models\Grant::getHubGrantForOrganization($fundingOrganization);

                if (! $hubGrant) {
                    continue;
                }

                // Calculate estimated salary for this allocation
                $payrollData = $this->calculateAllocationPayroll($employee, $allocation, $payPeriodDate);
                $estimatedNetSalary = $payrollData['calculations']['net_salary'];

                $advancePreviews[] = [
                    'allocation_id' => $allocation->id,
                    'allocation_type' => $allocation->allocation_type,
                    'fte' => $allocation->fte,
                    'project_grant' => [
                        'id' => $projectGrant->id,
                        'code' => $projectGrant->code,
                        'name' => $projectGrant->name,
                        'organization' => $projectGrant->organization,
                    ],
                    'hub_grant' => [
                        'id' => $hubGrant->id,
                        'code' => $hubGrant->code,
                        'name' => $hubGrant->name,
                        'organization' => $hubGrant->organization,
                    ],
                    'from_organization' => $fundingOrganization,
                    'to_organization' => $employeeOrganization,
                    'estimated_amount' => $estimatedNetSalary,
                    'formatted_amount' => '฿'.number_format($estimatedNetSalary, 2),
                ];

                $totalAdvanceAmount += $estimatedNetSalary;
            }
        }

        return [
            'advances_needed' => ! empty($advancePreviews),
            'employee' => [
                'id' => $employee->id,
                'staff_id' => $employee->staff_id,
                'name' => $employee->first_name_en.' '.$employee->last_name_en,
                'organization' => $employee->organization,
            ],
            'pay_period_date' => $payPeriodDate->format('Y-m-d'),
            'advance_previews' => $advancePreviews,
            'summary' => [
                'total_advances' => count($advancePreviews),
                'total_amount' => $totalAdvanceAmount,
                'formatted_total_amount' => '฿'.number_format($totalAdvanceAmount, 2),
            ],
        ];
    }

    /**
     * Process bulk payroll for multiple employees
     */
    public function processBulkPayroll(array $employeeIds, Carbon $payPeriodDate, bool $savePayroll = true): array
    {
        $results = [];
        $errors = [];
        $totalAdvances = 0;
        $totalPayrolls = 0;

        foreach ($employeeIds as $employeeId) {
            try {
                $employee = Employee::findOrFail($employeeId);
                $result = $this->processEmployeePayroll($employee, $payPeriodDate, $savePayroll);

                $results[] = [
                    'employee_id' => $employeeId,
                    'staff_id' => $employee->staff_id,
                    'name' => $employee->first_name_en.' '.$employee->last_name_en,
                    'success' => true,
                    'net_salary' => $result['total_net_salary'],
                    'allocations' => $result['allocation_count'],
                ];

                if ($savePayroll && isset($result['summary'])) {
                    $totalPayrolls += $result['summary']['payrolls_created'];
                    $totalAdvances += $result['summary']['advances_created'];
                }

            } catch (\Exception $e) {
                $errors[] = [
                    'employee_id' => $employeeId,
                    'error' => $e->getMessage(),
                ];

                Log::error('Bulk payroll processing failed for employee', [
                    'employee_id' => $employeeId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return [
            'success' => true,
            'processed' => count($results),
            'errors' => count($errors),
            'results' => $results,
            'error_details' => $errors,
            'summary' => [
                'total_payrolls_created' => $totalPayrolls,
                'total_advances_created' => $totalAdvances,
                'processing_date' => now()->format('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * Calculate payroll for a specific funding allocation (public method for controller)
     */
    public function calculateAllocationPayrollForController(Employee $employee, EmployeeFundingAllocation $allocation, Carbon $payPeriodDate): array
    {
        return $this->calculateAllocationPayroll($employee, $allocation, $payPeriodDate);
    }

    /**
     * Calculate comprehensive payroll summary for employee with all funding allocations
     */
    public function calculateEmployeePayrollSummary(Employee $employee, Carbon $payPeriodDate): array
    {
        $employment = $employee->employment;

        if (! $employment) {
            throw new \Exception('Employee has no active employment record');
        }

        if ($employee->employeeFundingAllocations->isEmpty()) {
            throw new \Exception('Employee has no active funding allocations');
        }

        $allocationCalculations = [];
        $totals = [
            'salary_current_year_by_fte' => 0,
            'compensation_refund' => 0,
            'thirteen_month_salary' => 0,
            'pvd_saving_employee' => 0,
            'social_security_employee' => 0,
            'health_welfare_employee' => 0,
            'income_tax' => 0,
            'social_security_employer' => 0,
            'health_welfare_employer' => 0,
        ];

        // Calculate for each funding allocation
        foreach ($employee->employeeFundingAllocations as $allocation) {
            $calculation = $this->calculateAllocationPayroll($employee, $allocation, $payPeriodDate);
            $allocationCalculations[] = $calculation;

            // Sum up totals
            $calc = $calculation['calculations'];
            $totals['salary_current_year_by_fte'] += $calc['gross_salary_by_FTE'];
            $totals['compensation_refund'] += $calc['compensation_refund'];
            $totals['thirteen_month_salary'] += $calc['thirteen_month_salary'];
            $totals['pvd_saving_employee'] += $calc['pvd_saving_fund_employee'];
            $totals['social_security_employee'] += $calc['employee_social_security'];
            $totals['health_welfare_employee'] += $calc['employee_health_welfare'];
            $totals['income_tax'] += $calc['income_tax'];
            $totals['social_security_employer'] += $calc['employer_social_security'];
            $totals['health_welfare_employer'] += $calc['employer_health_welfare'];
        }

        // Calculate final summary totals based on your formulas
        // Net Salary = salary current year by fte + compensation/refund + 13 months salary - (PVD/Saving + employee social security + employee health welfare + tax)
        $netSalary = $totals['salary_current_year_by_fte'] +
                    $totals['compensation_refund'] +
                    $totals['thirteen_month_salary'] -
                    ($totals['pvd_saving_employee'] +
                     $totals['social_security_employee'] +
                     $totals['health_welfare_employee'] +
                     $totals['income_tax']);

        // Total Salary = salary current year by fte + compensation/refund + 13 months salary + employer social security + employer health welfare
        $totalSalary = $totals['salary_current_year_by_fte'] +
                      $totals['compensation_refund'] +
                      $totals['thirteen_month_salary'] +
                      $totals['social_security_employer'] +
                      $totals['health_welfare_employer'];

        // Total PVD/Saving Fund = (PVD/Saving fund) * 2
        $totalPvdSavingFund = $totals['pvd_saving_employee'] * 2;

        return [
            'employee' => $employee,
            'pay_period_date' => $payPeriodDate->format('Y-m-d'),
            'allocation_calculations' => $allocationCalculations,
            'summary_totals' => [
                'salary_current_year_by_fte' => round($totals['salary_current_year_by_fte'], 2),
                'compensation_refund' => round($totals['compensation_refund'], 2),
                'thirteen_month_salary' => round($totals['thirteen_month_salary'], 2),
                'pvd_saving_employee_total' => round($totals['pvd_saving_employee'], 2),
                'social_security_employee_total' => round($totals['social_security_employee'], 2),
                'health_welfare_employee_total' => round($totals['health_welfare_employee'], 2),
                'income_tax_total' => round($totals['income_tax'], 2),
                'social_security_employer_total' => round($totals['social_security_employer'], 2),
                'health_welfare_employer_total' => round($totals['health_welfare_employer'], 2),
                'net_salary' => round($netSalary, 2),
                'total_salary' => round($totalSalary, 2),
                'total_pvd_saving_fund' => round($totalPvdSavingFund, 2),
            ],
            'allocation_count' => $employee->employeeFundingAllocations->count(),
        ];
    }

    /**
     * Calculate payroll for a specific funding allocation
     */
    private function calculateAllocationPayroll(Employee $employee, EmployeeFundingAllocation $allocation, Carbon $payPeriodDate): array
    {
        $employment = $employee->employment;

        // Calculate pro-rated salary for probation transition
        $salaryCalculation = $this->calculateProRatedSalaryForProbation($employment, $payPeriodDate);

        // Calculate annual salary increase
        $annualIncrease = $this->calculateAnnualSalaryIncrease($employee, $employment, $payPeriodDate);
        $adjustedGrossSalary = $salaryCalculation['gross_salary'] + $annualIncrease;

        // ===== CALCULATE ALL 13 PAYROLL ITEMS USING DEDICATED METHODS =====

        // 1. Gross Salary
        $grossSalary = $this->calculateGrossSalary($employment);

        // 2. Gross Salary of Current Year by FTE (includes pro-rating and LOE)
        $grossSalaryCurrentYearByFTE = $this->calculateGrossSalaryCurrentYearByFTE($employment, $allocation, $payPeriodDate, $adjustedGrossSalary);

        // 3. Compensation/Refund
        $compensationRefund = $this->calculateCompensationRefundAmount($employment, $payPeriodDate, $grossSalaryCurrentYearByFTE);

        // 4. 13th Month Salary
        $thirteenthMonthSalary = $this->calculateThirteenthMonthSalaryAmount($employee, $employment, $payPeriodDate, $grossSalaryCurrentYearByFTE);

        // 5. PVD/Saving Fund (7.5%)
        $pvdSavingCalculations = $this->calculatePVDSavingFund($employee, $grossSalaryCurrentYearByFTE, $employment);
        $pvdSavingEmployee = $pvdSavingCalculations['pvd_employee'] + $pvdSavingCalculations['saving_fund'];

        // 6. Employer Social Security (5%)
        $employerSocialSecurity = $this->calculateEmployerSocialSecurity($grossSalaryCurrentYearByFTE);

        // 7. Employee Social Security (5%)
        $employeeSocialSecurity = $this->calculateEmployeeSocialSecurity($grossSalaryCurrentYearByFTE);

        // 8. Health Welfare Employer
        $healthWelfareEmployer = $this->calculateHealthWelfareEmployer($employee, $grossSalaryCurrentYearByFTE);

        // 9. Health Welfare Employee
        $healthWelfareEmployee = $this->calculateHealthWelfareEmployee($grossSalaryCurrentYearByFTE);

        // 10. Income Tax
        $incomeTax = $this->calculateIncomeTax($employee, $grossSalaryCurrentYearByFTE, $employment, $payPeriodDate);

        // 11. Net Salary
        $netSalary = $this->calculateNetSalary(
            $grossSalaryCurrentYearByFTE,
            $compensationRefund,
            $thirteenthMonthSalary,
            $pvdSavingEmployee,
            $employeeSocialSecurity,
            $healthWelfareEmployee,
            $incomeTax
        );

        // 12. Total Salary (Total Cost to Company)
        $totalSalary = $this->calculateTotalSalary(
            $grossSalaryCurrentYearByFTE,
            $compensationRefund,
            $thirteenthMonthSalary,
            $employerSocialSecurity,
            $healthWelfareEmployer
        );

        // 13. Total PVD/Saving Fund
        $totalPVDSaving = $this->calculateTotalPVDSaving($pvdSavingEmployee);

        // ===== LEGACY CALCULATIONS (for compatibility) =====
        $totalIncome = $grossSalaryCurrentYearByFTE + $compensationRefund + $thirteenthMonthSalary;
        $totalDeductions = $pvdSavingEmployee + $employeeSocialSecurity + $healthWelfareEmployee + $incomeTax;
        $employerContributions = $employerSocialSecurity + $healthWelfareEmployer;
        // Note: No PVD/Saving Fund employer contribution

        return [
            'allocation_id' => $allocation->id,
            'staff_id' => $employee->staff_id,
            'employee_name' => trim($employee->first_name_en.' '.$employee->last_name_en),
            'department' => $employment->department->name ?? 'N/A',
            'position' => $employment->position->title ?? 'N/A',
            'employment_type' => $employment->employment_type,
            'fte_percentage' => ($allocation->fte ?? 1.0) * 100, // Convert decimal to percentage
            'funding_source' => $this->getFundingSourceName($allocation),
            'funding_type' => $allocation->allocation_type,
            'calculations' => [
                // ===== PAYROLL FIELDS (matching database schema) =====
                'gross_salary' => $grossSalary,
                'gross_salary_by_fte' => $grossSalaryCurrentYearByFTE, // Fixed: lowercase 'fte'
                'gross_salary_by_FTE' => $grossSalaryCurrentYearByFTE, // Legacy compatibility
                'salary_increase_1_percent' => $annualIncrease,
                'compensation_refund' => $compensationRefund,
                'thirteenth_month_salary' => $thirteenthMonthSalary,
                'pvd_saving_fund_employee' => $pvdSavingEmployee,
                'employer_social_security' => $employerSocialSecurity,
                'employee_social_security' => $employeeSocialSecurity,
                'employer_health_welfare' => $healthWelfareEmployer,
                'employee_health_welfare' => $healthWelfareEmployee,
                'income_tax' => $incomeTax, // Primary key for bulk payroll
                'net_salary' => $netSalary,
                'total_salary' => $totalSalary,
                'total_pvd_saving_fund' => $totalPVDSaving,

                // ===== ADDITIONAL CALCULATED FIELDS =====
                'pvd' => $pvdSavingCalculations['pvd_employee'],
                'saving_fund' => $pvdSavingCalculations['saving_fund'],
                'tax' => $incomeTax, // Legacy compatibility
                'total_income' => $totalIncome,
                'total_deduction' => $totalDeductions,
                'employer_contribution' => $employerContributions,
                'total_pvd' => $pvdSavingCalculations['pvd_employee'],
                'total_saving_fund' => $pvdSavingCalculations['saving_fund'],
            ],
        ];
    }

    /**
     * Create payroll record in database
     */
    private function createPayrollRecord(Employment $employment, EmployeeFundingAllocation $allocation, array $payrollData, Carbon $payPeriodDate): Payroll
    {
        $calculations = $payrollData['calculations'];

        return Payroll::create([
            'employment_id' => $employment->id,
            'employee_funding_allocation_id' => $allocation->id,
            'gross_salary' => $calculations['gross_salary'],
            'gross_salary_by_FTE' => $calculations['gross_salary_by_FTE'],
            'compensation_refund' => $calculations['compensation_refund'],
            'thirteen_month_salary' => $calculations['thirteen_month_salary'],
            'thirteen_month_salary_accured' => $calculations['thirteen_month_salary'],
            'pvd' => $calculations['pvd_employee'],
            'saving_fund' => $calculations['saving_fund'],
            'employer_social_security' => $calculations['employer_social_security'],
            'employee_social_security' => $calculations['employee_social_security'],
            'employer_health_welfare' => $calculations['employer_health_welfare'],
            'employee_health_welfare' => $calculations['employee_health_welfare'],
            'tax' => $calculations['tax'],
            'net_salary' => $calculations['net_salary'],
            'total_salary' => $calculations['gross_salary_by_FTE'],
            'total_pvd' => $calculations['pvd_employee'],
            'total_saving_fund' => $calculations['saving_fund'],
            'salary_bonus' => 0, // Can be added later
            'total_income' => $calculations['total_income'],
            'employer_contribution' => $calculations['employer_contribution'],
            'total_deduction' => $calculations['total_deduction'],
            'pay_period_date' => $payPeriodDate,
            'notes' => 'Processed via PayrollService on '.now()->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Create inter-organization advance if needed
     */
    public function createInterOrganizationAdvanceIfNeeded(Employee $employee, EmployeeFundingAllocation $allocation, Payroll $payroll, Carbon $payPeriodDate): ?InterOrganizationAdvance
    {
        $projectGrant = $this->getFundingGrant($allocation);
        if (! $projectGrant) {
            Log::warning('Cannot create inter-organization advance: No project grant found', [
                'allocation_id' => $allocation->id,
                'payroll_id' => $payroll->id,
            ]);

            return null;
        }

        $fundingOrganization = $projectGrant->organization;
        $employeeOrganization = $employee->organization;

        // No advance needed if subsidiaries match
        if ($fundingOrganization === $employeeOrganization) {
            return null;
        }

        // Get the correct hub grant for the lending organization
        $hubGrant = \App\Models\Grant::getHubGrantForOrganization($fundingOrganization);

        if (! $hubGrant) {
            Log::error('Hub grant not found for organization', [
                'organization' => $fundingOrganization,
                'allocation_id' => $allocation->id,
                'payroll_id' => $payroll->id,
            ]);

            return null;
        }

        $advance = InterOrganizationAdvance::create([
            'payroll_id' => $payroll->id,
            'from_organization' => $fundingOrganization,
            'to_organization' => $employeeOrganization,
            'via_grant_id' => $hubGrant->id, // Use hub grant, not project grant
            'amount' => $payroll->net_salary,
            'advance_date' => $payPeriodDate,
            'notes' => "Hub grant advance: {$projectGrant->code} → {$hubGrant->code} for {$employee->staff_id}",
            'created_by' => Auth::user()->name ?? 'system',
            'updated_by' => Auth::user()->name ?? 'system',
        ]);

        Log::info('Inter-organization advance created', [
            'advance_id' => $advance->id,
            'from' => $fundingOrganization,
            'to' => $employeeOrganization,
            'amount' => $payroll->net_salary,
            'employee' => $employee->staff_id,
            'project_grant' => $projectGrant->code,
            'hub_grant' => $hubGrant->code,
            'payroll_id' => $payroll->id,
        ]);

        return $advance;
    }

    /**
     * Get funding organization from allocation
     */
    private function getFundingOrganization(EmployeeFundingAllocation $allocation): string
    {
        if ($allocation->grantItem && $allocation->grantItem->grant) {
            return $allocation->grantItem->grant->organization ?? 'UNKNOWN';
        }

        return 'UNKNOWN';
    }

    /**
     * Get funding grant from allocation
     */
    private function getFundingGrant(EmployeeFundingAllocation $allocation): ?object
    {
        if ($allocation->grantItem && $allocation->grantItem->grant) {
            return $allocation->grantItem->grant;
        }

        return null;
    }

    /**
     * Get funding source name from allocation
     */
    private function getFundingSourceName(EmployeeFundingAllocation $allocation): string
    {
        if ($allocation->grantItem && $allocation->grantItem->grant) {
            return $allocation->grantItem->grant->name ?? 'Grant';
        }

        return 'Grant';
    }

    // ===========================================
    // INDIVIDUAL PAYROLL CALCULATION METHODS
    // 13 Required Payroll Items
    // ===========================================

    /**
     * 1. Calculate Gross Salary (Position Salary)
     */
    private function calculateGrossSalary($employment): float
    {
        return (float) $employment->pass_probation_salary;
    }

    /**
     * 2. Calculate Gross Salary of Current Year by FTE
     * (Includes pro-rating for mid-month starts and Level of Effort)
     */
    private function calculateGrossSalaryCurrentYearByFTE($employment, EmployeeFundingAllocation $allocation, Carbon $payPeriodDate, float $adjustedGrossSalary): float
    {
        // Apply Level of Effort percentage
        $grossSalaryByFTE = $adjustedGrossSalary * $allocation->fte;

        // Apply pro-rating if employee started mid-month
        $startDate = Carbon::parse($employment->start_date);
        if ($startDate->year == $payPeriodDate->year && $startDate->month == $payPeriodDate->month) {
            $daysInMonth = $payPeriodDate->copy()->endOfMonth()->day;
            $daysWorked = $startDate->diffInDays($payPeriodDate->copy()->endOfMonth()) + 1;
            $grossSalaryByFTE = ($grossSalaryByFTE / $daysInMonth) * $daysWorked;
        }

        return round($grossSalaryByFTE, 2);
    }

    /**
     * 3. Calculate Compensation/Refund
     * (Currently set to 0 as pro-rating is handled in gross salary calculation)
     */
    private function calculateCompensationRefundAmount($employment, Carbon $payPeriodDate, float $monthlySalary): float
    {
        // This function calculates adjustment for mid-month starts/ends
        // For now, we'll return 0 and handle pro-rating in the main salary calculation
        // This prevents double-calculation and confusion
        return 0.0;
    }

    /**
     * 4. Calculate 13th Month Salary
     */
    private function calculateThirteenthMonthSalaryAmount(Employee $employee, $employment, Carbon $payPeriodDate, float $monthlySalary): float
    {
        $startDate = Carbon::parse($employment->start_date);
        $serviceMonths = $startDate->diffInMonths($payPeriodDate);

        if ($serviceMonths >= 6) {
            return round($monthlySalary / 12, 2);
        }

        return 0.0;
    }

    /**
     * 5. Calculate PVD/Saving Fund (percentage from global settings)
     * Local ID = PVD, Local non ID = Saving Fund
     */
    private function calculatePVDSavingFund(Employee $employee, float $monthlySalary, $employment): array
    {
        // Get PVD/Saving Fund percentage from global settings (default to 7.5% if not set)
        $pvdPercentage = BenefitSetting::getActiveSetting('pvd_percentage') ?? 7.5;
        $savingFundPercentage = BenefitSetting::getActiveSetting('saving_fund_percentage') ?? 7.5;

        // PVD for Local ID (employee only)
        // Saving Fund for Local non ID (employee only)
        // Will be deducted after passing probation with the exact date
        $probationPassDate = $employment->pass_probation_date ? Carbon::parse($employment->pass_probation_date) : null;

        // Check if employee has passed probation
        $hasPassed = $probationPassDate && Carbon::now()->gte($probationPassDate);

        if ($hasPassed) {
            if ($employee->status === 'Local ID') {
                // PVD for Local ID employees (from global settings)
                return [
                    'pvd_employee' => round($monthlySalary * ($pvdPercentage / 100), 2),
                    'saving_fund' => 0.0,
                ];
            } elseif ($employee->status === 'Local non ID') {
                // Saving Fund for Local non ID employees (from global settings)
                return [
                    'pvd_employee' => 0.0,
                    'saving_fund' => round($monthlySalary * ($savingFundPercentage / 100), 2),
                ];
            }
        }

        return [
            'pvd_employee' => 0.0,
            'saving_fund' => 0.0,
        ];
    }

    /**
     * 6. Calculate Employer Social Security (5%)
     */
    private function calculateEmployerSocialSecurity(float $monthlySalary): float
    {
        // Employer social security 5% (doesn't exceed 750 Baht)
        $employerContribution = min($monthlySalary * 0.05, 750.0);

        return round($employerContribution, 2);
    }

    /**
     * 7. Calculate Employee Social Security (5%)
     */
    private function calculateEmployeeSocialSecurity(float $monthlySalary): float
    {
        // Employee social security 5% (doesn't exceed 750 Baht)
        $employeeContribution = min($monthlySalary * 0.05, 750.0);

        return round($employeeContribution, 2);
    }

    /**
     * 8. Calculate Health Welfare Employer
     */
    private function calculateHealthWelfareEmployer(Employee $employee, float $monthlySalary): float
    {
        $employerContribution = 0.0;

        // Health Welfare Employer contribution
        // SMRU organization pays for Non-Thai ID and some expat
        // BHF organization doesn't have to pay for health welfare employer
        if ($employee->organization === 'SMRU' &&
            ($employee->status === 'Non-Thai ID' || $employee->status === 'Expat')) {
            // Calculate employee contribution first
            if ($monthlySalary > 15000) {
                $employerContribution = 150;
            } elseif ($monthlySalary > 5000) {
                $employerContribution = 100;
            } else {
                $employerContribution = 60;
            }
        } elseif ($employee->organization === 'BHF') {
            $employerContribution = 0.0; // BHF doesn't pay employer health welfare
        }

        return round($employerContribution, 2);
    }

    /**
     * 9. Calculate Health Welfare Employee
     */
    private function calculateHealthWelfareEmployee(float $monthlySalary): float
    {
        // Health Welfare Employee contribution based on salary
        if ($monthlySalary > 15000) {
            return 150.0;
        } elseif ($monthlySalary > 5000) {
            return 100.0;
        } else {
            return 60.0;
        }
    }

    /**
     * 10. Calculate Tax (Income Tax)
     */
    private function calculateIncomeTax(Employee $employee, float $grossSalaryByFTE, $employment, Carbon $payPeriodDate): float
    {
        // Prepare employee data for tax calculation
        $employeeData = [
            'has_spouse' => $employee->has_spouse,
            'children' => $employee->employeeChildren->count(),
            'eligible_parents' => $employee->eligible_parents_count,
            'employee_status' => $employee->status,
            'months_working_this_year' => $this->calculateMonthsWorkingThisYear($employment, $payPeriodDate),
        ];

        // Calculate income tax using TaxCalculationService
        $taxCalculation = $this->taxService->calculateEmployeeTax($grossSalaryByFTE, $employeeData);

        return round($taxCalculation['monthly_tax_amount'], 2);
    }

    /**
     * 11. Calculate Net Salary
     * Formula: Gross Salary Current Year by FTE + Compensation/Refund + 13th Month - (PVD/Saving + Employee Social Security + Health Welfare Employee + Tax)
     */
    private function calculateNetSalary(
        float $grossSalaryCurrentYearByFTE,
        float $compensationRefund,
        float $thirteenthMonthSalary,
        float $pvdSavingEmployee,
        float $employeeSocialSecurity,
        float $healthWelfareEmployee,
        float $incomeTax
    ): float {
        $totalIncome = $grossSalaryCurrentYearByFTE + $compensationRefund + $thirteenthMonthSalary;
        $totalDeductions = $pvdSavingEmployee + $employeeSocialSecurity + $healthWelfareEmployee + $incomeTax;

        return round($totalIncome - $totalDeductions, 2);
    }

    /**
     * 12. Calculate Total Salary (Total Cost to Company)
     * Formula: Gross Salary Current Year by FTE + Compensation/Refund + 13th Month + Employer Social Security + Health Welfare Employer
     */
    private function calculateTotalSalary(
        float $grossSalaryCurrentYearByFTE,
        float $compensationRefund,
        float $thirteenthMonthSalary,
        float $employerSocialSecurity,
        float $healthWelfareEmployer
    ): float {
        return round(
            $grossSalaryCurrentYearByFTE +
            $compensationRefund +
            $thirteenthMonthSalary +
            $employerSocialSecurity +
            $healthWelfareEmployer,
            2
        );
    }

    /**
     * 13. Calculate Total PVD/Saving Fund
     * Formula: (PVD/Saving Fund Employee) * 2
     */
    private function calculateTotalPVDSaving(float $pvdSavingEmployee): float
    {
        return round($pvdSavingEmployee * 2, 2);
    }

    // ===========================================
    // LEGACY HELPER METHODS (for compatibility)
    // ===========================================

    // Helper calculation methods (simplified versions of the ones in PayrollController)

    private function calculateProRatedSalaryForProbation($employment, Carbon $payPeriodDate): array
    {
        // Use ProbationTransitionService for salary calculations with standardized 30-day month approach
        $probationService = app(ProbationTransitionService::class);

        // Check if this is the first month (employee started mid-month)
        $startDate = Carbon::parse($employment->start_date);
        $isFirstMonth = $probationService->startedMidMonthIn($employment, $payPeriodDate);

        if ($isFirstMonth) {
            // Calculate partial salary for first month
            $firstMonthSalary = $probationService->calculateFirstMonthSalary($employment);

            return ['gross_salary' => $firstMonthSalary];
        }

        // Check if this is transition month (probation completion falls mid-month)
        $isTransitionMonth = $probationService->isTransitionMonth($employment, $payPeriodDate);

        if ($isTransitionMonth) {
            // Calculate pro-rated salary for transition month
            $proRatedSalary = $probationService->calculateProRatedSalary($employment, $payPeriodDate);

            return ['gross_salary' => $proRatedSalary];
        }

        // Regular month - use current applicable salary
        $salary = $employment->getCurrentSalary();

        return ['gross_salary' => $salary];
    }

    private function calculateAnnualSalaryIncrease(Employee $employee, $employment, Carbon $payPeriodDate): float
    {
        // 1% increase for employees with 1+ years service (365 working days, excluding weekends)
        $startDate = Carbon::parse($employment->start_date);

        // Calculate working days (excluding weekends)
        $workingDays = 0;
        $currentDate = $startDate->copy();

        while ($currentDate->lte($payPeriodDate)) {
            if (! $currentDate->isWeekend()) {
                $workingDays++;
            }
            $currentDate->addDay();
        }

        // Check if employee has worked 365 working days (approximately 1 year)
        if ($workingDays >= 365) {
            return round($employment->pass_probation_salary * 0.01, 2);
        }

        return 0.0;
    }

    private function calculateThirteenthMonthSalary(Employee $employee, $employment, Carbon $payPeriodDate, float $monthlySalary): float
    {
        $startDate = Carbon::parse($employment->start_date);
        $serviceMonths = $startDate->diffInMonths($payPeriodDate);

        if ($serviceMonths >= 6) {
            return round($monthlySalary / 12, 2);
        }

        return 0.0;
    }

    private function calculateCompensationRefund($employment, Carbon $payPeriodDate, float $monthlySalary): float
    {
        // This function calculates adjustment for mid-month starts/ends
        // For now, we'll return 0 and handle pro-rating in the main salary calculation
        // This prevents double-calculation and confusion
        return 0.0;
    }

    private function calculatePVDContributions(Employee $employee, float $monthlySalary, $employment): array
    {
        // Get PVD/Saving Fund percentage from global settings (default to 7.5% if not set)
        $pvdPercentage = BenefitSetting::getActiveSetting('pvd_percentage') ?? 7.5;
        $savingFundPercentage = BenefitSetting::getActiveSetting('saving_fund_percentage') ?? 7.5;

        // PVD for Local ID (employee only)
        // Saving Fund for Local non ID (employee only)
        // Will be deducted after passing probation with the exact date
        $probationPassDate = $employment->pass_probation_date ? Carbon::parse($employment->pass_probation_date) : null;

        // Check if employee has passed probation
        $hasPassed = $probationPassDate && Carbon::now()->gte($probationPassDate);

        if ($hasPassed) {
            if ($employee->status === 'Local ID') {
                // PVD for Local ID employees (from global settings)
                return [
                    'pvd_employee' => round($monthlySalary * ($pvdPercentage / 100), 2),
                    'saving_fund' => 0.0,
                ];
            } elseif ($employee->status === 'Local non ID') {
                // Saving Fund for Local non ID employees (from global settings)
                return [
                    'pvd_employee' => 0.0,
                    'saving_fund' => round($monthlySalary * ($savingFundPercentage / 100), 2),
                ];
            }
        }

        return [
            'pvd_employee' => 0.0,
            'saving_fund' => 0.0,
        ];
    }

    private function calculateSocialSecurity(float $monthlySalary): array
    {
        // Employee social security 5% (doesn't exceed 750 Baht)
        // Employer social security 5% (doesn't exceed 750 Baht)
        $employeeContribution = min($monthlySalary * 0.05, 750.0);
        $employerContribution = min($monthlySalary * 0.05, 750.0);

        return [
            'employee' => round($employeeContribution, 2),
            'employer' => round($employerContribution, 2),
        ];
    }

    private function calculateHealthWelfare(Employee $employee, $employment, float $monthlySalary): array
    {
        $employeeContribution = 0.0;
        $employerContribution = 0.0;

        // Health Welfare Employee contribution based on salary
        if ($monthlySalary > 15000) {
            $employeeContribution = 150;
        } elseif ($monthlySalary > 5000) {
            $employeeContribution = 100;
        } else {
            $employeeContribution = 60;
        }

        // Health Welfare Employer contribution
        // SMRU organization pays for Non-Thai ID and some expat
        // BHF organization doesn't have to pay for health welfare employer
        if ($employee->organization === 'SMRU' &&
            ($employee->status === 'Non-Thai ID' || $employee->status === 'Expat')) {
            $employerContribution = $employeeContribution; // Same as employee contribution
        } elseif ($employee->organization === 'BHF') {
            $employerContribution = 0.0; // BHF doesn't pay employer health welfare
        }

        return [
            'employee' => round($employeeContribution, 2),
            'employer' => round($employerContribution, 2),
        ];
    }

    private function calculateMonthsWorkingThisYear($employment, Carbon $payPeriodDate): int
    {
        $startDate = Carbon::parse($employment->start_date);
        $currentYear = $payPeriodDate->year;

        if ($startDate->year < $currentYear) {
            return 12;
        }

        if ($startDate->year == $currentYear) {
            return min(12, $startDate->diffInMonths(Carbon::create($currentYear, 12, 31)) + 1);
        }

        return 12;
    }

    /**
     * Get payroll statistics for a period
     */
    public function getPayrollStatistics(Carbon $startDate, Carbon $endDate): array
    {
        $payrolls = Payroll::whereBetween('pay_period_date', [$startDate, $endDate])->get();
        $advances = InterOrganizationAdvance::whereBetween('advance_date', [$startDate, $endDate])->get();

        return [
            'period' => [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
            'payroll_summary' => [
                'total_payrolls' => $payrolls->count(),
                'total_gross_salary' => $payrolls->sum('gross_salary'),
                'total_net_salary' => $payrolls->sum('net_salary'),
                'total_deductions' => $payrolls->sum('total_deduction'),
                'total_employer_contributions' => $payrolls->sum('employer_contribution'),
            ],
            'advance_summary' => [
                'total_advances' => $advances->count(),
                'total_advance_amount' => $advances->sum('amount'),
                'by_subsidiary' => $advances->groupBy('from_organization')->map->count()->toArray(),
                'pending_settlements' => $advances->whereNull('settlement_date')->count(),
            ],
        ];
    }
}
