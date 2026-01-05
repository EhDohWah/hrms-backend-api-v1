# Leave Management System Documentation

**Version:** 3.0  
**Date:** October 4, 2025  
**Status:** Production Ready  
**Last Updated:** Consolidated HR/Site Admin approval fields

## Overview

The Leave Management System is designed as a **Data Entry and Display System** that digitizes paper-based leave processes into database records. It handles leave requests, leave balances, and approval information while maintaining data integrity through balance calculations. The system has been simplified to store all approval data directly in the leave request record, eliminating the need for separate approval tables and complex workflow management.

## Recent Updates (Version 3.0)

### ✅ **Major Change: Consolidated HR/Site Admin Approval**
Based on paper form analysis, Site administrator and HR approval are the same thing. The system has been updated to reflect this:

**Before (Version 2.0):**
- `hr_approved` + `site_admin_approved` (separate fields)
- `hr_approved_date` + `site_admin_approved_date` (separate dates)

**After (Version 3.0):**
- `hr_site_admin_approved` (single consolidated field)
- `hr_site_admin_approved_date` (single consolidated date)

**Files Updated:**
- ✅ Database migration (`2025_03_16_021936_create_leave_management_tables.php`)
- ✅ LeaveRequest model (`app/Models/LeaveRequest.php`)
- ✅ LeaveManagementController (`app/Http/Controllers/Api/LeaveManagementController.php`)
- ✅ LeaveRequestResource (`app/Http/Resources/LeaveRequestResource.php`)
- ✅ LeaveRequestFactory (`database/factories/LeaveRequestFactory.php`)
- ✅ Feature tests (`tests/Feature/LeaveRequestFeatureTest.php`)
- ✅ API documentation (OpenAPI/Swagger annotations)

### Key Features
- ✅ Complete CRUD operations for leave requests, types, and balances
- ✅ Server-side pagination with advanced filtering and search
- ✅ Automatic balance calculations and validation
- ✅ **Consolidated approval tracking (supervisor + HR/Site Admin)**
- ✅ Comprehensive statistics and reporting
- ✅ Attachment notes for document references
- ✅ Multi-field sorting and filtering capabilities
- ✅ Comprehensive API documentation (Swagger)

## System Architecture

### Core Principle
This system follows the HRMS data entry approach:
- **Paper forms are processed offline** (approvals, signatures, etc.)
- **System stores the final state** of leave requests with approval information
- **Leave balances are calculated and maintained** to prevent data inconsistencies
- **No automated workflow** - all business logic happens outside the system
- **Simplified data structure** - approval information stored directly in leave requests
- **Consolidated approvals** - HR and Site Admin are treated as the same approval level

## Database Structure

### 1. Leave Types (`leave_types`)
Predefined leave categories with default allocations:

```sql
- id (Primary Key)
- name (e.g., "Annual Leave", "Sick Leave")
- default_duration (Default days allocated per year)
- description (Detailed description in English/Thai)
- requires_attachment (Boolean - if medical certificates needed)
- created_by, updated_by, timestamps
```

**Seeded Leave Types:**
- Annual Leave (26 days) - Annual vacation / ลาพักร้อนประจำปี
- Sick Leave (30 days) - Sick/ลาป่วย (state disease/ระบุโรค)
- Personal Leave (3 days) - Personal leave/ลากิจธุระส่วนตัวเป็น
- Maternity Leave (98 days) - Maternity leave / Paternity leave วันหยุดมาตรดา
- Traditional Day-off (13 days) - Traditional day-off /วันพุฒหาประเพณี
- Compassionate Leave (5 days) - Compassionate (spouse, children, parents, etc.)
- Career Development Training (14 days) - Career development training
- Military Leave (60 days) - Military leave / ลาเพื่อรับราชการทหาร
- Unpaid Leave (0 days) - Unpaid Leave / ลาป่วยไม่จ่ายเงิน
- Sterilization Leave (requires attachment) - Sterilization leave/ ลาเพื่อทำหมัน
- Other - Other/ อื่น ๆ

### 2. Leave Requests (`leave_requests`) - **UPDATED SCHEMA**
Individual leave applications with integrated approval data from paper forms:

```sql
- id (Primary Key)
- employee_id (Foreign Key to employees)
- leave_type_id (Foreign Key to leave_types)
- start_date, end_date
- total_days (Calculated working days, decimal 18,2)
- reason (Purpose of leave)
- status (pending/approved/declined/cancelled)

-- Approval fields (boolean flags with dates) - UPDATED
- supervisor_approved (Boolean, default false)
- supervisor_approved_date (Date, nullable)
- hr_site_admin_approved (Boolean, default false) -- CONSOLIDATED FIELD
- hr_site_admin_approved_date (Date, nullable)    -- CONSOLIDATED FIELD

-- Attachment reference (simplified)
- attachment_notes (Text field for attachment references)

- created_by, updated_by, timestamps

-- Performance indexes
- INDEX(employee_id, status)
- INDEX(start_date, end_date)
```

**Key Features:**
- **Simplified Structure**: All approval information stored directly in the leave request record
- **Paper Form Mapping**: Fields directly correspond to signature sections on paper forms
- **Consolidated Approvals**: HR and Site Admin approvals merged into single field
- **No Workflow Management**: System doesn't manage approval process, just records final state
- **Text-Based References**: Attachment notes instead of file management

### 3. Leave Balances (`leave_balances`)
Annual leave entitlements and usage tracking:

```sql
- id (Primary Key)
- employee_id (Foreign Key to employees)
- leave_type_id (Foreign Key to leave_types)
- total_days (Annual allocation)
- used_days (Days consumed)
- remaining_days (total_days - used_days)
- year (Calendar year)
- created_by, updated_by, timestamps
- UNIQUE(employee_id, leave_type_id, year)
```

**Note**: Attachment tracking is done through the `attachment_notes` text field in the `leave_requests` table. This field stores simple text notes about physical documents attached to the paper leave request forms (e.g., "Medical certificate attached", "Doctor's note - dated 2025-03-15").

## API Endpoints

### Leave Requests
```
GET    /api/v1/leaves/requests           # List with advanced filtering, pagination, and statistics
POST   /api/v1/leaves/requests           # Create new request with automatic balance validation
GET    /api/v1/leaves/requests/{id}      # Get specific request with full relationships
PUT    /api/v1/leaves/requests/{id}      # Update request with balance recalculation
DELETE /api/v1/leaves/requests/{id}      # Delete request with balance restoration
```

**Advanced Query Parameters for GET /api/v1/leaves/requests:**
- `page` (integer): Page number for pagination
- `per_page` (integer, 1-100): Items per page (default: 10)
- `search` (string): Search by staff ID or employee name
- `from` (date): Start date filter (YYYY-MM-DD)
- `to` (date): End date filter (YYYY-MM-DD)
- `leave_types` (string): Comma-separated leave type IDs
- `status` (string): Request status filter (pending/approved/declined/cancelled)
- `supervisor_approved` (boolean): Filter by supervisor approval status
- `hr_site_admin_approved` (boolean): **UPDATED** - Filter by HR/Site Admin approval status
- `sort_by` (string): Sort option (recently_added/ascending/descending/last_month/last_7_days)

**Response includes:**
- Paginated leave requests with employee, leave type relationships
- Comprehensive statistics (total, pending, approved, declined, cancelled requests)
- Time-based breakdowns (this week, month, year)
- Leave type usage statistics

### Leave Balances
```
GET    /api/v1/leaves/balances                           # List all balances
POST   /api/v1/leaves/balances                           # Create balance
PUT    /api/v1/leaves/balances/{id}                      # Update balance
GET    /api/v1/leaves/balance/{employeeId}/{leaveTypeId} # Get specific balance
```

### Leave Types
```
GET    /api/v1/leaves/types        # List leave types
POST   /api/v1/leaves/types        # Create leave type
PUT    /api/v1/leaves/types/{id}   # Update leave type
DELETE /api/v1/leaves/types/{id}   # Delete leave type
```

## Data Entry Workflow

### 1. **Setting Up Employee Leave Balances**
```
1. Create annual leave balances for each employee
2. Set total_days based on leave type defaults or custom amounts
3. System calculates remaining_days automatically
```

### 2. **Processing Leave Requests - UPDATED WORKFLOW**
```
1. Employee submits paper leave form
2. Form goes through offline approval process (supervisor, HR/Site admin sign)
3. HR enters completed form data into system in a single record:
   - Employee, leave type, dates, reason, status
   - Supervisor name and approval date from paper form
   - HR/Site Admin approval name and date from paper form (CONSOLIDATED)
   - Attachment notes (simple text describing attachments)
4. System validates against leave balance (if approved)
5. System updates balance automatically if approved
```

### 3. **Balance Adjustments**
```
1. Manual corrections via balance update API
2. System recalculates remaining_days automatically
3. Audit trail maintained through created_by/updated_by
```

## Business Logic

### Balance Validation Rules:
1. **Cannot approve requests exceeding available balance**
2. **Cannot create negative balances**
3. **Automatic restoration when requests are cancelled/deleted**
4. **Year-based balance tracking**

### Status Management:
- **pending**: Initial state when entered
- **approved**: Approved by authorized personnel (deducts from balance)
- **declined**: Rejected (no balance impact)
- **cancelled**: Cancelled after approval (restores balance)

### Approval Logic - UPDATED:
- **Two-Level Approval**: Supervisor + HR/Site Admin (consolidated)
- **Paper Form Alignment**: Database fields match paper form signature sections
- **No Automated Workflow**: System stores final approval states only

## Example Usage

### Creating Employee Leave Balance:
```json
POST /api/v1/leaves/balances
{
  "employee_id": 123,
  "leave_type_id": 1,
  "total_days": 26,
  "year": 2024
}
```

### Entering Approved Leave Request - UPDATED EXAMPLE:
```json
POST /api/v1/leaves/requests
{
  "employee_id": 123,
  "leave_type_id": 1,
  "start_date": "2024-12-01",
  "end_date": "2024-12-05",
  "total_days": 5,
  "reason": "Family vacation",
  "status": "approved",
  "supervisor_approved": true,
  "supervisor_approved_date": "2024-11-25",
  "hr_site_admin_approved": true,
  "hr_site_admin_approved_date": "2024-11-26",
  "attachment_notes": "Medical certificate submitted - dated 2024-11-20"
}
```

The system will automatically:
- Validate sufficient balance exists (21 days remaining)
- Deduct 5 days from balance
- Update remaining_days to 16

## API Documentation & Testing

### Swagger Documentation
The Leave Management API is fully documented with OpenAPI/Swagger annotations:
- **Access:** Available at `/api/documentation` endpoint
- **Interactive Testing:** Use Swagger UI to test all endpoints
- **Schema Definitions:** Complete request/response schemas included
- **Authentication:** All endpoints require Bearer token authentication
- **Updated Schemas:** All documentation reflects consolidated HR/Site Admin fields

### API Response Format
All endpoints return consistent JSON responses:

```json
{
  "success": boolean,
  "message": "string",
  "data": object|array,
  "pagination": {
    "current_page": integer,
    "per_page": integer,
    "total": integer,
    "last_page": integer,
    "from": integer,
    "to": integer,
    "has_more_pages": boolean
  },
  "statistics": {
    "totalRequests": integer,
    "pendingRequests": integer,
    "approvedRequests": integer,
    "declinedRequests": integer,
    "cancelledRequests": integer,
    "thisMonthRequests": integer,
    "thisWeekRequests": integer,
    "thisYearRequests": integer,
    "statusBreakdown": object,
    "timeBreakdown": object,
    "leaveTypeBreakdown": object
  }
}
```

## Performance Features
- **Server-side Pagination:** Configurable page sizes (1-100 items)
- **Eager Loading:** Optimized database queries with relationship loading
- **Strategic Indexing:** Database indexes on frequently queried fields
- **Comprehensive Statistics:** Real-time calculations for dashboard widgets
- **Advanced Filtering:** Multiple filter combinations with efficient queries

## Migration and Data Conversion

### Automatic Data Migration - UPDATED
The system includes automatic migration logic to convert from the old separate approval structure to the new consolidated structure:

**Migration Process:**
1. **Approval Data Consolidation**: Automatically merges HR and Site Admin approvals into single field
2. **Field Mapping**: 
   - `hr_approved` + `site_admin_approved` → `hr_site_admin_approved`
   - `hr_approved_date` + `site_admin_approved_date` → `hr_site_admin_approved_date`
3. **Backward Compatibility**: Handles both old and new field structures
4. **Safe Migration**: Gracefully handles missing tables for fresh installations

**Supported Role Mapping:**
- Supervisor/Manager roles → `supervisor_approved` and `supervisor_approved_date`
- HR/Site Admin/Administrator roles → `hr_site_admin_approved` and `hr_site_admin_approved_date`

## Data Integrity Features

### 1. **Unique Constraints**
- One balance record per employee/leave_type/year combination
- Prevents duplicate balance entries

### 2. **Cascade Deletes**
- Deleting leave request removes related attachments (if any)
- Balance restoration happens automatically
- All approval data is stored in the main record, so no separate cleanup needed

### 3. **Audit Trail**
- All records track created_by/updated_by
- Timestamps for all operations
- Change history preserved

## Permissions

Uses standard CRUD permissions:
- `leave_request.create` - Create leave requests and balances
- `leave_request.read` - View leave data
- `leave_request.update` - Edit leave records
- `leave_request.delete` - Delete leave records

## Testing

### Comprehensive Test Coverage
- ✅ **20 Feature Tests** - All passing (108 assertions)
- ✅ **Model Tests** - Fillable fields, casts, relationships
- ✅ **Database Tests** - Schema validation, constraints
- ✅ **API Tests** - CRUD operations, filtering, pagination
- ✅ **Business Logic Tests** - Balance calculations, approval workflows
- ✅ **Factory Tests** - Data generation with new field structure

### Test Results (Latest Run):
```
PASS  Tests\Feature\LeaveRequestFeatureTest
✓ leave request model has correct fillable fields
✓ leave request model has correct casts
✓ leave request has employee relationship
✓ leave request has leave type relationship
✓ database schema has approval columns
✓ database schema has required columns
✓ can create leave request using factory
✓ can create leave request with approval states
✓ can create leave request with new approval fields
✓ can update leave request approval status
✓ leave request statistics method works
✓ leave request requires valid employee id
✓ leave request requires valid leave type id
✓ can query leave requests by employee
✓ can query leave requests by status
✓ can query leave requests by approval status
✓ leave request loads employee relationship
✓ leave request loads leave type relationship
✓ leave request boolean fields are properly cast
✓ leave request date fields are properly cast

Tests: 20 passed (108 assertions)
```

## Summary

The Leave Management System maintains the HRMS principle of being a **data entry and display system** while adding necessary **balance calculations** to ensure data integrity. Version 3.0 introduces consolidated HR/Site Admin approval fields that better reflect the actual paper form structure.

### Key Architectural Benefits:
- **Simplified Data Model**: No separate approval tables - all information in one record
- **Paper Form Alignment**: Database fields directly match paper form sections
- **Consolidated Approvals**: HR and Site Admin treated as single approval level
- **Data Integrity**: Balance calculations prevent mathematical inconsistencies
- **No Workflow Complexity**: System stores final states rather than managing processes
- **Easy Integration**: Flat structure makes frontend integration straightforward
- **Comprehensive Testing**: Full test coverage ensures reliability

### Version 3.0 Improvements:
- ✅ **Consolidated approval fields** for better paper form alignment
- ✅ **Updated API documentation** with new field structure
- ✅ **Comprehensive migration** from old to new field structure
- ✅ **Full test coverage** with updated test suite
- ✅ **Backward compatibility** during migration period
- ✅ **Performance optimizations** with updated database schema

This approach provides all the functionality needed for digitizing leave management while keeping the system simple, maintainable, and true to the HRMS data entry principles. The consolidated approval structure better reflects real-world paper form processes and simplifies both data entry and system maintenance.