# 13th Month Salary — Deep Research & Implementation Analysis

**Date**: 2026-03-04

---

## 1. Executive Summary

The 13th month salary is calculated from **already-processed payroll records** in the `payrolls` table — NOT from the `employee_funding_allocations` table. It queries historical payroll data per allocation, sums the `gross_salary_by_FTE` field across all months, and divides by 12.

**Key answer**: In December, the system reads all payroll records (Jan–Nov) already stored in the database for each allocation, adds the current December month's `gross_salary_by_FTE`, and divides by 12. For historical (inactive/closed) allocations, it reads ALL their year payrolls since they have no current December month to add.

---

## 2. The Two Distinct Calculations

The system has **two separate 13th-month calculations** that serve different purposes:

| | 13th Month Salary (Payout) | 13th Month Accrued (Projection) |
|---|---|---|
| **When calculated** | December ONLY | Every month (Jan–Dec) |
| **Purpose** | Actual payout — added to employee's income | Informational — shows projected December payout |
| **DB field** | `thirteen_month_salary` | `thirteen_month_salary_accured` |
| **Affects net pay** | Yes — included in total income | No — informational only |
| **Formula** | Same | Same |

Both use the same formula: `(YTD gross_salary_by_FTE + current month gross_salary_by_FTE) / divisor`

---

## 3. Data Source: `payrolls` Table (NOT `employee_funding_allocations`)

### 3.1 How YTD Data is Retrieved

**Method**: `getYtdGrossSalaryByFTE()` — `PayrollService.php:1927–1945`

```php
private function getYtdGrossSalaryByFTE($employment, ?EmployeeFundingAllocation $allocation, Carbon $payPeriodDate): float
{
    $ytdPayrolls = Payroll::where('employment_id', $employment->id)
        ->where('employee_funding_allocation_id', $allocation->id)
        ->whereYear('pay_period_date', $payPeriodDate->year)
        ->where('pay_period_date', '<', $payPeriodDate->copy()->startOfMonth())
        ->get();

    $total = 0.0;
    foreach ($ytdPayrolls as $p) {
        $total += (float) $p->gross_salary_by_FTE;
    }
    return $total;
}
```

**What it queries**: The `payrolls` table — actual processed payroll records.

**Filters**:
- Same `employment_id` (employee's employment)
- Same `employee_funding_allocation_id` (per-allocation, NOT all allocations combined)
- Same year as the pay period
- **Before the current month** (`pay_period_date < start of current month`)

**What it sums**: The `gross_salary_by_FTE` column from each payroll record. This column is **encrypted** in the database, so the sum must be done in PHP (not SQL).

**Why the `payrolls` table and not `employee_funding_allocations`?** Because `gross_salary_by_FTE` varies month to month — it accounts for pro-rating (start month, resign month), probation salary transitions, retroactive adjustments, and FTE changes. The funding allocation table only stores the allocation metadata (FTE, grant, status), not the actual monthly salary paid.

### 3.2 What `gross_salary_by_FTE` Contains

Each payroll record's `gross_salary_by_FTE` = the employee's adjusted gross salary × FTE × (days worked / 30).

For example, an employee with ฿30,000 salary at 56% FTE:
- Full month: ฿30,000 × 0.56 = ฿16,800
- Start month (day 20): ฿30,000 × (11/30) × 0.56 = ฿6,160
- Probation transition month: pro-rated salary × 0.56

This means the 13th month calculation automatically includes the correct salary for each month — whether probation salary, post-probation salary, or pro-rated partial months.

---

## 4. December Payout — Active Allocations

### 4.1 Method: `calculateThirteenthMonthSalaryAmount()`

**File**: `PayrollService.php:1421–1454`

```php
private function calculateThirteenthMonthSalaryAmount(
    Employee $employee, $employment, Carbon $payPeriodDate,
    float $grossSalaryCurrentYearByFTE,
    ?EmployeeFundingAllocation $allocation = null
): float {
    $policy = PayrollPolicySetting::getActivePolicy();
    $enabled = $policy?->thirteenth_month_enabled ?? true;
    if (! $enabled) { return 0.0; }

    // Gate: December only
    if ($payPeriodDate->month !== 12) { return 0.0; }

    // No probation gate — eligible from start_date

    // Sum YTD (Jan–Nov) from payrolls table + current December month
    $ytdGrossByFTE = $this->getYtdGrossSalaryByFTE($employment, $allocation, $payPeriodDate);
    $totalYearGrossByFTE = $ytdGrossByFTE + $grossSalaryCurrentYearByFTE;

    if ($totalYearGrossByFTE <= 0) { return 0.0; }

    $divisor = $policy?->thirteenth_month_divisor ?? 12;
    return round($totalYearGrossByFTE / $divisor);
}
```

### 4.2 Step-by-Step Flow in December

```
December Payroll for Active Allocation:

1. calculateAllocationPayroll() called with December date
   │
2. Step 2: Calculate grossSalaryCurrentYearByFTE for December
   │  e.g., ฿30,000 × 0.56 FTE = ฿16,800 (December's portion)
   │
3. Step 4: Call calculateThirteenthMonthSalaryAmount()
   │
4. │→ Gate: Is it December? YES → proceed
   │
5. │→ Call getYtdGrossSalaryByFTE()
   │   │→ Query payrolls WHERE:
   │   │   - employment_id = this employee's employment
   │   │   - allocation_id = this specific allocation
   │   │   - year = 2026
   │   │   - pay_period_date < 2026-12-01  (Jan–Nov only)
   │   │
   │   │→ Results: 11 payroll records (Jan through Nov)
   │   │→ Sum their gross_salary_by_FTE values
   │   │   e.g., ฿16,800 × 11 = ฿184,800
   │   │
   │   └→ Return ฿184,800
   │
6. │→ totalYearGrossByFTE = ฿184,800 + ฿16,800 = ฿201,600
   │
7. │→ thirteenthMonth = round(฿201,600 / 12) = ฿16,800
   │
8. └→ Return ฿16,800 (added to December's total income)
```

### 4.3 How 13th Month Affects December Payroll

In the `calculateAllocationPayroll()` method, the 13th month is included in:

```php
// Line 1028: Added to total income
$totalIncome = $grossSalaryCurrentYearByFTE + $retroactiveAdjustment + $thirteenthMonthSalary + $salaryBonus;

// Line 969-978: Included in net salary calculation
$netSalary = calculateNetSalary(
    $grossSalaryCurrentYearByFTE,
    $retroactiveAdjustment,
    $thirteenthMonthSalary,  // ← included here
    $salaryBonus,
    ...deductions...
);
```

So in December, the employee receives their regular monthly salary PLUS the 13th month bonus, minus all deductions.

---

## 5. December Payout — Historical (Inactive/Closed) Allocations

### 5.1 The Problem

An employee may have had an allocation active from January to August, then it was deactivated. By December, the allocation status is "Inactive" or "Closed". But the employee earned salary on that grant for 8 months — they deserve their 13th month for those 8 months.

### 5.2 How Historical Allocations Are Found

**File**: `ProcessBulkPayroll.php:112–132`

```php
if ($isDecember && $employeeIds->isNotEmpty()) {
    $historicalAllocationIds = DB::table('payrolls')
        ->join('employee_funding_allocations', ...)
        ->whereIn('employee_funding_allocations.employee_id', $employeeIds->toArray())
        ->whereIn('employee_funding_allocations.status', ['inactive', 'closed'])
        ->whereYear('payrolls.pay_period_date', $payPeriodDate->year)
        ->distinct()
        ->pluck('payrolls.employee_funding_allocation_id');
}
```

The system finds historical allocations by joining the `payrolls` table with `employee_funding_allocations` to identify allocations that:
- Have status = `inactive` or `closed`
- Have at least one payroll record in the current year

### 5.3 Method: `calculateHistoricalAllocation13thMonth()`

**File**: `PayrollService.php:824–932`

```php
public function calculateHistoricalAllocation13thMonth(
    Employee $employee, EmployeeFundingAllocation $allocation, Carbon $payPeriodDate
): ?array {
    // Gate: December only
    if ($payPeriodDate->month !== 12) { return null; }

    // Gate: 13th month enabled in policy
    $policy = PayrollPolicySetting::getActivePolicy();
    if (! ($policy?->thirteenth_month_enabled ?? true)) { return null; }

    // Duplicate prevention: skip if December payroll already exists
    $existingDecember = Payroll::where('employment_id', $employment->id)
        ->where('employee_funding_allocation_id', $allocation->id)
        ->whereYear('pay_period_date', $payPeriodDate->year)
        ->whereMonth('pay_period_date', 12)
        ->exists();
    if ($existingDecember) { return null; }

    // Query ALL payrolls for this allocation in the year
    // Note: NO "before current month" exclusion — includes all months
    $yearPayrolls = Payroll::where('employment_id', $employment->id)
        ->where('employee_funding_allocation_id', $allocation->id)
        ->whereYear('pay_period_date', $payPeriodDate->year)
        ->get();

    if ($yearPayrolls->isEmpty()) { return null; }

    // Sum gross_salary_by_FTE across all months
    $totalYearGrossByFTE = 0.0;
    foreach ($yearPayrolls as $p) {
        $totalYearGrossByFTE += (float) $p->gross_salary_by_FTE;
    }

    $divisor = $policy?->thirteenth_month_divisor ?? 12;
    $thirteenthMonthAmount = round($totalYearGrossByFTE / $divisor);

    // Return payroll structure with ONLY 13th month fields populated
    return [
        'calculations' => [
            'thirteen_month_salary' => $thirteenthMonthAmount,
            'thirteen_month_salary_accured' => $thirteenthMonthAmount,
            'net_salary' => $thirteenthMonthAmount,
            'total_income' => $thirteenthMonthAmount,
            // ... all other fields = 0 (no salary, no deductions, no tax)
        ],
    ];
}
```

### 5.4 Key Difference: Active vs Historical

| Aspect | Active Allocation (December) | Historical Allocation (December) |
|--------|-----|------|
| **Query** | `getYtdGrossSalaryByFTE()` — Jan to Nov only | ALL year payrolls (no month exclusion) |
| **Current month added** | Yes — December's `grossSalaryCurrentYearByFTE` is added | No — allocation is inactive, no current month salary |
| **Other salary fields** | Full payroll (salary, deductions, tax, etc.) | Only 13th month — all other fields are zero |
| **Deductions applied** | Yes — tax, SSF, PVD etc. applied to December total | No — zero deductions (13th month only) |
| **Payroll record notes** | `null` | `'13th month salary - historical allocation'` |

### 5.5 Example: Historical Allocation

```
Employee worked on Grant A (60% FTE, salary ฿40,000) from Jan–Aug, then allocation closed.
Monthly gross_by_FTE = ฿40,000 × 0.60 = ฿24,000

Payroll records exist: Jan ฿24,000, Feb ฿24,000, ..., Aug ฿24,000
Total = ฿24,000 × 8 = ฿192,000

13th month = ฿192,000 / 12 = ฿16,000

December payroll record created:
- thirteen_month_salary: ฿16,000
- net_salary: ฿16,000
- all other fields: 0
- notes: "13th month salary - historical allocation"
```

---

## 6. Monthly Accrual (Non-December Months)

### 6.1 Where It's Calculated

**File**: `PayrollService.php:1032–1044` (inside `calculateAllocationPayroll()`)

```php
// 13th month accrued: YTD-based projection of December payout
$policy = PayrollPolicySetting::getActivePolicy();
$thirteenthMonthEnabled = $policy?->thirteenth_month_enabled ?? true;
$divisor = $policy?->thirteenth_month_divisor ?? 12;

if ($thirteenthMonthEnabled) {
    $ytdGrossByFTE = $this->getYtdGrossSalaryByFTE($employment, $allocation, $payPeriodDate);
    $thirteenthMonthAccrued = round(($ytdGrossByFTE + $grossSalaryCurrentYearByFTE) / $divisor);
} else {
    $thirteenthMonthAccrued = 0;
}
```

### 6.2 How Accrual Progresses Through the Year

Example: Employee with ฿30,000 salary, 100% FTE, started January 1:

| Month | YTD Gross (prior months) | Current Month | Total | Accrued (÷12) |
|-------|-------------------------|---------------|-------|----------------|
| Jan | ฿0 | ฿30,000 | ฿30,000 | ฿2,500 |
| Feb | ฿30,000 | ฿30,000 | ฿60,000 | ฿5,000 |
| Mar | ฿60,000 | ฿30,000 | ฿90,000 | ฿7,500 |
| Apr | ฿90,000 | ฿30,000 | ฿120,000 | ฿10,000 |
| ... | ... | ... | ... | ... |
| Nov | ฿300,000 | ฿30,000 | ฿330,000 | ฿27,500 |
| **Dec** | **฿330,000** | **฿30,000** | **฿360,000** | **฿30,000** (payout) |

- **Jan–Nov**: `thirteen_month_salary = 0` (no payout), `thirteen_month_salary_accured = projection`
- **December**: `thirteen_month_salary = ฿30,000` (actual payout), `thirteen_month_salary_accured = ฿30,000`

### 6.3 Accrual with Salary Change (Probation Transition)

Example: Probation salary ฿35,000, passes probation in July at ฿43,000, 100% FTE:

| Month | Salary | YTD | Current | Total | Accrued (÷12) |
|-------|--------|-----|---------|-------|----------------|
| Jan | ฿35,000 | ฿0 | ฿35,000 | ฿35,000 | ฿2,917 |
| ... | ฿35,000 | ... | ... | ... | ... |
| Jun | ฿35,000 | ฿175,000 | ฿35,000 | ฿210,000 | ฿17,500 |
| Jul | ฿43,000 | ฿210,000 | ฿43,000 | ฿253,000 | ฿21,083 |
| ... | ฿43,000 | ... | ... | ... | ... |
| **Dec** | ฿43,000 | ฿468,000 | ฿43,000 | ฿468,000 | **฿39,000** |

The 13th month naturally includes BOTH salary levels because it uses actual `gross_salary_by_FTE` from each stored payroll record.

---

## 7. Per-Allocation, Not Per-Employee

A critical design decision: **each funding allocation calculates its own 13th month independently**.

If an employee has two allocations:
- Allocation A: 60% FTE → 13th month based on Allocation A's monthly `gross_salary_by_FTE` values
- Allocation B: 40% FTE → 13th month based on Allocation B's monthly `gross_salary_by_FTE` values

The `getYtdGrossSalaryByFTE()` method filters by `employee_funding_allocation_id`, ensuring each allocation only sums its own payroll records.

### Example: Two Allocations

Employee salary ฿30,000, worked full year:

| Allocation | FTE | Monthly Gross by FTE | YTD (12 months) | 13th Month |
|-----------|-----|---------------------|-----------------|------------|
| A (60%) | 0.60 | ฿18,000 | ฿216,000 | ฿18,000 |
| B (40%) | 0.40 | ฿12,000 | ฿144,000 | ฿12,000 |
| **Total** | 1.00 | ฿30,000 | ฿360,000 | **฿30,000** |

Each allocation gets its own December payroll record with its proportional 13th month amount.

---

## 8. Policy Configuration

**Model**: `PayrollPolicySetting` — `app/Models/PayrollPolicySetting.php`

| Field | Default | Used in Code? | Purpose |
|-------|---------|---------------|---------|
| `thirteenth_month_enabled` | `true` | Yes | Toggle entire 13th month feature on/off |
| `thirteenth_month_divisor` | `12` | Yes | Divisor in the formula (always 12 in practice) |
| `thirteenth_month_min_months` | `6` | **No** | Minimum months to qualify — stored but NOT checked |
| `thirteenth_month_accrual_method` | `'monthly'` | **No** | Accrual method — stored but NOT used |

**Note**: `thirteenth_month_min_months` and `thirteenth_month_accrual_method` are defined in the database schema and seeder but are not referenced in any calculation logic. If these need to be enforced, the code must be updated.

---

## 9. Database Storage

**Model**: `Payroll` — `app/Models/Payroll.php`

| Column | Type | Encrypted | Description |
|--------|------|-----------|-------------|
| `thirteen_month_salary` | text | Yes | December payout amount (0 for Jan–Nov) |
| `thirteen_month_salary_accured` | text | Yes | Monthly projection of December payout |

Both fields are stored as encrypted text in SQL Server. The encryption means aggregation (SUM, AVG) cannot be done at the database level — all summing happens in PHP after decryption.

---

## 10. Complete Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                    MONTHLY PAYROLL (Jan-Nov)                     │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  calculateAllocationPayroll()                                   │
│  │                                                              │
│  ├─ Step 4: calculateThirteenthMonthSalaryAmount()             │
│  │  └─ month !== 12 → return 0.0                               │
│  │     (thirteen_month_salary = 0, no payout)                   │
│  │                                                              │
│  └─ Accrual calculation (lines 1032-1044):                     │
│     ├─ getYtdGrossSalaryByFTE() → queries payrolls table       │
│     │  WHERE allocation_id = X AND year = Y                     │
│     │  AND pay_period_date < current month start                │
│     │  → SUM(gross_salary_by_FTE) for prior months              │
│     │                                                           │
│     └─ accrued = (ytd + currentMonth) / 12                     │
│        (thirteen_month_salary_accured = projection)             │
│                                                                 │
│  Result stored in payrolls table:                              │
│  ├─ thirteen_month_salary = 0                                   │
│  └─ thirteen_month_salary_accured = monthly projection          │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                   DECEMBER PAYROLL (Active)                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  calculateAllocationPayroll()                                   │
│  │                                                              │
│  ├─ Step 4: calculateThirteenthMonthSalaryAmount()             │
│  │  ├─ month === 12 → proceed                                  │
│  │  ├─ getYtdGrossSalaryByFTE() → Jan-Nov from payrolls table  │
│  │  ├─ total = ytd(Jan-Nov) + currentDecemberGrossByFTE        │
│  │  └─ return round(total / 12) → ACTUAL PAYOUT                │
│  │                                                              │
│  └─ 13th month included in:                                    │
│     ├─ total_income (added)                                     │
│     ├─ net_salary (added to income before deductions)           │
│     └─ total_salary (added)                                     │
│                                                                 │
│  Result stored in payrolls table:                              │
│  ├─ thirteen_month_salary = PAYOUT AMOUNT                       │
│  └─ thirteen_month_salary_accured = same amount                 │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│             DECEMBER PAYROLL (Historical/Closed)                │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  calculateHistoricalAllocation13thMonth()                       │
│  │                                                              │
│  ├─ Find inactive/closed allocations with YTD payroll records  │
│  │  (join payrolls + employee_funding_allocations)              │
│  │                                                              │
│  ├─ Query ALL payrolls for allocation in year                  │
│  │  (no "before current month" exclusion)                      │
│  │                                                              │
│  ├─ Sum gross_salary_by_FTE across all months                  │
│  ├─ thirteenthMonth = round(total / 12)                        │
│  │                                                              │
│  └─ Return special payroll structure:                           │
│     ├─ thirteen_month_salary = amount                           │
│     ├─ net_salary = amount (no deductions)                      │
│     └─ all other fields = 0                                     │
│                                                                 │
│  Result stored in payrolls table:                              │
│  ├─ thirteen_month_salary = PAYOUT AMOUNT                       │
│  ├─ net_salary = same (no deductions applied)                   │
│  └─ notes = "13th month salary - historical allocation"         │
└─────────────────────────────────────────────────────────────────┘
```

---

## 11. Source Code References

| Component | File | Lines | Method |
|-----------|------|-------|--------|
| 13th month payout (active) | PayrollService.php | 1421–1454 | `calculateThirteenthMonthSalaryAmount()` |
| 13th month payout (historical) | PayrollService.php | 824–932 | `calculateHistoricalAllocation13thMonth()` |
| YTD gross query | PayrollService.php | 1927–1945 | `getYtdGrossSalaryByFTE()` |
| Monthly accrual | PayrollService.php | 1032–1044 | Inline in `calculateAllocationPayroll()` |
| Called at step 4 | PayrollService.php | 967 | Inside `calculateAllocationPayroll()` |
| Historical detection | ProcessBulkPayroll.php | 112–132 | `handle()` method |
| Historical processing | ProcessBulkPayroll.php | 276–356 | December block in `handle()` |
| Preview (active) | BulkPayrollService.php | 206–253 | `processEmploymentForPreview()` |
| Preview (historical) | BulkPayrollService.php | 255–291 | December block in `processEmploymentForPreview()` |
| Policy settings | PayrollPolicySetting.php | 16–19 | Model schema |
| Policy defaults | PayrollPolicySettingSeeder.php | 21–36 | Default seeder values |
| DB columns | Payroll.php | 25–26 | Encrypted casts |

---

## 12. Summary: Answering the Key Questions

### Q: Is it calculated from the funding allocation table or from already-processed payroll records?

**Answer**: From **already-processed payroll records** in the `payrolls` table. The `getYtdGrossSalaryByFTE()` method queries `Payroll::where(employment_id, allocation_id, year, ...)` and sums the `gross_salary_by_FTE` column. The `employee_funding_allocations` table is only used for metadata (allocation ID, FTE, status) — not for salary amounts.

### Q: What happens in December specifically?

**Answer**: Two things happen:

1. **Active allocations**: The normal `calculateAllocationPayroll()` runs. At step 4, `calculateThirteenthMonthSalaryAmount()` detects it's December, queries Jan–Nov payrolls, adds December's current gross, divides by 12, and returns the payout amount. This is included in the employee's December net salary.

2. **Historical (inactive/closed) allocations**: `calculateHistoricalAllocation13thMonth()` runs separately. It queries ALL year payrolls for the closed allocation, divides by 12, and creates a special payroll record with only the 13th month amount (all other fields zero, no deductions).

### Q: Does it use `already processed months + current December month`?

**Answer**: **Yes, for active allocations.** The formula is:
```
13th_month = (sum of Jan–Nov gross_salary_by_FTE from payrolls table + December's calculated gross_salary_by_FTE) / 12
```

For historical allocations, there is no "current December month" since the allocation is inactive — so it only uses all previously processed months.
