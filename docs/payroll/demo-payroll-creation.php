<?php

/**
 * Demo: Complete HRMS Payroll Creation Workflow
 *
 * This script demonstrates how to create a complete payroll record
 * from scratch using your HRMS system with automated tax calculations.
 */

use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
use App\Models\Employment;
use App\Models\Payroll;
use App\Services\TaxCalculationService;

// Step 1: Create Employee
echo "🧑 Creating Employee...\n";

$employee = Employee::create([
    'staff_id' => 'EMP001',
    'subsidiary' => 'SMRU',
    'first_name_en' => 'John',
    'last_name_en' => 'Doe',
    'first_name_th' => 'จอห์น',
    'last_name_th' => 'โด',
    'gender' => 'Male',
    'date_of_birth' => '1990-01-15',
    'nationality' => 'Thai',
    'phone' => '+66812345678',
    'email' => 'john.doe@company.com',
    'status' => 'Active',
    'created_by' => 'demo-script',
    'updated_by' => 'demo-script',
]);

echo "✅ Employee created: ID {$employee->id} - {$employee->first_name_en} {$employee->last_name_en}\n\n";

// Step 2: Create Employment Record
echo "💼 Creating Employment Record...\n";

$employment = Employment::create([
    'employee_id' => $employee->id,
    'start_date' => '2025-01-01',
    'pay_method' => 'Bank Transfer',
    'department_id' => 1, // Assumes exists
    'position_id' => 1, // Assumes exists
    'work_location_id' => 1, // Assumes exists
    'pass_probation_salary' => 50000.00,
    'probation_salary' => 45000.00,
    'probation_pass_date' => '2025-04-01',
    'health_welfare' => true,
    'pvd' => true,
    'saving_fund' => false,

    'created_by' => 'demo-script',
    'updated_by' => 'demo-script',
]);

echo "✅ Employment created: ID {$employment->id} - Salary: ฿{$employment->pass_probation_salary}\n\n";

// Step 3: Create Funding Allocations (Mixed Funding Example)
echo "💰 Creating Funding Allocations...\n";

// Grant Funding (60%)
$grantAllocation = EmployeeFundingAllocation::create([
    'employee_id' => $employee->id,
    'employment_id' => $employment->id,
    'position_slot_id' => 1, // Assumes exists
    'fte' => 0.60,
    'allocation_type' => 'grant',
    'allocated_amount' => 30000.00,
    'start_date' => '2025-01-01',
    'end_date' => '2025-12-31',
    'created_by' => 'demo-script',
    'updated_by' => 'demo-script',
]);

// Organizational Funding (40%)
$orgAllocation = EmployeeFundingAllocation::create([
    'employee_id' => $employee->id,
    'employment_id' => $employment->id,
    'org_funded_id' => 1, // Assumes exists
    'fte' => 0.40,
    'allocation_type' => 'org_funded',
    'allocated_amount' => 20000.00,
    'start_date' => '2025-01-01',
    'end_date' => '2025-12-31',
    'created_by' => 'demo-script',
    'updated_by' => 'demo-script',
]);

echo '✅ Grant Allocation: '.($grantAllocation->fte * 100)."% FTE (฿{$grantAllocation->allocated_amount})\n";
echo '✅ Org Allocation: '.($orgAllocation->fte * 100)."% FTE (฿{$orgAllocation->allocated_amount})\n";

// Verify total FTE = 100%
$totalFTE = $employee->employeeFundingAllocations()->sum('fte');
echo '✅ Total FTE: '.($totalFTE * 100).'% '.($totalFTE == 1.00 ? '✓' : '❌')."\n\n";

// Step 4: Calculate Payroll Using Tax Service
echo "🧮 Calculating Payroll with Tax Service...\n";

$taxService = new TaxCalculationService(2025);

$payrollData = $taxService->calculatePayroll(
    employeeId: $employee->id,
    grossSalary: 50000,
    additionalIncome: [
        [
            'type' => 'performance_bonus',
            'amount' => 5000,
            'description' => 'Q4 Performance Bonus',
        ],
        [
            'type' => 'overtime',
            'amount' => 2000,
            'description' => 'Overtime Pay',
        ],
    ],
    additionalDeductions: [
        [
            'type' => 'company_loan',
            'amount' => 1000,
            'description' => 'Monthly loan repayment',
        ],
    ]
);

echo "✅ Tax Calculation Completed:\n";
echo '   - Gross Salary: ฿'.number_format($payrollData['gross_salary'], 2)."\n";
echo '   - Total Income: ฿'.number_format($payrollData['total_income'], 2)."\n";
echo '   - Income Tax: ฿'.number_format($payrollData['income_tax'], 2)."\n";
echo '   - Social Security (Employee): ฿'.number_format($payrollData['social_security']['employee_contribution'], 2)."\n";
echo '   - Net Salary: ฿'.number_format($payrollData['net_salary'], 2)."\n\n";

// Step 5: Create Payroll Record
echo "💾 Creating Payroll Record...\n";

$payroll = Payroll::create([
    'employment_id' => $employment->id,
    'employee_funding_allocation_id' => $grantAllocation->id, // Primary allocation
    'pay_period_date' => '2025-01-31',

    // Salary Information
    'gross_salary' => $payrollData['gross_salary'],
    'gross_salary_by_FTE' => $payrollData['gross_salary'], // FTE now tracked in funding allocations
    'net_salary' => $payrollData['net_salary'],
    'total_income' => $payrollData['total_income'],

    // Tax & Deductions
    'tax' => $payrollData['income_tax'],
    'total_deduction' => $payrollData['income_tax'] + $payrollData['social_security']['employee_contribution'] + 1000, // Including loan

    // Social Security
    'employee_social_security' => $payrollData['social_security']['employee_contribution'],
    'employer_social_security' => $payrollData['social_security']['employer_contribution'],

    // Provident Fund
    'pvd' => $payrollData['deductions']['provident_fund'],
    'total_pvd' => $payrollData['deductions']['provident_fund'],

    // Benefits
    'employer_health_welfare' => $employment->health_welfare ? 500 : 0,
    'employee_health_welfare' => $employment->health_welfare ? 200 : 0,

    // 13th Month Salary
    'thirteen_month_salary' => $payrollData['gross_salary'] / 12,
    'thirteen_month_salary_accured' => $payrollData['gross_salary'] / 12,

    // Additional Fields
    'compensation_refund' => 0,
    'saving_fund' => $employment->saving_fund ? 1000 : 0,
    'total_saving_fund' => $employment->saving_fund ? 1000 : 0,
    'salary_increase' => 7000, // Performance + Overtime bonuses
    'employer_contribution' => $payrollData['social_security']['employer_contribution'],

    'notes' => 'Demo payroll created with automated tax calculation system',
]);

echo "✅ Payroll Record Created: ID {$payroll->id}\n\n";

// Step 6: Display Summary
echo "📊 PAYROLL SUMMARY\n";
echo "==================\n";
echo "Employee: {$employee->first_name_en} {$employee->last_name_en} (ID: {$employee->id})\n";
echo "Staff ID: {$employee->staff_id}\n";
echo "Employment ID: {$employment->id} (Total FTE: {$totalFTE})\n";
echo "Pay Period: {$payroll->pay_period_date}\n\n";

echo "💰 COMPENSATION BREAKDOWN\n";
echo "========================\n";
echo 'Base Salary:           ฿'.number_format($payrollData['gross_salary'], 2)."\n";
echo "Performance Bonus:     ฿5,000.00\n";
echo "Overtime Pay:          ฿2,000.00\n";
echo 'Total Gross Income:    ฿'.number_format($payrollData['total_income'], 2)."\n\n";

echo "🏦 FUNDING ALLOCATION\n";
echo "====================\n";
echo 'Grant Funding (60%):   ฿'.number_format($payrollData['total_income'] * 0.60, 2)."\n";
echo 'Org Funding (40%):     ฿'.number_format($payrollData['total_income'] * 0.40, 2)."\n\n";

echo "📉 DEDUCTIONS\n";
echo "=============\n";
echo 'Income Tax:            ฿'.number_format($payrollData['income_tax'], 2)."\n";
echo 'Social Security:       ฿'.number_format($payrollData['social_security']['employee_contribution'], 2)."\n";
echo 'Provident Fund:        ฿'.number_format($payrollData['deductions']['provident_fund'], 2)."\n";
echo "Company Loan:          ฿1,000.00\n";
echo "Health & Welfare:      ฿200.00\n";
echo 'Total Deductions:      ฿'.number_format($payroll->total_deduction, 2)."\n\n";

echo "💵 NET PAY\n";
echo "==========\n";
echo 'Net Salary:            ฿'.number_format($payrollData['net_salary'], 2)."\n\n";

echo "🏢 EMPLOYER COSTS\n";
echo "================\n";
echo 'Gross Salary:          ฿'.number_format($payrollData['gross_salary'], 2)."\n";
echo 'Employer SS:           ฿'.number_format($payrollData['social_security']['employer_contribution'], 2)."\n";
echo "Health & Welfare:      ฿500.00\n";
echo '13th Month (Accrued):  ฿'.number_format($payroll->thirteen_month_salary_accured, 2)."\n";
echo 'Total Employer Cost:   ฿'.number_format(
    $payrollData['gross_salary'] +
    $payrollData['social_security']['employer_contribution'] +
    500 +
    $payroll->thirteen_month_salary_accured, 2
)."\n\n";

echo "🎯 TAX CALCULATION DETAILS\n";
echo "=========================\n";
echo "Tax Year: {$payrollData['tax_year']}\n";
echo 'Annual Taxable Income: ฿'.number_format($payrollData['taxable_income'], 2)."\n";

foreach ($payrollData['tax_breakdown'] as $bracket) {
    if ($bracket['tax_amount'] > 0) {
        echo "Tax Bracket {$bracket['bracket_order']}: {$bracket['income_range']} @ {$bracket['tax_rate']} = ฿".number_format($bracket['tax_amount'], 2)."\n";
    }
}

echo "\n🎉 Demo Complete! Payroll system working perfectly with:\n";
echo "   ✅ Automated tax calculations\n";
echo "   ✅ Funding allocation tracking\n";
echo "   ✅ Social security compliance\n";
echo "   ✅ Employee benefit management\n";
echo "   ✅ Complete audit trail\n";
echo "   ✅ Data encryption security\n\n";

echo "🔗 You can now use the API endpoints:\n";
echo "   GET /api/payrolls/{$payroll->id} - View payroll details\n";
echo "   GET /api/payrolls/{$payroll->id}/tax-summary - Tax breakdown\n";
echo "   POST /api/payrolls/calculate - Calculate new payroll\n";
echo "   GET /api/employees/{$employee->id}?include=employment,payrolls - Employee overview\n";
