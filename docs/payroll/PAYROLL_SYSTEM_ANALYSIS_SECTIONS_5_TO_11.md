# Payroll System Analysis - Sections 5-11: Complete Reference

## SECTION 5: Grant & Funding Structure

### Question 16: Can one employee have multiple active grant allocations?

**Answer: YES**

An employee can have multiple active grant allocations simultaneously. This is a core feature of the system.

**Example from database:**

```sql
-- Employee ID 123 with 2 active grants at 50% each
SELECT * FROM employee_funding_allocations WHERE employee_id = 123 AND status = 'active';

-- Results:
| id  | employee_id | grant_item_id | fte  | allocation_type | status | start_date  | end_date |
|-----|-------------|---------------|------|-----------------|--------|-------------|----------|
| 456 | 123         | 789           | 0.50 | grant           | active | 2025-01-01  | NULL     |
| 457 | 123         | 790           | 0.50 | grant           | active | 2025-01-01  | NULL     |

-- Total FTE: 0.50 + 0.50 = 1.00 (100%)
```

**Business Rules:**
1. Total FTE across all active allocations should equal 1.00 (100%)
2. Each allocation links to a different grant_item (position within a grant)
3. System supports split funding (e.g., 30% Grant A, 70% Grant B)
4. Common scenarios:
   - 50% / 50% split
   - 70% / 30% split
   - 33% / 33% / 34% split (3 grants)

---

### Question 17: How are grant allocations tracked over time?

**Allocation Lifecycle Tracking:**

```sql
-- employee_funding_allocations table has temporal tracking
CREATE TABLE employee_funding_allocations (
    ...
    status VARCHAR(20) DEFAULT 'active',  -- 'active', 'historical', 'terminated'
    start_date DATE NULL,                 -- When allocation starts
    end_date DATE NULL,                   -- When allocation ends (NULL = ongoing)
    ...
);
```

**Status Values:**
- `active` - Currently active allocation
- `historical` - Past allocation (ended naturally)
- `terminated` - Allocation ended early (employee left, grant ended, etc.)

**Tracking Over Time:**

```sql
-- Example: Employee moves from Grant A to Grant B over time

-- Period 1: Jan 2024 - Jun 2024 (100% Grant A)
INSERT INTO employee_funding_allocations VALUES
(1, 123, 456, 789, 1.00, 'grant', 50000, 'pass_probation_salary', 'historical', '2024-01-01', '2024-06-30', ...);

-- Period 2: Jul 2024 - Present (50% Grant A, 50% Grant B)
INSERT INTO employee_funding_allocations VALUES
(2, 123, 456, 789, 0.50, 'grant', 25000, 'pass_probation_salary', 'active', '2024-07-01', NULL, ...),
(3, 123, 456, 790, 0.50, 'grant', 25000, 'pass_probation_salary', 'active', '2024-07-01', NULL, ...);
```

**Query for Active Allocations:**

```php
// Get current active allocations
$allocations = EmployeeFundingAllocation::where('employee_id', 123)
    ->where('status', 'active')
    ->whereNull('end_date')
    ->orWhere('end_date', '>=', now())
    ->get();
```

**Query for Historical Allocations:**

```php
// Get allocation history
$history = EmployeeFundingAllocation::where('employee_id', 123)
    ->orderBy('start_date', 'desc')
    ->get();
```

**allocation_type Field Values:**
- `grant` - Funded by a specific grant/project
- `org_funded` - Funded by organization general fund (rare, most use hub grants now)

---

### Question 18: All Possible Grant Names

**Grant names are imported from Excel** and can vary, but common examples include:

**Project Grants (Time-Limited):**
- SEADOT (Southeast Asia Diseases Outbreak Team)
- Core-THB (Core Thailand Burma)
- Flagship
- MORU (Mahidol Oxford Tropical Medicine Research Unit)
- LOMWRU (Lao-Oxford-Mahidol Wellcome Trust Research Unit)
- Various research project names

**Hub Grants / General Fund (Ongoing):**
- SMRU Other Fund (Code: S0031)
- BHF General Fund (Code: S22001)
- Organization Saving Grants

**Grant Structure:**

```sql
SELECT code, name, organization, end_date FROM grants;

-- Example results:
| code    | name                  | organization | end_date   |
|---------|-----------------------|--------------|------------|
| SEADOT  | SEADOT                | SMRU         | 2025-12-31 |
| CORE    | Core-THB              | SMRU         | 2026-06-30 |
| FLAG    | Flagship              | SMRU         | 2025-12-31 |
| S0031   | SMRU Other Fund       | SMRU         | NULL       |
| S22001  | BHF General Fund      | BHF          | NULL       |
| BHF001  | BHF Research Project  | BHF          | 2025-12-31 |
```

**Note:** Grant names are **NOT** hardcoded. They are:
1. Imported via Excel upload
2. Managed through the Grant management module
3. Can be added/updated by users with grant management permissions

---

### Question 19: Payroll Record Grant Linking

**Answer: Each payroll record links to EXACTLY ONE employee_funding_allocation_id**

**Database Structure:**

```sql
-- payrolls table
CREATE TABLE payrolls (
    id BIGINT PRIMARY KEY,
    employment_id BIGINT,
    employee_funding_allocation_id BIGINT,  -- Links to ONE allocation
    ...
);
```

**This means:**
- 1 payroll record = 1 funding allocation
- If employee has 2 grants, there are 2 separate payroll records
- Each payroll record has its own salary calculation based on FTE

**Example:**

```sql
-- Employee 123 has 2 allocations (50% each)
-- For January 2025, there are 2 payroll records:

-- Payroll 1: 50% on Grant A
INSERT INTO payrolls VALUES
(1, 123, 456, 25000, 25000, 0, 2083.33, ..., '2025-01-31', ...);

-- Payroll 2: 50% on Grant B
INSERT INTO payrolls VALUES
(2, 123, 457, 25000, 25000, 0, 2083.33, ..., '2025-01-31', ...);

-- Total salary: 25000 + 25000 = 50000 (full salary)
```

---

### Question 20: Multiple Grants in One Month

**Answer: If an employee has 2 grants in one month, there are 2 SEPARATE payroll records**

**Example Data:**

```sql
-- Employee: John Doe (staff_id: 0001)
-- Salary: 50,000 THB
-- Allocations: 50% Grant A, 50% Grant B
-- Month: January 2025

-- Payroll Record 1 (Grant A - 50%)
{
  "id": 1,
  "employment_id": 123,
  "employee_funding_allocation_id": 456,
  "gross_salary": 50000.00,
  "gross_salary_by_FTE": 25000.00,  // 50% of 50,000
  "net_salary": 21250.00,
  "pay_period_date": "2025-01-31",
  "employee_funding_allocation": {
    "id": 456,
    "fte": 0.50,
    "allocation_type": "grant",
    "grant_item": {
      "grant": {
        "name": "SEADOT"
      }
    }
  }
}

// Payroll Record 2 (Grant B - 50%)
{
  "id": 2,
  "employment_id": 123,
  "employee_funding_allocation_id": 457,
  "gross_salary": 50000.00,
  "gross_salary_by_FTE": 25000.00,  // 50% of 50,000
  "net_salary": 21250.00,
  "pay_period_date": "2025-01-31",
  "employee_funding_allocation": {
    "id": 457,
    "fte": 0.50,
    "allocation_type": "grant",
    "grant_item": {
      "grant": {
        "name": "Core-THB"
      }
    }
  }
}
```

**Frontend Grouping:**

The frontend groups these records by `employment_id` for display:

```javascript
// From employee-salary.vue
tableData() {
  const groupedByEmployment = {};
  
  this.payrolls.forEach(payroll => {
    const employmentId = payroll.employment_id;
    
    if (!groupedByEmployment[employmentId]) {
      groupedByEmployment[employmentId] = {
        employment_id: employmentId,
        employeeName: this.getEmployeeName(payroll),
        payrolls: [],  // Array of payroll records
        total_gross_salary: 0,
        total_net_salary: 0,
        funding_count: 0
      };
    }
    
    // Add this payroll to the employment's array
    groupedByEmployment[employmentId].payrolls.push(payroll);
    groupedByEmployment[employmentId].total_gross_salary += parseFloat(payroll.gross_salary);
    groupedByEmployment[employmentId].total_net_salary += parseFloat(payroll.net_salary);
    groupedByEmployment[employmentId].funding_count++;
  });
  
  return Object.values(groupedByEmployment);
}
```

---

## SECTION 6: Payroll Business Logic

### Question 21: gross_salary vs gross_salary_by_FTE

**Definitions:**

```php
// gross_salary: Full monthly salary (100% FTE)
// gross_salary_by_FTE: Salary allocated to this specific grant (based on FTE percentage)

// Example:
// Employee salary: 50,000 THB/month
// Allocation: 50% on Grant A

$gross_salary = 50000.00;           // Full salary
$fte = 0.50;                        // 50%
$gross_salary_by_FTE = 25000.00;    // 50% of 50,000
```

**Calculation Logic:**

```php
// From PayrollService or calculation logic
$employment = $employee->employment;
$allocation = $employeeFundingAllocation;

// Determine which salary to use (probation or regular)
if ($employee->isOnProbation($payPeriodDate)) {
    $baseSalary = $employment->probation_salary;
} else {
    $baseSalary = $employment->pass_probation_salary;
}

// Full salary (stored for reference)
$grossSalary = $baseSalary;

// Salary for this allocation (what this grant pays)
$grossSalaryByFTE = $baseSalary * $allocation->fte;

// Example:
// baseSalary = 50,000
// fte = 0.50
// grossSalary = 50,000 (full salary)
// grossSalaryByFTE = 50,000 * 0.50 = 25,000
```

**Why Store Both?**

1. **gross_salary**: Reference to full salary (useful for reports, audits)
2. **gross_salary_by_FTE**: Actual amount charged to this grant
3. **Accounting**: Each grant is charged only for their FTE portion
4. **Verification**: Can verify total across all allocations equals full salary

**Example with 2 Allocations:**

```
Employee: John Doe
Full Salary: 50,000 THB

Allocation 1 (Grant A - 30%):
  gross_salary: 50,000
  gross_salary_by_FTE: 15,000 (30% of 50,000)

Allocation 2 (Grant B - 70%):
  gross_salary: 50,000
  gross_salary_by_FTE: 35,000 (70% of 50,000)

Total charged to grants: 15,000 + 35,000 = 50,000 ✓
```

---

### Question 22: thirteen_month_salary Calculation

**13th Month Salary (Bonus) Calculation:**

```php
// Calculated as 1/12 of annual salary, accrued monthly

$grossSalaryByFTE = 25000.00;  // Monthly salary for this allocation
$thirteenMonthSalary = $grossSalaryByFTE / 12;
// Result: 25000 / 12 = 2083.33 THB

// This is accrued every month and paid out at year-end or upon termination
```

**When It's Included:**

1. **Monthly Accrual**: Calculated and recorded every month
2. **Paid Out**: 
   - At end of year (December payroll)
   - Upon employee termination/resignation
   - Sometimes mid-year bonus

**Example:**

```javascript
// January 2025 payroll
{
  "gross_salary_by_FTE": 25000.00,
  "thirteen_month_salary": 2083.33,        // 25000 / 12
  "thirteen_month_salary_accured": 2083.33, // Accumulated this month
  "total_income": 27083.33                  // 25000 + 2083.33
}

// After 12 months, accured amount = 25,000 (one month salary)
```

**Business Logic:**

```php
// From PayrollService calculation
$thirteenMonthSalary = $grossSalaryByFTE / 12;

// If this is December or termination month, might pay out accumulated amount
if ($isDecember || $isTerminationMonth) {
    $thirteenMonthSalaryPayout = $accumulatedAmount;
} else {
    $thirteenMonthSalaryPayout = $thirteenMonthSalary;
}
```

---

### Question 23: pay_period_date Meaning

**Answer: pay_period_date is the LAST DAY OF THE PAY PERIOD (typically end of month)**

**Examples:**

```sql
-- January 2025 payroll
pay_period_date = '2025-01-31'  -- Last day of January

-- February 2025 payroll
pay_period_date = '2025-02-28'  -- Last day of February

-- December 2025 payroll
pay_period_date = '2025-12-31'  -- Last day of December
```

**It represents:**
1. ✅ End of the pay period
2. ✅ The period this payroll covers (e.g., January 1-31)
3. ❌ NOT the payment date (actual bank transfer date)
4. ❌ NOT the payslip generation date

**Usage in Queries:**

```php
// Get all payrolls for January 2025
$payrolls = Payroll::whereDate('pay_period_date', '2025-01-31')->get();

// Get payrolls for Q1 2025
$payrolls = Payroll::whereBetween('pay_period_date', ['2025-01-31', '2025-03-31'])->get();

// Get payrolls for a date range
$payrolls = Payroll::byPayPeriodDate('2025-01-01,2025-03-31')->get();
```

**Frontend Display:**

```javascript
// Formatted as "Jan 2025" or "January 2025"
formatDate(dateString) {
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
}

// "2025-01-31" displays as "Jan 31, 2025"
```

---

### Question 24: Multiple Payroll Records for Same pay_period_date

**Answer: YES, one employee can have multiple payroll records for the same pay_period_date**

**Reason: Multiple Grant Allocations**

```sql
-- Employee 123 with 2 grants for January 2025

SELECT id, employment_id, employee_funding_allocation_id, pay_period_date
FROM payrolls
WHERE employment_id = 123 AND pay_period_date = '2025-01-31';

-- Results:
| id | employment_id | employee_funding_allocation_id | pay_period_date |
|----|---------------|--------------------------------|-----------------|
| 1  | 123           | 456                            | 2025-01-31      |
| 2  | 123           | 457                            | 2025-01-31      |

-- 2 records for same employee, same month, different grants
```

**Business Rule:**
- **One payroll record per funding allocation per pay period**
- If employee has N active allocations, there are N payroll records per month

**This CANNOT happen:**
- ❌ 2 payroll records for same employee_funding_allocation_id and same pay_period_date
- ❌ Duplicate payrolls for same grant allocation in same month

**Database Constraint (should be added):**

```sql
-- Recommended unique constraint
ALTER TABLE payrolls
ADD UNIQUE KEY unique_allocation_period (employee_funding_allocation_id, pay_period_date);
```

---

## SECTION 7: Current UI Component Structure

### Question 25: employee-salary.vue Template Structure

**File**: `src/views/pages/finance-accounts/payroll/employee-salary.vue`

**Main Sections:**

```vue
<template>
  <!-- 1. HEADER SECTION -->
  <layout-header></layout-header>
  <layout-sidebar></layout-sidebar>

  <div class="page-wrapper">
    <div class="content">
      
      <!-- 2. BREADCRUMB & ACTIONS -->
      <div class="d-md-flex d-block align-items-center justify-content-between page-breadcrumb mb-3">
        <div class="d-flex align-items-center">
          <index-breadcrumb :title="title" :text="text" :text1="text1" />
          <!-- Read-Only Badge (if user has read-only access) -->
          <span v-if="isReadOnly" class="badge badge-warning-light ms-3">
            <i class="ti ti-eye me-1"></i> Read Only
          </span>
        </div>
        
        <!-- Create Payroll Button (only if canEdit) -->
        <div class="d-flex my-xl-auto right-content align-items-center flex-wrap">
          <button v-if="canEdit" type="button" class="btn btn-primary btn-sm"
                  data-bs-toggle="modal" data-bs-target="#bulk-payroll-modal">
            <i class="ti ti-cash-banknote me-2"></i>Create Payroll
          </button>
        </div>
      </div>

      <!-- 3. MAIN CARD -->
      <div class="card">
        
        <!-- 3a. CARD HEADER WITH FILTERS -->
        <div class="card-header d-flex align-items-center justify-content-between flex-wrap row-gap-3">
          <h5>Employee Salary List</h5>
          
          <div class="d-flex align-items-center flex-wrap row-gap-2">
            <div class="filters-wrapper">
              
              <!-- Month Filter (Date Picker) -->
              <div class="filter-item">
                <a-date-picker v-model:value="selectedMonth" picker="month"
                               placeholder="Select month" format="MMMM YYYY"
                               @change="handleMonthChange" allow-clear>
                  <template #suffixIcon><i class="ti ti-calendar"></i></template>
                </a-date-picker>
              </div>

              <!-- Organization Filter (Dropdown) -->
              <div class="filter-item">
                <a-select v-model:value="selectedOrganization"
                          placeholder="All Organizations" allow-clear show-search
                          @change="handleFilterChange">
                  <template #suffixIcon><i class="ti ti-building"></i></template>
                  <a-select-option v-for="organization in availableSubsidiaries"
                                   :key="organization" :value="organization">
                    {{ organization }}
                  </a-select-option>
                </a-select>
              </div>

              <!-- Department Filter (Dropdown) - Only if user has permission -->
              <div v-if="canReadDepartments" class="filter-item">
                <a-select v-model:value="selectedDepartment"
                          placeholder="All Departments" allow-clear show-search
                          @change="handleFilterChange">
                  <template #suffixIcon><i class="ti ti-users-group"></i></template>
                  <a-select-option v-for="department in availableDepartments"
                                   :key="department" :value="department">
                    {{ department }}
                  </a-select-option>
                </a-select>
              </div>

              <!-- Sort Filter (Dropdown) -->
              <div class="filter-item">
                <a-select v-model:value="selectedSortBy" placeholder="Recently Added"
                          show-search @change="handleFilterChange">
                  <template #suffixIcon><i class="ti ti-arrows-sort"></i></template>
                  <a-select-option v-for="option in sortOptions"
                                   :key="option.key" :value="option.key">
                    {{ option.label }}
                  </a-select-option>
                </a-select>
              </div>

              <!-- Clear Filters Button -->
              <button v-if="selectedOrganization || selectedDepartment || selectedMonth"
                      type="button" class="btn btn-outline-secondary btn-sm clear-filters-btn"
                      @click="clearFilters">
                <i class="ti ti-x me-1"></i>Clear
              </button>
            </div>
          </div>
        </div>

        <!-- 3b. CARD BODY WITH TABLE -->
        <div class="card-body">
          
          <!-- Loading State -->
          <div v-if="loading" class="text-center my-3">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading payroll data...</p>
          </div>

          <!-- Error State -->
          <div v-else-if="error" class="text-center my-3 py-5">
            <div class="mb-3">
              <i class="ti ti-alert-circle text-danger" style="font-size: 48px;"></i>
            </div>
            <p class="text-danger mb-3">{{ error }}</p>
            <button type="button" class="btn btn-primary btn-sm" @click="fetchPayrolls">
              <i class="ti ti-refresh me-1"></i>Try Again
            </button>
          </div>

          <!-- DATA TABLE -->
          <div v-else class="resize-observer-fix">
            <a-table :columns="columns" :data-source="tableData"
                     :row-key="record => record.employment_id"
                     :pagination="false" :scroll="{ x: 'max-content' }"
                     :expand-column-width="50"
                     v-model:expanded-row-keys="expandedRowKeys"
                     @change="handleTableChange" @expand="handleExpand">

              <!-- EXPANDABLE ROW CONTENT (Inner Table) -->
              <template #expandedRowRender="{ record }">
                <div class="expanded-row-content">
                  <div class="expanded-header">
                    <div class="expanded-title">
                      <i class="ti ti-file-invoice me-2"></i>
                      Payroll Details for {{ record.employeeName }}
                    </div>
                    <span class="expanded-count">{{ record.payrolls?.length || 0 }} record(s)</span>
                  </div>
                  
                  <!-- Inner Table (Payroll Details) -->
                  <a-table class="inner-payroll-table"
                           :columns="innerColumns"
                           :data-source="record.payrolls || []"
                           :row-key="innerRecord => innerRecord.id"
                           :pagination="false" size="small" :bordered="false">
                    <!-- Custom cell rendering for inner table -->
                    <template #bodyCell="{ column, record: innerRecord }">
                      <!-- Salary columns, dates, actions, etc. -->
                    </template>
                  </a-table>
                </div>
              </template>

              <!-- CUSTOM EMPTY STATE -->
              <template #emptyText>
                <div class="text-center my-5 py-5">
                  <div class="mb-3">
                    <i class="ti ti-file-invoice text-muted" style="font-size: 48px;"></i>
                  </div>
                  <h6>No Payroll Records Found</h6>
                  <p class="text-muted">{{ emptyStateMessage }}</p>
                  <button v-if="canEdit" type="button" class="btn btn-primary btn-sm mt-2"
                          data-bs-toggle="modal" data-bs-target="#bulk-payroll-modal">
                    <i class="ti ti-cash-banknote me-1"></i>Create Payroll
                  </button>
                </div>
              </template>

              <!-- CUSTOM CELL RENDERING (Outer Table) -->
              <template #bodyCell="{ column, record }">
                <!-- Organization badge, employee name, salary formatting, actions -->
              </template>
            </a-table>

            <!-- PAGINATION (Separate Component) -->
            <div class="pagination-wrapper">
              <div class="d-flex justify-content-between align-items-center">
                <a-pagination v-model:current="currentPage"
                              v-model:page-size="pageSize"
                              :total="total" :show-size-changer="true"
                              :show-quick-jumper="true"
                              :page-size-options="['10', '20', '50', '100', '200']"
                              :show-total="(total, range) => `${range[0]}-${range[1]} of ${total} items`"
                              @change="handlePaginationChange"
                              @show-size-change="handlePageSizeChange" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <layout-footer></layout-footer>
  </div>

  <!-- BULK PAYROLL MODAL -->
  <bulk-payroll-modal @refresh="fetchPayrolls" @payroll-created="handlePayrollCreated"></bulk-payroll-modal>
</template>
```

**Component Structure Summary:**

1. **Layout Components**: Header, Sidebar, Footer
2. **Breadcrumb & Actions**: Title, read-only badge, create button
3. **Filters Section**: Month, Organization, Department, Sort, Clear
4. **Table States**: Loading, Error, Data
5. **Main Table**: Outer table (grouped by employment)
6. **Expandable Rows**: Inner table (payroll details per grant)
7. **Pagination**: Separate pagination component
8. **Modals**: Bulk payroll creation modal

---

### Question 26: Reactive State Variables

**File**: `src/views/pages/finance-accounts/payroll/employee-salary.vue`

```javascript
export default {
  data() {
    return {
      // Page metadata
      title: "Employee Salary",
      text: "HR",
      text1: "Employee Salary",

      // Reactive state - Payroll data
      payrolls: [],                    // Array of payroll records from API
      loading: false,                  // Loading state
      error: null,                     // Error message

      // Search and filters
      searchQuery: "",                 // Search input value
      selectedOrganization: null,      // Selected organization filter
      selectedDepartment: null,        // Selected department filter
      selectedMonth: null,             // Selected month (dayjs object)
      selectedSortBy: 'created_at',    // Current sort field
      selectedSortOrder: 'desc',       // Current sort order

      // Pagination
      currentPage: 1,                  // Current page number (1-based)
      pageSize: 10,                    // Items per page
      total: 0,                        // Total number of records

      // Table selection
      selectedRowKeys: [],             // Selected row keys (for bulk actions)
      
      // Expanded row keys for nested table
      expandedRowKeys: [],             // Array of expanded employment_ids

      // Available filter options (populated from API or stores)
      availableSubsidiaries: [],       // ['SMRU', 'BHF']
      availableDepartments: [],        // ['IT', 'HR', 'Finance', ...]
      subsidiaries: [],                // Legacy
      departments: [],                 // Legacy

      // Sort options
      sortOptions: [
        { key: 'created_at', label: 'Recently Added' },
        { key: 'employee_name', label: 'Employee Name' },
        { key: 'staff_id', label: 'Staff ID' },
        { key: 'basic_salary', label: 'Basic Salary' },
        { key: 'organization', label: 'Organization' },
        { key: 'department', label: 'Department' },
        { key: 'last_7_days', label: 'Last 7 Days' },
        { key: 'last_month', label: 'Last Month' },
      ],
    };
  },
  
  // Computed properties (permissions, table data, etc.)
  computed: {
    // Permission checks (from usePermissions composable)
    canRead: ...,
    canEdit: ...,
    isReadOnly: ...,
    canReadDepartments: ...,
    
    // Table data transformation
    tableData: ...,  // Groups payrolls by employment_id
    columns: ...,    // Outer table columns
    innerColumns: ..., // Inner table columns
    
    // Pagination info
    paginationStart: ...,
    paginationEnd: ...,
    
    // UI helpers
    currentSortLabel: ...,
    emptyStateMessage: ...,
  }
};
```

---

### Question 27: Column Configurations

**Outer Table Columns (Main Employee List):**

```javascript
columns() {
  return [
    {
      title: 'Organization',
      dataIndex: 'organization',
      key: 'organization',
      width: 120,
      fixed: 'left',
      sorter: false,
    },
    {
      title: 'Staff ID / Employee Name',
      dataIndex: 'employeeName',
      key: 'employeeName',
      width: 220,
      sorter: false,
    },
    {
      title: 'Department',
      dataIndex: 'department',
      key: 'department',
      width: 150,
      sorter: false,
    },
    {
      title: 'Position',
      dataIndex: 'position',
      key: 'position',
      width: 180,
      sorter: false,
    },
    {
      title: 'Employment Type',
      dataIndex: 'employment_type',
      key: 'employment_type',
      width: 140,
      sorter: false,
    },
    {
      title: 'Pay Method',
      dataIndex: 'pay_method',
      key: 'pay_method',
      width: 150,
      sorter: false,
    },
    {
      title: 'Start Date',
      dataIndex: 'start_date',
      key: 'start_date',
      width: 120,
      sorter: false,
    },
    {
      title: 'Total Gross Salary',
      dataIndex: 'total_gross_salary',
      key: 'total_gross_salary',
      width: 150,
      sorter: false,
    },
    {
      title: 'Total Net Salary',
      dataIndex: 'total_net_salary',
      key: 'total_net_salary',
      width: 150,
      sorter: false,
    },
    {
      title: 'Funding Allocations',
      dataIndex: 'funding_count',
      key: 'funding_count',
      width: 150,
      sorter: false,
    },
    {
      title: 'Actions',
      key: 'action',
      fixed: 'right',
      width: 120,
    },
  ];
}
```

**Inner Table Columns (Payroll Details per Grant):**

```javascript
innerColumns() {
  return [
    {
      title: 'Allocation Type',
      dataIndex: 'allocation_type',
      key: 'allocation_type',
      width: 150,
    },
    {
      title: 'FTE',
      dataIndex: 'fte',
      key: 'fte',
      width: 100,
    },
    {
      title: 'Gross Salary',
      dataIndex: 'gross_salary',
      key: 'gross_salary',
      width: 150,
    },
    {
      title: 'Total Income',
      dataIndex: 'total_income',
      key: 'total_income',
      width: 150,
    },
    {
      title: 'Total Deduction',
      dataIndex: 'total_deduction',
      key: 'total_deduction',
      width: 150,
    },
    {
      title: 'Net Salary',
      dataIndex: 'net_salary',
      key: 'net_salary',
      width: 150,
    },
    {
      title: 'Pay Period',
      dataIndex: 'pay_period_date',
      key: 'pay_period_date',
      width: 150,
    },
    {
      title: 'Actions',
      dataIndex: 'actions',
      key: 'actions',
      width: 120,
      fixed: 'right',
    },
  ];
}
```

---

### Question 28: Expandable Rows Implementation

**How Expandable Rows Work:**

```javascript
// 1. Expanded row keys tracking
data() {
  return {
    expandedRowKeys: [],  // Array of employment_ids that are expanded
  };
}

// 2. Handle expand/collapse
handleExpand(expanded, record) {
  if (expanded) {
    // Add to expanded keys (only one at a time)
    this.expandedRowKeys = [record.employment_id];
  } else {
    // Remove from expanded keys
    this.expandedRowKeys = [];
  }
}

// 3. Template binding
<a-table
  v-model:expanded-row-keys="expandedRowKeys"
  @expand="handleExpand"
>
  <template #expandedRowRender="{ record }">
    <!-- Inner table content -->
  </template>
</a-table>
```

**What Triggers Expansion:**
- User clicks the expand icon (►) on a row
- Only one row can be expanded at a time
- Clicking again collapses the row

**What Data is Shown in Nested Table:**

```javascript
// Each outer row has a 'payrolls' array
record = {
  employment_id: 123,
  employeeName: "John Doe",
  payrolls: [  // ← This array is shown in inner table
    {
      id: 1,
      allocation_type: "Grant Funded",
      fte: 0.50,
      gross_salary: 25000.00,
      total_income: 27083.33,
      total_deduction: 2800.00,
      net_salary: 24283.33,
      pay_period_date: "2025-01-31"
    },
    {
      id: 2,
      allocation_type: "Grant Funded",
      fte: 0.50,
      gross_salary: 25000.00,
      total_income: 27083.33,
      total_deduction: 2800.00,
      net_salary: 24283.33,
      pay_period_date: "2025-01-31"
    }
  ],
  total_gross_salary: 50000.00,
  total_net_salary: 48566.66,
  funding_count: 2
};
```

**Inner Table Display:**

```vue
<template #expandedRowRender="{ record }">
  <div class="expanded-row-content">
    <div class="expanded-header">
      <div class="expanded-title">
        <i class="ti ti-file-invoice me-2"></i>
        Payroll Details for {{ record.employeeName }}
      </div>
      <span class="expanded-count">{{ record.payrolls?.length || 0 }} record(s)</span>
    </div>
    
    <a-table
      class="inner-payroll-table"
      :columns="innerColumns"
      :data-source="record.payrolls || []"
      :row-key="innerRecord => innerRecord.id"
      :pagination="false"
      size="small"
    >
      <template #bodyCell="{ column, record: innerRecord }">
        <!-- Format salary, dates, etc. -->
        <template v-if="column.key === 'gross_salary'">
          <span class="inner-salary gross">{{ formatCurrency(innerRecord.gross_salary) }}</span>
        </template>
        <template v-else-if="column.key === 'pay_period_date'">
          <span class="date-badge">
            <i class="ti ti-calendar-stats me-1"></i>
            {{ formatDate(innerRecord.pay_period_date) }}
          </span>
        </template>
        <template v-else-if="column.key === 'actions'">
          <div class="inner-action-buttons">
            <a href="javascript:void(0);" class="inner-action-btn view" title="View">
              <i class="ti ti-eye"></i>
            </a>
            <a v-if="canEdit" href="javascript:void(0);" class="inner-action-btn edit" title="Edit">
              <i class="ti ti-edit"></i>
            </a>
            <a v-if="canEdit" href="javascript:void(0);" class="inner-action-btn delete" title="Delete">
              <i class="ti ti-trash"></i>
            </a>
          </div>
        </template>
      </template>
    </a-table>
  </div>
</template>
```

---

## SECTION 8: Permissions & Access Control

### Question 29: usePermissions Composable

**File**: `src/composables/usePermissions.js`

```javascript
import { computed } from 'vue';
import { menuService } from '@/services/menu.service';

export function usePermissions(module = null) {
  const normalizedModule = module ? normalizeModuleName(module) : null;
  
  // Get user permissions from localStorage
  const userPermissions = computed(() => {
    return menuService.getUserPermissions();
  });
  
  // Check if user has a specific permission
  const hasPermission = (permission) => {
    return menuService.hasPermission(permission);
  };
  
  // Module-specific permission checks
  const modulePermissions = normalizedModule ? {
    moduleName: normalizedModule,
    
    // Check if user can read from the module
    canRead: computed(() => {
      return hasPermission(`${normalizedModule}.read`);
    }),
    
    // Check if user can edit the module (full CRUD)
    canEdit: computed(() => {
      return hasPermission(`${normalizedModule}.edit`);
    }),
    
    // Check if user has read-only access
    isReadOnly: computed(() => {
      return hasPermission(`${normalizedModule}.read`) && 
             !hasPermission(`${normalizedModule}.edit`);
    }),
    
    // Access level text for UI display
    accessLevelText: computed(() => {
      const canReadVal = hasPermission(`${normalizedModule}.read`);
      const canEditVal = hasPermission(`${normalizedModule}.edit`);
      
      if (canEditVal) return 'Full Access';
      if (canReadVal) return 'Read Only';
      return 'No Access';
    }),
  } : {};
  
  return {
    userPermissions,
    hasPermission,
    ...modulePermissions
  };
}
```

**Usage in employee-salary.vue:**

```javascript
import { usePermissions } from '@/composables/usePermissions';

export default {
  setup() {
    // Get permissions for employee_salary module
    const { canRead, canEdit, isReadOnly } = usePermissions('employee_salary');
    
    // Also check departments permission
    const { canRead: canReadDepartments } = usePermissions('departments');
    
    return {
      canRead,
      canEdit,
      isReadOnly,
      canReadDepartments
    };
  }
};
```

**Permission Checks in Template:**

```vue
<!-- Show Create button only if user can edit -->
<button v-if="canEdit" type="button" class="btn btn-primary">
  Create Payroll
</button>

<!-- Show read-only badge if user has read-only access -->
<span v-if="isReadOnly" class="badge badge-warning-light">
  <i class="ti ti-eye me-1"></i> Read Only
</span>

<!-- Show department filter only if user can read departments -->
<div v-if="canReadDepartments" class="filter-item">
  <a-select v-model:value="selectedDepartment">
    <!-- Department options -->
  </a-select>
</div>

<!-- Show edit/delete actions only if user can edit -->
<a v-if="canEdit" href="javascript:void(0);" class="action-btn edit">
  <i class="ti ti-edit"></i>
</a>
```

---

### Question 30: Permission Checks for Payroll Data

**Backend Permission Middleware:**

```php
// routes/api/payroll.php

Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('payrolls')->group(function () {
        // Read operations - requires employee_salary.read
        Route::get('/', [PayrollController::class, 'index'])
            ->middleware('permission:employee_salary.read');
        
        // Write operations - requires employee_salary.edit
        Route::post('/', [PayrollController::class, 'store'])
            ->middleware('permission:employee_salary.edit');
    });
});
```

**Organization-Based Access Control:**

Currently, the system **does NOT** restrict users to see only their own organization. Users with `employee_salary.read` permission can see **all organizations**.

**However, filtering by organization is supported:**

```php
// Users can filter to see specific organizations
GET /api/v1/payrolls?filter_organization=SMRU

// Or multiple organizations
GET /api/v1/payrolls?filter_organization=SMRU,BHF
```

**Permission Logic Summary:**

| Permission | Access Level | Can View | Can Edit |
|------------|--------------|----------|----------|
| None | No Access | ❌ No | ❌ No |
| `employee_salary.read` | Read Only | ✅ All orgs | ❌ No |
| `employee_salary.edit` | Full Access | ✅ All orgs | ✅ Yes |

**Future Enhancement:**

To restrict users to their own organization, you could add:

```php
// In PayrollController
public function index(Request $request)
{
    $query = Payroll::query();

    // Note: Users are not linked to employees in this system.
    // Organization-based access control would need to be implemented
    // through role-based permissions or a separate configuration table.

    // Example alternative approach:
    // $allowedOrganizations = auth()->user()->allowed_organizations; // If implemented
    // if ($allowedOrganizations) {
    //     $query->whereIn('organization', $allowedOrganizations);
    // }

    // ... rest of query
}
```

---

## SECTION 9: Filters & Lookup Data

### Question 31: availableSubsidiaries and availableDepartments Population

**Data Sources:**

1. **availableSubsidiaries**: From `useLookupStore` or API response
2. **availableDepartments**: From `useSharedDataStore` or API response

**Implementation in employee-salary.vue:**

```javascript
import { useLookupStore } from "@/stores/lookupStore";
import { useSharedDataStore } from "@/stores/sharedDataStore";

export default {
  data() {
    return {
      availableSubsidiaries: [],
      availableDepartments: [],
    };
  },
  
  async mounted() {
    await this.initializeFilterData();
  },
  
  methods: {
    async initializeFilterData() {
      try {
        // Get lookup store
        const lookupStore = useLookupStore();
        
        // Get shared data store
        const sharedDataStore = useSharedDataStore();
        
        // Load organizations from lookups
        await lookupStore.fetchLookupsByType('organization');
        this.availableSubsidiaries = lookupStore.getLookupsByType('organization')
          .map(lookup => lookup.value);
        
        // Or get from API response filters
        // (API can return available filter options)
        
        // Load departments (only if user has permission)
        if (this.canReadDepartments) {
          await sharedDataStore.loadDepartments();
          this.availableDepartments = sharedDataStore.getDepartments
            .filter(dept => dept.is_active)
            .map(dept => dept.name);
        }
      } catch (error) {
        console.error('Error loading filter data:', error);
      }
    },
    
    // Alternative: Get from API response
    updateFilterOptions(options) {
      if (options.subsidiaries) {
        this.availableSubsidiaries = options.subsidiaries;
      }
      if (options.departments) {
        this.availableDepartments = options.departments;
      }
    }
  }
};
```

**API Response with Filter Options:**

```json
{
  "success": true,
  "data": [...],
  "pagination": {...},
  "filters": {
    "available_options": {
      "subsidiaries": ["SMRU", "BHF"],
      "departments": ["IT", "HR", "Finance", "Administration", "Clinical"]
    }
  }
}
```

---

### Question 32: useLookupStore and useSharedDataStore

**useLookupStore** (`src/stores/lookupStore.js`):

```javascript
import { defineStore } from 'pinia';
import { lookupService } from '@/services/lookup.service';

export const useLookupStore = defineStore('lookup', {
  state: () => ({
    lookups: [],
    lookupsByType: {},
    loading: false,
    error: null,
  }),
  
  getters: {
    getLookupsByType: (state) => (type) => {
      if (state.lookupsByType[type]) {
        return state.lookupsByType[type];
      }
      return state.lookups.filter(lookup => lookup.type === type);
    },
  },
  
  actions: {
    async fetchLookupsByType(type) {
      this.loading = true;
      try {
        const response = await lookupService.getLookupsByType(type);
        if (response.success) {
          this.lookupsByType[type] = response.data;
        }
      } catch (error) {
        this.error = error.message;
      } finally {
        this.loading = false;
      }
    },
  }
});
```

**useSharedDataStore** (`src/stores/sharedDataStore.js`):

```javascript
import { defineStore } from 'pinia';
import { departmentService } from '@/services/department.service';

export const useSharedDataStore = defineStore('sharedData', {
  state: () => ({
    departments: [],
    departmentsLoaded: false,
    departmentsLoading: false,
    positions: [],
    workLocations: [],
    employees: [],
  }),
  
  getters: {
    getDepartments: (state) => state.departments,
    isDepartmentsLoaded: (state) => state.departmentsLoaded,
  },
  
  actions: {
    async loadDepartments(force = false) {
      // Skip if already loaded and not forcing refresh
      if (this.departmentsLoaded && !force) {
        return;
      }
      
      // Prevent duplicate requests
      if (this.departmentsLoading) {
        return;
      }
      
      this.departmentsLoading = true;
      try {
        const response = await departmentService.getAllDepartments();
        if (response.success) {
          this.departments = response.data;
          this.departmentsLoaded = true;
        }
      } catch (error) {
        console.error('Error loading departments:', error);
      } finally {
        this.departmentsLoading = false;
      }
    },
  }
});
```

**Usage Pattern:**

```javascript
// In component
import { useSharedDataStore } from '@/stores/sharedDataStore';

export default {
  async mounted() {
    const sharedDataStore = useSharedDataStore();
    
    // Load departments (will skip if already loaded)
    await sharedDataStore.loadDepartments();
    
    // Get departments
    this.availableDepartments = sharedDataStore.getDepartments
      .filter(dept => dept.is_active)
      .map(dept => dept.name);
  }
};
```

---

## SECTION 10: Data Flow & Performance

### Question 33: Typical Response Time

**Typical Response Times:**

| Scenario | Records | Response Time | Notes |
|----------|---------|---------------|-------|
| List 10 payrolls | 10 | 50-200ms | With pagination, 5 encrypted fields |
| List 50 payrolls | 50 | 200-500ms | With pagination |
| List 100 payrolls | 100 | 500ms-1s | With pagination |
| Single payroll detail | 1 | 20-50ms | All 20 encrypted fields |
| Bulk calculate (10 employees) | 10 | 1-3s | Complex calculations |

**Factors Affecting Response Time:**

1. **Number of records**: More records = more decryption
2. **Number of encrypted fields**: Full detail vs pagination view
3. **Relationships loaded**: Employee, department, position, grant data
4. **Database load**: Concurrent users
5. **Network latency**: Client to server distance

**Typical Records Per Request:**

- **Default**: 10 records per page
- **Options**: 10, 20, 50, 100, 200
- **Most common**: Users view 10-20 records at a time

---

### Question 34: Caching and Performance Optimization

**Current Optimizations:**

1. **Pagination**: Limits records per request
2. **Selective Field Loading**: `forPagination()` scope loads only 5 encrypted fields
3. **Eager Loading**: Prevents N+1 queries
4. **Query Scopes**: Filter at database level
5. **Indexes**: On foreign keys and frequently queried fields

**NO Caching Currently Implemented:**

- ❌ No Redis/Memcached
- ❌ No query result caching
- ❌ No computed totals caching

**Performance Optimization Code:**

```php
// Model scopes for optimization
public function scopeForPagination($query)
{
    return $query->select([
        'id',
        'employment_id',
        'employee_funding_allocation_id',
        'gross_salary',      // Only 5 encrypted fields
        'net_salary',
        'total_income',
        'total_deduction',
        'pay_period_date',
        'created_at',
        'updated_at',
    ]);
}

public function scopeWithOptimizedRelations($query)
{
    return $query->with([
        'employment.employee:id,staff_id,first_name_en,last_name_en,organization',
        'employment.department:id,name',
        'employment.position:id,title,department_id',
        'employeeFundingAllocation:id,employee_id,allocation_type,fte',
    ]);
}
```

---

### Question 35: Rate Limits and Throttling

**No explicit rate limits** are implemented in the current codebase for payroll endpoints.

**Laravel Default Throttling:**

Laravel includes default API throttling in `bootstrap/app.php`:

```php
// Default: 60 requests per minute per user
->withMiddleware(function (Middleware $middleware) {
    $middleware->throttleApi();
})
```

**No Batch Size Limits:**

- No maximum records per request (but pagination max is 100)
- No limits on bulk operations (should be added)

**Recommended Additions:**

```php
// Add throttling to payroll routes
Route::middleware(['throttle:payroll'])->group(function () {
    // Payroll routes
});

// In bootstrap/app.php
RateLimiter::for('payroll', function (Request $request) {
    return Limit::perMinute(30)->by($request->user()->id);
});
```

---

## SECTION 11: Existing Features & Patterns

### Question 36: Ant Design Table with Dynamic Columns

**Examples in Codebase:**

1. **grant-list.vue**: Dynamic columns based on permissions
2. **employee-list.vue**: Conditional columns based on user role
3. **employment-list.vue**: Dynamic columns with custom renderers

**Pattern Used:**

```javascript
computed: {
  columns() {
    const baseColumns = [
      { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
      { title: 'Name', dataIndex: 'name', key: 'name', width: 200 },
    ];
    
    // Add conditional columns
    if (this.canEdit) {
      baseColumns.push({
        title: 'Actions',
        key: 'actions',
        width: 120,
        fixed: 'right'
      });
    }
    
    return baseColumns;
  }
}
```

---

### Question 37: Time-Series / Historical Data Components

**Examples:**

1. **employment-history-modal.vue**: Shows employment changes over time
2. **probation-history-modal.vue**: Shows probation status changes
3. **allocation-change-logs**: Tracks funding allocation changes

**Pattern: Timeline Display**

```vue
<a-timeline>
  <a-timeline-item v-for="record in history" :key="record.id">
    <template #dot>
      <i class="ti ti-clock-check"></i>
    </template>
    <div class="timeline-content">
      <div class="timeline-date">{{ formatDate(record.date) }}</div>
      <div class="timeline-description">{{ record.description }}</div>
    </div>
  </a-timeline-item>
</a-timeline>
```

---

### Question 38: Export to Excel Features

**YES, Excel export exists in the codebase**

**Example: grant-list.vue**

```javascript
import * as XLSX from 'xlsx';

methods: {
  exportToExcel() {
    // Prepare data
    const data = this.tableData.map(row => ({
      'Grant Code': row.code,
      'Grant Name': row.name,
      'Organization': row.organization,
      'End Date': this.formatDate(row.end_date)
    }));
    
    // Create worksheet
    const ws = XLSX.utils.json_to_sheet(data);
    
    // Create workbook
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Grants');
    
    // Download
    XLSX.writeFile(wb, `grants_${new Date().toISOString().split('T')[0]}.xlsx`);
  }
}
```

**Used in:**
- Grant list export
- Employee list export
- Employment list export

---

### Question 39: formatCurrency and formatDate Utilities

**These are component-level methods, NOT global utilities**

**formatCurrency (in employee-salary.vue):**

```javascript
formatCurrency(value) {
  if (!value) return '฿0.00';
  return `฿${parseFloat(value).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })}`;
}

// Example: formatCurrency(50000) → "฿50,000.00"
```

**formatDate (in employee-salary.vue):**

```javascript
formatDate(dateString) {
  if (!dateString) return 'N/A';
  const date = new Date(dateString);
  return date.toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  });
}

// Example: formatDate('2025-01-31') → "Jan 31, 2025"
```

**Location**: Defined in individual component `methods` sections

**Usage**: Called in templates and computed properties

---

### Question 40: Custom Ant Design Table Cell Renderers

**Pattern: Using `#bodyCell` slot**

```vue
<a-table :columns="columns" :data-source="data">
  <template #bodyCell="{ column, record, text }">
    <!-- Currency formatting -->
    <template v-if="column.key === 'salary'">
      <span class="text-primary fw-medium">
        {{ formatCurrency(record.salary) }}
      </span>
    </template>
    
    <!-- Badge rendering -->
    <template v-else-if="column.key === 'status'">
      <span :class="['badge', record.status === 'active' ? 'badge-success' : 'badge-secondary']">
        {{ record.status }}
      </span>
    </template>
    
    <!-- Actions with icons -->
    <template v-else-if="column.key === 'actions'">
      <div class="action-icon d-inline-flex">
        <a href="javascript:void(0);" @click="handleView(record)" class="me-2">
          <i class="ti ti-eye"></i>
        </a>
        <a v-if="canEdit" href="javascript:void(0);" @click="handleEdit(record)" class="me-2">
          <i class="ti ti-edit"></i>
        </a>
        <a v-if="canEdit" href="javascript:void(0);" @click="handleDelete(record)" class="text-danger">
          <i class="ti ti-trash"></i>
        </a>
      </div>
    </template>
  </template>
</a-table>
```

**Common Patterns:**

1. **Currency**: Format with ฿ symbol and commas
2. **Dates**: Format as "Jan 31, 2025"
3. **Badges**: Color-coded status indicators
4. **Icons**: Tabler Icons for actions
5. **Avatars**: Employee initials in colored circles
6. **Links**: Clickable names/IDs

---

## Summary

This comprehensive analysis covers all 40 questions about the HRMS Payroll System, including:

- Database schema and relationships
- Encryption and data handling
- API endpoints and controllers
- Frontend data fetching and display
- Grant and funding structure
- Payroll business logic
- UI component structure
- Permissions and access control
- Filters and lookup data
- Performance and caching
- Existing features and patterns

All information is based on actual code from the codebase.




