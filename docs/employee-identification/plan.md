# Employee Identifications — Implementation Plan

> **Date:** 2026-03-09
> **Prerequisite:** Read `docs/employee-identification/research.md` first
> **Estimated files:** 9 new + 14 modified + tests

---

## Table of Contents

1. [Phase 1 — Foundation](#phase-1--foundation)
2. [Phase 2 — New Feature Files](#phase-2--new-feature-files)
3. [Phase 3 — Update Reads](#phase-3--update-reads)
4. [Phase 4 — Update Writes](#phase-4--update-writes)
5. [Phase 5 — Update Imports](#phase-5--update-imports)
6. [Phase 6 — Tests](#phase-6--tests)
7. [Phase 7 — Cleanup (Future)](#phase-7--cleanup-future)
8. [Verification Checklist](#verification-checklist)
9. [Detailed Todo List](#detailed-todo-list)

---

## Detailed Todo List

### Phase 1 — Foundation (5 tasks)

No breaking changes. Existing code continues to work unchanged.

- [x] **1.1** Create migration `2026_03_10_000001_create_employee_identifications_table.php`
  - Create `employee_identifications` table with all columns (id, employee_id, identification_type, identification_number, issue_date, expiry_date, name fields, is_primary, audit fields, timestamps)
  - Add indexes: `idx_emp_ident_employee`, `idx_emp_ident_primary`, `idx_emp_ident_employee_primary`
  - Backfill existing data from `employees` table using raw SQL `INSERT INTO ... SELECT` with `GETDATE()`
  - Set `is_primary = 1` for all backfilled records
  - Only backfill rows where `identification_type IS NOT NULL AND deleted_at IS NULL`
- [x] **1.2** Create model `app/Models/EmployeeIdentification.php`
  - Define `$fillable` with all 14 fields
  - Define `$casts` for dates and `is_primary` boolean
  - Add `NAME_FIELDS` constant (6 name fields that sync to employee)
  - Add `employee()` belongsTo relationship with `->withTrashed()`
  - Add `HasFactory` trait
  - Add OpenAPI schema attributes
- [x] **1.3** Create observer `app/Observers/EmployeeIdentificationObserver.php`
  - `created()` — if `is_primary`, call `ensureSinglePrimary()` + `syncNamesToEmployee()`
  - `updated()` — if `is_primary` just changed to true, call `ensureSinglePrimary()` + `syncNamesToEmployee()`
  - `updated()` — if already primary AND name fields changed, call `syncNamesToEmployee()`
  - `ensureSinglePrimary()` — unset `is_primary` on all other records for same employee
  - `syncNamesToEmployee()` — update employee with non-null name fields + `updated_by`
  - `nameFieldsChanged()` — check if any NAME_FIELDS were changed via `wasChanged()`
- [x] **1.4** Register observer in `app/Providers/AppServiceProvider.php`
  - Add import for `EmployeeIdentification` and `EmployeeIdentificationObserver`
  - Add `EmployeeIdentification::observe(EmployeeIdentificationObserver::class)` after existing observer registrations
- [x] **1.5** Add relationships to `app/Models/Employee.php`
  - Add `identifications(): HasMany` relationship
  - Add `primaryIdentification(): HasOne` relationship with `->where('is_primary', true)`
  - Add `HasMany` and `HasOne` imports if not already present
- [x] **1.P** Run `php artisan migrate` and `php artisan test` — all existing tests must pass

### Phase 2 — New Feature Files (6 tasks)

All new files. No existing code modified.

- [x] **2.1** Create enum `app/Enums/IdentificationType.php`
  - 7 cases: TenYearsID, BurmeseID, CI, Borderpass, ThaiID, Passport, Other
  - `label()` method returning human-readable labels
  - `values()` static method returning string values array
- [x] **2.2** Create service `app/Services/EmployeeIdentificationService.php`
  - Constructor: inject `NotificationService`
  - `listByEmployee(int $employeeId): Collection` — query with employee eager-load, ordered by `is_primary` desc then `created_at` desc
  - `show(EmployeeIdentification): EmployeeIdentification` — load employee relationship
  - `create(array $data): EmployeeIdentification` — set audit fields, auto-set `is_primary = true` if first identification for employee, invalidate cache, notify
  - `update(EmployeeIdentification, array $data): EmployeeIdentification` — set `updated_by`, update, invalidate cache, notify
  - `delete(EmployeeIdentification): array` — prevent deleting only identification, auto-promote next if primary deleted, invalidate cache
  - `setPrimary(EmployeeIdentification): EmployeeIdentification` — no-op if already primary, use `DB::transaction()` + `lockForUpdate()` for atomicity, unset others, set target as primary, invalidate cache, notify
  - `invalidateCache()` — `Cache::forget('employee_statistics')`
  - `notifyAction()` — send `EmployeeActionNotification` via `notificationService`
- [x] **2.3** Create form requests
  - `app/Http/Requests/StoreEmployeeIdentificationRequest.php` — require `employee_id`, `identification_type` (enum validation), `identification_number`; nullable dates, name fields, `is_primary`; custom messages
  - `app/Http/Requests/UpdateEmployeeIdentificationRequest.php` — all fields `sometimes`/`nullable`, NO `is_primary` field (use `setPrimary` endpoint instead); custom messages
- [x] **2.4** Create resource `app/Http/Resources/EmployeeIdentificationResource.php`
  - Return all fields including `is_primary`, audit fields, ISO8601 timestamps
  - Include `employee` via `whenLoaded()` with id, staff_id, first_name_en, last_name_en
- [x] **2.5** Create controller `app/Http/Controllers/Api/V1/EmployeeIdentificationController.php`
  - Extends `BaseApiController`
  - Constructor: inject `EmployeeIdentificationService`
  - `index(Request)` — validate `employee_id` required, call `listByEmployee()`, return collection
  - `show(EmployeeIdentification)` — call `show()`, return single resource
  - `store(StoreEmployeeIdentificationRequest)` — call `create()`, return 201
  - `update(UpdateEmployeeIdentificationRequest, EmployeeIdentification)` — call `update()`, return 200
  - `destroy(EmployeeIdentification)` — call `delete()`, return 200 or 422 if only one
  - `setPrimary(EmployeeIdentification)` — call `setPrimary()`, return 200
  - Add OpenAPI attributes to all 6 methods
- [x] **2.6** Add routes to `routes/api/employees.php`
  - Add import for `EmployeeIdentificationController`
  - Add `employee-identifications` prefix group with 6 routes:
    - `GET /` → `index` (permission: `employees.read`)
    - `GET /{employeeIdentification}` → `show` (permission: `employees.read`)
    - `POST /` → `store` (permission: `employees.create`)
    - `PUT /{employeeIdentification}` → `update` (permission: `employees.update`)
    - `PATCH /{employeeIdentification}/set-primary` → `setPrimary` (permission: `employees.update`)
    - `DELETE /{employeeIdentification}` → `destroy` (permission: `employees.delete`)
- [x] **2.P** Run `php artisan route:list --path=employee-identifications` and `php artisan test` — 6 routes registered, existing tests pass

### Phase 3 — Update Reads (4 tasks)

Safe, additive changes. All backward-compatible.

- [x] **3.1** Update `app/Http/Resources/EmployeeResource.php`
  - Replace 4 flat identification fields with `whenLoaded('primaryIdentification', ...)` with fallback to direct column
  - Fields: `identification_type`, `identification_number`, `identification_issue_date`, `identification_expiry_date`
- [x] **3.2** Update `app/Http/Resources/EmployeeDetailResource.php`
  - Replace 4 flat identification fields with `$this->primaryIdentification?->field ?? $this->field` pattern
  - Add `primary_identification` field via `whenLoaded('primaryIdentification', fn => new EmployeeIdentificationResource(...))`
  - Add `identifications` field via `EmployeeIdentificationResource::collection($this->whenLoaded('identifications'))`
  - Add import for `EmployeeIdentificationResource`
- [x] **3.3** Update `app/Services/EmployeeDataService.php` `show()` method
  - Add `'identifications'` and `'primaryIdentification'` to the `load()` array
- [x] **3.4** Update `app/Exports/EmployeesExport.php`
  - In `query()`: add `'primaryIdentification'` to the `with()` array
  - In `map()`: change `$employee->identification_type` to `$primary?->identification_type` (with reverse mapping)
  - In `map()` return array: change 4 direct column references to `$primary?->` properties
- [x] **3.P** Run `php artisan test` — all tests pass, employee detail endpoint includes `identifications` and `primary_identification`

### Phase 4 — Update Writes (7 tasks)

Redirect identification writes from `employees` table to `employee_identifications` table.

- [x] **4.1** Update `app/Models/Employee.php` — remove identification fields
  - Remove 4 fields from `$fillable`: `identification_type`, `identification_number`, `identification_issue_date`, `identification_expiry_date`
  - Remove 2 casts: `identification_issue_date`, `identification_expiry_date`
  - Remove `getIdTypeAttribute()` accessor
  - Rewrite `scopeByIdType()` to use `whereHas('identifications', ...)` instead of `whereIn('identification_type', ...)`
  - Remove 4 OpenAPI `@OA\Property` lines for identification fields
- [x] **4.2** Update `app/Services/EmployeeDataService.php` `store()` method
  - Call `extractIdentificationData($validated)` to separate identification fields
  - After `Employee::create()`, if identification data exists: set `employee_id`, `is_primary = true`, copy name fields from employee data, create `EmployeeIdentification`
- [x] **4.3** Update `app/Services/EmployeeDataService.php` `fullUpdate()` method
  - Call `extractIdentificationData($validated)` to separate identification fields
  - After `$employee->update()`, if identification data exists: update primary identification or create new one if none exists
- [x] **4.4** Update `app/Services/EmployeeDataService.php` `updatePersonalInfo()` method
  - Call `extractIdentificationData($data)` to separate identification fields
  - Handle legacy nested `employee_identification` format (map `id_type` → `identification_type`, `document_number` → `identification_number`)
  - After `$employee->update($data)`, if identification data exists: update primary or create new primary
- [x] **4.5** Add `extractIdentificationData()` private helper to `app/Services/EmployeeDataService.php`
  - Accept `array &$data` by reference
  - Extract and unset 4 identification fields from `$data`: `identification_type`, `identification_number`, `identification_issue_date`, `identification_expiry_date`
  - Return extracted fields as separate array
  - Add import for `EmployeeIdentification` model
- [x] **4.6** Update `app/Services/EmployeeDataService.php` `list()` sort
  - Change `identification_type` sort from `$query->orderBy('employees.identification_type', ...)` to `leftJoin('employee_identifications', ...)` with `is_primary = true` condition
- [x] **4.7** Remove identification validation rules from 4 form requests
  - `app/Http/Requests/StoreEmployeeRequest.php` — delete 4 identification rules (lines 39-43)
  - `app/Http/Requests/UpdateEmployeeRequest.php` — delete 4 identification rules (lines 40-44)
  - `app/Http/Requests/UpdateEmployeePersonalRequest.php` — delete 8 lines: 4 identification rules + 3 legacy nested rules + comment (lines 31-38)
  - `app/Http/Requests/Employee/FullUpdateEmployeeRequest.php` — delete 2 identification rules (lines 35-36)
- [x] **4.P** Run `php artisan test` — all tests pass, creating/updating employees properly creates/updates identification records

### Phase 5 — Update Imports (2 tasks)

- [x] **5.1** Update `app/Imports/EmployeesImport.php`
  - Initialize `$validatedIdentifications = []` alongside `$validatedBeneficiaries`
  - In validation pass: remove 4 identification fields from `$validatedEmployees[]` array
  - In validation pass: build `$validatedIdentifications[]` with `_staff_id` linking key, identification fields, name fields, `is_primary = true`, timestamps
  - In insertion pass (after beneficiary insert): link identifications to employees via `$employeeIdMap[$staffId]`, unset `_staff_id`, bulk insert via `DB::table('employee_identifications')->insert()`
  - Note: intentionally bypasses observer — names already match from same Excel row
- [x] **5.2** Update `app/Imports/DevEmployeesImport.php`
  - Apply same pattern as EmployeesImport: remove identification from employee data, build separate identification array, bulk insert after employees
- [x] **5.P** Run `php artisan test` — all import tests pass

### Phase 6 — Tests (2 tasks)

- [x] **6.1** Create test file `tests/Feature/Api/EmployeeIdentificationApiTest.php`
  - `beforeEach`: create user, grant `employees.read/create/update/delete` permissions
  - **GET /api/v1/employee-identifications**:
    - Lists identifications for an employee (assert count)
    - Requires `employee_id` parameter (assert 422)
  - **POST /api/v1/employee-identifications**:
    - Creates a new identification (assert 201 + database has record)
    - Auto-sets first identification as primary (assert `is_primary = true`)
    - Validates required fields: employee_id, identification_type, identification_number (assert 422)
    - Validates identification_type enum values (assert 422)
  - **PUT /api/v1/employee-identifications/{id}**:
    - Updates an identification (assert 200 + database updated)
    - Syncs names to employee when primary record name fields are updated (assert employee table updated)
  - **PATCH /api/v1/employee-identifications/{id}/set-primary**:
    - Sets identification as primary and syncs names (assert new primary set, old primary unset, employee names synced)
    - Is a no-op when already primary (assert 200)
  - **DELETE /api/v1/employee-identifications/{id}**:
    - Deletes a non-primary identification (assert 200)
    - Prevents deleting the only identification (assert 422)
    - Promotes another identification when primary is deleted (assert new primary set + employee names synced)
  - **Authentication & Authorization**:
    - Returns 401 for unauthenticated requests
    - Returns 403 for user without permission
- [x] **6.2** Create factory `database/factories/EmployeeIdentificationFactory.php`
  - `definition()`: employee_id via Employee::factory, random identification_type, unique identification_number, date range for issue/expiry, faker name fields, `is_primary = false`
  - `primary()` state: sets `is_primary = true`
- [x] **6.P** Run `php artisan test --filter=EmployeeIdentification` — all new tests pass (15 passed, 35 assertions)
- [x] **6.F** Run full test suite `php artisan test` — ALL tests pass (273 passed, 1151 assertions)

### Phase 7 — Cleanup (Future, 2 tasks)

**Only do this AFTER the frontend is updated to use structured identification fields.**

- [ ] **7.1** Create column drop migration
  - Drop 4 columns from `employees` table: `identification_type`, `identification_number`, `identification_issue_date`, `identification_expiry_date`
- [ ] **7.2** Remove backward-compatible flat fields from resources
  - `EmployeeResource.php` — remove 4 flat identification fields
  - `EmployeeDetailResource.php` — remove 4 flat identification fields

### Code Quality (run after each phase)

- [x] Run `vendor/bin/pint --dirty` after Phase 1
- [x] Run `vendor/bin/pint --dirty` after Phase 2
- [x] Run `vendor/bin/pint --dirty` after Phase 3
- [x] Run `vendor/bin/pint --dirty` after Phase 4
- [x] Run `vendor/bin/pint --dirty` after Phase 5
- [x] Run `vendor/bin/pint --dirty` after Phase 6

### Summary

| Phase | Tasks | New Files | Modified Files | Risk Level |
|-------|-------|-----------|----------------|------------|
| 1 — Foundation | 5 + verify | 3 (migration, model, observer) | 2 (AppServiceProvider, Employee) | None — additive only |
| 2 — New Features | 6 + verify | 6 (enum, service, controller, resource, 2 requests) | 1 (routes) | None — all new files |
| 3 — Update Reads | 4 + verify | 0 | 4 (2 resources, EmployeeDataService, EmployeesExport) | Low — backward-compatible |
| 4 — Update Writes | 7 + verify | 0 | 6 (Employee model, EmployeeDataService, 4 form requests) | Medium — redirects writes |
| 5 — Update Imports | 2 + verify | 0 | 2 (EmployeesImport, DevEmployeesImport) | Medium — bulk data handling |
| 6 — Tests | 2 + verify | 2 (test file, factory) | 0 | None — tests only |
| 7 — Cleanup | 2 | 1 (migration) | 2 (resources) | Low — but requires frontend update first |
| **Total** | **28 tasks + 8 verify steps** | **12 files** | **14 files** | |

---

## Phase 1 — Foundation

No breaking changes. Existing code continues to work unchanged.

### Step 1.1: Create migration

**NEW file:** `database/migrations/2026_03_10_000001_create_employee_identifications_table.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_identifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('identification_type', 50);
            $table->string('identification_number', 50);
            $table->date('identification_issue_date')->nullable();
            $table->date('identification_expiry_date')->nullable();
            $table->string('first_name_en', 255)->nullable();
            $table->string('last_name_en', 255)->nullable();
            $table->string('first_name_th', 255)->nullable();
            $table->string('last_name_th', 255)->nullable();
            $table->string('initial_en', 10)->nullable();
            $table->string('initial_th', 10)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamps();

            $table->index('employee_id', 'idx_emp_ident_employee');
            $table->index('is_primary', 'idx_emp_ident_primary');
            $table->index(['employee_id', 'is_primary'], 'idx_emp_ident_employee_primary');
        });

        // Backfill: copy existing identification data from employees table
        DB::statement("
            INSERT INTO employee_identifications (
                employee_id, identification_type, identification_number,
                identification_issue_date, identification_expiry_date,
                first_name_en, last_name_en, first_name_th, last_name_th,
                initial_en, initial_th, is_primary,
                created_by, created_at, updated_at
            )
            SELECT
                id, identification_type, identification_number,
                identification_issue_date, identification_expiry_date,
                first_name_en, last_name_en, first_name_th, last_name_th,
                initial_en, initial_th, 1,
                'migration', GETDATE(), GETDATE()
            FROM employees
            WHERE identification_type IS NOT NULL
              AND deleted_at IS NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_identifications');
    }
};
```

**Important notes:**
- Uses `GETDATE()` (SQL Server syntax, not `NOW()`)
- `is_primary = 1` for all backfilled records (each employee starts with one primary)
- Only backfills rows that have `identification_type IS NOT NULL`
- Does NOT drop old columns yet (backward compatibility)

---

### Step 1.2: Create EmployeeIdentification model

**NEW file:** `app/Models/EmployeeIdentification.php`

Follow the `EmployeeChild` pattern (simple model, `belongsTo Employee`).

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'EmployeeIdentification',
    title: 'Employee Identification',
    description: 'Employee identification document record',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64'),
        new OA\Property(property: 'employee_id', type: 'integer', format: 'int64'),
        new OA\Property(property: 'identification_type', type: 'string', maxLength: 50),
        new OA\Property(property: 'identification_number', type: 'string', maxLength: 50),
        new OA\Property(property: 'identification_issue_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'identification_expiry_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'first_name_en', type: 'string', nullable: true),
        new OA\Property(property: 'last_name_en', type: 'string', nullable: true),
        new OA\Property(property: 'first_name_th', type: 'string', nullable: true),
        new OA\Property(property: 'last_name_th', type: 'string', nullable: true),
        new OA\Property(property: 'initial_en', type: 'string', nullable: true),
        new OA\Property(property: 'initial_th', type: 'string', nullable: true),
        new OA\Property(property: 'is_primary', type: 'boolean'),
        new OA\Property(property: 'created_by', type: 'string', nullable: true),
        new OA\Property(property: 'updated_by', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class EmployeeIdentification extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'identification_type',
        'identification_number',
        'identification_issue_date',
        'identification_expiry_date',
        'first_name_en',
        'last_name_en',
        'first_name_th',
        'last_name_th',
        'initial_en',
        'initial_th',
        'is_primary',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'identification_issue_date' => 'date',
        'identification_expiry_date' => 'date',
        'is_primary' => 'boolean',
    ];

    /**
     * Name fields that sync to the employees table when this record is primary.
     */
    public const NAME_FIELDS = [
        'first_name_en',
        'last_name_en',
        'first_name_th',
        'last_name_th',
        'initial_en',
        'initial_th',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }
}
```

---

### Step 1.3: Create EmployeeIdentificationObserver

**NEW file:** `app/Observers/EmployeeIdentificationObserver.php`

Follows the `EmployeeFundingAllocationObserver` pattern (event-based side effects).

```php
<?php

namespace App\Observers;

use App\Models\EmployeeIdentification;

/**
 * Observer to sync primary identification name fields to the employees table.
 *
 * When an identification record is created or updated with is_primary = true,
 * the employee's name fields are automatically synced to match.
 *
 * Note: Bulk imports use DB::table()->insert() which bypasses this observer.
 * This is intentional — during import, names already match from the same Excel row.
 */
class EmployeeIdentificationObserver
{
    /**
     * Handle the "created" event.
     *
     * If the new record is primary, sync names to the employee.
     */
    public function created(EmployeeIdentification $identification): void
    {
        if ($identification->is_primary) {
            $this->ensureSinglePrimary($identification);
            $this->syncNamesToEmployee($identification);
        }
    }

    /**
     * Handle the "updated" event.
     *
     * Sync names when:
     * - is_primary just became true (flag changed)
     * - Record is already primary AND name fields changed
     */
    public function updated(EmployeeIdentification $identification): void
    {
        if ($identification->is_primary && $identification->wasChanged('is_primary')) {
            // Just became primary — sync all names
            $this->ensureSinglePrimary($identification);
            $this->syncNamesToEmployee($identification);

            return;
        }

        if ($identification->is_primary && $this->nameFieldsChanged($identification)) {
            // Already primary, but name fields were edited — re-sync
            $this->syncNamesToEmployee($identification);
        }
    }

    /**
     * Unset is_primary on all other identifications for the same employee.
     */
    private function ensureSinglePrimary(EmployeeIdentification $identification): void
    {
        EmployeeIdentification::where('employee_id', $identification->employee_id)
            ->where('id', '!=', $identification->id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }

    /**
     * Sync non-null name fields from the identification record to the employee.
     *
     * This triggers the Employee's LogsActivity trait, which automatically
     * captures old/new name values in the activity_logs table.
     */
    private function syncNamesToEmployee(EmployeeIdentification $identification): void
    {
        $updates = [];

        foreach (EmployeeIdentification::NAME_FIELDS as $field) {
            if ($identification->$field !== null) {
                $updates[$field] = $identification->$field;
            }
        }

        if (! empty($updates)) {
            $updates['updated_by'] = $identification->updated_by ?? $identification->created_by ?? 'System';
            $identification->employee()->update($updates);
        }
    }

    /**
     * Check if any name fields were changed on this update.
     */
    private function nameFieldsChanged(EmployeeIdentification $identification): bool
    {
        foreach (EmployeeIdentification::NAME_FIELDS as $field) {
            if ($identification->wasChanged($field)) {
                return true;
            }
        }

        return false;
    }
}
```

---

### Step 1.4: Register observer in AppServiceProvider

**MODIFY file:** `app/Providers/AppServiceProvider.php`

Add import at top:
```php
use App\Models\EmployeeIdentification;
use App\Observers\EmployeeIdentificationObserver;
```

Add after line 59 (after `EmployeeFundingAllocation::observe(...)`):
```php
// Register observer to sync primary identification names to employee
EmployeeIdentification::observe(EmployeeIdentificationObserver::class);
```

---

### Step 1.5: Add relationships to Employee model

**MODIFY file:** `app/Models/Employee.php`

Add import at top:
```php
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
```

Add after the `transfers()` relationship (after line 284):

```php
/**
 * Get all identification documents for this employee.
 */
public function identifications(): HasMany
{
    return $this->hasMany(EmployeeIdentification::class);
}

/**
 * Get the primary identification document.
 * Used by EmployeeResource for backward-compatible flat fields.
 */
public function primaryIdentification(): HasOne
{
    return $this->hasOne(EmployeeIdentification::class)->where('is_primary', true);
}
```

---

### Phase 1 verification

After Phase 1, run:
```bash
php artisan migrate
php artisan test
```

All existing tests should still pass. The new table exists with backfilled data.
No existing code is affected — old columns are still on the employees table.

---

## Phase 2 — New Feature Files

All new files. No existing code modified.

### Step 2.1: Create IdentificationType enum

**NEW file:** `app/Enums/IdentificationType.php`

```php
<?php

namespace App\Enums;

enum IdentificationType: string
{
    case TenYearsID = '10YearsID';
    case BurmeseID = 'BurmeseID';
    case CI = 'CI';
    case Borderpass = 'Borderpass';
    case ThaiID = 'ThaiID';
    case Passport = 'Passport';
    case Other = 'Other';

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::TenYearsID => '10 Years ID',
            self::BurmeseID => 'Burmese ID',
            self::CI => 'CI',
            self::Borderpass => 'Borderpass',
            self::ThaiID => 'Thai ID',
            self::Passport => 'Passport',
            self::Other => 'Other',
        };
    }

    /**
     * Get all values as an array.
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

---

### Step 2.2: Create EmployeeIdentificationService

**NEW file:** `app/Services/EmployeeIdentificationService.php`

Follows the `EmployeeChildService` pattern for basic CRUD, with added `setPrimary()` using pessimistic locking.

```php
<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeIdentification;
use App\Notifications\EmployeeActionNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EmployeeIdentificationService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Get all identifications for an employee.
     */
    public function listByEmployee(int $employeeId): Collection
    {
        return EmployeeIdentification::where('employee_id', $employeeId)
            ->with('employee:id,staff_id,first_name_en,last_name_en')
            ->orderByDesc('is_primary')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Get a single identification with the employee relationship loaded.
     */
    public function show(EmployeeIdentification $identification): EmployeeIdentification
    {
        return $identification->loadMissing('employee:id,staff_id,first_name_en,last_name_en');
    }

    /**
     * Create a new identification record.
     *
     * If is_primary = true, the observer handles:
     * - Unsetting other primaries for this employee
     * - Syncing name fields to the employee
     */
    public function create(array $data): EmployeeIdentification
    {
        $user = Auth::user();
        $data['created_by'] = $user->name ?? 'System';
        $data['updated_by'] = $user->name ?? 'System';

        // If this is the employee's first identification, auto-set as primary
        $existingCount = EmployeeIdentification::where('employee_id', $data['employee_id'])->count();
        if ($existingCount === 0) {
            $data['is_primary'] = true;
        }

        $identification = EmployeeIdentification::create($data);

        $this->invalidateCache();
        $this->notifyAction($identification);

        return $identification->loadMissing('employee:id,staff_id,first_name_en,last_name_en');
    }

    /**
     * Update an existing identification record.
     *
     * If is_primary is changed to true, the observer handles name sync.
     * If name fields change on a primary record, the observer re-syncs.
     */
    public function update(EmployeeIdentification $identification, array $data): EmployeeIdentification
    {
        $data['updated_by'] = Auth::user()->name ?? 'System';

        $identification->update($data);

        $this->invalidateCache();
        $this->notifyAction($identification);

        return $identification->loadMissing('employee:id,staff_id,first_name_en,last_name_en');
    }

    /**
     * Delete an identification record.
     *
     * Cannot delete the primary identification if it's the only one.
     *
     * @return array{success: bool, message?: string}
     */
    public function delete(EmployeeIdentification $identification): array
    {
        if ($identification->is_primary) {
            $otherCount = EmployeeIdentification::where('employee_id', $identification->employee_id)
                ->where('id', '!=', $identification->id)
                ->count();

            if ($otherCount === 0) {
                return [
                    'success' => false,
                    'message' => 'Cannot delete the only identification record. Add another identification first.',
                ];
            }
        }

        // Load employee before deletion for notification
        $employee = $identification->employee;

        $identification->delete();

        // If the deleted record was primary, promote the most recent remaining one
        if ($identification->is_primary) {
            $nextPrimary = EmployeeIdentification::where('employee_id', $identification->employee_id)
                ->orderByDesc('created_at')
                ->first();

            if ($nextPrimary) {
                // This triggers the observer which syncs names
                $nextPrimary->update([
                    'is_primary' => true,
                    'updated_by' => Auth::user()->name ?? 'System',
                ]);
            }
        }

        $this->invalidateCache();

        return ['success' => true];
    }

    /**
     * Set a specific identification record as the primary.
     *
     * Uses pessimistic locking (SELECT FOR UPDATE) to prevent race conditions
     * when two users simultaneously set different records as primary.
     *
     * The observer handles:
     * - Unsetting other primaries
     * - Syncing name fields to the employee
     */
    public function setPrimary(EmployeeIdentification $identification): EmployeeIdentification
    {
        if ($identification->is_primary) {
            return $identification->loadMissing('employee:id,staff_id,first_name_en,last_name_en');
        }

        DB::transaction(function () use ($identification) {
            // Lock the target row to prevent concurrent modification
            $identification = EmployeeIdentification::where('id', $identification->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Lock all identifications for this employee to prevent race conditions
            EmployeeIdentification::where('employee_id', $identification->employee_id)
                ->where('id', '!=', $identification->id)
                ->lockForUpdate()
                ->update(['is_primary' => false]);

            // Set as primary — observer fires and syncs names to employee
            $identification->update([
                'is_primary' => true,
                'updated_by' => Auth::user()->name ?? 'System',
            ]);
        });

        $this->invalidateCache();
        $this->notifyAction($identification);

        return $identification->fresh()->loadMissing('employee:id,staff_id,first_name_en,last_name_en');
    }

    /**
     * Clear employee statistics cache.
     */
    private function invalidateCache(): void
    {
        Cache::forget('employee_statistics');
    }

    /**
     * Send notification for employee identification action.
     */
    private function notifyAction(EmployeeIdentification $identification): void
    {
        $performedBy = Auth::user();
        if (! $performedBy) {
            return;
        }

        $employee = $identification->employee;
        if (! $employee) {
            return;
        }

        $this->notificationService->notifyByModule(
            'employees',
            new EmployeeActionNotification('updated', $employee, $performedBy, 'employees'),
            'updated'
        );
    }
}
```

**Key design decisions:**
- `setPrimary()` uses `lockForUpdate()` inside `DB::transaction()` — prevents race condition where two users click "Set as Primary" simultaneously
- `delete()` auto-promotes the next record to primary when the primary is deleted
- `create()` auto-sets `is_primary = true` for the first identification added to an employee
- The observer does the actual name sync — the service only manages the `is_primary` flag

---

### Step 2.3: Create form requests

**NEW file:** `app/Http/Requests/StoreEmployeeIdentificationRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Enums\IdentificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeIdentificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'identification_type' => ['required', 'string', Rule::in(IdentificationType::values())],
            'identification_number' => ['required', 'string', 'max:50'],
            'identification_issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'identification_expiry_date' => ['nullable', 'date', 'after:identification_issue_date'],
            'first_name_en' => ['nullable', 'string', 'max:255'],
            'last_name_en' => ['nullable', 'string', 'max:255'],
            'first_name_th' => ['nullable', 'string', 'max:255'],
            'last_name_th' => ['nullable', 'string', 'max:255'],
            'initial_en' => ['nullable', 'string', 'max:10'],
            'initial_th' => ['nullable', 'string', 'max:10'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Please select an employee.',
            'employee_id.exists' => 'The selected employee does not exist.',
            'identification_type.required' => 'Identification type is required.',
            'identification_type.in' => 'Invalid identification type. Allowed: ' . implode(', ', IdentificationType::values()),
            'identification_number.required' => 'Identification number is required.',
            'identification_expiry_date.after' => 'Expiry date must be after the issue date.',
        ];
    }
}
```

**NEW file:** `app/Http/Requests/UpdateEmployeeIdentificationRequest.php`

```php
<?php

namespace App\Http\Requests;

use App\Enums\IdentificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeIdentificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identification_type' => ['sometimes', 'string', Rule::in(IdentificationType::values())],
            'identification_number' => ['sometimes', 'string', 'max:50'],
            'identification_issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'identification_expiry_date' => ['nullable', 'date', 'after:identification_issue_date'],
            'first_name_en' => ['nullable', 'string', 'max:255'],
            'last_name_en' => ['nullable', 'string', 'max:255'],
            'first_name_th' => ['nullable', 'string', 'max:255'],
            'last_name_th' => ['nullable', 'string', 'max:255'],
            'initial_en' => ['nullable', 'string', 'max:10'],
            'initial_th' => ['nullable', 'string', 'max:10'],
        ];
    }

    public function messages(): array
    {
        return [
            'identification_type.in' => 'Invalid identification type. Allowed: ' . implode(', ', IdentificationType::values()),
            'identification_expiry_date.after' => 'Expiry date must be after the issue date.',
        ];
    }
}
```

**Note:** `is_primary` is NOT in the update request. Use the dedicated `PATCH /set-primary` endpoint instead to prevent accidental side effects.

---

### Step 2.4: Create EmployeeIdentificationResource

**NEW file:** `app/Http/Resources/EmployeeIdentificationResource.php`

Follows the `EmployeeChildResource` pattern.

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeIdentificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'identification_type' => $this->identification_type,
            'identification_number' => $this->identification_number,
            'identification_issue_date' => $this->identification_issue_date,
            'identification_expiry_date' => $this->identification_expiry_date,
            'first_name_en' => $this->first_name_en,
            'last_name_en' => $this->last_name_en,
            'first_name_th' => $this->first_name_th,
            'last_name_th' => $this->last_name_th,
            'initial_en' => $this->initial_en,
            'initial_th' => $this->initial_th,
            'is_primary' => $this->is_primary,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships
            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'id' => $this->employee->id,
                    'staff_id' => $this->employee->staff_id,
                    'first_name_en' => $this->employee->first_name_en,
                    'last_name_en' => $this->employee->last_name_en,
                ];
            }),
        ];
    }
}
```

---

### Step 2.5: Create EmployeeIdentificationController

**NEW file:** `app/Http/Controllers/Api/V1/EmployeeIdentificationController.php`

Follows the `EmployeeChildrenController` pattern exactly.

```php
<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\StoreEmployeeIdentificationRequest;
use App\Http\Requests\UpdateEmployeeIdentificationRequest;
use App\Http\Resources\EmployeeIdentificationResource;
use App\Models\EmployeeIdentification;
use App\Services\EmployeeIdentificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

/**
 * Handles CRUD operations for employee identification documents.
 */
#[OA\Tag(name: 'Employee Identifications', description: 'API Endpoints for Employee Identification management')]
class EmployeeIdentificationController extends BaseApiController
{
    public function __construct(
        private readonly EmployeeIdentificationService $identificationService
    ) {}

    /**
     * List all identifications for a given employee.
     */
    #[OA\Get(
        path: '/employee-identifications',
        summary: 'Get all identifications for an employee',
        tags: ['Employee Identifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employee_id', in: 'query', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $request->validate(['employee_id' => 'required|integer|exists:employees,id']);

        $identifications = $this->identificationService->listByEmployee(
            $request->integer('employee_id')
        );

        return $this->successResponse(
            EmployeeIdentificationResource::collection($identifications),
            'Employee identifications retrieved successfully'
        );
    }

    /**
     * Retrieve a specific identification by ID.
     */
    #[OA\Get(
        path: '/employee-identifications/{employeeIdentification}',
        summary: 'Get identification by ID',
        tags: ['Employee Identifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employeeIdentification', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Successful operation'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function show(EmployeeIdentification $employeeIdentification): JsonResponse
    {
        $identification = $this->identificationService->show($employeeIdentification);

        return EmployeeIdentificationResource::make($identification)
            ->additional(['success' => true, 'message' => 'Employee identification retrieved successfully'])
            ->response();
    }

    /**
     * Create a new identification record.
     */
    #[OA\Post(
        path: '/employee-identifications',
        summary: 'Create a new employee identification',
        tags: ['Employee Identifications'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/EmployeeIdentification')),
        responses: [
            new OA\Response(response: 201, description: 'Created successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(StoreEmployeeIdentificationRequest $request): JsonResponse
    {
        $identification = $this->identificationService->create($request->validated());

        return EmployeeIdentificationResource::make($identification)
            ->additional(['success' => true, 'message' => 'Employee identification created successfully'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an existing identification record.
     */
    #[OA\Put(
        path: '/employee-identifications/{employeeIdentification}',
        summary: 'Update an employee identification',
        tags: ['Employee Identifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employeeIdentification', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(type: 'object')),
        responses: [
            new OA\Response(response: 200, description: 'Updated successfully'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function update(UpdateEmployeeIdentificationRequest $request, EmployeeIdentification $employeeIdentification): JsonResponse
    {
        $identification = $this->identificationService->update(
            $employeeIdentification,
            $request->validated()
        );

        return EmployeeIdentificationResource::make($identification)
            ->additional(['success' => true, 'message' => 'Employee identification updated successfully'])
            ->response();
    }

    /**
     * Delete an identification record.
     */
    #[OA\Delete(
        path: '/employee-identifications/{employeeIdentification}',
        summary: 'Delete an employee identification',
        tags: ['Employee Identifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employeeIdentification', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Deleted successfully'),
            new OA\Response(response: 404, description: 'Not found'),
            new OA\Response(response: 422, description: 'Cannot delete only identification'),
        ]
    )]
    public function destroy(EmployeeIdentification $employeeIdentification): JsonResponse
    {
        $result = $this->identificationService->delete($employeeIdentification);

        if (! $result['success']) {
            return $this->errorResponse($result['message'], 422);
        }

        return $this->successResponse(null, 'Employee identification deleted successfully');
    }

    /**
     * Set an identification record as the primary.
     *
     * Side effects:
     * - All other identifications for this employee have is_primary set to false
     * - Employee name fields are synced from this identification record
     */
    #[OA\Patch(
        path: '/employee-identifications/{employeeIdentification}/set-primary',
        summary: 'Set identification as primary',
        tags: ['Employee Identifications'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'employeeIdentification', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Primary set successfully'),
            new OA\Response(response: 404, description: 'Not found'),
        ]
    )]
    public function setPrimary(EmployeeIdentification $employeeIdentification): JsonResponse
    {
        $identification = $this->identificationService->setPrimary($employeeIdentification);

        return EmployeeIdentificationResource::make($identification)
            ->additional(['success' => true, 'message' => 'Primary identification updated successfully'])
            ->response();
    }
}
```

---

### Step 2.6: Add routes

**MODIFY file:** `routes/api/employees.php`

Add import at top:
```php
use App\Http\Controllers\Api\V1\EmployeeIdentificationController;
```

Add after the employee-language route group (after line 96):

```php
// Employee identification routes (use employees permission)
Route::prefix('employee-identifications')->group(function () {
    Route::get('/', [EmployeeIdentificationController::class, 'index'])->middleware('permission:employees.read');
    Route::get('/{employeeIdentification}', [EmployeeIdentificationController::class, 'show'])->middleware('permission:employees.read');
    Route::post('/', [EmployeeIdentificationController::class, 'store'])->middleware('permission:employees.create');
    Route::put('/{employeeIdentification}', [EmployeeIdentificationController::class, 'update'])->middleware('permission:employees.update');
    Route::patch('/{employeeIdentification}/set-primary', [EmployeeIdentificationController::class, 'setPrimary'])->middleware('permission:employees.update');
    Route::delete('/{employeeIdentification}', [EmployeeIdentificationController::class, 'destroy'])->middleware('permission:employees.delete');
});
```

---

### Phase 2 verification

After Phase 2, the new CRUD endpoints work independently. Existing employee endpoints are unchanged.

```bash
php artisan route:list --path=employee-identifications
php artisan test
```

---

## Phase 3 — Update Reads

Safe, additive changes. All backward-compatible.

### Step 3.1: Update EmployeeResource (backward-compatible)

**MODIFY file:** `app/Http/Resources/EmployeeResource.php`

Replace lines 28-31 (the 4 identification fields):

```php
// FROM (lines 28-31):
'identification_type' => $this->identification_type,
'identification_number' => $this->identification_number,
'identification_issue_date' => $this->identification_issue_date,
'identification_expiry_date' => $this->identification_expiry_date,

// TO:
// Backward-compatible flat fields (from primary identification)
'identification_type' => $this->whenLoaded('primaryIdentification',
    fn () => $this->primaryIdentification?->identification_type,
    $this->identification_type  // Fallback to direct column during transition
),
'identification_number' => $this->whenLoaded('primaryIdentification',
    fn () => $this->primaryIdentification?->identification_number,
    $this->identification_number
),
'identification_issue_date' => $this->whenLoaded('primaryIdentification',
    fn () => $this->primaryIdentification?->identification_issue_date,
    $this->identification_issue_date
),
'identification_expiry_date' => $this->whenLoaded('primaryIdentification',
    fn () => $this->primaryIdentification?->identification_expiry_date,
    $this->identification_expiry_date
),
```

**Why the fallback?** During the transition period, some code paths may not eager-load `primaryIdentification`. The fallback reads the old direct columns (which still exist). Once Phase 4 removes the columns from `$fillable`, the fallback returns null, which is fine.

---

### Step 3.2: Update EmployeeDetailResource (backward-compatible + full collection)

**MODIFY file:** `app/Http/Resources/EmployeeDetailResource.php`

Replace lines 37-40:

```php
// FROM (lines 37-40):
'identification_type' => $this->identification_type,
'identification_number' => $this->identification_number,
'identification_issue_date' => $this->identification_issue_date,
'identification_expiry_date' => $this->identification_expiry_date,

// TO:
// Backward-compatible flat fields (from primary identification)
'identification_type' => $this->primaryIdentification?->identification_type ?? $this->identification_type,
'identification_number' => $this->primaryIdentification?->identification_number ?? $this->identification_number,
'identification_issue_date' => $this->primaryIdentification?->identification_issue_date ?? $this->identification_issue_date,
'identification_expiry_date' => $this->primaryIdentification?->identification_expiry_date ?? $this->identification_expiry_date,

// New structured fields
'primary_identification' => $this->whenLoaded('primaryIdentification', function () {
    return new EmployeeIdentificationResource($this->primaryIdentification);
}),
'identifications' => EmployeeIdentificationResource::collection(
    $this->whenLoaded('identifications')
),
```

Add import at top:
```php
use App\Http\Resources\EmployeeIdentificationResource;
```

---

### Step 3.3: Update EmployeeDataService show() to eager-load identifications

**MODIFY file:** `app/Services/EmployeeDataService.php`

In `show()` method (line 100-118), add to the `load()` array:

```php
// Add these two lines to the existing load() array:
'identifications',
'primaryIdentification',
```

The full `show()` becomes:
```php
public function show(Employee $employee): Employee
{
    return $employee->load([
        'employment',
        'employment.department',
        'employment.position',
        'employment.site',
        'employeeFundingAllocations',
        'employeeFundingAllocations.grantItem',
        'employeeFundingAllocations.grantItem.grant',
        'employeeFundingAllocations.employment',
        'employeeFundingAllocations.employment.department',
        'employeeFundingAllocations.employment.position',
        'employeeBeneficiaries',
        'employeeEducation',
        'employeeChildren',
        'employeeLanguages',
        'leaveBalances',
        'leaveBalances.leaveType',
        'identifications',
        'primaryIdentification',
    ]);
}
```

---

### Step 3.4: Update EmployeesExport to read from primaryIdentification

**MODIFY file:** `app/Exports/EmployeesExport.php`

In `query()` (line 59), add `'primaryIdentification'` to the `with()` array:

```php
// FROM (line 59):
return $query->with(['employeeBeneficiaries', 'employment:id,employee_id,organization']);

// TO:
return $query->with(['employeeBeneficiaries', 'employment:id,employee_id,organization', 'primaryIdentification']);
```

In `map()` (lines 122-126), change the identification type lookup:

```php
// FROM (lines 122-126):
$identificationTypeDisplay = null;
if ($employee->identification_type) {
    $identificationTypeDisplay = self::IDENTIFICATION_TYPE_REVERSE_MAPPING[$employee->identification_type] ?? $employee->identification_type;
}

// TO:
$primary = $employee->primaryIdentification;
$identificationTypeDisplay = null;
if ($primary?->identification_type) {
    $identificationTypeDisplay = self::IDENTIFICATION_TYPE_REVERSE_MAPPING[$primary->identification_type] ?? $primary->identification_type;
}
```

In the return array (lines 151-154), change:

```php
// FROM (lines 151-154):
$identificationTypeDisplay,
$employee->identification_number,
$employee->identification_issue_date,
$employee->identification_expiry_date,

// TO:
$identificationTypeDisplay,
$primary?->identification_number,
$primary?->identification_issue_date,
$primary?->identification_expiry_date,
```

---

## Phase 4 — Update Writes

These changes redirect identification field writes from the `employees` table to the `employee_identifications` table.

### Step 4.1: Remove identification fields from Employee model

**MODIFY file:** `app/Models/Employee.php`

**Remove from `$fillable` (lines 90-93):**
```php
// DELETE these 4 lines:
'identification_type',
'identification_number',
'identification_issue_date',
'identification_expiry_date',
```

**Remove from `$casts` (lines 128-129):**
```php
// DELETE these 2 lines:
'identification_issue_date' => 'date',
'identification_expiry_date' => 'date',
```

**Remove the `getIdTypeAttribute()` accessor (lines 381-384):**
```php
// DELETE this entire method:
public function getIdTypeAttribute()
{
    return $this->identification_type;
}
```

**Rewrite `scopeByIdType()` (lines 353-360):**
```php
// FROM:
public function scopeByIdType($query, $idTypes)
{
    if (is_string($idTypes)) {
        $idTypes = explode(',', $idTypes);
    }

    return $query->whereIn('identification_type', array_filter($idTypes));
}

// TO:
public function scopeByIdType($query, $idTypes)
{
    if (is_string($idTypes)) {
        $idTypes = explode(',', $idTypes);
    }

    return $query->whereHas('identifications', function ($q) use ($idTypes) {
        $q->whereIn('identification_type', array_filter($idTypes));
    });
}
```

**Update OpenAPI schema (lines 32-35):**
```php
// DELETE these 4 lines:
new OA\Property(property: 'identification_type', type: 'string', maxLength: 50, nullable: true),
new OA\Property(property: 'identification_number', type: 'string', maxLength: 50, nullable: true),
new OA\Property(property: 'identification_issue_date', type: 'string', format: 'date', nullable: true),
new OA\Property(property: 'identification_expiry_date', type: 'string', format: 'date', nullable: true),
```

---

### Step 4.2: Update EmployeeDataService store()

**MODIFY file:** `app/Services/EmployeeDataService.php`

```php
// FROM (lines 123-131):
public function store(array $validated): Employee
{
    $employee = Employee::create($validated);

    $this->invalidateCache();
    $this->notifyAction('created', $employee);

    return $employee;
}

// TO:
public function store(array $validated): Employee
{
    // Extract identification fields — these go to employee_identifications, not employees
    $identificationData = $this->extractIdentificationData($validated);

    $employee = Employee::create($validated);

    // Create identification record if identification data was provided
    if (! empty($identificationData)) {
        $identificationData['employee_id'] = $employee->id;
        $identificationData['is_primary'] = true;
        $identificationData['created_by'] = $validated['created_by'] ?? auth()->user()->name ?? 'System';
        $identificationData['updated_by'] = $identificationData['created_by'];

        // Copy name fields from the employee to the identification record
        $identificationData['first_name_en'] = $validated['first_name_en'] ?? null;
        $identificationData['last_name_en'] = $validated['last_name_en'] ?? null;
        $identificationData['first_name_th'] = $validated['first_name_th'] ?? null;
        $identificationData['last_name_th'] = $validated['last_name_th'] ?? null;
        $identificationData['initial_en'] = $validated['initial_en'] ?? null;
        $identificationData['initial_th'] = $validated['initial_th'] ?? null;

        EmployeeIdentification::create($identificationData);
    }

    $this->invalidateCache();
    $this->notifyAction('created', $employee);

    return $employee;
}
```

---

### Step 4.3: Update EmployeeDataService fullUpdate()

```php
// FROM (lines 136-147):
public function fullUpdate(Employee $employee, array $validated): Employee
{
    $employee->update($validated + [
        'updated_by' => auth()->user()->name ?? 'system',
    ]);

    $this->invalidateCache();
    $employee->refresh();
    $this->notifyAction('updated', $employee);

    return $employee;
}

// TO:
public function fullUpdate(Employee $employee, array $validated): Employee
{
    // Extract identification fields — handle separately
    $identificationData = $this->extractIdentificationData($validated);

    $employee->update($validated + [
        'updated_by' => auth()->user()->name ?? 'system',
    ]);

    // Update or create primary identification if data was provided
    if (! empty($identificationData)) {
        $primary = $employee->primaryIdentification;
        $updatedBy = auth()->user()->name ?? 'system';

        if ($primary) {
            $primary->update($identificationData + ['updated_by' => $updatedBy]);
        } else {
            $identificationData['employee_id'] = $employee->id;
            $identificationData['is_primary'] = true;
            $identificationData['created_by'] = $updatedBy;
            $identificationData['updated_by'] = $updatedBy;
            EmployeeIdentification::create($identificationData);
        }
    }

    $this->invalidateCache();
    $employee->refresh();
    $this->notifyAction('updated', $employee);

    return $employee;
}
```

---

### Step 4.4: Update EmployeeDataService updatePersonalInfo()

```php
// FROM (lines 412-421):
// Handle identification fields - support both direct and legacy nested format
if (isset($data['employee_identification']) && ! isset($data['identification_type'])) {
    $identData = $data['employee_identification'];
    if (isset($identData['id_type'])) {
        $data['identification_type'] = $identData['id_type'];
    }
    if (isset($identData['document_number'])) {
        $data['identification_number'] = $identData['document_number'];
    }
}

// Remove relation keys before updating main table
unset($data['employee_identification'], $data['languages']);

// TO:
// Extract identification fields and legacy nested format
$identificationData = $this->extractIdentificationData($data);

// Handle legacy nested format (employee_identification.id_type / document_number)
if (isset($data['employee_identification'])) {
    $legacyData = $data['employee_identification'];
    if (isset($legacyData['id_type'])) {
        $identificationData['identification_type'] = $legacyData['id_type'];
    }
    if (isset($legacyData['document_number'])) {
        $identificationData['identification_number'] = $legacyData['document_number'];
    }
}

// Remove relation keys before updating main table
unset($data['employee_identification'], $data['languages']);

$employee->update($data);

// Update or create primary identification
if (! empty($identificationData)) {
    $primary = $employee->primaryIdentification;
    $updatedBy = auth()->user()->name ?? 'system';

    if ($primary) {
        $primary->update($identificationData + ['updated_by' => $updatedBy]);
    } else if (isset($identificationData['identification_type'])) {
        $identificationData['employee_id'] = $employee->id;
        $identificationData['is_primary'] = true;
        $identificationData['created_by'] = $updatedBy;
        $identificationData['updated_by'] = $updatedBy;
        EmployeeIdentification::create($identificationData);
    }
}
```

---

### Step 4.5: Add extractIdentificationData() helper to EmployeeDataService

Add as a new private method:

```php
/**
 * Extract identification fields from a validated array.
 *
 * Removes the identification fields from the input array (by reference via unset)
 * and returns them as a separate array for the employee_identifications table.
 */
private function extractIdentificationData(array &$data): array
{
    $identificationFields = [
        'identification_type',
        'identification_number',
        'identification_issue_date',
        'identification_expiry_date',
    ];

    $identificationData = [];

    foreach ($identificationFields as $field) {
        if (array_key_exists($field, $data)) {
            $identificationData[$field] = $data[$field];
            unset($data[$field]);
        }
    }

    return $identificationData;
}
```

Add import at top of EmployeeDataService:
```php
use App\Models\EmployeeIdentification;
```

---

### Step 4.6: Update EmployeeDataService list() sort

```php
// FROM (lines 77-78):
} elseif ($sortBy === 'identification_type') {
    $query->orderBy('employees.identification_type', $sortOrder);

// TO:
} elseif ($sortBy === 'identification_type') {
    $query->leftJoin('employee_identifications', function ($join) {
        $join->on('employees.id', '=', 'employee_identifications.employee_id')
            ->where('employee_identifications.is_primary', true);
    })
    ->orderBy('employee_identifications.identification_type', $sortOrder)
    ->select('employees.*');
```

---

### Step 4.7: Remove identification rules from form requests

**MODIFY file:** `app/Http/Requests/StoreEmployeeRequest.php`

Delete lines 39-43:
```php
// DELETE these lines:
// Identification - direct columns (not separate table)
'identification_type' => ['nullable', 'string', 'in:10YearsID,BurmeseID,CI,Borderpass,ThaiID,Passport,Other'],
'identification_number' => ['nullable', 'string', 'max:50', 'required_with:identification_type'],
'identification_issue_date' => ['nullable', 'date', 'before_or_equal:today'],
'identification_expiry_date' => ['nullable', 'date', 'after:identification_issue_date'],
```

**MODIFY file:** `app/Http/Requests/UpdateEmployeeRequest.php`

Delete lines 40-44:
```php
// DELETE these lines:
// Identification - direct columns (not separate table)
'identification_type' => ['nullable', 'string', 'in:10YearsID,BurmeseID,CI,Borderpass,ThaiID,Passport,Other'],
'identification_number' => ['nullable', 'string', 'max:50', 'required_with:identification_type'],
'identification_issue_date' => ['nullable', 'date', 'before_or_equal:today'],
'identification_expiry_date' => ['nullable', 'date', 'after:identification_issue_date'],
```

**MODIFY file:** `app/Http/Requests/UpdateEmployeePersonalRequest.php`

Delete lines 31-38:
```php
// DELETE these lines:
'identification_type' => ['nullable', 'string', 'in:10YearsID,BurmeseID,CI,Borderpass,ThaiID,Passport,Other'],
'identification_number' => ['nullable', 'string', 'max:50', 'required_with:identification_type'],
'identification_issue_date' => ['nullable', 'date', 'before_or_equal:today'],
'identification_expiry_date' => ['nullable', 'date', 'after:identification_issue_date'],
// Support legacy nested format for backward compatibility
'employee_identification' => ['nullable', 'array'],
'employee_identification.id_type' => ['nullable', 'string', 'max:30'],
'employee_identification.document_number' => ['nullable', 'string', 'max:50'],
```

**MODIFY file:** `app/Http/Requests/Employee/FullUpdateEmployeeRequest.php`

Delete lines 35-36:
```php
// DELETE these lines:
'identification_type' => ['nullable', 'string', 'in:10YearsID,BurmeseID,CI,Borderpass,ThaiID,Passport,Other'],
'identification_number' => ['nullable', 'string', 'max:50', 'required_with:identification_type'],
```

---

## Phase 5 — Update Imports

### Step 5.1: Update EmployeesImport

**MODIFY file:** `app/Imports/EmployeesImport.php`

**In the validation pass (lines 371-414):** Remove identification fields from `$validatedEmployees[]` and build `$validatedIdentifications[]` instead.

```php
// In the $validatedEmployees[] array, DELETE these 4 lines:
'identification_type' => $identificationTypeValidation['identificationType'],
'identification_number' => $this->trimOrNull($row['id_number'] ?? null),
'identification_issue_date' => $this->parseDate($row['id_issue_date'] ?? null),
'identification_expiry_date' => $this->parseDate($row['id_expiry_date'] ?? null),
```

After the `$validatedEmployees[]` array (around line 414), before the kin1 beneficiary block, add:

```php
// Prepare identification data (linked later after employees inserted)
if ($identificationTypeValidation['identificationType'] !== null) {
    $validatedIdentifications[] = [
        '_staff_id' => $staffIdValidation['staffId'],
        'identification_type' => $identificationTypeValidation['identificationType'],
        'identification_number' => $this->trimOrNull($row['id_number'] ?? null),
        'identification_issue_date' => $this->parseDate($row['id_issue_date'] ?? null),
        'identification_expiry_date' => $this->parseDate($row['id_expiry_date'] ?? null),
        'first_name_en' => $firstNameValidation['firstName'],
        'last_name_en' => $this->trimOrNull($row['last_name'] ?? null),
        'first_name_th' => $this->trimOrNull($row['first_name_th'] ?? null),
        'last_name_th' => $this->trimOrNull($row['last_name_th'] ?? null),
        'initial_en' => $this->trimOrNull($row['initial'] ?? null),
        'initial_th' => $this->trimOrNull($row['initial_th'] ?? null),
        'is_primary' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ];
}
```

Initialize `$validatedIdentifications = [];` at the top of the method alongside `$validatedBeneficiaries = [];`.

**In the insertion pass (after line 522, after beneficiary insert):** Add identification insert block:

```php
// Link identifications to employees and insert
if (! empty($validatedIdentifications)) {
    $identificationsToInsert = [];
    foreach ($validatedIdentifications as $ident) {
        $staffId = $ident['_staff_id'];
        unset($ident['_staff_id']); // Remove temporary key

        if (isset($employeeIdMap[$staffId])) {
            $ident['employee_id'] = $employeeIdMap[$staffId];
            $identificationsToInsert[] = $ident;
        }
    }

    if (! empty($identificationsToInsert)) {
        DB::table('employee_identifications')->insert($identificationsToInsert);
        Log::info('Inserted identifications', ['count' => count($identificationsToInsert), 'import_id' => $this->importId]);
    }
}
```

**Important:** Uses `DB::table()->insert()` (NOT `EmployeeIdentification::create()`). This intentionally bypasses the observer because during import:
- Names are already on the employee from the same Excel row
- No name sync needed — both records already have identical names

---

### Step 5.2: Update DevEmployeesImport (same approach)

**MODIFY file:** `app/Imports/DevEmployeesImport.php`

Apply the same pattern as EmployeesImport:
1. Remove identification fields from the employee data array
2. Build `$validatedIdentifications[]` with `_staff_id` linking key
3. Insert identifications after employees using `DB::table()->insert()`

---

## Phase 6 — Tests

### Step 6.1: Create EmployeeIdentificationApiTest

**NEW file:** `tests/Feature/Api/EmployeeIdentificationApiTest.php`

```php
<?php

use App\Models\Employee;
use App\Models\EmployeeIdentification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

describe('Employee Identification API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create();

        Permission::firstOrCreate(['name' => 'employees.read']);
        Permission::firstOrCreate(['name' => 'employees.create']);
        Permission::firstOrCreate(['name' => 'employees.update']);
        Permission::firstOrCreate(['name' => 'employees.delete']);

        $this->user->givePermissionTo(['employees.read', 'employees.create', 'employees.update', 'employees.delete']);
        $this->actingAs($this->user);
    });

    describe('GET /api/v1/employee-identifications', function () {
        it('lists identifications for an employee', function () {
            $employee = Employee::factory()->create();
            EmployeeIdentification::factory()->count(2)->create(['employee_id' => $employee->id]);

            $response = $this->getJson("/api/v1/employee-identifications?employee_id={$employee->id}");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
            expect($response->json('data'))->toHaveCount(2);
        });

        it('requires employee_id parameter', function () {
            $response = $this->getJson('/api/v1/employee-identifications');

            $response->assertStatus(422);
        });
    });

    describe('POST /api/v1/employee-identifications', function () {
        it('creates a new identification', function () {
            $employee = Employee::factory()->create();

            $data = [
                'employee_id' => $employee->id,
                'identification_type' => 'Passport',
                'identification_number' => 'AB1234567',
                'first_name_en' => 'John',
                'last_name_en' => 'Doe',
            ];

            $response = $this->postJson('/api/v1/employee-identifications', $data);

            $response->assertStatus(201)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('employee_identifications', [
                'employee_id' => $employee->id,
                'identification_type' => 'Passport',
                'identification_number' => 'AB1234567',
            ]);
        });

        it('auto-sets first identification as primary', function () {
            $employee = Employee::factory()->create();

            $response = $this->postJson('/api/v1/employee-identifications', [
                'employee_id' => $employee->id,
                'identification_type' => 'ThaiID',
                'identification_number' => '1234567890123',
            ]);

            $response->assertStatus(201);

            $this->assertDatabaseHas('employee_identifications', [
                'employee_id' => $employee->id,
                'is_primary' => true,
            ]);
        });

        it('validates required fields', function () {
            $response = $this->postJson('/api/v1/employee-identifications', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['employee_id', 'identification_type', 'identification_number']);
        });

        it('validates identification_type enum', function () {
            $employee = Employee::factory()->create();

            $response = $this->postJson('/api/v1/employee-identifications', [
                'employee_id' => $employee->id,
                'identification_type' => 'InvalidType',
                'identification_number' => '12345',
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['identification_type']);
        });
    });

    describe('PUT /api/v1/employee-identifications/{id}', function () {
        it('updates an identification', function () {
            $identification = EmployeeIdentification::factory()->create([
                'identification_number' => 'OLD123',
            ]);

            $response = $this->putJson("/api/v1/employee-identifications/{$identification->id}", [
                'identification_number' => 'NEW456',
            ]);

            $response->assertStatus(200)
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('employee_identifications', [
                'id' => $identification->id,
                'identification_number' => 'NEW456',
            ]);
        });

        it('syncs names to employee when primary record name fields are updated', function () {
            $employee = Employee::factory()->create([
                'first_name_en' => 'OldFirst',
                'last_name_en' => 'OldLast',
            ]);
            $identification = EmployeeIdentification::factory()->create([
                'employee_id' => $employee->id,
                'is_primary' => true,
                'first_name_en' => 'OldFirst',
                'last_name_en' => 'OldLast',
            ]);

            $response = $this->putJson("/api/v1/employee-identifications/{$identification->id}", [
                'first_name_en' => 'NewFirst',
                'last_name_en' => 'NewLast',
            ]);

            $response->assertStatus(200);

            // Employee names should be synced
            $this->assertDatabaseHas('employees', [
                'id' => $employee->id,
                'first_name_en' => 'NewFirst',
                'last_name_en' => 'NewLast',
            ]);
        });
    });

    describe('PATCH /api/v1/employee-identifications/{id}/set-primary', function () {
        it('sets identification as primary and syncs names', function () {
            $employee = Employee::factory()->create([
                'first_name_en' => 'OriginalFirst',
                'last_name_en' => 'OriginalLast',
            ]);

            $oldPrimary = EmployeeIdentification::factory()->create([
                'employee_id' => $employee->id,
                'is_primary' => true,
                'first_name_en' => 'OriginalFirst',
            ]);

            $newPrimary = EmployeeIdentification::factory()->create([
                'employee_id' => $employee->id,
                'is_primary' => false,
                'first_name_en' => 'PassportFirst',
                'last_name_en' => 'PassportLast',
                'identification_type' => 'Passport',
            ]);

            $response = $this->patchJson("/api/v1/employee-identifications/{$newPrimary->id}/set-primary");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);

            // New primary should be set
            $this->assertDatabaseHas('employee_identifications', [
                'id' => $newPrimary->id,
                'is_primary' => true,
            ]);

            // Old primary should be unset
            $this->assertDatabaseHas('employee_identifications', [
                'id' => $oldPrimary->id,
                'is_primary' => false,
            ]);

            // Employee names should be synced from new primary
            $this->assertDatabaseHas('employees', [
                'id' => $employee->id,
                'first_name_en' => 'PassportFirst',
                'last_name_en' => 'PassportLast',
            ]);
        });

        it('is a no-op when already primary', function () {
            $identification = EmployeeIdentification::factory()->create(['is_primary' => true]);

            $response = $this->patchJson("/api/v1/employee-identifications/{$identification->id}/set-primary");

            $response->assertStatus(200);
        });
    });

    describe('DELETE /api/v1/employee-identifications/{id}', function () {
        it('deletes a non-primary identification', function () {
            $employee = Employee::factory()->create();
            EmployeeIdentification::factory()->create(['employee_id' => $employee->id, 'is_primary' => true]);
            $nonPrimary = EmployeeIdentification::factory()->create(['employee_id' => $employee->id, 'is_primary' => false]);

            $response = $this->deleteJson("/api/v1/employee-identifications/{$nonPrimary->id}");

            $response->assertStatus(200)
                ->assertJson(['success' => true]);
        });

        it('prevents deleting the only identification', function () {
            $employee = Employee::factory()->create();
            $only = EmployeeIdentification::factory()->create(['employee_id' => $employee->id, 'is_primary' => true]);

            $response = $this->deleteJson("/api/v1/employee-identifications/{$only->id}");

            $response->assertStatus(422);
        });

        it('promotes another identification when primary is deleted', function () {
            $employee = Employee::factory()->create();
            $primary = EmployeeIdentification::factory()->create([
                'employee_id' => $employee->id,
                'is_primary' => true,
            ]);
            $other = EmployeeIdentification::factory()->create([
                'employee_id' => $employee->id,
                'is_primary' => false,
                'first_name_en' => 'PromotedName',
            ]);

            $response = $this->deleteJson("/api/v1/employee-identifications/{$primary->id}");

            $response->assertStatus(200);

            // Other should be promoted to primary
            $this->assertDatabaseHas('employee_identifications', [
                'id' => $other->id,
                'is_primary' => true,
            ]);

            // Employee names should be synced from promoted identification
            $this->assertDatabaseHas('employees', [
                'id' => $employee->id,
                'first_name_en' => 'PromotedName',
            ]);
        });
    });

    describe('Authentication & Authorization', function () {
        it('returns 401 for unauthenticated requests', function () {
            $this->app['auth']->forgetGuards();

            $response = $this->getJson('/api/v1/employee-identifications?employee_id=1');

            $response->assertStatus(401);
        });

        it('returns 403 for user without permission', function () {
            $userWithoutPermission = User::factory()->create();
            $this->actingAs($userWithoutPermission);

            $employee = Employee::factory()->create();
            $response = $this->getJson("/api/v1/employee-identifications?employee_id={$employee->id}");

            $response->assertStatus(403);
        });
    });
});
```

### Step 6.2: Create EmployeeIdentification factory

**NEW file:** `database/factories/EmployeeIdentificationFactory.php`

```php
<?php

namespace Database\Factories;

use App\Enums\IdentificationType;
use App\Models\Employee;
use App\Models\EmployeeIdentification;
use Illuminate\Database\Eloquent\Factories\Factory;

class EmployeeIdentificationFactory extends Factory
{
    protected $model = EmployeeIdentification::class;

    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'identification_type' => $this->faker->randomElement(IdentificationType::values()),
            'identification_number' => $this->faker->unique()->numerify('ID-########'),
            'identification_issue_date' => $this->faker->dateTimeBetween('-5 years', '-1 year'),
            'identification_expiry_date' => $this->faker->dateTimeBetween('+1 year', '+5 years'),
            'first_name_en' => $this->faker->firstName(),
            'last_name_en' => $this->faker->lastName(),
            'is_primary' => false,
            'created_by' => 'factory',
        ];
    }

    /**
     * Mark this identification as primary.
     */
    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }
}
```

---

## Phase 7 — Cleanup (Future)

**Only do this AFTER the frontend is updated to use `primary_identification` and `identifications` fields instead of the flat fields.**

### Step 7.1: Create column drop migration

```php
// database/migrations/xxxx_drop_identification_columns_from_employees_table.php

Schema::table('employees', function (Blueprint $table) {
    $table->dropColumn([
        'identification_type',
        'identification_number',
        'identification_issue_date',
        'identification_expiry_date',
    ]);
});
```

### Step 7.2: Remove backward-compatible flat fields from resources

In `EmployeeResource.php` and `EmployeeDetailResource.php`, remove the `identification_type`, `identification_number`, `identification_issue_date`, `identification_expiry_date` flat fields.

---

## Verification Checklist

After each phase, run:

```bash
php artisan test
vendor/bin/pint --dirty
```

### Full verification after all phases:

- [x] `php artisan migrate` — no errors
- [x] `php artisan test` — all tests pass (existing + new) — 273 passed, 1151 assertions
- [x] `vendor/bin/pint --dirty` — code style clean
- [x] `php artisan route:list --path=employee-identifications` — 6 routes registered

### Manual testing:

- [ ] `GET /api/v1/employee-identifications?employee_id=1` — returns identification list
- [ ] `POST /api/v1/employee-identifications` — creates new identification
- [ ] `PUT /api/v1/employee-identifications/1` — updates identification
- [ ] `PATCH /api/v1/employee-identifications/1/set-primary` — sets primary + syncs names
- [ ] `DELETE /api/v1/employee-identifications/1` — deletes (or 422 if only one)
- [ ] `GET /api/v1/employees/1` — detail view includes `identifications` and `primary_identification`
- [ ] `GET /api/v1/employees` — list still shows `identification_type` via backward-compat flat fields
- [ ] Employee export Excel — ID columns populated from `primaryIdentification`
- [ ] Employee import Excel — creates both employee AND identification records

### Invariant checks:

- [ ] Only one `is_primary = true` per employee at any time
- [ ] Changing primary syncs names to `employees` table
- [ ] Activity log captures name changes when primary is switched
- [ ] Deleting a primary auto-promotes the next identification
- [ ] First identification for an employee is auto-set as primary
- [ ] Bulk import uses `DB::table()->insert()` — observer does NOT fire (names already match)

---

## Files Summary

### NEW files (11)

| # | File | Purpose |
|---|------|---------|
| 1 | `database/migrations/2026_03_10_000001_create_employee_identifications_table.php` | Table + backfill |
| 2 | `app/Models/EmployeeIdentification.php` | Model |
| 3 | `app/Observers/EmployeeIdentificationObserver.php` | is_primary name sync |
| 4 | `app/Enums/IdentificationType.php` | String-backed enum |
| 5 | `app/Services/EmployeeIdentificationService.php` | CRUD + setPrimary |
| 6 | `app/Http/Controllers/Api/V1/EmployeeIdentificationController.php` | REST endpoints |
| 7 | `app/Http/Resources/EmployeeIdentificationResource.php` | API response |
| 8 | `app/Http/Requests/StoreEmployeeIdentificationRequest.php` | Create validation |
| 9 | `app/Http/Requests/UpdateEmployeeIdentificationRequest.php` | Update validation |
| 10 | `tests/Feature/Api/EmployeeIdentificationApiTest.php` | API tests |
| 11 | `database/factories/EmployeeIdentificationFactory.php` | Test factory |

### MODIFIED files (14)

| # | File | Change |
|---|------|--------|
| 1 | `app/Providers/AppServiceProvider.php` | Register observer |
| 2 | `app/Models/Employee.php` | Add relationships, remove identification fields/scope/accessor |
| 3 | `app/Services/EmployeeDataService.php` | Extract ID fields in store/fullUpdate/updatePersonalInfo, update sort |
| 4 | `app/Http/Resources/EmployeeResource.php` | Backward-compatible from primaryIdentification |
| 5 | `app/Http/Resources/EmployeeDetailResource.php` | Same + full identifications collection |
| 6 | `app/Exports/EmployeesExport.php` | Read from primaryIdentification |
| 7 | `app/Imports/EmployeesImport.php` | Build $validatedIdentifications, insert after employees |
| 8 | `app/Imports/DevEmployeesImport.php` | Same as EmployeesImport |
| 9 | `app/Http/Requests/StoreEmployeeRequest.php` | Remove identification rules |
| 10 | `app/Http/Requests/UpdateEmployeeRequest.php` | Remove identification rules |
| 11 | `app/Http/Requests/UpdateEmployeePersonalRequest.php` | Remove identification + legacy rules |
| 12 | `app/Http/Requests/Employee/FullUpdateEmployeeRequest.php` | Remove identification rules |
| 13 | `routes/api/employees.php` | Add employee-identifications route group |
| 14 | `database/seeders/LookupSeeder.php` | No change needed (ID types are same strings) |
