# Leave Management Controller Documentation

## Overview

The `NewLeaveManagementController` is a comprehensive Laravel controller that provides a complete leave management system with advanced features including pagination, filtering, sorting, file attachments, approval workflows, and automatic balance management.

## Features

### 🔄 **Server-side Pagination**
- Consistent pagination pattern following EmployeeController and GrantController
- Configurable items per page (1-100, default: 10)
- Complete pagination metadata in responses

### 🔍 **Advanced Filtering**
- **Date Range**: Filter leave requests by start/end date ranges
- **Leave Types**: Filter by comma-separated leave type IDs
- **Search**: Search by employee staff ID, first name, or last name
- **Status**: Filter by request status (pending, approved, declined, cancelled)

### 📊 **Sorting Options**
- `recently_added`: Sort by created_at DESC (default)
- `ascending`: Sort by start_date ASC
- `descending`: Sort by start_date DESC  
- `last_month`: Filter past month + sort by created_at DESC
- `last_7_days`: Filter past 7 days + sort by created_at DESC

### 💼 **Business Logic**
- **Auto-deduct leave balance** when request is approved
- **Restore balance** when approved request is declined/cancelled
- **Prevent negative balances** with validation
- **Create initial approval records** for new requests
- **Evaluate overall status** based on all approvals
- **File attachment handling** with proper validation

## API Endpoints

### Leave Requests

#### `GET /api/leave-requests`
Get paginated leave requests with advanced filtering.

**Query Parameters:**
- `page` (integer): Page number
- `per_page` (integer): Items per page (1-100)
- `search` (string): Search by staff ID or employee name
- `from` (date): Start date filter (YYYY-MM-DD)
- `to` (date): End date filter (YYYY-MM-DD)
- `leave_types` (string): Comma-separated leave type IDs
- `status` (string): Request status filter
- `sort_by` (string): Sort option

**Example Request:**
```bash
GET /api/leave-requests?page=1&per_page=10&search=EMP001&from=2024-01-01&to=2024-12-31&leave_types=1,2&status=pending&sort_by=recently_added
```

**Response:**
```json
{
    "success": true,
    "message": "Leave requests retrieved successfully",
    "data": [...],
    "pagination": {
        "current_page": 1,
        "per_page": 10,
        "total": 50,
        "last_page": 5,
        "from": 1,
        "to": 10,
        "has_more_pages": true
    },
    "stats": {
        "total_requests": 50,
        "pending_requests": 15,
        "approved_requests": 25,
        "declined_requests": 5,
        "cancelled_requests": 5,
        "this_month": 10,
        "this_week": 3
    }
}
```

#### `GET /api/leave-requests/{id}`
Get a single leave request with full relationships.

#### `POST /api/leave-requests`
Create a new leave request with file attachments.

**Request Body (multipart/form-data):**
```json
{
    "employee_id": 1,
    "leave_type_id": 2,
    "start_date": "2024-03-01",
    "end_date": "2024-03-05",
    "total_days": 5,
    "reason": "Family vacation",
    "attachments": [file1, file2]
}
```

**Validation Rules:**
- `employee_id`: Required, must exist in employees table
- `leave_type_id`: Required, must exist in leave_types table
- `start_date`: Required, must be today or future date
- `end_date`: Required, must be >= start_date
- `total_days`: Required, numeric, minimum 0.5
- `attachments`: Optional, max 5 files, 2MB each, specific formats only

#### `PUT /api/leave-requests/{id}`
Update a leave request with automatic balance handling.

#### `DELETE /api/leave-requests/{id}`
Delete a leave request with balance restoration.

### Leave Types

#### `GET /api/leave-types`
Get paginated leave types with search functionality.

#### `POST /api/leave-types`
Create a new leave type.

**Request Body:**
```json
{
    "name": "Annual Leave",
    "default_duration": 21,
    "description": "Annual vacation leave",
    "requires_attachment": false
}
```

#### `PUT /api/leave-types/{id}`
Update a leave type.

#### `DELETE /api/leave-types/{id}`
Delete a leave type (prevents deletion if in use).

### Leave Balances

#### `GET /api/leave-balances`
Get leave balances with filtering by employee, leave type, and year.

**Query Parameters:**
- `employee_id` (integer): Filter by specific employee
- `leave_type_id` (integer): Filter by specific leave type
- `year` (integer): Filter by year (defaults to current year)
- `search` (string): Search by employee details

#### `POST /api/leave-balances`
Create a leave balance with automatic remaining days calculation.

**Request Body:**
```json
{
    "employee_id": 1,
    "leave_type_id": 2,
    "total_days": 21,
    "year": 2024
}
```

#### `PUT /api/leave-balances/{id}`
Update a leave balance with automatic remaining_days calculation.

### Approvals

#### `GET /api/leave-requests/{leaveRequestId}/approvals`
Get all approvals for a specific leave request.

#### `POST /api/leave-requests/{leaveRequestId}/approvals`
Create an approval for a leave request.

**Request Body:**
```json
{
    "approver_role": "HR Manager",
    "approver_name": "John Smith",
    "approver_signature": "J.Smith",
    "status": "approved"
}
```

#### `PUT /api/approvals/{id}`
Update an approval with automatic leave request status evaluation.

## Business Logic Details

### Balance Management
1. **Creation**: When creating a leave request, system checks available balance
2. **Approval**: When request is approved, balance is automatically deducted
3. **Status Changes**: When approved request is declined/cancelled, balance is restored
4. **Deletion**: When approved request is deleted, balance is restored

### Approval Workflow
1. **Initial Creation**: New requests get initial approval record with "pending" status
2. **Multiple Approvers**: System supports multiple approval levels
3. **Status Evaluation**: Overall request status determined by approval statuses:
   - Any "declined" approval → Request becomes "declined"
   - All "approved" approvals → Request becomes "approved"
   - Otherwise → Request remains "pending"

### File Attachments
1. **Validation**: Files validated for type, size, and count
2. **Storage**: Files stored in `public/leave_attachments` directory
3. **Cleanup**: Files automatically deleted when request is deleted
4. **Requirements**: Some leave types require mandatory attachments

## Error Handling

All methods include comprehensive error handling with:
- Try-catch blocks for exception handling
- Database transactions for data consistency
- Proper HTTP status codes
- Detailed error messages
- Logging for debugging

## Performance Optimizations

### Database Queries
- **Eager Loading**: Specific field selection to minimize data transfer
- **Indexes**: Strategic indexes on commonly queried fields
- **Query Optimization**: Prevents N+1 query problems

### Caching Strategy
- Statistics calculations optimized for performance
- Query result optimization through proper relationships

## Security Features

### Validation
- Comprehensive input validation using Laravel validation rules
- File upload security with type and size restrictions
- Foreign key constraint validation

### Authorization
- All routes protected with `auth:sanctum` middleware
- User context tracking with `created_by` and `updated_by` fields

## Response Format

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
    "stats": {
        "total_requests": integer,
        "pending_requests": integer,
        "approved_requests": integer,
        "declined_requests": integer,
        "cancelled_requests": integer,
        "this_month": integer,
        "this_week": integer
    }
}
```

## Usage Examples

### Creating a Leave Request with Attachments

```javascript
const formData = new FormData();
formData.append('employee_id', '1');
formData.append('leave_type_id', '2');
formData.append('start_date', '2024-03-01');
formData.append('end_date', '2024-03-05');
formData.append('total_days', '5');
formData.append('reason', 'Family vacation');
formData.append('attachments[]', file1);
formData.append('attachments[]', file2);

fetch('/api/leave-requests', {
    method: 'POST',
    headers: {
        'Authorization': 'Bearer ' + token
    },
    body: formData
});
```

### Advanced Filtering

```javascript
const params = new URLSearchParams({
    page: '1',
    per_page: '20',
    search: 'EMP001',
    from: '2024-01-01',
    to: '2024-12-31',
    leave_types: '1,2,3',
    status: 'pending',
    sort_by: 'recently_added'
});

fetch(`/api/leave-requests?${params}`);
```

## Integration Notes

### Frontend Integration
- Use the pagination metadata to build pagination controls
- Implement real-time search with debouncing
- Show statistics in dashboard widgets
- Handle file uploads with progress indicators

### Database Integration
- Ensure proper foreign key relationships are maintained
- Run migrations for leave management tables
- Set up proper indexes for performance

### Testing
- Unit tests for business logic methods
- Feature tests for API endpoints
- File upload testing with various scenarios
- Balance calculation testing

## Maintenance

### Monitoring
- Monitor file storage usage for attachments
- Track approval workflow performance
- Monitor balance calculation accuracy

### Cleanup
- Implement periodic cleanup of orphaned files
- Archive old leave requests based on retention policy
- Maintain balance history for audit purposes

This controller provides a production-ready leave management system with enterprise-level features and robust error handling.
