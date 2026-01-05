# Employee API Documentation

**Version:** 1.1  
**Date:** October 5, 2025  
**Status:** Production Ready - Updated Tree Search with Employment Data  

## Overview

This document provides comprehensive documentation for the Employee API endpoints in the HRMS backend system. The Employee API handles all employee-related operations including CRUD operations, advanced filtering, tree search functionality, and various employee information management features.

### Key Features
- ✅ **Complete CRUD operations** for employee management
- ✅ **Advanced filtering and pagination** with performance optimization
- ✅ **Tree search functionality** with employment data integration
- ✅ **Profile picture management** with file upload support
- ✅ **Bulk operations** for efficient data management
- ✅ **Excel import/export** capabilities
- ✅ **Comprehensive validation** and error handling
- ✅ **Employment integration** with department and position data

## Table of Contents

1. [Authentication & Authorization](#authentication--authorization)
2. [Core Employee Endpoints](#core-employee-endpoints)
3. [Tree Search Endpoint](#tree-search-endpoint)
4. [Specialized Endpoints](#specialized-endpoints)
5. [Bulk Operations](#bulk-operations)
6. [Import/Export Operations](#importexport-operations)
7. [Employee Information Updates](#employee-information-updates)
8. [Error Handling](#error-handling)
9. [Performance Considerations](#performance-considerations)

---

## Authentication & Authorization

All Employee API endpoints require authentication using Laravel Sanctum bearer tokens:

```http
Authorization: Bearer {your-token-here}
```

### Required Permissions
- `employee.read` - View employee data
- `employee.create` - Create new employees
- `employee.update` - Update employee information
- `employee.delete` - Delete employees
- `employee.import` - Import employee data
- `employee.export` - Export employee data

---

## Core Employee Endpoints

### 1. List Employees with Advanced Filtering

```http
GET /api/v1/employees
```

**Query Parameters:**
- `page` (integer, min: 1): Page number for pagination
- `per_page` (integer, min: 1, max: 100): Items per page (default: 10)
- `filter_subsidiary` (string): Filter by subsidiary (comma-separated)
- `filter_status` (string): Filter by employee status (comma-separated)
- `filter_gender` (string): Filter by gender (comma-separated)
- `filter_age` (integer): Filter by age
- `filter_id_type` (string): Filter by identification type (comma-separated)
- `filter_staff_id` (string): Filter by staff ID (partial match)
- `sort_by` (string): Sort field (subsidiary, staff_id, first_name_en, last_name_en, gender, date_of_birth, status, age, id_type)
- `sort_order` (string): Sort direction (asc, desc)

**Response:**
```json
{
  "success": true,
  "message": "Employees retrieved successfully",
  "data": [
    {
      "id": 1,
      "subsidiary": "SMRU",
      "staff_id": "EMP001",
      "first_name_en": "John",
      "last_name_en": "Doe",
      "gender": "Male",
      "date_of_birth": "1990-01-01",
      "status": "Local ID Staff",
      "created_at": "2025-01-01T00:00:00Z",
      "updated_at": "2025-01-01T00:00:00Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 10,
    "total": 450,
    "last_page": 45,
    "from": 1,
    "to": 10,
    "has_more_pages": true
  },
  "statistics": {
    "totalEmployees": 450,
    "activeCount": 400,
    "inactiveCount": 50,
    "subsidiaryCount": {
      "SMRU_count": 300,
      "BHF_count": 150
    }
  }
}
```

### 2. Get Employee by Staff ID

```http
GET /api/v1/employees/staff-id/{staff_id}
```

**Parameters:**
- `staff_id` (string, required): Staff ID of the employee

**Response:**
```json
{
  "success": true,
  "message": "Employee(s) retrieved successfully",
  "data": [
    {
      "id": 1,
      "subsidiary": "SMRU",
      "staff_id": "EMP001",
      "initial_en": "Mr.",
      "first_name_en": "John",
      "last_name_en": "Doe",
      "gender": "Male",
      "date_of_birth": "1990-01-01",
      "age": 35,
      "status": "Local ID Staff",
      "employee_identification": [
        {
          "id": 1,
          "id_type": "Passport",
          "document_number": "P12345678",
          "issue_date": "2020-01-01",
          "expiry_date": "2030-01-01"
        }
      ],
      "employment": {
        "id": 1,
        "start_date": "2023-01-01",
        "end_date": null
      }
    }
  ]
}
```

### 3. Get Employee Details

```http
GET /api/v1/employees/{id}
```

**Parameters:**
- `id` (integer, required): Employee ID

**Response:**
```json
{
  "success": true,
  "message": "Employee retrieved successfully",
  "data": {
    "id": 1,
    "subsidiary": "SMRU",
    "staff_id": "EMP001",
    "first_name_en": "John",
    "last_name_en": "Doe",
    "employment": {
      "department": {
        "id": 5,
        "name": "Human Resources"
      },
      "position": {
        "id": 12,
        "title": "HR Manager"
      }
    },
    "employeeFundingAllocations": [],
    "employeeBeneficiaries": [],
    "employeeIdentification": [],
    "leaveBalances": []
  }
}
```

### 4. Create Employee

```http
POST /api/v1/employees
```

**Request Body:**
```json
{
  "subsidiary": "SMRU",
  "staff_id": "EMP001",
  "first_name_en": "John",
  "last_name_en": "Doe",
  "gender": "Male",
  "date_of_birth": "1990-01-01",
  "status": "Local ID Staff",
  "mobile_phone": "0812345678"
}
```

### 5. Update Employee

```http
PUT /api/v1/employees/{id}
```

### 6. Delete Employee

```http
DELETE /api/v1/employees/{id}
```

---

## Tree Search Endpoint

### Get Employees for Tree Search (Updated)

```http
GET /api/v1/employees/tree-search
```

**Description:** Returns employees organized by subsidiary in a tree structure, now including department and position information from employment records.

**Features:**
- ✅ **Hierarchical Structure:** Employees grouped by subsidiary
- ✅ **Employment Integration:** Includes department and position data
- ✅ **Optimized Queries:** Uses eager loading to prevent N+1 queries
- ✅ **Null-Safe:** Handles employees without employment records gracefully

**Response Structure:**
```json
{
  "success": true,
  "message": "Employees retrieved successfully",
  "data": [
    {
      "key": "subsidiary-SMRU",
      "title": "SMRU",
      "value": "subsidiary-SMRU",
      "children": [
        {
          "key": "34",
          "title": "0001 - John Doe",
          "status": "Local ID Staff",
          "value": "34",
          "department_id": 5,
          "position_id": 12,
          "employment": {
            "department": {
              "id": 5,
              "name": "Human Resources"
            },
            "position": {
              "id": 12,
              "title": "HR Manager"
            }
          }
        },
        {
          "key": "35",
          "title": "0002 - Jane Smith",
          "status": "Expats",
          "value": "35",
          "department_id": null,
          "position_id": null,
          "employment": null
        }
      ]
    },
    {
      "key": "subsidiary-BHF",
      "title": "BHF",
      "value": "subsidiary-BHF",
      "children": [
        {
          "key": "36",
          "title": "0003 - Bob Johnson",
          "status": "Local ID Staff",
          "value": "36",
          "department_id": 8,
          "position_id": 25,
          "employment": {
            "department": {
              "id": 8,
              "name": "Information Technology"
            },
            "position": {
              "id": 25,
              "title": "Software Developer"
            }
          }
        }
      ]
    }
  ]
}
```

**Key Features:**

1. **Subsidiary Grouping:** Employees are organized under their respective subsidiaries
2. **Employment Data:** Each employee includes their current department and position information
3. **Flexible Structure:** Handles employees with or without employment records
4. **Performance Optimized:** Uses eager loading with specific field selection
5. **Frontend Ready:** Structure optimized for tree components in frontend frameworks

**Data Fields Explained:**

- **Subsidiary Level:**
  - `key`: Unique identifier for the subsidiary node
  - `title`: Display name of the subsidiary
  - `value`: Value used for selection/identification
  - `children`: Array of employees under this subsidiary

- **Employee Level:**
  - `key`: Employee ID as string
  - `title`: Display format "{staff_id} - {full_name}"
  - `status`: Employee status (Local ID Staff, Expats, etc.)
  - `value`: Employee ID as string
  - `department_id`: ID of employee's department (null if no employment)
  - `position_id`: ID of employee's position (null if no employment)
  - `employment`: Nested object with department and position details (null if no employment)

**Usage Examples:**

```javascript
// Frontend usage example for tree component
const treeData = response.data;

// Access employee with employment info
const employee = treeData[0].children[0];
console.log(employee.employment.department.name); // "Human Resources"
console.log(employee.employment.position.title);  // "HR Manager"

// Handle employee without employment
const employeeWithoutEmployment = treeData[0].children[1];
if (employee.employment) {
  // Has employment data
} else {
  // No employment data available
}
```

---

## Specialized Endpoints

### 1. Filter Employees

```http
GET /api/v1/employees/filter
```

**Query Parameters:**
- `staff_id` (string): Filter by staff ID
- `status` (string): Filter by employee status
- `subsidiary` (string): Filter by subsidiary

### 2. Get Site Records

```http
GET /api/v1/employees/site-records
```

**Response:**
```json
{
  "success": true,
  "message": "Site records retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Main Office",
      "address": "123 Main Street",
      "created_at": "2025-01-01T00:00:00Z",
      "updated_at": "2025-01-01T00:00:00Z"
    }
  ]
}
```

### 3. Upload Profile Picture

```http
POST /api/v1/employees/{id}/profile-picture
```

**Request:** Multipart form data with `profile_picture` file
**Supported formats:** jpeg, png, jpg, gif, svg (max 2MB)

---

## Bulk Operations

### 1. Delete Multiple Employees

```http
DELETE /api/v1/employees/delete-selected/{ids}
```

**Request Body:**
```json
{
  "ids": [1, 2, 3, 4, 5]
}
```

**Response:**
```json
{
  "success": true,
  "message": "5 employee(s) deleted successfully",
  "count": 5
}
```

---

## Import/Export Operations

### 1. Upload Employee Data

```http
POST /api/v1/employees/upload
```

**Request:** Multipart form data with Excel file
**Supported formats:** xlsx, xls, csv

**Response:**
```json
{
  "success": true,
  "message": "Your file is being imported. You'll be notified when it's done.",
  "data": {
    "import_id": "import_67890abcdef"
  }
}
```

### 2. Export Employees

```http
GET /api/v1/employees/export
```

**Response:** Excel file download

### 3. Get Import Status

```http
GET /api/v1/employees/import-status/{import_id}
```

---

## Employee Information Updates

### 1. Update Basic Information

```http
PUT /api/v1/employees/{employee}/basic-information
```

### 2. Update Personal Information

```http
PUT /api/v1/employees/{employee}/personal-information
```

### 3. Update Family Information

```http
PUT /api/v1/employees/{employee}/family-information
```

### 4. Update Bank Information

```http
PUT /api/v1/employees/{id}/bank-information
```

**Request Body:**
```json
{
  "bank_name": "Bangkok Bank",
  "bank_branch": "Silom Branch",
  "bank_account_name": "John Doe",
  "bank_account_number": "1234567890"
}
```

---

## Error Handling

### Common Error Responses

#### 404 - Employee Not Found
```json
{
  "success": false,
  "message": "Employee not found"
}
```

#### 422 - Validation Error
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "staff_id": ["The staff id field is required."],
    "first_name_en": ["The first name en field is required."]
  }
}
```

#### 500 - Server Error
```json
{
  "success": false,
  "message": "Failed to retrieve employees",
  "error": "Internal server error occurred"
}
```

---

## Performance Considerations

### 1. Query Optimization
- **Model Scopes:** Uses `forPagination()` and `withOptimizedRelations()` scopes
- **Eager Loading:** Prevents N+1 queries with specific field selection
- **Indexing:** Proper database indexes on frequently queried fields

### 2. Caching Strategy
- **Statistics Caching:** Employee statistics are cached for improved performance
- **Cache Invalidation:** Automatic cache clearing on data modifications

### 3. Tree Search Optimization
- **Selective Loading:** Only loads necessary fields for tree structure
- **Relationship Optimization:** Uses eager loading with field selection
- **Memory Efficient:** Processes data in chunks for large datasets

### 4. Best Practices
- Use pagination for large datasets
- Implement proper filtering to reduce query load
- Cache frequently accessed data
- Use specific field selection in relationships

---

## API Usage Examples

### 1. Get Paginated Employees with Filtering

```bash
curl -X GET "/api/v1/employees?page=1&per_page=20&filter_subsidiary=SMRU&filter_status=Local ID Staff&sort_by=first_name_en&sort_order=asc" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 2. Get Tree Search Data

```bash
curl -X GET "/api/v1/employees/tree-search" \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 3. Create New Employee

```bash
curl -X POST "/api/v1/employees" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "subsidiary": "SMRU",
    "staff_id": "EMP001",
    "first_name_en": "John",
    "last_name_en": "Doe",
    "gender": "Male",
    "date_of_birth": "1990-01-01",
    "status": "Local ID Staff"
  }'
```

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.1 | 2025-10-05 | **Tree Search Enhancement:** Added department and position information to tree search endpoint |
| 1.0 | 2025-10-01 | Initial comprehensive employee API documentation |

---

**End of Documentation**
