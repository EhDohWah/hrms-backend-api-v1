# HRMS Database Schema & Relationships Analysis
**Complete System Architecture Documentation**

**Date**: 2025-11-15
**Version**: 1.0
**Status**: Current Implementation
**Purpose**: Comprehensive database schema analysis for AI review and recommendations

---

## Table of Contents
1. [System Overview](#system-overview)
2. [Complete Database Schema](#complete-database-schema)
3. [Entity Relationship Diagram (ERD)](#entity-relationship-diagram-erd)
4. [Table Details & Relationships](#table-details--relationships)
5. [Foreign Key Relationships Map](#foreign-key-relationships-map)
6. [Model Relationships (Eloquent)](#model-relationships-eloquent)
7. [Key Architectural Patterns](#key-architectural-patterns)
8. [Data Flow Diagrams](#data-flow-diagrams)
9. [Indexes & Performance Optimization](#indexes--performance-optimization)
10. [Constraints & Business Rules](#constraints--business-rules)

---

## System Overview

### Database Technology
- **DBMS**: MySQL / MSSQL Server compatible
- **ORM**: Laravel Eloquent
- **Migration System**: Laravel Migrations
- **ID Strategy**: Auto-increment BIGINT (not UUIDs)
- **Naming Convention**: snake_case

### Core System Components

```
┌─────────────────────────────────────────────────────────────────┐
│                     HRMS CORE ARCHITECTURE                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  1. USER MANAGEMENT                                             │
│     └─ users, permissions, roles                                │
│                                                                 │
│  2. EMPLOYEE MANAGEMENT                                         │
│     └─ employees, employee_* (children, languages, education)   │
│                                                                 │
│  3. ORGANIZATIONAL STRUCTURE                                    │
│     └─ departments, positions (hierarchical reporting)          │
│                                                                 │
│  4. EMPLOYMENT CONTRACTS                                        │
│     └─ employments, employment_histories                        │
│                                                                 │
│  5. MULTI-SOURCE FUNDING ALLOCATION                             │
│     └─ grants → grant_items → position_slots                    │
│     └─ org_funded_allocations                                   │
│     └─ employee_funding_allocations (THE AGGREGATOR)            │
│                                                                 │
│  6. PROBATION MANAGEMENT                                        │
│     └─ probation_records (event-based tracking)                 │
│                                                                 │
│  7. LEAVE MANAGEMENT                                            │
│     └─ leave_types, leave_requests, leave_request_items         │
│     └─ leave_balances                                           │
│                                                                 │
│  8. PAYROLL SYSTEM                                              │
│     └─ payrolls (encrypted salary data)                         │
│     └─ bulk_payroll_batches                                     │
│     └─ tax_brackets, tax_settings                               │
│                                                                 │
│  9. PERSONNEL ACTIONS                                           │
│     └─ personnel_actions (promotions, transfers, salary adj.)   │
│     └─ resignations                                             │
│                                                                 │
│ 10. SUPPORTING SYSTEMS                                          │
│     └─ work_locations, benefit_settings, lookups                │
│     └─ interviews, job_offers, trainings                        │
│     └─ travel_requests                                          │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

---

## Complete Database Schema

### 📊 **Table Count**: 50+ tables

### Core Tables Summary

| Category | Table Name | Primary Purpose | Key Relationships |
|----------|------------|-----------------|-------------------|
| **Authentication** | `users` | System authentication | → employees (1:1) |
| | `password_reset_tokens` | Password recovery | |
| | `sessions` | User sessions | |
| | `personal_access_tokens` | API tokens | |
| | `permissions`, `roles` | RBAC system | |
| **Employees** | `employees` | Personal information | ← users, → employments |
| | `employee_identifications` | ID documents | → employees |
| | `employee_beneficiaries` | Emergency contacts | → employees |
| | `employee_children` | Dependent children | → employees |
| | `employee_languages` | Language proficiency | → employees |
| | `employee_education` | Education history | → employees |
| **Organization** | `departments` | Organizational units | ← positions |
| | `positions` | Job titles + hierarchy | → departments, → positions (reports_to) |
| | `work_locations` | Physical locations | ← employments |
| **Employment** | `employments` | Employment contracts | → employees, → departments, → positions |
| | `employment_histories` | Audit trail | → employments |
| | `probation_records` | Probation tracking | → employments |
| **Funding** | `grants` | Funding sources | ← grant_items |
| | `grant_items` | Grant positions/budget lines | → grants, ← position_slots |
| | `position_slots` | Individual grant slots | → grant_items |
| | `org_funded_allocations` | Org-funded positions | → grants, → departments, → positions |
| | `employee_funding_allocations` | **MULTI-SOURCE FUNDING** | → employees, → employments, → position_slots / org_funded |
| | `allocation_change_logs` | Funding change history | → employee_funding_allocations |
| **Leave** | `leave_types` | Leave categories | ← leave_requests |
| | `leave_requests` | Leave applications | → employees, ← leave_request_items |
| | `leave_request_items` | Multi-type leave support | → leave_requests, → leave_types |
| | `leave_balances` | Annual entitlements | → employees, → leave_types |
| **Payroll** | `payrolls` | Monthly payroll records | → employments, → employee_funding_allocations |
| | `bulk_payroll_batches` | Bulk payroll processing | |
| | `tax_brackets` | Tax calculation rules | |
| | `tax_settings` | Tax configuration | |
| | `benefit_settings` | Global benefit percentages | |
| **Personnel Actions** | `personnel_actions` | Promotions, transfers, etc. | → employments, → departments, → positions |
| | `resignations` | Resignation tracking | → employees, → departments, → positions |
| **Recruitment** | `interviews` | Interview scheduling | → employees |
| | `job_offers` | Job offer management | → employees |
| **Training** | `trainings` | Training programs | |
| | `employee_trainings` | Employee-training links | → employees, → trainings |
| **Other** | `travel_requests` | Travel management | → employees |
| | `letter_templates` | Document templates | |
| | `lookups` | System lookup values | |
| | `subsidiaries` | Company subsidiaries | |
| | `subsidiary_hub_funds` | Hub fund tracking | |
| | `notifications` | System notifications | |
| | `deleted_models` | Soft delete audit | |

---

## Entity Relationship Diagram (ERD)

### Core HR System ERD (Simplified)

```
┌──────────────┐
│    users     │
│──────────────│
│ id (PK)      │
│ name         │
│ email        │
│ password     │
└──────┬───────┘
       │ 1:1
       ↓
┌──────────────────────┐
│     employees        │
│──────────────────────│
│ id (PK)              │
│ user_id (FK)         │───────────────┐
│ staff_id (unique)    │               │
│ subsidiary           │               │
│ first_name_en/th     │               │
│ last_name_en/th      │               │
│ date_of_birth        │               │
│ gender               │               │
│ nationality          │               │
│ bank_account_*       │               │
│ ... (40+ fields)     │               │
└──────┬───────────────┘               │
       │ 1:M                           │
       ↓                               │ 1:M
┌──────────────────────────────────┐   │
│        employments               │   │
│──────────────────────────────────│   │
│ id (PK)                          │   │
│ employee_id (FK) ────────────────┘   │
│ department_id (FK) ─────────┐        │
│ position_id (FK) ────────┐  │        │
│ section_department       │  │        │
│ work_location_id (FK)    │  │        │
│ employment_type          │  │        │
│ start_date               │  │        │
│ end_date                 │  │        │
│ pass_probation_date      │  │        │
│ probation_salary         │  │        │
│ pass_probation_salary    │  │        │
│ health_welfare (bool)    │  │        │
│ pvd (bool)               │  │        │
│ saving_fund (bool)       │  │        │
│ status (bool)            │  │        │
└──────┬────────┬──────────┘  │        │
       │ 1:M    │             │        │
       │        ↓             ↓        ↓
       │  ┌──────────┐  ┌──────────┐  ┌──────────────┐
       │  │positions │  │departments│  │employee_*    │
       │  │──────────│  │──────────│  │──────────────│
       │  │id (PK)   │  │id (PK)   │  │children      │
       │  │title     │  │name      │  │languages     │
       │  │dept_id(FK│  │desc.     │  │education     │
       │  │reports_to│  │is_active │  │identifications│
       │  │  _id(FK) │  └──────────┘  │beneficiaries │
       │  │level     │                └──────────────┘
       │  │is_manager│
       │  │is_active │
       │  └──────────┘
       │
       ↓ 1:M
┌─────────────────────────────────┐
│  employee_funding_allocations   │
│─────────────────────────────────│
│ id (PK)                         │
│ employee_id (FK)                │
│ employment_id (FK)              │
│ org_funded_id (FK, nullable)    │───┐
│ position_slot_id (FK, nullable) │───┼──┐
│ allocation_type                 │   │  │
│ fte (%)                         │   │  │
│ allocated_amount                │   │  │
│ salary_type                     │   │  │
│ status                          │   │  │
│ start_date, end_date            │   │  │
└─────────┬───────────────────────┘   │  │
          │ M:1                        │  │
          ↓                            │  │
┌─────────────────┐                   │  │
│    payrolls     │                   │  │
│─────────────────│                   │  │
│ id (PK)         │                   │  │
│ employment_id   │                   │  │
│ emp_funding_    │                   │  │
│   allocation_id │                   │  │
│ gross_salary    │ (encrypted)       │  │
│ net_salary      │ (encrypted)       │  │
│ tax             │ (encrypted)       │  │
│ pvd             │ (encrypted)       │  │
│ ... (20+ fields)│                   │  │
│ pay_period_date │                   │  │
└─────────────────┘                   │  │
                                      │  │
        ┌─────────────────────────────┘  │
        ↓                                │
┌────────────────────────┐               │
│ org_funded_allocations │               │
│────────────────────────│               │
│ id (PK)                │               │
│ grant_id (FK)          │               │
│ department_id (FK)     │               │
│ position_id (FK)       │               │
│ description            │               │
└────────────────────────┘               │
                                         │
        ┌────────────────────────────────┘
        ↓
┌───────────────────┐
│ position_slots    │
│───────────────────│
│ id (PK)           │
│ grant_item_id(FK) │───┐
│ slot_number       │   │
└───────────────────┘   │
                        │ M:1
                        ↓
                ┌──────────────┐
                │ grant_items  │
                │──────────────│
                │ id (PK)      │
                │ grant_id(FK) │───┐
                │ grant_position   │
                │ grant_salary     │
                │ grant_benefit    │
                │ grant_LOE        │
                │ budgetline_code  │
                └──────────────┘   │
                                   │ M:1
                                   ↓
                           ┌───────────┐
                           │  grants   │
                           │───────────│
                           │ id (PK)   │
                           │ code      │
                           │ name      │
                           │ subsidiary│
                           │ end_date  │
                           └───────────┘
```

### Probation System ERD

```
┌──────────────────────┐
│    employments       │
│──────────────────────│
│ pass_probation_date  │
└──────┬───────────────┘
       │ 1:M
       ↓
┌────────────────────────────┐
│    probation_records       │
│────────────────────────────│
│ id (PK)                    │
│ employment_id (FK)         │
│ employee_id (FK)           │
│ event_type VARCHAR(20)     │ ← 'initial', 'extension', 'passed', 'failed'
│ event_date                 │
│ decision_date              │
│ probation_start_date       │
│ probation_end_date         │
│ previous_end_date          │
│ extension_number (0,1,2..) │
│ decision_reason            │
│ evaluation_notes           │
│ approved_by                │
│ is_active (bool)           │ ← Identifies current record
└────────────────────────────┘

Key Concept: Single Source of Truth
- NO probation_status in employments table
- Status derived from: active_record.event_type
- Full history maintained in probation_records
```

### Leave Management ERD (Multi-Type Support)

```
┌───────────────┐
│  employees    │
└───┬───────────┘
    │ 1:M
    ↓
┌──────────────────────┐
│  leave_requests      │
│──────────────────────│
│ id (PK)              │
│ employee_id (FK)     │
│ start_date           │
│ end_date             │
│ total_days           │
│ reason               │
│ status               │
│ supervisor_approved  │
│ hr_site_admin_approved│
└──────┬───────────────┘
       │ 1:M
       ↓
┌───────────────────────┐
│ leave_request_items   │  ← Multi-Type Leave Support
│───────────────────────│
│ id (PK)               │
│ leave_request_id (FK) │
│ leave_type_id (FK)    │
│ days                  │
└───────┬───────────────┘
        │ M:1
        ↓
┌───────────────────┐
│   leave_types     │
│───────────────────│
│ id (PK)           │
│ name              │
│ default_duration  │
│ requires_attachment│
└───────────────────┘

┌────────────────────┐
│  leave_balances    │
│────────────────────│
│ id (PK)            │
│ employee_id (FK)   │
│ leave_type_id (FK) │
│ total_days         │
│ used_days          │
│ remaining_days     │
│ year               │
└────────────────────┘
  UNIQUE(employee_id, leave_type_id, year)
```

### Personnel Actions ERD

```
┌──────────────────────┐
│    employments       │
└──────┬───────────────┘
       │ 1:M
       ↓
┌───────────────────────────────┐
│    personnel_actions          │
│───────────────────────────────│
│ id (PK)                       │
│ employment_id (FK)            │
│ reference_number (unique)     │
│ action_type                   │ ← 'appointment', 'fiscal_increment', etc.
│ action_subtype                │
│ effective_date                │
│                               │
│ -- CURRENT STATE --           │
│ current_department_id (FK)    │
│ current_position_id (FK)      │
│ current_salary                │
│ current_work_location_id(FK)  │
│                               │
│ -- NEW/PROPOSED STATE --      │
│ new_department_id (FK)        │
│ new_position_id (FK)          │
│ new_salary                    │
│ new_work_location_id (FK)     │
│ new_work_schedule             │
│ new_report_to                 │
│                               │
│ -- APPROVALS --               │
│ dept_head_approved            │
│ coo_approved                  │
│ hr_approved                   │
│ accountant_approved           │
└───────────────────────────────┘
```

---

## Table Details & Relationships

### 1. **users** (Authentication)

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(255),
    email VARCHAR(255) UNIQUE,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255),
    phone VARCHAR(255) NULL,
    status VARCHAR(255) DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    last_login_ip VARCHAR(255) NULL,
    profile_picture VARCHAR(255) NULL,
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

**Relationships:**
- `1:1 → employees` (user_id)

---

### 2. **employees** (Personal Information)

```sql
CREATE TABLE employees (
    id BIGINT UNSIGNED PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,  -- FK → users
    subsidiary VARCHAR(10),  -- 'SMRU' or 'BHF'
    staff_id VARCHAR(50),
    UNIQUE(staff_id, subsidiary),

    -- Personal Info
    initial_en VARCHAR(10) NULL,
    initial_th VARCHAR(20) NULL,
    first_name_en VARCHAR(255) NULL,
    last_name_en VARCHAR(255) NULL,
    first_name_th VARCHAR(255) NULL,
    last_name_th VARCHAR(255) NULL,
    gender VARCHAR(50),
    date_of_birth DATE,
    status VARCHAR(50),  -- 'Expats', 'Local ID', 'Local non ID'
    nationality VARCHAR(100) NULL,
    religion VARCHAR(100) NULL,

    -- Financial Info
    social_security_number VARCHAR(50) NULL,
    tax_number VARCHAR(50) NULL,
    bank_name VARCHAR(100) NULL,
    bank_branch VARCHAR(100) NULL,
    bank_account_name VARCHAR(100) NULL,
    bank_account_number VARCHAR(50) NULL,

    -- Contact Info
    mobile_phone VARCHAR(50) NULL,
    permanent_address TEXT NULL,
    current_address TEXT NULL,

    -- Family Info
    military_status VARCHAR(50) NULL,
    marital_status VARCHAR(50) NULL,
    spouse_name VARCHAR(200) NULL,
    spouse_phone_number VARCHAR(50) NULL,
    emergency_contact_person_name VARCHAR(100) NULL,
    emergency_contact_person_relationship VARCHAR(100) NULL,
    emergency_contact_person_phone VARCHAR(50) NULL,
    father_name VARCHAR(200) NULL,
    father_occupation VARCHAR(200) NULL,
    father_phone_number VARCHAR(50) NULL,
    mother_name VARCHAR(200) NULL,
    mother_occupation VARCHAR(200) NULL,
    mother_phone_number VARCHAR(50) NULL,

    -- Other
    driver_license_number VARCHAR(100) NULL,
    remark VARCHAR(255) NULL,
    created_by VARCHAR(100) NULL,
    updated_by VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_employees_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_employees_subsidiary (subsidiary),
    INDEX idx_employees_staff_id (staff_id),
    INDEX idx_employees_gender (gender),
    INDEX idx_employees_dob (date_of_birth),
    INDEX idx_employees_status (status)
);
```

**Relationships:**
- `M:1 ← users` (user_id)
- `1:M → employments`
- `1:M → employee_children`
- `1:M → employee_languages`
- `1:M → employee_education`
- `1:M → employee_identifications`
- `1:M → employee_beneficiaries`
- `1:M → leave_requests`
- `1:M → interviews`
- `1:M → resignations`

---

### 3. **departments** (Organizational Units)

```sql
CREATE TABLE departments (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(255) UNIQUE,
    description VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

-- Pre-seeded with 19 departments:
-- Administration, Finance, Grant, Human Resources, Logistics,
-- Procurement & Store, Data management, IT, Clinical,
-- Research/Study, Training, Research/Study M&E, MCH, M&E,
-- Laboratory, Malaria, Public Engagement, TB, Media Group
```

**Relationships:**
- `1:M → positions` (department_id)
- `1:M → employments` (department_id)
- `1:M → org_funded_allocations` (department_id)

---

### 4. **positions** (Job Titles with Hierarchical Reporting)

```sql
CREATE TABLE positions (
    id BIGINT UNSIGNED PRIMARY KEY,
    title VARCHAR(255),
    department_id BIGINT UNSIGNED,  -- FK → departments
    reports_to_position_id BIGINT UNSIGNED NULL,  -- FK → positions (HIERARCHY)
    level INT DEFAULT 1,  -- 1 = top level, 2 = reports to level 1, etc.
    is_manager BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_positions_department FOREIGN KEY (department_id)
        REFERENCES departments(id) ON DELETE CASCADE,
    CONSTRAINT fk_positions_reports_to FOREIGN KEY (reports_to_position_id)
        REFERENCES positions(id) ON DELETE NO ACTION,

    INDEX idx_positions_dept_active (department_id, is_active),
    INDEX idx_positions_reports_to (reports_to_position_id),
    INDEX idx_positions_dept_level (department_id, level)
);

-- Business Rule: Position can only report to position in same department
-- Enforced in Position model boot() method
```

**Relationships:**
- `M:1 ← departments` (department_id)
- `M:1 ← positions` (reports_to_position_id) **[SELF-REFERENCING HIERARCHY]**
- `1:M → positions` (reports_to_position_id) **[SUBORDINATES]**
- `1:M → employments` (position_id)
- `1:M → org_funded_allocations` (position_id)

---

### 5. **employments** (Employment Contracts - THE CENTRAL MODEL)

```sql
CREATE TABLE employments (
    id BIGINT UNSIGNED PRIMARY KEY,
    employee_id BIGINT UNSIGNED,  -- FK → employees
    employment_type VARCHAR(255),  -- 'Full-time', 'Part-time', 'Contract', 'Temporary'
    pay_method VARCHAR(255) NULL,  -- 'Transferred to bank', 'Cash cheque'
    pass_probation_date DATE NULL,  -- When employee gets full salary
    start_date DATE,
    end_date DATE NULL,

    -- Organizational Assignment
    department_id BIGINT UNSIGNED NULL,  -- FK → departments
    position_id BIGINT UNSIGNED NULL,  -- FK → positions
    section_department VARCHAR(255) NULL,  -- Sub-department/section
    work_location_id BIGINT UNSIGNED NULL,  -- FK → work_locations

    -- Salary
    pass_probation_salary DECIMAL(10,2),
    probation_salary DECIMAL(10,2) NULL,

    -- Benefits (boolean flags - percentages in benefit_settings table)
    health_welfare BOOLEAN DEFAULT FALSE,
    pvd BOOLEAN DEFAULT FALSE,
    saving_fund BOOLEAN DEFAULT FALSE,

    -- Status
    status BOOLEAN DEFAULT TRUE,  -- true=Active, false=Inactive

    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_employments_employee FOREIGN KEY (employee_id)
        REFERENCES employees(id) ON DELETE CASCADE,
    CONSTRAINT fk_employments_department FOREIGN KEY (department_id)
        REFERENCES departments(id) ON DELETE NO ACTION,
    CONSTRAINT fk_employments_position FOREIGN KEY (position_id)
        REFERENCES positions(id) ON DELETE NO ACTION,
    CONSTRAINT fk_employments_work_location FOREIGN KEY (work_location_id)
        REFERENCES work_locations(id) ON DELETE SET NULL,

    INDEX idx_transition_check (pass_probation_date, end_date, status)
);

-- NOTE: Probation status is NOW tracked in probation_records table
-- NOTE: Benefit percentages managed globally in benefit_settings table
```

**Relationships:**
- `M:1 ← employees` (employee_id)
- `M:1 ← departments` (department_id)
- `M:1 ← positions` (position_id)
- `M:1 ← work_locations` (work_location_id)
- `1:M → employment_histories`
- `1:M → probation_records`
- `1:M → employee_funding_allocations`
- `1:M → payrolls`
- `1:M → personnel_actions`

---

### 6. **probation_records** (Probation Tracking - Single Source of Truth)

```sql
CREATE TABLE probation_records (
    id BIGINT UNSIGNED PRIMARY KEY,
    employment_id BIGINT UNSIGNED,  -- FK → employments
    employee_id BIGINT UNSIGNED,  -- FK → employees

    -- Event Details
    event_type VARCHAR(20),  -- 'initial', 'extension', 'passed', 'failed'
    event_date DATE,
    decision_date DATE NULL,

    -- Probation Dates
    probation_start_date DATE,
    probation_end_date DATE,
    previous_end_date DATE NULL,

    -- Extension Tracking
    extension_number INT DEFAULT 0,  -- 0=initial, 1=first ext, 2=second ext

    -- Decision Details
    decision_reason VARCHAR(500) NULL,
    evaluation_notes TEXT NULL,
    approved_by VARCHAR(255) NULL,

    -- Current Status
    is_active BOOLEAN DEFAULT TRUE,  -- Identifies the current record

    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_probation_employment FOREIGN KEY (employment_id)
        REFERENCES employments(id) ON DELETE CASCADE,
    CONSTRAINT fk_probation_employee FOREIGN KEY (employee_id)
        REFERENCES employees(id) ON DELETE NO ACTION,

    INDEX idx_probation_employment (employment_id),
    INDEX idx_probation_employee (employee_id),
    INDEX idx_probation_event_type (event_type),
    INDEX idx_probation_is_active (is_active),
    INDEX idx_probation_end_date (probation_end_date)
);

-- ARCHITECTURAL DECISION:
-- Single source of truth: probation status = active_record.event_type
-- NO probation_status field in employments table
-- Mapping: 'initial'/'extension' → 'ongoing', 'passed' → 'passed', 'failed' → 'failed'
```

---

### 7. **grants** (Funding Sources)

```sql
CREATE TABLE grants (
    id BIGINT UNSIGNED PRIMARY KEY,
    code VARCHAR(255),
    name VARCHAR(255),
    subsidiary VARCHAR(255),
    description TEXT NULL,
    end_date DATE NULL,
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    INDEX idx_grants_subsidiary_code (subsidiary, code),
    INDEX idx_grants_subsidiary_end_date_id (subsidiary, end_date, id)
);

-- Default grants:
-- SMRU: S0031 - Other Fund
-- BHF: S22001 - General Fund
```

**Relationships:**
- `1:M → grant_items`
- `1:M → org_funded_allocations`

---

### 8. **grant_items** (Grant Positions / Budget Lines)

```sql
CREATE TABLE grant_items (
    id BIGINT UNSIGNED PRIMARY KEY,
    grant_id BIGINT UNSIGNED,  -- FK → grants
    grant_position VARCHAR(255) NULL,  -- Position title in grant
    grant_salary DECIMAL(15,2) NULL,
    grant_benefit DECIMAL(15,2) NULL,
    grant_level_of_effort DECIMAL(5,2) NULL,  -- % LOE
    grant_position_number INT NULL,  -- How many people
    budgetline_code VARCHAR(255) NULL,  -- Budget line code
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_grant_items_grant FOREIGN KEY (grant_id)
        REFERENCES grants(id) ON DELETE CASCADE,

    -- Prevent duplicate grant positions with same budget line
    UNIQUE unique_grant_position_budgetline (grant_id, grant_position, budgetline_code)
);
```

**Relationships:**
- `M:1 ← grants` (grant_id)
- `1:M → position_slots`

**NOTE**: `grant_position` in this table is your "grant position" concept!

---

### 9. **position_slots** (Individual Grant Slots)

```sql
CREATE TABLE position_slots (
    id BIGINT UNSIGNED PRIMARY KEY,
    grant_item_id BIGINT UNSIGNED,  -- FK → grant_items
    slot_number INT UNSIGNED,  -- 1, 2, 3... for multiple people
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_position_slots_grant_item FOREIGN KEY (grant_item_id)
        REFERENCES grant_items(id) ON DELETE CASCADE
);
```

**Relationships:**
- `M:1 ← grant_items` (grant_item_id)
- `1:M → employee_funding_allocations`

---

### 10. **org_funded_allocations** (Organization-Funded Positions)

```sql
CREATE TABLE org_funded_allocations (
    id BIGINT UNSIGNED PRIMARY KEY,
    grant_id BIGINT UNSIGNED,  -- FK → grants (org uses a "grant" too)
    department_id BIGINT UNSIGNED,  -- FK → departments
    position_id BIGINT UNSIGNED,  -- FK → positions
    description VARCHAR(255) NULL,
    created_by VARCHAR(100) NULL,
    updated_by VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_org_funded_grant FOREIGN KEY (grant_id)
        REFERENCES grants(id) ON DELETE CASCADE,
    CONSTRAINT fk_org_funded_department FOREIGN KEY (department_id)
        REFERENCES departments(id) ON DELETE NO ACTION,
    CONSTRAINT fk_org_funded_position FOREIGN KEY (position_id)
        REFERENCES positions(id) ON DELETE NO ACTION,

    INDEX idx_org_funded_grant_dept_pos (grant_id, department_id, position_id),
    INDEX idx_org_funded_dept_pos (department_id, position_id)
);
```

**Relationships:**
- `M:1 ← grants` (grant_id)
- `M:1 ← departments` (department_id)
- `M:1 ← positions` (position_id)
- `1:M → employee_funding_allocations`

---

### 11. **employee_funding_allocations** (⭐ MULTI-SOURCE FUNDING - THE KEY TABLE!)

```sql
CREATE TABLE employee_funding_allocations (
    id BIGINT UNSIGNED PRIMARY KEY,
    employee_id BIGINT UNSIGNED,  -- FK → employees
    employment_id BIGINT UNSIGNED NULL,  -- FK → employments
    org_funded_id BIGINT UNSIGNED NULL,  -- FK → org_funded_allocations
    position_slot_id BIGINT UNSIGNED NULL,  -- FK → position_slots

    fte DECIMAL(4,2),  -- Full-Time Equivalent % (0.00 to 100.00)
    allocation_type VARCHAR(20),  -- 'grant' or 'org_funded'
    allocated_amount DECIMAL(15,2) NULL,  -- Calculated: salary × fte%
    salary_type VARCHAR(50) NULL,  -- 'probation_salary' or 'pass_probation_salary'
    status VARCHAR(20) DEFAULT 'active',  -- 'active', 'historical', 'terminated'
    start_date DATE NULL,
    end_date DATE NULL,
    created_by VARCHAR(100) NULL,
    updated_by VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_efa_employee FOREIGN KEY (employee_id)
        REFERENCES employees(id),
    CONSTRAINT fk_efa_employment FOREIGN KEY (employment_id)
        REFERENCES employments(id),
    CONSTRAINT fk_efa_org_funded FOREIGN KEY (org_funded_id)
        REFERENCES org_funded_allocations(id),
    CONSTRAINT fk_efa_position_slot FOREIGN KEY (position_slot_id)
        REFERENCES position_slots(id),

    INDEX idx_efa_employee_employment (employee_id, employment_id),
    INDEX idx_efa_employment_status (employment_id, status),
    INDEX idx_efa_status_end_date (status, end_date)
);

-- BUSINESS LOGIC:
-- An employee can have MULTIPLE funding allocations
-- Example: 60% Grant A + 40% Org-funded = 100% FTE
-- allocated_amount = (employment.salary × fte) / 100
```

**Relationships:**
- `M:1 ← employees` (employee_id)
- `M:1 ← employments` (employment_id)
- `M:1 ← org_funded_allocations` (org_funded_id, nullable)
- `M:1 ← position_slots` (position_slot_id, nullable)
- `1:M → payrolls`
- `1:M → allocation_change_logs`

**CRITICAL CONCEPT:**
This table is the **aggregator** of all funding sources. It allows:
- Multi-source funding (employee funded by multiple grants/org)
- FTE tracking (% allocation per funding source)
- Automatic salary calculations
- Probation salary transitions

---

### 12. **payrolls** (Monthly Payroll Records)

```sql
CREATE TABLE payrolls (
    id BIGINT UNSIGNED PRIMARY KEY,
    employment_id BIGINT UNSIGNED,  -- FK → employments
    employee_funding_allocation_id BIGINT UNSIGNED,  -- FK → employee_funding_allocations

    -- ALL SALARY FIELDS ARE ENCRYPTED (TEXT)
    gross_salary TEXT,  -- DECIMAL(15,2) when decrypted
    gross_salary_by_FTE TEXT,  -- DECIMAL(15,2)
    compensation_refund TEXT,
    thirteen_month_salary TEXT,
    thirteen_month_salary_accured TEXT,
    pvd TEXT NULL,
    saving_fund TEXT NULL,
    employer_social_security TEXT,
    employee_social_security TEXT,
    employer_health_welfare TEXT,
    employee_health_welfare TEXT,
    tax TEXT,
    net_salary TEXT,
    total_salary TEXT,
    total_pvd TEXT,
    total_saving_fund TEXT,
    salary_bonus TEXT NULL,
    total_income TEXT,
    employer_contribution TEXT,
    total_deduction TEXT,

    notes TEXT NULL,  -- Plain text, for payslip display
    pay_period_date DATE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_payroll_employment FOREIGN KEY (employment_id)
        REFERENCES employments(id) CASCADE ON UPDATE NO ACTION ON DELETE,
    CONSTRAINT fk_payroll_allocation FOREIGN KEY (employee_funding_allocation_id)
        REFERENCES employee_funding_allocations(id) CASCADE ON UPDATE NO ACTION ON DELETE
);

-- SECURITY: All salary fields encrypted using Laravel's encrypt() helper
-- Casted in Payroll model for automatic encryption/decryption
```

**Relationships:**
- `M:1 ← employments` (employment_id)
- `M:1 ← employee_funding_allocations` (employee_funding_allocation_id)

---

### 13. **leave_types**, **leave_requests**, **leave_request_items**, **leave_balances**

#### leave_types
```sql
CREATE TABLE leave_types (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(100),
    default_duration DECIMAL(18,2) NULL,
    description TEXT NULL,
    requires_attachment BOOLEAN DEFAULT FALSE,
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

-- Pre-seeded with 11 leave types:
-- Annual Leave, Unpaid Leave, Traditional day-off, Sick,
-- Maternity leave, Compassionate, Career development training,
-- Personal leave, Military leave, Sterilization leave, Other
```

#### leave_requests
```sql
CREATE TABLE leave_requests (
    id BIGINT UNSIGNED PRIMARY KEY,
    employee_id BIGINT UNSIGNED,  -- FK → employees
    start_date DATE,
    end_date DATE,
    total_days DECIMAL(18,2),
    reason TEXT NULL,
    status VARCHAR(50) DEFAULT 'pending',

    -- Approvals (embedded in table)
    supervisor_approved BOOLEAN DEFAULT FALSE,
    supervisor_approved_date DATE NULL,
    hr_site_admin_approved BOOLEAN DEFAULT FALSE,
    hr_site_admin_approved_date DATE NULL,

    attachment_notes TEXT NULL,
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_leave_requests_employee FOREIGN KEY (employee_id)
        REFERENCES employees(id),

    INDEX idx_leave_requests_emp_status (employee_id, status),
    INDEX idx_leave_requests_dates (start_date, end_date)
);

-- NOTE: leave_type_id removed - now handled by leave_request_items
```

#### leave_request_items (Multi-Type Leave Support)
```sql
CREATE TABLE leave_request_items (
    id BIGINT UNSIGNED PRIMARY KEY,
    leave_request_id BIGINT UNSIGNED,  -- FK → leave_requests
    leave_type_id BIGINT UNSIGNED,  -- FK → leave_types
    days DECIMAL(8,2),
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_lri_leave_request FOREIGN KEY (leave_request_id)
        REFERENCES leave_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_lri_leave_type FOREIGN KEY (leave_type_id)
        REFERENCES leave_types(id) ON DELETE NO ACTION,

    -- Prevent duplicate leave types in one request
    UNIQUE unique_request_leave_type (leave_request_id, leave_type_id),
    INDEX idx_lri_request_type (leave_request_id, leave_type_id)
);

-- FEATURE: Allows one leave request to span multiple leave types
-- Example: 2 days Annual Leave + 1 day Personal Leave = 3 days total
```

#### leave_balances
```sql
CREATE TABLE leave_balances (
    id BIGINT UNSIGNED PRIMARY KEY,
    employee_id BIGINT UNSIGNED,  -- FK → employees
    leave_type_id BIGINT UNSIGNED,  -- FK → leave_types
    total_days DECIMAL(18,2) DEFAULT 0,
    used_days DECIMAL(18,2) DEFAULT 0,
    remaining_days DECIMAL(18,2) DEFAULT 0,
    year YEAR DEFAULT CURRENT_YEAR,
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    CONSTRAINT fk_leave_balances_employee FOREIGN KEY (employee_id)
        REFERENCES employees(id),
    CONSTRAINT fk_leave_balances_leave_type FOREIGN KEY (leave_type_id)
        REFERENCES leave_types(id),

    UNIQUE unique_employee_leave_type_year (employee_id, leave_type_id, year),
    INDEX idx_leave_balances_emp_year (employee_id, year)
);
```

---

### 14. **personnel_actions** (Promotions, Transfers, Salary Adjustments)

```sql
CREATE TABLE personnel_actions (
    id BIGINT UNSIGNED PRIMARY KEY,
    form_number VARCHAR(255) DEFAULT 'SMRU-SF038',
    reference_number VARCHAR(255) UNIQUE,
    employment_id BIGINT UNSIGNED,  -- FK → employments

    -- Section 1: Current State (captured at creation for audit)
    current_employee_no VARCHAR(255) NULL,
    current_department_id BIGINT UNSIGNED NULL,  -- FK → departments
    current_position_id BIGINT UNSIGNED NULL,  -- FK → positions
    current_salary DECIMAL(12,2) NULL,
    current_work_location_id BIGINT UNSIGNED NULL,  -- FK → work_locations
    current_employment_date DATE NULL,
    effective_date DATE,

    -- Section 2: Action Type
    action_type VARCHAR(255),  -- 'appointment', 'fiscal_increment', etc.
    action_subtype VARCHAR(255) NULL,
    is_transfer BOOLEAN DEFAULT FALSE,
    transfer_type VARCHAR(255) NULL,

    -- Section 3: New/Proposed State
    new_department_id BIGINT UNSIGNED NULL,  -- FK → departments
    new_position_id BIGINT UNSIGNED NULL,  -- FK → positions
    new_work_location_id BIGINT UNSIGNED NULL,  -- FK → work_locations
    new_salary DECIMAL(12,2) NULL,
    new_work_schedule VARCHAR(255) NULL,
    new_report_to VARCHAR(255) NULL,
    new_pay_plan VARCHAR(255) NULL,
    new_phone_ext VARCHAR(255) NULL,
    new_email VARCHAR(255) NULL,

    -- Section 4: Comments
    comments TEXT NULL,
    change_details TEXT NULL,

    -- Section 5: Approvals (4 simple booleans)
    dept_head_approved BOOLEAN DEFAULT FALSE,
    coo_approved BOOLEAN DEFAULT FALSE,
    hr_approved BOOLEAN DEFAULT FALSE,
    accountant_approved BOOLEAN DEFAULT FALSE,

    created_by BIGINT UNSIGNED,  -- FK → users
    updated_by BIGINT UNSIGNED NULL,  -- FK → users
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,  -- Soft deletes

    -- Foreign Keys (NO ACTION for SQL Server compatibility)
    CONSTRAINT fk_pa_employment FOREIGN KEY (employment_id)
        REFERENCES employments(id),
    CONSTRAINT fk_pa_current_dept FOREIGN KEY (current_department_id)
        REFERENCES departments(id),
    CONSTRAINT fk_pa_current_pos FOREIGN KEY (current_position_id)
        REFERENCES positions(id),
    CONSTRAINT fk_pa_current_work_loc FOREIGN KEY (current_work_location_id)
        REFERENCES work_locations(id),
    CONSTRAINT fk_pa_new_dept FOREIGN KEY (new_department_id)
        REFERENCES departments(id),
    CONSTRAINT fk_pa_new_pos FOREIGN KEY (new_position_id)
        REFERENCES positions(id),
    CONSTRAINT fk_pa_new_work_loc FOREIGN KEY (new_work_location_id)
        REFERENCES work_locations(id),
    CONSTRAINT fk_pa_created_by FOREIGN KEY (created_by)
        REFERENCES users(id),
    CONSTRAINT fk_pa_updated_by FOREIGN KEY (updated_by)
        REFERENCES users(id)
);
```

---

### 15. **benefit_settings** (Global Benefit Percentages)

```sql
CREATE TABLE benefit_settings (
    id BIGINT UNSIGNED PRIMARY KEY,
    setting_key VARCHAR(255) UNIQUE,
    setting_value DECIMAL(10,2),
    setting_type VARCHAR(50),  -- 'percentage', 'numeric'
    description TEXT NULL,
    effective_date DATE NULL,
    is_active BOOLEAN DEFAULT TRUE,
    applies_to VARCHAR(255) NULL,  -- 'all', 'SMRU', 'BHF'
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    INDEX idx_benefit_settings_key_active (setting_key, is_active)
);

-- Pre-configured settings:
-- health_welfare_percentage: 5.00%
-- pvd_percentage: 7.50%
-- saving_fund_percentage: 7.50%
-- social_security_percentage: 5.00%
-- social_security_max_amount: 750.00 THB
```

---

## Foreign Key Relationships Map

### Complete Foreign Key Matrix

| Child Table | FK Column | Parent Table | Parent Column | On Delete | On Update |
|-------------|-----------|--------------|---------------|-----------|-----------|
| **employees** | user_id | users | id | SET NULL | - |
| **positions** | department_id | departments | id | CASCADE | - |
| **positions** | reports_to_position_id | positions | id | NO ACTION | - |
| **employments** | employee_id | employees | id | CASCADE | - |
| **employments** | department_id | departments | id | NO ACTION | - |
| **employments** | position_id | positions | id | NO ACTION | - |
| **employments** | work_location_id | work_locations | id | SET NULL | - |
| **employment_histories** | employment_id | employments | id | - | - |
| **employment_histories** | employee_id | employees | id | - | - |
| **employment_histories** | department_id | departments | id | NO ACTION | - |
| **employment_histories** | position_id | positions | id | NO ACTION | - |
| **employment_histories** | work_location_id | work_locations | id | - | - |
| **probation_records** | employment_id | employments | id | CASCADE | - |
| **probation_records** | employee_id | employees | id | NO ACTION | - |
| **grant_items** | grant_id | grants | id | CASCADE | - |
| **position_slots** | grant_item_id | grant_items | id | CASCADE | - |
| **org_funded_allocations** | grant_id | grants | id | CASCADE | - |
| **org_funded_allocations** | department_id | departments | id | NO ACTION | - |
| **org_funded_allocations** | position_id | positions | id | NO ACTION | - |
| **employee_funding_allocations** | employee_id | employees | id | - | - |
| **employee_funding_allocations** | employment_id | employments | id | - | - |
| **employee_funding_allocations** | org_funded_id | org_funded_allocations | id | - | - |
| **employee_funding_allocations** | position_slot_id | position_slots | id | - | - |
| **payrolls** | employment_id | employments | id | NO ACTION | CASCADE |
| **payrolls** | employee_funding_allocation_id | employee_funding_allocations | id | NO ACTION | CASCADE |
| **leave_requests** | employee_id | employees | id | - | - |
| **leave_request_items** | leave_request_id | leave_requests | id | CASCADE | - |
| **leave_request_items** | leave_type_id | leave_types | id | NO ACTION | - |
| **leave_balances** | employee_id | employees | id | - | - |
| **leave_balances** | leave_type_id | leave_types | id | - | - |
| **resignations** | employee_id | employees | id | CASCADE | - |
| **resignations** | department_id | departments | id | NO ACTION | - |
| **resignations** | position_id | positions | id | NO ACTION | - |
| **resignations** | acknowledged_by | users | id | SET NULL | - |
| **personnel_actions** | employment_id | employments | id | - | - |
| **personnel_actions** | current_department_id | departments | id | - | - |
| **personnel_actions** | current_position_id | positions | id | - | - |
| **personnel_actions** | current_work_location_id | work_locations | id | - | - |
| **personnel_actions** | new_department_id | departments | id | - | - |
| **personnel_actions** | new_position_id | positions | id | - | - |
| **personnel_actions** | new_work_location_id | work_locations | id | - | - |
| **personnel_actions** | created_by | users | id | - | - |
| **personnel_actions** | updated_by | users | id | - | - |

---

## Model Relationships (Eloquent)

### Employee Model

```php
class Employee extends Model
{
    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employments(): HasMany
    {
        return $this->hasMany(Employment::class);
    }

    public function currentEmployment(): HasOne
    {
        return $this->hasOne(Employment::class)
            ->where('status', true)
            ->latest();
    }

    public function children(): HasMany
    {
        return $this->hasMany(EmployeeChild::class);
    }

    public function languages(): HasMany
    {
        return $this->hasMany(EmployeeLanguage::class);
    }

    public function education(): HasMany
    {
        return $this->hasMany(EmployeeEducation::class);
    }

    public function identifications(): HasMany
    {
        return $this->hasMany(EmployeeIdentification::class);
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(EmployeeBeneficiary::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }
}
```

### Employment Model (CENTRAL MODEL)

```php
class Employment extends Model
{
    // Relationships

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    public function employeeFundingAllocations(): HasMany
    {
        return $this->hasMany(EmployeeFundingAllocation::class, 'employment_id');
    }

    public function activeAllocations(): HasMany
    {
        return $this->hasMany(EmployeeFundingAllocation::class)
            ->where('status', 'active');
    }

    public function probationHistory(): HasMany
    {
        return $this->hasMany(ProbationRecord::class)->orderBy('event_date');
    }

    public function activeProbationRecord(): HasOne
    {
        return $this->hasOne(ProbationRecord::class)
            ->where('is_active', true);
    }

    public function history(): HasMany
    {
        return $this->hasMany(EmploymentHistory::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function personnelActions(): HasMany
    {
        return $this->hasMany(PersonnelAction::class);
    }
}
```

### Position Model (Hierarchical)

```php
class Position extends Model
{
    // Relationships

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'reports_to_position_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'reports_to_position_id')
            ->where('is_manager', true);
    }

    public function directReports(): HasMany
    {
        return $this->hasMany(Position::class, 'reports_to_position_id')
            ->where('is_active', true);
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Position::class, 'reports_to_position_id');
    }

    // Helper Methods

    public function isDepartmentHead(): bool
    {
        return $this->level === 1 && $this->is_manager;
    }

    public function getManagerNameAttribute()
    {
        if ($this->manager) {
            return $this->manager->title;
        }

        $departmentManager = $this->getDepartmentManager();
        return $departmentManager?->title ?? 'No Manager Assigned';
    }
}
```

### GrantItem Model

```php
class GrantItem extends Model
{
    public function grant(): BelongsTo
    {
        return $this->belongsTo(Grant::class);
    }

    public function positionSlots(): HasMany
    {
        return $this->hasMany(PositionSlot::class);
    }
}
```

### EmployeeFundingAllocation Model (THE AGGREGATOR)

```php
class EmployeeFundingAllocation extends Model
{
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function orgFundedAllocation(): BelongsTo
    {
        return $this->belongsTo(OrgFundedAllocation::class, 'org_funded_id');
    }

    public function positionSlot(): BelongsTo
    {
        return $this->belongsTo(PositionSlot::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(AllocationChangeLog::class);
    }
}
```

---

## Key Architectural Patterns

### 1. **Multi-Source Funding Allocation Pattern**

```
┌─────────────────────────────────────────────────────┐
│         MULTI-SOURCE FUNDING ARCHITECTURE           │
├─────────────────────────────────────────────────────┤
│                                                     │
│  Employee can be funded from MULTIPLE sources:     │
│                                                     │
│  ┌──────────────────────────────────────────┐      │
│  │ Employee: John Doe                       │      │
│  │ Salary: $100,000/year                    │      │
│  ├──────────────────────────────────────────┤      │
│  │ Funding Sources:                         │      │
│  │  1. Grant A (60% FTE) → $60,000          │      │
│  │  2. Grant B (20% FTE) → $20,000          │      │
│  │  3. Org-Funded (20% FTE) → $20,000       │      │
│  │                          ────────         │      │
│  │  Total: 100% FTE = $100,000              │      │
│  └──────────────────────────────────────────┘      │
│                                                     │
│  Implementation:                                    │
│    - 3 records in employee_funding_allocations     │
│    - Each tracks FTE %, allocated_amount           │
│    - Payroll generated per allocation              │
│                                                     │
└─────────────────────────────────────────────────────┘
```

### 2. **Probation Tracking Pattern (Event-Based)**

```
┌─────────────────────────────────────────────────────┐
│         PROBATION EVENT-BASED TRACKING             │
├─────────────────────────────────────────────────────┤
│                                                     │
│  Timeline:                                          │
│                                                     │
│  Day 1 (Jan 1)    Day 90 (Mar 31)   Day 120 (Apr 30│
│      │                 │                 │          │
│      ↓                 ↓                 ↓          │
│  [Initial]       [Extension]        [Passed]       │
│                                                     │
│  probation_records table:                          │
│  ┌─────────────────────────────────────────┐       │
│  │ ID │ event_type │ start │ end  │active  │       │
│  ├────┼────────────┼───────┼──────┼────────┤       │
│  │ 1  │ initial    │ 1/1   │ 3/31 │ false  │       │
│  │ 2  │ extension  │ 1/1   │ 4/30 │ false  │       │
│  │ 3  │ passed     │ 1/1   │ 4/30 │ TRUE   │ ← Current
│  └────┴────────────┴───────┴──────┴────────┘       │
│                                                     │
│  Single Source of Truth:                           │
│    current_status = active_record.event_type       │
│    NO probation_status in employments table!       │
│                                                     │
└─────────────────────────────────────────────────────┘
```

### 3. **Position Hierarchy Pattern (Self-Referencing)**

```
┌─────────────────────────────────────────────────────┐
│         HIERARCHICAL REPORTING STRUCTURE            │
├─────────────────────────────────────────────────────┤
│                                                     │
│  HR Department:                                     │
│                                                     │
│     Level 1: HR Manager (id=1, reports_to=null)    │
│         ├─ Level 2: Sr. HR Assistant (id=2, reports_to=1)
│         ├─ Level 3: HR Assistant (id=3, reports_to=1)
│         └─ Level 4: Jr. HR Assistant (id=4, reports_to=1)
│                                                     │
│  Enforced Rules:                                    │
│    1. Position can only report to position in same dept
│    2. Circular references prevented                │
│    3. Hierarchy validated in model boot()          │
│                                                     │
└─────────────────────────────────────────────────────┘
```

### 4. **Multi-Type Leave Request Pattern**

```
┌─────────────────────────────────────────────────────┐
│         MULTI-TYPE LEAVE REQUEST PATTERN            │
├─────────────────────────────────────────────────────┤
│                                                     │
│  Leave Request #123:                                │
│    Employee: Jane Smith                             │
│    Period: June 1-5 (5 days total)                  │
│                                                     │
│  leave_request_items:                               │
│  ┌─────────────────────────────────────────┐       │
│  │ ID │ leave_request_id │ leave_type_id │ days │
│  ├────┼──────────────────┼───────────────┼──────┤
│  │ 1  │       123        │  1 (Annual)   │ 3.00 │
│  │ 2  │       123        │  8 (Personal) │ 2.00 │
│  └────┴──────────────────┴───────────────┴──────┘
│                                                     │
│  Benefit: One request can span multiple leave types
│  Example: 3 days Annual + 2 days Personal = 5 days │
│                                                     │
└─────────────────────────────────────────────────────┘
```

### 5. **Personnel Action Pattern (Before/After State)**

```
┌─────────────────────────────────────────────────────┐
│         PERSONNEL ACTION STATE TRACKING             │
├─────────────────────────────────────────────────────┤
│                                                     │
│  Action: Promotion                                  │
│                                                     │
│  BEFORE STATE (Captured at creation):               │
│    - current_department_id: 4 (HR)                  │
│    - current_position_id: 18 (HR Assistant)         │
│    - current_salary: $40,000                        │
│                                                     │
│  AFTER STATE (Proposed/New):                        │
│    - new_department_id: 4 (HR)                      │
│    - new_position_id: 17 (Sr. HR Assistant)         │
│    - new_salary: $50,000                            │
│                                                     │
│  Approvals:                                         │
│    ☑ dept_head_approved                            │
│    ☑ coo_approved                                  │
│    ☑ hr_approved                                   │
│    ☑ accountant_approved                           │
│                                                     │
│  Audit Trail: Full before/after captured            │
│                                                     │
└─────────────────────────────────────────────────────┘
```

---

## Data Flow Diagrams

### 1. Employee Hiring Flow

```
┌─────────┐
│Interview│
└────┬────┘
     │
     ↓
┌─────────┐
│Job Offer│
└────┬────┘
     │
     ↓
┌─────────────┐
│   Employee  │ ← Created
│  (Personal) │
└─────┬───────┘
      │
      ↓
┌──────────────┐
│  Employment  │ ← Employment Contract
│   (Contract) │
└──────┬───────┘
       │
       ├──→ ┌────────────────┐
       │    │ Probation      │ ← Initial probation record
       │    │ Record         │
       │    └────────────────┘
       │
       └──→ ┌────────────────────────┐
            │ Employee Funding       │ ← Allocation(s)
            │ Allocation(s)          │
            │  - Grant/Org-funded    │
            │  - FTE %               │
            └────────────────────────┘
```

### 2. Payroll Generation Flow

```
Employment
    ↓
Employee Funding Allocations (multiple)
    ├─→ Allocation 1: Grant A (60% FTE)
    │       ↓
    │   Calculate: salary × 60% = allocated_amount
    │       ↓
    │   Generate Payroll Record #1
    │
    ├─→ Allocation 2: Grant B (20% FTE)
    │       ↓
    │   Calculate: salary × 20% = allocated_amount
    │       ↓
    │   Generate Payroll Record #2
    │
    └─→ Allocation 3: Org-Funded (20% FTE)
            ↓
        Calculate: salary × 20% = allocated_amount
            ↓
        Generate Payroll Record #3
```

### 3. Probation Completion Flow

```
┌──────────────────────┐
│ Daily Cron Job       │
│ ProcessProbation     │
│ Completions          │
└──────────┬───────────┘
           │
           ↓
    Check: pass_probation_date = today?
           │
           ├─ YES → ┌────────────────────────┐
           │        │ Create probation record│
           │        │ event_type: 'passed'   │
           │        │ is_active: true        │
           │        └───────┬────────────────┘
           │                │
           │                ↓
           │        ┌────────────────────────┐
           │        │ Update funding         │
           │        │ allocations            │
           │        │ - Use pass_probation_  │
           │        │   salary               │
           │        │ - Recalculate amounts  │
           │        └────────────────────────┘
           │
           └─ NO → Continue
```

---

## Indexes & Performance Optimization

### Indexed Columns

| Table | Index Type | Columns | Purpose |
|-------|------------|---------|---------|
| **employees** | Composite | (staff_id, subsidiary) | Unique constraint |
| | Single | subsidiary | Filter by subsidiary |
| | Single | staff_id | Employee lookup |
| | Single | gender | Statistics |
| | Single | date_of_birth | Age calculations |
| **positions** | Composite | (department_id, is_active) | Active positions by dept |
| | Single | reports_to_position_id | Hierarchy queries |
| | Composite | (department_id, level) | Level-based queries |
| **employments** | Composite | (pass_probation_date, end_date, status) | Probation transitions |
| **probation_records** | Single | employment_id | Quick lookup |
| | Single | employee_id | Employee history |
| | Single | event_type | Filter by event |
| | Single | is_active | Current record |
| | Single | probation_end_date | Due date queries |
| **employee_funding_allocations** | Composite | (employee_id, employment_id) | Allocation lookup |
| | Composite | (employment_id, status) | Active allocations |
| | Composite | (status, end_date) | Cleanup queries |
| **leave_requests** | Composite | (employee_id, status) | Employee leaves |
| | Composite | (start_date, end_date) | Date range queries |
| **leave_balances** | Composite | (employee_id, leave_type_id, year) | Unique + lookup |
| **grants** | Composite | (subsidiary, code) | Grant lookup |
| | Composite | (subsidiary, end_date, id) | Active grants |

---

## Constraints & Business Rules

### Unique Constraints

| Table | Columns | Purpose |
|-------|---------|---------|
| **employees** | (staff_id, subsidiary) | Unique employee ID per subsidiary |
| **departments** | name | Unique department names |
| **grant_items** | (grant_id, grant_position, budgetline_code) | Prevent duplicate grant positions |
| **personnel_actions** | reference_number | Unique reference numbers |
| **leave_balances** | (employee_id, leave_type_id, year) | One balance per type per year |
| **leave_request_items** | (leave_request_id, leave_type_id) | Prevent duplicate types in request |

### Check Constraints (Model-Level)

1. **Position Hierarchy**:
   - Position can only report to position in same department
   - Enforced in `Position::boot()` creating/updating events

2. **FTE Validation**:
   - Total FTE % per employment should not exceed 100%
   - Enforced in application logic

3. **Probation Events**:
   - Only one active probation record per employment
   - Event sequence validation (extension → passed/failed)

4. **Leave Request Dates**:
   - end_date >= start_date
   - total_days = sum(leave_request_items.days)

---

## Summary & Key Takeaways

### ✅ System Strengths

1. **Well-Normalized Schema**: Proper separation of concerns (employees, employments, funding)
2. **Flexible Funding Model**: Multi-source allocation supports complex funding scenarios
3. **Complete Audit Trail**: Employment histories, probation records, allocation logs
4. **Hierarchical Organization**: Self-referencing positions enable org chart
5. **Event-Based Probation**: Single source of truth with full history
6. **Multi-Type Leave**: Flexible leave request system
7. **Encrypted Payroll**: Security for sensitive salary data
8. **MSSQL Compatible**: VARCHAR instead of ENUM, NO ACTION on deletes

### 🎯 Architectural Patterns Used

1. **Single Source of Truth**: Probation status from active record
2. **Aggregator Pattern**: employee_funding_allocations aggregates multiple funding sources
3. **Event Sourcing**: Probation events tracked chronologically
4. **Self-Referencing Hierarchy**: positions.reports_to_position_id
5. **Polymorphic-like Pattern**: employee_funding_allocations → position_slots OR org_funded_allocations
6. **State Capture**: Personnel actions capture before/after state

### 📊 Total Statistics

- **Tables**: 50+
- **Foreign Keys**: 50+
- **Indexes**: 30+ composite/single indexes
- **Unique Constraints**: 6+
- **Core Entities**: 10 (users, employees, employments, departments, positions, grants, payrolls, leaves, probation, personnel_actions)

---

## Recommended Areas for AI Analysis

1. **Funding Allocation Optimization**:
   - Is the multi-source funding pattern optimal?
   - Should there be constraints on total FTE %?
   - How to handle funding allocation history better?

2. **Probation System**:
   - Is event-based tracking the best approach?
   - Should probation_status be denormalized for performance?
   - How to handle complex probation rules (extensions, failures)?

3. **Position Hierarchy**:
   - Is self-referencing the best approach?
   - Should there be a `reports_to` table for flexibility?
   - How to handle matrix organizations?

4. **Leave Management**:
   - Is the multi-type leave pattern necessary?
   - Should leave balances be calculated or stored?
   - How to handle carry-forward, prorated balances?

5. **Personnel Actions**:
   - Should current/new state be in separate tables?
   - Is the approval pattern (4 booleans) scalable?
   - How to handle complex approval workflows?

6. **Performance**:
   - Are indexes sufficient for scale?
   - Should there be caching strategies?
   - Are there N+1 query concerns?

7. **Data Integrity**:
   - Should there be more check constraints?
   - Are soft deletes needed on more tables?
   - How to handle cascading deletes better?

---

**END OF DATABASE SCHEMA & RELATIONSHIPS ANALYSIS**

**For AI Review**: Please analyze this schema and provide recommendations for:
- Schema improvements
- Performance optimization
- Data integrity enhancements
- Alternative architectural patterns
- Scalability concerns
- Missing relationships or tables
