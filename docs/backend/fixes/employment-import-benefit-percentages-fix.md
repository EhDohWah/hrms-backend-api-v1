# Employment Import - Benefit Percentages Fix

## Date: January 9, 2026

## Issue

Employment import was failing with database error:
```
SQLSTATE[42S22]: Invalid column name 'health_welfare_percentage'
```

### Root Cause

The import code was trying to insert benefit percentage columns (`health_welfare_percentage`, `pvd_percentage`, `saving_fund_percentage`) that **do not exist** in the `employments` table.

According to the database migration comment:
> "Benefit percentages are now managed globally in benefit_settings table"

The system uses **global benefit percentages** stored in the `benefit_settings` table, not per-employment percentages.

### Additional Issue Found

Even after removing the percentage fields from the import logic, the error persisted because the `Employment` model still had these fields in its `$fillable` and `$casts` arrays. Laravel was attempting to mass-assign these non-existent columns during the insert operation.

## Solution

Removed the percentage fields from the employment import template and logic, keeping only the boolean flags for enabling/disabling benefits.

### Changes Made

#### 1. Import Logic (`EmploymentsImport.php`)

**Removed:**
- Parsing of `health_welfare_percentage`, `pvd_percentage`, `saving_fund_percentage` from Excel rows
- These fields from the `$employmentData` array

**Updated:**
```php
// Before
$healthWelfare = $this->parseBoolean($row['health_welfare'] ?? '0');
$healthWelfarePercentage = $this->parseNumeric($row['health_welfare_percentage'] ?? null);
$isPVD = $this->parseBoolean($row['pvd'] ?? '0');
$pvdPercentage = $this->parseNumeric($row['pvd_percentage'] ?? null);
// ...

// After  
$healthWelfare = $this->parseBoolean($row['health_welfare'] ?? '0');
$isPVD = $this->parseBoolean($row['pvd'] ?? '0');
$isSavingFund = $this->parseBoolean($row['saving_fund'] ?? '0');
```

**Removed from employment data:**
```php
'health_welfare_percentage' => $healthWelfarePercentage,
'pvd_percentage' => $pvdPercentage,
'saving_fund_percentage' => $savingFundPercentage,
```

#### 2. Template Generation (`EmploymentController.php`)

**Removed columns:**
- `health_welfare_percentage`
- `pvd_percentage`
- `saving_fund_percentage`

**Updated headers from 19 to 16 columns:**
```php
// Old: 19 columns
['staff_id', 'employment_type', ..., 'health_welfare', 'health_welfare_percentage', 'pvd', 'pvd_percentage', 'saving_fund', 'saving_fund_percentage', 'status']

// New: 16 columns
['staff_id', 'employment_type', ..., 'health_welfare', 'pvd', 'saving_fund', 'status']
```

**Updated validation rules:**
- Changed descriptions to note that percentages are managed globally
- Example: "Health welfare benefit enabled (default: 0) - Percentages managed globally"

**Updated sample data:**
- Removed percentage values from all 3 sample rows
- Kept only boolean flags (0 or 1)

**Updated instructions:**
```
7. Benefit Percentages:
   - Percentages are managed globally in system settings
   - Only enable/disable benefits using 1 (enabled) or 0 (disabled)
   - Contact administrator to view or modify benefit percentages
```

#### 3. Validation Rules

**Updated from:**
```php
'*.health_welfare' => 'nullable|in:0,1',
'*.health_welfare_percentage' => 'nullable|numeric',
'*.pvd' => 'nullable|in:0,1',
'*.pvd_percentage' => 'nullable|numeric',
'*.saving_fund' => 'nullable|in:0,1',
'*.saving_fund_percentage' => 'nullable|numeric',
```

**To:**
```php
'*.health_welfare' => 'nullable|in:0,1',
'*.pvd' => 'nullable|in:0,1',
'*.saving_fund' => 'nullable|in:0,1',
```

#### 4. Tests Updated

Updated test files to match new 16-column structure:
- `EmploymentTemplateImportTest.php`
- Removed percentage columns from test data
- All 8 tests passing ✅

## Database Structure

### Employments Table
```sql
$table->boolean('health_welfare')->default(false);
$table->boolean('pvd')->default(false);
$table->boolean('saving_fund')->default(false);
// NOTE: Benefit percentages are now managed globally in benefit_settings table
```

### Benefit Settings Table
Global percentages are stored here and applied to all employees who have the benefit enabled.

## Impact

### Breaking Changes
- ⚠️ **Old templates with percentage columns will not work**
- Users must download new template
- Existing Excel files need percentage columns removed

### Benefits
- ✅ Fixes database column error
- ✅ Aligns with system architecture (global percentages)
- ✅ Simpler template (16 columns vs 19)
- ✅ Easier for users (just enable/disable, no percentage entry)
- ✅ Centralized percentage management

## How It Works Now

1. **User fills template:**
   - Sets `health_welfare` to `1` (enabled) or `0` (disabled)
   - Sets `pvd` to `1` (enabled) or `0` (disabled)
   - Sets `saving_fund` to `1` (enabled) or `0` (disabled)
   - **No percentage entry needed**

2. **System applies percentages:**
   - Reads global percentages from `benefit_settings` table
   - Applies to all employees with benefits enabled
   - Administrators manage percentages centrally

3. **Example:**
   - Employee has `pvd = 1` (enabled)
   - System reads global PVD percentage (e.g., 7.5%)
   - Applies 7.5% to this employee's salary

## Testing

### Test Results
```
✓ it can download employment template
✓ it validates template has correct headers
✓ it can import employment with human readable fields
✓ it resolves department name to id
✓ it resolves site code to id
✓ it resolves position title to id
✓ it resolves section department name to id
✓ it handles missing optional fields

Tests: 8 passed (20 assertions)
```

### Manual Testing
1. Download new template
2. Fill with sample data (only boolean flags for benefits)
3. Upload file
4. Verify import succeeds without database errors

## Files Modified

1. **app/Imports/EmploymentsImport.php**
   - Removed percentage parsing
   - Removed percentage fields from employment data array
   - Updated validation rules

2. **app/Http/Controllers/Api/EmploymentController.php**
   - Removed percentage columns from headers
   - Updated validation rule descriptions
   - Updated sample data
   - Updated column widths
   - Updated instructions

3. **app/Models/Employment.php** ⚠️ **CRITICAL FIX**
   - Removed `health_welfare_percentage` from `$fillable` array
   - Removed `pvd_percentage` from `$fillable` array
   - Removed `saving_fund_percentage` from `$fillable` array
   - Removed percentage fields from `$casts` array
   - Added comment explaining percentages are managed globally

4. **tests/Feature/Api/EmploymentTemplateImportTest.php**
   - Updated test data to match new structure
   - All tests passing

## User Communication

### Message to Users
```
IMPORTANT UPDATE: Employment Import Template

The employment import template has been updated to remove benefit percentage columns.

What changed:
- Removed: health_welfare_percentage, pvd_percentage, saving_fund_percentage
- Kept: health_welfare (1/0), pvd (1/0), saving_fund (1/0)

Why:
- Benefit percentages are managed globally by administrators
- Simpler template with fewer columns
- Just enable/disable benefits per employee

Action required:
1. Download the new template
2. Use 1 (enabled) or 0 (disabled) for benefit columns
3. Do not enter percentage values

Questions? Contact your system administrator.
```

## Related Documentation

- `docs/backend/features/employment-template-human-readable-fields.md`
- `docs/backend/features/employment-template-update-summary.md`
- `docs/backend/testing/employment-template-tests.md`

## Conclusion

The fix successfully resolves the database column error by aligning the import logic with the actual database structure. Benefit percentages are now managed globally as intended by the system architecture, providing a simpler user experience and centralized control.

---

**Fixed by**: AI Assistant  
**Date**: January 9, 2026  
**Status**: ✅ Fixed and Tested  
**Tests**: 8/8 Passing
