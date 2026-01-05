# Employment Import Excel Format Documentation

## Overview

The Employment Import feature allows you to bulk upload or update employment records using an Excel file (`.xlsx`, `.xls`, or `.csv`). The import is processed asynchronously in the background with chunk processing and comprehensive error handling.

## Import Process

1. **Upload**: Submit the Excel file via the `/api/employments/upload` endpoint
2. **Validation**: File is validated for format and size (max 10MB)
3. **Queued Processing**: Import is queued and processed in chunks
4. **Employee Matching**: System matches staff IDs to existing employees
5. **Create/Update**: Creates new employments or updates existing ones
6. **Notification**: User receives a notification when import is complete
7. **Error Handling**: Detailed error messages for each failed row

## Excel File Structure

### Required Columns (Header Row)

The Excel file must have a **header row** with the following column names. The column names should match your existing Excel format:

| Excel Header | Mapped Field | Type | Required | Description | Example |
|--------------|--------------|------|----------|-------------|---------|
| `S no.` | - | Integer | No | Serial number (not imported) | `111` |
| `ID.no.` | `staff_id` | String | **Yes** | Employee staff ID | `0202` |
| `Initial` | - | String | No | Employee initial (not imported) | `Ms.` |
| `Name` | - | String | No | Employee name (not imported) | `Naw Esther` |
| `Pay method` | `pay_method` | String | No | Payment method | `Transferred to bank` |
| `Status` | `employment_type` | String | No | Employment status/type | `Local non ID Staff` |
| `PVD/Saving` | `pvd`/`saving_fund` | String | No | PVD or Saving fund indicator | `Saving fund` |
| `Grant` | - | String | No | Grant number (not used for employment) | `1` |
| `Site` | `site_id` | String | No | Site/work location name | `MRM` |
| `Dept.` | `department_id` | String | No | Department name | `Malaria` |
| `Section Dept.` | `section_department_id` | String | No | Section department | `-` |
| `Salary 2025` | `pass_probation_salary` | Numeric | **Yes** | Monthly salary after probation | `75000` |
| `Actual Position` | `position_id` | String | No | Position title | `Field Officer` |
| `Start date BHF` | `start_date` | Date | No* | Start date for BHF employees | `1-Mar-25` |
| `Start date SMRU` | `start_date` | Date | No* | Start date for SMRU employees | `01-Jan-19` |
| `Pass Prob. Date` | `pass_probation_date` | Date | No | Date when probation ends | `01-Apr-25` |
| `Health Welfare Employer` | `health_welfare_percentage` | Numeric | No | Health welfare percentage | `5` |
| `Note` | - | Text | No | Notes (not imported) | `Any notes` |

**Note**: Either `Start date BHF` or `Start date SMRU` must be provided (at least one is required).

### Additional Columns (Not Imported)

The following columns in your Excel are calculated fields or not used for employment import:
- `Salary 2025 by FTE`
- `Compensation/refund`
- `13 month salary`
- `PVD/Saving fund 7.5%`
- `Employer S.Insu 5%`
- `Employee S.Insu 5%`
- `Health Welfare Employee`
- `Tax`
- `Balance`
- `Sal+SC+SF+M13`
- `SF (2 sides)`
- `Report to`
- `Position under Grant`
- `Budget line`
- `Under budget May`
- `End of Prob. Date`
- `Sal+Bonus`
- `PF+HI+SS`
- `(PF*2)+(SS*2)+HI+Tax`

These columns can remain in your Excel file but will be ignored during import.

## Excel Format Examples

### Example 1: Simple Employment Record

```
| S no. | ID.no. | Initial | Name        | Pay method              | Status              | PVD/Saving   | Site | Dept.   | Salary 2025 | Actual Position | Start date BHF | Start date SMRU | Pass Prob. Date |
|-------|--------|---------|-------------|------------------------|---------------------|--------------|------|---------|-------------|----------------|----------------|-----------------|-----------------|
| 1     | 0001   | Ms.     | Jane Smith  | Transferred to bank    | Local non ID Staff  | Saving fund  | MRM  | Malaria | 75000       | Field Officer  | 01-Jan-25      |                 | 01-Apr-25       |
```

### Example 2: Multiple Employment Records

```
| S no. | ID.no. | Initial | Name          | Pay method              | Status              | PVD/Saving   | Site | Dept.   | Salary 2025 | Actual Position   | Start date BHF | Start date SMRU | Pass Prob. Date |
|-------|--------|---------|---------------|------------------------|---------------------|--------------|------|---------|-------------|------------------|----------------|-----------------|-----------------|
| 1     | 0001   | Ms.     | Jane Smith    | Transferred to bank    | Local non ID Staff  | Saving fund  | MRM  | Malaria | 75000       | Field Officer    | 01-Jan-25      |                 | 01-Apr-25       |
| 2     | 0002   | Mr.     | John Doe      | Cash                   | Full-time           | PVD          | BHF  | HR      | 85000       | HR Manager       |                | 15-Feb-24       | 15-May-24       |
| 3     | 0003   | Dr.     | Sarah Johnson | Transferred to bank    | Contract            | Saving fund  | MRM  | Medical | 120000      | Medical Director | 01-Mar-25      |                 | 01-Jun-25       |
```

## Field Details

### Staff ID (ID.no.) - **Required**
- **Purpose**: Links the employment record to an existing employee
- **Format**: String (must match exactly with employee's staff_id in database)
- **Example**: `0202`, `EMP001`
- **Important**: Employee must exist in the database before importing employment

### Pay Method
- **Maps to**: `pay_method` field
- **Accepted Values**: 
  - "Transferred to bank" or "Bank Transfer" → `Bank Transfer`
  - "Cash" → `Cash`
  - "Cheque" or "Check" → `Cheque`
- **Default**: If not provided, remains null
- **Example**: `Transferred to bank`

### Status (Employment Type)
- **Maps to**: `employment_type` field
- **Logic**: Automatically mapped based on text content
  - Contains "full" or "local" → `Full-time`
  - Contains "part" → `Part-time`
  - Contains "contract" → `Contract`
  - Contains "temp" → `Temporary`
- **Default**: `Full-time`
- **Examples**: 
  - `Local non ID Staff` → `Full-time`
  - `Full-time Employee` → `Full-time`
  - `Part-time Contract` → `Part-time`

### PVD/Saving Fund
- **Maps to**: `pvd` and `saving_fund` boolean fields, plus percentage fields
- **Logic**:
  - Contains "PVD" → Sets `pvd = true`, `pvd_percentage = 7.5`
  - Contains "Saving" → Sets `saving_fund = true`, `saving_fund_percentage = 7.5`
- **Examples**: 
  - `PVD` → PVD enabled at 7.5%
  - `Saving fund` → Saving fund enabled at 7.5%
  - `PVD and Saving` → Both enabled

### Site
- **Maps to**: `site_id` field
- **Logic**: System looks up the site name in the `sites` table
- **Important**: Site name must exist in the database
- **Example**: `MRM`, `BHF`, `SMRU`

### Salary 2025 - **Required**
- **Maps to**: `pass_probation_salary` field
- **Format**: Numeric value (commas will be removed)
- **Example**: `75000`, `75,000.00`
- **Important**: This is the monthly salary after probation period

### Start Date (BHF or SMRU) - **At least one required**
- **Maps to**: `start_date` field
- **Priority**: BHF date takes priority if both are provided
- **Formats Accepted**:
  - `YYYY-MM-DD`: `2025-01-01`
  - `DD-MMM-YY`: `1-Mar-25`
  - `DD/MM/YYYY`: `01/01/2025`
  - Excel date numbers: Auto-converted
- **Example**: `1-Mar-25`, `01-Jan-19`

### Pass Probation Date
- **Maps to**: `pass_probation_date` field
- **Format**: Same as start date formats
- **Logic**: If probation date is in the future, probation_salary will be set equal to pass_probation_salary
- **Example**: `01-Apr-25`

### Health Welfare Employer
- **Maps to**: `health_welfare_percentage` field
- **Format**: Numeric (percentage value)
- **Logic**: If value > 0, sets `health_welfare = true`
- **Example**: `5` (means 5%)

## Validation Rules

### Employment Level Validation
1. **Staff ID Required**: Must be provided for every row
2. **Employee Must Exist**: Staff ID must match an existing employee
3. **Salary Required**: Monthly salary must be provided and valid
4. **Start Date Required**: At least one start date (BHF or SMRU) must be provided
5. **No Duplicate Staff IDs**: Cannot have duplicate staff IDs in the same import file

### Data Type Validation
- **Numeric Fields**: Salary, percentages must be valid numbers
- **Date Fields**: Must be valid dates in supported formats
- **Text Fields**: Status, pay method, site names

## Import Behavior

### Create vs Update Logic
- **If employee has NO active employment**: Creates new employment record
- **If employee has ACTIVE employment**: Updates the existing employment record
- **Active Employment**: Defined as `status = true` in the database

### Field Mapping
The import automatically:
1. **Looks up employees** by staff_id
2. **Converts status text** to employment_type
3. **Maps site names** to site_id (must exist in sites table)
4. **Parses dates** from various formats
5. **Calculates benefit flags** based on text indicators
6. **Determines probation status** based on probation date

### What Gets Created/Updated
For each valid row, the system creates or updates:
- Employment record with all relevant fields
- Automatically creates employment history record (via model observer)

### What Does NOT Get Imported
- Employee personal information (name, initial)
- Grant allocations (use separate grant allocation import)
- Calculated fields (tax, balance, totals)
- Notes and comments

## Error Reporting

### Common Error Messages

**"Row X: Missing staff ID (ID.no.)"**
- **Cause**: ID.no. column is empty
- **Solution**: Fill in the employee staff ID

**"Row X: Employee with staff_id 'XXX' not found in database"**
- **Cause**: Employee doesn't exist in the system
- **Solution**: Create the employee first using employee import

**"Row X: Missing start date"**
- **Cause**: Both Start date BHF and Start date SMRU are empty
- **Solution**: Provide at least one start date

**"Row X: Missing or invalid salary"**
- **Cause**: Salary 2025 column is empty or contains invalid value
- **Solution**: Provide a valid numeric salary

**"Duplicate staff_id in import file"**
- **Cause**: Same staff ID appears multiple times in the file
- **Solution**: Remove duplicate rows or use separate imports

### Error Handling Features
- **Row-by-row validation**: Each row is validated independently
- **Partial success**: Valid rows are imported even if some rows fail
- **Detailed error log**: Each error includes row number and specific issue
- **Notification summary**: Final notification includes counts of created, updated, and failed records

## API Usage

### Endpoint
```
POST /api/employments/upload
```

### Request
```http
POST /api/employments/upload
Content-Type: multipart/form-data
Authorization: Bearer {token}

file: [Excel file]
```

### Response (Success)
```json
{
  "success": true,
  "message": "Employment import started successfully. You will receive a notification when the import is complete.",
  "data": {
    "import_id": "employment_import_abc123",
    "status": "processing"
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

### Notification Message
When import completes, you'll receive a notification like:
```
Employment import finished! Created: 45, Updated: 23, Errors: 2, Validation failures: 1
```

## Best Practices

1. **Prepare Employees First**: Ensure all employees exist in the system before importing employments
2. **Verify Sites**: Confirm all site names match exactly with site names in the database
3. **Check Dates**: Use consistent date format throughout the file
4. **Review Existing Records**: Know which employees already have active employments
5. **Start Small**: Test with 5-10 records before importing large datasets
6. **Keep Backup**: Always keep a backup of your original Excel file
7. **Monitor Notifications**: Check notifications for import status and errors
8. **Clean Data**: Remove unnecessary calculated columns or keep them (they'll be ignored)

## Troubleshooting

### Common Issues

**Issue**: "Employee with staff_id 'XXX' not found"
- **Solution**: Import employees first, or verify the staff_id matches exactly

**Issue**: "Site lookup failed"
- **Solution**: Ensure site names in Excel match site names in database (case-sensitive)

**Issue**: "Missing start date"
- **Solution**: Fill in either "Start date BHF" or "Start date SMRU" column

**Issue**: "Invalid date format"
- **Solution**: Use standard date format (DD-MMM-YY or YYYY-MM-DD)

**Issue**: "File too large"
- **Solution**: Split the file into smaller chunks (< 10MB each)

**Issue**: Employment created but benefit percentages are wrong
- **Solution**: Check benefit settings in the system settings

## Notes About Your Excel Format

Based on your provided Excel structure, here are specific notes:

1. **Calculated Columns**: Many columns in your Excel appear to be formulas (tax, balance, totals). These are NOT imported and can remain in your file.

2. **Budget Information**: Columns like "Position under Grant", "Budget line", "Under budget May" are for grant allocation management, not employment import. Use a separate process for funding allocations.

3. **Multiple Organizations**: Your format includes both BHF and SMRU start dates. The system will use whichever date is provided (BHF takes priority if both exist).

4. **PVD vs Saving Fund**: The system automatically determines which benefit type based on the text in "PVD/Saving" column and sets the percentage to 7.5%.

5. **Status Mapping**: Your "Status" column (e.g., "Local non ID Staff") is automatically mapped to a standard employment type.

## Excel Template

You can continue using your existing Excel format. Just ensure these key columns have valid data:
- ✅ `ID.no.` (staff ID)
- ✅ `Salary 2025`
- ✅ `Start date BHF` OR `Start date SMRU`

All other columns are optional or will be ignored during import.

## Technical Details

### Import Class
- **File**: `app/Imports/EmploymentsImport.php`
- **Queue**: Yes (asynchronous processing)
- **Chunk Size**: 40 rows per chunk
- **Cache TTL**: 1 hour for import status/errors

### Dependencies
- Laravel Excel (Maatwebsite)
- Laravel Queue System
- Laravel Notifications

### Database Impact
- Creates or updates records in `employments` table
- Automatically creates records in `employment_histories` table (via model observer)
- Does NOT create funding allocations (separate process)

## Comparison with Employee & Grant Imports

Similar to Employee and Grant imports, the Employment Import:
- ✅ Uses the same Excel-based import approach
- ✅ Processes data asynchronously in chunks
- ✅ Provides detailed error reporting and validation
- ✅ Sends notifications on completion
- ✅ Handles duplicates gracefully (updates existing records)
- ✅ Supports large file imports with queue processing

**Key Difference**: Employment import can UPDATE existing records, not just create new ones.

## Support

For issues or questions about employment imports, please contact your system administrator or refer to the API documentation at `/api/documentation`.
