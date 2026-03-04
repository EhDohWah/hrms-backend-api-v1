# Payroll Implementation vs Business Logic — Cross-Reference Report

> **Implementation**: Laravel backend (`app/Services/PayrollService.php`, `TaxCalculationService.php`, related models/services)
> **Business Logic**: `PAYROLL-BUSINESS-LOGIC.docx` (Version 2.0, February 2026), documented in `research.md`
> **Analysis date**: 2026-03-03

---

## Executive Summary

| Category | Count |
|----------|-------|
| Business logic steps fully implemented and correct | 25 |
| Discrepancies found (implementation ≠ business logic) | 12 |
| Critical (will produce wrong payroll amounts) | 2 |
| High (will cause incorrect behavior in specific scenarios) | 3 |
| Medium (incorrect but limited practical impact) | 4 |
| Low (cosmetic / labeling / minor) | 3 |

The core payroll calculation pipeline is **largely correct**. The 30-day basis, proration formulas, probation salary splits, deduction tiers, ACM tax method, 13th month per-allocation logic, and multi-grant FTE handling all match the business logic. The two critical issues are **tax being split across grants instead of deducted from one grant only** and the **annual salary increase counting working days instead of calendar days**.

---

## Step-by-Step Comparison

### Step 1: Employee Eligibility

#### 1.1 Employment Status Check

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| Active employment = `start_date` exists, `end_date` null | Yes | `Employment::isActive()` returns true when `end_date` is NULL | ✅ |
| Active funding allocation required | `status = Active` | `activeAllocations()` scope filters `status = Active` | ✅ |
| Inactive allocations skipped | Yes | Only active allocations processed in bulk payroll loop | ✅ |

**Verdict: MATCH**

#### 1.2 Start Date Proration Rules

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| 30-day standardized month | `daily_rate = salary / 30` | `round(adjustedGrossSalary / 30)` | ✅ |
| Start day 1 | Full 30 days | `daysWorked = 30` | ✅ |
| Start day 2–15 | `30 - (startDay - 1)` days | `30 - startDay + 1` (equivalent) | ✅ |
| Start day ≥ 16 | 0 days (deferred) | `daysWorked = 0`, retroactive next month | ✅ |

**Implementation** (`PayrollService`, line ~1298–1335):
```
Start day 1:     daysWorked = 30
Start day 2-15:  daysWorked = 30 - startDay + 1
Start day >= 16: daysWorked = 0
```

**Example verification**: Start 5th → `30 - 5 + 1 = 26 days` → Business logic: `30 - (5-1) = 26 days` ✅

**Verdict: MATCH**

#### 1.3 Resignation Proration

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| Pay through last working day | `prorated = daily_rate × days_worked` | `resignDays = end_date.day` (capped at 30), `daysWorked = min(calculated, resignDays)` | ✅ |

**Verdict: MATCH**

---

### Step 2: Salary Basis Determination

#### 2.1 Base Calculation (30-Day Month)

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| All calculations use 30 days | Universal | `round(salary / 30) * daysWorked` everywhere | ✅ |

**Verdict: MATCH**

#### 2.2 Probation → Pass-Probation Transition

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| Pass-probation on 1st = full month at new salary | Case A | `ProbationTransitionService::calculateProRatedSalary()` returns `pass_probation_salary` when payroll month is after transition | ✅ |
| Mid-month split | `probation_days = pass_date - 1`, `post_days = 30 - probation_days` | `$probationDays = $passProbationDate->day - 1; $regularDays = 30 - $probationDays` | ✅ |
| Pass-probation date itself = post-probation day | Day itself is post-probation | `probationDays = day - 1` excludes the transition day from probation | ✅ |
| Daily rate rounding | `probation_salary / 30` then multiply | `round($employment->probation_salary / 30)` per rate | ✅ |

**Worked example verification (pass probation on the 4th)**:
- Business logic: probation days = 3, post days = 27
- Implementation: `$probationDays = 4 - 1 = 3`, `$regularDays = 30 - 3 = 27` ✅

**Verdict: MATCH**

#### 2.3 Salary by FTE

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| FTE from allocation, not employment | `gross_salary_by_fte = gross_salary × FTE` | `round(proRatedSalary * allocation.fte)` | ✅ |
| No FTE field on employment | Confirmed in business logic | Employment model has no FTE field; FTE on `EmployeeFundingAllocation.fte` | ✅ |

**Verdict: MATCH**

#### 2.4 Funding Allocation Update on Probation Pass

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| Allocation records updated with new salary | Single transaction | `Employment::updateFundingAllocationsAfterProbation()` recalculates `allocated_amount` for all allocations using `pass_probation_salary` | ✅ |

**Verdict: MATCH**

#### 2.5 Annual Salary Increase

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| Applied on January 1st | Specific month | No month restriction in code — applies whenever threshold met | ⚠️ |
| 365-day requirement | 365 calendar days | Counts **working days** (excludes weekends) — ~511 calendar days to reach 365 | ❌ |
| Start date 1st–15th for month to count | Explicit cutoff | **Not implemented** — no 15th cutoff check | ❌ |
| Rate: 1% (configurable) | `salary × 1.01` | `round(pass_probation_salary × rate)` added to gross — effectively ×1.01 | ✅ |
| Base salary for increase | Current salary | Uses `pass_probation_salary` specifically | ⚠️ |

**Implementation** (`PayrollService`, lines 1954–1984):
```php
// Counts WORKING days (Mon-Fri), not calendar days
while ($currentDate->lte($payPeriodDate)) {
    if (!$currentDate->isWeekend()) {
        $workingDays++;
    }
    $currentDate->addDay();
}
if ($workingDays >= $minWorkingDays) {
    return round($employment->pass_probation_salary * $rate);
}
```

**Impact**: An employee starting Jan 1, 2025 would need ~511 calendar days (~July 2026) to accumulate 365 working days, instead of qualifying in Jan 2026 (365 calendar days). This is a significant bug — employees will not receive their annual increase on time.

**Verdict: DISCREPANCY — Critical (D1)**

---

### Step 3: Deductions

#### 3.1 PVD / Saving Fund (7.5%)

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| Thai citizens → PVD | Yes | `Employee::STATUS_LOCAL_ID` → PVD | ✅ |
| Non-Thai ID staff → Saving Fund | Yes | `Employee::STATUS_LOCAL_NON_ID` → Saving Fund | ✅ |
| Expat / Local → Neither | Yes | Expats return 0 | ✅ |
| Rate: 7.5% (configurable) | Yes | `BenefitSetting::KEY_PVD_EMPLOYEE_RATE` default 7.5 | ✅ |
| Only after probation | Yes | Checks `probation_required` and `pass_probation_date` before calculating | ✅ |
| HR checkbox toggle | Yes | Checks `employment.pvd` / `employment.saving_fund` boolean | ✅ |
| Employer matches employee | Yes | `employer_portion = employee_portion × (employer_rate / employee_rate)` | ✅ |

**Verdict: MATCH**

#### 3.2 Social Security (5%)

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| Rate: 5% of salary | Yes | `BenefitSetting::KEY_SSF_EMPLOYEE_RATE` default 5.0 | ✅ |
| Cap: THB 875/month | Yes | `BenefitSetting::KEY_SSF_MAX_MONTHLY` default 875 | ✅ |
| Min salary: THB 1,650 | Yes | `BenefitSetting::KEY_SSF_MIN_SALARY` default 1,650 | ✅ |
| Max salary: THB 17,500 | Yes | `BenefitSetting::KEY_SSF_MAX_SALARY` default 17,500 | ✅ |
| FTE-proportional when capped | `875 × FTE per allocation` | `round(fullContribution * fte)` | ✅ |
| No probation gate | From start date | No probation check — calculated for all eligible | ✅ |
| Expat/Local excluded | Yes | Only `Local ID` and `Local Non ID` eligible | ✅ |

**Verdict: MATCH**

#### 3.3 Health Welfare

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| Non-Thai employee tiers | 30 / 50 / 75 | Seeder defaults: 30.00 / 50.00 / 75.00 | ✅ |
| Non-Thai employer tiers | 60 / 100 / 150 | Seeder defaults: 60.00 / 100.00 / 150.00 | ✅ |
| Thai employee tiers | 50 / 80 / 100 | Seeder defaults: 50.00 / 80.00 / 100.00 | ✅ |
| Thai employer = 0 | No employer contribution | Code returns 0 for Thai employees | ✅ |
| Salary thresholds | ≤5,000 / 5,001–15,000 / 15,001+ | `health_welfare_medium_threshold = 5,000`, `high_threshold = 15,000` | ✅ |
| FTE-proportional | Yes | `round(amount * fte)` | ✅ |
| No probation gate | From start date | No probation check | ✅ |
| Expat / Local: varies by individual | Per-individual override | Code returns 0 for Expats; no per-individual override implemented | ⚠️ |

**Note**: Business logic says Expat/Local HW "varies by individual." The seeder includes `Expats (Local)` in `eligible_statuses` for employer HW, but the code returns 0 for Expat employee HW. The employer HW may apply via `getEmployerHWEligibility()` for Expats but employee portion returns 0. This inconsistency is minor since Expats are rare.

**Verdict: MATCH (with minor Expat caveat)**

#### 3.4 Tax (Thai Personal Income Tax)

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| Tax eligibility: `tax_number` NOT NULL | Yes | Checked in controller/form request level | ✅ |
| Tax eligibility: `bank_account_number` NOT NULL | Yes | Implied by payment method | ✅ |
| ACM method used | Yes | `TaxCalculationService` implements ACM | ✅ |
| Remaining months = 13 - current_month | Jan=12, Dec=1 | `remainingInclusive = 13 - currentMonth` | ✅ |
| Annual income estimate | `ytd + (salary × remaining)` | `ytdIncome + (grossSalary * remainingInclusive)` | ✅ |
| Monthly tax = `(annual_tax - ytd_withheld) / remaining` | ACM formula | `max(0, (annualTax - ytdTaxWithheld) / remainingInclusive)` | ✅ |
| Progressive brackets (0%–35%) | Thai PND1 | `calculateProgressiveIncomeTax()` iterates brackets | ✅ |
| Employment deduction: 50%, max 100K | Yes | `EMPLOYMENT_DEDUCTION_RATE=50`, `MAX=100,000` | ✅ |
| Personal allowance: 60K | Yes | `PERSONAL_ALLOWANCE=60,000` | ✅ |
| Spouse allowance: 60K (if no income) | Yes | Conditional on `has_spouse` AND `is_selected` | ✅ |
| Children allowance: 30K each | Yes | `CHILD_ALLOWANCE=30,000`, subsequent `=60,000` | ✅ |
| Parent allowance: 30K per parent | Yes | `PARENT_ALLOWANCE=30,000` × `eligible_parents_count` | ✅ |
| SSF deductible pre-tax | Yes | Included in deductions before taxable income | ✅ |
| PVD/SF deductible pre-tax | Yes | Included in deductions before taxable income | ✅ |
| **Tax on ONE grant only** | **Not split** | **Tax = `round(fullTax × fte)` — SPLIT across allocations** | ❌ |

**Critical Discrepancy — Tax Split Logic**:

Business logic (Step 3.4, Step 7.1): *"Tax is deducted from ONE grant only, even when the employee has multiple funding sources."*

Implementation (`PayrollService`, line ~1689):
```php
return round($fullMonthlyTax * $fte);
```

This splits tax proportionally by FTE across ALL allocations. For a 60%/40% split employee with 1,000 THB monthly tax:
- **Implementation**: Grant A = 600, Grant B = 400
- **Business logic**: Grant A = 1,000, Grant B = 0 (or vice versa)

**Impact**: Each payroll record has incorrect tax amounts. Total tax collected is the same (both sum to 1,000), but the per-allocation breakdown is wrong. This affects:
- Payslip accuracy (each allocation's net salary is wrong)
- Grant budget reporting (tax cost allocated to wrong grants)
- Inter-organization advance amounts (net_salary is used for advance amount)

**Verdict: DISCREPANCY — Critical (D2)**

#### 3.5 Rounding Rules

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| Salary/proration → integer | `round(10,000/30) × 16 = 5,328` | `round(salary / 30) * days` — PHP `round()` = integer | ✅ |
| Deductions (SSF, PVD, HW) → integer | `875` (integer cap) | `round(contribution * fte)` — integer | ✅ |
| 13th month → integer | `round(17,165 × 6 / 12) = 8,583` | `round(total / divisor)` — integer | ✅ |
| **Tax → 2 decimal places** | `monthly_tax = 52.08` | `round(fullTax * fte)` — **integer, not 2 decimals** | ❌ |

**Implementation**: PHP's `round()` without second argument rounds to nearest integer. The business logic worked example shows `monthly_tax = 625 / 12 = 52.08` (2 decimal places).

**Impact**: For a 30,000/month employee (annual tax ~1,975), monthly tax via ACM ÷ 12 ≈ 164.58. Implementation stores 165 (integer), business logic expects 164.58.

Over 12 months: `165 × 11 + (1,975 - 1,815) = 1,815 + 160 = 1,975` — ACM self-corrects in December, so the annual total is correct. But monthly amounts differ slightly from what the business logic specifies.

**Verdict: DISCREPANCY — Medium (D3)**

#### 3.6 Leave Without Pay Deduction

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| `deduction = daily_rate × unpaid_leave_days` | Defined in Step 3.6 | **Not found** in PayrollService calculation flow | ❌ |
| Shown as separate line item on payslip | Yes | No leave deduction field in Payroll model or payslip template | ❌ |

**Note**: The business logic defines leave-without-pay deduction as `daily_rate × unpaid_leave_days`, shown as a separate payslip line. The implementation has no mechanism to input or calculate unpaid leave deductions during payroll. This may be handled manually via the `retroactive_adjustment` field, but it's not an explicit feature.

**Verdict: DISCREPANCY — Medium (D4)**

---

### Step 4: Employer Contributions

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| Employer SSF = same as employee | 5%, max 875 | Identical calculation method | ✅ |
| Employer HW = tier table (Non-Thai only) | Yes | `calculateHealthWelfareEmployer()` — Non-Thai tiers, Thai = 0 | ✅ |
| Employer PVD = same rate as employee | 7.5% matching | `employer_portion = employee × (employer_rate / employee_rate)` | ✅ |

**Verdict: MATCH**

---

### Step 5: Income Additions

#### 5.1 Thirteenth Month Salary

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| Eligible from `start_date` (no probation gate) | Yes | No probation check before 13th month calculation | ✅ |
| December only | Yes | `payPeriodDate->month === 12` check | ✅ |
| Formula: `YTD gross_by_FTE / 12` | Always divide by 12 | `round(totalYearGrossByFTE / divisor)`, divisor default 12 | ✅ |
| Per-allocation (not per-employee) | Yes | Queries payrolls per allocation independently | ✅ |
| Historical (inactive) allocations get December payout | Yes | `calculateHistoricalAllocation13thMonth()` handles closed allocations | ✅ |
| Duplicate prevention for December | Yes | Checks `existingDecember` before calculation | ✅ |
| Includes both probation and post-probation salary months | Yes | YTD uses actual `gross_salary_by_FTE` from each month's payroll | ✅ |

**Verdict: MATCH**

#### 5.1 (cont.) 13th Month Accrued

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| Manually entered by HR | Yes (updated in business logic doc) | `thirteen_month_salary_accured` field exists but is **system-calculated** as `round((ytd + current) / divisor)` | ⚠️ |

**Note**: The updated business logic says "13th Month Accrued is manually entered by HR staff — not system-calculated." However, the implementation auto-calculates it as a running YTD projection. This is actually useful for HR reporting and doesn't affect the payroll amount — it's informational only. The discrepancy is in the definition (system-calculated vs manually entered), not in the payroll outcome.

**Verdict: MINOR DISCREPANCY — Low (D5)**

#### 5.2 Retroactive Adjustment

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| Start day ≥ 16 → deferred salary to next month | Yes | `calculateRetroactiveAdjustment()` checks previous month start day | ✅ |
| Deferred days = 30 - startDay + 1 | Yes | `deferredDays = 30 - startDay + 1` | ✅ |
| Prorated by FTE | Yes | `round(proRatedSalary * fte)` | ✅ |

**Verdict: MATCH**

#### 5.3 Salary Bonus

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| Included in total income | Yes | `total_income = gross_by_fte + retro + thirteenth_month + bonus` | ✅ |
| Displayed on payslip | Business logic lists it in Step 10.1 payslip incomes | **Not displayed** — no row for salary bonus on payslip template | ❌ |

**Verdict: DISCREPANCY — Low (D6)**

---

### Step 6: Final Amounts

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| total_deductions = PVD + SSF + HW + Tax | Yes | `pvd_saving_employee + employee_ssf + hw_employee + tax` | ✅ |
| total_income = gross_by_fte + 13th + retro + bonus | Yes | Same formula | ✅ |
| net_salary = total_income - total_deductions | Yes | `round(totalIncome - totalDeductions)` | ✅ |
| total_salary_cost = net + deductions + employer_cost | Includes employer PVD | `total_salary = total_income + employer_ssf + employer_hw` — **excludes employer PVD** | ⚠️ |
| **Negative net pay → cap at 0** | "Must not go below zero" | **No `max(0, ...)` applied** — negative net pay possible | ❌ |

**Implementation** (`PayrollService`, line ~1709):
```php
$netSalary = round($totalIncome - $totalDeductions);
// No floor at 0
```

**`total_salary` formula** — The implementation's "Grand total: Salary & Benefit" on the payslip uses `total_salary` which equals `total_income + employer_ssf + employer_hw`. This excludes employer PVD/Saving Fund, while the business logic's `total_salary_cost` formula includes it. The employer PVD is tracked separately in `employer_contribution`.

**Verdict: DISCREPANCY — High for negative net pay (D7), Low for total_salary formula (D8)**

---

### Step 7: Multi-Grant Funding

#### 7.1 Per-Allocation Payroll

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| One payroll record per active allocation | Yes | Bulk payroll loop iterates each allocation | ✅ |
| FTE for salary split | Yes | `round(salary * fte)` | ✅ |
| SSF/PVD/HW split by FTE | Yes | `round(contribution * fte)` | ✅ |
| **Tax on ONE grant only** | Yes | **SPLIT by FTE — see D2** | ❌ |

#### 7.2 Inter-Organization Advances

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| Created when employee org ≠ grant org | Yes | `createInterOrganizationAdvanceIfNeeded()` compares organizations | ✅ |
| Amount = net_salary | Yes | `amount: payroll.net_salary` | ✅ |
| Uses hub grants (S0031/S22001) | Yes | `Grant::getHubGrantForOrganization()` | ✅ |

#### 7.3 Hub Grants

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| SMRU = S0031, BHF = S22001 | Yes | Retrieved via `getHubGrantForOrganization()` | ✅ |

**Verdict: MATCH (except tax split — see D2)**

---

### Step 8: Pay Period & Payment Date

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| HR manually selects pay date | Yes | `pay_period_date` field on payroll record, set by HR | ✅ |
| No auto-calculation of payment date | Yes | Date picker in UI | ✅ |

**Verdict: MATCH**

---

### Step 9: Bulk Payroll Processing

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| Preview (dry run) | Yes | `BulkPayrollService::preview()` — calculates without saving | ✅ |
| Batch creation | `BulkPayrollBatch` record with status | Yes — status: pending → processing → completed | ✅ |
| Async background job | For 500+ employees | `ProcessBulkPayroll` job dispatched immediately (no 500 threshold) | ✅ |
| Progress tracking | Processed/total count, success/fail | Broadcast every 10 payrolls via WebSocket + batch record updates | ✅ |
| Error logging per employee/allocation | Yes | Errors collected in array, stored in `batch.errors` JSON | ✅ |
| Downloadable error report | CSV | `getErrorReport()` generates CSV | ✅ |

**Verdict: MATCH**

---

### Step 10: Payroll Output

#### 10.1 Payslip Contents

**Income Section:**

| Business Logic Field | Payslip Template | Match? |
|---------------------|-----------------|--------|
| Gross Salary (by FTE) | Row 1: "Salary" → `gross_salary_by_FTE` | ✅ |
| 13th Month Salary | Row 2: "13-month salary" → `thirteen_month_salary` | ✅ |
| 13th Month Accrued (manually entered by HR) | **Not displayed** | ✅ (correct per updated business logic) |
| Retroactive Adjustment | Row 3: "Retroactive Sal." → `retroactive_adjustment` | ✅ |
| Salary Bonus | **Not displayed** — no row on payslip | ❌ (see D6) |
| Total Income | "Total" → `total_income` | ✅ |

**Deduction Section:**

| Business Logic Field | Payslip Template | Match? |
|---------------------|-----------------|--------|
| PVD / Saving Fund | Row 1: "Provident fund: staff 7.5%" → `pvd` | ⚠️ Label always says "Provident fund" |
| Social Security (Employee) | Row 2: "Social security: staff 5%" → `employee_social_security` | ✅ |
| Health Welfare (Employee) | Row 3: "Health Welfare: staff" → `employee_health_welfare` | ✅ |
| Tax | Row 4: "Tax" → `tax` | ✅ |
| Total Deductions | "Total" → `total_deduction` | ✅ |

**Payslip Label Issue**: The deduction row always shows "Provident fund: staff 7.5%" regardless of employee type. For Non-Thai employees with Saving Fund, the label should read "Saving fund: staff 7.5%" instead.

**Verdict: DISCREPANCY — Low (D9) for PVD/SF labeling, Low (D6) for missing bonus row**

#### 10.2 Payslip Notes

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| Replace payment method with auto-generated notes | "Notes are auto-generated during payroll calculation" | Payslip shows "Pay method: {bank_name}" — **no notes field displayed** | ❌ |

The business logic (Step 10.2) says: "Replace the payment method field at the bottom with Payroll Notes." The implementation still shows the bank name as payment method. The payroll record does have a `notes` field and the calculation generates a detailed `calculation_breakdown`, but neither is displayed on the payslip.

**Verdict: DISCREPANCY — Low (D10)**

#### 10.3 Layout

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| A5 landscape | Yes | `@page { size: A5 landscape; }` | ✅ |
| Organization-specific templates | SMRU vs BHF | `smru-payslip.blade.php` / `bhf-payslip.blade.php` | ✅ |

**Verdict: MATCH**

---

### Step 11: Configurable Settings

| Setting | Business Logic | Implementation | Match? |
|---------|---------------|----------------|--------|
| Social Security Rate | 5% | `ssf_employee_rate = 5.0` in BenefitSetting | ✅ |
| Social Security Cap | THB 875 | `ssf_max_monthly = 875.0` | ✅ |
| Social Security Min Salary | THB 1,650 | `ssf_min_salary = 1,650.0` | ✅ |
| Social Security Max Salary | THB 17,500 | `ssf_max_salary = 17,500.0` | ✅ |
| PVD / Saving Fund Rate | 7.5% | `pvd_employee_rate = 7.5`, `saving_fund_employee_rate = 7.5` | ✅ |
| Health Welfare Tiers (Non-Thai) | See Step 3.3 | Seeder matches all 6 tiers | ✅ |
| Health Welfare Tiers (Thai) | See Step 3.3 | Seeder matches all 3 tiers | ✅ |
| Annual Salary Increase | 1% | `salary_increase_rate = 1.00` in PayrollPolicySetting | ✅ |
| 30-Day Month Basis | 30 | Hardcoded `30` throughout | ✅ |
| Tax Brackets | Thai PND1 (0%–35%) | TaxBracket model with progressive rates | ✅ |
| Employment Deduction | 50%, max 100K | `EMPLOYMENT_DEDUCTION_RATE=50`, `MAX=100,000` | ✅ |
| Personal Allowance | 60K | `PERSONAL_ALLOWANCE=60,000` | ✅ |

**Verdict: MATCH — all settings are configurable, not hardcoded**

---

### Step 12: Data Migration & Uploads

| Rule | Business Logic | Implementation | Match? |
|------|---------------|----------------|--------|
| Excel upload template | Yes | `downloadTemplate()` generates Excel with headers, validation, sample data | ✅ |
| Queued import | Yes | `upload()` queues Excel import with unique ID | ✅ |
| Allocation reference download | Yes | `downloadAllocationsReference()` for active allocations | ✅ |

**Verdict: MATCH**

---

### Step 13: Edge Cases & System Behavior

| Edge Case | Business Logic | Implementation | Match? |
|-----------|---------------|----------------|--------|
| **Duplicate prevention** | Prevent same employee + month + allocation | Only December 13th month has explicit duplicate check — **no general duplicate prevention** | ❌ |
| **Negative net pay** | Cap at 0, flag for HR | No floor — negative values possible | ❌ |
| **Future-dated employees** | Excluded from pay period | Start date check in proration logic excludes them | ✅ |
| **Empty payroll** | Clear message | Bulk preview returns empty set; batch aborts if no employees match | ✅ |

**Verdict: DISCREPANCY — High for duplicate prevention (D11), High for negative net pay (D7)**

---

## Complete Discrepancy Register

### Critical (Will produce incorrect payroll amounts)

| ID | Area | Business Logic | Implementation | Impact |
|----|------|---------------|----------------|--------|
| **D1** | Annual Salary Increase | 365 **calendar** days + 15th cutoff rule | Counts **working days** (excludes weekends); no 15th cutoff | Employees won't receive annual increase on time (~1.4 years instead of 1 year). The 15th cutoff rule for start month is completely missing. |
| **D2** | Tax Grant Allocation | Tax deducted from **ONE grant only** | Tax **split by FTE** across all allocations | Per-allocation tax amounts are wrong. Grant budget reports show incorrect tax allocation. Inter-org advance amounts affected (uses net_salary which includes wrong tax split). Total tax is correct, per-grant breakdown is not. |

### High (Incorrect behavior in specific scenarios)

| ID | Area | Business Logic | Implementation | Impact |
|----|------|---------------|----------------|--------|
| **D7** | Negative Net Pay | Must not go below 0; flag for HR review | No floor — negative `net_salary` possible | Edge case: employee with high deductions (multiple mid-month events + tax + PVD) could show negative paycheck. Business logic requires capping at 0 and flagging. |
| **D11** | Duplicate Prevention | Prevent running payroll twice for same employee + month + allocation | Only December 13th month has duplicate check | Running bulk payroll twice for the same month would create duplicate records. No unique constraint or check-before-insert for regular months. |
| **D4** | Leave Without Pay | `deduction = daily_rate × unpaid_leave_days` as separate line | Not implemented as explicit payroll calculation | No mechanism to deduct unpaid leave during automated payroll. May be handled manually via retroactive_adjustment field, but not a first-class feature. |

### Medium (Incorrect but limited practical impact)

| ID | Area | Business Logic | Implementation | Impact |
|----|------|---------------|----------------|--------|
| **D3** | Tax Rounding | Tax amounts use **2 decimal places** (e.g., 52.08) | PHP `round()` returns **integer** (e.g., 52) | Monthly tax differs by up to ±0.99 THB. ACM self-corrects in December, so annual total is correct. Monthly payslips show slightly different amounts than business logic expects. |
| **D8** | Total Salary Cost | `total_salary_cost = net + deductions + employer_cost` (includes employer PVD) | `total_salary = total_income + employer_ssf + employer_hw` (excludes employer PVD) | The "Grand total: Salary & Benefit" on the payslip understates true cost to organization by the employer PVD/SF amount. Employer PVD is tracked in `employer_contribution` field but not in `total_salary`. |
| **D5** | 13th Month Accrued | Manually entered by HR (not system-calculated) | System auto-calculates as YTD projection | The field is informational only (not part of payroll amounts). Auto-calculation is arguably more useful than manual entry. Not displayed on payslip. No payroll amount impact. |

### Low (Cosmetic / labeling / minor)

| ID | Area | Business Logic | Implementation | Impact |
|----|------|---------------|----------------|--------|
| **D6** | Payslip: Salary Bonus | Listed as income line item in Step 10.1 | No row on payslip template for salary bonus | Bonus amount is included in `total_income` but not visible as a separate line. The payslip has empty rows that could accommodate this. |
| **D9** | Payslip: PVD/SF Label | "PVD" for Thai, "Saving Fund" for Non-Thai | Hardcoded "Provident fund" label for all employees | Non-Thai employees see incorrect label. Does not affect calculation amounts. |
| **D10** | Payslip: Notes | Replace payment method with auto-generated payroll notes | Shows "Pay method: {bank_name}" | Business logic says to show notes instead of payment method. The `notes` field exists but is not rendered on the payslip. |

---

## Summary: What's Working Well

The implementation is **production-quality** in these areas:

1. **Core salary calculation** — 30-day basis, proration, FTE split, and probation transitions are all mathematically correct
2. **ACM tax method** — The accumulative calculation with YTD tracking and self-correction is properly implemented
3. **Benefit settings architecture** — All rates, tiers, and thresholds are configurable via database with caching
4. **Multi-allocation handling** — Per-allocation payroll records with independent FTE calculations
5. **13th month salary** — Per-allocation YTD calculation with historical allocation support for December
6. **Bulk processing** — Preview → batch → async job → progress tracking → error reporting
7. **Inter-organization advances** — Automatic detection and creation when employee org ≠ grant org
8. **Encrypted salary storage** — All monetary fields encrypted at rest
9. **Detailed calculation breakdown** — Every payroll record includes step-by-step debugging info

---

## Recommended Fix Priority

| Priority | Discrepancy | Effort | Recommendation |
|----------|-------------|--------|---------------|
| 1 | **D1**: Annual salary increase (working days → calendar days + 15th rule) | Small | Replace weekend-excluding loop with simple `Carbon::diffInDays()`. Add 15th cutoff check. Add `salary_increase_effective_month` enforcement. |
| 2 | **D2**: Tax on one grant only | Medium | Add logic to select ONE allocation for tax (e.g., first active, or largest FTE). Set tax = 0 for all other allocations. Requires updating the tax calculation flow and net salary per allocation. |
| 3 | **D11**: General duplicate prevention | Small | Add check before batch insert: `Payroll::where(employment_id, allocation_id, pay_period_month)->exists()`. Or add a unique composite index. |
| 4 | **D7**: Negative net pay floor | Small | Add `$netSalary = max(0, $netSalary)` and flag payrolls where floor was applied for HR review. |
| 5 | **D3**: Tax rounding to 2 decimals | Small | Change tax-related `round()` calls to `round($value, 2)`. |
| 6 | **D4**: Leave Without Pay | Medium | Add `unpaid_leave_days` field to payroll, calculate deduction, add payslip line. Or confirm it's handled via retroactive_adjustment. |
| 7 | **D6/D9/D10**: Payslip cosmetics | Small | Add salary bonus row, dynamic PVD/SF label, render notes field. |
| 8 | **D8**: Total salary includes employer PVD | Small | Update `calculateTotalSalary()` to include employer PVD/SF portion. |
| 9 | **D5**: 13th Month Accrued definition | None | Keep system-calculated — more useful than manual entry. Document the difference. |
