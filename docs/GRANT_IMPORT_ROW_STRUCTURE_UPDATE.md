# Grant Import Row Structure Update

## Date
December 8, 2025

## Summary
Updated the Grant Import processing logic to start data processing from **row 7** instead of row 8, removing the dedicated column header row.

---

## Changes Made

### 1. **GrantsImport.php**
**File**: `app/Imports/GrantsImport.php`

**Changed** (Line 259-260):
```php
// OLD:
// Skip header rows (1-7) and start processing from row 8
$headerRowsCount = 7;

// NEW:
// Skip header rows (1-6) and start processing from row 7
$headerRowsCount = 6;
```

### 2. **GrantController.php**
**File**: `app/Http/Controllers/Api/GrantController.php`

**Changed** (Line 544-545):
```php
// OLD:
// Skip header rows (1-7) and start processing from row 8
$headerRowsCount = 7;

// NEW:
// Skip header rows (1-6) and start processing from row 7
$headerRowsCount = 6;
```

### 3. **Documentation**
**File**: `docs/GRANT_IMPORT_EXCEL_FORMAT.md`

- Updated all references from "row 8" to "row 7"
- Removed "Row 7: Column Headers" section
- Updated all examples to show data starting from row 7

### 4. **Template Generator**
**File**: `data_entry/grant/create_grant_template.py`

- Removed column header row (row 7)
- Data now starts from row 7
- Updated freeze panes from `A8` to `A7`
- Updated instructions in column H

---

## New Excel Structure

### Before (Old Format)
```
Row 1: Grant name - Health Initiative Grant
Row 2: Grant code - GR-2024-001
Row 3: Subsidiary - SMRU
Row 4: End date - 2024-12-31
Row 5: Description - Funding for health initiatives
Row 6: (empty)
Row 7: Budget Line | Position | Salary | Benefit | LOE (%) | Manpower  ← REMOVED
Row 8: BL001 | Project Manager | 75000 | 15000 | 75 | 2
Row 9: BL002 | Researcher | 60000 | 12000 | 100 | 3
```

### After (New Format)
```
Row 1: Grant name - Health Initiative Grant
Row 2: Grant code - GR-2024-001
Row 3: Subsidiary - SMRU
Row 4: End date - 2024-12-31
Row 5: Description - Funding for health initiatives
Row 6: (empty)
Row 7: BL001 | Project Manager | 75000 | 15000 | 75 | 2  ← DATA STARTS HERE
Row 8: BL002 | Researcher | 60000 | 12000 | 100 | 3
```

---

## Column Structure (Unchanged)

Data rows still follow the same column structure:

| Column | Field | Description |
|--------|-------|-------------|
| A | Budget Line Code | Required |
| B | Position | Required |
| C | Salary | Optional |
| D | Benefit | Optional |
| E | LOE (%) | Optional |
| F | Manpower | Optional |

---

## Impact

### ✅ What Changed
- Data processing starts from row 7 instead of row 8
- No dedicated column header row
- Template generator updated
- Documentation updated

### ⚠️ Backward Compatibility
- **Old Excel files with column headers in row 7 will need to be updated**
- Row 7 will now be treated as data, not as headers
- Users need to remove the header row from existing templates

### 📝 Migration Required
If users have existing Excel files with the old format:
1. Delete row 7 (column headers)
2. Data will automatically shift up to row 7
3. File is now compatible with new format

---

## Testing

### Test Case 1: Valid Import
```
Row 1: Grant name - Test Grant
Row 2: Grant code - GR-TEST-001
Row 3: Subsidiary - SMRU
Row 4: End date - 2025-12-31
Row 5: Description - Test grant
Row 6: (empty)
Row 7: BL001 | Manager | 50000 | 10000 | 100 | 1
```

**Expected**: Grant created with 1 item

### Test Case 2: Empty Row 7
```
Row 1: Grant name - Test Grant
Row 2: Grant code - GR-TEST-002
Row 3: Subsidiary - SMRU
Row 4: End date -
Row 5: Description -
Row 6: (empty)
Row 7: (empty) ← No data
```

**Expected**: Grant created with 0 items

### Test Case 3: Multiple Items
```
Row 1: Grant name - Test Grant
Row 2: Grant code - GR-TEST-003
Row 3: Subsidiary - SMRU
Row 4: End date -
Row 5: Description -
Row 6: (empty)
Row 7: BL001 | Manager | 50000 | 10000 | 100 | 1
Row 8: BL002 | Staff | 30000 | 6000 | 75 | 2
Row 9: BL003 | Driver | 20000 | 4000 | 50 | 1
```

**Expected**: Grant created with 3 items

---

## Files Modified

1. `app/Imports/GrantsImport.php`
2. `app/Http/Controllers/Api/GrantController.php`
3. `docs/GRANT_IMPORT_EXCEL_FORMAT.md`
4. `data_entry/grant/create_grant_template.py`

---

## Validation

✅ Code formatted with Pint  
✅ No linter errors  
✅ Documentation updated  
✅ Template generator updated  
✅ Consistent across all files  

---

## Reason for Change

This change simplifies the Excel structure by removing the redundant column header row, making it:
- **Simpler**: Users don't need to maintain a header row
- **Cleaner**: Less confusion about what row to start data on
- **Consistent**: Follows a clearer pattern of header info (rows 1-5), spacer (row 6), then data (row 7+)

---

## Next Steps

1. **Regenerate template**: Run `create_grant_template.py` to generate new template files
2. **Update existing files**: Users should remove row 7 from existing Excel files
3. **Test import**: Upload a file with the new format to verify
4. **Notify users**: Inform users of the structure change if they have existing templates

---

## Summary

The grant import now processes data starting from **row 7** instead of row 8. The column header row has been removed, simplifying the Excel structure. All related files and documentation have been updated to reflect this change.

