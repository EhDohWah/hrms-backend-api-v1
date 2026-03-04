# Payroll Business Logic — Deep Research Report

> Source document: `PAYROLL-BUSINESS-LOGIC.docx` (Version 2.0, February 2026)
> Covers: SMRU & BHF organizations
> Analysis date: 2026-03-03

---

## Document Structure Overview

The business logic document is organized as a **13-step payroll pipeline**, processing one employee at a time through a deterministic sequence. The document contains 42 tables (worked examples, tier charts, reference data) and 571 paragraphs covering every calculation rule.

**Processing order:**

| Step | Action | What It Does |
|------|--------|-------------|
| 1 | Eligibility | Active employment + active funding allocations |
| 2 | Salary Basis | Probation vs post-probation split (30-day basis) |
| 3 | Deductions | PVD (Local ID staff) / Saving Fund (Local non ID staff), SSF, Health Welfare, Tax |
| 4 | Employer Contributions | Employer-side SSF, HW, PVD |
| 5 | Income Additions | 13th month, retroactive adjustment, bonus (1% increase) |
| 6 | Final Amounts | Total deductions, total income, net salary, employer cost |
| 7 | Multi-Grant Funding | Per-allocation payroll, inter-org advances, hub grants |
| 8 | Pay Period | Manual pay date selection by HR |
| 9 | Bulk Processing | Preview, batch creation, async job, error handling |
| 10 | Output | Payslip contents and notes |
| 11 | Settings | All configurable parameters |
| 12 | Data Migration | Excel import/reconciliation |
| 13 | Edge Cases | Duplicates, negative pay, future-dated, empty runs |

---

## Step 1: Employee Eligibility

### 1.1 Employment Status Check

Two conditions must be true before any calculation:

1. Employee has **active employment** (determined by `start_date` existing and `end_date` being null — the employment table does not have a status attribute; `end_date` is set when a resignation is created)
2. Employee has **at least one active funding allocation** (`employee_funding_allocation.status = Active`; inactive allocations are skipped)

If either fails, the employee is skipped entirely.

### 1.2 Start Date Rules (Proration)

All salary calculations use a **fixed 30-day month** regardless of calendar month length.

```
daily_rate = monthly_salary / 30
prorated_salary = daily_rate × working_days
```

**Two rules based on start date:**

| Start Date | Pay This Month? | Working Days |
|-----------|----------------|-------------|
| On or before 15th | Yes (prorated) | 30 - (start_date - 1) |
| 16th or later | No | 0 (deferred to next month as retroactive) |

**Examples:**
- Start 15th → 30 - 14 = 16 days → paid 16/30 of salary
- Start 3rd → 30 - 2 = 28 days → paid 28/30 of salary
- Start 16th → 0 days this month → next month gets 15 extra days as retroactive

**Key insight:** The cutoff is the **15th**, not the 16th. Day 15 and earlier = paid; day 16 and later = deferred.

### 1.3 Resignation Rules

If employee resigns mid-month, pay is prorated to actual working days only.

- Resign on 8th → paid for 8 days only (8/30 of salary)

---

## Step 2: Salary Basis Determination

### 2.1 Base Calculation (30-Day Month)

Every salary calculation — proration, splits, daily rates — uses 30 days. February (28 days) still uses 30. December (31 days) still uses 30.

```
daily_rate = monthly_salary / 30
```

### 2.2 Probation → Pass-Probation Transition

This is explicitly called out as **"the most critical and error-prone calculation"** in the document.

**Case A: Pass-probation starts on the 1st**
- Full 30 days at post-probation salary
- Simple: `salary = pass_probation_salary`

**Case B: Pass-probation starts after the 1st**
- The month is **split into two parts** using both salary rates
- The `pass_probation_date` itself counts as a post-probation day

```
probation_days = pass_probation_date - 1
pass_probation_days = 30 - probation_days
salary = (probation_salary / 30 × probation_days) + (pass_probation_salary / 30 × pass_probation_days)
```

**Worked example (pass probation on the 4th):**
- Probation days = 3 (1st, 2nd, 3rd)
- Post-probation days = 27 (4th through 30th)
- salary = (8,000/30 × 3) + (10,000/30 × 27)

**Warning callout in document:** This transition is the #1 source of retroactive adjustment errors. The raw probation or pass-probation salary should NEVER be used as-is when the transition month is split.

### 2.3 Salary by FTE (Funding Allocation)

Gross salary is multiplied by the FTE from each **employee_funding_allocation** record. There is NO FTE field on the employment table — it comes exclusively from the allocation.

```
gross_salary_by_fte = gross_salary × FTE
```

**Example (salary 10,000 with two grants):**
- Grant A: FTE 0.8 → 10,000 × 0.8 = 8,000
- Grant B: FTE 0.2 → 10,000 × 0.2 = 2,000

### 2.4 Funding Allocation Update on Probation Pass

When probation is passed:
1. Payroll switches to using `pass_probation_salary`
2. The `employee_funding_allocation` records must ALSO be updated with the new salary
3. Both changes are treated as a **single transaction**

### 2.5 Annual Salary Increase

- Applied on **January 1st** each year
- Employee must have worked **365 days** (1 full year) to qualify
- The increase is annual and recurring
- Default rate: **1%** (configurable via system settings)
- Start date must fall between 1st and 15th to count toward the 365-day requirement

---

## Step 3: Deductions

All rates are configurable via system settings. Deductions are subtracted from the employee's salary.

### 3.1 PVD / Saving Fund (7.5%)

**Type depends on employee nationality:**

| Employee Type | Fund Type | Applies? |
|--------------|-----------|---------|
| Thai citizens | PVD (Provident Fund) | Yes |
| Non-Thai ID staff | Saving Fund | Yes |
| Expat / Local staff | Neither | No |

**Rules:**
- Rate: **7.5%** of salary (configurable)
- Both employee AND employer contribute (matching)
- Uses 30-day calculation basis
- **Toggled per employee** — HR enables via checkbox. If false, no calculation
- **Only after probation** — during probation, NO PVD/Saving Fund

```
pvd_employee = gross_salary_by_fte × 0.075
pvd_employer = gross_salary_by_fte × 0.075
total_pvd = pvd_employee + pvd_employer
```

### 3.2 Social Security (5%)

Social Security is calculated **from the start date** (including during probation — no probation gate).

**Rules:**
- Rate: **5%** of salary
- Cap: THB **875**/month (when salary ≥ 17,500)
- Salary range: min THB 1,650, max THB 17,500
- Expat/Local: varies by individual

**FTE-based allocation:** When salary exceeds 17,500, use `875 × FTE` per allocation:

| Allocation | FTE | Social Security |
|-----------|-----|----------------|
| Grant A | 0.8 | 875 × 0.8 = THB 700 |
| Grant B | 0.2 | 875 × 0.2 = THB 175 |
| Total | — | THB 875 |

**Important note:** Two or more funding allocations must collectively cover 100% FTE for the social security cap to work correctly.

### 3.3 Health Welfare (Health Insurance)

Health Welfare is calculated **from the start date** (including during probation — same as SSF).

**Non-Thai ID Staff (Employee + Employer):**

| Salary Range | Employee | Employer | Total |
|-------------|----------|----------|-------|
| ≤ THB 5,000 | 30/month | 60/month | 90/month |
| THB 5,001–15,000 | 50/month | 100/month | 150/month |
| THB 15,001+ | 75/month | 150/month | 225/month |

**Thai ID Staff (Employee Only, No Employer):**

| Salary Range | Employee | Employer | Total |
|-------------|----------|----------|-------|
| ≤ THB 5,000 | 50/month | 0 | 50/month |
| THB 5,001–15,000 | 80/month | 0 | 80/month |
| THB 15,001+ | 100/month | 0 | 100/month |

**Expat / Local Staff:**
- No PVD or Saving Fund
- Health Welfare varies by individual
- Social Security varies by individual

### 3.4 Tax (Thai Personal Income Tax)

**Tax is deducted from ONE grant only**, even when the employee has multiple funding sources.

Thailand uses a **progressive tax system** (0% to 35%).

**Tax Eligibility (ALL must be true):**
1. Employee has `tax_number` (not null)
2. Employee is paid via bank transfer (`bank_account_number` is not null)

If either is missing → no tax deducted.

#### Withholding Method: ACM vs CAM

| | CAM (Calculation In Advance) | ACM (Accumulative Calculation) |
|---|---|---|
| Used by | Government organizations | Private companies |
| How it projects | `current_salary × 12` | `YTD_income + (current_salary × remaining_months)` |
| When salary changes | Just re-annualizes; ignores past months | Incorporates actual YTD; self-corrects |
| Accuracy | Less accurate; large year-end adjustment | More accurate; minimal year-end adjustment |

**SMRU/BHF uses ACM** — the standard for private companies in Thailand.

#### ACM Step-by-Step Calculation

**Step A: Estimate Annual Income**

```
estimated_annual_income = ytd_income_so_far + (current_monthly_salary × remaining_months_in_year)
```

Where:
- `ytd_income_so_far` = sum of all actual gross income from Jan to current month (inclusive)
- `current_monthly_salary` = salary being paid THIS month (probation or post-probation)
- `remaining_months_in_year` = months after current month (e.g., in April: 12 - 4 = 8)

**For mid-year hires:** If the employee starts in April, remaining months = 8, NOT 12. The system only annualizes from the actual start month.

**Step B: Calculate Deductions**

| Deduction Item | Rule | Max (THB) |
|---------------|------|-----------|
| Personal Expenses | 50% of annual income | 100,000 |
| Personal Allowance | Fixed amount | 60,000 |
| Provident Fund (PVD) | Actual PVD contribution | Varies |
| Spouse Allowance | If spouse has no income | 60,000 |
| Children Allowance | Per child | 30,000 each |
| Social Security | Actual SS contribution | 10,500 (875×12) |

```
total_deductions = personal_expenses + personal_allowance + pvd + spouse + children + social_security
```

**Step C: Net Taxable Income**

```
net_taxable_income = annual_income - total_deductions
```

**Step D: Progressive Tax Brackets**

| Taxable Income (THB) | Rate | Max Tax | Cumulative Tax |
|---------------------|------|---------|---------------|
| 0 – 150,000 | 0% | 0 | 0 |
| 150,001 – 300,000 | 5% | 7,500 | 7,500 |
| 300,001 – 500,000 | 10% | 20,000 | 27,500 |
| 500,001 – 750,000 | 15% | 37,500 | 65,000 |
| 750,001 – 1,000,000 | 20% | 50,000 | 115,000 |
| 1,000,001 – 2,000,000 | 25% | 250,000 | 365,000 |
| 2,000,001 – 4,000,000 | 30% | 600,000 | 965,000 |
| Over 4,000,000 | 35% | — | — |

**Step E: Monthly Tax (ACM De-annualization)**

```
monthly_tax = (estimated_annual_tax - ytd_tax_already_withheld) / remaining_months_in_year
```

Where:
- `estimated_annual_tax` = tax from Step D
- `ytd_tax_already_withheld` = sum of tax withheld from Jan to PREVIOUS month
- `remaining_months_in_year` = months from current month to December (inclusive). Jan = 12, Apr = 9, Dec = 1

**Key insight:** ACM self-corrects. When salary changes (probation pass, promotion), the formula automatically adjusts future monthly withholdings to converge on the correct annual tax.

#### ACM Projection Rule

When projecting annual income, the system must use the **full-month salary** for the projection component, NOT a partial or split salary:

| Scenario | YTD Uses | Projection Uses |
|---------|----------|----------------|
| Partial start month | Actual partial amount | Full-month salary |
| Full probation month | Actual amount | Full-month probation salary |
| Split probation/post month | Actual split amount | Full-month POST-probation salary |
| Full post-probation month | Actual amount | Full-month post-probation salary |

**Key principle:** `ytd_income` always uses ACTUAL amounts paid. The projection always uses the FULL-MONTH equivalent of the current salary.

#### Worked Examples in Document

The document provides **three complete ACM worked examples** with full calculation tables:

1. **Simple probation → post-probation** (8,000 → 10,000): Tax = 0 throughout because income below threshold
2. **Higher salary (30,000/month, no probation change):** Demonstrates stable ACM where monthly tax is constant (52.08/month)
3. **Complex: Mid-month start + mid-month probation transition** (start Jan 15, probation salary 10,000, pass probation Mar 15 at 14,000): Shows three different monthly income amounts (5,328 → 10,000 → 12,134 → 14,000) and how ACM handles each

#### Key Rules for Tax

- Tax is calculated from the employee's **start date** — from the very first paycheck, regardless of probation
- Thai law requires employer to withhold PIT the moment the employee earns income
- Thai tax law has NO special rules for probation — when salary changes for any reason, the system recalculates using the new salary in ACM
- Tax is deducted from **ONE grant only** (not split across allocations)
- PVD/Saving Fund contributions are **tax-deductible**
- Social Security contributions are **tax-deductible**
- Spouse allowance (60,000) applies only if spouse has no assessable income
- Children allowance is 30,000 per child
- Social Security deduction for 2026 is THB 10,500/year (875 × 12), increased from 9,000 due to the wage ceiling change from 15,000 to 17,500

#### Year-End Tax Adjustment

ACM withholding is a projection and may not be perfectly accurate until December:

1. **Recalculate** actual annual tax based on true YTD income (no projection needed in December)
2. **Compare** actual annual tax with total YTD tax withheld
3. **If under-withheld:** deduct the difference from December's pay
4. **If over-withheld:** employee claims refund via annual PND.91 tax return (filed by 31 March)
5. Any fraction from dividing tax by payment periods is added to the last payment of the year

**Important:** The Revenue Department only returns overpaid tax at year-end filing. The system does NOT refund mid-year.

### 3.5 Rounding Rules

**Rounding depends on the field type:**

| Field Category | Rounding Rule | Example |
|---------------|--------------|---------|
| Salary / Proration | Round to nearest whole baht (integer) | `round(10,000/30) × 16 = 5,328` |
| Deductions (SSF, PVD, HW) | Round to nearest whole baht (integer) | SSF cap = THB 875 |
| 13th Month Salary | Round to nearest whole baht (integer) | `round(17,165 × 6 / 12) = 8,583` |
| Tax | 2 decimal places | `monthly_tax = 625 / 12 = 52.08` |

When an employee has more than one funding allocation, the rounding method must be applied uniformly per calculation type across all grants.

### 3.6 Leave Without Pay Deduction

```
deduction = daily_rate × unpaid_leave_days
daily_rate = monthly_salary / 30
```

Shown as a separate line item on the payslip.

---

## Step 4: Employer Contributions

These are costs the employer pays **on top of** the employee's salary. They do NOT reduce take-home pay.

| Contribution | Rule |
|-------------|------|
| Employer Social Security | Same rate & cap as employee (5%, max THB 875) |
| Employer Health Welfare | Per tier table (Non-Thai only; Thai = 0) |
| Employer PVD / Saving Fund | Same rate as employee (7.5%) |

---

## Step 5: Income Additions

### 5.1 Thirteenth Month Salary

**Eligibility:** From `start_date` — no probation gate.

**Rules:**
- Includes both probation salary and post-probation salary months
- **13th Month Accrued** is manually entered by HR staff in the payroll record — it is NOT system-calculated during automated payroll processing
- Partial-year employees: `13th month = sum of all actual monthly gross salaries / 12` (always divide by 12)

**Open questions in document:**
- Which organization pays 13th month for transferred employees?
- Should retroactive adjustments be included or excluded from 13th month average?

### 5.1.1 Multi-Allocation 13th Month

When an employee has multiple allocations during the year, 13th month is calculated **per allocation independently**.

```
13th_month_per_allocation = round(SUM(gross_salary_by_FTE for all months active) / 12)
```

**Key rules:**
1. Each allocation calculates independently based on its own payroll history
2. Divisor is ALWAYS 12 regardless of months active
3. Historical (closed) allocations generate a separate December payroll record with only the 13th month
4. Employee total = SUM of all allocation-level amounts
5. Uses actual `gross_salary_by_FTE` from each month's payroll record

**Three worked cases documented:**

| Case | Scenario | Allocations | Total 13th Month |
|------|---------|------------|-----------------|
| 1 | Sequential 100% grants, different salaries | Grant A (17,165 × 6mo) + Grant B (14,000 × 3mo) + Grant C (16,500 × 3mo) | 8,583 + 3,500 + 4,125 = 16,208 |
| 2 | Sequential 100% grants, same salary | Core (10,000 × 5mo) + IHRP (10,000 × 7mo) | 4,167 + 5,833 = 10,000 |
| 3 | FTE split mid-year | IHRP 100% (5mo) + IHRP 30% (7mo) + AZH 70% (7mo) | 4,167 + 1,750 + 4,083 = 10,000 |

### 5.2 Retroactive Adjustment

- Stored in `retroactive_adjustment` field on the payroll table
- Can be positive (underpaid) or negative (overpaid)
- Most commonly triggered during probation-to-pass-probation transitions

### 5.3 Salary Bonus

Additional bonus amounts when applicable.

---

## Step 6: Final Amounts

Four derived totals:

```
total_deductions = pvd + saving_fund + social_security_employee + health_welfare_employee + tax

total_income = gross_salary_by_fte + thirteen_month_salary + retroactive_adjustment + salary_bonus

net_salary = total_income - total_deductions

total_salary_cost = net_salary + total_deductions + employer_cost
```

**Important:** `total_salary_cost` represents the true cost to the organization per employee — their net pay plus all deductions plus all employer-side contributions.

---

## Step 7: Multi-Grant Funding

### 7.1 Per-Allocation Payroll

When an employee is funded by multiple grants, **one payroll record is created per active allocation.**

- Each allocation uses its own FTE for salary calculation
- Deductions (SSF, PVD) are split proportionally by FTE
- **Tax is deducted from ONE grant only** (not split)

### 7.2 Inter-Organization Advances

If the employee's organization differs from the grant's organization, an inter-organization advance is created.

Example: Employee belongs to SMRU but funded by a BHF grant → Advance from BHF to SMRU.

### 7.3 Hub Grants

Each organization has a central hub/general fund:

| Organization | Grant Code | Fund Name |
|-------------|-----------|-----------|
| SMRU | S0031 | Other Fund |
| BHF | S22001 | General Fund |

### 7.4 Organization Transfer (SMRU ↔ BHF)

**Clean month boundary (e.g., effective 1/Jan):**
- Last month payroll under old org
- New month payroll under new org

**Mid-month transfer (e.g., effective 15th):**
- Days 1–14 charged to old organization (prorated)
- Days 15–30 charged to new organization (prorated)

**Continuity rules:**
- YTD tax withholding **carries over** — annual tax is continuous, not reset
- Leave balance **carries over** to new organization
- 13th month uses **full 12-month history** across both organizations

---

## Step 8: Pay Period & Payment Date

HR **manually selects** the pay date using a date picker. The system does NOT auto-calculate.

**General guidelines:**

| Month | Rule |
|-------|------|
| Normal months | Usually the 25th. If Saturday/Sunday, move backward to 24th/23rd |
| December | Usually the 20th. If Sunday, move backward or forward (18th or 21st) |

---

## Step 9: Bulk Payroll Processing

### 9.1 Preview (Dry Run)

- Filter eligible employees by: subsidiaries, departments, grants
- Calculate payroll for each employee + allocation without saving
- Collect totals, warnings, inter-org advance needs
- Return summary with optional detailed per-employee breakdown

### 9.2 Batch Creation & Processing

1. Create a `BulkPayrollBatch` record (status: pending)
2. Dispatch async background job (`ProcessBulkPayroll`) for 500+ employees
3. Track progress: processed/total count, success/fail, percentage
4. Log errors per employee/allocation to batch record

### 9.3 Error Handling

- Errors per employee/allocation logged to batch
- Downloadable error report (CSV) after processing completes

---

## Step 10: Payroll Output

### 10.1 Payslip Contents

**Incomes Section:**

| Field |
|-------|
| Gross Salary (by FTE) |
| 13th Month Salary |
| 13th Month Accrued (manually entered by HR) |
| Retroactive Adjustment |
| Salary Bonus |
| Total Income |

**Deductions Section:**

| Field |
|-------|
| PVD / Saving Fund |
| Social Security (Employee) |
| Health Welfare (Employee) |
| Tax |
| Total Deductions |

### 10.2 Payslip Notes

- Replace the payment method field at the bottom with **Payroll Notes**
- Notes are auto-generated during payroll calculation

---

## Step 11: Configurable Settings

All values must be manageable via system settings — never hardcoded:

| Setting | Default | Type |
|---------|---------|------|
| Social Security Rate | 5% | Percentage |
| Social Security Cap | THB 875 | Amount |
| Social Security Min Salary | THB 1,650 | Amount |
| Social Security Max Salary | THB 17,500 | Amount |
| PVD / Saving Fund Rate | 7.5% | Percentage |
| Health Welfare Tiers (Non-Thai) | See Step 3.3 | Tiered |
| Health Welfare Tiers (Thai) | See Step 3.3 | Tiered |
| Annual Salary Increase | 1% | Percentage |
| 30-Day Month Basis | 30 | Fixed |

---

## Step 12: Data Migration & Uploads

1. Finalize payroll database tables and schema
2. Prepare Excel upload template matching schema
3. Build upload method to import existing payroll data
4. Validate imported data against HRMS calculations
5. Reconcile differences between Excel data and system calculations

---

## Step 13: Edge Cases & System Behavior

| Edge Case | Required Behavior |
|-----------|------------------|
| **Duplicate Prevention** | Prevent running payroll twice for same employee + month + allocation. Warn or safely replace |
| **Negative Net Pay** | If deductions > income, net salary must not go below zero. Flag for HR review |
| **Future-Dated Employees** | Employees with start_date after pay period end are excluded |
| **Empty Payroll** | If no eligible employees, return clear message without errors |

---

## Key Findings & Observations

### 1. The 30-Day Month is Universal

Every single calculation — proration, daily rates, probation splits, retroactive — uses 30 days. No exceptions. This simplifies the system but means an employee working February (28 days) gets the same daily rate as one working in March (31 days).

### 2. Probation Transition is the #1 Pain Point

The document explicitly flags this as the most error-prone area. The transition month requires splitting salary between two rates, updating funding allocations transactionally, and correctly handling the retroactive adjustment. The `pass_probation_date` day itself counts as a post-probation day.

### 3. FTE Lives on Allocations, Not Employment

There is NO FTE field on the employment table. FTE comes exclusively from `employee_funding_allocation` records. This is critical — salary is always full salary × allocation FTE, and an employee can have multiple allocations summing to 100%.

### 4. Tax is Single-Grant, Everything Else is Split

Tax is the only deduction that goes to **one grant only**. SSF, PVD, and HW are all split proportionally by FTE across allocations. This creates an asymmetry in the per-allocation payroll records.

### 5. ACM is Self-Correcting

The Accumulative Calculation Method naturally handles salary changes mid-year. When a salary increases (probation pass, promotion), the system doesn't need to recalculate past months — the formula's YTD component already captured actual historical payments, and the projection component uses the new salary going forward. The monthly withholding automatically adjusts.

### 6. PVD Has a Probation Gate, SSF/HW Do Not

- **PVD/Saving Fund:** Only after probation AND manually enabled via checkbox
- **Social Security:** From start date (day 1), during probation
- **Health Welfare:** From start date (day 1), during probation

### 7. Multi-Allocation 13th Month is Per-Allocation

The 13th month salary is not calculated once for the employee — it's calculated independently per allocation. Historical (closed) allocations generate separate December payroll records with only the 13th month populated. The divisor is always 12 regardless of months active.

### 8. Inter-Organization Transfers are Complex

When an employee transfers between SMRU and BHF:
- YTD tax withholding must carry over (continuous annual tax)
- Leave balance carries over
- 13th month uses full 12-month history
- Mid-month transfers require prorated split between organizations
- Several questions remain unconfirmed by HR (who pays 13th month, retro inclusion)

### 9. Tax Eligibility is Conditional

Not every employee gets tax deducted. Two conditions must both be true:
- Has a `tax_number` (not null)
- Has a `bank_account_number` (not null) — implying bank transfer payment

Cash-paid employees (those without bank accounts) = no tax deduction.

### 10. Year-End Tax is Mandatory

December payroll must perform a reconciliation: compare actual annual tax liability against total YTD withholdings, and adjust the final payment accordingly. Over-withholding is NOT refunded by the system — the employee files PND.91 by March 31.

### 11. Remaining Unconfirmed Business Rules

The document contains explicit `[HR to confirm]` markers for:
- Which organization pays 13th month for transferred employees
- Whether retroactive adjustments are included in 13th month average
- Health Welfare tiers and PVD rate (noted as organization-specific, may differ between SMRU and BHF)

**Resolved:** Rounding rules are now confirmed — salary/deductions/13th month use integer rounding (whole baht); tax uses 2 decimal places. See Step 3.5.

### 12. Document References

The document cites 14 external sources for verification, including:
- Royal Gazette (Thai Government) for Social Security 2026
- Thai Revenue Department for PIT brackets
- PwC, KPMG, BDO for tax summaries
- Principal Thailand for Provident Fund rules
- CXC Global for general Thailand payroll 2026 guide
