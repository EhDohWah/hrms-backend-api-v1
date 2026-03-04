# Expats (Local) Payroll Analysis Report

**Date**: 2026-03-04
**Employee**: Staff ID 0099 — Santos Jacobson (Expats Local, SMRU)
**Pay Period**: February 2026

---

## 1. Summary of Findings

For the **"Expats (Local)"** employee, the API response correctly returns:

| Component | Employee | Employer | Explanation |
|-----------|----------|----------|-------------|
| **PVD / Saving Fund** | 0 | 0 | Expats are explicitly excluded — neither PVD nor Saving Fund applies |
| **Social Security** | 0 | 0 | Only Local ID (Thai) and Local non ID staff are eligible |
| **Health Welfare (Employee)** | 0 | — | Expats explicitly excluded from employee HW contribution |
| **Health Welfare (Employer)** | — | **84 / 66** | Expats at SMRU/BHF ARE eligible for employer-paid HW |
| **Tax** | **90.91 / 0** | — | Tax on ONE allocation only (highest FTE = 0.56) |

---

## 2. Detailed Breakdown by Component

### 2.1 PVD / Saving Fund = 0 (Correct)

**Method**: `PayrollService::calculatePVDSavingFund()` (lines 1459–1496)

The method has a three-way status check:

```
IF status == "Local ID Staff" AND employment.pvd == true     → PVD at 7.5%
IF status == "Local non ID Staff" AND employment.saving_fund == true → Saving Fund at 7.5%
OTHERWISE → return 0 (catches Expats and any toggled-off employees)
```

**Why Expats get zero**: The status `"Expats (Local)"` doesn't match either `Local ID Staff` or `Local non ID Staff`, so the method returns `{ pvd_employee: 0, saving_fund: 0 }`.

**Business logic confirmation**: The business logic document explicitly states: **"Expat/Local staff: Neither"** — no PVD or Saving Fund. This is correct.

**API response data confirms**:
```json
"step_5_pvd_saving_fund": {
    "employment_pvd_toggle": false,
    "employment_saving_fund_toggle": false,
    "pvd_employee": 0,
    "saving_fund": 0
}
```

Note: Even if the HR toggles (`pvd`/`saving_fund`) were `true`, Expats would still get zero because the status check occurs AFTER the toggle check.

---

### 2.2 Social Security = 0 (Correct per Implementation, Ambiguous per Business Logic)

**Methods**:
- `calculateEmployeeSocialSecurity()` (lines 1537–1552)
- `calculateEmployerSocialSecurity()` (lines 1508–1527)

Both methods have the same gate:

```php
if (! in_array($employee->status?->value, [Employee::STATUS_LOCAL_ID, Employee::STATUS_LOCAL_NON_ID])) {
    return 0.0;
}
```

**Why Expats get zero**: Status `"Expats (Local)"` is not in the allowed list `["Local ID Staff", "Local non ID Staff"]`. Returns 0.0 immediately.

**Formula (if eligible)**:
- Effective salary = max(1,650, min(salary, 17,500))
- Contribution = min(effective_salary × 5%, 875)
- Final = contribution × FTE

**Business logic note**: The documentation says **"Expat/Local: varies by individual"** for Social Security. This means some Expats MAY need SSF (e.g., those with Thai work permits), but the current implementation blanket-excludes all Expats. This is a known ambiguity — see Section 4.

**API response data confirms**:
```json
"step_6_social_security": {
    "full_monthly_salary_input": 30000,
    "fte": "0.5600",
    "employer_ss": 0,
    "employee_ss": 0
}
```

---

### 2.3 Health Welfare — Employee = 0, Employer = 84/66

#### Employee Health Welfare = 0 (Correct)

**Method**: `calculateHealthWelfareEmployee()` (lines 1609–1642)

```php
// Line 1612: Direct exclusion
if ($employee->status?->value === Employee::STATUS_EXPATS) {
    return 0.0;
}
```

**Why Expats get zero**: Explicit status check returns 0.0 immediately. Code comment says: *"Skip for Expats by default (per-individual override can be added later)"*.

**Tier structure (if eligible)**:
| Salary Range | Thai (Local ID) | Non-Thai (Local non ID) |
|---|---|---|
| ≤ ฿5,000 | ฿50 | ฿30 |
| ฿5,001–15,000 | ฿80 | ฿50 |
| > ฿15,000 | ฿100 | ฿75 |

Expats don't use any tier — always zero.

#### Employer Health Welfare = 84 / 66 (Correct)

**Method**: `calculateHealthWelfareEmployer()` (lines 1564–1594)

This method uses a **configurable eligibility system** stored in `benefit_settings`:

```php
$eligibility = BenefitSetting::getEmployerHWEligibility();
$eligibleStatuses = $eligibility['eligible_statuses'] ?? [];
$eligibleOrgs = $eligibility['eligible_organizations'] ?? [];

if (! in_array($employee->organization, $eligibleOrgs) ||
    ! in_array($employee->status?->value, $eligibleStatuses)) {
    return 0.0;
}
```

**Configuration** (from `BenefitSettingSeeder`):
```php
'applies_to' => [
    'eligible_statuses' => ['Local non ID Staff', 'Expats (Local)'],
    'eligible_organizations' => ['SMRU', 'BHF'],
]
```

**Why Expats DO get employer HW**: `"Expats (Local)"` is explicitly listed in `eligible_statuses`, and the employee's organization `"SMRU"` is in `eligible_organizations`.

**Tier applied** (Non-Thai employer rates):
| Salary Range | Employer Amount |
|---|---|
| ≤ ฿5,000 | ฿60 |
| ฿5,001–15,000 | ฿100 |
| > ฿15,000 | ฿150 |

**Calculation for this employee** (salary = ฿30,000 > ฿15,000):
- Allocation 1: ฿150 × 0.56 FTE = **฿84**
- Allocation 2: ฿150 × 0.44 FTE = **฿66**

**API response confirms**:
```json
// Allocation 1 (56% FTE)
"step_7_health_welfare": { "employer_hw": 84, "employee_hw": 0 }

// Allocation 2 (44% FTE)
"step_7_health_welfare": { "employer_hw": 66, "employee_hw": 0 }
```

---

### 2.4 Tax = 90.91 on Allocation 1, 0 on Allocation 2

**Method**: `calculateIncomeTax()` (lines 1647–1710)

Tax is calculated on the **FULL gross salary** (not FTE-adjusted), then assigned to **one allocation only** — the one with the highest FTE.

- **Allocation 1** (FTE 0.56 — highest): `isTaxAllocation = true` → full tax = ฿90.91
- **Allocation 2** (FTE 0.44): `isTaxAllocation = false` → tax = ฿0

The tax is calculated using ACM (Accumulative Calculation Method):
1. Estimate annual income based on current month salary
2. Apply personal allowances (employment deduction 50% max ฿100K, personal allowance ฿60K)
3. Calculate progressive tax on taxable income
4. Monthly tax = (annual_tax − ytd_tax_withheld) / remaining_months

**API response confirms**:
```json
// Allocation 1
"step_8_tax": { "result_tax_by_fte": 90.91 }

// Allocation 2
"step_8_tax": { "result_tax_by_fte": 0 }
```

---

## 3. Complete Payroll Flow for This Employee

```
Employee: Santos Jacobson (Expats Local, SMRU)
Probation salary: ฿30,000 | Post-probation: ฿36,000
Start: 2026-01-20 | Pass probation: 2026-04-20 | Status: Still on probation
Pay period: February 2026

2 Allocations:
├── Allocation 6: 56% FTE → Grant S2022-NIH-4198
└── Allocation 7: 44% FTE → Grant S2020-WHO-7286
```

### Allocation 1 (56% FTE — Tax Allocation)

| Step | Item | Value | Formula |
|------|------|-------|---------|
| 1 | Gross Salary | ฿30,000 | Probation salary (hasn't passed probation) |
| 2 | Gross by FTE | ฿16,800 | ฿30,000 × 0.56 |
| 3 | Retroactive | ฿6,160 | Started Jan 20 (day > 15) → 11 deferred days × ฿1,000/day × 0.56 |
| 4 | 13th Month | ฿0 | Not December |
| 4a | 13th Month Accrued | ฿1,400 | (฿0 + ฿16,800) / 12 |
| 5 | PVD/Saving | ฿0 | Expats excluded |
| 6 | Employee SS | ฿0 | Expats excluded |
| 6 | Employer SS | ฿0 | Expats excluded |
| 7 | Employee HW | ฿0 | Expats excluded |
| 7 | Employer HW | ฿84 | ฿150 × 0.56 (salary > ฿15K, SMRU org) |
| 8 | Tax | ฿90.91 | ACM on full ฿30K salary (this is the tax allocation) |
| 9 | Total Income | ฿22,960 | ฿16,800 + ฿6,160 + ฿0 + ฿0 |
| 9 | Total Deductions | ฿90.91 | ฿0 + ฿0 + ฿0 + ฿90.91 |
| 9 | Net Salary | ฿22,869 | ฿22,960 − ฿90.91 (rounded) |
| 9 | Total Salary | ฿23,044 | ฿22,960 + ฿0 + ฿84 |

### Allocation 2 (44% FTE — Non-Tax Allocation)

| Step | Item | Value | Formula |
|------|------|-------|---------|
| 1 | Gross Salary | ฿30,000 | Same base salary |
| 2 | Gross by FTE | ฿13,200 | ฿30,000 × 0.44 |
| 3 | Retroactive | ฿4,840 | 11 deferred days × ฿1,000/day × 0.44 |
| 4a | 13th Month Accrued | ฿1,100 | (฿0 + ฿13,200) / 12 |
| 5-6 | PVD/SS | ฿0 | Expats excluded |
| 7 | Employer HW | ฿66 | ฿150 × 0.44 |
| 8 | Tax | ฿0 | Not the tax allocation |
| 9 | Net Salary | ฿18,040 | ฿18,040 − ฿0 |
| 9 | Total Salary | ฿18,106 | ฿18,040 + ฿0 + ฿66 |

### Combined Totals
- **Total Gross**: ฿30,000
- **Total Net**: ฿40,909 (฿22,869 + ฿18,040)

---

## 4. Known Ambiguities & Potential Issues

### 4.1 Social Security for Expats — "Varies by Individual"

The business logic document states SSF for Expats **"varies by individual"**, meaning some Expats with Thai work permits might need SSF deducted. The current implementation blanket-excludes all Expats.

**Impact**: If an Expat employee actually needs SSF, the system cannot accommodate them without code changes.

**Potential fix**: Add a per-employee toggle (e.g., `employee.ssf_eligible` boolean) similar to the PVD/Saving Fund toggle on Employment.

### 4.2 Employee Health Welfare for Expats — "Varies by Individual"

Same ambiguity. The code has a comment: *"Skip for Expats by default (per-individual override can be added later)"*.

**Impact**: Same as SSF — some Expats may need employee HW deducted.

**Potential fix**: Add a per-employee toggle (e.g., `employee.hw_eligible` boolean).

### 4.3 Tax Rounding in Preview vs Storage

The calculation returns tax as `90.91` (2 decimal places), but `buildAllocationDetail()` in BulkPayrollService uses `round($calc['tax'])` which rounds to integer (91). The stored database value preserves the 2dp precision.

**Impact**: Preview API shows `"tax": 91` but the actual payroll record stores `90.91`. Minor display inconsistency.

### 4.4 Total Net Salary = ฿40,909

The combined net salary across both allocations (฿22,869 + ฿18,040 = ฿40,909) is higher than the base salary (฿30,000) because of the ฿11,000 retroactive adjustment (deferred salary from January when the employee started on the 20th — 11 days × ฿1,000/day).

This is expected behavior — the retroactive adjustment catches up the unpaid portion from the previous month.

---

## 5. Eligibility Matrix — All Employee Statuses

| Benefit | Local ID (Thai) | Local non ID (Non-Thai) | Expats (Local) |
|---------|----------------|------------------------|----------------|
| **PVD** | 7.5% (if toggle ON + passed probation) | ❌ | ❌ |
| **Saving Fund** | ❌ | 7.5% (if toggle ON + passed probation) | ❌ |
| **Employee SSF** | 5% (capped ฿875) | 5% (capped ฿875) | ❌ |
| **Employer SSF** | 5% (capped ฿875) | 5% (capped ฿875) | ❌ |
| **Employee HW** | Tiered ฿50–100 | Tiered ฿30–75 | ❌ |
| **Employer HW** | ❌ | ✅ (SMRU/BHF, tiered ฿60–150) | ✅ (SMRU/BHF, tiered ฿60–150) |
| **Tax** | Progressive 0–35% | Progressive 0–35% | Progressive 0–35% |
| **13th Month** | ✅ | ✅ | ✅ |

---

## 6. Source Code References

| Component | File | Lines | Method |
|-----------|------|-------|--------|
| PVD/Saving Fund | PayrollService.php | 1459–1496 | `calculatePVDSavingFund()` |
| Employee SS | PayrollService.php | 1537–1552 | `calculateEmployeeSocialSecurity()` |
| Employer SS | PayrollService.php | 1508–1527 | `calculateEmployerSocialSecurity()` |
| Employee HW | PayrollService.php | 1609–1642 | `calculateHealthWelfareEmployee()` |
| Employer HW | PayrollService.php | 1564–1594 | `calculateHealthWelfareEmployer()` |
| Employer HW Eligibility | BenefitSetting.php | 166–182 | `getEmployerHWEligibility()` |
| Eligibility Config | BenefitSettingSeeder.php | 168–180 | Seeder data |
| Tax Calculation | PayrollService.php | 1647–1710 | `calculateIncomeTax()` |
| Tax Allocation | PayrollService.php | 793–805 | `determineTaxAllocationId()` |
| Preview API | BulkPayrollService.php | 327–387 | `buildAllocationDetail()` |
| API Endpoint | PayrollController.php | 119–133 | `bulkPreview()` |
| Employee Status | Employee.php | 71–76 | Status constants |
