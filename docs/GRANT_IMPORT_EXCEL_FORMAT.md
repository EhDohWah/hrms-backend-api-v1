# Grant Import Excel Format Documentation

## Overview

The Grant Import feature allows you to bulk upload grants and their associated grant items using an Excel file (`.xlsx`, `.xls`). **Each sheet in the Excel file represents one grant** with its grant items. The import is processed synchronously with comprehensive error handling.

## Import Process

1. **Upload**: Submit the Excel file via the `/api/grants/upload` endpoint
2. **Validation**: File is validated for format and size (max 10MB)
3. **Sheet Processing**: Each sheet is processed sequentially as one grant
4. **Grant Creation**: Grant header information is extracted from first 5 rows
5. **Items Creation**: Grant items are created from row 7 onwards
6. **Response**: Immediate response with processing results
7. **Error Handling**: Duplicate checking, validation errors, and detailed error messages per sheet

## Excel File Structure

### Multi-Sheet Format

**Important**: Each sheet represents ONE grant. You can have multiple sheets in one Excel file, and each will create a separate grant.

### Sheet Structure

Each sheet must follow this structure:

#### Header Rows (Rows 1-6)

| Row | Column A | Description | Required | Example |
|-----|----------|-------------|----------|---------|
| 1 | `Grant name - [name]` | Grant name | **Yes** | `Grant name - Health Initiative Grant` |
| 2 | `Grant code - [code]` | Unique grant code | **Yes** | `Grant code - GR-2024-001` |
| 3 | `Subsidiary - [org]` | Organization name | No | `Subsidiary - SMRU` |
| 4 | `End date - [date]` | Grant end date | No | `End date - 2024-12-31` |
| 5 | `Description - [text]` | Grant description | No | `Description - Funding for health initiatives` |
| 6 | (Empty row) | Spacer | - | - |

#### Data Rows (Row 9 onwards)

**Important:** Row 7 contains column headers, Row 8 contains validation rules (for reference only). Actual data starts from Row 9.

| Column | Field | Type | Required | Description | Example |
|--------|-------|------|----------|-------------|---------|
| A | `budgetline_code` | String (255) | **Yes** | Budget line code | `BL001` |
| B | `grant_position` | String (255) | **Yes** | Position title | `Project Manager` |
| C | `grant_salary` | Numeric | No | Monthly salary amount | `75000` |
| D | `grant_benefit` | Numeric | No | Monthly benefit amount | `15000` |
| E | `grant_level_of_effort` | Numeric | No | Level of effort (0-100%) | `75` or `75%` |
| F | `grant_position_number` | Integer | No | Number of positions | `2` |

## Excel Format Examples

### Example: Complete Excel File with Multiple Grants

**File**: `grants_import.xlsx`

#### Sheet 1: "Health Initiative"

```
Row 1: Grant name - Health Initiative Grant
Row 2: Grant code - GR-2024-001
Row 3: Subsidiary - SMRU
Row 4: End date - 2024-12-31
Row 5: Description - Funding for health initiatives in rural areas
Row 6: (empty)
Row 7: BL001 | Project Manager   | 75000 | 15000 | 75  | 2
Row 8: BL002 | Senior Researcher | 60000 | 12000 | 100 | 3
Row 9: BL003 | Field Officer     | 45000 | 9000  | 50  | 1
```

#### Sheet 2: "Education Program"

```
Row 1: Grant name - Education Program
Row 2: Grant code - GR-2024-002
Row 3: Subsidiary - BHF
Row 4: End date - 2025-06-30
Row 5: Description - Educational support and training
Row 6: (empty)
Row 7: BL101 | Coordinator | 55000 | 11000 | 100 | 1
Row 8: BL102 | Teacher     | 40000 | 8000  | 75  | 5
Row 9: BL103 | Assistant   | 30000 | 6000  | 50  | 2
```

#### Sheet 3: "Research Grant"

```
Row 1: Grant name - Research Grant
Row 2: Grant code - GR-2024-003
Row 3:  Subsidiary - SMRU
Row 4:  End date - 2025-12-31
Row 5:  Description - Basic research funding
Row 6:  (empty)
Row 7:  Budget Line | Position         | Salary | Benefit | LOE (%) | Manpower
(No data rows - grant without items)
```

## Field Details

### Date Fields
- **Format**: Accepts various date formats including Excel date serial numbers
- **Examples**: `2024-12-31`, `31/12/2024`, `December 31, 2024`
- **Nullable**: Yes (end_date)

### Numeric Fields
- **grant_salary**: Decimal number (e.g., `75000`, `75000.50`)
- **grant_benefit**: Decimal number (e.g., `15000`, `15000.00`)
- **grant_level_of_effort**: 
  - Can be decimal (0-1): `0.75` = 75%
  - Can be percentage: `75%` = 75%
  - Both formats are accepted and converted
- **grant_position_number**: Integer (e.g., `1`, `2`, `5`)

## Validation Rules

### Grant Level Validation
1. **Duplicate Grant Code**: Grant codes must be unique across the database
2. **Required Fields**: `grant_code`, `grant_name`, and `organization` are required
3. **Character Limits**: 
   - grant_code: max 255 characters
   - grant_name: max 255 characters
   - organization: max 255 characters

### Grant Item Level Validation
1. **Duplicate Grant Items**: The combination of `grant_position` + `budgetline_code` must be unique within each grant
2. **Numeric Validation**: Salary, benefit, and level of effort must be valid numbers
3. **Position Number**: Must be a positive integer (minimum 1)

### File Validation
1. **File Type**: Must be Excel format (`.xlsx`, `.xls`, or `.csv`)
2. **File Size**: Maximum 10MB
3. **Structure**: Must have a header row with column names

## Import Behavior

### Duplicate Handling
- **Duplicate Grant Code**: If a grant code already exists in the database, the entire row is skipped
- **Duplicate in File**: If the same grant code appears multiple times in the import file with different data, only the first occurrence is imported
- **Duplicate Grant Items**: If a grant item with the same position and budget line code exists for a grant, it is skipped

### Error Reporting
Errors are tracked and reported in several ways:
1. **Validation Errors**: Shown with row number and field name
2. **Duplicate Errors**: Detailed message indicating what duplicate was found
3. **Import Summary**: Final notification includes:
   - Number of grants processed
   - Number of grant items processed
   - Number of errors
   - Number of validation failures

### Success Scenarios
1. **New Grant Only**: Grant is created without grant items
2. **New Grant with Items**: Grant and all valid grant items are created
3. **Partial Success**: Valid grants/items are imported, invalid ones are skipped with errors

## API Usage

### Endpoint
```
POST /api/grants/upload
```

### Request
```http
POST /api/grants/upload
Content-Type: multipart/form-data
Authorization: Bearer {token}

file: [Excel file]
```

### Response (Success)
```json
{
  "success": true,
  "message": "Grant data import completed",
  "data": {
    "processed_grants": 2,
    "processed_items": 15,
    "warnings": [
      "Sheet 'Grant2' row 10: Duplicate grant item - Position 'Manager' with budget line code 'BL001' already exists for this grant"
    ],
    "skipped_grants": []
  }
}
```

### Response (Validation Error)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "file": ["The file field is required."]
  }
}
```

## Best Practices

1. **Test with Small Files First**: Test with 5-10 records before importing large datasets
2. **Unique Grant Codes**: Ensure all grant codes are unique
3. **Check Duplicates**: Verify no duplicate position + budget line combinations within each grant
4. **Date Format**: Use consistent date format (YYYY-MM-DD recommended)
5. **Backup**: Always keep a backup of your original data
6. **Review Template**: Use the provided template to ensure correct column names
7. **Monitor Notifications**: Check notifications for import completion and error details

## Troubleshooting

### Common Issues

**Issue**: "Grant code already exists in database"
- **Solution**: Check if the grant was previously imported. Use unique grant codes.

**Issue**: "Duplicate grant item"
- **Solution**: Ensure position + budget line code combination is unique within each grant.

**Issue**: "Missing required field"
- **Solution**: Verify grant_code, grant_name, and organization are filled for all rows.

**Issue**: "Invalid date format"
- **Solution**: Use standard date format (YYYY-MM-DD) or Excel date cells.

**Issue**: "File too large"
- **Solution**: Split the file into smaller chunks (< 10MB each).

## Excel Template Download

You can use this as a starting template for your grant imports:

| grant_code | grant_name | organization | description | end_date | grant_position | grant_salary | grant_benefit | grant_level_of_effort | grant_position_number | budgetline_code |
|------------|------------|--------------|-------------|----------|----------------|--------------|---------------|----------------------|----------------------|-----------------|
|            |            |              |             |          |                |              |               |                      |                      |                 |

## Comparison with Employee Import

**Key Differences from Employee/Employment Imports:**
- ❌ **NOT** asynchronous - Grant import is synchronous (immediate response)
- ✅ Multi-sheet format - Each sheet = one grant (vs single sheet with headers)
- ✅ Fixed row structure - Header info in rows 1-6, data starts at row 8
- ✅ Synchronous processing - Get results immediately in API response
- ✅ Detailed error reporting per sheet
- ✅ Handles duplicates gracefully

## Technical Details

### Import Processing
- **File**: `app/Http/Controllers/Api/GrantController.php`
- **Processing**: Synchronous (direct response)
- **Format**: Multi-sheet Excel workbook
- **Transaction**: All sheets processed in single database transaction

### Dependencies
- PhpOffice PhpSpreadsheet
- Laravel Eloquent ORM

## Support

For issues or questions about grant imports, please contact your system administrator or refer to the API documentation at `/api/documentation`.
