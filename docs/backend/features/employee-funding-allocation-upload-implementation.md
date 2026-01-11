# Employee Funding Allocation Upload Implementation

## Overview

This document describes the implementation of the Employee Funding Allocation upload feature, which allows bulk importing of employee funding allocations via Excel files.

**Date:** January 9, 2026  
**Author:** AI Assistant  
**Status:** Completed

---

## Key Design Decisions

### 1. Using Staff ID Instead of Employment ID

**Decision:** The template uses `staff_id` (or `employee_id`) instead of `employment_id`.

**Rationale:**
- Users know their employees by staff ID, not by internal employment record IDs
- The system automatically looks up the active employment for each employee
- Simplifies the user experience significantly
- Reduces errors from incorrect employment ID mapping

**Implementation:**
- Template requires `staff_id` field
- Import process automatically finds active employment using the staff_id
- If no active employment exists, the import logs an error for that row

### 2. Grant Items Reference Export

**Decision:** Created a separate download endpoint for Grant Items Reference.

**Rationale:**
- Grant Item IDs are database-generated and unknown to users
- One grant has many grant items (positions)
- Users need to see all available grant items with their IDs before importing
- The reference file includes grant information for context

**Implementation:**
- Endpoint: `GET /api/downloads/grant-items-reference`
- Returns Excel file with:
  - Grant ID, Code, Name, Organization
  - Grant Item ID, Position, Budget Line Code
  - Salary, Benefit, Level of Effort, Position Number
  - Grant Status

### 3. Simplified Template Structure

**Decision:** Reduced template columns to essential fields only.

**Template Columns:**
1. `staff_id` - Employee staff ID (Required)
2. `grant_item_id` - Grant item ID from reference file (Required)
3. `fte` - FTE percentage 0-100 (Required)
4. `allocated_amount` - Pre-calculated amount (Optional, auto-calculated if empty)
5. `start_date` - Allocation start date (Required)
6. `end_date` - Allocation end date (Optional)
7. `notes` - Additional notes (Optional)

**Removed Fields:**
- `employment_id` - Auto-detected from staff_id
- `allocation_type` - Defaulted to 'grant'
- `salary_type` - Auto-detected based on probation status
- `status` - Defaulted to 'active'

---

## Architecture

### Backend Components

#### 1. Controller: `EmployeeFundingAllocationController`

**Location:** `app/Http/Controllers/Api/EmployeeFundingAllocationController.php`

**New/Updated Methods:**
- `downloadTemplate()` - Downloads the simplified import template
- `downloadGrantItemsReference()` - Downloads grant items reference with IDs
- `upload()` - Handles file upload and queues import job

#### 2. Import Class: `EmployeeFundingAllocationsImport`

**Location:** `app/Imports/EmployeeFundingAllocationsImport.php`

**Key Features:**
- Implements `ShouldQueue` for background processing
- Chunk processing (50 rows per chunk)
- Auto-detects active employment from staff_id
- Auto-calculates allocated_amount if not provided
- Auto-detects salary_type based on probation status
- Validates grant_item_id existence
- Handles create/update logic for existing allocations

**Validation Rules:**
```php
'*.staff_id' => 'required|string',
'*.grant_item_id' => 'required|integer',
'*.fte' => 'required|numeric|min:0|max:100',
'*.allocated_amount' => 'nullable|numeric|min:0',
'*.start_date' => 'required|date',
'*.end_date' => 'nullable|date',
```

#### 3. Routes

**Location:** `routes/api/uploads.php`

**New Routes:**
```php
// Upload
POST /api/uploads/employee-funding-allocation

// Downloads
GET /api/downloads/employee-funding-allocation-template
GET /api/downloads/grant-items-reference  // NEW
```

### Frontend Components

#### 1. Component: `funding-allocation-upload.vue`

**Location:** `src/components/uploads/funding-allocation-upload.vue`

**Features:**
- File upload with progress tracking
- Download template button
- **NEW:** Download grant items reference button with helper text
- Upload validation
- Success/error notifications

**UI Enhancement:**
```vue
<a-button @click="downloadGrantItemsReference">
  Download Grant Items Reference
</a-button>
<span>(Required: Contains Grant Item IDs for the import)</span>
```

#### 2. Service: `upload-funding-allocation.service.js`

**Location:** `src/services/upload-funding-allocation.service.js`

**New Method:**
```javascript
async downloadGrantItemsReference() {
  const response = await apiService.get(
    API_ENDPOINTS.UPLOAD.GRANT_ITEMS_REFERENCE,
    { responseType: 'blob' }
  );
  // Trigger download...
}
```

#### 3. API Configuration

**Location:** `src/config/api.config.js`

**New Endpoint:**
```javascript
UPLOAD: {
  // ...existing endpoints
  GRANT_ITEMS_REFERENCE: '/downloads/grant-items-reference',
}
```

---

## User Workflow

### Step 1: Download Grant Items Reference
1. User clicks "Download Grant Items Reference" button
2. System generates Excel file with all grants and grant items
3. File includes Grant Item IDs needed for the import
4. User reviews available grant items and notes the IDs

### Step 2: Download Import Template
1. User clicks "Download Template" button
2. System generates simplified Excel template
3. Template includes:
   - Column headers
   - Validation rules
   - Sample data
   - Comprehensive instructions

### Step 3: Fill Template
1. User enters employee data:
   - `staff_id` - Known employee identifier
   - `grant_item_id` - Copied from Grant Items Reference
   - `fte` - Percentage (e.g., 100 for full-time, 50 for half-time)
   - `start_date` - Required
   - Other optional fields

2. For split funding, user creates multiple rows:
   ```
   EMP001 | 5  | 60 | 2025-01-01
   EMP001 | 10 | 40 | 2025-01-01
   ```

### Step 4: Upload File
1. User selects filled template
2. Clicks upload button
3. System validates file format and size
4. Import is queued for background processing
5. User receives notification when complete

---

## Import Processing Logic

### 1. Employee Lookup
```php
// Find employee by staff_id
$employeeId = $this->existingStaffIds[$staffId];

// Auto-detect active employment
$employmentId = $this->existingEmployments[$staffId];
```

### 2. Grant Item Validation
```php
// Validate grant_item_id exists
if (!isset($this->grantItemLookup[$grantItemId])) {
    $errors[] = "Invalid grant_item_id";
}
```

### 3. Auto-Calculation
```php
// Auto-calculate allocated_amount if not provided
if (empty($row['allocated_amount'])) {
    $salaryToUse = $employment->pass_probation_salary;
    if ($employment->isOnProbation()) {
        $salaryToUse = $employment->probation_salary;
    }
    $allocatedAmount = round($salaryToUse * $fteDecimal, 2);
}
```

### 4. Create or Update
```php
// Check if allocation exists
$existingAllocation = EmployeeFundingAllocation::where([
    'employee_id' => $employeeId,
    'employment_id' => $employmentId,
    'grant_item_id' => $grantItemId,
])->first();

if ($existingAllocation) {
    // Update existing
    $allocationUpdates[$existingAllocation->id] = $allocationData;
} else {
    // Create new
    $allocationBatch[] = $allocationData;
}
```

---

## Error Handling

### Validation Errors

**Missing Required Fields:**
```
Row 5: Missing staff_id
Row 10: Missing or invalid start_date
```

**Invalid References:**
```
Row 3: Employee with staff_id 'EMP999' not found
Row 7: Invalid grant_item_id '999'
Row 12: No active employment found for staff_id 'EMP005'
```

**Data Validation:**
```
Row 8: Invalid FTE value (must be between 0-100)
Row 15: Invalid allocation_type 'invalid' (must be: grant, org_funded)
```

### Notification System

**Success:**
```
Employee funding allocation import finished!
Created: 45, Updated: 12
```

**With Errors:**
```
Employee funding allocation import finished!
Created: 40, Updated: 10, Errors: 5
```

**Failure:**
```
Excel import failed
Error: [Detailed error message]
Import ID: funding_allocation_import_abc123
```

---

## Testing

### Test File Location
`tests/Feature/Api/EmployeeFundingAllocationUploadTest.php`

### Test Cases

1. **Template Downloads**
   - ✅ Can download funding allocation template
   - ✅ Can download grant items reference

2. **File Validation**
   - ✅ Validates file upload request
   - ✅ Accepts valid Excel files
   - ✅ Rejects invalid file types
   - ✅ Rejects files exceeding size limit

3. **Import Processing** (to be added)
   - Import creates new allocations
   - Import updates existing allocations
   - Import handles validation errors
   - Import auto-detects employment
   - Import auto-calculates amounts

### Running Tests

```bash
# Run all funding allocation tests
php artisan test --filter=EmployeeFundingAllocationUpload

# Run specific test
php artisan test --filter=it_can_download_grant_items_reference
```

---

## Database Schema

### Table: `employee_funding_allocations`

**Key Fields:**
- `employee_id` - Foreign key to employees
- `employment_id` - Foreign key to employments (nullable, auto-detected)
- `grant_item_id` - Foreign key to grant_items (required)
- `fte` - Decimal(4,2) - FTE as decimal (0.00 to 1.00)
- `allocated_amount` - Decimal(15,2) - Calculated amount
- `allocation_type` - String - 'grant' or 'org_funded'
- `salary_type` - String - 'probation_salary' or 'pass_probation_salary'
- `status` - String - 'active', 'historical', 'terminated'
- `start_date` - Date - Required
- `end_date` - Date - Optional

**Indexes:**
```sql
INDEX idx_employee_employment (employee_id, employment_id)
INDEX idx_employment_status (employment_id, status)
INDEX idx_grant_item_status (grant_item_id, status)
```

---

## Performance Considerations

### 1. Prefetching
- Employee staff_ids prefetched into memory
- Active employments prefetched and mapped
- Grant items prefetched with grant relationships
- Reduces database queries during import

### 2. Chunk Processing
- Processes 50 rows per chunk
- Prevents memory exhaustion on large files
- Allows progress tracking

### 3. Batch Operations
```php
// Batch insert for new allocations
EmployeeFundingAllocation::insert($allocationBatch);

// Batch update for existing allocations
foreach ($allocationUpdates as $id => $data) {
    EmployeeFundingAllocation::where('id', $id)->update($data);
}
```

### 4. Background Processing
- Import runs in queue (asynchronous)
- Doesn't block user interface
- Notification sent when complete

---

## Security Considerations

### 1. Authentication
- All endpoints require `auth:sanctum` middleware
- User must be authenticated to upload/download

### 2. Authorization
- Upload requires `employee_funding_allocations.edit` permission
- Download requires `employee_funding_allocations.read` permission

### 3. File Validation
- File type: xlsx, xls, csv only
- File size: Maximum 10MB
- Content validation before processing

### 4. Data Validation
- All foreign keys validated
- Employee must exist
- Employment must exist and be active
- Grant item must exist
- FTE must be within valid range

---

## Troubleshooting

### Common Issues

**Issue: "No active employment found"**
- **Cause:** Employee doesn't have an active employment record
- **Solution:** Create employment record for the employee first

**Issue: "Invalid grant_item_id"**
- **Cause:** Grant item ID doesn't exist or was typed incorrectly
- **Solution:** Download latest Grant Items Reference and verify ID

**Issue: "Employee with staff_id not found"**
- **Cause:** Staff ID doesn't match any employee in the system
- **Solution:** Verify staff ID spelling and create employee if needed

**Issue: Import notification shows errors**
- **Cause:** Some rows failed validation
- **Solution:** Check notification for error details and fix those rows

### Debug Mode

Check import logs:
```bash
tail -f storage/logs/laravel.log | grep "EmployeeFundingAllocation"
```

Check queue status:
```bash
php artisan queue:work --once
```

---

## Future Enhancements

### Potential Improvements

1. **Validation Preview**
   - Show validation results before actual import
   - Allow user to fix errors and re-upload

2. **Import History**
   - Track all imports with timestamps
   - Show import statistics and error logs
   - Allow re-processing failed imports

3. **Bulk Operations**
   - Bulk end-date updates
   - Bulk status changes
   - Bulk FTE adjustments

4. **Advanced Validation**
   - Warn if total FTE per employee ≠ 100%
   - Check grant item capacity before import
   - Validate date overlaps

5. **Export Existing Data**
   - Export current allocations to Excel
   - Use as template for updates
   - Include all current data

---

## Conclusion

The Employee Funding Allocation upload feature provides a streamlined way to bulk import funding allocations using:
- Simplified template with only essential fields
- Grant Items Reference for easy ID lookup
- Auto-detection of employment records
- Auto-calculation of amounts
- Background processing with notifications

This implementation significantly improves user experience by removing the need to know internal database IDs (employment_id) and providing a clear reference file for grant items.

---

## Related Documentation

- [Employment Template Human-Readable Fields](./employment-template-human-readable-fields.md)
- [Payroll Upload/Download Implementation](../routes/payroll-upload-download-implementation.md)
- [Quick Fix Summary](../fixes/QUICK-FIX-SUMMARY.md)
