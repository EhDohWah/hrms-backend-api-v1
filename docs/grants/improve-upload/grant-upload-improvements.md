# Grant Upload Feature - Backend Improvements

## Overview

This document summarizes the improvements, bug fixes, and refactoring done to the Grant Upload/Import feature in the HRMS backend API.

---

## 1. Excel Template Structure Change

### Previous Format (Deprecated)
- Column A contained combined label-value pairs (e.g., "Grant Name: ABC Grant")
- Values were extracted using `str_replace()` parsing

### New Format (Current)
| Row | Column A (Labels) | Column B (Values) |
|-----|-------------------|-------------------|
| 1 | Grant Name | [User Input] |
| 2 | Grant Code | [User Input] |
| 3 | Subsidiary | [Dropdown: SMRU/BHF] |
| 4 | End Date | [Date Picker] |
| 5 | Description | [User Input] |
| 6 | (Spacer) | |
| 7 | Budget Line Code | Position | Salary | Benefit | Level of Effort | Position Number |
| 8 | (Validation Rules) | |
| 9+ | [Data Rows] | |

### File Changed
- `app/Http/Controllers/Api/GrantController.php` - `downloadTemplate()` method

---

## 2. GrantsImport.php - Complete Rewrite

### Location
`app/Imports/GrantsImport.php`

### Key Changes

#### 2.1 Constants for Template Configuration
```php
public const VALID_ORGANIZATIONS = ['SMRU', 'BHF'];
public const GRANT_NAME_MIN_LENGTH = 3;
public const GRANT_NAME_MAX_LENGTH = 255;
public const GRANT_CODE_MAX_LENGTH = 50;
public const DESCRIPTION_MAX_LENGTH = 1000;
public const HEADER_ROW_GRANT_NAME = 1;
public const HEADER_ROW_GRANT_CODE = 2;
public const HEADER_ROW_ORGANIZATION = 3;
public const HEADER_ROW_END_DATE = 4;
public const HEADER_ROW_DESCRIPTION = 5;
public const COLUMN_HEADER_ROW = 7;
public const VALIDATION_RULES_ROW = 8;
public const DATA_START_ROW = 9;  // Changed from 8 to skip validation rules row
```

#### 2.2 Organization Validation with Levenshtein Distance
Fuzzy matching for typo detection:
```php
protected function validateOrganization(string $organization, string $sheetName): array
{
    // Exact match check
    if (in_array($normalizedOrg, GrantsImport::VALID_ORGANIZATIONS)) {
        return ['valid' => true, 'organization' => $normalizedOrg];
    }

    // Levenshtein distance for typo suggestions
    foreach (GrantsImport::VALID_ORGANIZATIONS as $validOrg) {
        $distance = levenshtein($normalizedOrg, $validOrg);
        if ($distance <= 2) {
            return [
                'valid' => false,
                'error' => "Did you mean '$closestMatch'? (Cell B3)"
            ];
        }
    }
}
```

#### 2.3 Two-Pass Item Validation (Atomic Transactions)
**Problem:** Previously, valid items were created while invalid items just added errors - partial imports occurred.

**Solution:** Validate ALL items first, then create only if no errors:
```php
protected function processGrantItems(array $data, Grant $grant, string $sheetName): int
{
    // PASS 1: Validate ALL rows first
    foreach ($rows as $row) {
        $validation = $this->validateGrantItemRow($row, ...);
        if (isset($validation['error'])) {
            $errors[] = $validation['error'];
        } else {
            $validatedItems[] = $validation;
        }
    }

    // If ANY errors, throw exception to rollback entire transaction
    if (!empty($errors)) {
        foreach ($errors as $error) {
            $this->parentImport->addError($error);
        }
        throw new \Exception('Grant items validation failed - no items created');
    }

    // PASS 2: Create all validated items
    foreach ($validatedItems as $item) {
        GrantItem::create([...]);
    }
}
```

#### 2.4 Cell/Row Reference in Error Messages
All error messages include specific cell references:
```
"Sheet 'A2H': Grant name is required (Cell B1)"
"Sheet 'A2H' Row 9: Invalid grant salary format: 'abc'"
"Sheet 'A2H': Invalid organization: 'SMRUU'. Did you mean 'SMRU'? (Cell B3)"
```

#### 2.5 Database Transaction Wrapping
Each sheet is processed in a transaction for atomic operations:
```php
DB::transaction(function () use ($data, $sheetName) {
    // Validate structure
    // Validate header
    // Check if grant exists
    // Create grant
    // Process items (with two-pass validation)
});
```

---

## 3. GrantController.php - Refactoring

### Location
`app/Http/Controllers/Api/GrantController.php`

### Changes

#### 3.1 Updated `upload()` Method
Now uses `GrantsImport` class instead of inline logic:
```php
public function upload(Request $request)
{
    $grantsImport = new \App\Imports\GrantsImport($importId, $userId);

    $spreadsheet = IOFactory::load($file->getRealPath());
    $sheets = $spreadsheet->getAllSheets();

    $sheetImport = new \App\Imports\GrantSheetImport($grantsImport);

    foreach ($sheets as $sheet) {
        $sheetImport->processSheet($sheet);
    }

    // Get results from import class
    $processedGrants = $grantsImport->getProcessedGrants();
    $processedItems = $grantsImport->getProcessedItems();
    $errors = $grantsImport->getErrors();
}
```

#### 3.2 Removed Duplicate Methods
The following methods were removed from GrantController (now handled by GrantsImport):
- `createGrant()` - 88 lines
- `validateOrganization()` - 60 lines
- `processGrantItems()` - 91 lines
- `toFloat()` - 14 lines

**Total:** ~253 lines removed, improving maintainability.

---

## 4. Bug Fixes

### 4.1 Row 8 Being Processed as Data
**Problem:** Row 8 (validation rules/instructions) was being processed as actual data.

**Fix:** Changed `DATA_START_ROW` from 8 to 9:
```php
public const VALIDATION_RULES_ROW = 8;
public const DATA_START_ROW = 9;
```

### 4.2 Mixed Success/Error Messages
**Problem:** Notification showed "Processed: 1 grants, 1 items, Errors: 1" - confusing partial success.

**Fix:** Two-pass validation ensures either all items succeed or entire sheet fails:
- Success: "Grant import finished! Processed: 1 grants, 5 grant items"
- Failure: "Grant import finished! Errors: 3" (nothing created)

### 4.3 Column A Labels Being Used as Values
**Problem:** Import was reading labels from Column A instead of values from Column B.

**Root Cause:** `GrantController::upload()` was not using `GrantsImport` class; had its own inline logic using the old format.

**Fix:** Updated controller to use `GrantsImport` class which reads from Column B.

---

## 5. Validation Rules Summary

### Grant Header Validation
| Field | Required | Validation |
|-------|----------|------------|
| Grant Name | Yes | Min 3 chars, Max 255 chars |
| Grant Code | Yes | Alphanumeric + `.`, `-`, `_` only, Max 50 chars |
| Subsidiary | Yes | Must be "SMRU" or "BHF" (fuzzy match for typos) |
| End Date | No | Valid date format, warns if past or >10 years future |
| Description | No | Max 1000 chars |

### Grant Item Validation
| Field | Required | Validation |
|-------|----------|------------|
| Position | Yes | Min 2 chars, Max 255 chars |
| Budget Line Code | No | Max 50 chars |
| Salary | No | 0 - 99,999,999.99 |
| Benefit | No | 0 - 99,999,999.99 |
| Level of Effort | No | 0-100% (accepts "75", "75%", "0.75") |
| Position Number | No | Integer 1-1000, defaults to 1 |

---

## 6. API Response Format

### Success Response
```json
{
    "success": true,
    "message": "Grant data import completed",
    "data": {
        "processed_grants": 2,
        "processed_items": 10
    }
}
```

### Partial Success (with skipped grants)
```json
{
    "success": true,
    "message": "Grant data import completed with skipped grants",
    "data": {
        "processed_grants": 1,
        "processed_items": 5,
        "skipped_grants": ["GRANT-001"]
    }
}
```

### Validation Errors
```json
{
    "success": true,
    "message": "Grant data import completed with errors",
    "data": {
        "processed_grants": 0,
        "processed_items": 0,
        "errors": [
            "Sheet 'A2H': Grant name is required (Cell B1)",
            "Sheet 'A2H' Row 9: Invalid grant salary format: 'abc'"
        ]
    }
}
```

---

## 7. Files Modified

| File | Changes |
|------|---------|
| `app/Imports/GrantsImport.php` | Complete rewrite with new format, validation, transactions |
| `app/Http/Controllers/Api/GrantController.php` | Updated upload(), removed duplicate methods |

---

## 8. Testing Checklist

- [ ] Upload valid Excel with single sheet
- [ ] Upload valid Excel with multiple sheets
- [ ] Upload with invalid organization (verify typo suggestion)
- [ ] Upload with missing required fields
- [ ] Upload with invalid data types (text in salary field)
- [ ] Upload with duplicate grant code (verify skip behavior)
- [ ] Upload with duplicate item (same position + budget line)
- [ ] Verify transaction rollback on item validation failure
- [ ] Verify notification shows proper message
