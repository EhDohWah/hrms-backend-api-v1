# Multi-Leave-Type Feature Implementation

**Version:** 2.0
**Date:** October 21, 2025
**Status:** ✅ Implemented and Production Ready

## Overview

The Leave Management System has been upgraded to support **multiple leave types per request**, matching the actual paper form workflow where employees can check multiple leave types in a single submission.

## What Changed

### Before (Version 1.0)
- One leave request = One leave type
- Direct `leave_type_id` column in `leave_requests` table
- Simple one-to-one relationship

### After (Version 2.0)
- One leave request = **Multiple leave types**
- New `leave_request_items` table for leave type details
- One-to-many relationship through items

---

## Database Architecture

### New Table: `leave_request_items`

```sql
CREATE TABLE leave_request_items (
    id BIGINT PRIMARY KEY,
    leave_request_id BIGINT NOT NULL,
    leave_type_id BIGINT NOT NULL,
    days DECIMAL(8,2) NOT NULL,
    created_at DATETIME,
    updated_at DATETIME,

    -- Foreign keys
    FOREIGN KEY (leave_request_id) REFERENCES leave_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (leave_type_id) REFERENCES leave_types(id) ON DELETE NO ACTION,

    -- Constraints
    UNIQUE (leave_request_id, leave_type_id),
    INDEX (leave_request_id, leave_type_id)
);
```

**Key Features:**
- **Cascade delete**: When a leave request is deleted, all items are automatically deleted
- **Unique constraint**: Prevents duplicate leave types in one request
- **Performance index**: Optimizes queries joining requests with items

### Updated Table: `leave_requests`

**Removed:**
- ❌ `leave_type_id` column

**Unchanged:**
- ✅ `employee_id`
- ✅ `start_date`, `end_date`
- ✅ `total_days` (now sum of all item days)
- ✅ `status`, `reason`
- ✅ All approval fields (`supervisor_approved`, `hr_site_admin_approved`, etc.)

---

## API Changes

### 1. Create Leave Request (POST /api/v1/leaves/requests)

**New Request Format:**

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

**New Response Format:**

```json
{
  "success": true,
  "message": "Leave request created successfully",
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

### 2. Update Leave Request (PUT /api/v1/leaves/requests/{id})

**Now Supports Item Updates:**

```json
{
  "status": "approved",
  "items": [
    {
      "leave_type_id": 1,
      "days": 3
    },
    {
      "leave_type_id": 3,
      "days": 2
    }
  ]
}
```

**Behavior:**
- If `items` array is provided, old items are replaced with new ones
- Balances are automatically restored and recalculated
- `total_days` is automatically recalculated from items

### 3. Get Leave Request (GET /api/v1/leaves/requests/{id})

**Response now includes `items` array** with full leave type details (see example above).

### 4. List Leave Requests (GET /api/v1/leaves/requests)

All requests now include eagerly loaded `items.leaveType` relationships for optimal performance.

---

## Business Logic

### Balance Validation

**Per-Leave-Type Checking:**
```
Before approval, system checks EACH leave type separately:
- Annual Leave: 2 days requested, 10 days available ✓
- Sick Leave: 1.5 days requested, 5 days available ✓
```

**Error Response if Insufficient:**
```json
{
  "success": false,
  "message": "Insufficient balance for Sick Leave: Insufficient leave balance.",
  "data": {
    "leave_type": "Sick Leave",
    "available_days": 0.5,
    "requested_days": 1.5,
    "shortfall": 1.0
  }
}
```

### Balance Deduction

When a request is **approved**, the system:
1. Deducts balance for **each** leave type separately
2. Updates `used_days` and `remaining_days` for each type

Example:
```
Annual Leave balance: 10 days → 8 days (deducted 2)
Sick Leave balance: 5 days → 3.5 days (deducted 1.5)
```

### Balance Restoration

When a request is **deleted** or **cancelled**, the system:
1. Restores balance for **all** leave types in the request
2. Ensures `used_days` never goes below 0

---

## Validation Rules

### Creating Leave Request

```php
'items' => 'required|array|min:1',
'items.*.leave_type_id' => 'required|exists:leave_types,id',
'items.*.days' => 'required|numeric|min:0.5',
```

**Business Rules:**
1. ✅ Minimum 1 leave type required
2. ✅ No duplicate leave types in one request
3. ✅ Each leave type must have positive days (≥ 0.5)
4. ✅ If ANY leave type requires attachment, `attachment_notes` is required
5. ✅ Total balance check for EACH leave type

### Updating Leave Request

- Items array is **optional** (if not provided, items remain unchanged)
- Same validation rules apply if items are provided
- Old items are **deleted** and replaced with new items

---

## Data Migration

### Automatic Migration

The migration automatically converts existing data:

```
Old Structure:
leave_requests (id=1, leave_type_id=1, total_days=5)

New Structure:
leave_requests (id=1, total_days=5)  -- leave_type_id removed
leave_request_items (id=1, leave_request_id=1, leave_type_id=1, days=5)
```

**Migration Steps:**
1. Create `leave_request_items` table
2. Copy data from `leave_requests.leave_type_id` to new items table
3. Drop `leave_type_id` column from `leave_requests`

**Rollback Support:**
```bash
php artisan migrate:rollback
```
This will restore `leave_type_id` column and move data back.

---

## Code Examples

### Creating a Leave Request with Multiple Types

```php
use App\Models\LeaveRequest;
use App\Models\LeaveRequestItem;

$leaveRequest = LeaveRequest::create([
    'employee_id' => 123,
    'start_date' => '2025-01-15',
    'end_date' => '2025-01-17',
    'total_days' => 3.5,
    'reason' => 'Mixed leave request',
    'status' => 'pending',
]);

// Create items
LeaveRequestItem::create([
    'leave_request_id' => $leaveRequest->id,
    'leave_type_id' => 1, // Annual Leave
    'days' => 2,
]);

LeaveRequestItem::create([
    'leave_request_id' => $leaveRequest->id,
    'leave_type_id' => 2, // Sick Leave
    'days' => 1.5,
]);
```

### Querying Leave Requests with Items

```php
use App\Models\LeaveRequest;

// Get request with items
$request = LeaveRequest::with('items.leaveType')->find(1);

// Access items
foreach ($request->items as $item) {
    echo "{$item->leaveType->name}: {$item->days} days\n";
}

// Calculate total days
$totalDays = $request->items->sum('days');
```

### Using Factory

```php
use App\Models\LeaveRequest;
use App\Models\LeaveType;

// Factory automatically creates one default item
$request = LeaveRequest::factory()->create();

// Or create with specific items manually
$request = LeaveRequest::factory()->create();
$request->items()->createMany([
    ['leave_type_id' => 1, 'days' => 2],
    ['leave_type_id' => 2, 'days' => 1.5],
]);
```

---

## Testing

### Key Test Scenarios

1. **✅ Creating request with multiple leave types**
2. **✅ Preventing duplicate leave types in one request**
3. **✅ Balance validation for each leave type**
4. **✅ Balance deduction for each leave type separately**
5. **✅ Balance restoration when deleting/cancelling**
6. **✅ Updating items (replacing old with new)**
7. **✅ Cascade delete of items when request is deleted**
8. **✅ Attachment requirement if ANY type requires it**

### Example Test

```php
public function test_can_create_leave_request_with_multiple_types()
{
    $employee = Employee::factory()->create();
    $annualLeave = LeaveType::where('name', 'Annual Leave')->first();
    $sickLeave = LeaveType::where('name', 'Sick Leave')->first();

    // Create balances
    LeaveBalance::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $annualLeave->id,
        'total_days' => 26,
        'year' => 2025,
    ]);

    LeaveBalance::factory()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $sickLeave->id,
        'total_days' => 30,
        'year' => 2025,
    ]);

    $response = $this->postJson('/api/v1/leaves/requests', [
        'employee_id' => $employee->id,
        'start_date' => '2025-03-01',
        'end_date' => '2025-03-03',
        'reason' => 'Mixed leave',
        'status' => 'approved',
        'items' => [
            ['leave_type_id' => $annualLeave->id, 'days' => 2],
            ['leave_type_id' => $sickLeave->id, 'days' => 1],
        ],
    ]);

    $response->assertStatus(201);
    $response->assertJsonPath('data.total_days', 3);
    $response->assertJsonCount(2, 'data.items');
}
```

---

## Frontend Integration Guide

### Updating the Create Form

**Before:**
```jsx
<Select name="leave_type_id" />
<Input name="total_days" />
```

**After:**
```jsx
<LeaveItemsBuilder
  items={items}
  onChange={setItems}
  leaveTypes={leaveTypes}
/>
// Items structure: [{ leave_type_id: 1, days: 2 }, ...]
// Total days calculated automatically: items.reduce((sum, item) => sum + item.days, 0)
```

### Displaying Leave Requests

```jsx
{request.items.map(item => (
  <div key={item.id}>
    <Badge>{item.leave_type.name}</Badge>
    <span>{item.days} days</span>
  </div>
))}
<div>Total: {request.total_days} days</div>
```

---

## Error Messages

### Duplicate Leave Types
```json
{
  "success": false,
  "message": "Duplicate leave types are not allowed in a single request"
}
```

### Insufficient Balance
```json
{
  "success": false,
  "message": "Insufficient balance for Annual Leave: Insufficient leave balance.",
  "data": {
    "leave_type": "Annual Leave",
    "available_days": 5,
    "requested_days": 10,
    "shortfall": 5
  }
}
```

### Missing Attachment
```json
{
  "success": false,
  "message": "This request includes leave types that require attachment notes: Sick Leave, Sterilization leave"
}
```

---

## Performance Optimizations

1. **Eager Loading**: All queries use `with('items.leaveType')` to prevent N+1 queries
2. **Database Indexes**: Composite index on `(leave_request_id, leave_type_id)` for fast lookups
3. **Batch Operations**: Balance updates use individual queries per type (optimized for accuracy)
4. **Cascade Delete**: Database-level cascade for automatic item cleanup

---

## Breaking Changes

### API Breaking Changes

1. **Request Payload**: Must now include `items` array instead of `leave_type_id`
2. **Response Structure**: Responses now include `items` array instead of `leave_type` object

### Backward Compatibility

- **Migration**: Automatic and safe - converts existing data
- **Factory**: Still works, auto-creates a default item
- **Old `leaveType()` relationship**: Deprecated but not removed (for gradual migration)

---

## Deployment Checklist

- [x] Run migration: `php artisan migrate`
- [x] Test API endpoints with Postman/cURL
- [ ] Update frontend to use new `items` array structure
- [ ] Update any automated scripts/jobs that create leave requests
- [ ] Notify users about new multi-leave-type capability
- [ ] Update user documentation/training materials

---

## Support & Troubleshooting

### Common Issues

**Issue**: "Cannot create leave request - validation error"
**Solution**: Ensure `items` array is provided with at least one item

**Issue**: "Duplicate leave types error"
**Solution**: Check that `items` array doesn't contain the same `leave_type_id` multiple times

**Issue**: "Balance deduction not working"
**Solution**: Verify request status is "approved" - balances only deduct on approval

### Database Queries

**Find requests with multiple leave types:**
```sql
SELECT leave_request_id, COUNT(*) as types_count
FROM leave_request_items
GROUP BY leave_request_id
HAVING COUNT(*) > 1;
```

**Check balance consistency:**
```sql
SELECT lb.*,
       SUM(lri.days) as total_used_from_items
FROM leave_balances lb
LEFT JOIN leave_request_items lri ON lri.leave_type_id = lb.leave_type_id
LEFT JOIN leave_requests lr ON lr.id = lri.leave_request_id AND lr.status = 'approved'
WHERE lb.year = 2025
GROUP BY lb.id;
```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 2.0 | 2025-10-21 | **Multi-leave-type support implemented** - New `leave_request_items` table, API updates, automatic migration |
| 1.0 | 2025-03-16 | Initial leave management system with single leave type per request |

---

**End of Documentation**

For questions or support, please contact the development team or refer to the main Leave Management System documentation.
