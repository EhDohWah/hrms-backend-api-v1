# Individual Leave Request Summary Report Implementation

## Overview
This document describes the implementation of the Individual Leave Request Summary Report feature, which generates PDF reports for specific employees showing their leave requests and balances within a specified date range.

## Features Implemented

### 1. API Endpoint
- **Route**: `POST /api/reports/leave-request-report/export-individual-pdf`
- **Method**: `LeaveRequestReportController@exportIndividualPDF`
- **Middleware**: `permission:reports.create`

### 2. Request Parameters
```json
{
  "start_date": "2025-08-01",
  "end_date": "2025-08-31",
  "staff_id": "EMP001"
}
```

### 3. Response
- **Content-Type**: `application/pdf`
- **Response**: Direct PDF download with cache control headers
- **Filename Format**: `individual_leave_request_report_{staff_id}_{employee_name}_{start_date}_to_{end_date}_{timestamp}.pdf`

## Implementation Details

### File Structure
```
app/
├── Http/
│   ├── Controllers/Api/Reports/
│   │   └── LeaveRequestReportController.php (updated)
│   └── Requests/
│       └── IndividualLeaveRequestReportRequest.php (new)
├── Models/
│   ├── Employee.php (existing)
│   ├── LeaveRequest.php (existing)
│   └── LeaveBalance.php (existing)
resources/
└── views/reports/
    └── leave_request_report_individual_pdf.blade.php (existing)
routes/
└── api/
    └── employment.php (updated)
```

### 1. Form Request Validation (`IndividualLeaveRequestReportRequest.php`)

#### Validation Rules
- `start_date`: Required, date format YYYY-MM-DD
- `end_date`: Required, date format YYYY-MM-DD, must be after or equal to start_date
- `staff_id`: Required, string, max 50 characters, must exist in employees table

#### Custom Error Messages
- Comprehensive validation messages for all fields
- User-friendly error descriptions
- Specific validation for date formats and employee existence

### 2. Controller Method (`exportIndividualPDF`)

#### Method Flow
1. **Input Validation**: Uses `IndividualLeaveRequestReportRequest`
2. **Cache Key Generation**: Creates unique cache key based on parameters
3. **Data Retrieval**: Calls `generateIndividualReportData()` with caching
4. **Employee Verification**: Returns 404 if employee not found
5. **PDF Generation**: Uses Laravel DomPDF with individual template
6. **Response**: Returns PDF download with security headers

#### Caching Strategy
- **Cache Key**: `individual_leave_report_{start_date}_{end_date}_{staff_id}`
- **TTL**: 15 minutes (SHORT_TTL)
- **Tags**: `['reports', 'leave_req', 'emp', 'individual']`

### 3. Data Generation (`generateIndividualReportData`)

#### Employee Data Retrieved
```php
Employee::where('staff_id', $staffId)
    ->with([
        'employment:id,employee_id,department_position_id,work_location_id',
        'employment.departmentPosition:id,department,position',
        'employment.workLocation:id,name',
    ])
    ->first();
```

#### Leave Requests Query
```php
LeaveRequest::where('employee_id', $employee->id)
    ->whereBetween('start_date', [$startDate, $endDate])
    ->with(['leaveType:id,name'])
    ->orderBy('start_date', 'asc')
    ->get()
```

#### Data Mapping for Template
- `date_requested`: Leave request creation date
- `duration_type`: "Full Day", "Half Day", or "X Days"
- `leave_type`: Leave type name from database
- `leave_reason`: Leave reason from request

### 4. Leave Balance Calculation

#### Process
1. Reuses existing `calculateEmployeeLeaveData()` method
2. Calculates used leave by type within date range
3. Calculates remaining leave balances for current year
4. Maps to template-friendly field names

#### Leave Types Supported
- Annual Leave (26 days)
- Sick Leave (30 days)
- Traditional Leave (13 days)
- Compassionate Leave (5 days)
- Maternity Leave (98 days)
- Training Leave (14 days)
- Personal Leave (3 days)
- Military Leave (60 days)
- Sterilization Leave (varies)
- Other Leave (0 days)

### 5. PDF Template Integration

#### Template Variables
- `$employee`: Complete employee data with employment details
- `$leaveRequests`: Collection of leave requests in date range
- `$entitlements`: Leave type entitlements/limits
- `$startDate`/`$endDate`: Report date range
- `$currentDateTime`: Generation timestamp

#### Employee Information Section
- Staff ID
- Staff Name (first_name_en + last_name_en)
- Work Location (from employment relationship)
- Department (from employment relationship)

#### Leave Requests Table
- Date Requested
- Date From/To
- Full/Half Day indicator
- Leave Type
- Leave Specific (reason)
- Leave Days

#### Remaining Leave Balances
- Grid layout showing all leave types
- Current remaining balance for each type
- Entitlement limits in parentheses

### 6. Route Configuration

#### Route Definition
```php
Route::post('/leave-request-report/export-individual-pdf', 
    [LeaveRequestReportController::class, 'exportIndividualPDF'])
    ->middleware('permission:reports.create');
```

#### Security
- Requires `reports.create` permission
- PDF download with security headers
- No caching headers to prevent sensitive data storage

## API Documentation (Swagger)

### Request Example
```bash
POST /api/reports/leave-request-report/export-individual-pdf
Content-Type: application/json
Authorization: Bearer {token}

{
  "start_date": "2025-08-01",
  "end_date": "2025-08-31",
  "staff_id": "EMP001"
}
```

### Response Codes
- **200**: PDF generated successfully (binary PDF download)
- **404**: Employee not found
- **422**: Validation error (invalid date format, missing fields)
- **500**: Server error during PDF generation

### Example Filename
```
individual_leave_request_report_EMP001_john_doe_20250801_to_20250831_20250909161726.pdf
```

## Error Handling

### Employee Not Found
```json
{
  "error": "Employee not found"
}
```

### Validation Errors
```json
{
  "message": "The given data was invalid",
  "errors": {
    "start_date": ["Start date is required."],
    "staff_id": ["The selected staff ID does not exist."]
  }
}
```

## Performance Considerations

### Caching
- Report data cached for 15 minutes
- Reduces database queries for repeated requests
- Cache invalidation via tags

### Query Optimization
- Selective field loading for employees
- Eager loading of relationships
- Indexed queries on employee_id and date ranges

### Memory Management
- Streams PDF directly to browser
- No temporary file storage
- Efficient data mapping

## Database Relationships Used

### Employee → Employment → WorkLocation
```php
$employee->employment->workLocation->name
```

### Employee → Employment → DepartmentPosition
```php
$employee->employment->departmentPosition->department
```

### Employee → LeaveRequests → LeaveType
```php
$employee->leaveRequests->leaveType->name
```

### Employee → LeaveBalances → LeaveType
```php
$employee->leaveBalances->leaveType->name
```

## Template Fixes Applied

### Field Name Corrections
- `$employee->employee_id` → `$employee->staff_id`
- `$employee->first_name` → `$employee->first_name_en`
- `$employee->last_name` → `$employee->last_name_en`

## Testing

### Test Cases
1. **Valid Request**: Staff ID exists, valid date range
2. **Invalid Staff ID**: Non-existent employee
3. **Invalid Date Range**: End date before start date
4. **Empty Date Range**: No leave requests in period
5. **Employee Without Employment**: Graceful handling
6. **Employee Without Leave Requests**: Empty table display

### Manual Testing Commands
```bash
# Valid request
curl -X POST /api/reports/leave-request-report/export-individual-pdf \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -d '{"start_date":"2025-08-01","end_date":"2025-08-31","staff_id":"EMP001"}'

# Invalid staff ID
curl -X POST /api/reports/leave-request-report/export-individual-pdf \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer {token}" \
  -d '{"start_date":"2025-08-01","end_date":"2025-08-31","staff_id":"INVALID"}'
```

## Security Considerations

### Access Control
- Requires `reports.create` permission
- Bearer token authentication required
- Staff ID validation against database

### Data Protection
- No caching of PDF files
- Security headers prevent browser caching
- Employee data only accessible to authorized users

### Input Validation
- SQL injection protection via Eloquent ORM
- XSS protection via Laravel's built-in escaping
- Date format validation

## Future Enhancements

### Potential Improvements
1. **Email Delivery**: Send PDF via email to employee/manager
2. **Bulk Generation**: Generate reports for multiple employees
3. **Custom Date Ranges**: Financial year, quarter selections
4. **Export Formats**: Excel, CSV options
5. **Approval Workflow**: Include approval history in report
6. **Multilingual Support**: Support for Thai language templates

### Performance Optimizations
1. **Background Processing**: Queue PDF generation for large reports
2. **CDN Storage**: Store frequently accessed reports
3. **Compression**: Optimize PDF file sizes
4. **Pagination**: Handle very large leave request histories

## Conclusion

The Individual Leave Request Summary Report provides a comprehensive solution for generating detailed leave reports for specific employees. The implementation follows Laravel best practices, includes proper validation and error handling, and maintains consistency with existing report functionality in the system.

The feature is production-ready and includes appropriate security measures, caching strategies, and error handling to ensure reliable operation in a multi-user environment.
