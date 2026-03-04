# Employee Funding Allocation System — Detailed Analysis

**Date**: 2026-03-04

---

## 1. Overview

Each employee's salary is split across one or more **grant allocations** (funding sources). The `employee_funding_allocations` table tracks which grants fund which percentage (FTE) of each employee's salary. This directly drives payroll — every payroll record is tied to a specific allocation.

**Key rule**: All active allocations for an employee's employment must sum to exactly 100% FTE.

---

## 2. Status Lifecycle

The system has three statuses defined in `App\Enums\FundingAllocationStatus`:

| Status | Meaning | Receives Regular Payroll? | Receives 13th Month (Dec)? |
|--------|---------|:------------------------:|:--------------------------:|
| **Active** | Currently funding the employee | Yes | Yes |
| **Inactive** | Employee terminated/resigned | No | Yes (if YTD payroll exists) |
| **Closed** | Replaced by a new allocation | No | Yes (if YTD payroll exists) |

### How each status is set:

| Status | Set by | Method |
|--------|--------|--------|
| **Active** | Default on creation | `store()`, `batchUpdate()` creates, `updateEmployeeAllocations()` |
| **Closed** | Replaced by new allocation | `store()` (via `replace_allocation_ids`), `batchUpdate()` (via `deletes`), `updateEmployeeAllocations()`, `bulkDeactivate()` |
| **Inactive** | Employee termination | Observer recognizes it, but **NO service method currently sets this status** |

### Important gap: `Inactive` is not actively used

The `Inactive` status exists in the enum and has:
- A model scope: `scopeInactive()`
- A helper method: `isInactive()`
- Observer handling: `recordTermination()` is called when status changes to Inactive
- PayrollJob support: December query includes `whereIn('status', ['inactive', 'closed'])` — treats Inactive same as Closed

**But no service method currently sets `status = Inactive`.** This is available for future use.

---

## 3. All Methods That Modify Allocations

### 3.1 `store()` — Create New Allocations (with optional replace)

**File**: `EmployeeFundingAllocationService.php` lines 81–250

**Flow**:
1. Validate employment has salary defined
2. Get all **Active** allocations for this employment
3. If `replace_allocation_ids` provided → validate they belong to this employment and are active
4. FTE validation: `existing_to_keep + new_allocations = 100%`
5. Phase 1: Validate ALL new allocations (grant capacity, duplicates)
6. Phase 2: In a DB transaction:
   - Mark replaced allocations as **Closed** (triggers observer → `recordEnded`)
   - Create new allocations as **Active** (triggers observer → `recordCreation`)

**Key behavior**: Old allocations get `Closed` status with full audit trail. New ones are `Active`.

### 3.2 `update()` — Update a Single Allocation

**File**: `EmployeeFundingAllocationService.php` lines 289–367

**Flow**:
1. Validate grant item exists and has capacity
2. If FTE changed → validate total still equals 100%
3. Recalculate `allocated_amount` based on new FTE
4. Update in-place (same record, same ID)

**Key behavior**: This is an **in-place update** — the same record is modified. The observer tracks field changes (FTE, grant_item_id, allocated_amount, salary_type) via `recordUpdate()`.

**Important**: When changing `grant_item_id` (moving to a different grant), the allocation record ID stays the same. The old grant assignment is only preserved in the history table.

### 3.3 `destroy()` — Delete a Single Allocation

**File**: `EmployeeFundingAllocationService.php` lines 372–375

```php
public function destroy(EmployeeFundingAllocation $allocation): void
{
    $allocation->delete();
}
```

**CRITICAL: This does a HARD DELETE.** The record is permanently removed from the database. No status change, no observer event, no history record. The Eloquent `deleting`/`deleted` events fire, but the observer only handles `created` and `updated`.

**Impact**: If an allocation is destroyed, its payroll records (`payrolls.employee_funding_allocation_id`) now point to a non-existent record. This is a data integrity concern.

### 3.4 `batchUpdate()` — Atomic Batch Operations

**File**: `EmployeeFundingAllocationService.php` lines 380–533

Accepts three arrays: `updates`, `creates`, `deletes`.

**Deletes within batchUpdate**: Marks as **Closed** (NOT hard delete):
```php
$allocation->update(['status' => FundingAllocationStatus::Closed, ...]);
```

**Updates**: In-place field modifications (FTE, grant_item_id, status).

**Creates**: New records with `Active` status.

**FTE validation**: Only counts Active allocations in the total.

**Key behavior**: Unlike `destroy()`, this method properly closes allocations instead of hard-deleting.

### 3.5 `updateEmployeeAllocations()` — Full Replacement

**File**: `EmployeeFundingAllocationService.php` lines 612–670

**Flow**:
1. Validate total FTE = 100%
2. Close ALL active allocations for this employee/employment
3. Create ALL new allocations from the input

**Key behavior**: This is a "nuclear" replace — closes everything, creates everything fresh. Good audit trail (all old allocations become Closed), but creates new record IDs for everything.

### 3.6 `bulkDeactivate()` — Close Multiple Allocations

**File**: `EmployeeFundingAllocationService.php` lines 597–607

Sets status to **Closed** for multiple allocation IDs at once. Does NOT validate FTE totals — this can leave an employee with 0% active FTE.

---

## 4. Inconsistency: `destroy()` vs Everything Else

| Method | What happens to old allocation | History? | Payroll FK safe? |
|--------|-------------------------------|:--------:|:----------------:|
| `store()` with replace | Closed | Yes | Yes |
| `batchUpdate()` deletes | Closed | Yes | Yes |
| `updateEmployeeAllocations()` | Closed | Yes | Yes |
| `bulkDeactivate()` | Closed | Yes | Yes |
| **`destroy()`** | **Hard deleted** | **No** | **No** |

The `destroy()` method is the only one that hard-deletes. All other methods properly set status to `Closed`.

---

## 5. How Payroll Uses Allocation Status

### 5.1 Regular Months (Jan–Nov): Active Only

**File**: `ProcessBulkPayroll.php` lines 96–100

```php
$allocations = EmployeeFundingAllocation::whereIn('employee_id', $employeeIds)
    ->where('status', FundingAllocationStatus::Active)
    ->with(['grantItem.grant'])
    ->get()
    ->groupBy('employee_id');
```

Only `Active` allocations receive payroll calculation. `Inactive` and `Closed` are completely ignored.

### 5.2 December: Active + Historical (Inactive/Closed with YTD Payroll)

**File**: `ProcessBulkPayroll.php` lines 112–132

In December, the system also loads "historical" allocations:
1. Join `payrolls` with `employee_funding_allocations`
2. Filter: status IN (`inactive`, `closed`) AND payrolls exist for the current year
3. These historical allocations get **13th-month-only** payroll records (all other fields zero)

**Why**: If an employee was funded by Grant A for Jan–June (now Closed) and Grant B for July–Dec (Active), they should receive 13th month salary proportional to their YTD earnings on BOTH grants.

### 5.3 `forPayrollCalculation` Scope

**File**: `EmployeeFundingAllocation.php` line 136–147

```php
public function scopeForPayrollCalculation($query)
{
    return $query->active()
        ->select(['id', 'employee_id', 'employment_id', 'fte', 'allocated_amount', 'grant_item_id'])
        ->with(['grantItem:id,grant_id,...', 'grantItem.grant:id,name,code']);
}
```

This scope is used by the single-employee payroll preview (PayrollController). It only returns Active allocations.

### 5.4 BulkPayrollService (Preview)

**File**: `BulkPayrollService.php`

Same pattern — loads only Active allocations for preview. Historical allocations are NOT included in preview (only in actual processing).

---

## 6. The Grant Transfer Problem

When an employee moves from Grant A to Grant B (same FTE, just a different funding source), the current system has **two paths**:

### Path 1: `update()` — In-place grant change
- Changes `grant_item_id` on the SAME record
- Record ID stays the same
- History table records the old `grant_item_id`
- All past payroll records still reference this allocation ID
- **Problem**: Past payroll records now appear to be funded by Grant B (the new grant), which is historically inaccurate

### Path 2: `store()` with `replace_allocation_ids` — Close old, create new
- Old allocation → Closed (preserved with original `grant_item_id`)
- New allocation → Active (with new `grant_item_id` and new record ID)
- Past payroll records still correctly reference the old allocation (which shows Grant A)
- Future payroll records reference the new allocation (Grant B)
- **This is the correct approach for grant transfers**

### Path 3: `destroy()` — Hard delete
- Old allocation is permanently deleted
- Past payroll records have a dangling foreign key
- **This should never be used for grant transfers**

---

## 7. Proposed Active/Inactive Approach for UI

Based on your requirement: *"we will need the active or inactive in the UI so that we will have backend records for all the allocation and the frontend will do the active / inactive for the payroll calculation"*

### Current State vs Proposed

| Aspect | Current | Proposed |
|--------|---------|----------|
| Regular months | Only Active allocations get payroll | Same — only Active |
| Allocation visible in UI | Active only (list endpoints filter by Active) | All statuses visible with filters |
| Switching grants | Close old + create new (or in-place update) | Toggle old to Inactive, create new as Active |
| Deleting allocation | Hard delete via `destroy()` | Set to Inactive (never hard delete) |
| December 13th month | Looks for Inactive/Closed with YTD payroll | Same — already works |
| History/audit | History table + status field | Same — already supported |

### What already works:

1. **The `list()` method** already supports `?active=false` filter to show Inactive/Closed allocations
2. **The model** has `scopeInactive()`, `isInactive()`, and observer handling for Inactive
3. **December payroll** already includes Inactive allocations for 13th month
4. **FTE validation** in `batchUpdate()` only counts Active allocations in the 100% total — Inactive allocations are correctly excluded

### What needs changing:

1. **`destroy()` should set Inactive, not hard delete** — this is the main fix
2. **`batchUpdate()` delete handling** already marks as Closed — could be changed to Inactive if desired (Closed = replaced by another allocation, Inactive = deactivated by user choice)
3. **Frontend needs** a way to view all allocations (Active + Inactive + Closed) and potentially reactivate Inactive ones
4. **Consider**: Should the UI distinguish Closed vs Inactive? Or simplify to just Active/Inactive?

---

## 8. Status Semantics Recommendation

| Status | When to use | Can be reactivated? |
|--------|-------------|:-------------------:|
| **Active** | Currently funding the employee, included in payroll | N/A (already active) |
| **Inactive** | Manually deactivated by user via UI, or employee terminated | Yes — user toggles back to Active |
| **Closed** | System-managed: replaced by a new allocation (close-and-create pattern) | No — a new allocation was already created to replace it |

This distinction matters because:
- **Inactive**: User made a deliberate choice to stop using this allocation. They might want to reactivate it.
- **Closed**: The system closed it because a new allocation replaced it. Reactivating would cause duplicate FTE.

---

## 9. Key File References

| Component | File | Lines |
|-----------|------|-------|
| Allocation model | `app/Models/EmployeeFundingAllocation.php` | Full file |
| Status enum | `app/Enums/FundingAllocationStatus.php` | 1–30 |
| Observer (history tracking) | `app/Observers/EmployeeFundingAllocationObserver.php` | Full file |
| History model | `app/Models/EmployeeFundingAllocationHistory.php` | Full file |
| CRUD service | `app/Services/EmployeeFundingAllocationService.php` | Full file |
| FTE validation helper | `app/Services/FundingAllocationService.php` | Full file |
| Controller | `app/Http/Controllers/Api/V1/EmployeeFundingAllocationController.php` | Full file |
| Payroll job (Active filter) | `app/Jobs/ProcessBulkPayroll.php` | 96–100 |
| Payroll job (December historical) | `app/Jobs/ProcessBulkPayroll.php` | 112–132, 277–325 |
| Payroll calculation scope | `app/Models/EmployeeFundingAllocation.php` | 136–147 |
| Preview service | `app/Services/BulkPayrollService.php` | ~190–210 |
