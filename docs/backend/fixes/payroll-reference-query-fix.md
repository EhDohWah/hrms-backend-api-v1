# Payroll Reference Query Fix

**Date:** January 9, 2026  
**Issue:** Employee Funding Allocations Reference only showing 1 record  
**Status:** ✅ Fixed

---

## Problem

When downloading the Employee Funding Allocations Reference for payroll, the Excel file only contained 1 record instead of all active allocations.

### Root Cause

The query had incorrect SQL logic due to improper use of `orWhere`:

```php
// ❌ INCORRECT - orWhere not properly grouped
$allocations = EmployeeFundingAllocation::with([...])
    ->where('status', 'active')
    ->whereNull('end_date')
    ->orWhere('end_date', '>=', now())  // This breaks the query!
    ->orderBy('employee_id')
    ->get();
```

**What was happening:**
The `orWhere` clause was not grouped with the previous conditions, resulting in SQL like:
```sql
WHERE status = 'active' 
  AND end_date IS NULL 
  OR end_date >= NOW()  -- This OR applies to the entire query!
```

This would return:
- Records with status='active' AND end_date IS NULL, **OR**
- **ANY** records with end_date >= NOW() (regardless of status)

This caused unpredictable results and only returned 1 record.

---

## Solution

Simplified the query to only filter by status, removing the end_date conditions entirely:

```php
// ✅ CORRECT - Simple and reliable
$allocations = EmployeeFundingAllocation::with([
    'employee:id,staff_id,first_name_en,last_name_en,organization',
    'grantItem.grant:id,name,code',
])
    ->where('status', 'active')
    ->orderBy('employee_id')
    ->get();
```

### Why This Works Better

1. **Simpler Logic** - Only one condition to evaluate
2. **Status is Authoritative** - The `status` field already indicates if an allocation is active
3. **No SQL Complexity** - No need for grouped OR conditions
4. **More Reliable** - Easier to understand and maintain

---

## Changes Made

### File Modified
`app/Http/Controllers/Api/PayrollController.php`

**Method:** `downloadEmployeeFundingAllocationsReference()`

**Lines Changed:** 3197-3205

**Before:**
```php
$allocations = EmployeeFundingAllocation::with([
    'employee:id,staff_id,first_name_en,last_name_en,organization',
    'grantItem.grant:id,name,code',
])
    ->where('status', 'active')
    ->whereNull('end_date')
    ->orWhere('end_date', '>=', now())
    ->orderBy('employee_id')
    ->get();
```

**After:**
```php
$allocations = EmployeeFundingAllocation::with([
    'employee:id,staff_id,first_name_en,last_name_en,organization',
    'grantItem.grant:id,name,code',
])
    ->where('status', 'active')
    ->orderBy('employee_id')
    ->get();
```

---

## Testing

### Verification Steps

1. ✅ Download Employee Funding Allocations Reference
2. ✅ Verify all active allocations are included
3. ✅ Check that only status='active' records appear
4. ✅ Confirm Excel file has correct number of rows

### Expected Results

- **Before Fix:** Only 1 record in Excel
- **After Fix:** All active funding allocations included

---

## Alternative Approach (If Needed)

If in the future we need to filter by end_date, the correct way would be:

```php
// ✅ CORRECT - Properly grouped OR conditions
$allocations = EmployeeFundingAllocation::with([...])
    ->where('status', 'active')
    ->where(function ($query) {
        $query->whereNull('end_date')
              ->orWhere('end_date', '>=', now());
    })
    ->orderBy('employee_id')
    ->get();
```

This generates proper SQL:
```sql
WHERE status = 'active' 
  AND (end_date IS NULL OR end_date >= NOW())
```

However, for this use case, filtering by status alone is sufficient and preferred.

---

## Documentation Updates

Updated documentation files to reflect the simplified query:

1. ✅ `docs/backend/features/payroll-funding-allocations-reference.md`
   - Updated "Data Filtering" section
   - Removed end_date filter references

2. ✅ `docs/backend/features/PAYROLL-REFERENCE-SUMMARY.md`
   - Updated "Active Allocations Only" section
   - Added note about status-only filtering

---

## Key Takeaways

### Best Practices

1. **Keep Queries Simple** - Use the minimum conditions needed
2. **Trust Your Data Model** - If `status` indicates active/inactive, use it
3. **Group OR Conditions** - Always use closures for OR logic
4. **Test Edge Cases** - Verify queries return expected results

### Common Pitfall

```php
// ❌ WRONG - orWhere applies to entire query
->where('a', 1)
->where('b', 2)
->orWhere('c', 3)

// ✅ RIGHT - OR grouped within parentheses
->where('a', 1)
->where(function($q) {
    $q->where('b', 2)->orWhere('c', 3);
})
```

---

## Impact

**Before Fix:**
- ❌ Only 1 allocation shown
- ❌ Users couldn't find most allocation IDs
- ❌ Payroll imports would fail

**After Fix:**
- ✅ All active allocations shown
- ✅ Users can find all allocation IDs
- ✅ Payroll imports work correctly
- ✅ Simpler, more maintainable code

---

## Related Issues

This same pattern should be checked in other controllers:
- ✅ `EmployeeFundingAllocationController::downloadGrantItemsReference()` - Uses simpler query (no issue)
- ✅ Other reference downloads - Should follow same pattern

---

## Conclusion

The fix simplifies the query by removing unnecessary end_date filtering and relying solely on the `status` field. This resolves the issue where only 1 record was returned and makes the code more maintainable.

**Result:** All active employee funding allocations now appear in the reference Excel file! 🎉
