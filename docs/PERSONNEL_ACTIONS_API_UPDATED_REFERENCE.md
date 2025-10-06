# Personnel Actions API - Updated Reference Guide

**Date:** October 2, 2025  
**Version:** 2.0 (Foreign Key Based)

---

## Quick Migration Guide for Frontend Developers

### ⚠️ BREAKING CHANGES

The Personnel Actions API now uses **foreign key IDs** instead of text fields for departments, positions, and locations.

### Field Name Changes

| Old Field (v1.0)      | New Field (v2.0)          | Type    |
|-----------------------|---------------------------|---------|
| `current_position`    | `current_position_id`     | integer |
| `current_title`       | *(removed)*               | -       |
| `current_department`  | `current_department_id`   | integer |
| `new_position`        | `new_position_id`         | integer |
| `new_job_title`       | *(removed)*               | -       |
| `new_department`      | `new_department_id`       | integer |
| `new_location`        | `new_work_location_id`    | integer |

---

## API Endpoints (Unchanged)

```
GET    /api/v1/personnel-actions              [List]
GET    /api/v1/personnel-actions/constants    [Get Constants]
GET    /api/v1/personnel-actions/{id}         [Show]
POST   /api/v1/personnel-actions              [Create]
PUT    /api/v1/personnel-actions/{id}         [Update]
PATCH  /api/v1/personnel-actions/{id}         [Update]
PATCH  /api/v1/personnel-actions/{id}/approve [Approve]
```

---

## Updated Request Examples

### ✅ Create Personnel Action (Promotion)

**Endpoint:** `POST /api/v1/personnel-actions`

```json
{
    "employment_id": 15,
    "effective_date": "2025-11-01",
    "action_type": "position_change",
    "action_subtype": "promotion",
    "new_position_id": 42,           // ✅ Use position ID
    "new_department_id": 5,          // ✅ Use department ID
    "new_work_location_id": 3,       // ✅ Use work location ID
    "new_salary": 65000.00,
    "comments": "Annual performance promotion",
    "dept_head_approved": false,
    "coo_approved": false,
    "hr_approved": false,
    "accountant_approved": false
}
```

### ✅ Create Transfer Action

**Endpoint:** `POST /api/v1/personnel-actions`

```json
{
    "employment_id": 20,
    "effective_date": "2025-12-01",
    "action_type": "transfer",
    "is_transfer": true,
    "transfer_type": "internal_department",
    "new_department_id": 7,          // ✅ Required for transfers
    "new_position_id": 28,           // ✅ Optional
    "new_work_location_id": 2,       // ✅ Optional
    "comments": "Transfer to Finance department"
}
```

### ✅ Create Fiscal Increment

**Endpoint:** `POST /api/v1/personnel-actions`

```json
{
    "employment_id": 18,
    "effective_date": "2025-10-01",
    "action_type": "fiscal_increment",
    "action_subtype": "re_evaluated_pay_adjustment",
    "new_salary": 58000.00,          // ✅ Required for fiscal increments
    "comments": "Annual salary adjustment"
}
```

---

## Updated Response Format

All responses now include **relationship objects** with full details:

```json
{
    "success": true,
    "message": "Personnel action created successfully",
    "data": {
        "id": 1,
        "form_number": "SMRU-SF038",
        "reference_number": "PA-2025-000001",
        "employment_id": 15,
        
        // Current state (IDs + Relationships)
        "current_employee_no": "EMP-001",
        "current_department_id": 4,
        "current_position_id": 38,
        "current_work_location_id": 1,
        "current_salary": "50000.00",
        "current_employment_date": "2024-01-15",
        
        // New state (IDs + Relationships)
        "new_department_id": 5,
        "new_position_id": 42,
        "new_work_location_id": 3,
        "new_salary": "65000.00",
        
        // Action details
        "effective_date": "2025-11-01",
        "action_type": "position_change",
        "action_subtype": "promotion",
        "is_transfer": false,
        "transfer_type": null,
        
        // Approvals
        "dept_head_approved": false,
        "coo_approved": false,
        "hr_approved": false,
        "accountant_approved": false,
        
        // Comments
        "comments": "Annual performance promotion",
        "change_details": null,
        
        // Timestamps
        "created_at": "2025-10-02T10:30:00.000000Z",
        "updated_at": "2025-10-02T10:30:00.000000Z",
        
        // ✅ NEW: Relationship objects included
        "current_department": {
            "id": 4,
            "name": "IT Department",
            "description": "Information Technology",
            "is_active": true
        },
        "current_position": {
            "id": 38,
            "title": "Developer",
            "department_id": 4,
            "level": 3,
            "is_manager": false,
            "is_active": true
        },
        "current_work_location": {
            "id": 1,
            "name": "Head Office",
            "address": "123 Main St",
            "is_active": true
        },
        "new_department": {
            "id": 5,
            "name": "Engineering",
            "description": "Engineering Department",
            "is_active": true
        },
        "new_position": {
            "id": 42,
            "title": "Senior Developer",
            "department_id": 5,
            "level": 4,
            "is_manager": false,
            "is_active": true
        },
        "new_work_location": {
            "id": 3,
            "name": "Branch Office",
            "address": "456 Oak Ave",
            "is_active": true
        },
        "employment": {
            "id": 15,
            "employee_id": 10,
            "employment_type": "Full-time",
            "start_date": "2024-01-15",
            "position_salary": "50000.00",
            "employee": {
                "id": 10,
                "staff_id": "EMP-001",
                "first_name_en": "John",
                "last_name_en": "Doe",
                "subsidiary": "SMRU",
                "status": "Local ID"
            }
        },
        "creator": {
            "id": 1,
            "name": "HR Manager",
            "email": "hr@example.com"
        }
    }
}
```

---

## Validation Rules

### Required Fields
- `employment_id` - Must exist in employments table
- `effective_date` - Must be today or future date
- `action_type` - Must be valid action type

### Conditional Required Fields
| Field                  | Required When                        |
|------------------------|--------------------------------------|
| `new_position_id`      | action_type = `position_change`      |
| `new_department_id`    | action_type = `transfer`             |
| `new_salary`           | action_type = `fiscal_increment`     |
| `transfer_type`        | is_transfer = `true`                 |

### Position-Department Validation
**Important:** When providing both `new_position_id` and `new_department_id`, the position **must belong to** the specified department. Otherwise, you'll get:

```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "new_position_id": [
            "The selected position must belong to the selected department."
        ]
    }
}
```

---

## How to Get Valid IDs

### 1. Get Departments
```
GET /api/v1/departments
```

### 2. Get Positions (by Department)
```
GET /api/v1/positions?department_id=5
```

### 3. Get Work Locations
```
GET /api/v1/work-locations
```

---

## Auto-Population Feature

**Current employment data is auto-populated!**

If you don't provide `current_department_id`, `current_position_id`, etc., the system automatically fills them from the linked employment record.

**This means:**
```json
// You can send just this:
{
    "employment_id": 15,
    "effective_date": "2025-11-01",
    "action_type": "fiscal_increment",
    "new_salary": 58000.00
}

// System auto-fills:
// current_employee_no
// current_department_id
// current_position_id
// current_work_location_id
// current_salary
// current_employment_date
```

---

## Approval Workflow

### Update Single Approval
**Endpoint:** `PATCH /api/v1/personnel-actions/{id}/approve`

```json
{
    "approval_type": "dept_head",
    "approved": true
}
```

**Valid approval types:**
- `dept_head`
- `coo`
- `hr`
- `accountant`

### Automatic Implementation
When **all 4 approvals** are `true`, the system automatically:
1. ✅ Updates the employment record with new values
2. ✅ Creates employment history entry
3. ✅ Clears employment caches
4. ✅ Returns updated personnel action

---

## Error Handling

### 422 Validation Errors
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "new_department_id": ["Selected department does not exist."],
        "new_position_id": ["The selected position must belong to the selected department."]
    }
}
```

### 500 Server Errors
```json
{
    "success": false,
    "message": "Failed to create personnel action: [error details]"
}
```

---

## Migration Checklist for Frontend

- [ ] Update create form to use ID selects instead of text inputs
- [ ] Add department dropdown
- [ ] Add position dropdown (filtered by selected department)
- [ ] Add work location dropdown
- [ ] Update validation to check position belongs to department
- [ ] Update response handling to use relationship objects
- [ ] Remove references to old text fields
- [ ] Test all action types (appointment, position_change, transfer, etc.)
- [ ] Test approval workflow
- [ ] Test error handling

---

## Constants Endpoint

**Endpoint:** `GET /api/v1/personnel-actions/constants`

Returns all valid constants for dropdowns:

```json
{
    "success": true,
    "data": {
        "action_types": {
            "appointment": "Appointment",
            "fiscal_increment": "Fiscal Increment",
            "title_change": "Title Change",
            "voluntary_separation": "Voluntary Separation",
            "position_change": "Position Change",
            "transfer": "Transfer"
        },
        "action_subtypes": {
            "re_evaluated_pay_adjustment": "Re-Evaluated Pay Adjustment",
            "promotion": "Promotion",
            "demotion": "Demotion",
            "end_of_contract": "End of Contract",
            "work_allocation": "Work Allocation"
        },
        "transfer_types": {
            "internal_department": "Internal Department",
            "site_to_site": "From Site to Site",
            "attachment_position": "Attachment Position"
        },
        "statuses": {
            "pending": "Pending Approval",
            "partial_approved": "Partially Approved",
            "fully_approved": "Fully Approved",
            "implemented": "Implemented"
        }
    }
}
```

---

## Support

For questions or issues, refer to:
- **Full Documentation:** `docs/PERSONNEL_ACTIONS_IMPROVEMENTS_IMPLEMENTED.md`
- **Analysis:** `docs/PERSONNEL_ACTIONS_ANALYSIS_AND_IMPROVEMENTS.md`
- **Original Spec:** `docs/PERSONNEL_ACTIONS_API_IMPLEMENTATION.md`

---

**Last Updated:** October 2, 2025  
**API Version:** 2.0  
**Status:** ✅ Production Ready

