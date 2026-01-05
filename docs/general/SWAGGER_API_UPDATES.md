# Swagger API Documentation Updates - Multi-Leave-Type Feature

**Date:** October 21, 2025
**Version:** 2.0
**Status:** ✅ Complete

## Overview

The Swagger/OpenAPI documentation has been fully updated to reflect the new multi-leave-type functionality in the Leave Management System.

---

## Updated Endpoints

### 1. POST /api/v1/leaves/requests

**Summary:** Create a new leave request with multiple leave types

**Changes:**
- ✅ **Removed** `leave_type_id` from required fields
- ✅ **Removed** `total_days` from required fields (now auto-calculated)
- ✅ **Added** `items` array (required, minimum 1 item)
- ✅ **Updated** request examples with multiple leave types
- ✅ **Updated** response schema to include `items` array
- ✅ **Added** new error response for duplicate leave types (422)
- ✅ **Updated** balance error response to include leave type name

**New Request Body Schema:**

```yaml
{
  "employee_id": integer (required),
  "start_date": date (required),
  "end_date": date (required),
  "reason": string (optional),
  "status": enum (optional) ["pending", "approved", "declined"],
  "items": array (required, min: 1) [
    {
      "leave_type_id": integer (required),
      "days": float (required)
    }
  ],
  "supervisor_approved": boolean (optional),
  "supervisor_approved_date": date (optional),
  "hr_site_admin_approved": boolean (optional),
  "hr_site_admin_approved_date": date (optional),
  "attachment_notes": string (optional)
}
```

**Example Request:**

```json
{
  "employee_id": 123,
  "start_date": "2025-01-15",
  "end_date": "2025-01-17",
  "reason": "Family emergency and medical checkup",
  "status": "pending",
  "items": [
    {"leave_type_id": 1, "days": 2},
    {"leave_type_id": 2, "days": 1.5}
  ],
  "supervisor_approved": false,
  "hr_site_admin_approved": false
}
```

**Example Response:**

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

---

### 2. PUT /api/v1/leaves/requests/{id}

**Summary:** Update a leave request with multiple leave types and approval data

**Changes:**
- ✅ **Removed** `total_days` from request body (auto-calculated)
- ✅ **Added** optional `items` array for updating leave types
- ✅ **Updated** description to explain item replacement behavior
- ✅ **Updated** response schema to include `items` array
- ✅ **Added** duplicate leave type error response (422)
- ✅ **Updated** balance error response format

**Request Body Schema:**

```yaml
{
  "start_date": date (optional),
  "end_date": date (optional),
  "reason": string (optional),
  "status": enum (optional) ["pending", "approved", "declined", "cancelled"],
  "items": array (optional, min: 1) [
    {
      "leave_type_id": integer (required),
      "days": float (required)
    }
  ],
  "supervisor_approved": boolean (optional),
  "supervisor_approved_date": date (optional),
  "hr_site_admin_approved": boolean (optional),
  "hr_site_admin_approved_date": date (optional),
  "attachment_notes": string (optional)
}
```

**Important Notes:**
- If `items` array is provided, it **replaces** all existing items
- If `items` is not provided, existing items remain unchanged
- `total_days` is automatically recalculated when items change
- Balances are restored for old items and deducted for new items

---

### 3. GET /api/v1/leaves/requests (List)

**Changes:**
- ✅ **Updated** response schema to include `items` array in each request
- ✅ Items are eagerly loaded with leave type details
- ✅ No changes to query parameters

---

### 4. GET /api/v1/leaves/requests/{id} (Show)

**Changes:**
- ✅ **Updated** response schema to include `items` array
- ✅ Items include full leave type relationship details
- ✅ No changes to path parameters

---

## Updated Schemas

### LeaveRequest Schema

**Updated Properties:**

```yaml
LeaveRequest:
  type: object
  description: "Leave request with support for multiple leave types"
  properties:
    id: integer (example: 1)
    employee_id: integer (example: 123)
    start_date: date (example: "2025-01-15")
    end_date: date (example: "2025-01-17")
    total_days: number (example: 3.5, description: "Sum of all item days")
    reason: string
    status: enum ["pending", "approved", "declined", "cancelled"]
    supervisor_approved: boolean (default: false)
    supervisor_approved_date: date (nullable)
    hr_site_admin_approved: boolean (default: false)
    hr_site_admin_approved_date: date (nullable)
    attachment_notes: string (nullable)
    created_by: string (nullable)
    updated_by: string (nullable)
    items: array of LeaveRequestItem
    employee: object (nested employee data)
```

### LeaveRequestItem Schema (New)

**New Schema Added:**

```yaml
LeaveRequestItem:
  type: object
  description: "Individual leave type item within a leave request"
  properties:
    id: integer (example: 1)
    leave_request_id: integer (example: 1, description: "Parent leave request ID")
    leave_type_id: integer (example: 1, description: "Leave type ID")
    days: number (example: 2, description: "Number of days for this leave type")
    created_at: datetime
    updated_at: datetime
    leave_type: object
      properties:
        id: integer
        name: string (example: "Annual Leave")
        default_duration: number
        description: string
        requires_attachment: boolean
```

---

## Error Responses

### New Error: Duplicate Leave Types (422)

```json
{
  "success": false,
  "message": "Duplicate leave types are not allowed in a single request",
  "errors": {
    "items": ["The items field is required."]
  }
}
```

### Updated Error: Insufficient Balance (400)

**Before:**
```json
{
  "success": false,
  "message": "Insufficient leave balance. You cannot request more days than available.",
  "data": {
    "available_days": 5,
    "requested_days": 10,
    "shortfall": 5
  }
}
```

**After:**
```json
{
  "success": false,
  "message": "Insufficient balance for Sick Leave: Insufficient leave balance.",
  "data": {
    "leave_type": "Sick Leave",
    "available_days": 0.5,
    "requested_days": 1.5,
    "shortfall": 1
  }
}
```

---

## Accessing Updated Documentation

### Swagger UI

1. Navigate to: `http://your-api-domain/api/documentation`
2. Look for **"Leave Management"** tag
3. Expand **POST /leaves/requests** or **PUT /leaves/requests/{id}**
4. View updated request/response schemas

### Try It Out

1. Click **"Try it out"** button
2. Use the new request format with `items` array:
   ```json
   {
     "employee_id": 1,
     "start_date": "2025-11-01",
     "end_date": "2025-11-03",
     "items": [
       {"leave_type_id": 1, "days": 2},
       {"leave_type_id": 2, "days": 1}
     ]
   }
   ```
3. Click **"Execute"** to test the endpoint

---

## Breaking Changes Summary

### Request Format Changes

| Endpoint | Field | Before | After |
|----------|-------|--------|-------|
| POST /leaves/requests | `leave_type_id` | Required | ❌ Removed |
| POST /leaves/requests | `total_days` | Required | ❌ Removed (auto-calculated) |
| POST /leaves/requests | `items` | N/A | ✅ Required array |
| PUT /leaves/requests/{id} | `total_days` | Optional | ❌ Removed (auto-calculated) |
| PUT /leaves/requests/{id} | `items` | N/A | ✅ Optional array |

### Response Format Changes

| Endpoint | Field | Before | After |
|----------|-------|--------|-------|
| All endpoints | `leave_type` | Single object | ❌ Deprecated |
| All endpoints | `items` | N/A | ✅ Array of items |
| All endpoints | `total_days` | User-provided | ✅ Auto-calculated |

---

## Migration Guide for API Consumers

### Frontend Applications

**Old API Call:**
```javascript
// Before
const createLeaveRequest = async () => {
  const response = await fetch('/api/v1/leaves/requests', {
    method: 'POST',
    body: JSON.stringify({
      employee_id: 123,
      leave_type_id: 1,
      total_days: 5,
      start_date: '2025-01-15',
      end_date: '2025-01-19'
    })
  });
};
```

**New API Call:**
```javascript
// After
const createLeaveRequest = async () => {
  const items = [
    { leave_type_id: 1, days: 3 },
    { leave_type_id: 2, days: 2 }
  ];

  const totalDays = items.reduce((sum, item) => sum + item.days, 0);

  const response = await fetch('/api/v1/leaves/requests', {
    method: 'POST',
    body: JSON.stringify({
      employee_id: 123,
      items: items,
      start_date: '2025-01-15',
      end_date: '2025-01-19'
      // total_days is auto-calculated
    })
  });
};
```

### Displaying Responses

**Old Display:**
```javascript
// Before
<div>{request.leave_type.name}: {request.total_days} days</div>
```

**New Display:**
```javascript
// After
{request.items.map(item => (
  <div key={item.id}>
    {item.leave_type.name}: {item.days} days
  </div>
))}
<div>Total: {request.total_days} days</div>
```

---

## Testing the Updated API

### Postman Collection

Update your Postman collection with these changes:

**Create Request:**
```json
POST /api/v1/leaves/requests
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "employee_id": 1,
  "start_date": "2025-11-01",
  "end_date": "2025-11-03",
  "items": [
    {"leave_type_id": 1, "days": 2},
    {"leave_type_id": 2, "days": 1}
  ],
  "status": "pending"
}
```

**Update Request:**
```json
PUT /api/v1/leaves/requests/1
Authorization: Bearer {{token}}
Content-Type: application/json

{
  "status": "approved",
  "items": [
    {"leave_type_id": 1, "days": 3},
    {"leave_type_id": 3, "days": 2}
  ]
}
```

---

## Validation Rules in Swagger

### Items Array Validation

```yaml
items:
  type: array
  minItems: 1
  items:
    type: object
    required: ["leave_type_id", "days"]
    properties:
      leave_type_id:
        type: integer
        minimum: 1
      days:
        type: number
        format: float
        minimum: 0.5
```

**Business Rules:**
- At least 1 item required
- No duplicate leave_type_id in items array
- Each item must have positive days (≥ 0.5)
- Leave type must exist in database

---

## Changelog

### Version 2.0 (October 21, 2025)

**Added:**
- ✅ `items` array support in POST /leaves/requests
- ✅ `items` array support in PUT /leaves/requests/{id}
- ✅ New `LeaveRequestItem` schema
- ✅ Duplicate leave type error response
- ✅ Enhanced balance error with leave type name

**Changed:**
- ✅ `LeaveRequest` schema updated with `items` property
- ✅ `total_days` changed from user-input to auto-calculated
- ✅ Error responses include more context

**Removed:**
- ❌ `leave_type_id` from POST request body
- ❌ `total_days` from POST/PUT request bodies

**Deprecated:**
- ⚠️ `leave_type` relationship (use `items` instead)

---

## Support

For questions or issues with the updated API:

1. Check Swagger UI at `/api/documentation`
2. Review examples in this document
3. Refer to `MULTI_LEAVE_TYPE_IMPLEMENTATION.md`
4. Contact backend development team

---

**Documentation Generated:** October 21, 2025
**Swagger Version:** 2.0
**API Version:** v1
