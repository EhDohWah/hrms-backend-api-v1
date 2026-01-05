# Intermediary Tables Removal and Site Renaming Implementation

**Date**: 2025-11-26  
**Author**: AI Assistant  
**Status**: Completed  

---

## Overview

This document details the implementation of two major architectural changes:
1. **Removal of intermediary tables** (`position_slots` and `org_funded_allocations`)
2. **Renaming of `work_location` to `site`** across the entire codebase

---

## 1. Intermediary Tables Removal

### Architecture Change

**Before:**
```
grants → grant_items → position_slots → employee_funding_allocations
grants → org_funded_allocations → employee_funding_allocations
```

**After:**
```
grants → grant_items → employee_funding_allocations (direct link via grant_item_id)
grants → employee_funding_allocations (direct link via grant_id)
```

### Rationale
- `position_slots` table was an unnecessary intermediary that only tracked slot numbers
- `org_funded_allocations` duplicated department_id and position_id from employments table
- Direct relationships simplify queries and reduce database complexity

---

## 2. Files Modified

### Backend Models

| File | Changes |
|------|---------|
| `app/Models/EmploymentHistory.php` | - Changed `work_location_id` to `site_id` in fillable<br>- Updated `workLocation()` relationship to use `Site` model<br>- Added `site()` relationship method |
| `app/Models/PersonnelAction.php` | - Updated `currentWorkLocation()` to use `Site` model<br>- Updated `newWorkLocation()` to use `Site` model<br>- Added `currentSite()` and `newSite()` alias methods<br>- Changed `$this->current_work_location_id = $employment->work_location_id` to use `site_id` |

### Backend Controllers

| File | Changes |
|------|---------|
| `app/Http/Controllers/Api/EmploymentController.php` | - Changed `filter_work_location` to `filter_site`<br>- Changed `sort_by` option from `work_location` to `site`<br>- Updated `work_location_id` to `site_id` in select columns<br>- Changed `workLocation:id,name` to `site:id,name` in relationships<br>- Removed `positionSlot` and `orgFunded` relationship loading<br>- Updated to use `grantItem` and `grant` directly<br>- Changed `work_locations` table reference to `sites` in sorting |
| `app/Http/Controllers/Api/PayrollController.php` | - Updated OpenAPI schema from `workLocation` to `site`<br>- Changed relationship loading from `positionSlot`/`orgFunded` to `grantItem`/`grant`<br>- Updated `getFundingSourceName()` method to use new relationships |
| `app/Http/Controllers/Api/BulkPayrollController.php` | - Changed `orgFunded.grant` to `grant` and `positionSlot.grantItem.grant` to `grantItem.grant`<br>- Updated `needsInterSubsidiaryAdvance()` method |
| `app/Http/Controllers/Api/InterSubsidiaryAdvanceController.php` | - Updated relationship loading to use `grantItem.grant` and `grant` |
| `app/Http/Controllers/Api/Reports/LeaveRequestReportController.php` | - Changed `workLocation` to `site` in queries<br>- Updated `work_location_id` to `site_id` |
| `app/Http/Controllers/Api/EmployeeTrainingController.php` | - Changed `workLocation` to `site` in employee loading |
| `app/Http/Controllers/Api/GrantController.php` | - Removed position slot management methods<br>- Updated `getGrantPositions()` to count allocations directly on grant_items<br>- Removed `createPositionSlots()` and `updatePositionSlots()` methods<br>- Updated grant item response to show capacity info |
| `app/Http/Controllers/Api/EmployeeFundingAllocationController.php` | - Updated OpenAPI description |

### Backend Services

| File | Changes |
|------|---------|
| `app/Services/ProbationTransitionService.php` | - Changed `org_funded_id` and `position_slot_id` to `grant_item_id` and `grant_id` in allocation creation |

### Backend Resources

| File | Changes |
|------|---------|
| `app/Http/Resources/GrantItemResource.php` | - Removed `position_slots` reference<br>- Added `active_allocations_count` and `available_capacity` fields |

### Backend Requests

| File | Changes |
|------|---------|
| `app/Http/Requests/LeaveRequestReportRequest.php` | - Changed `exists:work_locations,name` to `exists:sites,name` |
| `app/Http/Requests/PersonnelActionRequest.php` | - Changed `exists:work_locations,id` to `exists:sites,id` for both current and new work location fields |

### Files Deleted

| File | Reason |
|------|--------|
| `fix_org_funded_allocation.php` | Obsolete fix script for removed `org_funded_allocations` table |

### Frontend Components

| File | Changes |
|------|---------|
| `src/views/pages/grant/grant-details.vue` | - Completely rewritten to remove position slot management UI<br>- Now shows grant items with capacity/allocation status directly<br>- Removed all position slot CRUD operations<br>- Added capacity color coding (green/orange/red) |

---

## 3. Database Schema Changes

### employee_funding_allocations Table

**Removed columns:**
- `position_slot_id` (foreign key to position_slots)
- `org_funded_id` (foreign key to org_funded_allocations)

**Added columns:**
- `grant_item_id` (foreign key to grant_items) - for grant allocations
- `grant_id` (foreign key to grants) - for org_funded allocations

### Tables Removed
- `position_slots`
- `org_funded_allocations`

### Column Renames (employments table)
- `work_location_id` → `site_id`

---

## 4. Relationship Changes

### EmployeeFundingAllocation Model

**Before:**
```php
public function positionSlot() {
    return $this->belongsTo(PositionSlot::class);
}

public function orgFunded() {
    return $this->belongsTo(OrgFundedAllocation::class);
}
```

**After:**
```php
public function grantItem() {
    return $this->belongsTo(GrantItem::class);
}

public function grant() {
    return $this->belongsTo(Grant::class);
}
```

### Employment Model

**Before:**
```php
public function workLocation() {
    return $this->belongsTo(WorkLocation::class);
}
```

**After:**
```php
public function site() {
    return $this->belongsTo(Site::class);
}

// Backward compatibility alias
public function workLocation() {
    return $this->site();
}
```

---

## 5. API Changes

### Endpoint Parameter Changes

| Endpoint | Before | After |
|----------|--------|-------|
| `GET /employments` | `filter_work_location` | `filter_site` |
| `GET /employments` | `sort_by=work_location` | `sort_by=site` |

### Response Changes

**Grant Item Response:**
```json
// Before
{
  "id": 1,
  "grant_position": "Developer",
  "position_slots": [...]
}

// After
{
  "id": 1,
  "grant_position": "Developer",
  "active_allocations_count": 2,
  "available_capacity": 3
}
```

**Employment Response:**
```json
// Before
{
  "work_location": { "id": 1, "name": "Bangkok" }
}

// After
{
  "site": { "id": 1, "name": "Bangkok" }
}
```

---

## 6. Backward Compatibility

### WorkLocation Controller
The `WorklocationController.php` was kept as a wrapper that uses the `Site` model internally. This provides backward compatibility for any external integrations using the `/worklocations` API endpoints.

### Relationship Aliases
Added `workLocation()` alias methods that call `site()` in models where backward compatibility is needed.

---

## 7. Testing Recommendations

After these changes, the following should be tested:

1. **Employment CRUD Operations**
   - Create employment with site selection
   - Update employment site
   - Filter employments by site

2. **Funding Allocations**
   - Create grant allocation (links directly to grant_item)
   - Create org_funded allocation (links directly to grant)
   - Verify allocation capacity checking works

3. **Payroll Processing**
   - Verify payroll calculations with new relationships
   - Check inter-subsidiary advance detection

4. **Grant Management**
   - View grant details with allocation counts
   - Verify capacity tracking without position slots

5. **Reports**
   - Leave request report by site
   - Employee training summary

---

## 8. Migration Commands

If running a fresh migration:
```bash
php artisan migrate:fresh --seed
```

---

## 9. Code Formatting

All PHP files were formatted using Laravel Pint:
```bash
vendor/bin/pint --dirty
```

Result: 91 files passed

---

## 10. Related Documentation

- `COMPLETE_REMOVAL_PLAN_INTERMEDIARY_TABLES.md` - Original planning document
- `ORGANIZATIONAL_STRUCTURE_IMPLEMENTATION_COMPLETE.md` - Sites table implementation

---

**End of Document**




























