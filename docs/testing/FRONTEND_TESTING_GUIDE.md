# Frontend Testing Guide - Funding Allocation Fix

## Quick Reference for Testing the Employment Edit Modal

---

## What Was Fixed

The backend API endpoint `/api/v1/employments/{id}/funding-allocations` was throwing an error when trying to load funding allocations for employees with org-funded allocations. This has been fixed and is now ready for frontend testing.

---

## How to Test

### Step 1: Navigate to Employment List

1. Open your browser
2. Navigate to: `http://localhost:8080/employee/employment-list`
3. Wait for the page to load

### Step 2: Open Employment Edit Modal

**Test Case 1: John Doe (Mixed Allocations)**
1. Find "John Doe" (Staff ID: 0001) in the employment list
2. Click on the row or click the Edit button
3. The employment edit modal should open **WITHOUT errors**

**Expected Result:**
- ✅ Modal opens successfully
- ✅ Employment details are displayed
- ✅ Funding allocations section shows both allocations:
  - Grant allocation (80% FTE - MREP)
  - Org-funded allocation (20% FTE - Other Fund)
- ✅ No console errors in browser DevTools

### Step 3: Check Browser Console

Open Browser DevTools:
- **Chrome/Edge**: Press `F12` or `Ctrl+Shift+I`
- **Firefox**: Press `F12` or `Ctrl+Shift+K`

1. Go to the **Console** tab
2. Look for any errors
3. Clear the console
4. Open the employment modal again
5. Verify no errors appear

**Expected Result:**
- ✅ No red error messages
- ✅ No "Attempt to read property" errors
- ✅ API response status: 200 OK

### Step 4: Check Network Tab

In Browser DevTools:

1. Go to the **Network** tab
2. Clear the network log
3. Open the employment edit modal
4. Look for the API request: `employments/1/funding-allocations`

**Expected Request:**
```
GET http://localhost:8000/api/v1/employments/1/funding-allocations
Status: 200 OK
```

**Expected Response Structure:**
```json
{
  "success": true,
  "message": "Funding allocations retrieved successfully",
  "data": {
    "employment_id": 1,
    "employee": {
      "id": 1,
      "staff_id": "0001",
      "name": "John Doe",
      "subsidiary": "SMRU"
    },
    "funding_allocations": [
      {
        "id": 1,
        "allocation_type": "grant",
        "fte": 80,
        "grant_name": "MREP",
        "grant_code": "B-12ACD",
        "grant_position": "Medic",
        "budgetline_code": "B-DDCCAABB"
      },
      {
        "id": 2,
        "allocation_type": "org_funded",
        "fte": 20,
        "org_funded": {
          "grant": {
            "name": "Other Fund",
            "code": "S0031"
          }
        }
      }
    ],
    "summary": {
      "total_allocations": 2,
      "total_fte": 1,
      "total_fte_percentage": "100%"
    }
  }
}
```

---

## Additional Test Cases

### Test Case 2: Employee with Only Grant Allocations

If you have an employee with only grant-type allocations:

1. Open their employment edit modal
2. Verify funding allocations display correctly
3. Check that grant information is populated

**Expected Result:**
- ✅ Modal opens without errors
- ✅ Grant allocations show grant name, code, position
- ✅ No org_funded data

### Test Case 3: Employee with Only Org-Funded Allocations

If you have an employee with only org-funded allocations:

1. Open their employment edit modal
2. Verify funding allocations display correctly
3. Check that org-funded information is populated

**Expected Result:**
- ✅ Modal opens without errors
- ✅ Org-funded allocations show grant name
- ✅ No position_slot data

### Test Case 4: Employee with No Allocations

If you have an employee with no funding allocations:

1. Open their employment edit modal
2. Verify the modal opens

**Expected Result:**
- ✅ Modal opens without errors
- ✅ Empty funding allocations section
- ✅ Message indicating no allocations

---

## Common Issues & Troubleshooting

### Issue 1: Modal Still Shows Error

**Solution:**
1. Clear browser cache:
   - Chrome: `Ctrl+Shift+Delete` → Clear cached images and files
   - Firefox: `Ctrl+Shift+Delete` → Clear cached web content
2. Hard refresh the page:
   - `Ctrl+F5` (Windows/Linux)
   - `Cmd+Shift+R` (Mac)
3. Close and reopen the browser

### Issue 2: API Returns 500 Error

**Solution:**
1. Check if backend server is running
2. Verify Laravel cache was cleared:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   ```
3. Check Laravel logs:
   ```bash
   tail -100 storage/logs/laravel.log
   ```

### Issue 3: Funding Allocations Not Displaying

**Possible Causes:**
1. Frontend code needs to be updated to handle the new response format
2. Check if the API response has the correct structure (see expected response above)
3. Verify the frontend is parsing the `funding_allocations` array correctly

**Frontend Code to Check:**
- Modal component that displays funding allocations
- State management for funding allocation data
- Data mapping/transformation logic

---

## Expected API Response Fields

### For Grant Allocations:

```javascript
{
  id: number,
  allocation_type: "grant",
  fte: number (0-100),
  allocated_amount: string,
  position_slot: {
    id: number,
    slot_number: string,
    grant_item: {
      grant_position: string,
      grant_salary: string,
      budgetline_code: string,
      grant: {
        name: string,
        code: string
      }
    }
  },
  // Flattened fields for easier access
  grant_name: string,
  grant_code: string,
  grant_position: string,
  budgetline_code: string
}
```

### For Org-Funded Allocations:

```javascript
{
  id: number,
  allocation_type: "org_funded",
  fte: number (0-100),
  allocated_amount: string,
  org_funded: {
    id: number,
    description: string,
    grant: {
      name: string,
      code: string
    },
    department: {
      name: string
    },
    position: {
      name: string
    }
  }
}
```

**Note:** `grant_name` and `grant_code` are NOT present in org_funded allocations. Access them via `org_funded.grant.name` instead.

---

## Frontend Code Example

### Displaying Funding Allocations

```javascript
// Example Vue/React component logic
fundingAllocations.forEach(allocation => {
  if (allocation.allocation_type === 'grant') {
    // Display grant allocation
    console.log('Grant:', allocation.grant_name);
    console.log('Position:', allocation.grant_position);
    console.log('FTE:', allocation.fte + '%');
  } else if (allocation.allocation_type === 'org_funded') {
    // Display org-funded allocation
    console.log('Grant:', allocation.org_funded.grant.name);
    console.log('Department:', allocation.org_funded.department.name);
    console.log('FTE:', allocation.fte + '%');
  }
});
```

---

## Success Criteria

Before closing this testing task, verify:

- [ ] Employment edit modal opens without errors for John Doe
- [ ] Both grant and org-funded allocations display correctly
- [ ] No console errors in browser DevTools
- [ ] API response status is 200 OK
- [ ] Network request completes successfully
- [ ] All funding allocation data is visible in the UI
- [ ] FTE percentages add up to 100%
- [ ] Grant names and codes are displayed correctly

---

## If Issues Persist

If you encounter any issues after following this guide:

1. **Document the Issue:**
   - Screenshot of the error
   - Browser console logs
   - Network tab showing the API request/response
   - Steps to reproduce

2. **Check Backend Logs:**
   ```bash
   tail -100 storage/logs/laravel.log
   ```

3. **Verify Backend Fix:**
   Run the backend test:
   ```bash
   php artisan tinker
   >>> $employment = App\Models\Employment::find(1);
   >>> $allocations = $employment->employeeFundingAllocations;
   >>> App\Http\Resources\EmployeeFundingAllocationResource::collection($allocations)->resolve();
   ```
   This should return data without errors.

4. **Contact Backend Team:**
   Provide all documentation from step 1.

---

## Backend Changes Summary

**File Modified:** `app/Http/Resources/EmployeeFundingAllocationResource.php`

**Changes:**
- Wrapped property access in closures for lazy evaluation
- Fixed 6 fields: `grant_name`, `grant_code`, `grant_position`, `budgetline_code`
- All changes are backward compatible

**No Frontend Changes Required** - The API response structure remains the same, only the error is fixed.

---

## Questions?

If you have questions about:
- **API Response Structure**: Check the "Expected API Response Fields" section above
- **Backend Fix**: See `FUNDING_ALLOCATION_FIX_SUMMARY.md`
- **Error Analysis**: See `FUNDING_ALLOCATION_GRANTITEM_NULL_ERROR_ANALYSIS.md`

---

**Testing Date**: 2025-11-08
**Status**: Ready for Frontend Testing
**Priority**: High - Blocking critical user workflow
