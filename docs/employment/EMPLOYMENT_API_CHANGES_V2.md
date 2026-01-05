# Employment Management API Changes - Version 2.0

## Document Information
**Version:** 2.0  
**Date:** January 2025  
**Status:** Implemented  
**Breaking Changes:** None (Backward Compatible)

---

## Table of Contents
1. [Overview](#overview)
2. [Summary of Changes](#summary-of-changes)
3. [New API Endpoints](#new-api-endpoints)
4. [Modified API Endpoints](#modified-api-endpoints)
5. [Calculation Logic Changes](#calculation-logic-changes)
6. [Data Format Changes](#data-format-changes)
7. [Migration Guide](#migration-guide)
8. [Backward Compatibility](#backward-compatibility)
9. [Examples](#examples)

---

## Overview

The Employment Management API has been enhanced to provide server-side calculation of funding allocation amounts, ensuring consistency with payroll calculations and eliminating frontend calculation discrepancies.

### Key Improvements:
- ✅ **Server-side Calculation**: All allocation amounts calculated by backend
- ✅ **Real-time Calculation API**: New endpoint for instant feedback
- ✅ **Correct Salary Usage**: Uses `probation_salary` or `pass_probation_salary`
- ✅ **Consistent with Payroll**: Same calculation logic as payroll system
- ✅ **Backward Compatible**: Optional `allocated_amount` in requests

---

## Summary of Changes

### New Features:
1. **Real-time Calculation Endpoint** - `POST /api/employments/calculate-allocation`
2. **Automatic Amount Calculation** - Backend calculates if not provided
3. **Enhanced Response Data** - Includes calculation details

### Modified Behavior:
1. **CREATE Employment** - `allocated_amount` now optional in allocations array
2. **UPDATE Employment** - `allocated_amount` now optional in allocations array
3. **Salary Selection** - Backend automatically selects correct salary field

### Fields:
- `end_date` - **REMAINS OPTIONAL** (not removed, useful for contracts)
- `allocated_amount` - **NOW OPTIONAL** (backend calculates if missing)
- `fte` - **ACCEPTED AS PERCENTAGE** (converted to decimal internally)

---

## New API Endpoints

### 1. Calculate Allocation Amount

**Endpoint:** `POST /api/employments/calculate-allocation`

**Purpose:** Calculate funding allocation amount in real-time based on FTE percentage and employment salary.

**Authentication:** Required (Bearer Token)

**Permission:** `employment.read`

#### Request

**Headers:**
```http
Content-Type: application/json
Authorization: Bearer {token}
```

**Body:**
```json
{
  "employment_id": 123,
  "fte": 60
}
```

**Parameters:**

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| employment_id | integer | Yes | exists:employments,id | ID of employment record |
| fte | number | Yes | min:0, max:100 | FTE percentage (0-100) |

#### Response

**Success Response (200):**
```json
{
  "success": true,
  "message": "Allocation amount calculated successfully",
  "data": {
    "employment_id": 123,
    "fte": 60,
    "fte_decimal": 0.60,
    "base_salary": 50000,
    "salary_type": "probation_salary",
    "allocated_amount": 30000,
    "formatted_amount": "฿30,000.00",
    "calculation_formula": "(50000 × 60) / 100 = 30000"
  }
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| employment_id | integer | Employment record ID |
| fte | number | FTE as percentage |
| fte_decimal | number | FTE as decimal (for database) |
| base_salary | number | Salary used for calculation |
| salary_type | string | Which salary field was used |
| allocated_amount | number | Calculated amount |
| formatted_amount | string | Currency-formatted amount |
| calculation_formula | string | Human-readable formula |

**Error Responses:**

**404 - Employment Not Found:**
```json
{
  "success": false,
  "message": "Employment not found"
}
```

**422 - Validation Error:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "fte": [
      "The fte must be between 0 and 100."
    ]
  }
}
```

**401 - Unauthenticated:**
```json
{
  "success": false,
  "message": "Unauthenticated"
}
```

**500 - Server Error:**
```json
{
  "success": false,
  "message": "Calculation failed",
  "error": "Error details"
}
```

#### Usage Examples

**Example 1: Calculate for 60% FTE**
```javascript
// JavaScript/Axios
const response = await axios.post('/api/employments/calculate-allocation', {
  employment_id: 123,
  fte: 60
});

console.log(response.data.data.allocated_amount); // 30000
console.log(response.data.data.formatted_amount); // ฿30,000.00
```

**Example 2: Calculate for 100% FTE**
```javascript
const response = await axios.post('/api/employments/calculate-allocation', {
  employment_id: 456,
  fte: 100
});

console.log(response.data.data.allocated_amount); // 50000
```

**Example 3: Error Handling**
```javascript
try {
  const response = await axios.post('/api/employments/calculate-allocation', {
    employment_id: 999999, // Non-existent ID
    fte: 60
  });
} catch (error) {
  if (error.response.status === 404) {
    console.error('Employment not found');
  }
}
```

---

## Modified API Endpoints

### 1. Create Employment

**Endpoint:** `POST /api/employments`

#### Changes:

**BEFORE (v1.0):**
```json
{
  "allocations": [
    {
      "allocation_type": "grant",
      "position_slot_id": 45,
      "fte": 60,
      "allocated_amount": 30000  // ❌ REQUIRED
    }
  ]
}
```

**AFTER (v2.0):**
```json
{
  "allocations": [
    {
      "allocation_type": "grant",
      "position_slot_id": 45,
      "fte": 60
      // ✅ allocated_amount is OPTIONAL - backend calculates
    }
  ]
}
```

#### New Behavior:

1. **If `allocated_amount` is provided:**
   - Backend uses the provided value
   - Useful for manual overrides
   
2. **If `allocated_amount` is NOT provided:**
   - Backend calculates automatically
   - Formula: `(base_salary × fte) / 100`
   - Uses `probation_salary` OR `pass_probation_salary`

#### Full Request Example:

```json
{
  "employee_id": 123,
  "employment_type": "Full-Time",
  "pay_method": "Transferred to bank",
  "start_date": "2024-01-01",
  "end_date": null,
  "pass_probation_date": "2024-04-01",
  "department_id": 5,
  "position_id": 12,
  "work_location_id": 2,
  "pass_probation_salary": 50000,
  "probation_salary": 45000,
  "health_welfare": true,
  "health_welfare_percentage": 5,
  "pvd": true,
  "pvd_percentage": 3,
  "saving_fund": false,
  "saving_fund_percentage": null,
  "allocations": [
    {
      "allocation_type": "grant",
      "position_slot_id": 45,
      "fte": 60
      // Backend calculates: (45000 × 60) / 100 = 27000
    },
    {
      "allocation_type": "org_funded",
      "grant_id": 2,
      "department_id": 5,
      "position_id": 12,
      "fte": 40
      // Backend calculates: (45000 × 40) / 100 = 18000
    }
  ]
}
```

#### Response:

```json
{
  "success": true,
  "message": "Employment created successfully",
  "data": {
    "id": 456,
    "employee_id": 123,
    "employment_type": "Full-Time",
    "start_date": "2024-01-01",
    "end_date": null,
    "pass_probation_salary": 50000,
    "probation_salary": 45000,
    "employee_funding_allocations": [
      {
        "id": 789,
        "allocation_type": "grant",
        "position_slot_id": 45,
        "fte": 0.60,
        "allocated_amount": 27000  // ✅ Calculated by backend
      },
      {
        "id": 790,
        "allocation_type": "org_funded",
        "org_funded_id": 12,
        "fte": 0.40,
        "allocated_amount": 18000  // ✅ Calculated by backend
      }
    ]
  }
}
```

### 2. Update Employment

**Endpoint:** `PUT /api/employments/{id}`

**Changes:** Same as Create Employment

- `allocated_amount` is now optional in allocations array
- Backend calculates if not provided
- Existing allocations retain their calculated amounts

---

## Calculation Logic Changes

### Salary Field Selection

**Backend Logic:**
```php
$baseSalary = $employment->probation_salary ?? $employment->pass_probation_salary;
```

**Decision Tree:**
1. If `probation_salary` exists → Use it
2. Otherwise → Use `pass_probation_salary`
3. This matches payroll calculation logic

### Calculation Formula

**Formula:**
```
allocated_amount = (base_salary × fte_percentage) / 100
```

**Example Calculations:**

| Base Salary | FTE (%) | Calculation | Allocated Amount |
|-------------|---------|-------------|------------------|
| 50,000 | 60 | (50,000 × 60) / 100 | 30,000 |
| 45,000 | 100 | (45,000 × 100) / 100 | 45,000 |
| 60,000 | 40 | (60,000 × 40) / 100 | 24,000 |
| 35,000 | 50 | (35,000 × 50) / 100 | 17,500 |

### Why This Matters

**OLD Frontend Calculation (WRONG):**
```javascript
// ❌ Used wrong salary field
const amount = (formData.position_salary * fte) / 100;
```

**NEW Backend Calculation (CORRECT):**
```php
// ✅ Uses correct salary field
$baseSalary = $employment->probation_salary ?? $employment->pass_probation_salary;
$amount = ($baseSalary * $fte) / 100;
```

**Impact:**
- Ensures payroll calculations match allocation amounts
- Uses salary during probation period correctly
- Consistent across system

---

## Data Format Changes

### FTE (Full-Time Equivalent)

**API Request Format:**
```json
{
  "fte": 60  // ✅ Send as percentage (0-100)
}
```

**Database Storage Format:**
```sql
fte DECIMAL(4,2) -- ✅ Stored as decimal (0.60)
```

**API Response Format:**
```json
{
  "fte": 0.60,  // ✅ Returns as decimal
  "fte_percentage": 60  // ✅ Some endpoints include percentage
}
```

**Conversion:**
```php
// Request → Database
$fteDecimal = $ftePercentage / 100;  // 60 → 0.60

// Database → Response (some endpoints)
$ftePercentage = $fteDecimal * 100;  // 0.60 → 60
```

### Allocated Amount

**Format:**
```json
{
  "allocated_amount": 30000,  // ✅ Number (decimal)
  "formatted_allocated_amount": "฿30,000.00"  // ✅ String (formatted)
}
```

**Precision:**
- Stored as `DECIMAL(15,2)`
- Always rounded to 2 decimal places
- Formatted with currency symbol in some responses

---

## Migration Guide

### For Frontend Developers

#### Step 1: Update Service Layer

**Add new method to employment service:**
```javascript
async calculateAllocationAmount(data) {
  return this.post('/employments/calculate-allocation', data);
}
```

#### Step 2: Remove Local Calculations

**REMOVE these methods:**
```javascript
// ❌ DELETE
getCalculatedSalary(ftePercentage) {
  const salary = (this.formData.position_salary * ftePercentage) / 100;
  return this.formatCurrency(salary);
}

// ❌ DELETE
calculateSalaryFromFte(fte) {
  return (this.formData.position_salary * fte) / 100;
}
```

#### Step 3: Use Real-time Calculation API

**ADD real-time calculation:**
```javascript
async onFteChange() {
  if (this.employmentId && this.currentAllocation.fte) {
    const result = await this.calculateAllocationAmount(
      this.employmentId,
      this.currentAllocation.fte
    );
    
    this.displayedAmount = result.formatted_amount;
  }
}
```

#### Step 4: Update Payload Builder

**REMOVE allocated_amount from request:**
```javascript
buildPayloadForAPI() {
  return {
    // ... other fields
    allocations: this.fundingAllocations.map(allocation => ({
      allocation_type: allocation.allocation_type,
      fte: allocation.fte
      // ✅ NO allocated_amount
    }))
  };
}
```

### For Backend Developers

#### No Changes Required

The backend changes are already implemented and backward compatible:
- Accepts `allocated_amount` as optional
- Calculates automatically if not provided
- Existing endpoints unchanged

---

## Backward Compatibility

### ✅ Fully Backward Compatible

**Old Clients (v1.0) - Still Work:**
```json
{
  "allocations": [
    {
      "fte": 60,
      "allocated_amount": 30000  // ✅ Still accepted
    }
  ]
}
```

**New Clients (v2.0) - Recommended:**
```json
{
  "allocations": [
    {
      "fte": 60
      // ✅ Backend calculates
    }
  ]
}
```

### Migration Path

1. **Phase 1:** Deploy backend changes (already done)
2. **Phase 2:** Update frontend to use new API (gradual)
3. **Phase 3:** Remove old calculation code (cleanup)

**No downtime required** - both approaches work simultaneously.

---

## Examples

### Example 1: Create Employment with Auto-Calculation

**Request:**
```http
POST /api/employments
Content-Type: application/json
Authorization: Bearer {token}

{
  "employee_id": 100,
  "employment_type": "Full-Time",
  "start_date": "2024-01-01",
  "pass_probation_date": "2024-04-01",
  "department_id": 10,
  "position_id": 25,
  "work_location_id": 3,
  "pass_probation_salary": 60000,
  "probation_salary": 55000,
  "allocations": [
    {
      "allocation_type": "grant",
      "position_slot_id": 100,
      "fte": 70
    },
    {
      "allocation_type": "org_funded",
      "grant_id": 5,
      "department_id": 10,
      "position_id": 25,
      "fte": 30
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "message": "Employment created successfully",
  "data": {
    "id": 500,
    "employee_funding_allocations": [
      {
        "id": 1000,
        "fte": 0.70,
        "allocated_amount": 38500  // (55000 × 70) / 100
      },
      {
        "id": 1001,
        "fte": 0.30,
        "allocated_amount": 16500  // (55000 × 30) / 100
      }
    ]
  }
}
```

### Example 2: Real-time Calculation

**Request:**
```http
POST /api/employments/calculate-allocation
Content-Type: application/json
Authorization: Bearer {token}

{
  "employment_id": 500,
  "fte": 75
}
```

**Response:**
```json
{
  "success": true,
  "message": "Allocation amount calculated successfully",
  "data": {
    "employment_id": 500,
    "fte": 75,
    "fte_decimal": 0.75,
    "base_salary": 55000,
    "salary_type": "probation_salary",
    "allocated_amount": 41250,
    "formatted_amount": "฿41,250.00",
    "calculation_formula": "(55000 × 75) / 100 = 41250"
  }
}
```

### Example 3: Update Employment FTE

**Request:**
```http
PUT /api/employments/500
Content-Type: application/json
Authorization: Bearer {token}

{
  "allocations": [
    {
      "allocation_type": "grant",
      "position_slot_id": 100,
      "fte": 80  // Changed from 70 to 80
    },
    {
      "allocation_type": "org_funded",
      "grant_id": 5,
      "department_id": 10,
      "position_id": 25,
      "fte": 20  // Changed from 30 to 20
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "message": "Employment updated successfully",
  "data": {
    "employee_funding_allocations": [
      {
        "fte": 0.80,
        "allocated_amount": 44000  // Recalculated: (55000 × 80) / 100
      },
      {
        "fte": 0.20,
        "allocated_amount": 11000  // Recalculated: (55000 × 20) / 100
      }
    ]
  }
}
```

---

## Validation Rules

### Calculate Allocation Amount

| Field | Rules |
|-------|-------|
| employment_id | required, integer, exists:employments,id |
| fte | required, numeric, min:0, max:100 |

### Create/Update Employment Allocations

| Field | Rules |
|-------|-------|
| allocations | required, array, min:1 |
| allocations.*.allocation_type | required, in:grant,org_funded |
| allocations.*.fte | required, numeric, min:0, max:100 |
| allocations.*.allocated_amount | nullable, numeric, min:0 |
| allocations.*.position_slot_id | required_if:allocation_type,grant, exists:position_slots,id |
| allocations.*.grant_id | required_if:allocation_type,org_funded, exists:grants,id |

**Business Rules:**
- Total FTE of all allocations must equal exactly 100%
- No duplicate allocations allowed
- Employee cannot have overlapping active employments

---

## API Rate Limiting

**Calculate Allocation Amount Endpoint:**
- Rate Limit: 60 requests per minute per user
- Recommended: Debounce frontend calls (500ms)
- Use cached results when possible

---

## Error Codes

| Code | Message | Cause |
|------|---------|-------|
| 200 | Success | Request successful |
| 400 | Bad Request | Invalid request data |
| 401 | Unauthenticated | Missing or invalid token |
| 403 | Forbidden | Insufficient permissions |
| 404 | Employment not found | Invalid employment_id |
| 422 | Validation failed | Validation errors |
| 500 | Server error | Internal server error |

---

## Testing

### Postman Collection

Import this request to test the new endpoint:

```json
{
  "name": "Calculate Allocation Amount",
  "request": {
    "method": "POST",
    "url": "{{base_url}}/api/employments/calculate-allocation",
    "header": [
      {
        "key": "Authorization",
        "value": "Bearer {{access_token}}"
      },
      {
        "key": "Content-Type",
        "value": "application/json"
      }
    ],
    "body": {
      "mode": "raw",
      "raw": "{\n  \"employment_id\": 1,\n  \"fte\": 60\n}"
    }
  }
}
```

### Test Scenarios

1. **Valid Calculation:**
   - employment_id exists
   - fte between 0-100
   - Expected: 200 response with calculated amount

2. **Invalid Employment ID:**
   - employment_id = 999999
   - Expected: 404 error

3. **Invalid FTE:**
   - fte = 150
   - Expected: 422 validation error

4. **Missing Authentication:**
   - No bearer token
   - Expected: 401 error

---

## Support

For questions or issues:
- Review: `FRONTEND_EMPLOYMENT_MIGRATION_GUIDE.md`
- Check: Swagger API documentation at `/api/documentation`
- Contact: Development Team

---

## Changelog

### Version 2.0 (January 2025)
- ✅ Added `POST /api/employments/calculate-allocation` endpoint
- ✅ Made `allocated_amount` optional in allocations array
- ✅ Backend auto-calculates using correct salary field
- ✅ Enhanced response with calculation details
- ✅ Maintained backward compatibility

### Version 1.0 (Initial)
- Initial employment API implementation
- `allocated_amount` required in requests
- Frontend calculation logic

---

**Document Status:** Complete  
**Implementation Status:** Deployed  
**Last Updated:** January 2025

