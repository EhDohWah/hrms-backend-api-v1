# Payroll System Analysis - Section 1: Database Relationships

## Question 1: Complete Database Schema and Relationships

### Tables Overview

#### 1. **payrolls** Table
```sql
- id (primary key)
- employment_id (foreign key -> employments.id)
- employee_funding_allocation_id (foreign key -> employee_funding_allocations.id)
- gross_salary (text, encrypted)
- gross_salary_by_FTE (text, encrypted)
- compensation_refund (text, encrypted)
- thirteen_month_salary (text, encrypted)
- thirteen_month_salary_accured (text, encrypted)
- pvd (text, encrypted, nullable)
- saving_fund (text, encrypted, nullable)
- employer_social_security (text, encrypted)
- employee_social_security (text, encrypted)
- employer_health_welfare (text, encrypted)
- employee_health_welfare (text, encrypted)
- tax (text, encrypted)
- net_salary (text, encrypted)
- total_salary (text, encrypted)
- total_pvd (text, encrypted)
- total_saving_fund (text, encrypted)
- salary_bonus (text, encrypted, nullable)
- total_income (text, encrypted)
- employer_contribution (text, encrypted)
- total_deduction (text, encrypted)
- notes (text, nullable)
- pay_period_date (date)
- created_at, updated_at (timestamps)
```

**Foreign Keys:**
- `employment_id` -> `employments.id` (CASCADE ON UPDATE, NO ACTION ON DELETE)
- `employee_funding_allocation_id` -> `employee_funding_allocations.id` (CASCADE ON UPDATE, NO ACTION ON DELETE)

**Indexes:** None explicitly defined beyond foreign keys

---

#### 2. **employments** Table
```sql
- id (primary key)
- employee_id (foreign key -> employees.id)
- employment_type (string) - 'Full-time', 'Part-time', 'Contract', 'Temporary'
- pay_method (string, nullable) - 'Monthly', 'Weekly', 'Daily', 'Hourly'
- pass_probation_date (date, nullable)
- start_date (date)
- end_date (date, nullable)
- department_id (foreign key -> departments.id, nullable)
- section_department_id (foreign key -> section_departments.id, nullable)
- position_id (foreign key -> positions.id, nullable)
- site_id (foreign key -> sites.id, nullable)
- section_department (string, nullable) - Legacy field
- pass_probation_salary (decimal 10,2)
- probation_salary (decimal 10,2, nullable)
- health_welfare (boolean, default false)
- pvd (boolean, default false)
- saving_fund (boolean, default false)
- status (boolean, default true) - true=Active, false=Inactive
- created_at, updated_at (timestamps)
- created_by, updated_by (string, nullable)
```

**Foreign Keys:**
- `employee_id` -> `employees.id` (CASCADE ON DELETE)
- `department_id` -> `departments.id` (NO ACTION ON DELETE)
- `section_department_id` -> `section_departments.id` (NULL ON DELETE)
- `position_id` -> `positions.id` (NO ACTION ON DELETE)
- `site_id` -> `sites.id` (NULL ON DELETE)

**Indexes:**
- `idx_transition_check` on (pass_probation_date, end_date, status)
- Index on (employee_id, status)
- Index on (department_id, status)
- Index on (section_department_id, status)
- Index on (site_id, status)

---

#### 3. **employee_funding_allocations** Table
```sql
- id (primary key)
- employee_id (foreign key -> employees.id)
- employment_id (foreign key -> employments.id, nullable)
- grant_item_id (foreign key -> grant_items.id, nullable)
- fte (decimal 4,2) - Full-Time Equivalent percentage
- allocation_type (string, 20) - 'grant', 'org_funded'
- allocated_amount (decimal 15,2, nullable)
- salary_type (string, 50, nullable) - 'probation_salary' or 'pass_probation_salary'
- status (string, 20, default 'active') - 'active', 'historical', 'terminated'
- start_date (date, nullable)
- end_date (date, nullable)
- created_by, updated_by (string, 100, nullable)
- created_at, updated_at (timestamps)
```

**Foreign Keys:**
- `employee_id` -> `employees.id` (CASCADE ON DELETE)
- `employment_id` -> `employments.id` (CASCADE ON DELETE)
- `grant_item_id` -> `grant_items.id` (NO ACTION ON DELETE)

**Indexes:**
- Index on (employee_id, employment_id)
- `idx_employment_status` on (employment_id, status)
- `idx_status_end_date` on (status, end_date)
- `idx_grant_item_status` on (grant_item_id, status)

---

#### 4. **employees** Table
```sql
- id (primary key)
- user_id (foreign key -> users.id, nullable)
- organization (string, 10) - 'SMRU' or 'BHF'
- staff_id (string, 50)
- initial_en, initial_th (string, nullable)
- first_name_en, last_name_en (string, 255, nullable)
- first_name_th, last_name_th (string, 255, nullable)
- gender (string, 50)
- date_of_birth (date)
- status (string, 50) - 'Expats', 'Local ID', 'Local non ID'
- nationality, religion (string, 100, nullable)
- social_security_number, tax_number (string, 50, nullable)
- bank_name, bank_branch, bank_account_name, bank_account_number (string, nullable)
- mobile_phone (string, 50, nullable)
- permanent_address, current_address (text, nullable)
- military_status, marital_status (string, nullable)
- spouse_name, spouse_phone_number (string, nullable)
- emergency_contact_person_name, emergency_contact_person_relationship, emergency_contact_person_phone (string, nullable)
- father_name, father_occupation, father_phone_number (string, nullable)
- mother_name, mother_occupation, mother_phone_number (string, nullable)
- driver_license_number (string, 100, nullable)
- remark (string, 255, nullable)
- created_at, updated_at (timestamps)
- created_by, updated_by (string, 100, nullable)
```

**Foreign Keys:**
- `user_id` -> `users.id` (SET NULL ON DELETE)

**Indexes:**
- Index on `organization`
- Index on `staff_id`
- Unique constraint on (staff_id, organization)
- Index on `gender`
- Index on `date_of_birth`
- Index on `status`

---

#### 5. **grants** Table
```sql
- id (primary key)
- code (string)
- name (string)
- organization (string) - 'SMRU' or 'BHF'
- description (text, nullable)
- end_date (date, nullable)
- created_at, updated_at (timestamps)
- created_by, updated_by (string, nullable)
```

**Indexes:**
- `idx_grants_organization_code` on (organization, code)
- `idx_grants_organization_end_date_id` on (organization, end_date, id)

---

#### 6. **grant_items** Table
```sql
- id (primary key)
- grant_id (foreign key -> grants.id)
- grant_position (string, nullable)
- grant_salary (decimal 15,2, nullable)
- grant_benefit (decimal 15,2, nullable)
- grant_level_of_effort (decimal 5,2, nullable)
- grant_position_number (integer, nullable)
- budgetline_code (string, 255, nullable) - Can be NULL for General Fund
- created_at, updated_at (timestamps)
- created_by, updated_by (string, 255, nullable)
```

**Foreign Keys:**
- `grant_id` -> `grants.id` (CASCADE ON DELETE)

**Indexes:** None explicitly defined beyond foreign keys

---

#### 7. **departments** Table
```sql
- id (primary key)
- name (string, unique)
- description (string, nullable)
- is_active (boolean, default true)
- created_at, updated_at (timestamps)
- created_by, updated_by (string, nullable)
```

**Seeded Departments:**
Administration, Finance, Grant, Human Resources, Logistics, Procurement & Store, Data management, IT, Clinical, Research/Study, Training, Research/Study M&E, MCH, M&E, Laboratory, Malaria, Public Engagement, TB, Media Group

---

#### 8. **positions** Table
```sql
- id (primary key)
- title (string)
- department_id (foreign key -> departments.id)
- reports_to_position_id (foreign key -> positions.id, nullable)
- level (integer, default 1) - Hierarchy level
- is_manager (boolean, default false)
- is_active (boolean, default true)
- created_at, updated_at (timestamps)
- created_by, updated_by (string, nullable)
```

**Foreign Keys:**
- `department_id` -> `departments.id` (CASCADE ON DELETE)
- `reports_to_position_id` -> `positions.id` (NO ACTION ON DELETE)

**Indexes:**
- Index on (department_id, is_active)
- Index on reports_to_position_id
- Index on (department_id, level)

---

## Relationship Diagram

```
employees (1) ----< (M) employments (1) ----< (M) payrolls
    |                       |                           |
    |                       |                           |
    |                   department (M) --- (1) departments
    |                   position (M) --- (1) positions
    |
    +----< (M) employee_funding_allocations (M) --- (1) grant_items (M) --- (1) grants
                                                  |
                                                  +--- (1) employments
```

### Key Relationships:

1. **One Employee** has **One Employment** (active)
2. **One Employment** has **Many Payrolls** (one per pay period per funding allocation)
3. **One Employee** has **Many Employee Funding Allocations** (can have multiple grants)
4. **One Employee Funding Allocation** has **Many Payrolls** (one per pay period)
5. **One Grant** has **Many Grant Items** (positions within the grant)
6. **One Grant Item** has **Many Employee Funding Allocations** (multiple employees can be assigned to same position)
7. **One Department** has **Many Positions**
8. **One Position** can report to **One Position** (hierarchy)

---

## Question 2: Eloquent Model Relationships in Payroll Model

### Payroll Model Relationships

```php
// File: app/Models/Payroll.php

// 1. belongsTo Employment
public function employment()
{
    return $this->belongsTo(Employment::class, 'employment_id');
}

// 2. belongsTo EmployeeFundingAllocation
public function employeeFundingAllocation()
{
    return $this->belongsTo(EmployeeFundingAllocation::class, 'employee_funding_allocation_id');
}

// 3. hasOneThrough Employee (through Employment)
public function employee()
{
    return $this->hasOneThrough(
        Employee::class,      // Final model
        Employment::class,    // Intermediate model
        'id',                 // Foreign key on intermediate (employments.id)
        'id',                 // Foreign key on final (employees.id)
        'employment_id',      // Local key on payrolls
        'employee_id'         // Local key on employments
    );
}

// 4. hasMany PayrollGrantAllocation (not yet implemented in migration)
public function grantAllocations()
{
    return $this->hasMany(PayrollGrantAllocation::class, 'payroll_id');
}
```

### Relationship Summary:

| Relationship Type | Related Model | Description |
|-------------------|---------------|-------------|
| `belongsTo` | Employment | Each payroll belongs to one employment record |
| `belongsTo` | EmployeeFundingAllocation | Each payroll belongs to one funding allocation |
| `hasOneThrough` | Employee | Access employee data through employment |
| `hasMany` | PayrollGrantAllocation | Future: Split payroll across multiple grants |

### Usage in Queries:

```php
// Eager load all relationships
$payroll = Payroll::with([
    'employment.employee',
    'employment.department',
    'employment.position',
    'employeeFundingAllocation.grantItem.grant'
])->find($id);

// Access related data
$employeeName = $payroll->employment->employee->first_name_en;
$department = $payroll->employment->department->name;
$grantName = $payroll->employeeFundingAllocation->grantItem->grant->name;
$fte = $payroll->employeeFundingAllocation->fte;
```

---

## Question 3: employee_funding_allocations Table Structure

### Table Schema

```sql
CREATE TABLE employee_funding_allocations (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    employee_id BIGINT NOT NULL,
    employment_id BIGINT NULL,
    grant_item_id BIGINT NULL COMMENT 'Direct link to grant_items for all allocations',
    fte DECIMAL(4,2) NOT NULL COMMENT 'Full-Time Equivalent - actual funding allocation percentage',
    allocation_type VARCHAR(20) NOT NULL COMMENT 'grant, org_funded',
    allocated_amount DECIMAL(15,2) NULL,
    salary_type VARCHAR(50) NULL COMMENT 'probation_salary or pass_probation_salary',
    status VARCHAR(20) DEFAULT 'active' COMMENT 'active, historical, terminated',
    start_date DATE NULL,
    end_date DATE NULL,
    created_by VARCHAR(100) NULL,
    updated_by VARCHAR(100) NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (employment_id) REFERENCES employments(id) ON DELETE CASCADE,
    FOREIGN KEY (grant_item_id) REFERENCES grant_items(id) ON DELETE NO ACTION,
    
    INDEX idx_employee_employment (employee_id, employment_id),
    INDEX idx_employment_status (employment_id, status),
    INDEX idx_status_end_date (status, end_date),
    INDEX idx_grant_item_status (grant_item_id, status)
);
```

### Column Descriptions:

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key |
| `employee_id` | BIGINT | Links to employees table |
| `employment_id` | BIGINT | Links to employments table (nullable) |
| `grant_item_id` | BIGINT | Links to grant_items table (nullable for org-funded) |
| `fte` | DECIMAL(4,2) | Full-Time Equivalent (0.00 to 1.00 or 0% to 100%) |
| `allocation_type` | VARCHAR(20) | Type: 'grant' or 'org_funded' |
| `allocated_amount` | DECIMAL(15,2) | Pre-calculated allocation amount (optional) |
| `salary_type` | VARCHAR(50) | Which salary is used: 'probation_salary' or 'pass_probation_salary' |
| `status` | VARCHAR(20) | Lifecycle: 'active', 'historical', 'terminated' |
| `start_date` | DATE | When allocation starts |
| `end_date` | DATE | When allocation ends (NULL = ongoing) |

### Relationship to Grants:

```
employee_funding_allocations
    |
    +-- grant_item_id --> grant_items
                              |
                              +-- grant_id --> grants
```

**How it connects:**
1. An employee funding allocation links to a `grant_item` (a specific position within a grant)
2. The `grant_item` belongs to a `grant` (the overall grant/project)
3. The `grant` has organization, code, name, and end_date
4. For organization-funded employees, `grant_item_id` may be NULL or link to a "General Fund" grant item

### Example Data:

```sql
-- Employee with 50% on Grant A, 50% on Grant B
INSERT INTO employee_funding_allocations VALUES
(1, 123, 456, 789, 0.50, 'grant', 25000.00, 'pass_probation_salary', 'active', '2025-01-01', NULL, 'admin', 'admin', NOW(), NOW()),
(2, 123, 456, 790, 0.50, 'grant', 25000.00, 'pass_probation_salary', 'active', '2025-01-01', NULL, 'admin', 'admin', NOW(), NOW());
```

---

## Question 4: employees Table Structure

### Table Schema

```sql
CREATE TABLE employees (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NULL,
    organization VARCHAR(10) NOT NULL COMMENT 'SMRU or BHF',
    staff_id VARCHAR(50) NOT NULL,
    initial_en VARCHAR(10) NULL,
    initial_th VARCHAR(20) NULL,
    first_name_en VARCHAR(255) NULL,
    last_name_en VARCHAR(255) NULL,
    first_name_th VARCHAR(255) NULL,
    last_name_th VARCHAR(255) NULL,
    gender VARCHAR(50) NOT NULL,
    date_of_birth DATE NOT NULL,
    status VARCHAR(50) NOT NULL COMMENT 'Expats, Local ID, Local non ID',
    nationality VARCHAR(100) NULL,
    religion VARCHAR(100) NULL,
    social_security_number VARCHAR(50) NULL,
    tax_number VARCHAR(50) NULL,
    bank_name VARCHAR(100) NULL,
    bank_branch VARCHAR(100) NULL,
    bank_account_name VARCHAR(100) NULL,
    bank_account_number VARCHAR(50) NULL,
    mobile_phone VARCHAR(50) NULL,
    permanent_address TEXT NULL,
    current_address TEXT NULL,
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
    driver_license_number VARCHAR(100) NULL,
    remark VARCHAR(255) NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    created_by VARCHAR(100) NULL,
    updated_by VARCHAR(100) NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    UNIQUE KEY unique_staff_org (staff_id, organization),
    INDEX idx_organization (organization),
    INDEX idx_staff_id (staff_id),
    INDEX idx_gender (gender),
    INDEX idx_date_of_birth (date_of_birth),
    INDEX idx_status (status)
);
```

### Key Columns for Basic Information:

| Column | Type | Description | Example |
|--------|------|-------------|---------|
| `staff_id` | VARCHAR(50) | Unique employee identifier within organization | "0001", "EMP-2025-001" |
| `organization` | VARCHAR(10) | Organization code | "SMRU", "BHF" |
| `first_name_en` | VARCHAR(255) | First name in English | "John" |
| `last_name_en` | VARCHAR(255) | Last name in English | "Doe" |
| `first_name_th` | VARCHAR(255) | First name in Thai | "จอห์น" |
| `last_name_th` | VARCHAR(255) | Last name in Thai | "โด" |
| `initial_en` | VARCHAR(10) | English initial/title | "Mr.", "Dr." |
| `initial_th` | VARCHAR(20) | Thai initial/title | "นาย", "ดร." |
| `status` | VARCHAR(50) | Employee status | "Expats", "Local ID", "Local non ID" |

### Important Notes:

1. **Unique Constraint**: `(staff_id, organization)` - Same staff_id can exist in different organizations
2. **Organization Values**: Only "SMRU" or "BHF"
3. **Status Values**: "Expats", "Local ID", "Local non ID"
4. **Names**: System stores both English and Thai names
5. **User Link**: `user_id` links to system user account (nullable - not all employees have login)

---

## Question 5: grants Table Structure

### Table Schema

```sql
CREATE TABLE grants (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    organization VARCHAR(255) NOT NULL COMMENT 'SMRU or BHF',
    description TEXT NULL,
    end_date DATE NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    
    INDEX idx_grants_organization_code (organization, code),
    INDEX idx_grants_organization_end_date_id (organization, end_date, id)
);
```

### Column Descriptions:

| Column | Type | Description | Example |
|--------|------|-------------|---------|
| `id` | BIGINT | Primary key | 1, 2, 3 |
| `code` | VARCHAR(255) | Grant code/identifier | "S0031", "BHF001", "SEADOT-2025" |
| `name` | VARCHAR(255) | Grant name | "SEADOT", "Core-THB", "Flagship", "General Fund" |
| `organization` | VARCHAR(255) | Owning organization | "SMRU", "BHF" |
| `description` | TEXT | Grant description | "Research grant for malaria studies" |
| `end_date` | DATE | Grant end date | "2025-12-31" (NULL = no end date) |

### Grant Types:

1. **Project Grants**: Specific research/project grants with budget line codes
   - Example: SEADOT, Core-THB, Flagship
   - Have specific grant items with budget line codes
   - Time-limited (have end_date)

2. **Hub Grants / General Fund**: Organization savings/general fund
   - Example: "S0031" (SMRU Other Fund), "S22001" (BHF General Fund)
   - Grant items have NULL budget line codes
   - Usually no end_date (ongoing)

### Example Grant Names (from system):

- **SEADOT** - Southeast Asia Diseases Outbreak Team
- **Core-THB** - Core Thailand Burma
- **Flagship** - Flagship project
- **General Fund** - Organization general fund
- **SMRU Other Fund** (S0031)
- **BHF General Fund** (S22001)

### Relationship to Grant Items:

```sql
-- One grant has many grant items (positions)
grants (1) ----< (M) grant_items

-- Example:
Grant: "SEADOT" (id=1)
  +-- Grant Item: "Senior Researcher" (budgetline_code: "1.2.2.1")
  +-- Grant Item: "Research Assistant" (budgetline_code: "1.2.2.2")
  +-- Grant Item: "Data Analyst" (budgetline_code: "1.2.3.1")
```

### Notes:

1. Grants are imported via Excel upload
2. Default hub grants (General Fund) should be imported, not created automatically
3. Organization field determines which org owns/manages the grant
4. Composite indexes optimize filtering by organization and date ranges




