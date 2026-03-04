# Employee Funding Allocation — Active/Inactive Toggle Implementation

**Date**: 2026-03-04
**Status**: Implementing

---

## 1. Problem Statement

When an employee moves from one grant to another, the system should **keep the old allocation as Inactive** rather than deleting it. This preserves a full audit trail of which grants funded the employee over time. The frontend needs to show both Active and Inactive allocations, let users toggle status, and add new grants while old ones remain visible.

---

## 2. Current State Analysis

### 2.1 Status Enum

`App\Enums\FundingAllocationStatus` defines three statuses:

| Status | Meaning | Used by Payroll? |
|--------|---------|:----------------:|
| **Active** | Currently funding the employee | Yes (regular payroll) |
| **Inactive** | Deactivated (available for future use) | December 13th month only |
| **Closed** | System-replaced by a new allocation | December 13th month only |

### 2.2 What Already Works

| Feature | Location | Status |
|---------|----------|--------|
| `batchUpdate()` loads Active + Inactive allocations | Service line 391 | Working |
| FTE validation excludes Inactive from 100% total | Service line 420, 426 | Working |
| Request allows `status: "inactive"` in updates | Request line 23 | Working |
| Status dropdown in edit mode | Panel line 268-283 | Working |
| Frontend FTE calc excludes inactive rows | Panel line 400 | Working |
| Inactive card CSS (reduced opacity) | Panel line 1129-1132 | Working |
| Observer tracks Inactive → `recordTermination` | Observer line 49-56 | Working |
| December payroll includes Inactive for 13th month | ProcessBulkPayroll line 121 | Working |

### 2.3 Gaps Preventing End-to-End Functionality

| # | Gap | Impact |
|---|-----|--------|
| 1 | `grant_item_id`/`fte` required in batch update validation | Can't toggle status without resending all fields |
| 2 | `batchUpdate()` response only returns Active allocations | Inactive allocations disappear after save |
| 3 | `destroy()` does hard delete | No audit trail, orphaned payroll FKs |
| 4 | `GET /employments/:id/funding-allocations` route missing | Panel can't load allocations (404) |

---

## 3. Implementation Details

### 3.1 Make `grant_item_id`/`fte` Optional in Batch Updates

**File**: `app/Http/Requests/FundingAllocation/BatchUpdateAllocationsRequest.php`

```php
// BEFORE (lines 21-22):
'updates.*.grant_item_id' => 'required|integer|exists:grant_items,id',
'updates.*.fte' => 'required|numeric|min:1|max:100',

// AFTER:
'updates.*.grant_item_id' => 'nullable|integer|exists:grant_items,id',
'updates.*.fte' => 'nullable|numeric|min:1|max:100',
```

**File**: `app/Services/EmployeeFundingAllocationService.php`

FTE validation (lines 425-427) must handle null `fte`:
```php
$updatesFte = collect($updates)
    ->filter(function ($u) {
        $status = $u['status'] ?? FundingAllocationStatus::Active->value;
        return $status === FundingAllocationStatus::Active->value;
    })
    ->sum(function ($u) use ($currentAllocations) {
        if (isset($u['fte'])) {
            return $u['fte'];
        }
        $existing = $currentAllocations->get($u['id']);
        return $existing ? (float) $existing->fte * 100 : 0;
    });
```

Update processing (lines 479-490) must fall back to existing values:
```php
$newFte = isset($updateData['fte'])
    ? (float) $updateData['fte'] / 100
    : (float) $allocation->fte;
$newGrantItemId = $updateData['grant_item_id'] ?? $allocation->grant_item_id;
$allocatedAmount = $baseSalary * $newFte;
```

### 3.2 Response Includes Inactive Allocations

**File**: `app/Services/EmployeeFundingAllocationService.php` (lines 518-530)

```php
// BEFORE:
->where('status', FundingAllocationStatus::Active)

// AFTER:
->whereIn('status', [FundingAllocationStatus::Active, FundingAllocationStatus::Inactive])
->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
->orderByDesc('updated_at')

// total_fte counts Active only:
'total_fte' => round(
    $freshAllocations->where('status', FundingAllocationStatus::Active)->sum('fte') * 100,
    2
),
```

### 3.3 `destroy()` Sets Inactive

**File**: `app/Services/EmployeeFundingAllocationService.php` (lines 372-375)

```php
// BEFORE:
public function destroy(EmployeeFundingAllocation $allocation): void
{
    $allocation->delete();
}

// AFTER:
public function destroy(EmployeeFundingAllocation $allocation, ?User $performedBy = null): void
{
    $allocation->update([
        'status' => FundingAllocationStatus::Inactive,
        'updated_by' => $performedBy?->name ?? 'system',
    ]);
}
```

The observer automatically creates an audit record when status changes to Inactive (`recordTermination()`).

### 3.4 Add Missing Route

**File**: `routes/api/employment.php` — inside employments prefix group:
```php
Route::get('/{employment}/funding-allocations', [EmploymentController::class, 'fundingAllocations'])
    ->middleware('permission:employment_records.read');
```

**File**: `app/Http/Controllers/Api/V1/EmploymentController.php`:
```php
public function fundingAllocations(Employment $employment): JsonResponse
{
    $allocations = EmployeeFundingAllocation::with([
        'grantItem:id,grant_id,grant_position,budgetline_code',
        'grantItem.grant:id,name,code',
    ])
        ->where('employment_id', $employment->id)
        ->whereIn('status', [FundingAllocationStatus::Active, FundingAllocationStatus::Inactive])
        ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
        ->orderByDesc('updated_at')
        ->get();

    return $this->successResponse([
        'funding_allocations' => EmployeeFundingAllocationResource::collection($allocations),
    ], 'Funding allocations retrieved successfully');
}
```

### 3.5 Frontend Changes

**File**: `src/components/panel/EmployeeFundingAllocationPanel.vue`

1. **Subtitle**: Show "2 active / 3 total" instead of "3 allocations"
2. **Deactivate text**: Change "Delete this allocation?" to "Deactivate this allocation?"
3. **Disable fields**: When a row is Inactive, disable grant/position/FTE inputs

---

## 4. Status Semantics

| Status | Set by | Meaning | Can reactivate? |
|--------|--------|---------|:---------------:|
| **Active** | Default on create | Currently funding | N/A |
| **Inactive** | User deactivation / `destroy()` | Manually deactivated | Yes |
| **Closed** | System: `store()` replace, `batchUpdate()` delete, `updateEmployeeAllocations()` | Replaced by new allocation | No (replacement exists) |

---

## 5. Payroll Impact

No payroll code changes needed. The existing payroll system already:
- Only processes **Active** allocations for regular months (Jan-Nov)
- Includes **Inactive + Closed** allocations in December for 13th month salary
- References allocations by FK — soft-deactivating preserves the FK relationship

---

## 6. Files Modified

| File | Change |
|------|--------|
| `app/Http/Requests/FundingAllocation/BatchUpdateAllocationsRequest.php` | `required` → `nullable` |
| `app/Services/EmployeeFundingAllocationService.php` | 4 changes: destroy, FTE validation, update processing, response |
| `app/Http/Controllers/Api/V1/EmployeeFundingAllocationController.php` | Pass user to destroy |
| `app/Http/Controllers/Api/V1/EmploymentController.php` | New `fundingAllocations()` method |
| `routes/api/employment.php` | New route |
| `src/components/panel/EmployeeFundingAllocationPanel.vue` | UI text, subtitle, disabled fields |
