# PayrollService Class Documentation

## Overview

The `PayrollService` class is a comprehensive service layer component responsible for processing employee payroll calculations, managing inter-subsidiary advances, and handling complex payroll scenarios in the HRMS system. This service encapsulates all payroll-related business logic and provides a clean interface for payroll operations.

## Table of Contents

1. [Class Structure](#class-structure)
2. [Constructor](#constructor)
3. [Public Methods](#public-methods)
4. [Private Calculation Methods](#private-calculation-methods)
5. [Payroll Calculation Items](#payroll-calculation-items)
6. [Inter-Subsidiary Advances](#inter-subsidiary-advances)
7. [Usage Examples](#usage-examples)
8. [Dependencies](#dependencies)

## Class Structure

```php
namespace App\Services;

class PayrollService
{
    protected TaxCalculationService $taxService;
    
    public function __construct(?int $taxYear = null)
    {
        $this->taxService = new TaxCalculationService($taxYear ?? date('Y'));
    }
}
```

### Properties

- **`$taxService`**: Instance of `TaxCalculationService` used for income tax calculations

## Constructor

The constructor initializes the service with a tax calculation service for the specified year.

**Parameters:**
- `$taxYear` (int|null): Tax year for calculations (defaults to current year)

## Public Methods

### 1. processEmployeePayroll()

Processes complete payroll for an employee including inter-subsidiary advances.

```php
public function processEmployeePayroll(Employee $employee, Carbon $payPeriodDate, bool $savePayroll = true): array
```

**Parameters:**
- `$employee` (Employee): The employee to process payroll for
- `$payPeriodDate` (Carbon): The pay period date
- `$savePayroll` (bool): Whether to save payroll records to database (default: true)

**Returns:** Array containing:
- `success`: Boolean indicating success
- `employee`: Employee object with loaded relationships
- `pay_period_date`: Formatted pay period date
- `total_net_salary`: Combined net salary from all allocations
- `allocation_count`: Number of funding allocations processed
- `payroll_records`: Array of created payroll records (if saved)
- `inter_subsidiary_advances`: Array of created advances (if saved)
- `summary`: Processing summary statistics

**Key Features:**
- Loads employee with all necessary relationships
- Processes each funding allocation separately
- Creates payroll records and inter-subsidiary advances
- Uses database transactions for data integrity
- Comprehensive error handling and logging

### 2. previewInterSubsidiaryAdvances()

Previews inter-subsidiary advances that would be created for an employee without saving them.

```php
public function previewInterSubsidiaryAdvances(Employee $employee, Carbon $payPeriodDate): array
```

**Parameters:**
- `$employee` (Employee): The employee to preview advances for
- `$payPeriodDate` (Carbon): The pay period date

**Returns:** Array containing:
- `advances_needed`: Boolean indicating if advances are required
- `employee`: Employee summary information
- `pay_period_date`: Formatted pay period date
- `advance_previews`: Array of advance details
- `summary`: Total advances and amounts

### 3. processBulkPayroll()

Processes payroll for multiple employees in batch.

```php
public function processBulkPayroll(array $employeeIds, Carbon $payPeriodDate, bool $savePayroll = true): array
```

**Parameters:**
- `$employeeIds` (array): Array of employee IDs to process
- `$payPeriodDate` (Carbon): The pay period date
- `$savePayroll` (bool): Whether to save payroll records

**Returns:** Array containing:
- `success`: Boolean indicating overall success
- `processed`: Number of successfully processed employees
- `errors`: Number of errors encountered
- `results`: Array of individual processing results
- `error_details`: Detailed error information
- `summary`: Processing statistics

### 4. calculateAllocationPayrollForController()

Public wrapper for allocation payroll calculation (used by controllers).

```php
public function calculateAllocationPayrollForController(Employee $employee, EmployeeFundingAllocation $allocation, Carbon $payPeriodDate): array
```

### 5. calculateEmployeePayrollSummary()

Calculates comprehensive payroll summary for employee with all funding allocations.

```php
public function calculateEmployeePayrollSummary(Employee $employee, Carbon $payPeriodDate): array
```

**Returns:** Array containing:
- `employee`: Employee object
- `pay_period_date`: Formatted pay period date
- `allocation_calculations`: Individual allocation calculations
- `summary_totals`: Aggregated totals across all allocations
- `allocation_count`: Number of allocations processed

### 6. createInterSubsidiaryAdvanceIfNeeded()

Creates inter-subsidiary advance if the employee's subsidiary differs from funding subsidiary.

```php
public function createInterSubsidiaryAdvanceIfNeeded(Employee $employee, EmployeeFundingAllocation $allocation, Payroll $payroll, Carbon $payPeriodDate): ?InterSubsidiaryAdvance
```

### 7. getPayrollStatistics()

Retrieves payroll statistics for a specified period.

```php
public function getPayrollStatistics(Carbon $startDate, Carbon $endDate): array
```

## Private Calculation Methods

### Core Calculation Method

#### calculateAllocationPayroll()

The main private method that calculates payroll for a specific funding allocation. This method orchestrates all 13 payroll calculation items.

```php
private function calculateAllocationPayroll(Employee $employee, EmployeeFundingAllocation $allocation, Carbon $payPeriodDate): array
```

**Process Flow:**
1. Calculate pro-rated salary for probation transition
2. Calculate annual salary increase
3. Execute all 13 payroll calculation items
4. Return comprehensive calculation results

## Payroll Calculation Items

The service calculates 13 distinct payroll items as per Thai labor law and company policies:

### 1. Gross Salary
```php
private function calculateGrossSalary($employment): float
```
- Base position salary from employment record

### 2. Gross Salary Current Year by FTE
```php
private function calculateGrossSalaryCurrentYearByFTE($employment, EmployeeFundingAllocation $allocation, Carbon $payPeriodDate, float $adjustedGrossSalary): float
```

This is one of the most critical calculation methods as it determines the actual salary amount considering both Level of Effort (FTE) and pro-rating for employees who started mid-month.

#### **Method Logic Breakdown:**

**Step 1: Apply Level of Effort (FTE) Percentage**
```php
$grossSalaryByFTE = $adjustedGrossSalary * $allocation->fte;
```

**Step 2: Check for Mid-Month Start and Apply Pro-Rating**
```php
$startDate = Carbon::parse($employment->start_date);
if ($startDate->year == $payPeriodDate->year && $startDate->month == $payPeriodDate->month) {
    $daysInMonth = $payPeriodDate->copy()->endOfMonth()->day;
    $daysWorked = $startDate->diffInDays($payPeriodDate->copy()->endOfMonth()) + 1;
    $grossSalaryByFTE = ($grossSalaryByFTE / $daysInMonth) * $daysWorked;
}
```

#### **Mid-Month Start Detection Logic:**

The method determines if an employee started mid-month by checking:
- **Same Year**: `$startDate->year == $payPeriodDate->year`
- **Same Month**: `$startDate->month == $payPeriodDate->month`

When both conditions are true, the employee started within the payroll period being processed, requiring pro-rating.

#### **Practical Examples:**

**Example 1: Full Month Employee (No Pro-Rating)**
```
Employee Start Date: December 1, 2023
Processing Payroll: January 2024
Position Salary: ฿50,000
FTE: 0.8 (80%)
Annual Increase: ฿500

Calculation:
- Adjusted Gross Salary = ฿50,000 + ฿500 = ฿50,500
- Gross Salary by FTE = ฿50,500 × 0.8 = ฿40,400
- No pro-rating needed (different month/year)
- Final Amount = ฿40,400
```

**Example 2: Mid-Month Start (Pro-Rating Required)**
```
Employee Start Date: January 15, 2024
Processing Payroll: January 2024
Position Salary: ฿60,000
FTE: 1.0 (100%)
Annual Increase: ฿0

Calculation:
- Adjusted Gross Salary = ฿60,000 + ฿0 = ฿60,000
- Gross Salary by FTE = ฿60,000 × 1.0 = ฿60,000
- Pro-rating needed (same month/year):
  - Days in January = 31
  - Days worked = Jan 15 to Jan 31 = 17 days
  - Pro-rated salary = (฿60,000 ÷ 31) × 17 = ฿32,903.23
- Final Amount = ฿32,903.23
```

**Example 3: Part-Time Employee with Mid-Month Start**
```
Employee Start Date: March 10, 2024
Processing Payroll: March 2024
Position Salary: ฿45,000
FTE: 0.5 (50% - Part-time)
Annual Increase: ฿450

Calculation:
- Adjusted Gross Salary = ฿45,000 + ฿450 = ฿45,450
- Gross Salary by FTE = ฿45,450 × 0.5 = ฿22,725
- Pro-rating needed (same month/year):
  - Days in March = 31
  - Days worked = Mar 10 to Mar 31 = 22 days
  - Pro-rated salary = (฿22,725 ÷ 31) × 22 = ฿16,129.03
- Final Amount = ฿16,129.03
```

#### **Edge Cases and Scenarios:**

**Scenario 1: Employee Started on Last Day of Month**
```
Start Date: January 31, 2024
Processing: January 2024
Days worked = 1 day
Pro-rated salary = (Full Salary ÷ 31) × 1
```

**Scenario 2: Employee Started on First Day of Month**
```
Start Date: January 1, 2024
Processing: January 2024
Days worked = 31 days
Pro-rated salary = Full Salary (no reduction)
```

**Scenario 3: Leap Year Consideration**
```
Start Date: February 15, 2024 (Leap Year)
Processing: February 2024
Days in February = 29 (not 28)
Days worked = Feb 15 to Feb 29 = 15 days
Pro-rated salary = (Full Salary ÷ 29) × 15
```

#### **Key Features:**
- **FTE Integration**: Seamlessly applies Level of Effort percentages
- **Automatic Pro-Rating**: Detects and handles mid-month starts
- **Precision**: Uses exact day calculations for fairness
- **Leap Year Aware**: Correctly handles February in leap years
- **Rounding**: Returns rounded values to 2 decimal places

### 3. Compensation/Refund
```php
private function calculateCompensationRefundAmount($employment, Carbon $payPeriodDate, float $monthlySalary): float
```

**Current Implementation:**
This method currently returns `0.0` as pro-rating adjustments are handled directly in the `calculateGrossSalaryCurrentYearByFTE` method to prevent double-calculation.

**Purpose:**
Originally designed to handle salary adjustments for:
- Mid-month employment start/end dates
- Compensation for partial work periods
- Refunds for overpayments

**Example Scenarios:**
```
Scenario 1: No Adjustment Needed
Employee: Full month worker
Compensation/Refund = ฿0.00

Scenario 2: Future Enhancement Possibility
Employee: Had unpaid leave mid-month
Potential Refund = (Daily Salary × Unpaid Days) × -1
```

**Design Decision:**
To maintain calculation clarity and prevent confusion, all pro-rating logic is centralized in the FTE calculation method.

### 4. 13th Month Salary
```php
private function calculateThirteenthMonthSalaryAmount(Employee $employee, $employment, Carbon $payPeriodDate, float $monthlySalary): float
```

**Thai Labor Law Requirement:**
Employees with 6+ months of continuous service are entitled to a 13th month salary bonus equivalent to 1/12 of their monthly salary.

**Calculation Logic:**
```php
$startDate = Carbon::parse($employment->start_date);
$serviceMonths = $startDate->diffInMonths($payPeriodDate);

if ($serviceMonths >= 6) {
    return round($monthlySalary / 12, 2);
}
return 0.0;
```

**Practical Examples:**

**Example 1: Eligible Employee (6+ Months Service)**
```
Employee Start Date: January 1, 2024
Processing Payroll: August 2024
Monthly Salary: ฿48,000
Service Months: 8 months

Calculation:
- Service Duration = 8 months (≥ 6 months) ✅ Eligible
- 13th Month Salary = ฿48,000 ÷ 12 = ฿4,000.00
```

**Example 2: Ineligible Employee (Less than 6 Months)**
```
Employee Start Date: May 1, 2024
Processing Payroll: August 2024
Monthly Salary: ฿35,000
Service Months: 4 months

Calculation:
- Service Duration = 4 months (< 6 months) ❌ Not Eligible
- 13th Month Salary = ฿0.00
```

**Example 3: Exactly 6 Months Service**
```
Employee Start Date: February 1, 2024
Processing Payroll: August 2024
Monthly Salary: ฿52,000
Service Months: 6 months

Calculation:
- Service Duration = 6 months (= 6 months) ✅ Eligible
- 13th Month Salary = ฿52,000 ÷ 12 = ฿4,333.33
```

**Example 4: Pro-Rated Salary Employee**
```
Employee Start Date: March 15, 2024 (Mid-month start)
Processing Payroll: October 2024
Monthly Salary (Pro-rated): ฿30,000
Service Months: 7 months

Calculation:
- Service Duration = 7 months (≥ 6 months) ✅ Eligible
- 13th Month Salary = ฿30,000 ÷ 12 = ฿2,500.00
Note: Uses the actual monthly salary amount (including pro-rating)
```

**Key Features:**
- **Automatic Eligibility Check**: Only calculates for employees with 6+ months service
- **Monthly Accrual**: Represents 1/12 of monthly earnings
- **Fair Distribution**: Applied monthly rather than lump sum at year-end
- **Pro-Rating Compatible**: Works with adjusted monthly salaries

### 5. PVD/Saving Fund (Employee Contribution)
```php
private function calculatePVDSavingFund(Employee $employee, float $monthlySalary, $employment): array
```

**Company Policy:**
Different employee types contribute to different retirement funds based on their status and probation completion.

**Calculation Logic:**
```php
$probationPassDate = $employment->probation_pass_date ? Carbon::parse($employment->probation_pass_date) : null;
$hasPassed = $probationPassDate && Carbon::now()->gte($probationPassDate);

if ($hasPassed) {
    if ($employee->status === 'Local ID') {
        return ['pvd_employee' => round($monthlySalary * 0.075, 2), 'saving_fund' => 0.0];
    } elseif ($employee->status === 'Local non ID') {
        return ['pvd_employee' => 0.0, 'saving_fund' => round($monthlySalary * 0.075, 2)];
    }
}
return ['pvd_employee' => 0.0, 'saving_fund' => 0.0];
```

**Practical Examples:**

**Example 1: Local ID Employee (Post-Probation)**
```
Employee Status: Local ID
Monthly Salary: ฿40,000
Probation Pass Date: March 1, 2024
Processing Date: April 2024
Probation Status: Passed ✅

Calculation:
- PVD Employee = ฿40,000 × 7.5% = ฿3,000.00
- Saving Fund = ฿0.00
- Total Deduction = ฿3,000.00
```

**Example 2: Local non ID Employee (Post-Probation)**
```
Employee Status: Local non ID
Monthly Salary: ฿35,000
Probation Pass Date: February 15, 2024
Processing Date: April 2024
Probation Status: Passed ✅

Calculation:
- PVD Employee = ฿0.00
- Saving Fund = ฿35,000 × 7.5% = ฿2,625.00
- Total Deduction = ฿2,625.00
```

**Example 3: Employee Still in Probation**
```
Employee Status: Local ID
Monthly Salary: ฿45,000
Probation Pass Date: June 1, 2024
Processing Date: April 2024
Probation Status: Not Passed Yet ❌

Calculation:
- PVD Employee = ฿0.00
- Saving Fund = ฿0.00
- Total Deduction = ฿0.00
- Reason: Still in probation period
```

**Example 4: Expat Employee**
```
Employee Status: Expat
Monthly Salary: ฿80,000
Probation Pass Date: January 1, 2024
Processing Date: April 2024
Probation Status: Passed ✅

Calculation:
- PVD Employee = ฿0.00
- Saving Fund = ฿0.00
- Total Deduction = ฿0.00
- Reason: Expat employees not eligible for PVD/Saving Fund
```

**Example 5: High Salary Local ID Employee**
```
Employee Status: Local ID
Monthly Salary: ฿120,000
Probation Pass Date: January 1, 2024
Processing Date: April 2024
Probation Status: Passed ✅

Calculation:
- PVD Employee = ฿120,000 × 7.5% = ฿9,000.00
- Saving Fund = ฿0.00
- Total Deduction = ฿9,000.00
- Note: No maximum cap on PVD contributions
```

**Key Features:**
- **Status-Based**: Different funds for Local ID vs Local non ID employees
- **Probation-Dependent**: Only applies after probation completion
- **Employee-Only**: No employer matching contributions
- **7.5% Rate**: Fixed percentage across all eligible employees
- **Mutually Exclusive**: Employee contributes to either PVD OR Saving Fund, never both

### 6. Employer Social Security
```php
private function calculateEmployerSocialSecurity(float $monthlySalary): float
```

**Thai Social Security Law:**
Employers must contribute 5% of employee's monthly salary to the social security fund, capped at ฿750 per month.

**Calculation Logic:**
```php
$employerContribution = min($monthlySalary * 0.05, 750.0);
return round($employerContribution, 2);
```

**Practical Examples:**

**Example 1: Low Salary Employee (Under Cap)**
```
Monthly Salary: ฿12,000
Calculation: ฿12,000 × 5% = ฿600
Cap Check: ฿600 < ฿750 ✅
Employer Social Security = ฿600.00
```

**Example 2: Medium Salary Employee (At Cap)**
```
Monthly Salary: ฿15,000
Calculation: ฿15,000 × 5% = ฿750
Cap Check: ฿750 = ฿750 ✅
Employer Social Security = ฿750.00
```

**Example 3: High Salary Employee (Above Cap)**
```
Monthly Salary: ฿50,000
Calculation: ฿50,000 × 5% = ฿2,500
Cap Check: ฿2,500 > ฿750 ❌ Apply Cap
Employer Social Security = ฿750.00
```

**Example 4: Very High Salary Employee**
```
Monthly Salary: ฿100,000
Calculation: ฿100,000 × 5% = ฿5,000
Cap Check: ฿5,000 > ฿750 ❌ Apply Cap
Employer Social Security = ฿750.00
```

### 7. Employee Social Security
```php
private function calculateEmployeeSocialSecurity(float $monthlySalary): float
```

**Thai Social Security Law:**
Employees contribute 5% of their monthly salary to the social security fund, capped at ฿750 per month (same as employer).

**Calculation Logic:**
```php
$employeeContribution = min($monthlySalary * 0.05, 750.0);
return round($employeeContribution, 2);
```

**Practical Examples:**

**Example 1: Entry Level Employee**
```
Monthly Salary: ฿8,000
Calculation: ฿8,000 × 5% = ฿400
Cap Check: ฿400 < ฿750 ✅
Employee Social Security = ฿400.00
```

**Example 2: Mid-Level Employee (Exactly at Cap Threshold)**
```
Monthly Salary: ฿15,000
Calculation: ฿15,000 × 5% = ฿750
Cap Check: ฿750 = ฿750 ✅
Employee Social Security = ฿750.00
```

**Example 3: Senior Employee (Above Cap)**
```
Monthly Salary: ฿80,000
Calculation: ฿80,000 × 5% = ฿4,000
Cap Check: ฿4,000 > ฿750 ❌ Apply Cap
Employee Social Security = ฿750.00
```

**Key Features (Both Employer & Employee):**
- **Fixed Rate**: 5% for both employer and employee
- **Equal Cap**: ฿750 maximum for both parties
- **Mandatory**: Required by Thai law for all employees
- **Symmetrical**: Employer and employee contribute equally (up to cap)

### 8. Health Welfare Employer
```php
private function calculateHealthWelfareEmployer(Employee $employee, float $monthlySalary): float
```

**Company-Specific Policy:**
Health welfare employer contributions vary by subsidiary and employee status.

**Calculation Logic:**
```php
if ($employee->subsidiary === 'SMRU' && 
    ($employee->status === 'Non-Thai ID' || $employee->status === 'Expat')) {
    // Calculate based on salary tiers (same as employee contribution)
    if ($monthlySalary > 15000) return 150;
    elseif ($monthlySalary > 5000) return 100;
    else return 60;
} elseif ($employee->subsidiary === 'BHF') {
    return 0.0; // BHF doesn't pay employer health welfare
}
return 0.0;
```

**Practical Examples:**

**Example 1: SMRU Non-Thai ID Employee (High Salary)**
```
Subsidiary: SMRU
Employee Status: Non-Thai ID
Monthly Salary: ฿45,000

Calculation:
- Salary > ฿15,000 ✅
- Employer Health Welfare = ฿150.00
- Reason: SMRU covers Non-Thai ID employees
```

**Example 2: SMRU Expat Employee (Medium Salary)**
```
Subsidiary: SMRU
Employee Status: Expat
Monthly Salary: ฿12,000

Calculation:
- ฿5,000 < Salary ≤ ฿15,000 ✅
- Employer Health Welfare = ฿100.00
- Reason: SMRU covers Expat employees
```

**Example 3: SMRU Local ID Employee**
```
Subsidiary: SMRU
Employee Status: Local ID
Monthly Salary: ฿35,000

Calculation:
- Employee Status = Local ID ❌
- Employer Health Welfare = ฿0.00
- Reason: SMRU only covers Non-Thai ID and Expat
```

**Example 4: BHF Non-Thai ID Employee**
```
Subsidiary: BHF
Employee Status: Non-Thai ID
Monthly Salary: ฿50,000

Calculation:
- Subsidiary = BHF ❌
- Employer Health Welfare = ฿0.00
- Reason: BHF subsidiary has no employer health welfare policy
```

**Example 5: SMRU Expat Employee (Low Salary)**
```
Subsidiary: SMRU
Employee Status: Expat
Monthly Salary: ฿4,500

Calculation:
- Salary ≤ ฿5,000 ✅
- Employer Health Welfare = ฿60.00
- Reason: Minimum tier for eligible employees
```

### 9. Health Welfare Employee
```php
private function calculateHealthWelfareEmployee(float $monthlySalary): float
```

**Universal Employee Contribution:**
All employees contribute to health welfare based on salary tiers, regardless of subsidiary or status.

**Calculation Logic:**
```php
if ($monthlySalary > 15000) return 150.0;
elseif ($monthlySalary > 5000) return 100.0;
else return 60.0;
```

**Practical Examples:**

**Example 1: High Salary Employee**
```
Monthly Salary: ฿80,000
Tier: > ฿15,000
Employee Health Welfare = ฿150.00
```

**Example 2: Medium Salary Employee**
```
Monthly Salary: ฿12,000
Tier: ฿5,000 < Salary ≤ ฿15,000
Employee Health Welfare = ฿100.00
```

**Example 3: Low Salary Employee**
```
Monthly Salary: ฿4,000
Tier: ≤ ฿5,000
Employee Health Welfare = ฿60.00
```

**Example 4: Exactly at Tier Boundary (Upper)**
```
Monthly Salary: ฿15,000
Tier: ฿5,000 < Salary ≤ ฿15,000 (not > ฿15,000)
Employee Health Welfare = ฿100.00
```

**Example 5: Exactly at Tier Boundary (Lower)**
```
Monthly Salary: ฿5,000
Tier: ≤ ฿5,000 (not > ฿5,000)
Employee Health Welfare = ฿60.00
```

**Key Features:**
- **Employee**: Universal contribution for all employees
- **Employer**: Selective contribution based on subsidiary policy and employee status
- **Tiered System**: Three salary-based contribution levels
- **Fixed Amounts**: Not percentage-based like social security

### 10. Income Tax
```php
private function calculateIncomeTax(Employee $employee, float $grossSalaryByFTE, $employment, Carbon $payPeriodDate): float
```

**Thai Income Tax Law:**
Complex progressive tax calculation considering personal allowances, family status, and employment duration.

**Calculation Logic:**
```php
$employeeData = [
    'has_spouse' => $employee->has_spouse,
    'children' => $employee->employeeChildren->count(),
    'eligible_parents' => $employee->eligible_parents_count,
    'employee_status' => $employee->status,
    'months_working_this_year' => $this->calculateMonthsWorkingThisYear($employment, $payPeriodDate),
];

$taxCalculation = $this->taxService->calculateEmployeeTax($grossSalaryByFTE, $employeeData);
return round($taxCalculation['monthly_tax_amount'], 2);
```

**Practical Examples:**

**Example 1: Single Employee (No Dependents)**
```
Employee Status: Local ID
Monthly Salary: ฿45,000
Marital Status: Single
Children: 0
Eligible Parents: 0
Months Working This Year: 12

Tax Calculation Process:
1. Annual Salary = ฿45,000 × 12 = ฿540,000
2. Personal Allowance = ฿60,000
3. Taxable Income = ฿540,000 - ฿60,000 = ฿480,000
4. Annual Tax = Progressive tax brackets applied
5. Monthly Tax = Annual Tax ÷ 12
Result: Income Tax = ฿1,833.33 (example)
```

**Example 2: Married Employee with Children**
```
Employee Status: Local ID
Monthly Salary: ฿60,000
Marital Status: Married
Children: 2
Eligible Parents: 1
Months Working This Year: 12

Tax Calculation Process:
1. Annual Salary = ฿60,000 × 12 = ฿720,000
2. Personal Allowance = ฿60,000
3. Spouse Allowance = ฿60,000
4. Child Allowance = 2 × ฿30,000 = ฿60,000
5. Parent Allowance = 1 × ฿30,000 = ฿30,000
6. Total Allowances = ฿210,000
7. Taxable Income = ฿720,000 - ฿210,000 = ฿510,000
8. Annual Tax = Progressive tax brackets applied
9. Monthly Tax = Annual Tax ÷ 12
Result: Income Tax = ฿2,125.00 (example)
```

**Example 3: New Employee (Mid-Year Start)**
```
Employee Status: Local ID
Monthly Salary: ฿50,000
Start Date: July 1, 2024
Processing: December 2024
Months Working This Year: 6
Marital Status: Single

Tax Calculation Process:
1. Annual Salary = ฿50,000 × 6 = ฿300,000 (pro-rated)
2. Personal Allowance = ฿60,000
3. Taxable Income = ฿300,000 - ฿60,000 = ฿240,000
4. Annual Tax = Lower tax bracket applied
5. Monthly Tax = Annual Tax ÷ 6 (working months)
Result: Income Tax = ฿500.00 (example)
```

**Example 4: High Salary Employee**
```
Employee Status: Expat
Monthly Salary: ฿150,000
Marital Status: Married
Children: 1
Months Working This Year: 12

Tax Calculation Process:
1. Annual Salary = ฿150,000 × 12 = ฿1,800,000
2. Personal Allowance = ฿60,000
3. Spouse Allowance = ฿60,000
4. Child Allowance = ฿30,000
5. Total Allowances = ฿150,000
6. Taxable Income = ฿1,800,000 - ฿150,000 = ฿1,650,000
7. Annual Tax = Higher tax brackets applied
8. Monthly Tax = Annual Tax ÷ 12
Result: Income Tax = ฿15,416.67 (example)
```

**Example 5: Low Salary Employee (Below Tax Threshold)**
```
Employee Status: Local non ID
Monthly Salary: ฿18,000
Marital Status: Single
Children: 0
Months Working This Year: 12

Tax Calculation Process:
1. Annual Salary = ฿18,000 × 12 = ฿216,000
2. Personal Allowance = ฿60,000
3. Taxable Income = ฿216,000 - ฿60,000 = ฿156,000
4. Tax Threshold Check = ฿156,000 < ฿150,000 ❌
5. Annual Tax = Minimal tax applied
6. Monthly Tax = Annual Tax ÷ 12
Result: Income Tax = ฿25.00 (example)
```

**Key Features:**
- **Progressive Tax System**: Higher income = higher tax rate
- **Family Allowances**: Reduces taxable income for dependents
- **Pro-Rating**: Adjusts for partial year employment
- **Status Consideration**: Different rules for different employee types
- **Integration**: Uses dedicated `TaxCalculationService` for accuracy

### 11. Net Salary
```php
private function calculateNetSalary(
    float $grossSalaryCurrentYearByFTE,
    float $compensationRefund,
    float $thirteenthMonthSalary,
    float $pvdSavingEmployee,
    float $employeeSocialSecurity,
    float $healthWelfareEmployee,
    float $incomeTax
): float
```

**Take-Home Pay Calculation:**
The actual amount the employee receives after all deductions.

**Formula:**
```
Net Salary = Total Income - Total Deductions

Where:
Total Income = Gross Salary by FTE + Compensation/Refund + 13th Month Salary
Total Deductions = PVD/Saving + Employee Social Security + Health Welfare Employee + Income Tax
```

**Practical Examples:**

**Example 1: Local ID Employee (Standard Case)**
```
Gross Salary by FTE: ฿45,000
Compensation/Refund: ฿0
13th Month Salary: ฿3,750
PVD Employee: ฿3,375
Employee Social Security: ฿750
Health Welfare Employee: ฿150
Income Tax: ฿1,833

Calculation:
Total Income = ฿45,000 + ฿0 + ฿3,750 = ฿48,750
Total Deductions = ฿3,375 + ฿750 + ฿150 + ฿1,833 = ฿6,108
Net Salary = ฿48,750 - ฿6,108 = ฿42,642
```

**Example 2: High Salary Employee (Capped Deductions)**
```
Gross Salary by FTE: ฿100,000
Compensation/Refund: ฿0
13th Month Salary: ฿8,333
PVD Employee: ฿7,500
Employee Social Security: ฿750 (capped)
Health Welfare Employee: ฿150
Income Tax: ฿12,500

Calculation:
Total Income = ฿100,000 + ฿0 + ฿8,333 = ฿108,333
Total Deductions = ฿7,500 + ฿750 + ฿150 + ฿12,500 = ฿20,900
Net Salary = ฿108,333 - ฿20,900 = ฿87,433
```

### 12. Total Salary (Total Cost to Company)
```php
private function calculateTotalSalary(
    float $grossSalaryCurrentYearByFTE,
    float $compensationRefund,
    float $thirteenthMonthSalary,
    float $employerSocialSecurity,
    float $healthWelfareEmployer
): float
```

**Employer's Total Cost:**
The complete cost to the company for employing this person.

**Formula:**
```
Total Salary = Employee Benefits + Employer Contributions

Where:
Employee Benefits = Gross Salary by FTE + Compensation/Refund + 13th Month Salary
Employer Contributions = Employer Social Security + Health Welfare Employer
```

**Practical Examples:**

**Example 1: SMRU Non-Thai ID Employee**
```
Gross Salary by FTE: ฿50,000
Compensation/Refund: ฿0
13th Month Salary: ฿4,167
Employer Social Security: ฿750
Health Welfare Employer: ฿150

Calculation:
Employee Benefits = ฿50,000 + ฿0 + ฿4,167 = ฿54,167
Employer Contributions = ฿750 + ฿150 = ฿900
Total Salary = ฿54,167 + ฿900 = ฿55,067
```

**Example 2: BHF Local ID Employee**
```
Gross Salary by FTE: ฿40,000
Compensation/Refund: ฿0
13th Month Salary: ฿3,333
Employer Social Security: ฿750
Health Welfare Employer: ฿0 (BHF policy)

Calculation:
Employee Benefits = ฿40,000 + ฿0 + ฿3,333 = ฿43,333
Employer Contributions = ฿750 + ฿0 = ฿750
Total Salary = ฿43,333 + ฿750 = ฿44,083
```

### 13. Total PVD/Saving Fund
```php
private function calculateTotalPVDSaving(float $pvdSavingEmployee): float
```

**Theoretical Total Fund Value:**
Represents the total fund value if employer matched employee contributions (for reporting purposes).

**Formula:**
```
Total PVD/Saving Fund = Employee Contribution × 2
```

**Note:** This is a theoretical calculation since the company doesn't actually provide employer matching for PVD/Saving funds.

**Practical Examples:**

**Example 1: Local ID Employee with PVD**
```
Monthly Salary: ฿60,000
PVD Employee Contribution: ฿4,500 (7.5%)
Total PVD/Saving Fund = ฿4,500 × 2 = ฿9,000
```

**Example 2: Local non ID Employee with Saving Fund**
```
Monthly Salary: ฿35,000
Saving Fund Employee Contribution: ฿2,625 (7.5%)
Total PVD/Saving Fund = ฿2,625 × 2 = ฿5,250
```

**Example 3: Employee in Probation**
```
Monthly Salary: ฿40,000
PVD/Saving Employee Contribution: ฿0 (probation)
Total PVD/Saving Fund = ฿0 × 2 = ฿0
```

**Key Features:**
- **Net Salary**: Employee's actual take-home pay
- **Total Salary**: Company's complete employment cost
- **Total PVD/Saving**: Theoretical fund value for reporting consistency

## Helper Methods

### Probation and Salary Calculations

#### calculateProRatedSalaryForProbation()
Handles salary transitions when employees pass probation mid-month.

#### calculateAnnualSalaryIncrease()
Calculates 1% annual increase for employees with 365+ working days (excluding weekends).

#### calculateMonthsWorkingThisYear()
Determines months worked in current tax year for tax calculations.

### Funding Source Methods

#### getFundingGrant()
Retrieves the grant object from funding allocation (grant or org_funded).

#### getFundingSubsidiary()
Determines the subsidiary providing funding for the allocation.

#### getFundingSourceName()
Gets display name for the funding source.

## Inter-Subsidiary Advances

The service automatically creates inter-subsidiary advances when:
1. Employee's subsidiary differs from funding subsidiary
2. A hub grant exists for the funding subsidiary
3. Payroll processing is successful

**Process:**
1. Identify funding subsidiary from allocation
2. Compare with employee's subsidiary
3. Find appropriate hub grant for cross-subsidiary transfer
4. Create advance record with proper audit trail

## Database Operations

### Payroll Record Creation
```php
private function createPayrollRecord(Employment $employment, EmployeeFundingAllocation $allocation, array $payrollData, Carbon $payPeriodDate): Payroll
```

Creates comprehensive payroll records with all calculated fields mapped to database schema.

### Transaction Management
- Uses database transactions for data integrity
- Automatic rollback on errors
- Comprehensive error logging

## Usage Examples

### Single Employee Payroll Processing
```php
$payrollService = new PayrollService();
$employee = Employee::find(1);
$payPeriodDate = Carbon::now()->startOfMonth();

$result = $payrollService->processEmployeePayroll($employee, $payPeriodDate);
```

### Bulk Payroll Processing
```php
$payrollService = new PayrollService();
$employeeIds = [1, 2, 3, 4, 5];
$payPeriodDate = Carbon::now()->startOfMonth();

$results = $payrollService->processBulkPayroll($employeeIds, $payPeriodDate);
```

### Preview Inter-Subsidiary Advances
```php
$payrollService = new PayrollService();
$employee = Employee::find(1);
$payPeriodDate = Carbon::now()->startOfMonth();

$preview = $payrollService->previewInterSubsidiaryAdvances($employee, $payPeriodDate);
```

## Dependencies

### Required Models
- `Employee`: Employee information and relationships
- `Employment`: Employment details and salary information
- `EmployeeFundingAllocation`: Funding allocation and FTE details
- `Payroll`: Payroll record storage
- `InterSubsidiaryAdvance`: Cross-subsidiary advance tracking

### Required Services
- `TaxCalculationService`: Income tax calculations

### Required Packages
- `Carbon`: Date/time manipulation
- `Laravel Framework`: Database, logging, authentication

## Error Handling

The service implements comprehensive error handling:
- Database transaction rollbacks
- Detailed error logging
- Exception propagation with context
- Graceful handling of missing relationships

## Performance Considerations

- Eager loading of relationships to prevent N+1 queries
- Efficient bulk processing capabilities
- Optimized calculation methods
- Proper indexing requirements for related tables

## Security Features

- Authentication-aware advance creation
- Audit trail for all operations
- Input validation and sanitization
- Proper authorization checks (handled at controller level)

## Compliance

The service ensures compliance with:
- Thai labor law requirements
- Social security regulations
- Tax calculation standards
- Company-specific policies for different subsidiaries (SMRU, BHF)

## 📊 Complete Payroll Calculation Example

### Comprehensive Example: SMRU Non-Thai ID Employee

Let's walk through a complete payroll calculation for a typical employee:

```
Employee Profile:
- Name: John Smith
- Status: Non-Thai ID
- Subsidiary: SMRU
- Start Date: January 1, 2024
- Processing Date: August 2024
- Position Salary: ฿55,000
- FTE: 0.8 (80% - Part-time)
- Probation Pass Date: March 1, 2024
- Marital Status: Married
- Children: 1
- Eligible Parents: 0
```

**Step-by-Step Calculation:**

**1. Gross Salary**
```
Base Position Salary = ฿55,000
```

**2. Gross Salary Current Year by FTE**
```
Annual Increase = ฿550 (1% after 1 year service)
Adjusted Gross Salary = ฿55,000 + ฿550 = ฿55,550
Gross Salary by FTE = ฿55,550 × 0.8 = ฿44,440
No pro-rating needed (full month employee)
Final Amount = ฿44,440
```

**3. Compensation/Refund**
```
Compensation/Refund = ฿0 (handled in FTE calculation)
```

**4. 13th Month Salary**
```
Service Months = 8 months (≥ 6 months) ✅ Eligible
13th Month Salary = ฿44,440 ÷ 12 = ฿3,703.33
```

**5. PVD/Saving Fund (Employee)**
```
Employee Status = Non-Thai ID (not eligible for PVD/Saving)
PVD Employee = ฿0
Saving Fund = ฿0
```

**6. Employer Social Security**
```
Calculation = ฿44,440 × 5% = ฿2,222
Cap Check = ฿2,222 > ฿750 ❌ Apply Cap
Employer Social Security = ฿750
```

**7. Employee Social Security**
```
Calculation = ฿44,440 × 5% = ฿2,222
Cap Check = ฿2,222 > ฿750 ❌ Apply Cap
Employee Social Security = ฿750
```

**8. Health Welfare Employer**
```
Subsidiary = SMRU ✅
Employee Status = Non-Thai ID ✅
Salary = ฿44,440 > ฿15,000 ✅
Health Welfare Employer = ฿150
```

**9. Health Welfare Employee**
```
Salary = ฿44,440 > ฿15,000 ✅
Health Welfare Employee = ฿150
```

**10. Income Tax**
```
Annual Salary = ฿44,440 × 12 = ฿533,280
Personal Allowance = ฿60,000
Spouse Allowance = ฿60,000
Child Allowance = 1 × ฿30,000 = ฿30,000
Total Allowances = ฿150,000
Taxable Income = ฿533,280 - ฿150,000 = ฿383,280
Monthly Income Tax = ฿1,597.50 (calculated via TaxCalculationService)
```

**11. Net Salary**
```
Total Income = ฿44,440 + ฿0 + ฿3,703.33 = ฿48,143.33
Total Deductions = ฿0 + ฿750 + ฿150 + ฿1,597.50 = ฿2,497.50
Net Salary = ฿48,143.33 - ฿2,497.50 = ฿45,645.83
```

**12. Total Salary (Cost to Company)**
```
Employee Benefits = ฿44,440 + ฿0 + ฿3,703.33 = ฿48,143.33
Employer Contributions = ฿750 + ฿150 = ฿900
Total Salary = ฿48,143.33 + ฿900 = ฿49,043.33
```

**13. Total PVD/Saving Fund**
```
PVD/Saving Employee = ฿0
Total PVD/Saving Fund = ฿0 × 2 = ฿0
```

**Final Summary:**
- **Employee Receives**: ฿45,645.83 (Net Salary)
- **Company Pays**: ฿49,043.33 (Total Cost)
- **Government Receives**: ฿3,397.50 (Taxes + Social Security + Health Welfare)

---

## 🔧 Implementation Best Practices

### For Developers Working with PayrollService:

1. **Always Use Database Transactions**
   ```php
   DB::beginTransaction();
   try {
       $result = $payrollService->processEmployeePayroll($employee, $payPeriodDate);
       DB::commit();
   } catch (\Exception $e) {
       DB::rollBack();
       throw $e;
   }
   ```

2. **Load Required Relationships**
   ```php
   $employee->load([
       'employment',
       'employeeFundingAllocations',
       'employeeChildren'
   ]);
   ```

3. **Handle Edge Cases**
   - Employees without employment records
   - Employees without funding allocations
   - Mid-month probation transitions
   - Leap year calculations

4. **Validate Input Data**
   - Ensure pay period dates are valid
   - Verify employee has active employment
   - Check funding allocation validity

5. **Monitor Performance**
   - Use eager loading to prevent N+1 queries
   - Consider caching for bulk operations
   - Log processing times for optimization

---

*This documentation covers the complete functionality of the PayrollService class as of the current implementation. The detailed examples and scenarios provided should help developers understand the complex business logic and maintain the system effectively.*
