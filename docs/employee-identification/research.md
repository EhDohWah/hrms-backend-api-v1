# Employee Identifications — Implementation Research

> **Date:** 2026-03-09
> **Status:** Research complete — ready for implementation planning

---

## Table of Contents

1. [Design Overview](#1-design-overview)
2. [Current State — Identification Fields on employees Table](#2-current-state--identification-fields-on-employees-table)
3. [Current State — Name Fields and the is_primary Sync](#3-current-state--name-fields-and-the-is_primary-sync)
4. [Observer vs Service — Where Sync Logic Lives](#4-observer-vs-service--where-sync-logic-lives)
5. [EmployeeDataService — Exact Methods to Modify](#5-employeedataservice--exact-methods-to-modify)
6. [API Endpoint Design](#6-api-endpoint-design)
7. [setPrimary() — Atomic Operation Breakdown](#7-setprimary--atomic-operation-breakdown)
8. [Race Condition and Locking Strategy](#8-race-condition-and-locking-strategy)
9. [Import Impact — EmployeesImport.php](#9-import-impact--employeesimportphp)
10. [Export Impact — EmployeesExport.php](#10-export-impact--employeesexportphp)
11. [API Resource Changes](#11-api-resource-changes)
12. [Form Request Changes](#12-form-request-changes)
13. [Employee Model Changes](#13-employee-model-changes)
14. [Existing Patterns to Follow](#14-existing-patterns-to-follow)
15. [Files That Never Change](#15-files-that-never-change)
16. [Complete File Inventory](#16-complete-file-inventory)
17. [Implementation Phases](#17-implementation-phases)
18. [The Golden Rule](#18-the-golden-rule)

---

## 1. Design Overview

### What we are building

A new `employee_identifications` table that stores ID documents (passport, national ID, 10-year Thai ID, border pass, etc.). Each record has its own name fields AND identification fields.

### Key design decisions

1. **employees table KEEPS its name fields** — they are the "working name" used everywhere
2. **When `is_primary = true`** on an identification record, the employee's name fields are synced to match
3. **All existing code that reads `employees.first_name_en` continues to work WITHOUT changes**
4. **Only one identification per employee can be `is_primary = true`** at a time

### Columns MOVED from employees to employee_identifications

| Column | Type | Nullable |
|--------|------|----------|
| `identification_type` | varchar(50) | NO (required on identification record) |
| `identification_number` | varchar(50) | NO (required on identification record) |
| `identification_issue_date` | date | YES |
| `identification_expiry_date` | date | YES |

### Columns that STAY on employees

- `first_name_en`, `last_name_en`, `first_name_th`, `last_name_th`, `initial_en`, `initial_th`
- `social_security_number`, `tax_number`, `driver_license_number`

### New columns on employee_identifications

| Column | Type | Nullable | Notes |
|--------|------|----------|-------|
| `id` | bigint | NO | PK |
| `employee_id` | bigint | NO | FK → employees(id) CASCADE DELETE |
| `identification_type` | varchar(50) | NO | 10YearsID, BurmeseID, CI, Borderpass, ThaiID, Passport, Other |
| `identification_number` | varchar(50) | NO | Document number |
| `identification_issue_date` | date | YES | |
| `identification_expiry_date` | date | YES | |
| `first_name_en` | varchar(255) | YES | Name as shown on this document |
| `last_name_en` | varchar(255) | YES | |
| `first_name_th` | varchar(255) | YES | |
| `last_name_th` | varchar(255) | YES | |
| `initial_en` | varchar(10) | YES | |
| `initial_th` | varchar(10) | YES | |
| `is_primary` | boolean | NO | DEFAULT false. Only one per employee. |
| `created_by` | varchar(100) | YES | |
| `updated_by` | varchar(100) | YES | |
| `created_at` | datetime | YES | |
| `updated_at` | datetime | YES | |

---

## 2. Current State — Identification Fields on employees Table

### Employee Model — `app/Models/Employee.php`

**$fillable array (lines 90-93):**
```php
'identification_type',        // line 90
'identification_number',      // line 91
'identification_issue_date',  // line 92
'identification_expiry_date', // line 93
```

**$casts array (lines 128-129):**
```php
'identification_issue_date' => 'date',
'identification_expiry_date' => 'date',
```

**Scope — `scopeByIdType()` (lines 353-360):**
```php
public function scopeByIdType($query, $idTypes)
{
    if (is_string($idTypes)) {
        $idTypes = explode(',', $idTypes);
    }
    return $query->whereIn('identification_type', array_filter($idTypes));
}
```

**Accessor — `getIdTypeAttribute()` (lines 381-384):**
```php
public function getIdTypeAttribute()
{
    return $this->identification_type;
}
```

**OpenAPI Schema properties (lines 32-35):**
```php
new OA\Property(property: 'identification_type', type: 'string', ...),
new OA\Property(property: 'identification_number', type: 'string', ...),
new OA\Property(property: 'identification_issue_date', type: 'string', format: 'date', ...),
new OA\Property(property: 'identification_expiry_date', type: 'string', format: 'date', ...),
```

### Migrations

**Initial (2025_02_12_131510_create_employees_table.php, lines 30-31):**
- `identification_type` — string(50), nullable
- `identification_number` — string(50), nullable

**Added later (2026_01_27_143217_add_identification_dates_to_employees_table.php, lines 15-16):**
- `identification_issue_date` — date, nullable
- `identification_expiry_date` — date, nullable

### Files that reference these 4 fields (73 occurrences across 16 files)

| Category | File | Key Lines |
|----------|------|-----------|
| **Model** | `app/Models/Employee.php` | 32-35, 90-93, 128-129, 353-360, 381-384 |
| **Service** | `app/Services/EmployeeDataService.php` | 57-58, 77-78, 413-421, 615-616 |
| **Resource** | `app/Http/Resources/EmployeeResource.php` | 28-31 |
| **Resource** | `app/Http/Resources/EmployeeDetailResource.php` | 37-40 |
| **Request** | `app/Http/Requests/StoreEmployeeRequest.php` | 40-43 |
| **Request** | `app/Http/Requests/UpdateEmployeeRequest.php` | 40-44 |
| **Request** | `app/Http/Requests/UpdateEmployeePersonalRequest.php` | 31-37 |
| **Request** | `app/Http/Requests/Employee/FullUpdateEmployeeRequest.php` | 35-36 |
| **Request** | `app/Http/Requests/FilterEmployeeRequest.php` | 28, 32 |
| **Request** | `app/Http/Requests/Employee/ListEmployeesRequest.php` | 24, 26 |
| **Export** | `app/Exports/EmployeesExport.php` | 27-35, 79-82, 123-126, 151-154, 203-204 |
| **Import** | `app/Imports/EmployeesImport.php` | 61-95, 229-246, 335-381, 614-617, 914-968, 1219-1256 |
| **Import** | `app/Imports/DevEmployeesImport.php` | 108-121, 249-250, 395-396 |
| **Migration** | `database/migrations/2025_02_12_...` | 30-31 |
| **Migration** | `database/migrations/2026_01_27_...` | 15-16 |
| **Seeder** | `database/seeders/LookupSeeder.php` | 90-96 |

### What does NOT reference these fields

- **Zero blade templates** — payslips, letters, reports never display identification_type or identification_number
- **Zero test files** — no existing tests reference identification fields
- **Zero other services** — only EmployeeDataService touches these fields

---

## 3. Current State — Name Fields and the is_primary Sync

### Where employees.first_name_en is WRITTEN (all write paths)

#### CREATE operations

| File | Lines | Method | How |
|------|-------|--------|-----|
| `app/Services/EmployeeDataService.php` | 125 | `store()` | `Employee::create($validated)` |
| `app/Imports/EmployeesImport.php` | 371-387, 496 | Bulk import | `Employee::insert($validatedEmployees)` |
| `app/Imports/DevEmployeesImport.php` | 236-277, 284 | Dev import | `Employee::insert($employeeBatch)` |
| `database/factories/EmployeeFactory.php` | 23-26 | Factory | `$this->faker->firstName()` |
| `database/seeders/EmployeeSeeder.php` | 26-87 | Seeder | `Employee::create([...])` |

#### UPDATE operations

| File | Lines | Method | How |
|------|-------|--------|-----|
| `app/Services/EmployeeDataService.php` | 138-140 | `fullUpdate()` | `$employee->update($validated)` |
| `app/Services/EmployeeDataService.php` | 395 | `updateBasicInfo()` | `$employee->update($validated)` |
| `app/Services/EmployeeDataService.php` | 426 | `updatePersonalInfo()` | `$employee->update($data)` |

**Key finding:** No code does direct `$employee->first_name_en = 'value'` assignments. All writes are via mass-assignment (`create()` or `update()`). The `is_primary` sync will be the ONLY place that does targeted name-field writes — making it easy to distinguish in activity logs.

### Where employees.first_name_en is READ (overview)

- **76+ file references** across all services, resources, exports, blade templates
- **Dominant eager-load pattern (30+ locations):** `'employee:id,staff_id,first_name_en,last_name_en'`
- **Search/filter (8 services):** `LIKE first_name_en`, `CONCAT(first_name_en, ' ', last_name_en)`
- **All payslips and reports:** English names only
- **Thai names only used in:** `EmployeeDetailResource` and `EmployeesExport`

**None of these read paths change.** The sync ensures `employees.first_name_en` always reflects the primary ID name.

---

## 4. Observer vs Service — Where Sync Logic Lives

### Recommendation: Observer on EmployeeIdentification

**Use an `EmployeeIdentificationObserver`** that fires on `created()` and `updated()`.

### Why an observer (not explicit service call)

1. **Automatic — can't be bypassed.** Whether created via API, seeder, or tinker, sync always fires.
2. **Follows established pattern.** Three observers already exist:
   - `EmployeeObserver` — auto-creates LeaveBalance on employee creation
   - `EmploymentObserver` — validates dates, updates allocations on change
   - `EmployeeFundingAllocationObserver` — records status changes in history
3. **Single responsibility.** Observer handles sync. Service handles CRUD + locking.

### Exception: Bulk import bypasses Eloquent events

`Employee::insert()` (used in EmployeesImport) bypasses model events. During import:
- Employee is created with name fields already set in the same row
- Identification record is inserted via `DB::table()->insert()` (also bypasses events)
- **No sync needed** — both employee and identification have the same names from the same Excel row
- `is_primary = true` is set during insert

### Existing observer registration pattern — `app/Providers/AppServiceProvider.php:52-59`

```php
Employee::observe(EmployeeObserver::class);
JobOffer::observe(JobOfferObserver::class);
Employment::observe(EmploymentObserver::class);
EmployeeFundingAllocation::observe(EmployeeFundingAllocationObserver::class);
```

Add: `EmployeeIdentification::observe(EmployeeIdentificationObserver::class);`

### Observer implementation sketch

```php
class EmployeeIdentificationObserver
{
    public function created(EmployeeIdentification $identification): void
    {
        if ($identification->is_primary) {
            $this->syncPrimaryToEmployee($identification);
        }
    }

    public function updated(EmployeeIdentification $identification): void
    {
        if ($identification->is_primary && $identification->wasChanged('is_primary')) {
            $this->syncPrimaryToEmployee($identification);
        }
    }

    private function syncPrimaryToEmployee(EmployeeIdentification $identification): void
    {
        // Unset other primaries
        EmployeeIdentification::where('employee_id', $identification->employee_id)
            ->where('id', '!=', $identification->id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);

        // Sync non-null name fields to employee
        $nameFields = [
            'first_name_en', 'last_name_en',
            'first_name_th', 'last_name_th',
            'initial_en', 'initial_th',
        ];
        $updates = [];
        foreach ($nameFields as $field) {
            if ($identification->$field !== null) {
                $updates[$field] = $identification->$field;
            }
        }

        if (!empty($updates)) {
            $identification->employee()->update($updates);
        }
    }
}
```

**Important:** The observer's `syncPrimaryToEmployee()` triggers `$employee->update()`, which fires the `LogsActivity` trait. This means name changes from `is_primary` sync are automatically captured in `activity_logs` with old/new values.

---

## 5. EmployeeDataService — Exact Methods to Modify

### 5.1 store() — `app/Services/EmployeeDataService.php:123-131`

```php
public function store(array $validated): Employee
{
    $employee = Employee::create($validated);
    $this->invalidateCache();
    $this->notifyAction('created', $employee);
    return $employee;
}
```

**Change required:**
1. Extract identification fields from `$validated` before `Employee::create()`
2. Create employee first (names come from request, not from identification yet)
3. If identification fields present, create `EmployeeIdentification` record with `is_primary = true`
4. Observer handles syncing names (though on first create, names already match)
5. Wrap in DB transaction

### 5.2 fullUpdate() — `app/Services/EmployeeDataService.php:136-147`

```php
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
```

**Change required:**
1. Extract identification fields from `$validated`
2. Update employee (without identification fields)
3. If identification fields present, delegate to `EmployeeIdentificationService`
4. Add DB transaction

### 5.3 updatePersonalInfo() — `app/Services/EmployeeDataService.php:407-452`

```php
public function updatePersonalInfo(Employee $employee, array $data, ?array $languages = null): Employee
{
    DB::beginTransaction();
    try {
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
        unset($data['employee_identification'], $data['languages']);
        $employee->update($data);
        // ... languages ...
        DB::commit();
    } catch (\Exception $e) {
        DB::rollBack();
        throw $e;
    }
    // ...
}
```

**Change required:**
1. Replace entire identification handling block (lines 413-421) with `EmployeeIdentificationService` call
2. Remove legacy `employee_identification` nested format mapping
3. Keep the same DB transaction — identification creation happens inside it

### 5.4 list() filter — `app/Services/EmployeeDataService.php:57-58`

```php
if (! empty($validated['filter_identification_type'])) {
    $query->byIdType($validated['filter_identification_type']);
}
```

**Change required:** Replace `byIdType()` scope with `whereHas('identifications', ...)`:
```php
$query->whereHas('identifications', function ($q) use ($validated) {
    $q->whereIn('identification_type', explode(',', $validated['filter_identification_type']));
});
```

### 5.5 list() sort — `app/Services/EmployeeDataService.php:77-78`

```php
$query->orderBy('employees.identification_type', $sortOrder);
```

**Change required:** JOIN on `employee_identifications` where `is_primary = true`:
```php
$query->leftJoin('employee_identifications', function ($join) {
    $join->on('employees.id', '=', 'employee_identifications.employee_id')
         ->where('employee_identifications.is_primary', true);
})
->orderBy('employee_identifications.identification_type', $sortOrder)
->select('employees.*');
```

---

## 6. API Endpoint Design

### Existing nested resource pattern

ALL employee sub-resources use standalone routes with `employee_id` in request body:

| Resource | Route Prefix | Permissions |
|----------|-------------|-------------|
| Employee Children | `POST /api/v1/employee-children` | `employees.create` |
| Employee Beneficiaries | `POST /api/v1/employee-beneficiaries` | `employees.create` |
| Employee Education | `POST /api/v1/employee-education` | `employees.create` |
| Employee Languages | `POST /api/v1/employee-language` | `employees.create` |
| Employee Training | `POST /api/v1/employee-trainings` | `employee_training.create` |

**None are nested under `/employees/{id}/`.** All receive `employee_id` in JSON body.

### New routes for employee_identifications

```
GET    /api/v1/employee-identifications                    → index
GET    /api/v1/employee-identifications/{id}               → show
POST   /api/v1/employee-identifications                    → store
PUT    /api/v1/employee-identifications/{id}               → update
DELETE /api/v1/employee-identifications/{id}               → destroy
PATCH  /api/v1/employee-identifications/{id}/set-primary   → setPrimary
```

**Route file:** `routes/api/employees.php` — add new group after existing nested resources.

**Permissions:** Use `employees.*` (read, create, update, delete) — consistent with all other employee sub-resources.

**`setPrimary` as dedicated endpoint** because:
- It has side effects (syncs names, unsets other primaries)
- Should NOT be a side effect of a regular `PUT` update
- Maps cleanly to a UI button "Set as Primary"

---

## 7. setPrimary() — Atomic Operation Breakdown

When HR marks an identification record as `is_primary = true`, the following must happen atomically:

### A) Unset other primaries on this employee

```php
EmployeeIdentification::where('employee_id', $identification->employee_id)
    ->where('id', '!=', $identification->id)
    ->where('is_primary', true)
    ->update(['is_primary' => false]);
```

### B) Set this record as primary

```php
$identification->update(['is_primary' => true]);
```

### C) Sync name fields to employee

```php
$nameFields = ['first_name_en', 'last_name_en', 'first_name_th',
               'last_name_th', 'initial_en', 'initial_th'];
$updates = [];
foreach ($nameFields as $field) {
    if ($identification->$field !== null) {
        $updates[$field] = $identification->$field;
    }
}
if (!empty($updates)) {
    $identification->employee()->update($updates);
}
```

### D) Activity logs — automatically captured

The `LogsActivity` trait on Employee (via `app/Traits/LogsActivity.php`) listens to the `updated` event and captures old/new values for all non-excluded fields.

**Name fields ARE logged** — they are NOT in the excluded list. The excluded list (lines 71-96) contains: passwords, tokens, SSN, bank_account_number, timestamps — but NOT names.

When `setPrimary()` triggers `$employee->update([name fields])`, activity log records:
```json
{
  "old": { "first_name_en": "Somchai", "last_name_en": "Jaidee" },
  "new": { "first_name_en": "Somchai", "last_name_en": "Smith" },
  "changes": ["first_name_en", "last_name_en"]
}
```

### E) Cache — minimal impact

`EmployeeDataService.php:574-577`:
```php
private function invalidateCache(): void
{
    Cache::forget('employee_statistics');
}
```

Only `employee_statistics` is cached. No employee names are cached. The `setPrimary()` method should call `Cache::forget('employee_statistics')` for safety.

---

## 8. Race Condition and Locking Strategy

### The problem

Two HR users simultaneously click "Set as Primary" on different identification records for the same employee:

1. User A reads: ID-1 is primary
2. User B reads: ID-1 is primary
3. User A: Set ID-2 primary → unset ID-1 → sync names from ID-2
4. User B: Set ID-3 primary → unset ID-1 (already false, misses ID-2) → sync names from ID-3
5. **Result:** Both ID-2 AND ID-3 have `is_primary = true` — BROKEN

### The solution — pessimistic locking (SELECT FOR UPDATE)

Existing transaction pattern from `updatePersonalInfo()` (line 407-452):

```php
DB::beginTransaction();
try {
    // ... operations ...
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}
```

For `setPrimary()`, add `lockForUpdate()`:

```php
DB::beginTransaction();
try {
    // Lock the target identification row
    $identification = EmployeeIdentification::where('id', $id)
        ->lockForUpdate()
        ->firstOrFail();

    // Lock and unset ALL other primaries for this employee
    EmployeeIdentification::where('employee_id', $identification->employee_id)
        ->where('id', '!=', $identification->id)
        ->lockForUpdate()
        ->update(['is_primary' => false]);

    // Set this one as primary (fires observer → syncs names)
    $identification->update(['is_primary' => true]);

    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}
```

The `lockForUpdate()` ensures the second request blocks until the first completes.

### Where the lock happens

The lock lives in `EmployeeIdentificationService::setPrimary()`, NOT in the observer. The observer handles the name sync. The service handles the locking + primary flag toggle.

**Important architectural split:**
- **Service:** Manages the `is_primary` flag with locking (ensures only one primary)
- **Observer:** Syncs names to employee when `is_primary` becomes true (fires automatically after service updates the flag)

---

## 9. Import Impact — EmployeesImport.php

### Current flow — lines 371-381 (mapping) and 496-522 (insertion)

**Validation pass (lines 371-381):**
```php
$validatedEmployees[] = [
    'staff_id' => $staffIdValidation['staffId'],
    'first_name_en' => $firstNameValidation['firstName'],
    // ... other fields ...
    'identification_type' => $identificationTypeValidation['identificationType'],   // line 378
    'identification_number' => $this->trimOrNull($row['id_number'] ?? null),        // line 379
    'identification_issue_date' => $this->parseDate($row['id_issue_date'] ?? null), // line 380
    'identification_expiry_date' => $this->parseDate($row['id_expiry_date'] ?? null), // line 381
];
```

**Insertion pass (lines 496-522):**
```php
Employee::insert($validatedEmployees);

$staffIds = array_column($validatedEmployees, 'staff_id');
$employeeIdMap = Employee::whereIn('staff_id', $staffIds)->pluck('id', 'staff_id')->toArray();

// Link beneficiaries
foreach ($validatedBeneficiaries as $bene) {
    $staffId = $bene['_staff_id'];
    unset($bene['_staff_id']);
    if (isset($employeeIdMap[$staffId])) {
        $bene['employee_id'] = $employeeIdMap[$staffId];
        $beneficiariesToInsert[] = $bene;
    }
}
DB::table('employee_beneficiaries')->insert($beneficiariesToInsert);
```

### New flow — follow the beneficiary pattern

**During validation pass:**
1. Remove identification fields from `$validatedEmployees` array (keep names — employee still stores names)
2. Build `$validatedIdentifications[]` with `_staff_id` as temporary linking key:

```php
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

**During insertion pass:**
1. Insert employees (without identification fields)
2. Fetch `$employeeIdMap`
3. Link identifications to employees and bulk insert

```php
if (! empty($validatedIdentifications)) {
    $identificationsToInsert = [];
    foreach ($validatedIdentifications as $ident) {
        $staffId = $ident['_staff_id'];
        unset($ident['_staff_id']);
        if (isset($employeeIdMap[$staffId])) {
            $ident['employee_id'] = $employeeIdMap[$staffId];
            $identificationsToInsert[] = $ident;
        }
    }
    if (! empty($identificationsToInsert)) {
        DB::table('employee_identifications')->insert($identificationsToInsert);
    }
}
```

**No observer fires** during bulk insert (`DB::table()->insert()` bypasses Eloquent events). This is correct because:
- Names are already on the employee from the same Excel row
- No sync needed — both records have identical names

### Constants that stay (lines 61-95)

The identification type constants (`VALID_IDENTIFICATION_TYPES_DISPLAY`, `VALID_IDENTIFICATION_TYPES_DATABASE`, `IDENTIFICATION_TYPE_MAPPING`) remain in EmployeesImport — they're still needed for Excel display↔database value conversion.

### DevEmployeesImport.php — same approach

Lines 108-121, 249-250, 395-396 follow the same pattern as EmployeesImport.

---

## 10. Export Impact — EmployeesExport.php

### Current query — lines 47-60

```php
public function query()
{
    $query = Employee::query();
    // ... filters ...
    return $query->with(['employeeBeneficiaries', 'employment:id,employee_id,organization']);
}
```

### Current map — lines 123-154

```php
$identificationTypeDisplay = null;
if ($employee->identification_type) {
    $identificationTypeDisplay = self::IDENTIFICATION_TYPE_REVERSE_MAPPING[$employee->identification_type]
        ?? $employee->identification_type;
}
return [
    // ...
    $identificationTypeDisplay,          // line 151
    $employee->identification_number,    // line 152
    $employee->identification_issue_date,  // line 153
    $employee->identification_expiry_date, // line 154
    // ...
];
```

### New approach — load primaryIdentification

**Add to query():**
```php
return $query->with([
    'employeeBeneficiaries',
    'employment:id,employee_id,organization',
    'primaryIdentification',  // NEW
]);
```

**Change map():**
```php
$primary = $employee->primaryIdentification;
$identificationTypeDisplay = null;
if ($primary?->identification_type) {
    $identificationTypeDisplay = self::IDENTIFICATION_TYPE_REVERSE_MAPPING[$primary->identification_type]
        ?? $primary->identification_type;
}
return [
    // ...
    $identificationTypeDisplay,
    $primary?->identification_number,
    $primary?->identification_issue_date,
    $primary?->identification_expiry_date,
    // ...
];
```

This keeps one row per employee (primary ID only). The Excel format doesn't change.

---

## 11. API Resource Changes

### EmployeeResource.php — lines 28-31 (current)

```php
'identification_type' => $this->identification_type,
'identification_number' => $this->identification_number,
'identification_issue_date' => $this->identification_issue_date,
'identification_expiry_date' => $this->identification_expiry_date,
```

### New approach — backward-compatible during transition

```php
// Backward-compatible flat fields (from primary identification)
'identification_type' => $this->primaryIdentification?->identification_type,
'identification_number' => $this->primaryIdentification?->identification_number,
'identification_issue_date' => $this->primaryIdentification?->identification_issue_date,
'identification_expiry_date' => $this->primaryIdentification?->identification_expiry_date,

// New structured field
'primary_identification' => new EmployeeIdentificationResource(
    $this->whenLoaded('primaryIdentification')
),
```

### EmployeeDetailResource.php — same + full collection

```php
// Same backward-compat flat fields as above, plus:
'identifications' => EmployeeIdentificationResource::collection(
    $this->whenLoaded('identifications')
),
```

### Frontend impact

Frontend currently reads flat fields directly:

`hrms-frontend-dev/src/views/pages/hrm/employees/employees-list.vue:845-846`:
```javascript
id_type: emp.identification_type || 'N/A',
id_number: emp.identification_number || 'N/A',
```

**The backward-compat flat fields mean the frontend works unchanged during transition.** Once the frontend is updated to use `emp.primary_identification.identification_type`, the flat fields can be removed.

### No backend code parses resource output

Resources are only used for HTTP responses. Exports and imports use model properties directly.

---

## 12. Form Request Changes

### Current validation — StoreEmployeeRequest.php:40-43

```php
'identification_type' => ['nullable', 'string', 'in:10YearsID,BurmeseID,CI,Borderpass,ThaiID,Passport,Other'],
'identification_number' => ['nullable', 'string', 'max:50', 'required_with:identification_type'],
'identification_issue_date' => ['nullable', 'date', 'before_or_equal:today'],
'identification_expiry_date' => ['nullable', 'date', 'after:identification_issue_date'],
```

Same rules in: `UpdateEmployeeRequest.php:40-44`, `FullUpdateEmployeeRequest.php:35-36`

### Legacy nested format — UpdateEmployeePersonalRequest.php:31-37

```php
'identification_type' => [...],
'identification_number' => [...],
'identification_issue_date' => [...],
'identification_expiry_date' => [...],
'employee_identification' => ['nullable', 'array'],
'employee_identification.id_type' => ['nullable', 'string', 'max:30'],
'employee_identification.document_number' => ['nullable', 'string', 'max:50'],
```

### Filter/sort — FilterEmployeeRequest.php:28,32 and ListEmployeesRequest.php:24,26

```php
'filter_identification_type' => ['string', 'nullable'],  // filter
'sort_by' => [..., 'identification_type', ...],           // sort option
```

### What changes

1. **StoreEmployeeRequest, UpdateEmployeeRequest, FullUpdateEmployeeRequest:** Remove identification rules. These fields now go through `StoreEmployeeIdentificationRequest`.
2. **UpdateEmployeePersonalRequest:** Remove identification rules AND legacy nested format.
3. **FilterEmployeeRequest, ListEmployeesRequest:** Keep `filter_identification_type` param (implementation changes in service, not in validation).

### New form requests

**StoreEmployeeIdentificationRequest:**
```php
'employee_id' => ['required', 'integer', 'exists:employees,id'],
'identification_type' => ['required', 'string', 'in:10YearsID,BurmeseID,...'],
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
```

**UpdateEmployeeIdentificationRequest:** Same but `employee_id` not required (from route param).

---

## 13. Employee Model Changes

### Remove from $fillable (lines 90-93)

```php
// REMOVE these 4 lines:
'identification_type',
'identification_number',
'identification_issue_date',
'identification_expiry_date',
```

### Remove from $casts (lines 128-129)

```php
// REMOVE these 2 lines:
'identification_issue_date' => 'date',
'identification_expiry_date' => 'date',
```

### Remove accessor (lines 381-384)

```php
// REMOVE:
public function getIdTypeAttribute() { ... }
```

### Rewrite scope (lines 353-360)

```php
// CHANGE FROM:
public function scopeByIdType($query, $idTypes) {
    return $query->whereIn('identification_type', array_filter($idTypes));
}

// CHANGE TO:
public function scopeByIdType($query, $idTypes) {
    if (is_string($idTypes)) {
        $idTypes = explode(',', $idTypes);
    }
    return $query->whereHas('identifications', function ($q) use ($idTypes) {
        $q->whereIn('identification_type', array_filter($idTypes));
    });
}
```

### Add new relationships

```php
public function identifications(): HasMany
{
    return $this->hasMany(EmployeeIdentification::class);
}

public function primaryIdentification(): HasOne
{
    return $this->hasOne(EmployeeIdentification::class)->where('is_primary', true);
}
```

### Update OpenAPI schema (lines 32-35)

Remove 4 identification properties. Add `identifications` array property.

---

## 14. Existing Patterns to Follow

### Model pattern — EmployeeBeneficiary.php

```php
class EmployeeBeneficiary extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'beneficiary_name',
        'beneficiary_relationship',
        'phone_number',
        'created_by',
        'updated_by',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }
}
```

### Controller pattern — EmployeeChildrenController.php

- Extends `BaseApiController`
- Constructor DI with `readonly` service property
- Standard CRUD: `index()`, `show()`, `store()`, `update()`, `destroy()`
- Uses FormRequest validation
- Returns API Resources with `->additional(['success' => true, ...])`

### Service pattern — EmployeeChildService.php

- `listAll()` with eager loading
- `create()` sets `created_by` from user
- `update()` sets `updated_by`
- `delete()` loads employee before deletion for notification
- Private `notifyEmployeeAction()` and `broadcastNotification()` methods

### Observer pattern — EmployeeFundingAllocationObserver.php

- `created()` — records creation in history
- `updated()` — checks `getChanges()`, tracks specific fields via `wasChanged()`
- Records audit trail via related History model

### Enum pattern — FundingAllocationStatus.php

```php
enum FundingAllocationStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Closed => 'Closed',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
```

### Exception pattern — ResignationNotPendingException.php

```php
class ResignationNotPendingException extends BusinessRuleException
{
    public function __construct()
    {
        parent::__construct('Only pending resignations can be acknowledged or rejected', 400);
    }
}
```

### Migration pattern — employee_children table

```php
Schema::create('employee_children', function (Blueprint $table) {
    $table->id();
    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
    // ... columns ...
    $table->string('created_by', 100)->nullable();
    $table->string('updated_by', 100)->nullable();
    $table->timestamps();
    $table->index('employee_id', 'idx_emp_children_employee');
});
```

---

## 15. Files That Never Change

```
All 30+ eager-load patterns             ('employee:id,staff_id,first_name_en,last_name_en')
All payslip blade templates              (bhf-payslip, smru-payslip, bulk-payslip)
All report blade templates               (leave_request_report_pdf, interview_report_pdf, etc.)
All letter blade templates               (jobOffer, recommendationLetter)
All search queries on name fields        (LIKE first_name_en, CONCAT(...))
All services reading employee names      (PayrollService, LeaveRequestService, etc.)
LogsActivity trait                       (auto-captures name changes when sync happens)
Frontend (during transition)             (backward-compat flat fields in resources)
```

---

## 16. Complete File Inventory

### NEW files to create (9 files)

| # | File | Purpose |
|---|------|---------|
| 1 | `database/migrations/xxxx_create_employee_identifications_table.php` | Schema + backfill data |
| 2 | `app/Models/EmployeeIdentification.php` | Model: belongsTo Employee, $fillable, $casts, LogsActivity |
| 3 | `app/Observers/EmployeeIdentificationObserver.php` | created/updated: is_primary sync |
| 4 | `app/Services/EmployeeIdentificationService.php` | CRUD + setPrimary with locking |
| 5 | `app/Http/Controllers/Api/V1/EmployeeIdentificationController.php` | REST endpoints |
| 6 | `app/Http/Resources/EmployeeIdentificationResource.php` | API response |
| 7 | `app/Http/Requests/StoreEmployeeIdentificationRequest.php` | Create validation |
| 8 | `app/Http/Requests/UpdateEmployeeIdentificationRequest.php` | Update validation |
| 9 | `app/Enums/IdentificationType.php` | String-backed enum (optional — can keep string validation) |

### EXISTING files to modify (14 files, 28 changes)

| # | File | Method/Section | Change |
|---|------|---------------|--------|
| 1 | `app/Models/Employee.php` | $fillable (90-93) | Remove 4 identification fields |
| 2 | `app/Models/Employee.php` | $casts (128-129) | Remove 2 date casts |
| 3 | `app/Models/Employee.php` | scopeByIdType (353-360) | Rewrite to whereHas |
| 4 | `app/Models/Employee.php` | getIdTypeAttribute (381-384) | Remove |
| 5 | `app/Models/Employee.php` | OpenAPI schema (32-35) | Update properties |
| 6 | `app/Models/Employee.php` | New | Add identifications() + primaryIdentification() |
| 7 | `app/Providers/AppServiceProvider.php` | boot() (~59) | Register observer |
| 8 | `app/Services/EmployeeDataService.php` | store() (125) | Extract ID fields, create identification |
| 9 | `app/Services/EmployeeDataService.php` | fullUpdate() (138) | Extract ID fields |
| 10 | `app/Services/EmployeeDataService.php` | updatePersonalInfo() (413-421) | Replace legacy mapping |
| 11 | `app/Services/EmployeeDataService.php` | list() filter (57-58) | whereHas |
| 12 | `app/Services/EmployeeDataService.php` | list() sort (77-78) | JOIN |
| 13 | `app/Http/Resources/EmployeeResource.php` | lines 28-31 | primaryIdentification + backward-compat |
| 14 | `app/Http/Resources/EmployeeDetailResource.php` | lines 37-40 | Same + full collection |
| 15 | `app/Http/Requests/StoreEmployeeRequest.php` | lines 40-43 | Remove identification rules |
| 16 | `app/Http/Requests/UpdateEmployeeRequest.php` | lines 40-44 | Remove identification rules |
| 17 | `app/Http/Requests/UpdateEmployeePersonalRequest.php` | lines 31-37 | Remove identification + legacy |
| 18 | `app/Http/Requests/Employee/FullUpdateEmployeeRequest.php` | lines 35-36 | Remove identification rules |
| 19 | `app/Exports/EmployeesExport.php` | query() (55) | Add primaryIdentification eager load |
| 20 | `app/Exports/EmployeesExport.php` | map() (123-154) | Read from primaryIdentification |
| 21 | `app/Imports/EmployeesImport.php` | lines 378-381 | Extract to $validatedIdentifications |
| 22 | `app/Imports/EmployeesImport.php` | lines 496-522 | Insert identifications after employees |
| 23 | `app/Imports/DevEmployeesImport.php` | lines 249-250 | Same approach |
| 24 | `routes/api/employees.php` | New group | Add employee-identifications routes |

### Cleanup phase (LAST — after frontend updated)

| # | File | Change |
|---|------|--------|
| 25 | New migration | Drop 4 old columns from employees table |
| 26 | `EmployeeResource.php` | Remove backward-compat flat fields |
| 27 | `EmployeeDetailResource.php` | Remove backward-compat flat fields |

---

## 17. Implementation Phases

### Phase 1 — Foundation (No breaking changes)

```
1. Create migration (new table only, old columns untouched)
2. Create EmployeeIdentification model
3. Create EmployeeIdentificationObserver
4. Register observer in AppServiceProvider
5. Add identifications() + primaryIdentification() to Employee model
6. Backfill existing data: employees → employee_identifications (is_primary = true)
```

### Phase 2 — New Feature Files

```
7.  Create EmployeeIdentificationService (with setPrimary + locking)
8.  Create EmployeeIdentificationController
9.  Create EmployeeIdentificationResource
10. Create StoreEmployeeIdentificationRequest
11. Create UpdateEmployeeIdentificationRequest
12. Add routes to routes/api/employees.php
```

### Phase 3 — Update Reads (Safe, additive)

```
13. EmployeeResource — add identifications + keep backward-compat flat fields
14. EmployeeDetailResource — same + full collection
15. EmployeesExport — add primaryIdentification to query, update map()
```

### Phase 4 — Update Writes

```
16. Employee model — remove from fillable/casts, rewrite scope, remove accessor
17. EmployeeDataService store() — extract ID fields, create identification after
18. EmployeeDataService fullUpdate() — same approach
19. EmployeeDataService updatePersonalInfo() — replace legacy mapping
20. EmployeeDataService list() filter — whereHas('identifications',...)
21. EmployeeDataService list() sort — JOIN on employee_identifications
22. All 4 Form Requests — remove flat identification rules
```

### Phase 5 — Update Imports

```
23. EmployeesImport — extract ID fields, insert after employee (beneficiary pattern)
24. DevEmployeesImport — same approach
```

### Phase 6 — Cleanup (LAST — point of no return)

```
25. Drop old columns from employees table
26. Remove backward-compat flat fields from Resources (after frontend updated)
```

---

## 18. The Golden Rule

```
employees.first_name_en     = "What name does the system use right now?"
                              → Source of truth for ALL existing code
                              → Updated ONLY by is_primary sync

employee_identifications    = "What IDs does this person have?"
                              → is_primary = true drives the sync
                              → Never directly read by existing code
```

**Design invariant:** If you delete all `employee_identifications` rows, every existing feature (payslips, search, reports, leave requests, payroll) continues to work using the names already on the `employees` table. The identification table is purely additive.
