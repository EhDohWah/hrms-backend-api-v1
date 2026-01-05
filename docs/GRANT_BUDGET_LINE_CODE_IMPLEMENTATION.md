# Grant Budget Line Code Implementation

## Overview

This document explains the budget line code handling for grant imports, including support for General Fund (hub grants) which don't have budget line codes.

## Implementation Date

December 8, 2025

---

## Grant Types

### 1. Project Grants (WITH Budget Line Codes)
- Examples: Health Initiative, Education Program, Research Projects
- **Have budget line codes**: `1.2.2.1`, `1.2.1.2`, `BL-001`, `A.B.C`, etc.
- Budget line codes can be **any format** - no restrictions
- Used for project-specific funding with specific budget tracking

### 2. General Fund / Hub Grants (WITHOUT Budget Line Codes)
- Examples: SMRU Other Fund (S0031), BHF General Fund (S22001)
- **NO budget line codes**: Column A is empty
- Used for organizational employee salaries
- Organization's saving/reserve grant for staff payments

---

## Budget Line Code Rules

### Format Flexibility
✅ **Accepted Formats** (Any string):
- Hierarchical: `1.2.2.1`, `1.2.1.2`, `1.1.2.8`
- Alphanumeric: `BL001`, `BL-002`, `BG_003`
- Mixed: `A.1.B.2`, `CODE-123-XYZ`
- Special characters: `.` (dot), `-` (dash), `_` (underscore)
- **NULL**: Empty for General Fund

❌ **No Restrictions**: Budget line codes accept any string value (max 255 characters)

### Nullability
- **Optional**: Budget line code can be NULL
- **General Fund**: Always NULL
- **Project Grants**: Should have budget line codes

---

## Database Schema Changes

### 1. Grant Items Table Migration

**File**: `database/migrations/2025_02_13_025154_create_grant_items_table.php`

#### Before:
```php
$table->string('budgetline_code')->nullable();
$table->unique(['grant_id', 'grant_position', 'budgetline_code'], 'unique_grant_position_budgetline');
```

#### After:
```php
// Can be NULL for General Fund (hub grants) which don't have budget line codes
// Can be any format: 1.2.2.1, BL-001, A.B.C, etc. - no restrictions
$table->string('budgetline_code', 255)->nullable();

// Note: No unique constraint on budgetline_code + position
// General Fund items have NULL budget line codes, so duplicates would occur
// Uniqueness is enforced at application level for items WITH budget line codes
```

**Key Changes**:
1. ✅ Already nullable - no change needed
2. ❌ Removed unique constraint (allows NULL duplicates)
3. 📝 Added comments explaining General Fund handling
4. 🔢 Specified length: 255 characters

### 2. Grants Table Migration

**File**: `database/migrations/2025_02_13_025153_create_grants_table.php`

#### Before:
```php
$this->insertDefaultGrants(); // Created S0031 and S22001
```

#### After:
```php
// Removed: Default hub grants are now imported via Excel
// Previously created: S0031 (SMRU Other Fund) and S22001 (BHF General Fund)
// These hub grants (General Fund/Organization Saving Grants) should be imported from Excel
```

**Reason**: All grants, including hub grants, are now imported from Excel files.

---

## Import Logic Changes

### Duplicate Detection Logic

#### For Items WITH Budget Line Codes:
```php
// Check if this combination already exists
$existingItem = GrantItem::where('grant_id', $grant->id)
    ->where('grant_position', $grantPosition)
    ->where('budgetline_code', $bgLineCode)
    ->first();
```

#### For Items WITHOUT Budget Line Codes (General Fund):
```php
// NO duplicate check - multiple items with same position allowed
// General Fund can have: Manager, Manager, Field Officer, Field Officer, etc.
```

### Updated Import Methods

**Files Modified**:
1. `app/Imports/GrantsImport.php` - Line 265-285
2. `app/Http/Controllers/Api/GrantController.php` - Line 550-575

**Key Changes**:
```php
// 1. Accept empty budget line codes
$bgLineCode = $bgLineCode !== '' ? $bgLineCode : null;

// 2. Create unique key with NULL handling
$itemKey = $grant->id.'|'.$grantPosition.'|'.($bgLineCode ?? 'NULL_'.uniqid());

// 3. Only check duplicates if budget line code exists
if ($bgLineCode !== null) {
    // Check for duplicates
}
```

---

## Excel File Examples

### Example 1: Project Grant (WITH Budget Line Codes)

**Sheet**: "Health Initiative"

```
Row 1: Grant name - Health Initiative Grant
Row 2: Grant code - GR-2024-001
Row 3: Subsidiary - SMRU
Row 4: End date - 2024-12-31
Row 5: Description - Health programs
Row 6: (empty)
Row 7: 1.2.2.1 | Capacity Building Officer | 61,800 | 10,841 | 100% | 2
Row 8: 1.2.1.2 | CE Field Coordinator       | 42,436 | 7,774  | 100% | 1
Row 9: 1.1.2.8 | Cleaner                    | 31,827 | 6,096  | 100% | 1
```

**Column A**: Budget line codes present (`1.2.2.1`, `1.2.1.2`, etc.)

### Example 2: General Fund (WITHOUT Budget Line Codes)

**Sheet**: "BHF General Fund"

```
Row 1: Grant name - General Fund
Row 2: Grant code - S22001
Row 3: Subsidiary - BHF
Row 4: End date - 
Row 5: Description - BHF's hub grant
Row 6: (empty)
Row 7: (empty) | Project Manager  | 75,000 | 15,000 | 100% | 2
Row 8: (empty) | Field Officer    | 45,000 | 9,000  | 100% | 3
Row 9: (empty) | Administrative   | 40,000 | 8,000  | 100% | 1
```

**Column A**: Empty (no budget line codes)

### Example 3: Mixed Format Budget Codes

**Sheet**: "Education Program"

```
Row 1: Grant name - Education Program
Row 2: Grant code - EDU-2024
Row 3: Subsidiary - SMRU
Row 4: End date - 2025-06-30
Row 5: Description - Educational support
Row 6: (empty)
Row 7: BL-001     | Coordinator | 55,000 | 11,000 | 100% | 1
Row 8: EDU.A.1    | Teacher     | 40,000 | 8,000  | 75%  | 5
Row 9: CODE_123   | Assistant   | 30,000 | 6,000  | 50%  | 2
```

**Column A**: Various formats accepted

---

## Validation Rules

### Grant Item Validation

| Rule | Project Grants | General Fund |
|------|----------------|--------------|
| **Position Required** | ✅ Yes | ✅ Yes |
| **Budget Line Code** | ✅ Should exist | ⚪ Can be NULL |
| **Duplicate Check** | ✅ Position + Budget Code | ❌ No check (allows duplicates) |
| **Unique Constraint** | ✅ Enforced | ❌ Not enforced |

### Budget Line Code Validation

```php
// Accept any value or NULL
$bgLineCode = trim($row['A'] ?? '');
$bgLineCode = $bgLineCode !== '' ? $bgLineCode : null;

// Examples of valid values:
// "1.2.2.1"     ✅
// "BL-001"      ✅
// "A.B.C"       ✅
// ""            ✅ (becomes NULL)
// NULL          ✅
```

---

## Use Cases

### Use Case 1: Import Project Grant
```
Grant: Health Initiative (GR-2024-001)
Items:
  - 1.2.2.1 | Capacity Building Officer | 61,800 | 10,841 | 100% | 2
  - 1.2.1.2 | CE Field Coordinator       | 42,436 | 7,774  | 100% | 1

Result: ✅ 2 grant items created with budget line codes
```

### Use Case 2: Import General Fund
```
Grant: BHF General Fund (S22001)
Items:
  - (empty) | Manager        | 75,000 | 15,000 | 100% | 2
  - (empty) | Field Officer  | 45,000 | 9,000  | 100% | 3
  - (empty) | Manager        | 75,000 | 15,000 | 100% | 1  ← Duplicate position OK

Result: ✅ 3 grant items created without budget line codes (NULL)
```

### Use Case 3: Mixed Grant (Some with, some without)
```
Grant: Mixed Project (MIX-001)
Items:
  - 1.2.1   | Coordinator | 55,000 | 11,000 | 100% | 1
  - (empty) | Support     | 30,000 | 6,000  | 50%  | 2
  - 1.2.2   | Manager     | 60,000 | 12,000 | 75%  | 1

Result: ✅ 3 grant items created (2 with codes, 1 without)
```

---

## Database Queries

### Query Items WITH Budget Line Codes
```sql
SELECT * FROM grant_items 
WHERE grant_id = 1 
AND budgetline_code IS NOT NULL;
```

### Query Items WITHOUT Budget Line Codes (General Fund)
```sql
SELECT * FROM grant_items 
WHERE grant_id = 2 
AND budgetline_code IS NULL;
```

### Find Duplicate Check (Only for items with budget codes)
```php
GrantItem::where('grant_id', $grantId)
    ->where('grant_position', $position)
    ->where('budgetline_code', $code) // Not checking NULL values
    ->exists();
```

---

## API Response Examples

### Success - Project Grant
```json
{
    "success": true,
    "message": "Grant data import completed",
    "data": {
        "processed_grants": 1,
        "processed_items": 6,
        "warnings": []
    }
}
```

### Success - General Fund
```json
{
    "success": true,
    "message": "Grant data import completed",
    "data": {
        "processed_grants": 1,
        "processed_items": 10,
        "warnings": []
    }
}
```

**Note**: No warnings for NULL budget line codes - this is expected behavior.

---

## Employee Funding Allocation Impact

### How It Works

When an employee is allocated to grant items:

#### Project Grant Item (WITH Budget Line Code):
```php
EmployeeFundingAllocation::create([
    'employee_id' => 123,
    'grant_item_id' => 456,
    'allocated_percentage' => 50.00,
]);

// Related grant_item has:
// - budgetline_code: "1.2.2.1"
// - Salary tracked against specific budget line
```

#### General Fund Item (WITHOUT Budget Line Code):
```php
EmployeeFundingAllocation::create([
    'employee_id' => 123,
    'grant_item_id' => 789,
    'allocated_percentage' => 50.00,
]);

// Related grant_item has:
// - budgetline_code: NULL
// - Salary from general organizational funds
```

### Mixed Allocation Example
```
Employee: John Doe
Allocations:
  - 50% from "1.2.2.1" (Project Grant - Health Initiative)
  - 50% from NULL (General Fund - BHF)

Total: 100% funding
```

---

## Migration Impact

### Fresh Installation
1. Run migrations as normal
2. No default grants created
3. Import all grants from Excel (including General Fund)

### Existing Database
If you already have the default hub grants (S0031, S22001):

**Option A - Keep them, import items:**
- Grants already exist
- Import will skip grant creation (already exists)
- Will import grant items only

**Option B - Delete and re-import:**
```sql
-- Delete existing hub grants and their items
DELETE FROM grant_items WHERE grant_id IN (
    SELECT id FROM grants WHERE code IN ('S0031', 'S22001')
);
DELETE FROM grants WHERE code IN ('S0031', 'S22001');

-- Then import from Excel
```

---

## Testing

### Test Case 1: Import General Fund
**Excel**:
```
Sheet: "BHF General Fund"
Row 1: Grant name - General Fund
Row 2: Grant code - S22001
Row 3: Subsidiary - BHF
Row 4: End date -
Row 5: Description - BHF's hub grant
Row 6: (empty)
Row 7: (empty) | Manager | 75000 | 15000 | 100 | 2
Row 8: (empty) | Staff   | 40000 | 8000  | 100 | 3
```

**Expected Result**:
```
✅ Grant created: S22001 - General Fund
✅ 2 items created with budgetline_code = NULL
✅ No duplicate errors
✅ No warnings
```

### Test Case 2: Import Project Grant
**Excel**:
```
Sheet: "Health Initiative"
Row 1: Grant name - Health Initiative
Row 2: Grant code - GR-2024-001
Row 3: Subsidiary - SMRU
Row 4: End date - 2024-12-31
Row 5: Description - Health programs
Row 6: (empty)
Row 7: 1.2.2.1 | Capacity Building Officer | 61800 | 10841 | 100 | 2
Row 8: 1.2.1.2 | CE Field Coordinator       | 42436 | 7774  | 100 | 1
```

**Expected Result**:
```
✅ Grant created: GR-2024-001
✅ 2 items created with budget line codes
✅ Duplicate check enforced
✅ No warnings
```

### Test Case 3: Duplicate Budget Line in Project Grant (Should Fail)
**Excel**:
```
Row 7: 1.2.2.1 | Manager | 75000 | 15000 | 100 | 2
Row 8: 1.2.2.1 | Manager | 75000 | 15000 | 100 | 1  ← Duplicate!
```

**Expected Result**:
```
⚠️ Warning: "Sheet 'HealthGrant' row 8: Duplicate grant item - Position 'Manager' with budget line code '1.2.2.1' already exists for this grant"
✅ First item created
❌ Second item skipped
```

### Test Case 4: Duplicate Position in General Fund (Should Succeed)
**Excel**:
```
Row 7: (empty) | Manager | 75000 | 15000 | 100 | 2
Row 8: (empty) | Manager | 60000 | 12000 | 75  | 1  ← Same position, OK!
```

**Expected Result**:
```
✅ First Manager item created (budgetline_code = NULL)
✅ Second Manager item created (budgetline_code = NULL)
✅ No duplicate warning (NULL codes allowed)
```

---

## Code Implementation Details

### GrantsImport.php (Lines 265-295)

```php
$grantPosition = trim($row['B'] ?? '');
$bgLineCode = trim($row['A'] ?? '');

// Skip empty rows
if (empty($grantPosition)) {
    continue;
}

// Budget Line Code can be empty for General Fund (hub grants)
// Accept any format: 1.2.2.1, BL-001, A.B.C, etc.
$bgLineCode = $bgLineCode !== '' ? $bgLineCode : null;

// Create unique key - handle NULL budget line codes
$itemKey = $grant->id.'|'.$grantPosition.'|'.($bgLineCode ?? 'NULL_'.uniqid());

if (! isset($createdGrantItems[$itemKey])) {
    // Check for duplicates ONLY if budget line code exists
    // General Fund items (NULL budget line) can have duplicate positions
    if ($bgLineCode !== null) {
        $existingItem = GrantItem::where('grant_id', $grant->id)
            ->where('grant_position', $grantPosition)
            ->where('budgetline_code', $bgLineCode)
            ->first();

        if ($existingItem) {
            // Duplicate found - skip this item
            $this->parentImport->addError("...");
            continue;
        }
    }
    
    // Create the grant item
    $grantItem = GrantItem::create([
        'grant_id' => $grant->id,
        'grant_position' => $grantPosition,
        'grant_salary' => ...,
        'grant_benefit' => ...,
        'grant_level_of_effort' => ...,
        'grant_position_number' => ...,
        'budgetline_code' => $bgLineCode, // Can be NULL
        'created_by' => auth()->user()->name ?? 'system',
        'updated_by' => auth()->user()->name ?? 'system',
    ]);
}
```

---

## Excel Column Structure

| Column | Field | Required | Format | Example |
|--------|-------|----------|--------|---------|
| A | Budget Line Code | ⚪ Optional | Any string or empty | `1.2.2.1` or empty |
| B | Position | ✅ Required | String | `Project Manager` |
| C | Salary | ⚪ Optional | Numeric | `75000` |
| D | Benefit | ⚪ Optional | Numeric | `15000` |
| E | LOE (%) | ⚪ Optional | Numeric | `100` or `100%` |
| F | Manpower | ⚪ Optional | Integer | `2` |

---

## Reporting & Analytics

### Grant Items by Type

**With Budget Line Codes** (Project Grants):
```sql
SELECT 
    g.code as grant_code,
    g.name as grant_name,
    gi.budgetline_code,
    gi.grant_position,
    gi.grant_salary
FROM grant_items gi
JOIN grants g ON gi.grant_id = g.id
WHERE gi.budgetline_code IS NOT NULL
ORDER BY g.code, gi.budgetline_code;
```

**Without Budget Line Codes** (General Fund):
```sql
SELECT 
    g.code as grant_code,
    g.name as grant_name,
    gi.grant_position,
    gi.grant_salary
FROM grant_items gi
JOIN grants g ON gi.grant_id = g.id
WHERE gi.budgetline_code IS NULL
ORDER BY g.code, gi.grant_position;
```

---

## Best Practices

### For Data Entry

1. **Project Grants**: Always include budget line codes
2. **General Fund**: Leave Column A empty
3. **Budget Line Format**: Use your organization's standard format
4. **Consistency**: Use same format within a grant

### For Developers

1. **NULL Checks**: Always check if `budgetline_code` is NULL before queries
2. **Uniqueness**: Only enforce for non-NULL codes
3. **Display**: Show "N/A" or "General Fund" for NULL codes in UI
4. **Filtering**: Provide separate filters for hub vs project grants

---

## Migration Steps

### For New Deployment

1. Run migrations:
   ```bash
   php artisan migrate
   ```

2. Import General Fund from Excel:
   ```
   POST /api/grants/upload
   file: general_fund.xlsx
   ```

3. Import Project Grants from Excel:
   ```
   POST /api/grants/upload
   file: project_grants.xlsx
   ```

### For Existing Database

1. Remove unique constraint:
   ```sql
   ALTER TABLE grant_items DROP INDEX unique_grant_position_budgetline;
   ```

2. Delete default hub grants (if needed):
   ```sql
   DELETE FROM grant_items WHERE grant_id IN (
       SELECT id FROM grants WHERE code IN ('S0031', 'S22001')
   );
   DELETE FROM grants WHERE code IN ('S0031', 'S22001');
   ```

3. Import all grants from Excel

---

## Summary

### Key Changes

✅ **Budget Line Code**: Now optional (nullable)  
✅ **Format**: Any string format accepted  
✅ **General Fund**: Works without budget codes  
✅ **Project Grants**: Work with any budget code format  
✅ **Duplicate Check**: Only for items WITH budget codes  
✅ **Default Grants**: Removed from migration  

### Files Modified

1. `database/migrations/2025_02_13_025154_create_grant_items_table.php`
2. `database/migrations/2025_02_13_025153_create_grants_table.php`
3. `app/Imports/GrantsImport.php`
4. `app/Http/Controllers/Api/GrantController.php`

---

## Next Steps

1. ✅ Run migrations (fresh or rollback/migrate)
2. ✅ Import General Fund from Excel
3. ✅ Import Project Grants from Excel
4. ✅ Verify data in database
5. ✅ Test employee funding allocations

The system now fully supports both hub grants (without budget codes) and project grants (with flexible budget code formats)! 🎉
