# Study Loan Implementation Plan

## Context

Study loan is a **fixed monthly deduction** (e.g., 3,000 THB/month) subtracted from an employee's net pay during payroll processing. The value must be configured **before** payroll runs. It is NOT tax-deductible (it's a loan repayment, not a qualifying deduction like PVD/SSF).

**Decision**: Store `study_loan` on the `employments` table as a `decimal(10,2)` field. This follows the established pattern where employment stores per-employee financial controls (`pvd`, `saving_fund`, `health_welfare`), and the payroll pipeline already loads `$employment` — zero extra queries.

---

## Step 1: Migration — Add `study_loan` to `employments` table

**New file**: `database/migrations/2026_03_05_000001_add_study_loan_to_employments_table.php`

```php
Schema::table('employments', function (Blueprint $table) {
    $table->decimal('study_loan', 10, 2)->nullable()->default(0)
        ->after('saving_fund')
        ->comment('Monthly study loan deduction amount (fixed THB, not a percentage)');
});
```

Placement: after `saving_fund` (line 35 of the create migration), grouping it with the other benefit/deduction fields.

Rollback:
```php
Schema::table('employments', function (Blueprint $table) {
    $table->dropColumn('study_loan');
});
```

---

## Step 2: Migration — Add `study_loan` to `payrolls` table

**New file**: `database/migrations/2026_03_05_000002_add_study_loan_to_payrolls_table.php`

```php
Schema::table('payrolls', function (Blueprint $table) {
    $table->text('study_loan')->nullable()
        ->after('saving_fund')
        ->comment('Required Encryption. TYPE - decimal(). Monthly study loan deduction');
});
```

Uses `text` type (not `decimal`) because all payroll monetary fields are encrypted via Laravel's `encrypted` cast — encrypted values are stored as long text strings.

Placement: after `saving_fund` (line 35 of the create migration), keeping deduction fields grouped.

Rollback:
```php
Schema::table('payrolls', function (Blueprint $table) {
    $table->dropColumn('study_loan');
});
```

---

## Step 3: Migration — Add `study_loan` to `employment_histories` table

**New file**: `database/migrations/2026_03_05_000003_add_study_loan_to_employment_histories_table.php`

```php
Schema::table('employment_histories', function (Blueprint $table) {
    $table->decimal('study_loan', 10, 2)->nullable()->default(0)
        ->after('saving_fund')
        ->comment('Snapshot of study loan deduction at time of change');
});
```

The `employment_histories` table mirrors employment fields for audit trail. Currently has `health_welfare`, `pvd`, `saving_fund` at lines 30-32 of the create migration. Without this column, study loan changes won't appear in employment history.

---

## Step 4: Employment Model — Add to `$fillable` and `$casts`

**File**: `app/Models/Employment.php`

### 4a. Add to `$fillable` (after line 33)

Current code (lines 31-34):
```php
    'health_welfare',
    'pvd',
    'saving_fund',
    'probation_required',
```

Change to:
```php
    'health_welfare',
    'pvd',
    'saving_fund',
    'study_loan',
    'probation_required',
```

### 4b. Add to `$casts` (after line 52)

Current code (lines 50-53):
```php
    'health_welfare' => 'boolean',
    'pvd' => 'boolean',
    'saving_fund' => 'boolean',
    'probation_required' => 'boolean',
```

Change to:
```php
    'health_welfare' => 'boolean',
    'pvd' => 'boolean',
    'saving_fund' => 'boolean',
    'study_loan' => 'decimal:2',
    'probation_required' => 'boolean',
```

---

## Step 5: Payroll Model — Add to `$fillable`, `$casts`, and OA\Schema

**File**: `app/Models/Payroll.php`

### 5a. Add OA\Property (after line 28)

Current code (lines 28-29):
```php
    new OA\Property(property: 'saving_fund', type: 'number', format: 'float'),
    new OA\Property(property: 'employer_social_security', type: 'number', format: 'float'),
```

Change to:
```php
    new OA\Property(property: 'saving_fund', type: 'number', format: 'float'),
    new OA\Property(property: 'study_loan', type: 'number', format: 'float', nullable: true, description: 'Monthly study loan deduction'),
    new OA\Property(property: 'employer_social_security', type: 'number', format: 'float'),
```

### 5b. Add to `$fillable` (after line 62)

Current code (lines 62-63):
```php
    'saving_fund',
    'employer_social_security',
```

Change to:
```php
    'saving_fund',
    'study_loan',
    'employer_social_security',
```

### 5c. Add to `$casts` (after line 90)

Current code (lines 90-91):
```php
    'saving_fund' => 'encrypted',
    'employer_social_security' => 'encrypted',
```

Change to:
```php
    'saving_fund' => 'encrypted',
    'study_loan' => 'encrypted',
    'employer_social_security' => 'encrypted',
```

---

## Step 6: PayrollService — Add study loan to calculation pipeline

**File**: `app/Services/PayrollService.php`

### 6a. Add study loan calculation (after line 997, before net salary)

Current code (lines 996-1000):
```php
    // Salary increase (currently 0 — set manually by HR or via import)
    $salaryIncrease = 0.0;

    // 11. Net Salary (includes salary_increase in total income)
    $netSalary = $this->calculateNetSalary(
```

Insert after `$salaryIncrease = 0.0;`:
```php
    // Study Loan Deduction
    // Fixed monthly amount from employment record; not FTE-proportional, not tax-deductible.
    // Applied to the tax-bearing allocation only (same pattern as income tax) to avoid
    // double-deducting when an employee has multiple funding allocations.
    $studyLoan = 0.0;
    if ($isTaxAllocation && (float) ($employment->study_loan ?? 0) > 0) {
        $studyLoan = round((float) $employment->study_loan);
    }
```

### 6b. Update `$totalDeductions` (line 1029)

Current code:
```php
    $totalDeductions = $pvdSavingEmployee + $employeeSocialSecurity + $healthWelfareEmployee + $incomeTax;
```

Change to:
```php
    $totalDeductions = $pvdSavingEmployee + $employeeSocialSecurity + $healthWelfareEmployee + $incomeTax + $studyLoan;
```

### 6c. Update `calculateNetSalary()` method (lines 1732-1747)

Current signature and body:
```php
private function calculateNetSalary(
    float $grossSalaryCurrentYearByFTE,
    float $retroactiveAdjustment,
    float $thirteenthMonthSalary,
    float $salaryIncrease,
    float $pvdSavingEmployee,
    float $employeeSocialSecurity,
    float $healthWelfareEmployee,
    float $incomeTax
): float {
    $totalIncome = $grossSalaryCurrentYearByFTE + $retroactiveAdjustment + $thirteenthMonthSalary + $salaryIncrease;
    $totalDeductions = $pvdSavingEmployee + $employeeSocialSecurity + $healthWelfareEmployee + $incomeTax;

    // Net salary cannot be negative per business logic
    return max(0, round($totalIncome - $totalDeductions));
}
```

Change to:
```php
private function calculateNetSalary(
    float $grossSalaryCurrentYearByFTE,
    float $retroactiveAdjustment,
    float $thirteenthMonthSalary,
    float $salaryIncrease,
    float $pvdSavingEmployee,
    float $employeeSocialSecurity,
    float $healthWelfareEmployee,
    float $incomeTax,
    float $studyLoan = 0.0
): float {
    $totalIncome = $grossSalaryCurrentYearByFTE + $retroactiveAdjustment + $thirteenthMonthSalary + $salaryIncrease;
    $totalDeductions = $pvdSavingEmployee + $employeeSocialSecurity + $healthWelfareEmployee + $incomeTax + $studyLoan;

    // Net salary cannot be negative per business logic
    return max(0, round($totalIncome - $totalDeductions));
}
```

### 6d. Update the call to `calculateNetSalary()` (lines 1000-1009)

Current code:
```php
    $netSalary = $this->calculateNetSalary(
        $grossSalaryCurrentYearByFTE,
        $retroactiveAdjustment,
        $thirteenthMonthSalary,
        $salaryIncrease,
        $pvdSavingEmployee,
        $employeeSocialSecurity,
        $healthWelfareEmployee,
        $incomeTax
    );
```

Change to:
```php
    $netSalary = $this->calculateNetSalary(
        $grossSalaryCurrentYearByFTE,
        $retroactiveAdjustment,
        $thirteenthMonthSalary,
        $salaryIncrease,
        $pvdSavingEmployee,
        $employeeSocialSecurity,
        $healthWelfareEmployee,
        $incomeTax,
        $studyLoan
    );
```

### 6e. Update calculation breakdown (after line 1137)

Current code (lines 1136-1137):
```php
            'total_deductions_formula' => 'pvd_saving + employee_ss + employee_hw + tax',
            'total_deductions' => $totalDeductions,
```

Change to:
```php
            'total_deductions_formula' => 'pvd_saving + employee_ss + employee_hw + tax + study_loan',
            'total_deductions' => $totalDeductions,
            'study_loan' => $studyLoan,
```

### 6f. Add `study_loan` to return array (after line 1184)

Current code (lines 1184-1185):
```php
            'saving_fund' => $pvdSavingCalculations['saving_fund'],
            'pvd_employer' => round($pvdSavingCalculations['pvd_employee'] > 0 ? $pvdEmployerPortion : 0),
```

Insert after line 1184:
```php
            'study_loan' => $studyLoan,
```

### 6g. Add `study_loan` to historical 13th month calculations (after line 912)

Current code (lines 911-913):
```php
            'pvd' => 0,
            'saving_fund' => 0,
            'pvd_employer' => 0,
```

Insert after line 912:
```php
            'study_loan' => 0,
```

---

## Step 7: ProcessBulkPayroll — Map `study_loan` in `preparePayrollRecord()`

**File**: `app/Jobs/ProcessBulkPayroll.php`

### 7a. Add to `preparePayrollRecord()` (after line 440)

Current code (lines 440-441):
```php
        'saving_fund' => $calculations['saving_fund'],
        'employer_social_security' => $calculations['employer_social_security'],
```

Change to:
```php
        'saving_fund' => $calculations['saving_fund'],
        'study_loan' => $calculations['study_loan'] ?? 0,
        'employer_social_security' => $calculations['employer_social_security'],
```

---

## Step 8: BulkPayrollService — Add `study_loan` to preview detail

**File**: `app/Services/BulkPayrollService.php`

### 8a. Add to deductions in `buildAllocationDetail()` (after line 344)

Current code (lines 344-345):
```php
            'saving_fund' => round($calc['saving_fund']),             // Saving Fund (employee portion)
            'employee_ss' => round($calc['employee_social_security']), // Social Security (5%, capped at ฿875)
```

Change to:
```php
            'saving_fund' => round($calc['saving_fund']),             // Saving Fund (employee portion)
            'study_loan' => round($calc['study_loan'] ?? 0),          // Study loan (fixed monthly deduction)
            'employee_ss' => round($calc['employee_social_security']), // Social Security (5%, capped at ฿875)
```

---

## Step 9: PayrollResource — Expose `study_loan` in API response

**File**: `app/Http/Resources/PayrollResource.php`

### 9a. Add after line 27 (after `saving_fund`)

Current code (lines 27-28):
```php
        'saving_fund' => $this->saving_fund,
        'employer_social_security' => $this->employer_social_security,
```

Change to:
```php
        'saving_fund' => $this->saving_fund,
        'study_loan' => $this->study_loan,
        'employer_social_security' => $this->employer_social_security,
```

---

## Step 10: EmploymentResource — Expose `study_loan` in API response

**File**: `app/Http/Resources/EmploymentResource.php`

### 10a. Add after line 29 (after `saving_fund`)

Current code (lines 29-30):
```php
        'saving_fund' => $this->saving_fund,
        'is_active' => $this->end_date === null,
```

Change to:
```php
        'saving_fund' => $this->saving_fund,
        'study_loan' => $this->study_loan,
        'is_active' => $this->end_date === null,
```

---

## Step 11: Employment Form Requests — Add validation rules

### 11a. StoreEmploymentRequest

**File**: `app/Http/Requests/StoreEmploymentRequest.php`

Current code (lines 37-38):
```php
        'saving_fund' => ['boolean'],
        'probation_required' => ['nullable', 'boolean'],
```

Change to:
```php
        'saving_fund' => ['boolean'],
        'study_loan' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        'probation_required' => ['nullable', 'boolean'],
```

### 11b. UpdateEmploymentRequest

**File**: `app/Http/Requests/UpdateEmploymentRequest.php`

Current code (lines 37-38):
```php
        'saving_fund' => ['sometimes', 'boolean'],
        'probation_required' => ['nullable', 'boolean'],
```

Change to:
```php
        'saving_fund' => ['sometimes', 'boolean'],
        'study_loan' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        'probation_required' => ['nullable', 'boolean'],
```

---

## Step 12: Payroll Form Request — Add validation rule

**File**: `app/Http/Requests/Payroll/UpdatePayrollRequest.php`

### 12a. Add after line 24 (after `saving_fund`)

Current code (lines 24-25):
```php
        'saving_fund' => 'sometimes|required|numeric',
        'employer_social_security' => 'sometimes|required|numeric',
```

Change to:
```php
        'saving_fund' => 'sometimes|required|numeric',
        'study_loan' => 'sometimes|numeric|min:0',
        'employer_social_security' => 'sometimes|required|numeric',
```

---

## Step 13: PayrollsImport — Add `study_loan` to Excel import

**File**: `app/Imports/PayrollsImport.php`

### 13a. Add to numeric fields array (after line 151)

Current code (lines 151-152):
```php
            'saving_fund',
            'employer_social_security',
```

Change to:
```php
            'saving_fund',
            'study_loan',
            'employer_social_security',
```

### 13b. Add to payroll data mapping (after line 288)

Current code (lines 288-289):
```php
                    'saving_fund' => $this->parseNumeric($row['saving_fund'] ?? null),
                    'employer_social_security' => $this->parseNumeric($row['employer_social_security'] ?? 0),
```

Change to:
```php
                    'saving_fund' => $this->parseNumeric($row['saving_fund'] ?? null),
                    'study_loan' => $this->parseNumeric($row['study_loan'] ?? 0),
                    'employer_social_security' => $this->parseNumeric($row['employer_social_security'] ?? 0),
```

### 13c. Add to validation rules (after line 358)

Current code (lines 358-359):
```php
        '*.saving_fund' => 'nullable|numeric',
        '*.employer_social_security' => 'nullable|numeric|min:0',
```

Change to:
```php
        '*.saving_fund' => 'nullable|numeric',
        '*.study_loan' => 'nullable|numeric|min:0',
        '*.employer_social_security' => 'nullable|numeric|min:0',
```

---

## Step 14: Payslip Templates — Add study loan deduction row

### 14a. SMRU Payslip

**File**: `resources/views/pdf/smru-payslip.blade.php`

After Row 4 (Tax row, lines 190-198), insert a new row for study loan. Current Row 5 (lines 200-208) shows "Provident fund SMRU 7.5%". Shift study loan between Tax and the employer deductions section.

Insert after line 198 (after Tax row closing `</tr>`):

```html
    {{-- Row 5: Study Loan --}}
    <tr>
        <td class="no-bt no-bb"></td>
        <td class="no-bt no-bb"></td>
        <td class="no-bt no-bb"></td>
        <td class="no-bt no-bb"></td>
        <td class="no-bt no-bb">Study Loan</td>
        <td class="text-end no-bt no-bb">{{ number_format((float) ($payroll->study_loan ?? 0), 2) }}</td>
    </tr>
```

### 14b. BHF Payslip

**File**: `resources/views/pdf/bhf-payslip.blade.php`

Same change — insert after the Tax row (line 198).

### 14c. Bulk Payslip

**File**: `resources/views/pdf/bulk-payslip.blade.php`

Same change — insert after the Tax row (line 238).

---

## Step 15: Frontend — Employment modal form

### 15a. Add `study_loan` to formData

**File**: `src/components/modal/employment-modal.vue`

Current code (lines 80-83):
```javascript
    health_welfare: false,
    saving_fund: false,
    pvd: false
  });
```

Change to:
```javascript
    health_welfare: false,
    saving_fund: false,
    pvd: false,
    study_loan: null
  });
```

### 15b. Add study loan input field in template (after PVD checkbox, line 895)

After the PVD benefit-item div (lines 890-894), add a study loan input:

```html
          <div class="benefit-item" style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #f0f0f0;">
            <label class="form-label mb-1" style="font-weight: 500;">Study Loan Deduction (THB/month)</label>
            <a-input-number
              v-model:value="formData.study_loan"
              :min="0"
              :max="999999.99"
              :precision="2"
              :step="500"
              placeholder="0.00"
              style="width: 200px;"
            />
            <small class="text-muted d-block mt-1">Fixed monthly amount deducted from net pay. Set to 0 or leave empty for no deduction.</small>
          </div>
```

### 15c. Add `study_loan` to submission payload (after line 340)

Current code (lines 338-341):
```javascript
        health_welfare: formData.value.health_welfare,
        saving_fund: formData.value.saving_fund,
        pvd: formData.value.pvd
      };
```

Change to:
```javascript
        health_welfare: formData.value.health_welfare,
        saving_fund: formData.value.saving_fund,
        pvd: formData.value.pvd,
        study_loan: formData.value.study_loan || 0
      };
```

---

## Step 16: Frontend — Employment form composable

### 16a. Add to formData reactive

**File**: `src/composables/useEmploymentForm.js`

Current code (lines 60-63):
```javascript
  health_welfare: false,
  pvd: false,
  saving_fund: false
});
```

Change to:
```javascript
  health_welfare: false,
  pvd: false,
  saving_fund: false,
  study_loan: null
});
```

### 16b. Add to `buildEmploymentOnlyPayload()` (after line 434)

Current code (lines 433-436):
```javascript
    saving_fund: !!formData.saving_fund
    // NOTE: No allocations - employment created without allocations
  };
};
```

Change to:
```javascript
    saving_fund: !!formData.saving_fund,
    study_loan: formData.study_loan || 0
    // NOTE: No allocations - employment created without allocations
  };
};
```

---

## Files Modified Summary

| # | File | Change |
|---|------|--------|
| 1 | New migration | Add `study_loan` decimal(10,2) to `employments` |
| 2 | New migration | Add `study_loan` text (encrypted) to `payrolls` |
| 3 | New migration | Add `study_loan` decimal(10,2) to `employment_histories` |
| 4 | `app/Models/Employment.php` | Add to `$fillable` + `$casts` |
| 5 | `app/Models/Payroll.php` | Add to `$fillable` + `$casts` + OA\Schema |
| 6 | `app/Services/PayrollService.php` | Add to calculation pipeline, `calculateNetSalary()`, return arrays |
| 7 | `app/Jobs/ProcessBulkPayroll.php` | Map in `preparePayrollRecord()` |
| 8 | `app/Services/BulkPayrollService.php` | Add to preview `buildAllocationDetail()` |
| 9 | `app/Http/Resources/PayrollResource.php` | Expose field |
| 10 | `app/Http/Resources/EmploymentResource.php` | Expose field |
| 11 | `app/Http/Requests/StoreEmploymentRequest.php` | Add validation rule |
| 12 | `app/Http/Requests/UpdateEmploymentRequest.php` | Add validation rule |
| 13 | `app/Http/Requests/Payroll/UpdatePayrollRequest.php` | Add validation rule |
| 14 | `app/Imports/PayrollsImport.php` | Add to import mapping + validation |
| 15 | `resources/views/pdf/smru-payslip.blade.php` | Add deduction row |
| 16 | `resources/views/pdf/bhf-payslip.blade.php` | Add deduction row |
| 17 | `resources/views/pdf/bulk-payslip.blade.php` | Add deduction row |
| 18 | `src/components/modal/employment-modal.vue` | Add input field + payload |
| 19 | `src/composables/useEmploymentForm.js` | Add to formData + payload builder |

---

## Key Design Decisions

### Why deduct from the tax-bearing allocation only?

When an employee has multiple allocations (e.g., 60% Grant A + 40% Grant B), the study loan should be deducted **once**, not split across allocations. The system already handles income tax the same way — `determineTaxAllocationId()` picks the highest-FTE allocation to bear the full tax. Study loan follows this exact pattern:

```php
if ($isTaxAllocation && (float) ($employment->study_loan ?? 0) > 0) {
    $studyLoan = round((float) $employment->study_loan);
}
```

Non-tax allocations get `study_loan = 0`.

### Why NOT FTE-proportional?

Study loan is a fixed obligation (e.g., 3,000 THB/month regardless of whether the employee is 50% or 100% FTE). Unlike PVD (which is 7.5% of FTE-adjusted salary), the loan amount doesn't change based on how the employee's time is allocated.

### Why NOT tax-deductible?

Under Thai Revenue Department rules, study loan repayments are **not** a qualifying deduction for personal income tax. Therefore, study loan is applied **after** the tax calculation (step 10) — it reduces `net_salary` but NOT `taxable_income`.

### Why default to 0 instead of null?

Using `default(0)` in the migration means existing employments automatically have no study loan deduction. The `nullable` allows the field to be omitted in API requests. The payroll calculation treats both `null` and `0` as "no deduction" via `(float) ($employment->study_loan ?? 0)`.

---

## Verification

1. **Run migrations**: `php artisan migrate`
2. **Route check**: No new routes needed — uses existing employment CRUD
3. **Test**: Create/update an employment with `study_loan: 3000` → run bulk payroll preview → verify net_salary reduced by 3,000 on the tax-bearing allocation
4. **Test multi-allocation**: Employee with 60%/40% split → only the 60% allocation should show the 3,000 deduction
5. **Test payslip**: Generate PDF → verify "Study Loan" row appears in deductions column
6. **Test zero**: Employment with `study_loan: 0` → payroll unchanged from current behavior
7. **Test import**: Upload payroll Excel with `study_loan` column → verify it's stored correctly

---

## Implementation Todo List

### Phase 1: Database Schema (Migrations)

- [x] **1.1** Create migration `add_study_loan_to_employments_table`
  - Add `study_loan` decimal(10,2) nullable default(0) after `saving_fund`
  - Add rollback (`dropColumn`)
- [x] **1.2** Create migration `add_study_loan_to_payrolls_table`
  - Add `study_loan` text nullable after `saving_fund` (text for encryption)
  - Add rollback (`dropColumn`)
- [x] **1.3** Create migration `add_study_loan_to_employment_histories_table`
  - Add `study_loan` decimal(10,2) nullable default(0) after `saving_fund`
  - Add rollback (`dropColumn`)
- [x] **1.4** Run `php artisan migrate` and confirm all three columns exist

### Phase 2: Backend Models (Employment + Payroll)

- [x] **2.1** `app/Models/Employment.php` — Add `'study_loan'` to `$fillable` (after `saving_fund`, before `probation_required`)
- [x] **2.2** `app/Models/Employment.php` — Add `'study_loan' => 'decimal:2'` to `$casts` (after `saving_fund`, before `probation_required`)
- [x] **2.3** `app/Models/Payroll.php` — Add OA\Property for `study_loan` (after `saving_fund` property, before `employer_social_security`)
- [x] **2.4** `app/Models/Payroll.php` — Add `'study_loan'` to `$fillable` (after `saving_fund`, before `employer_social_security`)
- [x] **2.5** `app/Models/Payroll.php` — Add `'study_loan' => 'encrypted'` to `$casts` (after `saving_fund`, before `employer_social_security`)

### Phase 3: Backend Validation (Form Requests)

- [x] **3.1** `app/Http/Requests/StoreEmploymentRequest.php` — Add `'study_loan' => ['nullable', 'numeric', 'min:0', 'max:999999.99']` rule (after `saving_fund`, before `probation_required`)
- [x] **3.2** `app/Http/Requests/UpdateEmploymentRequest.php` — Add `'study_loan' => ['nullable', 'numeric', 'min:0', 'max:999999.99']` rule (after `saving_fund`, before `probation_required`)
- [x] **3.3** `app/Http/Requests/Payroll/UpdatePayrollRequest.php` — Add `'study_loan' => 'sometimes|numeric|min:0'` rule (after `saving_fund`, before `employer_social_security`)

### Phase 4: Backend API Resources

- [x] **4.1** `app/Http/Resources/EmploymentResource.php` — Add `'study_loan' => $this->study_loan` (after `saving_fund`, before `is_active`)
- [x] **4.2** `app/Http/Resources/PayrollResource.php` — Add `'study_loan' => $this->study_loan` (after `saving_fund`, before `employer_social_security`)

### Phase 5: Payroll Calculation Engine (Core Logic)

- [x] **5.1** `app/Services/PayrollService.php` — In `calculateAllocationPayroll()`: Add `$studyLoan` variable with `$isTaxAllocation` guard (after `$salaryIncrease = 0.0;`, before `calculateNetSalary()` call)
- [x] **5.2** `app/Services/PayrollService.php` — Update `$totalDeductions` formula to include `+ $studyLoan` (line 1029)
- [x] **5.3** `app/Services/PayrollService.php` — Update `calculateNetSalary()` method signature to accept `float $studyLoan = 0.0` parameter
- [x] **5.4** `app/Services/PayrollService.php` — Update `calculateNetSalary()` body to include `$studyLoan` in `$totalDeductions`
- [x] **5.5** `app/Services/PayrollService.php` — Update the call to `calculateNetSalary()` to pass `$studyLoan` argument (lines 1000-1009)
- [x] **5.6** `app/Services/PayrollService.php` — Update calculation breakdown: change `total_deductions_formula` string and add `'study_loan' => $studyLoan` (after line 1137)
- [x] **5.7** `app/Services/PayrollService.php` — Add `'study_loan' => $studyLoan` to the return `calculations` array (after `saving_fund`, before `pvd_employer`)
- [x] **5.8** `app/Services/PayrollService.php` — Add `'study_loan' => 0` to `calculateHistoricalAllocation13thMonth()` return array (after `saving_fund`, before `pvd_employer`)

### Phase 6: Bulk Payroll Processing

- [x] **6.1** `app/Jobs/ProcessBulkPayroll.php` — Add `'study_loan' => $calculations['study_loan'] ?? 0` to `preparePayrollRecord()` (after `saving_fund`, before `employer_social_security`)
- [x] **6.2** `app/Services/BulkPayrollService.php` — Add `'study_loan' => round($calc['study_loan'] ?? 0)` to `buildAllocationDetail()` deductions array (after `saving_fund`, before `employee_ss`)

### Phase 7: Payroll Import (Excel)

- [x] **7.1** `app/Imports/PayrollsImport.php` — Add `'study_loan'` to `$numericFields` array (after `saving_fund`, before `employer_social_security`)
- [x] **7.2** `app/Imports/PayrollsImport.php` — Add `'study_loan'` mapping to payroll data array in row processing (after `saving_fund`, before `employer_social_security`)
- [x] **7.3** `app/Imports/PayrollsImport.php` — Add `'*.study_loan' => 'nullable|numeric|min:0'` to `rules()` (after `saving_fund`, before `employer_social_security`)

### Phase 8: Payslip PDF Templates

- [x] **8.1** `resources/views/pdf/smru-payslip.blade.php` — Insert "Study Loan" deduction row after the Tax row (after line 198)
- [x] **8.2** `resources/views/pdf/bhf-payslip.blade.php` — Insert same "Study Loan" deduction row after the Tax row
- [x] **8.3** `resources/views/pdf/bulk-payslip.blade.php` — Insert same "Study Loan" deduction row after the Tax row

### Phase 9: Frontend (Employment Form)

- [x] **9.1** `src/components/modal/employment-modal.vue` — Add `study_loan: null` to `formData` ref initial state (after `pvd`)
- [x] **9.2** `src/components/modal/employment-modal.vue` — Add study loan `<a-input-number>` field in the Benefits section template (after PVD checkbox div, before closing `</div>` of benefits-container)
- [x] **9.3** `src/components/modal/employment-modal.vue` — Add `study_loan: formData.value.study_loan || 0` to submission payload (after `pvd`)
- [x] **9.4** `src/composables/useEmploymentForm.js` — Add `study_loan: null` to `formData` reactive (after `saving_fund`)
- [x] **9.5** `src/composables/useEmploymentForm.js` — Add `study_loan: formData.study_loan || 0` to `buildEmploymentOnlyPayload()` return object (after `saving_fund`)

### Phase 10: Verification & Testing

- [x] **10.1** Run `php artisan migrate` — confirm all 3 migrations succeed
- [x] **10.2** Run `php artisan test --filter=Employment` — confirm no regressions in employment tests (pre-existing failures only)
- [x] **10.3** Run `php artisan test --filter=Payroll` — confirm no regressions in payroll tests (pre-existing failures only)
- [ ] **10.4** Manual test: Create employment with `study_loan: 3000` via API or frontend form
- [ ] **10.5** Manual test: Run bulk payroll preview — verify `study_loan` appears in deductions and net_salary is reduced by 3,000 on the tax-bearing allocation only
- [ ] **10.6** Manual test: Employee with multiple allocations (e.g., 60%/40%) — verify study loan deducted once on the higher-FTE allocation, zero on the other
- [ ] **10.7** Manual test: Employment with `study_loan: 0` — verify payroll output identical to current behavior (no regression)
- [ ] **10.8** Manual test: Generate payslip PDF — verify "Study Loan" row appears in deductions column with correct amount
- [ ] **10.9** Manual test: Update employment to change `study_loan` from 3000 to 0 — verify employment history records the change
- [x] **10.10** Run `vendor/bin/pint --dirty` — format any changed PHP files
