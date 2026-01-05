I need to redesign my Laravel Leave Management System to support multiple leave types per request, matching the actual paper form workflow. Currently, the system only allows one leave type per request, but the paper form allows employees to check multiple leave types in a single submission.

## Current System Architecture

**Database Schema (Current):**
- `leave_requests` table with `leave_type_id` (single type only)
- `leave_types` table (pre-seeded with 11 types)
- `leave_balances` table (tracks employee balances per leave type per year)

**Current Model Relationships:**
- LeaveRequest belongsTo Employee
- LeaveRequest belongsTo LeaveType (single)
- LeaveRequest uses consolidated approval fields: `supervisor_approved`, `hr_site_admin_approved`

**Existing Files:**
- Migration: `database/migrations/2025_03_16_021936_create_leave_management_tables.php`
- Model: `app/Models/LeaveRequest.php`
- Controller: `app/Http/Controllers/Api/LeaveManagementController.php`
- Routes: `routes/api.php` (prefix: `/api/v1/leaves`)
- Tests: `tests/Feature/LeaveRequestFeatureTest.php`

## Required Solution

Implement a **one-to-many relationship** where:
1. One `leave_request` can have multiple `leave_request_items`
2. Each `leave_request_item` links to a specific `leave_type` with its own `days` value
3. The main `leave_request.total_days` is the sum of all items
4. Balance validation checks ALL leave types before approval
5. Balance deduction happens for EACH leave type separately

## Implementation Requirements

### 1. Database Changes

**Create new migration** for `leave_request_items` table:
- Fields: `id`, `leave_request_id` (FK), `leave_type_id` (FK), `days` (decimal 8,2), `timestamps`
- Unique constraint on `(leave_request_id, leave_type_id)` to prevent duplicate types in one request
- Cascade delete when parent request is deleted
- Restrict delete if leave type is in use

**Update existing migration** `2025_03_16_021936_create_leave_management_tables.php`:
- Remove `leave_type_id` column from `leave_requests` table
- Keep all other fields unchanged (approval fields, dates, status, etc.)

### 2. Model Updates

**Create new model** `app/Models/LeaveRequestItem.php`:
- Fillable: `leave_request_id`, `leave_type_id`, `days`
- Cast `days` as `decimal:2`
- Relationships: `belongsTo(LeaveRequest)`, `belongsTo(LeaveType)`

**Update** `app/Models/LeaveRequest.php`:
- Add relationship: `hasMany(LeaveRequestItem::class, 'leave_request_id')`
- Update fillable array (remove `leave_type_id` if present)
- Add accessor for calculating total days from items if needed

### 3. Controller Logic Updates

**Update** `app/Http/Controllers/Api/LeaveManagementController.php`:

**store() method:**
- Accept `items` array in request: `items.*.leave_type_id`, `items.*.days`
- Validate minimum 1 item, each with valid leave type and positive days
- Calculate `total_days` by summing all item days
- Validate balance for EACH leave type before creating request
- Create main `LeaveRequest` record
- Create multiple `LeaveRequestItem` records in a loop
- If status is "approved", deduct balance for EACH leave type
- Wrap everything in DB transaction
- Return response with loaded `items.leaveType` relationship

**update() method:**
- Support updating items array
- Handle balance restoration for old items if status changes from approved
- Handle balance deduction for new items if status is approved
- Allow adding/removing leave types from existing request
- Recalculate total_days when items change
- Maintain data integrity with transactions

**show() method:**
- Eager load `items.leaveType` relationship
- Include item details in response

**index() method:**
- Eager load `items.leaveType` relationship for all requests
- Update statistics calculation if needed

**destroy() method:**
- Restore balances for ALL leave types in the request items
- Cascade delete handles items automatically

**Add new private helper methods:**
- `deductLeaveBalance(int $employeeId, int $leaveTypeId, float $days)`: Deduct balance for specific type
- `restoreLeaveBalance(int $employeeId, int $leaveTypeId, float $days)`: Restore balance for specific type
- Update existing `checkLeaveBalance()` to work with individual items

### 4. API Request/Response Format

**Request format for POST /api/v1/leaves/requests:**
```json
{
  "employee_id": 123,
  "start_date": "2025-01-15",
  "end_date": "2025-01-17",
  "reason": "Family emergency and medical checkup",
  "status": "approved",
  "items": [
    {
      "leave_type_id": 1,
      "days": 2
    },
    {
      "leave_type_id": 2,
      "days": 1.5
    }
  ],
  "supervisor_approved": true,
  "supervisor_approved_date": "2025-01-10",
  "hr_site_admin_approved": true,
  "hr_site_admin_approved_date": "2025-01-12",
  "attachment_notes": "Medical certificate attached"
}
```

**Response format:**
```json
{
  "success": true,
  "message": "Leave request created successfully with 2 leave types",
  "data": {
    "id": 1,
    "employee_id": 123,
    "start_date": "2025-01-15",
    "end_date": "2025-01-17",
    "total_days": 3.5,
    "status": "approved",
    "employee": {
      "id": 123,
      "staff_id": "EMP001",
      "first_name_en": "John",
      "last_name_en": "Doe"
    },
    "items": [
      {
        "id": 1,
        "leave_request_id": 1,
        "leave_type_id": 1,
        "days": 2,
        "leave_type": {
          "id": 1,
          "name": "Annual Leave",
          "requires_attachment": false
        }
      },
      {
        "id": 2,
        "leave_request_id": 1,
        "leave_type_id": 2,
        "days": 1.5,
        "leave_type": {
          "id": 2,
          "name": "Sick Leave",
          "requires_attachment": true
        }
      }
    ]
  }
}
```

### 5. Testing Updates

**Update** `tests/Feature/LeaveRequestFeatureTest.php`:
- Add tests for creating requests with multiple leave types
- Test balance validation for multiple types
- Test balance deduction for each type
- Test update scenarios (adding/removing items)
- Test that duplicate leave types in one request are rejected
- Test cascade delete of items when request is deleted
- Ensure all 20+ existing tests still pass

### 6. Factory Updates

**Update** `database/factories/LeaveRequestFactory.php`:
- Remove `leave_type_id` from definition
- Update factory to work with new structure (items created separately if needed)

### 7. Data Migration Strategy

**Create data migration script** for existing records:
- For each existing `leave_request` with `leave_type_id`:
  - Create one `leave_request_item` with same leave type and total days
  - This ensures backward compatibility with existing data
- Run in transaction with rollback on error
- Log any issues

## Constraints and Business Rules

1. **Balance Validation:** Before approving, check that EACH leave type has sufficient balance
2. **Atomic Operations:** All database operations must be wrapped in transactions
3. **Balance Integrity:** Never allow negative balances
4. **Attachment Requirements:** If ANY leave type requires attachment, enforce `attachment_notes`
5. **Status Changes:** 
   - `pending` → `approved`: Deduct all item balances
   - `approved` → `declined/cancelled`: Restore all item balances
6. **Unique Constraint:** One request cannot have duplicate leave types
7. **Minimum Items:** Request must have at least 1 leave type item

## Error Handling

Return appropriate error responses for:
- Insufficient balance for any leave type (specify which type)
- Invalid leave type IDs
- Duplicate leave types in items array
- Missing required attachment notes
- Negative remaining balance scenarios

## Code Quality Requirements

- Follow Laravel conventions and PSR-12 standards
- Use type hints for all parameters and return types
- Add PHPDoc blocks for all methods
- Use meaningful variable names
- Keep methods focused (single responsibility)
- Use early returns for validation failures
- Prefer explicit over implicit logic

## Important Notes

- Keep existing approval workflow (supervisor + HR/Site Admin consolidated fields)
- Maintain all existing audit fields (`created_by`, `updated_by`, timestamps)
- Preserve existing API endpoints (don't break frontend compatibility)
- Keep all existing filters and search functionality working
- Don't modify `leave_types` or `leave_balances` table structure
- Maintain OpenAPI/Swagger documentation compatibility

## Expected Deliverables

1. New migration file for `leave_request_items` table
2. Updated migration file for `leave_requests` table (remove `leave_type_id`)
3. New `LeaveRequestItem` model
4. Updated `LeaveRequest` model with relationship
5. Updated `LeaveManagementController` with all CRUD methods
6. Updated test suite with new test cases
7. Data migration script for existing records
8. Updated factory if needed

Please implement this solution ensuring:
- All existing tests pass
- New functionality is fully tested
- Code follows Laravel best practices
- Database transactions protect data integrity
- Clear error messages for all failure scenarios
- Backward compatibility during migration period

If you need any clarification on the existing code structure or business rules, please ask before implementing.