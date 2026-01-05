# Employment Management System - Complete Documentation

**Version:** 1.1  
**Last Updated:** October 14, 2025  
**System:** HRMS Backend API v1

---

## Table of Contents

1. [Overview](#overview)
2. [System Architecture](#system-architecture)
3. [Database Schema](#database-schema)
4. [Employment Lifecycle](#employment-lifecycle)
5. [API Endpoints Reference](#api-endpoints-reference)
6. [Business Logic & Rules](#business-logic--rules)
7. [Integration Points](#integration-points)
8. [Code Examples](#code-examples)
9. [Troubleshooting Guide](#troubleshooting-guide)

---

## 1. Overview

The Employment Management System is the core module of the HRMS that manages employee employment records, including salary information, probation periods, benefits, work locations, and funding allocations.

### Key Features

- ✅ **Employment Record Management** - Create, read, update, delete employment records
- ✅ **Funding Allocation Integration** - Mixed grant and org-funded allocations with FTE tracking
- ✅ **Probation Period Tracking** - Automatic pro-rated salary calculations with standardized 30-day month
- ✅ **Auto-Calculation Logic** - Automatic pass_probation_date calculation (3 months from start_date)
- ✅ **Probation Transition Service** - Automated probation completion with funding allocation updates
- ✅ **Scheduled Processing** - Daily automated probation completion processing
- ✅ **Employment History Audit Trail** - Automatic tracking of all employment changes
- ✅ **Advanced Filtering & Search** - Filter by subsidiary, type, location, department, position
- ✅ **Multi-Level Benefits** - Health welfare, PVD, and saving fund with customizable percentages
- ✅ **Performance Optimized** - Eager loading, caching, and pagination support
- ✅ **Validation & Business Rules** - Comprehensive validation ensuring data integrity

### System Components

```
Employment Management System
├── Controllers
│   └── EmploymentController (7 endpoints, 1,645 lines)
├── Models
│   ├── Employment (Main employment record)
│   ├── EmploymentHistory (Audit trail)
│   └── EmployeeFundingAllocation (Funding links)
├── Requests
│   ├── StoreEmploymentRequest (Create validation)
│   └── UpdateEmploymentRequest (Update validation)
├── Observers
│   └── EmploymentObserver (Business logic enforcement)
├── Services
│   ├── ProbationTransitionService (Probation calculations & transitions)
│   └── Integrated with PayrollService, PersonnelActionService
└── Console Commands
    └── ProcessProbationCompletions (Daily scheduled job)
```

---

## 2. System Architecture

### 2.1 Data Flow

```
┌─────────────┐
│   Client    │
│  (Frontend) │
└──────┬──────┘
       │
       ↓
┌─────────────────────────────────────┐
│    EmploymentController             │
│  - Request Validation               │
│  - Authorization Check              │
│  - Business Logic                   │
└──────┬──────────────────────────────┘
       │
       ↓
┌─────────────────────────────────────┐
│    Employment Model                 │
│  - Mass Assignment                  │
│  - Relationships                    │
│  - Query Scopes                     │
└──────┬──────────────────────────────┘
       │
       ↓
┌─────────────────────────────────────┐
│    EmploymentObserver               │
│  - Pre-save Validation              │
│  - Post-save Actions                │
│  - History Tracking                 │
└──────┬──────────────────────────────┘
       │
       ↓
┌─────────────────────────────────────┐
│    Database Layer                   │
│  - employments table                │
│  - employment_histories table       │
│  - employee_funding_allocations     │
└─────────────────────────────────────┘
```

### 2.2 Related Systems

The Employment system integrates with:

- **Employee System** - Links to employee records
- **Department & Position System** - Organizational structure
- **Work Location System** - Physical work locations
- **Funding Allocation System** - Grant and org-funded allocations
- **Payroll System** - Salary calculations and payroll processing
- **Personnel Actions System** - Employee transfers, promotions, etc.

---

## 3. Database Schema

### 3.1 Employments Table

```sql
CREATE TABLE employments (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    
    -- Employee Reference
    employee_id BIGINT UNSIGNED NOT NULL,
    
    -- Employment Details
    employment_type VARCHAR(255) NOT NULL COMMENT 'Full-time, Part-time, Contract, Temporary',
    pay_method VARCHAR(255) NULL COMMENT 'Transferred to bank, Cash cheque',
    start_date DATE NOT NULL,
    end_date DATE NULL,
    pass_probation_date DATE NULL COMMENT 'First day employee receives pass_probation_salary - typically 3 months after start_date',
    
    -- Organizational Links
    department_id BIGINT UNSIGNED NULL,
    position_id BIGINT UNSIGNED NULL,
    section_department VARCHAR(255) NULL,
    work_location_id BIGINT UNSIGNED NULL,
    
    -- Salary Information
    pass_probation_salary DECIMAL(10,2) NOT NULL COMMENT 'Regular salary after probation',
    probation_salary DECIMAL(10,2) NULL COMMENT 'Salary during probation period',
    
    -- Benefits Configuration
    health_welfare BOOLEAN DEFAULT FALSE,
    health_welfare_percentage DECIMAL(5,2) NULL COMMENT 'Health & Welfare percentage (0-100)',
    pvd BOOLEAN DEFAULT FALSE,
    pvd_percentage DECIMAL(5,2) NULL COMMENT 'PVD percentage (0-100)',
    saving_fund BOOLEAN DEFAULT FALSE,
    saving_fund_percentage DECIMAL(5,2) NULL COMMENT 'Saving Fund percentage (0-100)',
    
    -- Audit Fields
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    -- Foreign Keys
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE NO ACTION,
    FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE NO ACTION,
    FOREIGN KEY (work_location_id) REFERENCES work_locations(id) ON DELETE SET NULL,
    
    -- Indexes (for performance)
    INDEX idx_employee_id (employee_id),
    INDEX idx_employment_type (employment_type),
    INDEX idx_start_date (start_date),
    INDEX idx_end_date (end_date),
    INDEX idx_department_id (department_id),
    INDEX idx_position_id (position_id)
);
```

### 3.2 Employment Histories Table

Automatic audit trail for all employment changes:

```sql
CREATE TABLE employment_histories (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    
    -- References
    employment_id BIGINT UNSIGNED NOT NULL,
    employee_id BIGINT UNSIGNED NOT NULL,
    
    -- Snapshot of Employment Data
    employment_type VARCHAR(255) NOT NULL,
    start_date DATE NOT NULL,
    pass_probation_date DATE NULL,
    pay_method VARCHAR(255) NULL,
    department_id BIGINT UNSIGNED NULL,
    position_id BIGINT UNSIGNED NULL,
    work_location_id BIGINT UNSIGNED NULL,
    pass_probation_salary DECIMAL(10,2) NOT NULL,
    probation_salary DECIMAL(10,2) NULL,
    
    -- Status & Benefits
    active BOOLEAN DEFAULT TRUE,
    health_welfare BOOLEAN DEFAULT FALSE,
    pvd BOOLEAN DEFAULT FALSE,
    saving_fund BOOLEAN DEFAULT FALSE,
    
    -- Change Tracking
    change_date DATE NULL,
    change_reason VARCHAR(255) NULL,
    changed_by_user VARCHAR(255) NULL,
    changes_made JSON NULL COMMENT 'Details of what changed',
    previous_values JSON NULL COMMENT 'Previous values before change',
    notes TEXT NULL,
    
    -- Audit Fields
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    -- Foreign Keys
    FOREIGN KEY (employment_id) REFERENCES employments(id),
    FOREIGN KEY (employee_id) REFERENCES employees(id)
);
```

### 3.3 Key Relationships

```
Employment
├── belongsTo: Employee
├── belongsTo: Department
├── belongsTo: Position
├── belongsTo: WorkLocation
├── hasMany: EmployeeFundingAllocations
├── hasMany: Payrolls
└── hasMany: EmploymentHistories
```

---

## 4. Employment Lifecycle

### 4.1 Employment States

```
┌─────────────┐
│   Created   │ ← Initial employment record creation
└──────┬──────┘
       │
       ↓
┌─────────────┐
│ Probation   │ ← If pass_probation_date is set and in future
│   Period    │   Uses probation_salary
└──────┬──────┘
       │
       ↓
┌─────────────┐
│   Active    │ ← After pass_probation_date
│  (Regular)  │   Uses pass_probation_salary
└──────┬──────┘
       │
       ↓
┌─────────────┐
│ Terminated  │ ← When end_date is set and passed
└─────────────┘
```

### 4.2 Status Determination Logic

**Active Employment:**
```php
// Date-based logic
$isActive = $employment->start_date <= now()
    && (!$employment->end_date || $employment->end_date > now());
```

**Probation Status:**
```php
$isOnProbation = $employment->pass_probation_date 
    && $employment->pass_probation_date > now();
```

### 4.3 Salary Calculation During Probation (Standardized 30-Day Month)

**CRITICAL UPDATE: All salary calculations now use a standardized 30-day month, regardless of actual calendar days (28, 29, 30, or 31).**

When an employee passes probation mid-month, salary is pro-rated:

```
Formula:
dailyProbationRate = probation_salary / 30  (ALWAYS use 30)
dailyRegularRate = pass_probation_salary / 30  (ALWAYS use 30)

probationDays = (pass_probation_date day number) - 1
regularDays = 30 - probationDays

monthlySalary = (probationDays × dailyProbationRate) + (regularDays × dailyRegularRate)
```

**Example:**
```
probation_salary = ฿40,000
pass_probation_salary = ฿50,000
pass_probation_date = 15th (any month)

Daily probation: ฿40,000 / 30 = ฿1,333.33
Daily regular: ฿50,000 / 30 = ฿1,666.67

Probation days (1-14): 14 × ฿1,333.33 = ฿18,666.67
Regular days (15-30): 16 × ฿1,666.67 = ฿26,666.67

Total monthly: ฿45,333.34
```

**New Hire Starting Mid-Month:**
```
Formula:
working_days = 31 - start_day  (includes start day)
daily_rate = probation_salary / 30
first_month_salary = daily_rate × working_days

Example:
- Start on 10th
- probation_salary = ฿30,000
- working_days = 31 - 10 = 21 days
- daily_rate = ฿30,000 / 30 = ฿1,000
- first_month_salary = ฿1,000 × 21 = ฿21,000
```

---

## 5. API Endpoints Reference

### 5.1 List Employments

**GET** `/api/employments`

Get paginated list of employment records with advanced filtering and sorting.

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `page` | integer | No | Page number (default: 1) |
| `per_page` | integer | No | Items per page (1-100, default: 10) |
| `filter_subsidiary` | string | No | Filter by employee subsidiary (comma-separated) |
| `filter_employment_type` | string | No | Filter by type (comma-separated) |
| `filter_work_location` | string | No | Filter by work location (comma-separated) |
| `filter_department` | string | No | Filter by department (comma-separated) |
| `filter_position` | string | No | Filter by position (comma-separated) |
| `filter_status` | string | No | Filter by status: `active`, `inactive`, `probation` |
| `sort_by` | string | No | Sort field: `staff_id`, `name`, `work_location`, `start_date` |
| `sort_order` | string | No | Sort direction: `asc`, `desc` (default: `asc`) |
| `search` | string | No | Search by staff ID or employee name |

#### Response Example

```json
{
  "success": true,
  "message": "Employment records retrieved successfully",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "employee_id": 10,
        "employment_type": "Full-time",
        "start_date": "2024-01-15",
        "end_date": null,
        "pass_probation_date": "2024-04-15",
        "pass_probation_salary": "50000.00",
        "probation_salary": "45000.00",
        "pay_method": "Transferred to bank",
        "health_welfare": true,
        "health_welfare_percentage": "10.00",
        "pvd": true,
        "pvd_percentage": "5.00",
        "saving_fund": false,
        "saving_fund_percentage": null,
        "employee": {
          "id": 10,
          "staff_id": "EMP-001",
          "first_name_en": "John",
          "last_name_en": "Doe",
          "subsidiary": "SMRU",
          "status": "Local ID"
        },
        "department": {
          "id": 5,
          "name": "Research"
        },
        "position": {
          "id": 42,
          "title": "Research Assistant"
        },
        "workLocation": {
          "id": 3,
          "name": "Mae Sot Office"
        },
        "employeeFundingAllocations": [
          {
            "id": 1,
            "allocation_type": "grant",
            "fte": "60.00",
            "allocated_amount": "30000.00"
          },
          {
            "id": 2,
            "allocation_type": "org_funded",
            "fte": "40.00",
            "allocated_amount": "20000.00"
          }
        ]
      }
    ],
    "first_page_url": "http://api.example.com/api/employments?page=1",
    "from": 1,
    "last_page": 10,
    "last_page_url": "http://api.example.com/api/employments?page=10",
    "links": [...],
    "next_page_url": "http://api.example.com/api/employments?page=2",
    "path": "http://api.example.com/api/employments",
    "per_page": 10,
    "prev_page_url": null,
    "to": 10,
    "total": 95
  }
}
```

#### Performance Notes

- Uses eager loading for optimal performance
- Cached results for frequently accessed data
- Pagination prevents memory issues with large datasets
- Indexes on key filtering columns ensure fast queries

---

### 5.2 Search Employment by Staff ID

**GET** `/api/employments/search/{staffId}`

Quick search for employment by employee staff ID.

#### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `staffId` | string | Yes | Employee staff ID (path parameter) |

#### Response Example

```json
{
  "success": true,
  "message": "Employment record found successfully",
  "data": {
    "id": 1,
    "employee_id": 10,
    "employment_type": "Full-time",
    "start_date": "2024-01-15",
    "pass_probation_salary": "50000.00",
    "employee": {
      "id": 10,
      "staff_id": "EMP-001",
      "first_name_en": "John",
      "last_name_en": "Doe"
    }
    // ... full employment data
  }
}
```

#### Error Responses

**404 Not Found:**
```json
{
  "success": false,
  "message": "No employee found with staff ID: EMP-999"
}
```

---

### 5.3 Create Employment with Funding Allocations

**POST** `/api/employments`

Create a new employment record with associated funding allocations (atomic transaction).

#### Request Body

```json
{
  "employee_id": 10,
  "employment_type": "Full-time",
  "pay_method": "Transferred to bank",
  "start_date": "2025-01-15",
  "end_date": null,
  "pass_probation_date": "2025-04-15",
  "department_id": 5,
  "position_id": 42,
  "section_department": "Clinical Research",
  "work_location_id": 3,
  "pass_probation_salary": 50000,
  "probation_salary": 45000,
  "health_welfare": true,
  "health_welfare_percentage": 10,
  "pvd": true,
  "pvd_percentage": 5,
  "saving_fund": false,
  "saving_fund_percentage": null,
  "allocations": [
    {
      "allocation_type": "grant",
      "position_slot_id": 15,
      "fte": 60,
      "allocated_amount": 30000,
      "start_date": "2025-01-15",
      "end_date": "2025-12-31"
    },
    {
      "allocation_type": "org_funded",
      "grant_id": 3,
      "fte": 40,
      "allocated_amount": 20000,
      "start_date": "2025-01-15",
      "end_date": null
    }
  ]
}
```

#### Validation Rules

**Employment Fields:**
- `employee_id`: Required, must exist in employees table
- `employment_type`: Required, must be one of: `Full-time`, `Part-time`, `Contract`, `Temporary`
- `pay_method`: Optional, must be one of: `Transferred to bank`, `Cash cheque`
- `start_date`: Required, valid date
- `end_date`: Optional, must be after start_date
- `pass_probation_date`: Optional, auto-calculated as start_date + 3 months if not provided, must be after start_date
- `department_id`: Required, must exist
- `position_id`: Required, must exist and belong to selected department
- `pass_probation_salary`: Required, numeric, minimum 0
- `probation_salary`: Optional, numeric, minimum 0
- Benefits percentages: 0-100 range

**Allocation Fields:**
- `allocations`: Required array, minimum 1 allocation
- `allocation_type`: Required, `grant` or `org_funded`
- `fte`: Required, 0.01-100 range
- **Total FTE must equal exactly 100%**
- For grant type: `position_slot_id` is required
- For org_funded type: `grant_id` is required

#### Business Rules Enforced

1. **No Duplicate Active Employment**: Employee cannot have multiple active employments
2. **FTE Must Equal 100%**: Total of all allocation FTEs must be exactly 100
3. **Department-Position Validation**: Position must belong to selected department
4. **Probation Date Logic**: If set, must be after start_date
5. **Atomic Transaction**: Either all records created or none (rollback on error)

#### Response Example

```json
{
  "success": true,
  "message": "Employment and funding allocations created successfully",
  "data": {
    "employment": {
      "id": 150,
      "employee_id": 10,
      "employment_type": "Full-time",
      "start_date": "2025-01-15",
      "pass_probation_salary": "50000.00",
      "created_at": "2025-10-13T10:30:00.000000Z",
      "updated_at": "2025-10-13T10:30:00.000000Z"
    },
    "org_funded_allocations": [
      {
        "id": 45,
        "grant_id": 3,
        "department_id": 5,
        "position_id": 42
      }
    ],
    "funding_allocations": [
      {
        "id": 201,
        "employment_id": 150,
        "allocation_type": "grant",
        "fte": "60.00"
      },
      {
        "id": 202,
        "employment_id": 150,
        "allocation_type": "org_funded",
        "fte": "40.00"
      }
    ]
  },
  "summary": {
    "employment_created": true,
    "org_funded_created": 1,
    "funding_allocations_created": 2
  }
}
```

#### Error Responses

**422 Validation Error:**
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "allocations": [
      "Total FTE must equal 100%. Current total: 95.00%"
    ]
  }
}
```

**409 Conflict:**
```json
{
  "success": false,
  "message": "Employee already has an active employment record",
  "existing_employment_id": 149
}
```

---

### 5.4 Get Employment Details

**GET** `/api/employments/{id}`

Retrieve detailed information about a specific employment record.

#### Response Example

```json
{
  "success": true,
  "message": "Employment record retrieved successfully",
  "data": {
    "id": 1,
    "employee_id": 10,
    "employment_type": "Full-time",
    "start_date": "2024-01-15",
    "end_date": null,
    "pass_probation_date": "2024-04-15",
    "pass_probation_salary": "50000.00",
    "probation_salary": "45000.00",
    "is_active": true,
    "is_on_probation": false,
    "formatted_salary": "50,000.00",
    "employee": { /* full employee data */ },
    "department": { /* full department data */ },
    "position": { /* full position data */ },
    "workLocation": { /* full work location data */ },
    "employeeFundingAllocations": [ /* all allocations */ ],
    "employmentHistories": [ /* change history */ ]
  }
}
```

---

### 5.5 Get Funding Allocations

**GET** `/api/employments/{id}/funding-allocations`

Retrieve all funding allocations for a specific employment.

#### Response Example

```json
{
  "success": true,
  "message": "Funding allocations retrieved successfully",
  "data": [
    {
      "id": 201,
      "employment_id": 150,
      "allocation_type": "grant",
      "fte": "60.00",
      "allocated_amount": "30000.00",
      "start_date": "2025-01-15",
      "end_date": "2025-12-31",
      "positionSlot": {
        "id": 15,
        "slot_number": "PS-2025-001",
        "grantItem": {
          "id": 25,
          "grant_position": "Research Assistant",
          "grant_salary": "50000.00",
          "budgetline_code": "BL-001"
        }
      }
    },
    {
      "id": 202,
      "employment_id": 150,
      "allocation_type": "org_funded",
      "fte": "40.00",
      "allocated_amount": "20000.00",
      "orgFunded": {
        "id": 45,
        "grant": {
          "id": 3,
          "name": "Core Funding Grant",
          "code": "CFG-2025"
        }
      }
    }
  ],
  "summary": {
    "total_allocations": 2,
    "total_fte": "100.00",
    "grant_allocations": 1,
    "org_funded_allocations": 1
  }
}
```

---

### 5.6 Update Employment

**PATCH/PUT** `/api/employments/{id}`

Update an existing employment record. All fields are optional.

#### Request Body Example

```json
{
  "pass_probation_salary": 55000,
  "department_id": 6,
  "position_id": 48,
  "health_welfare_percentage": 12
}
```

#### Response Example

```json
{
  "success": true,
  "message": "Employment updated successfully",
  "data": {
    "id": 1,
    "pass_probation_salary": "55000.00",
    "department_id": 6,
    "position_id": 48,
    "updated_at": "2025-10-13T15:45:00.000000Z"
  },
  "changes": {
    "pass_probation_salary": {
      "old": "50000.00",
      "new": "55000.00"
    },
    "department_id": {
      "old": 5,
      "new": 6
    }
  }
}
```

#### Automatic History Tracking

Every update automatically creates an employment history record with:
- Snapshot of all employment data
- List of what changed
- Previous values
- Change reason (auto-generated)
- Changed by user
- Timestamp

---

### 5.7 Complete Probation (Manual Trigger)

**POST** `/api/employments/{id}/complete-probation`

Manually trigger probation completion for an employment record. This updates all funding allocations from `probation_salary` to `pass_probation_salary` and creates an employment history entry.

#### Use Cases
- Manual override when automated process fails
- Immediate probation completion outside of pass_probation_date
- Testing probation completion logic

#### Request
No request body required.

#### Response Example

```json
{
  "success": true,
  "message": "Probation completed successfully and funding allocations updated",
  "data": {
    "employment": {
      "id": 1,
      "employee_id": 10,
      "pass_probation_date": "2025-04-15",
      "probation_salary": "45000.00",
      "pass_probation_salary": "50000.00",
      "updated_at": "2025-10-14T10:30:00.000000Z"
    },
    "updated_allocations": [
      {
        "id": 201,
        "employment_id": 1,
        "fte": "60.00",
        "allocated_amount": "30000.00",
        "updated_at": "2025-10-14T10:30:00.000000Z"
      },
      {
        "id": 202,
        "employment_id": 1,
        "fte": "40.00",
        "allocated_amount": "20000.00",
        "updated_at": "2025-10-14T10:30:00.000000Z"
      }
    ]
  }
}
```

#### Error Responses

**400 Bad Request** - Probation already completed:
```json
{
  "success": false,
  "message": "Probation completion already processed for this employment."
}
```

**400 Bad Request** - No probation salary:
```json
{
  "success": false,
  "message": "Employment does not have probation salary configured."
}
```

---

### 5.8 Delete Employment

**DELETE** `/api/employments/{id}`

Soft delete an employment record.

#### Response Example

```json
{
  "success": true,
  "message": "Employment record deleted successfully"
}
```

#### Business Rules

- Cannot delete if employment has associated payroll records
- Cascades to employment histories (depending on configuration)
- Funding allocations are also removed

---

## 6. Business Logic & Rules

### 6.1 Employment Status Rules

#### Active Employment
```php
An employment is considered "active" if:
- start_date <= today
- AND (end_date is NULL OR end_date > today)
```

#### Inactive Employment
```php
An employment is "inactive" if:
- end_date is set AND end_date <= today
```

#### Probation Period
```php
An employee is "on probation" if:
- pass_probation_date is set
- AND pass_probation_date > today
```

### 6.2 Salary Rules

1. **Pass Probation Salary** (`pass_probation_salary`)
   - This is the **regular salary** after probation period ends
   - **Always required**
   - Used for payroll calculations after pass_probation_date
   - Must be greater than 0

2. **Probation Salary** (`probation_salary`)
   - Optional field
   - If not set, system uses `pass_probation_salary` during probation
   - Typically lower than pass_probation_salary
   - Used for payroll calculations before pass_probation_date

3. **Mid-Month Probation Transition**
   - When pass_probation_date falls mid-month
   - Salary is pro-rated using standardized 30-day month approach
   - See formula in section 4.3

4. **Auto-Calculation**
   - If pass_probation_date is not provided during employment creation
   - System automatically calculates: `pass_probation_date = start_date + 3 months`
   - If start_date is updated, pass_probation_date is recalculated automatically

### 6.3 Funding Allocation Rules

1. **Total FTE Must Equal 100%**
   ```
   Sum of all allocation FTEs must equal exactly 100%
   Validation allows 0.01% tolerance for floating point precision
   ```

2. **Allocation Types**
   - **Grant**: Requires `position_slot_id`
   - **Org Funded**: Requires `grant_id`

3. **Allocation Amount Calculation**
   ```
   allocated_amount = pass_probation_salary × (fte / 100)
   
   Example:
   pass_probation_salary = ฿50,000
   fte = 60%
   allocated_amount = ฿50,000 × 0.60 = ฿30,000
   ```

### 6.4 Department-Position Validation

**Critical Rule**: Position must belong to selected department

```php
// Validation in StoreEmploymentRequest
'position_id' => [
    'required',
    'integer',
    'exists:positions,id',
    Rule::exists('positions', 'id')
        ->where(fn ($q) => $q->where('department_id', $this->department_id))
]
```

This ensures organizational integrity and prevents data inconsistencies.

### 6.5 Benefits Configuration

Three types of benefits are supported:

1. **Health & Welfare**
   - Boolean flag: `health_welfare`
   - Percentage: `health_welfare_percentage` (0-100)
   - Used in payroll deductions/additions

2. **Provident Fund (PVD)**
   - Boolean flag: `pvd`
   - Percentage: `pvd_percentage` (0-100)
   - Employee contribution to retirement fund

3. **Saving Fund**
   - Boolean flag: `saving_fund`
   - Percentage: `saving_fund_percentage` (0-100)
   - Employee savings program

---

## 7. Integration Points

### 7.1 Employee System

```php
// Employment belongs to Employee
$employment->employee // Returns Employee model

// Employee can have multiple employments
$employee->employments // Returns Collection of Employment
$employee->currentEmployment() // Returns active Employment
```

### 7.2 Payroll System

```php
// Payrolls are generated from Employment
$employment->payrolls // Returns Collection of Payroll

// Salary calculation uses:
- pass_probation_salary (after probation)
- probation_salary (during probation)
- FTE allocations
- Benefits percentages
```

### 7.3 Personnel Actions System

```php
// Personnel actions modify Employment
PersonnelAction::execute() {
    // Updates employment fields:
    - department_id
    - position_id
    - pass_probation_salary
    - work_location_id
}
```

### 7.4 Funding Allocation System

```php
// Employment links to funding sources
$employment->employeeFundingAllocations

// Two types:
1. Grant-based (position_slot_id)
2. Org-funded (grant_id via org_funded_allocation)

// Automatic updates on probation completion:
// - allocated_amount recalculated from probation_salary to pass_probation_salary
// - Formula: new_allocated_amount = pass_probation_salary × (fte / 100)
```

### 7.5 Probation Transition Service

**Service Class:** `App\Services\ProbationTransitionService`

This service handles all probation-related calculations using a **standardized 30-day month approach**.

#### Key Methods:

1. **handleProbationCompletion($employment, $transitionDate)**
   - Updates funding allocations from probation to regular salary
   - Creates employment history entry
   - Wrapped in database transaction for safety
   - Returns success/failure status

2. **calculateWorkingDays($startDate)**
   - Formula: `working_days = 31 - start_day`
   - Includes start day as working day
   - Examples:
     - Start on 1st: 30 days (full month)
     - Start on 10th: 21 days
     - Start on 15th: 16 days

3. **calculateProRatedSalary($employment, $payrollMonth)**
   - Handles mid-month probation transition
   - Always divides by 30 for daily rates
   - Pro-rates between probation and regular salary

4. **calculateFirstMonthSalary($employment)**
   - Handles new hire starting mid-month
   - Formula: `(salary / 30) × working_days`

5. **calculatePassProbationDate($startDate, $months = 3)**
   - Auto-calculates: `start_date + 3 months`
   - Used when pass_probation_date not provided

#### Example Usage:

```php
use App\Services\ProbationTransitionService;

$service = app(ProbationTransitionService::class);

// Calculate first month salary for mid-month hire
$firstMonthSalary = $service->calculateFirstMonthSalary($employment);

// Calculate pro-rated salary during transition month
$proRatedSalary = $service->calculateProRatedSalary($employment, $payrollMonth);

// Process probation completion
$result = $service->handleProbationCompletion($employment, now());
if ($result['success']) {
    echo "Probation completed and allocations updated!";
}
```

### 7.6 Automated Probation Processing

**Console Command:** `employment:process-probation-completions`
**Schedule:** Daily at 00:01
**Location:** `app/Console/Commands/ProcessProbationCompletions.php`

#### What It Does:
1. Finds all employments where `pass_probation_date = today`
2. Filters for records with `probation_salary` set (had probation period)
3. Updates funding allocations from probation to regular salary
4. Creates employment history entry
5. Logs each processed employment
6. Skips already-processed records

#### How to Run Manually:
```bash
php artisan employment:process-probation-completions
```

#### Expected Output:
```
Starting probation completion processing...
Found 3 employment(s) completing probation today.
  ✓ Employee EMP001 (John Doe): Probation completed successfully
  ✓ Employee EMP045 (Jane Smith): Probation completed successfully
  ✓ Employee EMP087 (Bob Johnson): Probation completed successfully

=== Processing Summary ===
Total found: 3
Successfully processed: 3
Failed: 0
```

#### Schedule Configuration:
Located in `bootstrap/app.php`:
```php
->withSchedule(function (Schedule $schedule) {
    $schedule->command('employment:process-probation-completions')->dailyAt('00:01');
})
```

To ensure the scheduler runs, add to cron:
```bash
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

---

## 8. Code Examples

### 8.1 Creating Employment with Mixed Funding

```php
use App\Models\Employment;
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    // 1. Create employment
    $employment = Employment::create([
        'employee_id' => 10,
        'employment_type' => 'Full-time',
        'start_date' => '2025-01-15',
        'pass_probation_date' => '2025-04-15',
        'department_id' => 5,
        'position_id' => 42,
        'work_location_id' => 3,
        'pass_probation_salary' => 50000,
        'probation_salary' => 45000,
        'health_welfare' => true,
        'health_welfare_percentage' => 10,
        'pvd' => true,
        'pvd_percentage' => 5,
        'created_by' => auth()->user()->name,
    ]);
    
    // 2. Create org-funded allocation (if needed)
    $orgFunded = OrgFundedAllocation::create([
        'grant_id' => 3,
        'department_id' => 5,
        'position_id' => 42,
    ]);
    
    // 3. Create employee funding allocations
    EmployeeFundingAllocation::create([
        'employment_id' => $employment->id,
        'allocation_type' => 'grant',
        'position_slot_id' => 15,
        'fte' => 60,
        'allocated_amount' => 30000,
    ]);
    
    EmployeeFundingAllocation::create([
        'employment_id' => $employment->id,
        'allocation_type' => 'org_funded',
        'org_funded_id' => $orgFunded->id,
        'fte' => 40,
        'allocated_amount' => 20000,
    ]);
});
```

### 8.2 Querying Active Employments

```php
// Using scope
$activeEmployments = Employment::active()->get();

// Manual query
$activeEmployments = Employment::where('start_date', '<=', now())
    ->where(function ($q) {
        $q->whereNull('end_date')
          ->orWhere('end_date', '>', now());
    })
    ->get();
```

### 8.3 Getting Employment with Full Details

```php
$employment = Employment::with([
    'employee:id,staff_id,first_name_en,last_name_en,subsidiary',
    'department:id,name',
    'position:id,title',
    'workLocation:id,name',
    'employeeFundingAllocations' => function ($q) {
        $q->with([
            'positionSlot.grantItem.grant',
            'orgFunded.grant'
        ]);
    },
    'employmentHistories' => function ($q) {
        $q->latest()->limit(10);
    }
])->find($id);
```

### 8.4 Updating Employment and Tracking Changes

```php
$employment = Employment::find(1);

// Update fields
$employment->update([
    'pass_probation_salary' => 55000,
    'department_id' => 6,
    'position_id' => 48,
    'updated_by' => auth()->user()->name,
]);

// History is automatically created by EmploymentObserver
// Check what changed:
$history = $employment->employmentHistories()->latest()->first();
echo $history->change_reason; // "Salary adjustment, Department change, Position change"
echo json_encode($history->changes_made);
echo json_encode($history->previous_values);
```

### 8.5 Checking Probation Status

```php
$employment = Employment::find(1);

if ($employment->pass_probation_date && $employment->pass_probation_date > now()) {
    echo "Employee is on probation";
    $daysRemaining = now()->diffInDays($employment->pass_probation_date);
    echo "Days remaining: " . $daysRemaining;
} else {
    echo "Employee has passed probation";
}
```

### 8.6 Filtering Employments with Pagination

```php
$employments = Employment::query()
    ->with(['employee', 'department', 'position', 'workLocation'])
    ->when($request->filter_subsidiary, function ($q) use ($request) {
        $subsidiaries = explode(',', $request->filter_subsidiary);
        $q->whereHas('employee', function ($subQ) use ($subsidiaries) {
            $subQ->whereIn('subsidiary', $subsidiaries);
        });
    })
    ->when($request->filter_employment_type, function ($q) use ($request) {
        $types = explode(',', $request->filter_employment_type);
        $q->whereIn('employment_type', $types);
    })
    ->when($request->filter_status === 'active', function ($q) {
        $q->active();
    })
    ->when($request->search, function ($q) use ($request) {
        $q->whereHas('employee', function ($subQ) use ($request) {
            $subQ->where('staff_id', 'like', "%{$request->search}%")
                 ->orWhereRaw("CONCAT(first_name_en, ' ', last_name_en) LIKE ?", ["%{$request->search}%"]);
        });
    })
    ->paginate($request->per_page ?? 10);
```

---

## 9. Troubleshooting Guide

### Common Issues and Solutions

#### Issue 1: "Total FTE must equal 100%"

**Cause**: Sum of allocation FTEs doesn't equal exactly 100

**Solution**:
```php
// Check allocation totals
$total = array_sum(array_column($allocations, 'fte'));
echo "Total FTE: " . $total; // Should be 100.00

// Adjust allocations to sum to 100
$allocations = [
    ['fte' => 60, ...],
    ['fte' => 40, ...]  // 60 + 40 = 100 ✓
];
```

#### Issue 2: "Employee already has an active employment record"

**Cause**: Attempting to create second active employment

**Solution**:
```php
// Option 1: End previous employment first
$previousEmployment = Employment::where('employee_id', $employeeId)
    ->active()
    ->first();
    
$previousEmployment->update([
    'end_date' => now()->subDay(),
]);

// Option 2: Check for existing employment before creating
$hasActive = Employment::where('employee_id', $employeeId)
    ->active()
    ->exists();
    
if (!$hasActive) {
    // Create new employment
}
```

#### Issue 3: "Position must belong to selected department"

**Cause**: Position doesn't belong to the selected department

**Solution**:
```php
// Check position-department relationship
$position = Position::find($positionId);
if ($position->department_id !== $departmentId) {
    // Either:
    // 1. Select correct position for department
    // 2. Select correct department for position
}

// Get positions for a department
$positions = Position::where('department_id', $departmentId)->get();
```

#### Issue 4: Employment history not being created

**Cause**: Observer not registered or disabled

**Solution**:
```php
// Check if observer is registered in EventServiceProvider
protected $observers = [
    Employment::class => [EmploymentObserver::class],
];

// Or in AppServiceProvider boot():
Employment::observe(EmploymentObserver::class);
```

#### Issue 5: Probation salary calculation incorrect

**Cause**: Probation pass date logic issue

**Solution**:
```php
// Verify probation logic
$probationPassDate = Carbon::parse($employment->pass_probation_date);
$payPeriodDate = Carbon::parse($payrollMonth);

if ($probationPassDate->between($payPeriodDate->startOfMonth(), $payPeriodDate->endOfMonth())) {
    // Mid-month transition - use pro-rated calculation
    $proRatedSalary = calculateProRatedSalary($employment, $payPeriodDate);
} elseif ($probationPassDate > $payPeriodDate) {
    // Still on probation - use probation_salary
    $salary = $employment->probation_salary;
} else {
    // Passed probation - use pass_probation_salary
    $salary = $employment->pass_probation_salary;
}
```

#### Issue 6: Performance issues with large datasets

**Solutions**:

1. **Use pagination**:
```php
Employment::paginate(50); // Instead of ->get()
```

2. **Optimize eager loading**:
```php
Employment::with(['employee:id,staff_id,first_name_en,last_name_en'])
    ->select(['id', 'employee_id', 'pass_probation_salary'])
    ->get();
```

3. **Use indexes**:
```sql
-- Check if indexes exist
SHOW INDEX FROM employments;

-- Add missing indexes
CREATE INDEX idx_employee_id ON employments(employee_id);
CREATE INDEX idx_start_date ON employments(start_date);
```

4. **Enable query caching**:
```php
// In controller
$employments = Cache::remember('employments.list', 3600, function () {
    return Employment::with('employee')->get();
});
```

---

## Appendix A: Model Accessors

The Employment model provides helpful accessor methods:

```php
// Full employment type name
$employment->full_employment_type // "Full-time Employee"

// Active status check
$employment->is_active // true/false

// Formatted salary with commas
$employment->formatted_salary // "50,000.00"
```

## Appendix B: Query Scopes

Available query scopes on Employment model:

```php
// Active employments
Employment::active()->get();

// Inactive employments
Employment::inactive()->get();

// By employment type
Employment::byEmploymentType('Full-time')->get();

// By department
Employment::byDepartment(5)->get();

// By date range
Employment::byDateRange('2024-01-01', '2024-12-31')->get();

// With funding allocations eager loaded
Employment::withFundingAllocations()->get();

// For payroll processing (optimized loading)
Employment::forPayroll()->get();
```

## Appendix C: API Rate Limiting

Employment API endpoints are rate-limited:

- **Authenticated requests**: 60 requests per minute
- **Unauthenticated requests**: 20 requests per minute

Rate limit headers are included in all responses:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1697184000
```

---

## Summary

The Employment Management System is a comprehensive module that handles all aspects of employee employment records in the HRMS. Key takeaways:

✅ **Robust Data Model** - Well-structured database schema with proper relationships and constraints  
✅ **Comprehensive API** - 7 endpoints covering all CRUD operations plus advanced features  
✅ **Business Logic Enforcement** - Validation rules ensure data integrity  
✅ **Audit Trail** - Automatic history tracking for compliance  
✅ **Performance Optimized** - Caching, eager loading, and pagination  
✅ **Integration Ready** - Seamlessly integrates with Payroll, Personnel Actions, and Funding systems  

For additional support or questions, refer to:
- API Swagger Documentation: `/api/documentation`
- Payroll System Documentation: `PAYROLL_SYSTEM_COMPLETE_DOCUMENTATION.md`
- Personnel Actions Documentation: `PERSONNEL_ACTIONS_COMPLETE_DOCUMENTATION.md`

---

**Document Version**: 1.0  
**Last Updated**: October 13, 2025  
**Maintained By**: HRMS Development Team

