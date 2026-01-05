# HRMS Database Entity Relationship Diagram

## Overview
This document contains the complete database schema and Entity Relationship Diagram for the HRMS (Human Resource Management System) Laravel application.

## Database Statistics
- **Total Tables**: 29 tables
- **Core Business Tables**: 24 tables
- **Permission/Auth Tables**: 5 tables
- **Total Relationships**: 60+ relationships

## Mermaid ERD Diagram

```mermaid
erDiagram
    %% Core User & Authentication
    users ||--o{ employees : "has profile"
    users ||--o{ personnel_actions : "creates"
    users ||--o{ resignations : "acknowledges"
    users ||--o{ bulk_payroll_batches : "creates"

    %% Employee Core
    employees ||--o{ employments : "has"
    employees ||--o{ leave_requests : "submits"
    employees ||--o{ leave_balances : "has"
    employees ||--o{ employee_funding_allocations : "has"
    employees ||--o{ resignations : "files"
    employees }o--|| subsidiaries : "belongs to"

    %% Employment & History
    employments ||--o{ employment_histories : "tracks changes"
    employments ||--o{ employee_funding_allocations : "funds"
    employments ||--o{ payrolls : "generates"
    employments ||--o{ personnel_actions : "modifies"
    employments }o--|| departments : "assigned to"
    employments }o--|| positions : "holds"
    employments }o--|| work_locations : "works at"

    %% Organizational Structure
    departments ||--o{ positions : "contains"
    departments ||--o{ org_funded_allocations : "allocates"
    departments ||--o{ resignations : "from"
    positions ||--o{ positions : "reports to (self)"
    positions ||--o{ org_funded_allocations : "allocates"
    positions ||--o{ resignations : "from"

    %% Grant Management
    grants ||--o{ grant_items : "contains"
    grants ||--o{ org_funded_allocations : "funds"
    grants }o--|| subsidiaries : "belongs to"
    grant_items ||--o{ position_slots : "has"

    %% Funding Allocations
    org_funded_allocations ||--o{ employee_funding_allocations : "allocates to"
    position_slots ||--o{ employee_funding_allocations : "fills"
    employee_funding_allocations ||--o{ payrolls : "pays"

    %% Payroll System
    payrolls }o--|| tax_brackets : "uses"
    bulk_payroll_batches ||--o{ payrolls : "processes (indirect)"

    %% Leave Management
    leave_types ||--o{ leave_balances : "tracks"
    leave_types ||--o{ leave_request_items : "categorizes"
    leave_requests ||--o{ leave_request_items : "contains"

    %% Recruitment
    interviews ||--o{ job_offers : "results in (indirect)"

    %% Permissions (Spatie)
    users ||--o{ model_has_roles : "has roles"
    users ||--o{ model_has_permissions : "has permissions"
    roles ||--o{ model_has_roles : "assigned to"
    roles ||--o{ role_has_permissions : "has"
    permissions ||--o{ model_has_permissions : "assigned to"
    permissions ||--o{ role_has_permissions : "includes"

    %% Table Definitions
    users {
        bigint id PK
        string name
        string email UK
        string password
        string phone
        string status
        timestamp last_login_at
        string last_login_ip
        string profile_picture
        timestamp email_verified_at
        string created_by
        string updated_by
        timestamps
    }

    employees {
        bigint id PK
        bigint user_id FK
        string subsidiary FK
        string staff_id UK
        string initial_en
        string initial_th
        string first_name_en
        string last_name_en
        string first_name_th
        string last_name_th
        string gender
        date date_of_birth
        string status
        string nationality
        string religion
        string social_security_number
        string tax_number
        string bank_name
        string bank_branch
        string bank_account_name
        string bank_account_number
        string mobile_phone
        text permanent_address
        text current_address
        string military_status
        string marital_status
        string spouse_name
        string emergency_contact_person_name
        string created_by
        string updated_by
        timestamps
    }

    employments {
        bigint id PK
        bigint employee_id FK
        string employment_type
        string pay_method
        date pass_probation_date
        date start_date
        date end_date
        bigint department_id FK
        bigint position_id FK
        bigint work_location_id FK
        decimal pass_probation_salary
        decimal probation_salary
        boolean health_welfare
        decimal health_welfare_percentage
        boolean pvd
        decimal pvd_percentage
        boolean saving_fund
        decimal saving_fund_percentage
        boolean status
        string probation_status
        string created_by
        string updated_by
        timestamps
    }

    employment_histories {
        bigint id PK
        bigint employment_id FK
        bigint employee_id FK
        string employment_type
        date start_date
        date pass_probation_date
        string pay_method
        bigint department_id FK
        bigint position_id FK
        bigint work_location_id FK
        decimal pass_probation_salary
        decimal probation_salary
        boolean active
        boolean health_welfare
        boolean pvd
        boolean saving_fund
        date change_date
        string change_reason
        string changed_by_user
        json changes_made
        json previous_values
        text notes
        string created_by
        string updated_by
        timestamps
    }

    departments {
        bigint id PK
        string name UK
        string description
        boolean is_active
        string created_by
        string updated_by
        timestamps
    }

    positions {
        bigint id PK
        string title
        bigint department_id FK
        bigint reports_to_position_id FK
        int level
        boolean is_manager
        boolean is_active
        string created_by
        string updated_by
        timestamps
    }

    work_locations {
        bigint id PK
        string name
        string type
        string created_by
        string updated_by
        timestamps
    }

    subsidiaries {
        bigint id PK
        string code UK
        string created_by
        string updated_by
        timestamps
    }

    grants {
        bigint id PK
        string code
        string name
        string subsidiary FK
        text description
        date end_date
        string created_by
        string updated_by
        timestamps
    }

    grant_items {
        bigint id PK
        bigint grant_id FK
        string grant_position
        decimal grant_salary
        decimal grant_benefit
        decimal grant_level_of_effort
        int grant_position_number
        string budgetline_code
        string created_by
        string updated_by
        timestamps
    }

    position_slots {
        bigint id PK
        bigint grant_item_id FK
        int slot_number
        string created_by
        string updated_by
        timestamps
    }

    org_funded_allocations {
        bigint id PK
        bigint grant_id FK
        bigint department_id FK
        bigint position_id FK
        string description
        string created_by
        string updated_by
        timestamps
    }

    employee_funding_allocations {
        bigint id PK
        bigint employee_id FK
        bigint employment_id FK
        bigint org_funded_id FK
        bigint position_slot_id FK
        decimal fte
        string allocation_type
        decimal allocated_amount
        string salary_type
        string status
        date start_date
        date end_date
        string created_by
        string updated_by
        timestamps
    }

    payrolls {
        bigint id PK
        bigint employment_id FK
        bigint employee_funding_allocation_id FK
        text gross_salary "encrypted"
        text gross_salary_by_FTE "encrypted"
        text compensation_refund "encrypted"
        text thirteen_month_salary "encrypted"
        text thirteen_month_salary_accured "encrypted"
        text pvd "encrypted"
        text saving_fund "encrypted"
        text employer_social_security "encrypted"
        text employee_social_security "encrypted"
        text employer_health_welfare "encrypted"
        text employee_health_welfare "encrypted"
        text tax "encrypted"
        text net_salary "encrypted"
        text total_salary "encrypted"
        text total_pvd "encrypted"
        text total_saving_fund "encrypted"
        text salary_bonus "encrypted"
        text total_income "encrypted"
        text employer_contribution "encrypted"
        text total_deduction "encrypted"
        text notes
        date pay_period_date
        timestamps
    }

    tax_brackets {
        bigint id PK
        decimal min_income
        decimal max_income
        decimal tax_rate
        decimal base_tax
        int bracket_order
        int effective_year
        boolean is_active
        string description
        string created_by
        string updated_by
        timestamps
    }

    bulk_payroll_batches {
        bigint id PK
        string pay_period
        json filters
        int total_employees
        int total_payrolls
        int processed_payrolls
        int successful_payrolls
        int failed_payrolls
        int advances_created
        enum status
        json errors
        json summary
        string current_employee
        string current_allocation
        bigint created_by FK
        timestamps
    }

    leave_types {
        bigint id PK
        string name
        decimal default_duration
        text description
        boolean requires_attachment
        string created_by
        string updated_by
        timestamps
    }

    leave_requests {
        bigint id PK
        bigint employee_id FK
        date start_date
        date end_date
        decimal total_days
        text reason
        string status
        boolean supervisor_approved
        date supervisor_approved_date
        boolean hr_site_admin_approved
        date hr_site_admin_approved_date
        text attachment_notes
        string created_by
        string updated_by
        timestamps
    }

    leave_request_items {
        bigint id PK
        bigint leave_request_id FK
        bigint leave_type_id FK
        decimal days
        timestamps
    }

    leave_balances {
        bigint id PK
        bigint employee_id FK
        bigint leave_type_id FK
        decimal total_days
        decimal used_days
        decimal remaining_days
        year year
        string created_by
        string updated_by
        timestamps
    }

    personnel_actions {
        bigint id PK
        string form_number
        string reference_number UK
        bigint employment_id FK
        string current_employee_no
        bigint current_department_id FK
        bigint current_position_id FK
        decimal current_salary
        bigint current_work_location_id FK
        date current_employment_date
        date effective_date
        string action_type
        string action_subtype
        boolean is_transfer
        string transfer_type
        bigint new_department_id FK
        bigint new_position_id FK
        bigint new_work_location_id FK
        decimal new_salary
        string new_work_schedule
        string new_report_to
        string new_pay_plan
        string new_phone_ext
        string new_email
        text comments
        text change_details
        boolean dept_head_approved
        boolean coo_approved
        boolean hr_approved
        boolean accountant_approved
        bigint created_by FK
        bigint updated_by FK
        timestamps
        timestamp deleted_at
    }

    resignations {
        bigint id PK
        bigint employee_id FK
        bigint department_id FK
        bigint position_id FK
        date resignation_date
        date last_working_date
        string reason
        text reason_details
        string acknowledgement_status
        bigint acknowledged_by FK
        datetime acknowledged_at
        string created_by
        string updated_by
        timestamp deleted_at
        timestamps
    }

    interviews {
        bigint id PK
        string candidate_name
        string phone
        string job_position
        text interviewer_name
        date interview_date
        time start_time
        time end_time
        string interview_mode
        string interview_status
        string hired_status
        decimal score
        text feedback
        text reference_info
        string created_by
        string updated_by
        timestamps
    }

    job_offers {
        bigint id PK
        string custom_offer_id UK
        date date
        string candidate_name
        string position_name
        decimal probation_salary
        decimal post_probation_salary
        date acceptance_deadline
        string acceptance_status
        text note
        string created_by
        string updated_by
        timestamps
    }

    permissions {
        bigint id PK
        string name
        string guard_name
        timestamps
    }

    roles {
        bigint id PK
        string name
        string guard_name
        timestamps
    }

    model_has_permissions {
        bigint permission_id FK
        string model_type
        bigint model_id
    }

    model_has_roles {
        bigint role_id FK
        string model_type
        bigint model_id
    }

    role_has_permissions {
        bigint permission_id FK
        bigint role_id FK
    }
```

## How to View/Convert This Diagram

### Method 1: Online Mermaid Live Editor
1. Visit: https://mermaid.live/
2. Copy the entire Mermaid code above (from ```mermaid to ```)
3. Paste into the editor
4. Click "Export" → "PNG" or "SVG"

### Method 2: VS Code Extension
1. Install "Markdown Preview Mermaid Support" extension
2. Open this file in VS Code
3. Press `Ctrl+Shift+V` to preview
4. Right-click on diagram → Export as PNG/SVG

### Method 3: Command Line (mmdc)
```bash
npm install -g @mermaid-js/mermaid-cli
mmdc -i DATABASE_ERD.md -o DATABASE_ERD.png
```

## Database Structure Summary

### Total Tables: 29
- **Core Tables**: 24 business logic tables
- **Auth/Permission Tables**: 5 tables (Spatie Laravel-Permission)

### Main Entity Groups

#### 1. User & Authentication (2 tables)
- users
- employees

#### 2. Organizational Structure (4 tables)
- departments
- positions
- work_locations
- subsidiaries

#### 3. Employment Management (3 tables)
- employments
- employment_histories
- personnel_actions

#### 4. Grant & Funding Management (5 tables)
- grants
- grant_items
- position_slots
- org_funded_allocations
- employee_funding_allocations

#### 5. Payroll System (3 tables)
- payrolls
- tax_brackets
- bulk_payroll_batches

#### 6. Leave Management (4 tables)
- leave_types
- leave_requests
- leave_request_items
- leave_balances

#### 7. HR Operations (2 tables)
- resignations
- interviews

#### 8. Recruitment (1 table)
- job_offers

#### 9. Permissions & Roles (5 tables)
- permissions
- roles
- model_has_permissions
- model_has_roles
- role_has_permissions

## Key Relationships

### One-to-Many Relationships
- employees → employments (1:M)
- employments → payrolls (1:M)
- grants → grant_items (1:M)
- grant_items → position_slots (1:M)
- departments → positions (1:M)
- leave_requests → leave_request_items (1:M)

### Many-to-One Relationships
- employments → employees (M:1)
- employments → departments (M:1)
- employments → positions (M:1)
- employee_funding_allocations → position_slots (M:1)
- employee_funding_allocations → org_funded_allocations (M:1)

### Self-Referencing Relationships
- positions → positions (reports_to hierarchy)

### Polymorphic Relationships
- model_has_roles (polymorphic to users)
- model_has_permissions (polymorphic to users)

## Business Rules Implemented

1. **Unique Constraints**
   - employees.staff_id (per subsidiary)
   - users.email
   - personnel_actions.reference_number
   - job_offers.custom_offer_id

2. **Soft Deletes**
   - personnel_actions
   - resignations

3. **Encryption**
   - All payroll financial fields stored as encrypted text

4. **Multi-Tenancy**
   - Subsidiary-based separation for employees and grants

5. **Audit Trail**
   - created_by, updated_by on all tables
   - employment_histories for change tracking

## Performance Optimizations

### Indexes Created
- Foreign key indexes on all relationships
- Composite indexes for frequently queried combinations
- Specific indexes for date-based filtering
- Unique indexes for business constraints

### Key Performance Indexes
- employments(employee_id, status)
- employments(start_date, end_date)
- payrolls(employment_id, pay_period_date)
- leave_requests(employee_id, status)
- employee_funding_allocations(employment_id, status)

---

**Generated**: 2025-11-08
**Version**: 1.0
**Database**: SQL Server
**Framework**: Laravel 11
