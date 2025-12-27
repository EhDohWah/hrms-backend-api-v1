# HRMS Employee-to-Payroll Workflow Analysis

## Executive Summary

This document provides a comprehensive analysis of the HRMS system's Employee-to-Payroll workflow, covering the complete data flow from employee creation through employment record management to payroll generation. The system is built on **Laravel 11** (backend) and **Vue 3 with Pinia** (frontend), supporting multi-organization operations (SMRU and BHF).

---

## PART 1: Employee Record Creation Workflow

### 1.1 Frontend UI Components

**Primary Component:** `src/components/modal/employee-details-modal.vue` (2,867 lines)

**Form Sections:**
1. **Basic Information** - Staff ID, Organization, Names (Thai/English), Title, Photo
2. **Personal Information** - DOB, Gender, Nationality, Religion, ID Card, Passport
3. **Contact Information** - Address, Phone, Email, Emergency Contact
4. **Bank Information** - Bank Name, Account Number, Branch
5. **Family Information** - Marital Status, Spouse Details, Children, Parents

**Key Frontend Features:**
- Draft persistence using `localStorage` (auto-saves form state)
- Real-time validation with immediate feedback
- Photo upload with preview
- Virtual scrolling for large dropdowns (TreeSelect component)
- Stale-While-Revalidate (SWR) caching pattern

### 1.2 Backend API Endpoint

**Endpoint:** `POST /api/v1/employees`
**Controller:** `App\Http\Controllers\Api\EmployeeController::store()` (line 983)
**Request Validation:** `App\Http\Requests\StoreEmployeeRequest`

**Validation Rules (Key Fields):**
```php
'staff_id' => 'required|string|unique:employees,staff_id|max:10'
'organization' => 'nullable|in:SMRU,BHF'
'first_name' => 'required|string|max:255'
'last_name' => 'required|string|max:255'
'email' => 'nullable|email|unique:employees,email'
'phone' => 'nullable|string|max:20'
'date_of_birth' => 'required|date'
```

### 1.3 Database Table: `employees`

**Migration:** `database/migrations/2025_02_12_131510_create_employees_table.php`

**Key Columns (41 fillable fields):**
| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key |
| `staff_id` | VARCHAR(10) | Unique staff identifier |
| `organization` | ENUM | 'SMRU' or 'BHF' |
| `first_name`, `last_name` | VARCHAR | Name fields |
| `first_name_thai`, `last_name_thai` | VARCHAR | Thai name fields |
| `date_of_birth` | DATE | Birth date |
| `gender` | ENUM | 'male', 'female', 'other' |
| `nationality`, `religion` | VARCHAR | Demographics |
| `id_card_number` | VARCHAR | National ID |
| `passport_number`, `passport_expiry` | VARCHAR, DATE | Passport info |
| `bank_name`, `bank_account`, `bank_branch` | VARCHAR | Banking details |
| `profile_picture_path` | VARCHAR | Photo storage path |
| `marital_status` | ENUM | Relationship status |
| `user_id` | BIGINT | FK to users (nullable) |
| `created_at`, `updated_at`, `deleted_at` | TIMESTAMP | Timestamps + soft delete |

### 1.4 Eloquent Model: `App\Models\Employee`

**Key Relationships:**
```php
// One-to-One
public function user(): BelongsTo

// One-to-Many (Employee has many)
public function employments(): HasMany
public function employeeFundingAllocations(): HasMany
public function leaveBalances(): HasMany
public function taxCalculationLogs(): HasMany
public function payrolls(): HasMany
public function children(): HasMany
public function beneficiaries(): HasMany
public function educations(): HasMany
public function trainings(): BelongsToMany

// One-to-One (Latest)
public function latestEmployment(): HasOne // Orders by created_at desc
```

**Key Methods:**
```php
public function hasUserAccount(): bool
public function isOnProbation(): bool
public function getCurrentSalary(): float
public function getFullNameAttribute(): string
```

### 1.5 Events and Side Effects

**Observer:** `App\Observers\EmployeeObserver`

On employee creation (`created` event):
1. **Auto-creates Leave Balances** - For each active leave type, creates a `LeaveBalance` record with initial balance (default: 0 days)
2. **Sends Notification** - Dispatches `EmployeeActionNotification` (queued) to relevant users

**Notification:** `App\Notifications\EmployeeActionNotification`
- Uses `ShouldQueue` for async processing
- Notifies HR managers of new employee creation

### 1.6 Audit Logging

**Trait:** `App\Traits\LogsActivity`
- Logs all create/update/delete operations
- Stores in `activity_logs` table
- Records: user_id, action, model_type, model_id, old_values, new_values, ip_address

---

## PART 2: Employment Record and Funding Allocation Workflow

### 2.1 Frontend UI Components

**Primary Component:** `src/components/modal/employment-modal.vue` (3,957 lines)

**Form Sections:**
1. **Employee Selection** - TreeSelect with search, virtual scrolling
2. **Employment Details** - Position, Department, Site, Employment Type
3. **Salary Information** - Base Salary, Currency, Pay Frequency
4. **Funding Allocations** - Grant selection, FTE percentage, budget line codes
5. **Probation Settings** - Duration, salary during probation

**Key Features:**
- **Real-time salary calculation** via API call (`POST /api/v1/employments/calculate-allocation`)
- **FTE validation** - Must total exactly 100%
- **Grant capacity checking** - Validates available FTE on selected grants
- **Probation period handling** - Different salary rates during probation

### 2.2 Backend API Endpoints

**Employment CRUD:**
| Method | Endpoint | Permission | Description |
|--------|----------|------------|-------------|
| GET | `/api/v1/employments` | `employment_records.read` | List employments |
| GET | `/api/v1/employments/{id}` | `employment_records.read` | Get single employment |
| POST | `/api/v1/employments` | `employment_records.edit` | Create employment |
| PUT | `/api/v1/employments/{id}` | `employment_records.edit` | Update employment |
| DELETE | `/api/v1/employments/{id}` | `employment_records.edit` | Delete employment |
| POST | `/api/v1/employments/{id}/complete-probation` | `employment_records.edit` | Complete probation |
| POST | `/api/v1/employments/calculate-allocation` | `employment_records.read` | Calculate allocation |

**Controller:** `App\Http\Controllers\Api\EmploymentController`

### 2.3 Database Tables

#### Table: `employments`
**Migration:** `database/migrations/2025_02_13_025537_create_employments_table.php`

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key |
| `employee_id` | BIGINT | FK to employees |
| `site_id` | BIGINT | FK to sites (nullable) |
| `section_department_id` | BIGINT | FK to section_departments |
| `position` | VARCHAR | Position title |
| `employment_type` | ENUM | 'full_time', 'part_time', 'contract' |
| `employment_status` | ENUM | 'active', 'inactive', 'on_leave', 'terminated' |
| `start_date` | DATE | Employment start |
| `end_date` | DATE | Employment end (nullable) |
| `base_salary` | DECIMAL(12,2) | Monthly base salary |
| `salary_currency` | VARCHAR(3) | Currency code (THB) |
| `probation_period_months` | INTEGER | Probation duration |
| `probation_end_date` | DATE | Computed probation end |
| `probation_status` | ENUM | 'in_probation', 'completed', 'failed' |
| `probation_salary` | DECIMAL(12,2) | Salary during probation |
| `regular_salary` | DECIMAL(12,2) | Salary after probation |
| `reporting_to` | BIGINT | FK to employees (manager) |

#### Table: `employee_funding_allocations`
**Migration:** `database/migrations/2025_04_07_090015_create_employee_funding_allocations_table.php`

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key |
| `employee_id` | BIGINT | FK to employees |
| `employment_id` | BIGINT | FK to employments (nullable) |
| `grant_id` | BIGINT | FK to grants |
| `grant_item_id` | BIGINT | FK to grant_items |
| `fte` | DECIMAL(5,4) | FTE percentage (e.g., 0.5000 = 50%) |
| `allocated_amount` | DECIMAL(15,2) | Calculated: salary × FTE |
| `status` | ENUM | 'active', 'historical', 'terminated' |
| `effective_date` | DATE | When allocation starts |
| `end_date` | DATE | When allocation ends (nullable) |
| `notes` | TEXT | Additional notes |

### 2.4 Eloquent Models

#### `App\Models\Employment`

**Key Relationships:**
```php
public function employee(): BelongsTo
public function site(): BelongsTo
public function sectionDepartment(): BelongsTo
public function fundingAllocations(): HasMany
public function payrolls(): HasMany
public function probationRecord(): HasOne
```

**Key Methods:**
```php
// Get current effective salary (considers probation)
public function getCurrentSalary(): float
{
    if ($this->probation_status === 'in_probation') {
        return (float) ($this->probation_salary ?? $this->base_salary);
    }
    return (float) ($this->regular_salary ?? $this->base_salary);
}

// Calculate salary for specific date
public function getSalaryAmountForDate($date): float

// Calculate allocated amount for FTE
public function calculateAllocatedAmount(float $fte, $date = null): float
{
    $salary = $date ? $this->getSalaryAmountForDate($date) : $this->getCurrentSalary();
    return round($salary * $fte, 2);
}

// Update allocations after probation completion
public function updateFundingAllocationsAfterProbation(): bool
```

#### `App\Models\EmployeeFundingAllocation`

**Key Relationships:**
```php
public function employee(): BelongsTo
public function employment(): BelongsTo
public function grant(): BelongsTo
public function grantItem(): BelongsTo
```

**Status Lifecycle:**
- `active` → Currently in use for payroll calculations
- `historical` → Superseded by newer allocation (probation completed, salary change)
- `terminated` → Employment ended

### 2.5 Business Logic: Funding Allocation Service

**Service:** `App\Services\FundingAllocationService`

**Key Validation Rules:**
1. **100% FTE Requirement** - Total allocations must equal exactly 100%
2. **Grant Capacity Check** - Cannot exceed remaining FTE on grant item
3. **No Overlapping Allocations** - Same grant item cannot be allocated twice
4. **Date Range Validation** - Allocation dates must be within employment dates

**FTE Validation Logic:**
```php
public function validateFteTotal(array $allocations): bool
{
    $total = array_sum(array_column($allocations, 'fte'));
    return abs($total - 1.0) < 0.0001; // Must equal 100% (1.0)
}
```

### 2.6 Probation Period Handling

**Service:** `App\Services\ProbationTransitionService`

When probation completes:
1. Update `probation_status` to 'completed'
2. Update funding allocations with new salary amount
3. Mark old allocations as 'historical'
4. Create new allocations with regular salary
5. Record in `probation_records` table

---

## PART 3: Payroll Generation Workflow

### 3.1 Frontend UI Components

**Payroll Creation Types:**
1. **Individual Payroll** - Create for single employee
2. **Bulk Payroll** - Create for multiple employees with real-time progress

**Bulk Payroll Flow:**
1. Select pay period (month/year)
2. Filter employees (by organization, site, department)
3. Preview payroll calculations
4. Confirm and create payrolls
5. Track progress via WebSocket events

### 3.2 Backend API Endpoints

**Payroll CRUD:**
| Method | Endpoint | Permission | Description |
|--------|----------|------------|-------------|
| GET | `/api/v1/payrolls` | `employee_salary.read` | List payrolls |
| GET | `/api/v1/payrolls/{id}` | `employee_salary.read` | Get payroll detail |
| POST | `/api/v1/payrolls` | `employee_salary.edit` | Create payroll |
| PUT | `/api/v1/payrolls/{id}` | `employee_salary.edit` | Update payroll |
| DELETE | `/api/v1/payrolls/{id}` | `employee_salary.edit` | Delete payroll |
| POST | `/api/v1/payrolls/calculate` | `employee_salary.read` | Calculate without saving |
| POST | `/api/v1/payrolls/bulk/preview` | `employee_salary.edit` | Bulk preview |
| POST | `/api/v1/payrolls/bulk/create` | `employee_salary.edit` | Bulk create |
| GET | `/api/v1/payrolls/bulk/status/{batchId}` | `employee_salary.edit` | Get batch status |

**Controllers:**
- `App\Http\Controllers\Api\PayrollController` - Individual payroll
- `App\Http\Controllers\Api\BulkPayrollController` - Bulk processing

### 3.3 Database Table: `payrolls`

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key |
| `employee_id` | BIGINT | FK to employees |
| `employment_id` | BIGINT | FK to employments |
| `pay_period_start` | DATE | Period start date |
| `pay_period_end` | DATE | Period end date |
| `gross_salary` | DECIMAL(15,2) | **Encrypted** |
| `gross_salary_by_fte` | DECIMAL(15,2) | **Encrypted** |
| `compensation_refund` | DECIMAL(15,2) | **Encrypted** |
| `thirteenth_month_salary` | DECIMAL(15,2) | **Encrypted** |
| `pvd_saving_fund` | DECIMAL(15,2) | **Encrypted** |
| `employee_social_security` | DECIMAL(15,2) | **Encrypted** (5%, max 750฿) |
| `employer_social_security` | DECIMAL(15,2) | **Encrypted** (5%, max 750฿) |
| `health_welfare` | DECIMAL(15,2) | **Encrypted** |
| `income_tax` | DECIMAL(15,2) | **Encrypted** |
| `net_salary` | DECIMAL(15,2) | **Encrypted** |
| `total_salary` | DECIMAL(15,2) | **Encrypted** |
| `total_pvd` | DECIMAL(15,2) | **Encrypted** |
| `status` | ENUM | 'draft', 'approved', 'paid' |
| `payment_date` | DATE | When paid |
| `notes` | TEXT | Additional notes |

**Note:** 19 salary-related fields use Laravel's native encryption for data protection.

### 3.4 Payroll Calculation Service

**Service:** `App\Services\PayrollService`

**Main Method:** `processEmployeePayroll(Employee $employee, Carbon $payPeriodDate, bool $savePayroll)`

**13 Calculated Payroll Items:**
1. `gross_salary` - Base salary from employment
2. `gross_salary_by_FTE` - Salary × total FTE
3. `compensation_refund` - Any compensation adjustments
4. `13th_month_salary` - Annual bonus (if applicable)
5. `pvd_saving_fund` - Provident Fund contribution
6. `employee_social_security` - 5% capped at 750 THB
7. `employer_social_security` - 5% capped at 750 THB
8. `health_welfare` - Health benefit deductions
9. `income_tax` - Thai progressive tax
10. `net_salary` - Take-home pay
11. `total_salary` - Gross - deductions
12. `total_pvd` - Total provident fund
13. `per_grant_allocation` - Breakdown by funding source

**Calculation Flow:**
```
1. Get Employee + Employment
2. Get Active Funding Allocations
3. Calculate Gross Salary by FTE
4. Apply Benefits (PVD, 13th month)
5. Calculate Social Security (5% max 750)
6. Calculate Income Tax (Thai brackets)
7. Calculate Net Salary
8. If savePayroll → persist to database
9. Return PayrollResource
```

### 3.5 Thai Tax Calculation

**Service:** `App\Services\TaxCalculationService`

**Tax Calculation Sequence:**
1. **Employment Deductions** - 50% of income, max 100,000 THB
2. **Personal Allowances**:
   - Personal: 60,000 THB
   - Spouse (if applicable): 60,000 THB
   - Child: 30,000 THB (60,000 if studying)
   - Parent (each, max 2): 30,000 THB
3. **Social Security Deduction** - Actual amount paid
4. **Provident Fund Deduction** - Actual amount contributed
5. **Apply Progressive Tax Brackets**

**Thai Progressive Tax Brackets (2024):**
| Taxable Income (THB) | Rate |
|---------------------|------|
| 0 - 150,000 | 0% |
| 150,001 - 300,000 | 5% |
| 300,001 - 500,000 | 10% |
| 500,001 - 750,000 | 15% |
| 750,001 - 1,000,000 | 20% |
| 1,000,001 - 2,000,000 | 25% |
| 2,000,001 - 5,000,000 | 30% |
| 5,000,001+ | 35% |

**Social Security Calculation:**
```php
$socialSecurityRate = 0.05; // 5%
$maxContribution = 750; // THB per month
$contribution = min($grossSalary * $socialSecurityRate, $maxContribution);
```

### 3.6 Inter-Organization Advances

**Model:** `App\Models\InterOrganizationAdvance`

When an employee's organization differs from their funding source organization:
- System creates an inter-organization advance record
- Tracks amount owed between organizations
- Used for accounting reconciliation

---

## PART 4: Workflow Dependencies and Data Flow

### 4.1 Complete Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                         EMPLOYEE CREATION FLOW                               │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  [Frontend: employee-details-modal.vue]                                      │
│           │                                                                  │
│           ▼                                                                  │
│  [Validation] ──────────► [StoreEmployeeRequest]                            │
│           │                                                                  │
│           ▼                                                                  │
│  [EmployeeController::store()]                                              │
│           │                                                                  │
│           ▼                                                                  │
│  [Employee Model Created] ──► [EmployeeObserver::created()]                 │
│           │                              │                                   │
│           │                              ├── Create Leave Balances          │
│           │                              └── Send Notification              │
│           ▼                                                                  │
│  [Activity Log Created]                                                      │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                        EMPLOYMENT CREATION FLOW                              │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  [Frontend: employment-modal.vue]                                            │
│           │                                                                  │
│           ▼                                                                  │
│  [Select Employee] ──► [Load Grant Structure]                               │
│           │                    │                                             │
│           ▼                    ▼                                             │
│  [Enter Employment Details]  [Select Funding Sources]                       │
│           │                    │                                             │
│           │                    ▼                                             │
│           │           [Validate 100% FTE Total]                             │
│           │                    │                                             │
│           ▼                    ▼                                             │
│  [Calculate Allocation API] ◄─┘                                             │
│           │                                                                  │
│           ▼                                                                  │
│  [EmploymentController::store()]                                            │
│           │                                                                  │
│           ├──► [Create Employment Record]                                   │
│           │                                                                  │
│           └──► [Create Funding Allocations]                                 │
│                        │                                                     │
│                        ▼                                                     │
│              [FundingAllocationService]                                     │
│                        │                                                     │
│                        ├── Validate FTE totals                              │
│                        ├── Check grant capacity                             │
│                        └── Calculate allocated amounts                       │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────────────────┐
│                         PAYROLL GENERATION FLOW                              │
├─────────────────────────────────────────────────────────────────────────────┤
│                                                                              │
│  [Select Pay Period] ──► [Filter Employees]                                 │
│           │                                                                  │
│           ▼                                                                  │
│  [Preview Calculations] ◄─── [PayrollService::processEmployeePayroll()]    │
│           │                              │                                   │
│           │                              ├── Get Employment                  │
│           │                              ├── Get Funding Allocations         │
│           │                              ├── Calculate Gross by FTE          │
│           │                              ├── Apply Benefits                  │
│           │                              ├── Calculate Social Security       │
│           │                              ├── Calculate Tax                   │
│           │                              │        │                          │
│           │                              │        ▼                          │
│           │                              │  [TaxCalculationService]         │
│           │                              │        │                          │
│           │                              │        ├── Employment Deductions  │
│           │                              │        ├── Personal Allowances    │
│           │                              │        └── Progressive Tax        │
│           │                              │                                   │
│           │                              └── Calculate Net Salary            │
│           ▼                                                                  │
│  [Confirm Creation]                                                          │
│           │                                                                  │
│           ▼                                                                  │
│  [Save Payroll Records] ──► [Update Grant Item Spending]                    │
│           │                                                                  │
│           ▼                                                                  │
│  [Create Inter-Org Advances (if needed)]                                    │
│                                                                              │
└─────────────────────────────────────────────────────────────────────────────┘
```

### 4.2 Business Rules Across Workflows

| Rule | Workflow | Implementation |
|------|----------|----------------|
| Unique Staff ID | Employee | `StoreEmployeeRequest` validation |
| Valid Organization | Employee | ENUM constraint: SMRU, BHF |
| 100% FTE Allocation | Employment | `FundingAllocationService::validateFteTotal()` |
| Grant Capacity | Employment | Check `grant_items.remaining_fte` |
| Probation Salary | Employment | Separate `probation_salary` field |
| Salary Encryption | Payroll | Laravel encrypted casts |
| Tax Compliance | Payroll | Thai tax bracket calculations |
| Social Security Cap | Payroll | 5% capped at 750 THB |

### 4.3 Error Handling and Rollback

**Transaction Handling:**
All multi-table operations use database transactions:

```php
DB::beginTransaction();
try {
    $employment = Employment::create($data);
    foreach ($allocations as $allocation) {
        EmployeeFundingAllocation::create([...]);
    }
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    throw $e;
}
```

**Soft Deletes:**
- Employees, Employments, Allocations use `SoftDeletes` trait
- Deleted records moved to "Recycle Bin"
- Can be restored via `RecycleBinController`

---

## PART 5: Technical Architecture

### 5.1 Entity Relationship Diagram

```
┌──────────────┐     ┌──────────────────┐     ┌───────────────────┐
│    users     │     │    employees     │     │    employments    │
├──────────────┤     ├──────────────────┤     ├───────────────────┤
│ id           │◄────│ user_id (FK)     │     │ id                │
│ name         │     │ id               │◄────│ employee_id (FK)  │
│ email        │     │ staff_id         │     │ site_id (FK)      │
│ password     │     │ organization     │     │ section_dept_id   │
│ role         │     │ first_name       │     │ position          │
└──────────────┘     │ last_name        │     │ employment_type   │
                     │ date_of_birth    │     │ base_salary       │
                     │ ...              │     │ probation_salary  │
                     └──────────────────┘     │ probation_status  │
                              │               └───────────────────┘
                              │                         │
                              ▼                         ▼
┌──────────────────────────────────────────────────────────────────┐
│                  employee_funding_allocations                     │
├──────────────────────────────────────────────────────────────────┤
│ id | employee_id (FK) | employment_id (FK) | grant_id (FK)       │
│ grant_item_id (FK) | fte | allocated_amount | status             │
└──────────────────────────────────────────────────────────────────┘
                              │
          ┌───────────────────┴───────────────────┐
          ▼                                       ▼
┌──────────────────┐                    ┌──────────────────┐
│     grants       │                    │   grant_items    │
├──────────────────┤                    ├──────────────────┤
│ id               │◄───────────────────│ grant_id (FK)    │
│ grant_code       │                    │ id               │
│ grant_name       │                    │ budget_line_code │
│ organization     │                    │ position_title   │
│ start_date       │                    │ total_fte        │
│ end_date         │                    │ remaining_fte    │
│ total_budget     │                    │ salary_amount    │
└──────────────────┘                    └──────────────────┘
                              │
                              ▼
┌──────────────────────────────────────────────────────────────────┐
│                         payrolls                                  │
├──────────────────────────────────────────────────────────────────┤
│ id | employee_id (FK) | employment_id (FK) | pay_period_start    │
│ pay_period_end | gross_salary (encrypted) | net_salary (encrypted)│
│ income_tax (encrypted) | social_security (encrypted) | status    │
└──────────────────────────────────────────────────────────────────┘
```

### 5.2 Frontend State Management (Pinia Stores)

| Store | Purpose | Key State |
|-------|---------|-----------|
| `authStore` | Authentication, permissions | `user`, `token`, `permissions`, `role` |
| `employeeStore` | Employee CRUD operations | `employees`, `selectedEmployee`, `loading` |
| `employmentStore` | Employment management | `employments`, `currentEmployment` |
| `lookupStore` | Dropdown options | `departments`, `positions`, `sites`, `grantOptions` |
| `payrollStore` | Payroll operations | `payrolls`, `calculatedPayroll`, `bulkProgress` |
| `sharedDataStore` | Cross-component data | `grants`, `organizations`, `lookups` |
| `notificationStore` | User notifications | `notifications`, `unreadCount` |

### 5.3 API Design Patterns

**Resource Responses:**
All API responses follow consistent structure:
```json
{
  "success": true,
  "message": "Resource retrieved successfully",
  "data": { ... }
}
```

**Pagination:**
```json
{
  "success": true,
  "data": [...],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 15,
    "total": 73
  }
}
```

**Error Responses:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "field_name": ["Error message"]
  }
}
```

### 5.4 Performance Considerations

**Database Optimizations:**
- Indexed columns: `staff_id`, `employee_id`, `employment_id`, `grant_id`, `pay_period_start`
- Eager loading relationships to prevent N+1 queries
- Query caching for frequently accessed lookups

**Frontend Optimizations:**
- Virtual scrolling for large lists (TreeSelect)
- Stale-While-Revalidate (SWR) caching
- Debounced search inputs
- Lazy loading of modal components

**Bulk Operations:**
- Async job processing for bulk payroll
- Real-time progress via WebSocket (Laravel Reverb)
- Batch size limits to prevent timeouts

---

## PART 6: Integration Points

### 6.1 Cross-Module Dependencies

```
Employee Module
    │
    ├──► Leave Management (creates leave balances on employee creation)
    │
    ├──► User Management (optional user account linkage)
    │
    └──► Employment Module
              │
              ├──► Grant Management (funding allocations)
              │
              ├──► Organization Structure (sites, departments)
              │
              └──► Payroll Module
                        │
                        ├──► Tax Calculation (Thai compliance)
                        │
                        ├──► Benefits Management (PVD, health)
                        │
                        └──► Accounting (inter-org advances)
```

### 6.2 External System Integration

**WebSocket (Laravel Reverb):**
- Real-time permission updates
- Bulk payroll progress tracking
- Cross-tab synchronization

**File Storage:**
- Employee profile pictures: `storage/app/public/profile_pictures`
- Document uploads: `storage/app/documents`
- Export files: `storage/app/exports`

**Queued Jobs:**
- `EmployeeActionNotification` - Async notifications
- `BulkPayrollJob` - Batch payroll processing
- `GrantImportJob` - Excel grant imports

---

## PART 7: User Roles and Permissions

### 7.1 Permission System

**Package:** `spatie/laravel-permission`

**Permission Format:** `{module}.{action}`
- Actions: `read`, `edit`
- Example: `employees.read`, `employees.edit`

### 7.2 Core System Roles

| Role | Description | Protected |
|------|-------------|-----------|
| `admin` | System Administrator - Full access | Yes |
| `hr-manager` | HR Manager - HR module access | Yes |

**Protected roles cannot be modified or deleted.**

### 7.3 Permission Modules (52 permissions)

Organized by submenu:
```
Dashboard: dashboard.read, dashboard.edit
Grants: grants_list.read/edit, grant_position.read/edit
Recruitment: interviews.read/edit, job_offers.read/edit
Employees: employees.read/edit, employment_records.read/edit, employee_resignation.read/edit
Leaves: leaves_admin.read/edit, leave_types.read/edit, leave_balances.read/edit
Travel: travel_admin.read/edit
Payroll: employee_salary.read/edit, tax_settings.read/edit, benefit_settings.read/edit, payslip.read/edit
Organization: sites.read/edit, departments.read/edit, positions.read/edit, section_departments.read/edit
User Management: users.read/edit, roles.read/edit
Reports: report_list.read/edit
Administration: file_uploads.read/edit, recycle_bin_list.read/edit
```

### 7.4 Permission Enforcement

**Backend Middleware:**
```php
Route::get('/employees', [EmployeeController::class, 'index'])
    ->middleware('permission:employees.read');
```

**Frontend Guards:**
```javascript
// router/guards.js
export const permissionGuard = (requiredPermission) => {
    return (to, from, next) => {
        const permissions = JSON.parse(localStorage.getItem('permissions') || '[]');
        if (permissions.includes(requiredPermission)) {
            next();
        } else {
            next('/unauthorized');
        }
    };
};
```

### 7.5 Real-Time Permission Synchronization

When admin updates a user's permissions:
1. Backend dispatches `UserPermissionsUpdated` event
2. Laravel Reverb broadcasts to user's private channel
3. Frontend receives event and refreshes permissions
4. All tabs synchronized via BroadcastChannel API

### 7.6 Audit and Compliance

**Activity Logging:**
- All CRUD operations logged
- Stores: user_id, action, model, old/new values, IP address
- Retention policy: Configurable

**Separation of Duties:**
- Payroll creation requires `employee_salary.edit`
- Payroll approval requires separate approval workflow (if configured)
- Tax settings isolated from payroll processing

---

## Appendix A: Complete API Endpoint Reference

### Employee Endpoints
| Method | Endpoint | Permission |
|--------|----------|------------|
| GET | `/api/v1/employees` | `employees.read` |
| GET | `/api/v1/employees/{id}` | `employees.read` |
| GET | `/api/v1/employees/tree-search` | `employees.read` |
| POST | `/api/v1/employees` | `employees.edit` |
| PUT | `/api/v1/employees/{id}` | `employees.edit` |
| DELETE | `/api/v1/employees/{id}` | `employees.edit` |
| POST | `/api/v1/employees/{id}/profile-picture` | `employees.edit` |

### Employment Endpoints
| Method | Endpoint | Permission |
|--------|----------|------------|
| GET | `/api/v1/employments` | `employment_records.read` |
| GET | `/api/v1/employments/{id}` | `employment_records.read` |
| POST | `/api/v1/employments` | `employment_records.edit` |
| PUT | `/api/v1/employments/{id}` | `employment_records.edit` |
| DELETE | `/api/v1/employments/{id}` | `employment_records.edit` |
| POST | `/api/v1/employments/calculate-allocation` | `employment_records.read` |
| POST | `/api/v1/employments/{id}/complete-probation` | `employment_records.edit` |

### Payroll Endpoints
| Method | Endpoint | Permission |
|--------|----------|------------|
| GET | `/api/v1/payrolls` | `employee_salary.read` |
| GET | `/api/v1/payrolls/{id}` | `employee_salary.read` |
| POST | `/api/v1/payrolls` | `employee_salary.edit` |
| PUT | `/api/v1/payrolls/{id}` | `employee_salary.edit` |
| DELETE | `/api/v1/payrolls/{id}` | `employee_salary.edit` |
| POST | `/api/v1/payrolls/calculate` | `employee_salary.read` |
| POST | `/api/v1/payrolls/bulk/preview` | `employee_salary.edit` |
| POST | `/api/v1/payrolls/bulk/create` | `employee_salary.edit` |
| GET | `/api/v1/payrolls/bulk/status/{batchId}` | `employee_salary.edit` |

### Grant Endpoints
| Method | Endpoint | Permission |
|--------|----------|------------|
| GET | `/api/v1/grants` | `grants_list.read` |
| GET | `/api/v1/grants/by-id/{id}` | `grants_list.read` |
| GET | `/api/v1/grants/items` | `grants_list.read` |
| POST | `/api/v1/grants` | `grants_list.edit` |
| PUT | `/api/v1/grants/{id}` | `grants_list.edit` |
| DELETE | `/api/v1/grants/{id}` | `grants_list.edit` |

---

## Appendix B: Improvement Suggestions

### 1. Performance Enhancements
- Implement Redis caching for frequently accessed grant structures
- Add database read replicas for reporting queries
- Implement query result pagination for large employee lists

### 2. Business Logic Improvements
- Add approval workflow for employment changes
- Implement salary change history tracking
- Add automated probation reminder notifications

### 3. Security Enhancements
- Implement field-level encryption for all PII
- Add two-factor authentication for payroll approvals
- Implement audit log tamper protection

### 4. User Experience
- Add bulk employee import with validation preview
- Implement payroll templates for recurring patterns
- Add dashboard widgets for pending approvals

### 5. Integration Opportunities
- External payroll system export (CSV/XML)
- Government tax filing integration
- Banking API for direct salary transfers

---

*Document generated: 2025-12-27*
*HRMS Version: 1.0*
*Laravel Version: 11.x*
*Vue Version: 3.x*
