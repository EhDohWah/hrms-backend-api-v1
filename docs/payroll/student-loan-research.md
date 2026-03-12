# Study Loan Deduction — Research & Placement Analysis

## 1. Executive Summary

This document analyzes where the `study_loan` deduction should live in the HRMS database architecture, based on a comprehensive review of the payroll system's migrations, models, services, controllers, and calculation pipeline.

**Recommendation: Add `study_loan` as a decimal field on the `employments` table** — alongside the existing benefit flags (`pvd`, `saving_fund`, `health_welfare`). This follows the established pattern, requires minimal code changes, and naturally integrates with the payroll calculation pipeline.

---

## 2. Current Payroll Architecture Overview

### 2.1 Where Deduction Values Come From

The payroll system draws deduction data from three sources:

| Source | What It Stores | Examples |
|--------|---------------|----------|
| **`employments` table** | Per-employee **opt-in flags** (boolean) | `pvd`, `saving_fund`, `health_welfare` |
| **`benefit_settings` table** | Organization-wide **rates & caps** | `ssf_employee_rate` (5%), `pvd_employee_rate` (7.5%), `ssf_max_monthly` (875) |
| **`employees` table** | Personal data for **tax allowances** | `eligible_parents_count`, `marital_status`, `spouse_name` |

The pattern is: **Employment flags say IF the deduction applies → Benefit settings say HOW MUCH**.

### 2.2 Current Deductions in PayrollService (13-Item Pipeline)

The `PayrollService::calculateAllocationPayroll()` method (lines 937-1196) runs this pipeline:

```
 1. Gross Salary                    ← Employment.getSalaryAmountForDate()
 2. Gross Salary by FTE             ← salary × FTE × (days/30)
 3. Retroactive Adjustment          ← deferred salary from prev month
 4. 13th Month Salary               ← December only, YTD-based
 5. PVD / Saving Fund (employee)    ← if employment.pvd or employment.saving_fund
 6. Employer Social Security        ← 5% of salary, capped at ฿875/month
 7. Employee Social Security        ← same calculation as employer
 8. Health Welfare (employer)       ← tiered by salary, non-Thai only
 9. Health Welfare (employee)       ← tiered by salary + nationality
10. Income Tax                      ← ACM method via TaxCalculationService
11. Net Salary                      ← total_income - total_deductions
12. Total Salary (CTC)              ← income + employer contributions
13. Total PVD/Saving Fund           ← employee + employer combined
```

### 2.3 The Critical Line — Total Deductions

At line 1029 of `PayrollService.php`:

```php
$totalDeductions = $pvdSavingEmployee + $employeeSocialSecurity + $healthWelfareEmployee + $incomeTax;
```

And net salary at line 1141:

```php
$netSalary = max(0, round($totalIncome - $totalDeductions));
```

A new `study_loan` deduction would be added to `$totalDeductions`.

### 2.4 Payroll Model Fields (All Encrypted)

The `payrolls` table stores **calculated results** for each pay period per allocation:

```
gross_salary, gross_salary_by_FTE, retroactive_adjustment, thirteen_month_salary,
pvd, saving_fund, employer_social_security, employee_social_security,
employer_health_welfare, employee_health_welfare, tax, salary_increase,
net_salary, total_salary, total_pvd, total_saving_fund,
total_income, employer_contribution, total_deduction, notes
```

All monetary fields use Laravel's `encrypted` cast (stored as TEXT in SQL Server).

### 2.5 Payslip Template

The SMRU payslip (`resources/views/pdf/smru-payslip.blade.php`) has a **DEDUCTIONS** column with fixed rows:

```
Provident fund: staff 7.5%     → $payroll->pvd
Social security: staff 5%      → $payroll->employee_social_security
Health Welfare: staff           → $payroll->employee_health_welfare
Tax                             → $payroll->tax
Provident fund SMRU 7.5%       → $payroll->pvd (employer)
Social security SMRU 5%        → $payroll->employer_social_security
Health Welfare: SMRU            → $payroll->employer_health_welfare
```

There are currently **no empty rows** for additional deductions — the payslip template will need a new row for study loan.

---

## 3. Nature of Study Loan Deduction

Before deciding placement, understanding the characteristics:

| Characteristic | Study Loan | PVD/Saving Fund (existing) |
|---------------|------------|---------------------------|
| **Varies per employee?** | Yes — different loan amounts | No — same rate for all (7.5%) |
| **Value type** | Fixed amount (e.g., ฿3,000/month) | Percentage of salary |
| **Has start/end?** | Yes — loan is repaid over time | No — ongoing while employed |
| **Affects tax?** | No — loan repayment is not tax-deductible | Yes — PVD is tax-deductible |
| **Employer contribution?** | No — purely employee obligation | Yes — employer matches 7.5% |
| **FTE-proportional?** | Likely no — fixed amount regardless of FTE | Yes — calculated on FTE-adjusted salary |
| **Per allocation?** | No — deducted once from total pay | Yes — calculated per allocation |
| **Changed frequently?** | Rarely — set when loan starts, cleared when repaid | Rarely — toggled on/off |

**Key difference from existing deductions**: Study loan is a **fixed amount** (not a percentage), doesn't need FTE scaling, and isn't tax-deductible.

---

## 4. Option Analysis

### Option A: Add to `employments` table (Recommended)

Add a `study_loan` decimal field alongside the existing benefit flags.

**Schema change:**
```php
// Migration
$table->decimal('study_loan', 10, 2)->nullable()->default(0);
```

**Employment model:**
```php
// Add to $fillable
'study_loan',

// Add to $casts
'study_loan' => 'decimal:2',
```

**How it integrates with payroll:**

In `PayrollService::calculateAllocationPayroll()`, the deduction is read from the employment record (which is already loaded) and applied once to the tax-bearing allocation:

```php
// Study loan: fixed amount, deducted from the tax-bearing allocation only
$studyLoan = 0.0;
if ($isTaxAllocation && (float) $employment->study_loan > 0) {
    $studyLoan = (float) $employment->study_loan;
}

$totalDeductions = $pvdSavingEmployee + $employeeSocialSecurity
    + $healthWelfareEmployee + $incomeTax + $studyLoan;
```

**Pros:**
- Follows the established pattern (employment stores per-employee financial data)
- Zero additional queries — employment is already loaded in the calculation pipeline
- Admin can set/clear the value on the employment form (same place as PVD/Saving Fund toggles)
- Simple migration, simple code change
- The value persists across pay periods until manually changed
- Employment history already tracks all changes to employment fields

**Cons:**
- No automatic tracking of loan balance or repayment schedule
- If the employee has multiple employments (rare), the loan is per-employment
- No start/end date tracking on the deduction itself

**Files to modify (6):**
1. New migration: `add_study_loan_to_employments_table`
2. `app/Models/Employment.php` — add to fillable + casts
3. `app/Models/Payroll.php` — add `study_loan` to fillable + casts
4. New migration: `add_study_loan_to_payrolls_table`
5. `app/Services/PayrollService.php` — add to calculation pipeline + total_deductions
6. `app/Http/Resources/PayrollResource.php` — expose field
7. Payslip templates — add row
8. Frontend employment form — add input field

---

### Option B: Add to `employees` table

Store the study loan amount on the employee record itself.

**Schema change:**
```php
$table->decimal('study_loan', 10, 2)->nullable()->default(0);
```

**Pros:**
- Loan is personal, not tied to specific employment
- If employee changes employment (e.g., new contract), loan carries over automatically

**Cons:**
- Breaks the established pattern — Employee table stores personal info (name, DOB, family, identification), NOT financial deduction amounts
- PayrollService loads salary from `Employment`, not `Employee`. Adding financial data to Employee creates inconsistency
- The only payroll-adjacent field on Employee is `eligible_parents_count` (for tax allowances), which is a very different use case
- Would need to ensure `$employee` is always loaded with the study_loan field in the payroll pipeline (currently the employee relationship is already loaded, but semantic coupling increases)

**Verdict: Not recommended** — mixing personal data with payroll deductions violates the existing separation of concerns.

---

### Option C: Create a separate `employee_deductions` table

Create a new table for flexible per-employee deductions.

**Schema:**
```php
Schema::create('employee_deductions', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
    $table->foreignId('employment_id')->nullable()->constrained()->nullOnDelete();
    $table->string('deduction_type', 50); // 'study_loan', 'housing_loan', etc.
    $table->decimal('amount', 10, 2);
    $table->date('start_date');
    $table->date('end_date')->nullable();
    $table->boolean('is_active')->default(true);
    $table->text('notes')->nullable();
    $table->string('created_by')->nullable();
    $table->string('updated_by')->nullable();
    $table->timestamps();
});
```

**Pros:**
- Most flexible — supports unlimited deduction types (study loan, housing loan, car loan, union fees, etc.)
- Can track start/end dates and active/inactive status
- Could track remaining balance if extended further
- Clean separation — deductions have their own domain model
- Future-proof for additional deduction types

**Cons:**
- Significantly more work: new Model, Service, Controller, Form Requests, Resource, routes, frontend CRUD
- Additional query in payroll pipeline (though can be eager-loaded)
- Over-engineered if study loan is the only custom deduction needed
- PayrollService would need to aggregate deductions from a relationship instead of a simple field read
- No existing pattern in the codebase for this — introduces a new architectural concept

**Verdict: Good for the future, but over-engineered for a single deduction type.** Consider this if 2+ custom deduction types are needed.

---

### Option D: Add to `benefit_settings` table

Store study loan as a global benefit setting.

**Cons (immediately disqualifying):**
- `benefit_settings` stores **organization-wide** rates (e.g., PVD rate = 7.5% for everyone)
- Study loan amounts **vary per employee** — ฿3,000 for one, ฿5,000 for another
- This table cannot store per-employee values

**Verdict: Not viable** — wrong abstraction level entirely.

---

## 5. Recommendation: Option A (Employment Table)

### Why `employments` is the right place:

1. **Pattern consistency**: The employment table already stores `pvd` (bool), `saving_fund` (bool), `health_welfare` (bool) — all per-employee payroll deduction controls. Adding `study_loan` (decimal) follows the same pattern, just with an amount instead of a boolean.

2. **PayrollService already reads from employment**: The calculation pipeline has `$employment` loaded and reads `$employment->pvd`, `$employment->saving_fund`, etc. Reading `$employment->study_loan` requires zero additional queries.

3. **Employment history tracks changes**: The `EmploymentObserver` already creates history records when employment fields change. Study loan amount changes would be automatically audited.

4. **Admin workflow matches**: On the employment form, admins already toggle PVD/Saving Fund/Health Welfare. Adding a study loan amount field is a natural extension of this UI.

5. **Simplicity**: One migration, a few lines in PayrollService. No new models, services, or controllers needed.

### What about loan tracking (balance, schedule)?

If future requirements include tracking loan balance, repayment schedule, or multiple loan types, **then** migrate to Option C (separate table). But for the current requirement — "a value to include in the deduction" — Option A is sufficient and can be refactored later without breaking changes.

---

## 6. Implementation Outline (Option A)

### 6.1 Database Changes

**Migration 1: Add to employments**
```php
// database/migrations/xxxx_add_study_loan_to_employments_table.php
Schema::table('employments', function (Blueprint $table) {
    $table->decimal('study_loan', 10, 2)->nullable()->default(0)
        ->after('saving_fund');
});
```

**Migration 2: Add to payrolls** (to store the calculated deduction)
```php
// database/migrations/xxxx_add_study_loan_to_payrolls_table.php
Schema::table('payrolls', function (Blueprint $table) {
    $table->text('study_loan')->nullable()->after('saving_fund');
    // TEXT type because all payroll monetary fields use Laravel encryption
});
```

### 6.2 Model Changes

**Employment model** — add to `$fillable` and `$casts`:
```php
'study_loan',  // in $fillable
'study_loan' => 'decimal:2',  // in $casts
```

**Payroll model** — add to `$fillable` and `$casts`:
```php
'study_loan',  // in $fillable
'study_loan' => 'encrypted',  // in $casts (encrypted like all other monetary fields)
```

### 6.3 PayrollService Changes

In `calculateAllocationPayroll()`, after the income tax calculation (step 10), before building totals:

```php
// Study Loan Deduction
// Fixed amount, not FTE-proportional, not tax-deductible
// Applied to tax-bearing allocation only (like income tax)
$studyLoan = 0.0;
if ($isTaxAllocation && (float) ($employment->study_loan ?? 0) > 0) {
    $studyLoan = round((float) $employment->study_loan);
}
```

Update the total deductions line:
```php
$totalDeductions = $pvdSavingEmployee + $employeeSocialSecurity
    + $healthWelfareEmployee + $incomeTax + $studyLoan;
```

Add to the return array:
```php
'study_loan' => $studyLoan,
```

### 6.4 Tax Impact

Study loan repayment is **NOT tax-deductible** under Thai tax law (it's a personal loan repayment, not a qualifying deduction like PVD or SSF). Therefore:

- Do NOT pass study_loan to `TaxCalculationService`
- Do NOT include it in tax deduction calculations
- Simply subtract from net salary after tax is calculated

### 6.5 Multi-Allocation Handling

For employees with multiple funding allocations (e.g., 60% Grant A + 40% Grant B):

- **Study loan should be deducted ONCE**, not split across allocations
- Assign it to the **tax-bearing allocation** (the one with highest FTE), same pattern as income tax
- Non-tax allocations get `study_loan = 0`

This matches the existing tax allocation pattern in `ProcessBulkPayroll`:
```php
$taxAllocationId = $this->payrollService->determineTaxAllocationId($allocations);
```

### 6.6 PayrollResource & Payslip

**PayrollResource**: Add `'study_loan' => $this->study_loan` to the response.

**Payslip template**: Add a new row in the DEDUCTIONS column:
```html
<tr>
    <td>Study Loan</td>
    <td class="text-end">{{ number_format((float) $payroll->study_loan, 2) }}</td>
</tr>
```

### 6.7 ProcessBulkPayroll (Batch Processing)

The bulk payroll job calls `PayrollService::calculateAllocationPayrollForController()` which returns the calculation results. The `preparePayrollRecord()` method maps these to database fields. Add:

```php
'study_loan' => $calculations['study_loan'] ?? 0,
```

---

## 7. Files That Need Changes

| # | File | Change |
|---|------|--------|
| 1 | New migration | Add `study_loan` to `employments` table |
| 2 | New migration | Add `study_loan` to `payrolls` table |
| 3 | `app/Models/Employment.php` | Add to `$fillable` and `$casts` |
| 4 | `app/Models/Payroll.php` | Add to `$fillable`, `$casts`, and OA schema |
| 5 | `app/Services/PayrollService.php` | Add study_loan to calculation pipeline |
| 6 | `app/Jobs/ProcessBulkPayroll.php` | Map study_loan in `preparePayrollRecord()` |
| 7 | `app/Http/Resources/PayrollResource.php` | Expose study_loan field |
| 8 | `resources/views/pdf/smru-payslip.blade.php` | Add deduction row |
| 9 | `resources/views/pdf/bhf-payslip.blade.php` | Add deduction row |
| 10 | Employment form requests | Add validation rule |
| 11 | Payroll form requests | Add validation rule |
| 12 | Frontend employment form | Add study_loan input field |
| 13 | `app/Imports/PayrollsImport.php` | Add study_loan column mapping |

---

## 8. Data Flow Diagram

```
SETUP (once, by admin):
  Admin → Employment Form → sets study_loan = 3000
       → Saved to employments.study_loan
       → EmploymentObserver creates history record

PAYROLL CALCULATION (monthly):
  PayrollService.calculateAllocationPayroll()
    ├── $employment = loaded (already includes study_loan)
    ├── ... items 1-10 calculated as usual ...
    ├── $studyLoan = $isTaxAllocation ? $employment->study_loan : 0
    ├── $totalDeductions += $studyLoan
    ├── $netSalary = $totalIncome - $totalDeductions
    └── return ['study_loan' => $studyLoan, ...]

STORAGE:
  Payroll::create([
    ...,
    'study_loan' => $result['study_loan'],  // encrypted at rest
  ])

DISPLAY:
  PayrollResource → includes study_loan
  Payslip PDF → shows study_loan row in deductions
```

---

## 9. Comparison Summary

| Criteria | Employment (A) | Employee (B) | Separate Table (C) | Benefit Settings (D) |
|----------|:-:|:-:|:-:|:-:|
| Follows existing patterns | Yes | No | New pattern | No |
| Zero extra queries | Yes | Yes | No (1 extra) | N/A |
| Per-employee amounts | Yes | Yes | Yes | No |
| Tracks loan balance | No | No | Possible | No |
| Multiple deduction types | No | No | Yes | No |
| Implementation effort | Low | Low | High | N/A |
| Future extensibility | Limited | Limited | High | N/A |
| **Recommended** | **Yes** | No | If 2+ types needed | No |
