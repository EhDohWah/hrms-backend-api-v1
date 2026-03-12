# Previous Year Salary — Implementation Plan

## Problem

The annual 1% salary increase runs in January. It currently calculates:

```
increase = pass_probation_salary × 1% = 300
January gross = pass_probation_salary + 300 = 30,300
```

But after January, the employee's `pass_probation_salary` is still 30,000 — the increase was a one-time addition to January's payroll only, not persisted. February payroll goes back to 30,000.

**What we need:**
1. Before January payroll runs, snapshot the current salary into `previous_year_salary`
2. Calculate the 1% increase from `previous_year_salary`
3. Update `pass_probation_salary` to the new salary (30,300)
4. February onwards uses the new 30,300 as base salary

---

## Business Rules

- Salary increase applies in **January only**
- Every employee who receives January payroll gets the increase
- Employees who start after Jan 15 don't get January payroll (existing 15th cutoff rule), so no increase
- Rate comes from `payroll_policy_settings` table (`salary_increase` policy, e.g. 1%)
- `previous_year_salary` stores the December salary as a frozen reference

---

## Current State (Before Changes)

### Employment Table — salary fields

| Column | Type | Purpose |
|---|---|---|
| `probation_salary` | decimal(10,2) nullable | Salary during probation |
| `pass_probation_salary` | decimal(10,2) NOT NULL | Current salary (post-probation) |

### PayrollService::calculateAnnualSalaryIncrease()

**File:** `app/Services/PayrollService.php` (line ~1855)

```php
private function calculateAnnualSalaryIncrease(Employee $employee, $employment, Carbon $payPeriodDate): float
{
    if ($payPeriodDate->month !== 1) {
        return 0.0;
    }

    $setting = PayrollPolicySetting::getActiveSetting(PayrollPolicySetting::KEY_SALARY_INCREASE);
    if ($setting === false) {
        return 0.0;
    }

    $rate = ((float) ($setting['policy_value'] ?? 1.00)) / 100;

    return round($employment->pass_probation_salary * $rate);
}
```

**Problem:** Returns a one-time bonus added to January gross. Does NOT update `pass_probation_salary`. February reverts to old salary.

### ProcessBulkPayroll Job

**File:** `app/Jobs/ProcessBulkPayroll.php`

Flow per employment:
1. Load employment + employee + allocations (line 85–110)
2. For each allocation: call `PayrollService::calculateAllocationPayrollForController()` (line 217)
3. Buffer payroll records, batch-insert every 10 (line 236–238)
4. After all done, update batch status (line 366)

**No salary update step exists anywhere in this flow.**

### Where pass_probation_salary is written today

Only 3 places write to this field:
1. `EmploymentService::create()` — when creating employment
2. `EmploymentService::update()` — when HR edits employment
3. `EmploymentsImport` — Excel bulk import

**PayrollService never writes to it.**

---

## Implementation Plan

### Step 1: Migration — add `previous_year_salary` to `employments`

**New file:** `database/migrations/2026_03_05_000001_add_previous_year_salary_to_employments_table.php`

```php
Schema::table('employments', function (Blueprint $table) {
    $table->decimal('previous_year_salary', 10, 2)
          ->nullable()
          ->after('pass_probation_salary');
});
```

- Nullable because first-year employees and existing records won't have it yet
- Placed right after `pass_probation_salary` for logical grouping

### Step 2: Employment Model — add field

**File:** `app/Models/Employment.php`

Add to `$fillable`:
```php
'previous_year_salary',
```

Add to `$casts`:
```php
'previous_year_salary' => 'decimal:2',
```

No new methods needed — just a data field.

### Step 3: EmploymentResource — expose field in API

**File:** `app/Http/Resources/EmploymentResource.php`

Add after `pass_probation_salary`:
```php
'previous_year_salary' => $this->previous_year_salary,
```

### Step 4: PayrollService — update calculateAnnualSalaryIncrease()

**File:** `app/Services/PayrollService.php` (line ~1855)

Change to use `previous_year_salary` as the base when available:

```php
private function calculateAnnualSalaryIncrease(Employee $employee, $employment, Carbon $payPeriodDate): float
{
    if ($payPeriodDate->month !== 1) {
        return 0.0;
    }

    $setting = PayrollPolicySetting::getActiveSetting(PayrollPolicySetting::KEY_SALARY_INCREASE);
    if ($setting === false) {
        return 0.0;
    }

    $rate = ((float) ($setting['policy_value'] ?? 1.00)) / 100;

    // Use previous_year_salary as base if available (set before January payroll runs).
    // Fall back to pass_probation_salary for first-year employees.
    $baseSalary = $employment->previous_year_salary ?? $employment->pass_probation_salary;

    return round($baseSalary * $rate);
}
```

### Step 5: PayrollService — add applyAnnualSalaryIncrease() method

**File:** `app/Services/PayrollService.php`

New public method that ProcessBulkPayroll calls after January payrolls are created:

```php
/**
 * Apply annual salary increase to all employments that received January payroll.
 *
 * Called once after bulk January payroll completes:
 * 1. Snapshot current pass_probation_salary → previous_year_salary
 * 2. Add increase to pass_probation_salary
 * 3. Recalculate funding allocation amounts for the new salary
 *
 * Idempotent: skips employments where previous_year_salary is already set for this year.
 */
public function applyAnnualSalaryIncrease(array $employmentIds, Carbon $payPeriodDate): array
{
    if ($payPeriodDate->month !== 1) {
        return ['updated' => 0, 'skipped' => 0];
    }

    $setting = PayrollPolicySetting::getActiveSetting(PayrollPolicySetting::KEY_SALARY_INCREASE);
    if ($setting === false) {
        return ['updated' => 0, 'skipped' => 0];
    }

    $rate = ((float) ($setting['policy_value'] ?? 1.00)) / 100;
    $updated = 0;
    $skipped = 0;

    $employments = Employment::whereIn('id', $employmentIds)
        ->whereNull('end_date')
        ->get();

    foreach ($employments as $employment) {
        // Idempotent: skip if previous_year_salary already set
        // (means increase was already applied this cycle)
        if ($employment->previous_year_salary !== null) {
            $skipped++;
            continue;
        }

        $currentSalary = (float) $employment->pass_probation_salary;
        $increase = round($currentSalary * $rate);
        $newSalary = $currentSalary + $increase;

        // Snapshot current salary, then update to new salary
        $employment->update([
            'previous_year_salary'   => $currentSalary,
            'pass_probation_salary'  => $newSalary,
            'updated_by'             => 'system:annual_increase',
        ]);

        // Recalculate funding allocation amounts for the new salary
        $employment->employeeFundingAllocations()
            ->where('status', 'active')
            ->each(function ($allocation) use ($newSalary) {
                $allocation->update([
                    'allocated_amount' => round($newSalary * $allocation->fte, 2),
                ]);
            });

        $updated++;
    }

    return ['updated' => $updated, 'skipped' => $skipped];
}
```

### Step 6: ProcessBulkPayroll — call applyAnnualSalaryIncrease after January payroll

**File:** `app/Jobs/ProcessBulkPayroll.php`

After the final batch insert (line ~363), add:

```php
// Insert remaining payrolls in buffer
if (! empty($payrollBuffer)) {
    $insertedPayrolls = $this->insertPayrollBatch($payrollBuffer);
    $advancesCreated += $this->createAdvancesForPayrolls($insertedPayrolls, $payrollService, $payPeriodDate);
}

// === NEW: Apply annual salary increase after January payroll is complete ===
$salaryIncreaseResult = ['updated' => 0, 'skipped' => 0];
if ($payPeriodDate->month === 1) {
    $salaryIncreaseResult = $payrollService->applyAnnualSalaryIncrease(
        $this->employmentIds,
        $payPeriodDate
    );

    Log::info("ProcessBulkPayroll: Annual salary increase applied", $salaryIncreaseResult);
}
```

Also add `salary_increase_applied` to the batch summary (line ~375):

```php
'summary' => [
    // ... existing fields ...
    'salary_increase_applied' => $salaryIncreaseResult['updated'] ?? 0,
],
```

### Step 7: Calculation breakdown — add previous_year_salary

**File:** `app/Services/PayrollService.php` (line ~1059, calculation breakdown)

Add to the `inputs` array:

```php
'inputs' => [
    // ... existing fields ...
    'previous_year_salary' => $employment->previous_year_salary,
],
```

### Step 8: EmploymentsImport — include field in import (optional)

**File:** `app/Imports/EmploymentsImport.php`

No change needed. The import creates/updates employments with `pass_probation_salary`. `previous_year_salary` is system-managed — HR doesn't manually set it.

### Step 9: EmploymentObserver — allow previous_year_salary changes

**File:** `app/Observers/EmploymentObserver.php`

Review if the observer has salary validation logic. If it validates `pass_probation_salary` changes, ensure `previous_year_salary` is also allowed in mass assignment and doesn't trigger unexpected validations.

---

## Data Flow — January Payroll Sequence

```
1. HR clicks "Generate January Payroll"
   ↓
2. ProcessBulkPayroll job starts
   ↓
3. For each employee:
   → calculateAnnualSalaryIncrease() reads previous_year_salary (or pass_probation_salary if null)
   → Returns increase amount (e.g. 300)
   → adjustedGrossSalary = base salary + 300
   → All payroll items calculated from adjustedGrossSalary
   → Payroll record created with the increase baked into gross
   ↓
4. After ALL January payrolls are created:
   → applyAnnualSalaryIncrease() runs ONCE
   → For each employment:
     a. previous_year_salary = 30,000 (snapshot)
     b. pass_probation_salary = 30,300 (new salary)
     c. Funding allocations recalculated
   ↓
5. February payroll onwards:
   → pass_probation_salary is already 30,300
   → calculateAnnualSalaryIncrease() returns 0 (not January)
   → Employee gets 30,300 as regular salary
```

---

## Idempotency

If January payroll is re-run (deleted and regenerated):
- `previous_year_salary` is already set → `applyAnnualSalaryIncrease()` skips (idempotent check)
- `calculateAnnualSalaryIncrease()` uses `previous_year_salary` (30,000) as base → correct increase

If you need to re-apply the increase after a rate change:
- Set `previous_year_salary = null` on affected employments
- Delete January payrolls
- Re-run bulk payroll

---

## Edge Cases

| Scenario | Behavior |
|---|---|
| First-year employee (no previous_year_salary) | Falls back to `pass_probation_salary` |
| Employee on probation in January | Gets increase on `pass_probation_salary` (probation salary is separate) |
| Employee resigned before January | `whereNull('end_date')` filter skips them |
| Salary increase policy is inactive | No increase calculated, no salary update |
| Rate changed mid-year | Only affects next January (rate is read at calculation time) |
| Employee with multiple funding allocations | All allocations recalculated with new salary × FTE |

---

## Files to Modify

| # | File | Change |
|---|---|---|
| 1 | `database/migrations/2026_03_05_*` | **New** — add `previous_year_salary` column to `employments` |
| 2 | `app/Models/Employment.php` | Add to `$fillable` and `$casts` |
| 3 | `app/Http/Resources/EmploymentResource.php` | Expose `previous_year_salary` in API response |
| 4 | `app/Services/PayrollService.php` | Update `calculateAnnualSalaryIncrease()` to use `previous_year_salary` as base |
| 5 | `app/Services/PayrollService.php` | **New method** `applyAnnualSalaryIncrease()` |
| 6 | `app/Jobs/ProcessBulkPayroll.php` | Call `applyAnnualSalaryIncrease()` after January payroll completes |
| 7 | `app/Services/PayrollService.php` | Add `previous_year_salary` to calculation breakdown |

---

## What NOT to Change

- **Seeder** — `previous_year_salary` is system-managed, not seeded
- **Form Requests** — HR doesn't manually set this field via the API
- **Import/Export** — system-managed field, not part of Excel templates
- **Frontend** — display-only if shown at all (read-only in employment detail)

---

## Implementation Todo List

### Phase 1: Database & Model Layer ✅

- [x] **1.1** Create migration `2026_03_05_000001_add_previous_year_salary_to_employments_table.php`
  - Add `previous_year_salary` decimal(10,2) nullable after `pass_probation_salary`
  - Write `down()` method to drop the column
- [x] **1.2** Run migration: `php artisan migrate`
- [x] **1.3** Update `app/Models/Employment.php`
  - Add `'previous_year_salary'` to `$fillable` array
  - Add `'previous_year_salary' => 'decimal:2'` to `$casts` array
- [x] **1.4** Review `app/Observers/EmploymentObserver.php`
  - Checked: salary validation triggers on `isDirty(['pass_probation_salary', 'probation_salary'])` — annual increase values are valid (>0, <1M)
  - No changes needed

### Phase 2: API Response Layer ✅

- [x] **2.1** Update `app/Http/Resources/EmploymentResource.php`
  - Add `'previous_year_salary' => $this->previous_year_salary` after `pass_probation_salary`
- [x] **2.2** Verify the field appears in API responses
  - Field is included in EmploymentResource — will appear in all employment API responses

### Phase 3: PayrollService — Calculation Logic ✅

- [x] **3.1** Update `calculateAnnualSalaryIncrease()` in `app/Services/PayrollService.php`
  - Changed base salary to `$employment->previous_year_salary ?? $employment->pass_probation_salary`
  - Added inline comment explaining the fallback logic
- [x] **3.2** Add `previous_year_salary` to calculation breakdown
  - Added `'previous_year_salary' => $employment->previous_year_salary` to `$calculationBreakdown['inputs']`

### Phase 4: PayrollService — Salary Persistence Method ✅

- [x] **4.1** Create `applyAnnualSalaryIncrease(array $employmentIds, Carbon $payPeriodDate): array` method
  - Guard: returns early if not January
  - Guard: returns early if salary_increase policy is inactive
  - Reads rate from policy setting
  - Queries employments by IDs where `end_date IS NULL`
  - Idempotency: skips if `previous_year_salary` is already set
  - Snapshots current salary, applies increase, recalculates funding allocations
  - Returns `['updated' => count, 'skipped' => count]`
- [x] **4.2** Logic verified by code review — idempotency, null handling, funding recalculation all correct

### Phase 5: ProcessBulkPayroll Integration ✅

- [x] **5.1** Edit `app/Jobs/ProcessBulkPayroll.php`
  - Added January check after final `insertPayrollBatch()` call
  - Calls `$payrollService->applyAnnualSalaryIncrease($this->employmentIds, $payPeriodDate)`
  - Logs the result with `Log::info()`
- [x] **5.2** Add salary increase result to batch summary
  - Added `'salary_increase_applied' => $salaryIncreaseResult['updated']` to the `'summary'` array

### Phase 6: Verification & Testing

- [ ] **6.1** Manual test: non-January payroll
  - Run February bulk payroll
  - Confirm `calculateAnnualSalaryIncrease()` returns 0
  - Confirm `applyAnnualSalaryIncrease()` is NOT called
  - Confirm `previous_year_salary` remains unchanged
  - Confirm `pass_probation_salary` remains unchanged
- [ ] **6.2** Manual test: January payroll (first run)
  - Ensure `previous_year_salary` is null on test employment
  - Run January bulk payroll
  - Confirm payroll records have the 1% increase baked into gross salary
  - Confirm `previous_year_salary` is now set to the old salary (e.g., 30,000)
  - Confirm `pass_probation_salary` is now the new salary (e.g., 30,300)
  - Confirm funding allocation `allocated_amount` is recalculated with new salary
- [ ] **6.3** Manual test: January payroll re-run (idempotency)
  - Delete January payrolls
  - Re-run January bulk payroll
  - Confirm `previous_year_salary` is unchanged (still 30,000)
  - Confirm `pass_probation_salary` is unchanged (still 30,300)
  - Confirm `applyAnnualSalaryIncrease()` returned `skipped > 0, updated = 0`
  - Confirm payroll records still use 30,000 as the increase base (reads `previous_year_salary`)
- [ ] **6.4** Manual test: February payroll after January increase
  - Run February bulk payroll
  - Confirm gross salary is 30,300 (the new `pass_probation_salary`)
  - Confirm no annual increase is added (not January)
- [ ] **6.5** Edge case: salary increase policy disabled
  - Set `salary_increase` policy to inactive
  - Run January bulk payroll
  - Confirm no increase in payroll, no salary update, `previous_year_salary` stays null
- [ ] **6.6** Edge case: first-year employee (no previous_year_salary)
  - Create a new employment with no `previous_year_salary`
  - Run January payroll
  - Confirm increase is calculated from `pass_probation_salary` (fallback)
  - Confirm `previous_year_salary` is set after payroll completes

### Phase 7: Cleanup ✅

- [x] **7.1** Run `vendor/bin/pint --dirty` to format changed files
- [x] **7.2** Review all changes for consistency
  - Verified: `calculateAnnualSalaryIncrease()` uses `previous_year_salary ?? pass_probation_salary`
  - Verified: `previous_year_salary` appears in calculation breakdown inputs
  - Verified: batch summary includes `salary_increase_applied` count
  - Verified: ProcessBulkPayroll calls `applyAnnualSalaryIncrease()` only in January
