# Travel Request System - Complete Documentation

**Version:** 4.2  
**Date:** October 5, 2025  
**Status:** Production Ready - Removed Site Admin Fields  

## Overview

This document provides comprehensive documentation for the Travel Request system implementation in the Laravel backend API. This is a **simple CRUD system with approval tracking** - travel requests are stored and managed with approval status tracking but without complex workflow automation.

### Key Features
- ✅ Complete CRUD operations (Create, Read, Update, Delete)
- ✅ Enhanced "Other please specify" functionality with custom text fields
- ✅ Server-side pagination and advanced search
- ✅ Multi-field filtering and sorting
- ✅ Employee-based search by staff ID
- ✅ Optimized database queries with eager loading
- ✅ Comprehensive API documentation (Swagger)
- ✅ Approval tracking with dates (no signature boolean fields)

### System Architecture
This is a **Data Entry and Display System** - digitizing paper-based travel request forms into a database for storage, viewing, searching, and reporting. Approval tracking is available through boolean approval fields and corresponding dates.

## Table of Contents

1. [Database Schema](#database-schema)
2. [Backend API Implementation](#backend-api-implementation)
3. [API Endpoints](#api-endpoints)
4. [Validation Rules](#validation-rules)
5. [Testing & Development](#testing--development)
6. [Performance Considerations](#performance-considerations)

---

## Database Schema

### Travel Requests Table

```sql
CREATE TABLE travel_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id BIGINT UNSIGNED NOT NULL,
    department_id BIGINT UNSIGNED NULL,
    position_id BIGINT UNSIGNED NULL,
    destination VARCHAR(200) NULL,
    start_date DATE NULL,
    to_date DATE NULL,
    purpose TEXT NULL,
    grant VARCHAR(50) NULL,
    transportation VARCHAR(100) NULL,
    transportation_other_text VARCHAR(200) NULL, -- Custom text when transportation = 'other'
    accommodation VARCHAR(100) NULL,
    accommodation_other_text VARCHAR(200) NULL,  -- Custom text when accommodation = 'other'
    
    -- Request and Approval Fields (Updated Schema)
    request_by_date DATE NULL,
    supervisor_approved BOOLEAN DEFAULT FALSE,
    supervisor_approved_date DATE NULL,
    hr_acknowledged BOOLEAN DEFAULT FALSE,
    hr_acknowledgement_date DATE NULL,
    
    remarks TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    created_by VARCHAR(100) NULL,
    updated_by VARCHAR(100) NULL,
    
    -- Foreign Key Constraints
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE NO ACTION,
    FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE NO ACTION
);
```

### Key Schema Features

- **Employee Integration**: Required relationship with employees table
- **Department/Position**: Optional but validated relationships
- **Date Fields**: Support for travel date ranges
- **Approval Fields**: Boolean approval flags with corresponding approval dates
- **Custom "Other" Fields**: Fields for storing custom transportation/accommodation text
- **Flexible Text Fields**: Support for purpose, grant, and remarks

### Schema Changes (Version 4.0)
**Removed Fields:**
- `request_by_signature` (boolean) - No longer needed
- `supervisor_signature` (boolean) - No longer needed  
- `hr_signature` (boolean) - No longer needed

**Kept Fields:**
- `request_by_date` (date) - When request was made
- `supervisor_approved` (boolean) & `supervisor_approved_date` (date)
- `hr_acknowledged` (boolean) & `hr_acknowledgement_date` (date)

---

## Backend API Implementation

### File Structure

```
app/
├── Http/
│   ├── Controllers/Api/
│   │   └── TravelRequestController.php
│   ├── Requests/
│   │   ├── StoreTravelRequestRequest.php
│   │   └── UpdateTravelRequestRequest.php
│   └── Resources/
│       └── TravelRequestResource.php
├── Models/
│   └── TravelRequest.php
database/
├── factories/
│   └── TravelRequestFactory.php
└── migrations/
    └── 2025_03_29_045645_create_travel_requests_table.php
tests/
└── Feature/
    └── TravelRequestFeatureTest.php
```

### Model Relationships

The `TravelRequest` model includes these relationships:

```php
// Employee relationship (required)
public function employee(): BelongsTo
{
    return $this->belongsTo(Employee::class);
}

// Department relationship (optional)
public function department(): BelongsTo
{
    return $this->belongsTo(Department::class);
}

// Position relationship (optional)
public function position(): BelongsTo
{
    return $this->belongsTo(Position::class);
}

// Scope for eager loading relationships
public function scopeWithRelations($query)
{
    return $query->with([
        'employee:id,staff_id,first_name_en,last_name_en',
        'department:id,name',
        'position:id,title,department_id',
    ]);
}
```

### Model Configuration

```php
protected $fillable = [
    'employee_id',
    'department_id',
    'position_id',
    'destination',
    'start_date',
    'to_date',
    'purpose',
    'grant',
    'transportation',
    'transportation_other_text',
    'accommodation',
    'accommodation_other_text',
    'request_by_date',
    'supervisor_approved',
    'supervisor_approved_date',
    'hr_acknowledged',
    'hr_acknowledgement_date',
    'remarks',
    'created_by',
    'updated_by',
];

protected $casts = [
    'supervisor_approved' => 'boolean',
    'hr_acknowledged' => 'boolean',
    'start_date' => 'date',
    'to_date' => 'date',
    'request_by_date' => 'date',
    'supervisor_approved_date' => 'date',
    'hr_acknowledgement_date' => 'date',
];
```

---

## API Endpoints

### Base URL
```
/api/v1/travel-requests
```

**Authentication:** All endpoints require Bearer token authentication (`Authorization: Bearer {token}`)

### 1. Get Options for Dropdowns
```http
GET /api/v1/travel-requests/options
```

**Response:**
```json
{
  "success": true,
  "message": "Options retrieved successfully",
  "data": {
    "transportation": [
      {"value": "smru_vehicle", "label": "SMRU vehicle"},
      {"value": "public_transportation", "label": "Public transportation"},
      {"value": "air", "label": "Air"},
      {"value": "other", "label": "Other please specify"}
    ],
    "accommodation": [
      {"value": "smru_arrangement", "label": "SMRU arrangement"},
      {"value": "self_arrangement", "label": "Self arrangement"},
      {"value": "other", "label": "Other please specify"}
    ]
  }
}
```

### 2. List Travel Requests (With Pagination & Filtering)
```http
GET /api/v1/travel-requests
```

**Query Parameters:**
- `page` (integer, min: 1): Page number for pagination
- `per_page` (integer, min: 1, max: 100): Items per page (default: 10)
- `search` (string, max: 255): Search by employee staff ID, first name, or last name
- `filter_department` (string): Filter by department names (comma-separated)
- `filter_destination` (string): Filter by destination (partial match)
- `filter_transportation` (string): Filter by transportation type
- `sort_by` (string): Sort field (start_date, destination, employee_name, department, created_at)
- `sort_order` (string): Sort direction (asc, desc)

**Response Structure:**
```json
{
  "success": true,
  "message": "Travel requests retrieved successfully",
  "data": [
    {
      "id": 1,
      "employee_id": 1,
      "department_id": 8,
      "position_id": 47,
      "destination": "Bangkok",
      "start_date": "2025-04-01",
      "to_date": "2025-04-05",
      "purpose": "Business meeting",
      "grant": "Project X",
      "transportation": "other",
      "transportation_other_text": "Company shuttle bus with driver",
      "accommodation": "other",
      "accommodation_other_text": "Client-provided guest house",
      "request_by_date": "2025-03-15",
      "supervisor_approved": true,
      "supervisor_approved_date": "2025-03-16",
      "hr_acknowledged": false,
      "hr_acknowledgement_date": null,
      "remarks": "Meeting with key stakeholders",
      "created_at": "2025-03-15T12:00:00Z",
      "updated_at": "2025-03-16T12:00:00Z",
      "created_by": "admin",
      "updated_by": "admin",
      "employee": {
        "id": 1,
        "staff_id": "EMP001",
        "first_name_en": "John",
        "last_name_en": "Doe"
      },
      "department": {
        "id": 8,
        "name": "Information Technology"
      },
      "position": {
        "id": 47,
        "title": "Software Developer",
        "department_id": 8
      }
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 10,
    "total": 50,
    "last_page": 5,
    "from": 1,
    "to": 10,
    "has_more_pages": true
  }
}
```

### 3. Create Travel Request
```http
POST /api/v1/travel-requests
```

**Request Body Example:**
```json
{
  "employee_id": 1,
  "department_id": 8,
  "position_id": 47,
  "destination": "Bangkok",
  "start_date": "2025-04-01",
  "to_date": "2025-04-05",
  "purpose": "Business meeting",
  "grant": "Project X",
  "transportation": "other",
  "transportation_other_text": "Company shuttle bus with driver",
  "accommodation": "other",
  "accommodation_other_text": "Client-provided guest house",
  "request_by_date": "2025-03-15",
  "supervisor_approved": false,
  "supervisor_approved_date": null,
  "hr_acknowledged": false,
  "hr_acknowledgement_date": null,
  "remarks": "Meeting with key stakeholders",
  "created_by": "admin"
}
```

### 4. Show Travel Request
```http
GET /api/v1/travel-requests/{id}
```

### 5. Update Travel Request
```http
PUT /api/v1/travel-requests/{id}
```

### 6. Delete Travel Request
```http
DELETE /api/v1/travel-requests/{id}
```

### 7. Search Travel Requests by Employee Staff ID
```http
GET /api/v1/travel-requests/search/employee/{staffId}
```

---

## Validation Rules

### Create/Update Travel Request Validation

**Core Fields:**
```php
[
    'employee_id' => 'required|exists:employees,id',
    'department_id' => 'nullable|exists:departments,id',
    'position_id' => 'nullable|exists:positions,id',
    'destination' => 'nullable|string|max:200',
    'start_date' => 'nullable|date|after_or_equal:today',
    'to_date' => 'nullable|date|after:start_date',
    'purpose' => 'nullable|string',
    'grant' => 'nullable|string|max:50',
]
```

**Transportation & Accommodation with "Other" Support:**
```php
[
    'transportation' => 'nullable|string|in:smru_vehicle,public_transportation,air,other',
    'transportation_other_text' => 'nullable|string|max:200|required_if:transportation,other',
    'accommodation' => 'nullable|string|in:smru_arrangement,self_arrangement,other',
    'accommodation_other_text' => 'nullable|string|max:200|required_if:accommodation,other',
]
```

**Approval Fields (Updated):**
```php
[
    'request_by_date' => 'nullable|date',
    'supervisor_approved' => 'nullable|boolean',
    'supervisor_approved_date' => 'nullable|date',
    'hr_acknowledged' => 'nullable|boolean',
    'hr_acknowledgement_date' => 'nullable|date',
    'remarks' => 'nullable|string',
    'created_by' => 'nullable|string|max:100',
    'updated_by' => 'nullable|string|max:100',
]
```

### Key Validation Messages
```php
[
    'transportation_other_text.required_if' => 'Please specify the transportation method when selecting "Other".',
    'accommodation_other_text.required_if' => 'Please specify the accommodation type when selecting "Other".',
    'transportation_other_text.max' => 'Transportation specification cannot exceed 200 characters.',
    'accommodation_other_text.max' => 'Accommodation specification cannot exceed 200 characters.',
    'to_date.after' => 'End date must be after start date.',
    'start_date.after_or_equal' => 'Start date cannot be in the past.',
]
```

### Transportation & Accommodation Options

#### Valid Transportation Options
- `smru_vehicle` - SMRU vehicle
- `public_transportation` - Public transportation
- `air` - Air
- `other` - Other please specify (requires `transportation_other_text`)

#### Valid Accommodation Options
- `smru_arrangement` - SMRU arrangement
- `self_arrangement` - Self arrangement
- `other` - Other please specify (requires `accommodation_other_text`)

---

## Testing & Development

### API Testing Examples

#### 1. Creating a Travel Request with Approval Status
```bash
curl -X POST /api/v1/travel-requests \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 1,
    "destination": "Bangkok",
    "start_date": "2025-04-01",
    "to_date": "2025-04-05",
    "purpose": "Business meeting",
    "transportation": "other",
    "transportation_other_text": "Company shuttle bus with driver",
    "accommodation": "other",
    "accommodation_other_text": "Client-provided guest house",
    "request_by_date": "2025-03-15",
    "supervisor_approved": true,
    "supervisor_approved_date": "2025-03-16"
  }'
```

#### 2. Getting Travel Requests with Pagination and Filters
```bash
# Basic pagination
curl -X GET "/api/v1/travel-requests?page=1&per_page=10" \
  -H "Authorization: Bearer YOUR_TOKEN"

# With search and filters
curl -X GET "/api/v1/travel-requests?search=John&filter_department=IT,HR&sort_by=start_date&sort_order=desc" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

#### 3. Updating Approval Status
```bash
curl -X PUT "/api/v1/travel-requests/1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "hr_acknowledged": true,
    "hr_acknowledgement_date": "2025-03-17"
  }'
```

### Testing Checklist

#### Backend Testing
- [ ] CRUD operations for all endpoints
- [ ] Pagination with various page sizes (1, 10, 50, 100)
- [ ] Search functionality with partial employee name matches
- [ ] Department filtering with single and multiple departments
- [ ] "Other" field validation when transportation/accommodation = "other"
- [ ] Character limits on _other_text fields (200 chars max)
- [ ] Error handling for invalid parameters
- [ ] Approval field handling (boolean and date fields)

#### API Response Testing
- [ ] Consistent JSON response format
- [ ] Proper HTTP status codes
- [ ] Error message clarity
- [ ] Pagination metadata accuracy
- [ ] Applied filters tracking

---

## Performance Considerations

### Database Optimization
- **Indexing**: Ensure proper indexes on foreign keys and search fields
- **Eager Loading**: Use `with()` for relationships to prevent N+1 queries
- **Query Optimization**: Efficient filtering using `whereHas` for related models

### API Performance
- **Server-side Pagination**: Reduces memory usage and improves response times
- **Selective Field Loading**: Only load necessary fields in relationships
- **Caching**: Consider caching dropdown options for better performance

### Controller Features
- **Optimized Queries**: Uses eager loading with specific field selection
- **Efficient Search**: Multi-field search with proper indexing
- **Flexible Sorting**: Database-level sorting for better performance
- **Error Handling**: Comprehensive try-catch blocks with proper error responses

---

## API Documentation & Testing

### Swagger Documentation
The Travel Request API is fully documented with OpenAPI/Swagger annotations:
- **Access:** Available at `/api/documentation` endpoint
- **Interactive Testing:** Use Swagger UI to test all endpoints
- **Schema Definitions:** Complete request/response schemas with examples
- **Authentication:** All endpoints require Bearer token authentication

### Updated Swagger Schema
The Swagger documentation has been updated to reflect the removal of signature boolean fields:

**Removed from Schema:**
- `request_by_signature` (boolean)
- `supervisor_signature` (boolean)
- `hr_signature` (boolean)

**Current Schema Fields:**
- `request_by_date` (date)
- `supervisor_approved` (boolean)
- `supervisor_approved_date` (date)
- `hr_acknowledged` (boolean)
- `hr_acknowledgement_date` (date)

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
  }
}
```

---

## Summary

The Travel Request system is now a comprehensive, modern CRUD system featuring:

1. **✅ Complete CRUD operations** with proper validation
2. **✅ Enhanced "Other please specify"** functionality with custom text storage
3. **✅ Server-side pagination and search** for scalability
4. **✅ Multi-field filtering and sorting** for better user experience
5. **✅ Optimized database queries** for performance
6. **✅ Comprehensive API documentation** with updated Swagger
7. **✅ Approval tracking with dates** (simplified schema without signature booleans)
8. **✅ Simple architecture** without complex approval workflows

### Version 4.2 Updates
- **Removed** site admin approval fields (`site_admin_approved`, `site_admin_approved_date`)
- **Updated** original migration file instead of creating additional migration
- **Updated** all related files: model, requests, resources, controller, factory, and tests
- **Updated** Swagger documentation to reflect schema changes
- **Simplified** approval workflow to only include supervisor and HR approval

### Version 4.0 Updates
- **Removed** signature boolean fields (`request_by_signature`, `supervisor_signature`, `hr_signature`)
- **Kept** all approval boolean fields and corresponding dates
- **Updated** all related files: migration, model, requests, resources, controller, factory, and tests
- **Regenerated** Swagger documentation to reflect schema changes

This implementation provides a robust foundation for travel request management that can scale with organizational needs while maintaining simplicity and user-friendliness.

---

## Backend Files Reference

### Primary Files (Updated)
- **Migration:** `database/migrations/2025_03_29_045645_create_travel_requests_table.php`
- **Model:** `app/Models/TravelRequest.php`
- **Controller:** `app/Http/Controllers/Api/TravelRequestController.php`
- **Validation:** `app/Http/Requests/StoreTravelRequestRequest.php`, `UpdateTravelRequestRequest.php`
- **Resource:** `app/Http/Resources/TravelRequestResource.php`
- **Factory:** `database/factories/TravelRequestFactory.php`
- **Tests:** `tests/Feature/TravelRequestFeatureTest.php`

### Related System Documentation
- **[HRMS Backend Architecture](./HRMS_BACKEND_ARCHITECTURE.md)** - Overall system architecture
- **[Data Entry System Checklist](./DATA_ENTRY_SYSTEM_CHECKLIST.md)** - HRMS design patterns
- **[API Versioning Guide](./API_VERSIONING.md)** - API versioning standards

---

## Document History

| Version | Date | Changes |
|---------|------|---------|
| 4.2 | 2025-10-05 | **Field Removal:** Removed `site_admin_approved` and `site_admin_approved_date` fields from all system components |
| 4.1 | 2025-10-04 | **Field Rename:** Changed `hr_approved` to `hr_acknowledged` and `hr_approved_date` to `hr_acknowledgement_date` |
| 4.0 | 2025-10-04 | **Major Update:** Removed signature boolean fields, updated all documentation and code |
| 3.0 | 2025-10-04 | Unified documentation based on current TravelRequestController.php implementation |
| 2.2 | 2025-10-04 | Updated database schema and API documentation |
| 2.1 | 2025-10-01 | Fixed: Removed non-existent 'email' field from employee queries |
| 2.0 | 2025-10-01 | Consolidated duplicate documentation files |
| 1.5 | 2025-03-29 | Added "Other please specify" functionality |
| 1.0 | 2025-03-15 | Initial comprehensive documentation |

---

**End of Documentation**
