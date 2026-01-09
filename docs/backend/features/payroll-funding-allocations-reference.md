# Payroll - Employee Funding Allocations Reference

**Date:** January 9, 2026  
**Feature:** Employee Funding Allocations Reference export for Payroll imports

---

## Overview

Created a new reference export that shows all active employee funding allocations with their IDs. This helps users identify which `employee_funding_allocation_id` to use when creating payroll records.

**Similar to:** Grant Items Reference (for funding allocation imports)  
**Purpose:** Provide Funding Allocation IDs for payroll imports

---

## Problem Solved

When creating payroll records, users need to provide `employee_funding_allocation_id` in the import template. However:
- ❌ Users don't know these database-generated IDs
- ❌ One employee can have multiple funding allocations (split funding)
- ❌ Users need to know which allocation to use for each payroll entry
- ❌ No easy way to look up active allocations

---

## Solution

### New Download Endpoint

**Backend:**
```
GET /api/downloads/employee-funding-allocations-reference
```

**Frontend:**
- Added "Download Funding Allocations Reference" link next to "Download Template"
- Green link with info icon
- Inline with template download link

---

## Excel File Structure

### 1. Top Notice Banner (Row 1)
```
🔴 ⚠️ IMPORTANT: Copy the "Funding Allocation ID" (Column A - Green) to your Payroll Import Template
```

**Styling:**
- Red background (`#FF6B6B`)
- White text, bold, size 12
- Merged cells A1:L1
- 30px height

### 2. Header Row (Row 2)

**Columns:**
| Column | Header | Color | Description |
|--------|--------|-------|-------------|
| A | **Funding Allocation ID** | 🟢 Green | **PRIMARY - What users need!** |
| B | Staff ID | 🔵 Blue | Employee identifier |
| C | Employee Name | 🔵 Blue | Full name |
| D | Grant Code | 🔵 Blue | Grant short code |
| E | Grant Name | 🔵 Blue | Full grant name |
| F | Grant Position | 🔵 Blue | Position title |
| G | FTE (%) | 🔵 Blue | Percentage allocated |
| H | Allocated Amount | 🔵 Blue | Monthly amount |
| I | Start Date | 🔵 Blue | Allocation start |
| J | End Date | 🔵 Blue | Allocation end |
| K | Status | 🔵 Blue | Active/Historical/Terminated |
| L | Organization | 🔵 Blue | SMRU/BHF |

### 3. Data Rows (Row 3+)

**Funding Allocation ID Column (A):**
- 🟢 Light green background (`#D4EDDA`)
- 🟢 Dark green text (`#155724`)
- **Bold, size 11**
- 🟢 Green border (`#28A745`, medium weight)
- **Center aligned**

**Other Columns:**
- White background
- Black text
- Normal weight

---

## Data Filtering

### Only Active Allocations Shown

```php
$allocations = EmployeeFundingAllocation::with([...])
    ->where('status', 'active')
    ->orderBy('employee_id')
    ->get();
```

**Filters:**
- ✅ Status = 'active'
- ❌ Historical allocations excluded (status != 'active')
- ❌ Terminated allocations excluded (status != 'active')

**Note:** End date is not used as a filter. The status field is the primary indicator of whether an allocation is currently active.

---

## Example Data

```
┌──────────────────────────────────────────────────────────────────────┐
│ 🔴 ⚠️ IMPORTANT: Copy "Funding Allocation ID" (Column A - Green)    │
├────────────────┬──────────┬───────────────┬───────────┬─────────────┤
│ 🟢 FUNDING     │ 🔵 Staff │ 🔵 Employee   │ 🔵 Grant  │ 🔵 Grant    │
│ ALLOCATION ID  │ ID       │ Name          │ Code      │ Name        │
├────────────────┼──────────┼───────────────┼───────────┼─────────────┤
│ 🟢 5 🟢        │ EMP001   │ John Doe      │ RG-2024   │ Research    │
│ 🟢 6 🟢        │ EMP001   │ John Doe      │ OP-2024   │ Operations  │
│ 🟢 10 🟢       │ EMP002   │ Jane Smith    │ RG-2024   │ Research    │
└────────────────┴──────────┴───────────────┴───────────┴─────────────┘
```

---

## Split Funding Example

### Employee with Multiple Allocations

**EMP001 has two funding allocations:**

| Funding Allocation ID | Grant Code | Grant Position | FTE (%) | Allocated Amount |
|-----------------------|------------|----------------|---------|------------------|
| **5** | RG-2024 | Researcher | 60 | $30,000 |
| **6** | OP-2024 | Analyst | 40 | $20,000 |

### Payroll Import

User creates **TWO payroll rows**:

```excel
staff_id | employee_funding_allocation_id | pay_period_date | gross_salary_by_FTE | ...
---------|--------------------------------|-----------------|---------------------|----
EMP001   | 5                              | 2025-01-01      | 30000.00            | ...
EMP001   | 6                              | 2025-01-01      | 20000.00            | ...
```

---

## User Workflow

### Step 1: Download Reference
1. User clicks "Download Funding Allocations Reference"
2. System generates Excel with all active allocations
3. File includes Funding Allocation IDs

### Step 2: Find Employee
1. User searches by Staff ID or Employee Name
2. Identifies which allocation(s) to use
3. Notes the Funding Allocation ID(s)

### Step 3: Download Payroll Template
1. User clicks "Download Template"
2. Gets empty payroll import template

### Step 4: Fill Template
1. User enters payroll data
2. Uses Funding Allocation ID from reference
3. For split funding, creates multiple rows

### Step 5: Upload
1. User uploads completed template
2. System creates payroll records
3. Each record linked to correct funding allocation

---

## Instructions Sheet

### Key Points

**Quick Start:**
```
⭐ QUICK START:
Look for the GREEN column (Column A) - that's the "Funding Allocation ID" you need!
Copy this ID to your payroll import template.
```

**Color Coding:**
```
COLOR CODING:
🟢 GREEN COLUMN (A) = Funding Allocation ID - THIS IS WHAT YOU NEED!
🔵 BLUE COLUMNS = Reference information to help you find the right allocation
```

**Split Funding Example:**
```
SPLIT FUNDING EXAMPLE:
Employee EMP001 might have:
  - Allocation ID 5: 60% on Grant A (Allocated: $30,000)
  - Allocation ID 6: 40% on Grant B (Allocated: $20,000)

For payroll, you would create TWO rows:
  Row 1: staff_id=EMP001, employee_funding_allocation_id=5, gross_salary_by_FTE=30000
  Row 2: staff_id=EMP001, employee_funding_allocation_id=6, gross_salary_by_FTE=20000
```

---

## Implementation Details

### Backend

**File:** `app/Http/Controllers/Api/PayrollController.php`

**Method:** `downloadEmployeeFundingAllocationsReference()`

**Key Features:**
- Fetches active allocations with relationships
- Filters by status and end date
- Orders by employee_id
- Applies color coding to Funding Allocation ID column
- Generates comprehensive instructions

### Frontend

**Component:** `src/components/uploads/payroll-upload.vue`

**Added:**
- Reference download button in `additional-downloads` slot
- Green link with info icon
- Loading state during download
- Success/error messages

**Service:** `src/services/upload-payroll.service.js`

**Added:**
- `downloadFundingAllocationsReference()` method
- Blob download handling
- Timestamp in filename

**API Config:** `src/config/api.config.js`

**Added:**
```javascript
EMPLOYEE_FUNDING_ALLOCATIONS_REFERENCE: '/downloads/employee-funding-allocations-reference'
```

---

## Routes

**Backend Route:**
```php
Route::get('/employee-funding-allocations-reference', 
    [PayrollController::class, 'downloadEmployeeFundingAllocationsReference'])
    ->name('downloads.employee-funding-allocations-reference')
    ->middleware('permission:employee_salary.read');
```

**Frontend Endpoint:**
```javascript
API_ENDPOINTS.UPLOAD.EMPLOYEE_FUNDING_ALLOCATIONS_REFERENCE
```

---

## UI Integration

### Payroll Upload Interface

```
📊 Payroll Records Import
   Upload Excel file with monthly payroll records (bulk import)
   Download Template | Download Funding Allocations Reference ℹ️
```

**Visual Features:**
- ✅ Inline layout
- ✅ Separator (|) between links
- ✅ Template = Blue, Reference = Green
- ✅ Info icon with tooltip
- ✅ Loading state
- ✅ Disabled state during download

---

## Benefits

### For Users

✅ **Know Which ID to Use** - Clear reference with all active allocations  
✅ **Handle Split Funding** - See all allocations per employee  
✅ **Verify Active Status** - Only active allocations shown  
✅ **Easy Lookup** - Search by Staff ID or Name  
✅ **Visual Guidance** - Green highlighting shows what to copy  
✅ **Complete Information** - Grant, position, FTE, amount all visible

### For System

✅ **Reduced Errors** - Users use correct allocation IDs  
✅ **Better Data Integrity** - Payroll linked to correct allocations  
✅ **Audit Trail** - Clear connection between payroll and funding  
✅ **Split Funding Support** - Properly handles multiple allocations  
✅ **Consistent Pattern** - Matches Grant Items Reference approach

---

## Color Scheme

| Element | Background | Text | Border | Purpose |
|---------|-----------|------|--------|---------|
| Notice Banner | Red `#FF6B6B` | White | None | Attention |
| Funding Allocation ID Header | Green `#28A745` | White | None | Primary focus |
| Other Headers | Blue `#4472C4` | White | None | Standard info |
| Funding Allocation ID Data | Light Green `#D4EDDA` | Dark Green `#155724` | Green `#28A745` | Easy to scan |
| Other Data | White | Black | None | Supporting info |

---

## Testing

### Manual Testing Checklist

- [ ] Download reference file successfully
- [ ] Notice banner displays correctly
- [ ] Funding Allocation ID column is green and prominent
- [ ] Other columns are blue
- [ ] Data cells have correct formatting
- [ ] Only active allocations shown
- [ ] Split funding employees show multiple rows
- [ ] Instructions sheet is comprehensive
- [ ] File opens in Excel/LibreOffice
- [ ] Colors display correctly

### Test Scenarios

1. **Employee with Single Allocation**
   - Should show one row
   - Funding Allocation ID highlighted

2. **Employee with Split Funding**
   - Should show multiple rows
   - Each allocation clearly identified
   - FTE percentages visible

3. **No Active Allocations**
   - File still generates
   - Shows only headers and instructions

4. **Large Dataset**
   - Performance acceptable
   - All allocations included
   - File size reasonable

---

## Troubleshooting

### Common Issues

**Issue: "No allocations shown"**
- **Cause:** No active allocations in system
- **Solution:** Create funding allocations first

**Issue: "Employee not in reference"**
- **Cause:** Employee has no active allocations
- **Solution:** Check if allocations exist and are active

**Issue: "Multiple IDs for same employee"**
- **Cause:** Split funding (this is normal!)
- **Solution:** Create separate payroll rows for each allocation

**Issue: "Allocation ID not found during import"**
- **Cause:** Using old reference or allocation was deactivated
- **Solution:** Download latest reference before importing

---

## Related Documentation

- [Grant Items Reference Color Coding](./grant-items-reference-color-coding.md)
- [Employee Funding Allocation Upload](./employee-funding-allocation-upload-implementation.md)
- [Payroll Upload/Download Implementation](../routes/payroll-upload-download-implementation.md)

---

## Future Enhancements

### Potential Improvements

1. **Filtering Options**
   - Filter by organization (SMRU/BHF)
   - Filter by grant
   - Filter by employee

2. **Additional Information**
   - Department and position
   - Employment type
   - Salary information

3. **Export Formats**
   - PDF version for printing
   - CSV for data analysis

4. **Search Functionality**
   - Quick search in Excel
   - Pre-filtered sheets by organization

---

## Conclusion

The Employee Funding Allocations Reference export provides users with an easy way to find the Funding Allocation IDs they need for payroll imports. 

**Key Success Factors:**
- 🟢 Green color coding makes IDs obvious
- 📋 Comprehensive reference data
- 🔄 Handles split funding scenarios
- 📝 Clear instructions and examples
- 🎯 Consistent with Grant Items Reference pattern

Users can now confidently create payroll records with the correct funding allocation IDs! 🎉
