# Recycle Bin Implementation — Full Analysis Report

## 1. Model Classification Table

### SoftDeletes + Prunable (has child relationships)

| Model | Table | Children | Current Delete Mechanism | Already Has SoftDeletes? |
|-------|-------|----------|--------------------------|--------------------------|
| Employee | employees | 17 child tables | SafeDeleteService | NO |
| Grant | grants | grant_items, org_hub_funds, inter_org_advances | SafeDeleteService | NO |
| Department | departments | positions, section_departments | SafeDeleteService | NO |

### KeepsDeletedModels (flat, no children) — Keep as-is

| Model | Table | Children | Notes |
|-------|-------|----------|-------|
| Interview | interviews | None | Already working |
| JobOffer | job_offers | None | Already working |

### Already SoftDeleted — No changes needed

| Model | Table | Notes |
|-------|-------|-------|
| PersonnelAction | personnel_actions | Already has SoftDeletes |
| Resignation | resignations | Already has SoftDeletes |
| Site | sites | Already has SoftDeletes |
| SectionDepartment | section_departments | Already has SoftDeletes |
| Module | modules | Already has SoftDeletes |
| BenefitSetting | benefit_settings | Already has SoftDeletes |

### No Recycle Bin Needed — Direct hard delete is fine

| Model | Table | Reason |
|-------|-------|--------|
| Employment | employments | Child of Employee (CASCADE FK), delete via parent |
| EmploymentHistory | employment_histories | Audit trail, rarely deleted directly |
| EmployeeFundingAllocation | employee_funding_allocations | Child of Employee, managed by services |
| EmployeeFundingAllocationHistory | employee_funding_allocation_history | Audit trail, never deleted |
| EmployeeBeneficiary | employee_beneficiaries | Child of Employee (CASCADE FK) |
| EmployeeChild | employee_children | Child of Employee (CASCADE FK) |
| EmployeeEducation | employee_education | Child of Employee (CASCADE FK) |
| EmployeeLanguage | employee_languages | Child of Employee (CASCADE FK) |
| EmployeeTraining | employee_trainings | Child of Employee (CASCADE FK) |
| EmployeeParent | employee_parents | Child of Employee |
| GrantItem | grant_items | Child of Grant (CASCADE FK) |
| Position | positions | Child of Department (CASCADE FK) |
| LeaveRequest | leave_requests | Child of Employee |
| LeaveRequestItem | leave_request_items | Child of LeaveRequest (CASCADE FK) |
| LeaveBalance | leave_balances | Child of Employee |
| LeaveType | leave_types | Config/lookup table |
| TravelRequest | travel_requests | Child of Employee (CASCADE FK) |
| ProbationRecord | probation_records | Child of Employment (CASCADE FK) |
| HolidayCompensationRecord | holiday_compensation_records | Child of Employee |
| Holiday | holidays | Config table |
| Training | trainings | Config table |
| Payroll | payrolls | Financial record — should NEVER be deleted |
| PayrollGrantAllocation | payroll_grant_allocations | Child of Payroll (CASCADE FK) |
| InterOrganizationAdvance | inter_organization_advances | Financial record |
| OrganizationHubFund | organization_hub_funds | Config table |

### Not Deletable — Should never be deleted

| Model | Table | Reason |
|-------|-------|--------|
| ActivityLog | activity_logs | Audit trail |
| AllocationChangeLog | allocation_change_logs | Audit trail |
| DeletedModel | deleted_models | Recycle bin storage |
| DeletionManifest | deletion_manifests | Recycle bin metadata (to be removed) |
| SpatieDeletedModel | — | Spatie override (to be removed) |

### System/Config Tables — Standard delete, no bin

| Model | Table |
|-------|-------|
| User | users |
| TaxBracket | tax_brackets |
| TaxSetting | tax_settings |
| TaxCalculationLog | tax_calculation_logs |
| Lookup | lookups |
| DashboardWidget | dashboard_widgets |
| UserDashboardWidget | user_dashboard_widgets |
| BulkPayrollBatch | bulk_payroll_batches |

---

## 2. Relationship Audit — belongsTo needing withTrashed()

### belongsTo(Employee::class) — 17 relationships

| File | Method | Needs withTrashed? | Reason |
|------|--------|-------------------|--------|
| Employment.php:380 | employee() | YES | Employment must always reference its employee |
| EmploymentHistory.php:76 | employee() | YES | History must show employee name |
| EmployeeFundingAllocation.php:58 | employee() | YES | Allocation records are financial data |
| EmployeeFundingAllocationHistory.php:76 | employee() | YES | Audit trail |
| EmployeeBeneficiary.php:42 | employee() | YES | Personal data linked to employee |
| EmployeeChild.php:37 | employee() | YES | Personal data |
| EmployeeEducation.php:48 | employee() | YES | Personal data |
| EmployeeLanguage.php:35 | employee() | YES | Personal data |
| EmployeeParent.php:77 | employee() | YES | Personal data |
| EmployeeTraining.php:44 | employee() | YES | Training records |
| LeaveRequest.php:83 | employee() | YES | Leave history |
| LeaveBalance.php:55 | employee() | YES | Leave balances |
| ProbationRecord.php:90 | employee() | YES | Probation history |
| Resignation.php:100 | employee() | YES | Resignation is linked to employee |
| TravelRequest.php:111 | employee() | YES | Travel history |
| HolidayCompensationRecord.php:71 | employee() | YES | Compensation records |
| AllocationChangeLog.php:107 | employee() | YES | Audit trail |

### belongsTo(Grant::class) — 3 relationships

| File | Method | Needs withTrashed? | Reason |
|------|--------|-------------------|--------|
| GrantItem.php:64 | grant() | YES | Items must always reference their grant |
| InterOrganizationAdvance.php:50 | viaGrant() | YES | Financial record |
| OrganizationHubFund.php:49 | hubGrant() | YES | Fund reference |

### belongsTo(Department::class) — 8 relationships

| File | Method | Needs withTrashed? | Reason |
|------|--------|-------------------|--------|
| Employment.php:433 | department() | YES | Employment references department |
| EmploymentHistory.php:81 | department() | YES | History must show department |
| PersonnelAction.php:106 | currentDepartment() | YES | Action references department |
| PersonnelAction.php:127 | newDepartment() | YES | Action references department |
| Position.php:69 | department() | YES | Position belongs to department |
| Resignation.php:105 | department() | YES | Resignation references department |
| SectionDepartment.php:53 | department() | YES | Section belongs to department |
| TravelRequest.php:116 | department() | YES | Travel references department |

**Total: 28 belongsTo relationships need withTrashed()**

---

## 3. Query Audit — Queries needing updates

### Joins in Payroll.php (bypass SoftDeletes scope)

| File | Line | Query | Fix |
|------|------|-------|-----|
| Payroll.php | 232-234 | `join('employments')` + `join('employees')` for org sort | Add `AND employees.deleted_at IS NULL` or use `leftJoin` |
| Payroll.php | 238-240 | `join('employments')` + `join('departments')` for dept sort | Add `AND departments.deleted_at IS NULL` or use `leftJoin` |
| Payroll.php | 243-246 | `join('employees')` for staff_id sort | Same fix |
| Payroll.php | 253-256 | `join('employees')` for name sort | Same fix |

### DB::table() raw queries (bypass SoftDeletes scope)

| File | Line | Query | Fix |
|------|------|-------|-----|
| DepartmentSeeder.php | 19, 25, 49 | `DB::table('departments')` | Seeder only — no fix needed |
| SectionDepartmentSeeder.php | 23, 64 | `DB::table('departments')` | Seeder only — no fix needed |
| PositionSeeder.php | 28 | `DB::table('departments')` | Seeder only — no fix needed |

### Eager loading calls

| File | Call | Fix |
|------|------|-----|
| EmployeeBeneficiaryController.php:42 | `with('employee')` | If belongsTo has withTrashed(), no change needed |
| EmployeeBeneficiaryController.php:166 | `with('employee')` | Same |
| PayrollController.php:1459 | `with('employee')` | Same |
| PositionController.php:39 | `with('department')` | Same |
| SectionDepartmentController.php:37,92,150,220 | `with('department')` | Same |
| GrantItemController.php:201 | `with('grant')` | Same |

**Note:** If `withTrashed()` is added at the relationship definition level, eager loading automatically includes soft-deleted parents. No changes needed in controllers.

---

## 4. Unique Constraint List

### employees table

| Constraint | Columns | Current Type | Needs Filtered Index? |
|------------|---------|-------------|----------------------|
| `employees_staff_id_organization_unique` | (staff_id, organization) | UNIQUE composite | YES — replace with `WHERE deleted_at IS NULL` |

### departments table

| Constraint | Columns | Current Type | Needs Filtered Index? |
|------------|---------|-------------|----------------------|
| `departments_name_unique` | name | UNIQUE | YES — replace with `WHERE deleted_at IS NULL` |

### grants table

| Constraint | Columns | Current Type | Needs Filtered Index? |
|------------|---------|-------------|----------------------|
| None | — | — | No changes needed |

### section_departments table (already SoftDeleted)

| Constraint | Columns | Current Type | Needs Filtered Index? |
|------------|---------|-------------|----------------------|
| `section_departments_name_department_id_deleted_at_unique` | (name, department_id, deleted_at) | Already soft-delete safe | No changes needed |

### Unique Validation Rules to Update

| File | Current Rule | Updated Rule |
|------|-------------|-------------|
| StoreEmployeeRequest.php:36 | `Rule::unique('employees')->where(...)` | Add `->whereNull('deleted_at')` |
| UpdateEmployeeRequest.php:28 | `Rule::unique('employees')->where(...)->ignore(...)` | Add `->whereNull('deleted_at')` |
| EmployeeController.php:699 | `unique:employees,staff_id,$id` | Change to `Rule::unique('employees','staff_id')->ignore($id)->whereNull('deleted_at')` |
| EmployeesImport.php:384 | `unique:employees,staff_id` | Change to Rule with `whereNull('deleted_at')` |
| GrantController.php:838 | `unique:grants,code` | Add `->whereNull('deleted_at')` if Grant gets SoftDeletes |
| StoreDepartmentRequest.php:25 | `unique:departments,name` | Add `->whereNull('deleted_at')` |

---

## 5. FK Constraint Map

### Employee's Children — ON DELETE actions

| Child Table | FK Column | Current ON DELETE | SoftDelete Impact | Prune Impact | Recommended Action |
|-------------|-----------|-------------------|-------------------|--------------|-------------------|
| employments | employee_id | CASCADE | No effect (UPDATE) | CASCADE deletes employments | Keep CASCADE |
| employee_beneficiaries | employee_id | CASCADE | No effect | CASCADE deletes | Keep CASCADE |
| employee_children | employee_id | CASCADE | No effect | CASCADE deletes | Keep CASCADE |
| employee_education | employee_id | CASCADE | No effect | CASCADE deletes | Keep CASCADE |
| employee_trainings | employee_id | CASCADE | No effect | CASCADE deletes | Keep CASCADE |
| employee_languages | employee_id | implicit | No effect | Needs explicit CASCADE | Add CASCADE FK |
| leave_requests | employee_id | implicit | No effect | Needs explicit CASCADE | Add CASCADE FK |
| leave_balances | employee_id | implicit | No effect | Needs explicit CASCADE | Add CASCADE FK |
| travel_requests | employee_id | CASCADE | No effect | CASCADE deletes | Keep CASCADE |
| resignations | employee_id | CASCADE | No effect | CASCADE deletes | Keep CASCADE |
| probation_records | employee_id | NO ACTION | No effect | **BLOCKS prune** | Change to CASCADE |
| holiday_compensation_records | employee_id | implicit | No effect | Needs explicit CASCADE | Add CASCADE FK |
| allocation_change_logs | employee_id | implicit | No effect | Needs explicit | Add CASCADE or SET NULL |
| employee_funding_allocation_history | employee_id | NO ACTION | No effect | **BLOCKS prune** | Change to CASCADE |

### Employment's Children

| Child Table | FK Column | Current ON DELETE | Prune Impact | Recommended |
|-------------|-----------|-------------------|--------------|-------------|
| payrolls | employment_id | NO ACTION | **BLOCKS prune** | **Keep NO ACTION** — payrolls must block |
| employment_histories | employment_id | implicit (NO ACTION) | **BLOCKS prune** | Change to CASCADE |
| personnel_actions | employment_id | implicit (NO ACTION) | **BLOCKS prune** | Change to CASCADE |
| probation_records | employment_id | CASCADE | CASCADE deletes | Keep CASCADE |
| employee_funding_allocations | employment_id | implicit | Needs explicit | Add CASCADE or SET NULL |

### Grant's Children

| Child Table | FK Column | Current ON DELETE | Prune Impact | Recommended |
|-------------|-----------|-------------------|--------------|-------------|
| grant_items | grant_id | CASCADE | CASCADE deletes items | Keep CASCADE |
| organization_hub_funds | hub_grant_id | implicit | Needs explicit | Add SET NULL |
| inter_organization_advances | via_grant_id | implicit | Needs explicit | Add SET NULL |

### Department's Children

| Child Table | FK Column | Current ON DELETE | Prune Impact | Recommended |
|-------------|-----------|-------------------|--------------|-------------|
| positions | department_id | CASCADE | CASCADE deletes | Keep CASCADE |
| section_departments | department_id | CASCADE | CASCADE deletes | Keep CASCADE |
| employments | department_id | NO ACTION | **BLOCKS prune** | Keep NO ACTION (correct — reassign first) |
| employment_histories | department_id | NO ACTION | **BLOCKS prune** | Keep NO ACTION (audit trail) |
| personnel_actions | current/new_department_id | NO ACTION | **BLOCKS prune** | Keep NO ACTION |
| resignations | department_id | NO ACTION | **BLOCKS prune** | Keep NO ACTION |
| travel_requests | department_id | NO ACTION | **BLOCKS prune** | Keep NO ACTION |

**Key insight:** Department prune is heavily blocked by NO ACTION FKs — departments with ANY employment/history/personnel records can never be permanently deleted. This is correct behavior for an HR system.

---

## 6. Migration Plan

### Migration 1: Add deleted_at to Employee, Grant, Department

```php
// 2026_02_10_000001_add_soft_deletes_to_core_models.php

Schema::table('employees', function (Blueprint $table) {
    $table->softDeletes();
    $table->index('deleted_at');
});

Schema::table('grants', function (Blueprint $table) {
    $table->softDeletes();
    $table->index('deleted_at');
});

Schema::table('departments', function (Blueprint $table) {
    $table->softDeletes();
    $table->index('deleted_at');
});
```

### Migration 2: Replace unique constraints with filtered indexes

```php
// 2026_02_10_000002_update_unique_constraints_for_soft_deletes.php

// employees: (staff_id, organization) composite unique
Schema::table('employees', function (Blueprint $table) {
    $table->dropUnique(['staff_id', 'organization']);
});
DB::statement('
    CREATE UNIQUE NONCLUSTERED INDEX employees_staff_id_organization_unique
    ON employees (staff_id, organization)
    WHERE deleted_at IS NULL
');

// departments: name unique
Schema::table('departments', function (Blueprint $table) {
    $table->dropUnique(['name']);
});
DB::statement('
    CREATE UNIQUE NONCLUSTERED INDEX departments_name_unique
    ON departments (name)
    WHERE deleted_at IS NULL
');
```

### Migration 3: Fix implicit FK constraints (add explicit ON DELETE)

```php
// 2026_02_10_000003_add_explicit_fk_constraints.php

// employee_languages — add explicit CASCADE
// leave_requests — add explicit CASCADE on employee_id
// leave_balances — add explicit CASCADE on employee_id
// holiday_compensation_records — add explicit CASCADE
// employment_histories — change to CASCADE on employment_id
// probation_records — change employee_id from NO ACTION to CASCADE
// employee_funding_allocation_history — change employee_id from NO ACTION to CASCADE
```

### Migration 4: Migrate existing SafeDeleteService data (optional)

```php
// 2026_02_10_000004_migrate_safe_delete_data_to_soft_deletes.php

// For each DeletionManifest:
// 1. Re-insert the root record from deleted_models snapshot
// 2. Set deleted_at = manifest.created_at on the re-inserted record
// 3. Re-insert all children (they don't need deleted_at)
// 4. Delete the manifest and its deleted_models rows
//
// OR: Simply leave existing manifests as-is until they're auto-purged
```

---

## 7. Risk Assessment

### HIGH RISK

1. **Payroll joins in Payroll.php (4 instances)** — These raw joins on employees/departments table will include soft-deleted records unless manually filtered. Payroll reports could show deleted employees.

2. **Employee import unique validation** — `EmployeesImport.php:384` uses simple `unique:employees,staff_id`. Without `whereNull('deleted_at')`, importing a staff_id that belongs to a soft-deleted employee will fail even though the record is "deleted".

3. **Existing SafeDeleteService data** — Records currently in `deleted_models`/`deletion_manifests` were hard-deleted. Switching to SoftDeletes doesn't restore them. Need a migration strategy or accept data loss for already-deleted records.

### MEDIUM RISK

4. **EmploymentObserver.php `deleting()` hook** — Blocks employment deletion if active allocations/payrolls exist. With SoftDeletes on Employee, this observer still fires when someone tries to hard-delete an employment. But during normal soft-delete of Employee, employment is NOT deleted at all (no CASCADE on UPDATE). This is correct behavior.

5. **Position.php `boot()` validation** — Validates department hierarchy on create/update. If department is soft-deleted, position creation in that department should be blocked. Current validation doesn't check `deleted_at`.

6. **Grant GrantItem boot() uniqueness validation** — GrantItem validates uniqueness of (grant_position, budgetline_code, grant_id) in boot(). If grant is soft-deleted, this check could allow duplicates when grant is restored.

### LOW RISK

7. **Cache invalidation** — CacheInvalidationObserver handles `deleted` and `forceDeleted` events. SoftDeletes fires `deleted` event (not `forceDeleted`), so cache is properly invalidated on soft-delete.

8. **Activity logging** — LogsActivity trait logs `deleted` events. SoftDeletes fires `deleted` event, so logging works automatically.

9. **Notification on employee delete** — EmployeeController sends notification on delete. Still works with SoftDeletes since the controller logic doesn't change, only the model behavior.

---

## 8. Existing Delete Logic to Preserve

### Blocker Validation (must keep in controllers)

| Model | Blocker | Current Location | New Location |
|-------|---------|-----------------|-------------|
| Employee | Cannot delete if payrolls exist | SafeDeleteService:362-374 | EmployeeController::destroy() or model |
| Grant | Cannot delete if funding allocations exist | SafeDeleteService:412-424 | GrantController::destroy() or model |
| Department | Cannot delete if employments exist | SafeDeleteService:436-442 | DepartmentController::destroy() or model |
| Department | Cannot delete if employment history exists | SafeDeleteService:444-450 | DepartmentController::destroy() or model |
| Department | Cannot delete if personnel actions exist | SafeDeleteService:452-461 | DepartmentController::destroy() or model |
| Employment | Cannot delete if active allocations exist | EmploymentObserver:125-136 | Keep in observer |
| Employment | Cannot delete if payroll records exist | EmploymentObserver:139-144 | Keep in observer |

### Events/Observers to Review

| Observer/Hook | Event | Impact of SoftDeletes |
|---------------|-------|----------------------|
| EmploymentObserver::deleting() | Blocks delete | Still fires on direct employment delete — OK |
| CacheInvalidationObserver::deleted() | Cache clear | Still fires on soft-delete — OK |
| BenefitSetting::deleted() | Cache clear | Already SoftDeleted — OK |
| TaxSetting::deleted() | Cache clear | Not affected — no SoftDeletes on TaxSetting |
| Employment::booted() created/updated | History logging | Not affected by parent SoftDeletes |

---

## 9. Implementation Summary

### What changes:
- 3 models get `SoftDeletes` + `Prunable` traits (Employee, Grant, Department)
- 28 `belongsTo` relationships get `->withTrashed()`
- 2 unique constraints become MSSQL filtered indexes
- 6 unique validation rules add `->whereNull('deleted_at')`
- 4 Payroll.php join queries add `deleted_at IS NULL`
- ~7 FK constraints need explicit ON DELETE action
- Blocker validation moves from SafeDeleteService to controllers/models
- RecycleBinController updated to query both soft-deleted + Spatie records

### What gets removed:
- SafeDeleteService (cascade snapshot/restore logic)
- DeletionManifest model + migration
- SpatieDeletedModel (IDENTITY_INSERT override)
- ConvertsDatesForSqlServer trait
- PurgeExpiredDeletionsCommand (replaced by `model:prune`)

### What stays the same:
- Interview/JobOffer use KeepsDeletedModels (unchanged)
- UniversalRestoreService (for Interview/JobOffer legacy restores)
- RecycleBinController (refactored to query both sources)
- All existing SoftDeleted models (PersonnelAction, Resignation, Site, etc.)

---

## 10. Additional Checks (Checks 1-10)

### CHECK 1: Route Model Binding
**Status: CLEAR**

No implicit route model binding used. All routes use `{id}` parameters (not `{employee}`, `{grant}`, `{department}`). Controllers manually resolve models via `findOrFail($id)` or `find($id)`.

**Manual model resolution audit (40+ occurrences):**
- All normal CRUD operations (show, update, store) — 404 on soft-deleted is correct behavior
- SafeDeleteService destroy operations — correctly find before deleting
- RecycleBinController — queries `DeletionManifest`/`DeletedModel` tables, not actual models

**3 staff_id lookups that will correctly exclude soft-deleted records:**

| File | Line | Code |
|------|------|------|
| EmploymentController.php | 289-291 | `Employee::where('staff_id', $staffId)->first()` |
| TravelRequestController.php | 832-834 | `Employee::where('staff_id', $staffId)->first()` |
| LeaveRequestReportController.php | 442-445 | `Employee::where('staff_id', $staffId)->first()` |

These correctly exclude soft-deleted employees (desired behavior for lookups).

---

### CHECK 2: API Resources and Transformers
**Status: NEEDS CHANGES — 6 Resources have unsafe relationship access**

If all 28 `belongsTo` relationships get `withTrashed()`, the `whenLoaded()` closures will work correctly because the relationship will load the soft-deleted parent. However, **if the relationship is NOT loaded** (not eager-loaded), accessing `$this->employee->something` inside `whenLoaded()` still triggers a lazy load — which WITH `withTrashed()` on the relationship definition will include soft-deleted parents.

**Unsafe patterns (direct property access in whenLoaded closure):**

| File | Line | Pattern | Risk |
|------|------|---------|------|
| EmployeeBeneficiaryResource.php | 28-31 | `$this->employee->id` in whenLoaded | Safe IF belongsTo has withTrashed |
| EmployeeFundingAllocationResource.php | 39-46 | `$this->employee->staff_id` in whenLoaded | Safe IF belongsTo has withTrashed |
| LeaveRequestResource.php | 40-48 | `$this->employee->organization` in whenLoaded | Safe IF belongsTo has withTrashed |
| TravelRequestResource.php | 48-55 | `$this->employee->staff_id` in whenLoaded | Safe IF belongsTo has withTrashed |
| TravelRequestResource.php | 56-61 | `$this->department->name` in whenLoaded | Safe IF belongsTo has withTrashed |
| EmployeeDetailResource.php | 78-101 | Deeply nested: `$this->employment->department->name` | Safe IF belongsTo has withTrashed |

**Safe patterns already in use:**

| File | Line | Pattern |
|------|------|---------|
| GrantItemResource.php | 20-21 | `$this->grant?->code` (null-safe operator) |
| PositionResource.php | 41 | `new DepartmentResource($this->whenLoaded('department'))` |
| SectionDepartmentResource.php | 45 | `$this->department?->name` (null-safe) |

**Conclusion:** All 6 resources are safe **as long as the 28 belongsTo relationships get `withTrashed()` added**. No additional resource changes needed if that's done.

---

### CHECK 3: Policies and Authorization
**Status: NOT APPLICABLE**

- No Policy files exist (`app/Policies/` directory does not exist)
- Authorization is handled entirely via Spatie Permission middleware on routes
- `DynamicModulePermission` middleware: GET→read, POST/PUT/DELETE→edit
- No `Gate::define`, `Gate::before`, or `$this->authorize()` calls found
- No changes needed for SoftDeletes

---

### CHECK 4: Scheduled Tasks and Commands
**Status: NEEDS CHANGES — 2 commands need null checks**

**Commands inventory:**

| Command | Signature | Queries Employee/Grant/Department? | Issue |
|---------|-----------|-----------------------------------|-------|
| ProcessProbationCompletions | `employment:process-probation-transitions` | YES — `Employment::with('employee')` | Lines 82-85: accesses `$employment->employee->staff_id` WITHOUT null check |
| MigrateProbationRecords | `probation:migrate-records` | NO — uses IDs only | LOW risk |
| PurgeExpiredDeletionsCommand | `recycle-bin:purge` | NO — uses SafeDeleteService metadata | To be replaced by `model:prune` |
| UpdateGrantItemBudgetCodes | stub | NO — empty handle() | N/A |
| CleanExpiredPasswordResets | `auth:clean-resets` | NO | N/A |

**Fix needed in ProcessProbationCompletions.php (lines 82-85):**
```php
// Current (unsafe):
$employeeName = sprintf('%s (%s %s)',
    $employment->employee->staff_id,  // NULL DEREFERENCE if employee deleted
    $employment->employee->first_name_en,
    $employment->employee->last_name_en
);

// Fix: add null check
if (!$employment->employee) {
    Log::warning("Employment #{$employment->id} has no linked employee");
    continue;
}
```

**Schedule:** No scheduled tasks found in `routes/console.php` (only default `inspire` command). Schedule needs to be set up for `model:prune`.

---

### CHECK 5: Queued Jobs and Event Listeners
**Status: NEEDS CHANGES — 1 job has race condition**

**ProcessBulkPayroll.php:**
- Line 82-86: `Employment::with(['employee', 'department'])->whereIn('id', $this->employmentIds)->get()`
- Line 131: `$employee = $employment->employee` — returns null if soft-deleted between dispatch and execution
- Line 133: Has defensive `if (!$employee)` check — **partially safe**, but logs error and fails that employee's payroll
- **Fix:** Add `->withTrashed()` to the `with('employee')` eager load so payroll still processes for recently-deleted employees

**All notifications use safe patterns:**
- EmployeeActionNotification: null coalescing (`$this->employee->first_name_en ?? ''`)
- GrantActionNotification: safe getter helper method
- EmployeeController delete: passes static data copy, not model reference

---

### CHECK 6: Search and Filter Features
**Status: NEEDS CHANGES — dropdown selects for edit forms**

**Options/dropdown endpoints:**

| Controller | Method | Model | Issue |
|------------|--------|-------|-------|
| DepartmentController | options() | Department | Will exclude soft-deleted departments — correct for new records, problem for editing existing records that reference a soft-deleted dept |
| PositionController | options() | Position (with department) | Same issue for department |
| SiteController | options() | Site (already SoftDeleted) | Already excludes soft-deleted — no new issue |
| SectionDepartmentController | options() | SectionDepartment (already SoftDeleted) | Already excludes soft-deleted — no new issue |

**Edit form problem:** When editing an Employment record that references a soft-deleted Department, the department dropdown won't include the currently-selected value. Frontend needs to handle this by keeping the stored value even if it's not in the options list.

**Recommended approach:** No backend change needed. Frontend should display the current value even if it's not in the dropdown options (standard UX pattern for archived references).

---

### CHECK 7: Soft-Deleted Models That Reference Other Soft-Deleted Models
**Status: NEEDS CHANGES — 1 accessor has null risk**

**Cross-soft-delete relationships:**

| Already SoftDeleted Model | References | Issue |
|--------------------------|------------|-------|
| PersonnelAction | Department (currentDepartment, newDepartment) | Already in the 28 belongsTo list |
| Resignation | Employee, Department | Already in the 28 belongsTo list |
| SectionDepartment | Department | Already in the 28 belongsTo list |

**SectionDepartment.php line 87-89 — `getFullNameAttribute()` accessor:**
```php
// Accesses $this->department->name without null check
// If department is soft-deleted AND belongsTo doesn't have withTrashed(), this fails
```
Fix: Adding `withTrashed()` to `SectionDepartment::department()` resolves this.

**Employee → Resignation relationship:**
- `Employee::resignations()` returns `hasMany(Resignation::class)` — does NOT include `withTrashed()`
- Soft-deleted resignations are excluded from the employee's resignations collection
- This is CORRECT behavior — soft-deleted resignations should not appear in active lists
- If needed for admin/audit views, use `$employee->resignations()->withTrashed()->get()`

**Double-soft-delete scenarios:** Independent — restoring Employee does not affect PersonnelAction/Resignation soft-delete state. This is correct behavior.

---

### CHECK 8: Database Views and Stored Procedures
**Status: CLEAR**

No database views or stored procedures exist. The project uses a pure Laravel ORM approach. All business logic is in PHP service classes, model scopes, and controllers.

---

### CHECK 9: Data Export and Reporting
**Status: NEEDS REVIEW — 2 exports query Employee/Grant directly**

| Export Class | Model Queried | Will Exclude Soft-Deleted? | Should It? |
|-------------|---------------|---------------------------|------------|
| EmployeesExport.php | `Employee::query()` | YES (auto-excluded) | YES — active employee list should not include deleted |
| GrantItemsReferenceExport.php | `Grant::with('grantItems')` | YES (auto-excluded) | YES — reference sheet should show active grants only |
| InterviewReportExport.php | Interview (not soft-deleted) | N/A | N/A |
| 4 Template exports | No DB queries | N/A | N/A |

**Conclusion:** Both employee and grant exports correctly exclude soft-deleted records. No changes needed for normal export behavior. If a future "include archived" option is needed, add `withTrashed()` toggle.

**ReportController:** 6 methods are empty stubs (no implementation) — no issue.

---

### CHECK 10: WebSocket / Real-time Events
**Status: CLEAR — minimal risk**

| Event | Serializes Employee/Grant/Department? | Risk |
|-------|--------------------------------------|------|
| EmployeeActionEvent | YES — `public $employee` property | LOW — `broadcastWith()` extracts specific fields with null coalescing |
| PayrollBulkProgress | NO — only scalar values | NONE |
| UserPermissionsUpdated | NO — only scalar values | NONE |
| EmployeeImportCompleted | NO — only import metadata | NONE |
| UserProfileUpdated | NO — only scalar values | NONE |

**EmployeeActionEvent detail:** The `broadcastWith()` method (line 59-76) safely extracts data:
```php
$employeeName = ($this->employee->first_name_en ?? '').' '.($this->employee->last_name_en ?? '');
$staffId = $this->employee->staff_id ?? 'N/A';
```
Uses null coalescing throughout — safe for soft-deleted employees.

---

## 11. Full Change Count Summary

| Category | Items | Priority |
|----------|-------|----------|
| Models: add SoftDeletes + Prunable | 3 (Employee, Grant, Department) | HIGH |
| belongsTo: add withTrashed() | 28 relationships | HIGH |
| Unique constraints: filtered indexes | 2 (employees, departments) | HIGH |
| Unique validation rules: whereNull | 6 rules | HIGH |
| Payroll.php raw joins: add filter | 4 joins | HIGH |
| FK constraints: add explicit CASCADE | ~7 FKs | MEDIUM |
| ProcessProbationCompletions: null check | 1 command | MEDIUM |
| ProcessBulkPayroll: withTrashed eager load | 1 job | MEDIUM |
| Schedule model:prune command | 1 schedule entry | MEDIUM |
| Remove SafeDeleteService + related | 5 files | LOW (cleanup) |
| Frontend dropdown handling | UX pattern (no backend change) | LOW |
| RecycleBinController refactor | 1 controller | MEDIUM |
