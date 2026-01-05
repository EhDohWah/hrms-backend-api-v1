# Complete Payroll Management System Documentation

## Table of Contents
1. [System Overview](#system-overview)
2. [Architecture & Components](#architecture--components)
3. [Core Features](#core-features)
4. [Database Schema](#database-schema)
5. [API Endpoints](#api-endpoints)
6. [Tax Calculation System](#tax-calculation-system)
7. [Multi-Source Funding](#multi-source-funding)
8. [Inter-Subsidiary Advances](#inter-subsidiary-advances)
9. [Security & Encryption](#security--encryption)
10. [Workflow & Business Logic](#workflow--business-logic)
11. [Integration Points](#integration-points)
12. [Performance & Caching](#performance--caching)
13. [Validation & Error Handling](#validation--error-handling)
14. [Reporting & Analytics](#reporting--analytics)
15. [Deployment & Configuration](#deployment--configuration)

---

## System Overview

The HRMS Payroll Management System is a comprehensive, Thai Revenue Department-compliant payroll processing solution designed for multi-subsidiary organizations with complex funding structures. The system handles employee salary calculations, tax computations, social security contributions, provident fund management, and automatic inter-subsidiary financial transfers.

### Key Capabilities
- **Multi-Source Funding**: Employees can be funded by multiple grants and organizational sources
- **Thai Tax Compliance**: Full compliance with Thai Revenue Department regulations (2025)
- **Automated Calculations**: 13 distinct payroll calculation components
- **Inter-Subsidiary Advances**: Automatic detection and creation of cross-subsidiary transfers
- **Data Security**: End-to-end encryption for all salary and financial data
- **Audit Trail**: Complete tracking of all payroll transactions and changes

### Business Context
The system serves organizations with:
- Multiple subsidiaries (e.g., SMRU, BHF, MORU)
- Grant-funded research projects
- Mixed funding sources (grants + organizational funds)
- Thai employment law requirements
- Complex salary structures (probation, FTE, LOE)

---

## Architecture & Components

### System Architecture
```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Frontend      │    │   API Layer     │    │   Services      │
│   (Vue.js)      │────│   Controllers   │────│   Business      │
│                 │    │   Resources     │    │   Logic         │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         │                       │                       │
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Validation    │    │   Models &      │    │   Database      │
│   & Requests    │────│   Relationships │────│   (Encrypted)   │
│                 │    │                 │    │                 │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

### Core Components

#### 1. Models
- **`Payroll`**: Main payroll record with 13 calculation fields
- **`TaxBracket`**: Progressive tax brackets (8 levels: 0% to 35%)
- **`TaxSetting`**: Configurable tax settings and allowances
- **`InterSubsidiaryAdvance`**: Cross-subsidiary financial transfers
- **`EmployeeFundingAllocation`**: Multi-source funding assignments
- **`Employment`**: Employee employment details and salary information

#### 2. Services
- **`PayrollService`**: Core payroll processing and calculations
- **`TaxCalculationService`**: Thai-compliant tax computations
- **`FundingAllocationService`**: Multi-source funding management
- **`CacheManagerService`**: Performance optimization and caching

#### 3. Controllers
- **`PayrollController`**: 10 endpoints for payroll management
- **`TaxCalculationController`**: Tax calculation and compliance
- **`TaxBracketController`**: Tax bracket management
- **`TaxSettingController`**: Tax setting configuration
- **`InterSubsidiaryAdvanceController`**: Advance management

---

## Core Features

### 1. Comprehensive Payroll Calculation (13 Components)

The system calculates 13 distinct payroll components for each funding allocation:

1. **Gross Salary**: Base position salary
2. **Gross Salary by FTE**: Adjusted for Full-Time Equivalent and Level of Effort
3. **Compensation/Refund**: Mid-month start adjustments
4. **13th Month Salary**: Annual bonus calculation (eligible after 6 months)
5. **PVD (Provident Fund)**: Employee contribution (2-15% configurable)
6. **Saving Fund**: Additional employee savings
7. **Employer Social Security**: 5% employer contribution (capped at ฿750/month)
8. **Employee Social Security**: 5% employee contribution (capped at ฿750/month)
9. **Employer Health Welfare**: Tiered health benefits
10. **Employee Health Welfare**: Employee health contributions
11. **Income Tax**: Progressive tax calculation (8 brackets)
12. **Net Salary**: Final take-home amount
13. **Total Salary**: Comprehensive salary including all components

### 2. Advanced Salary Calculations

#### Probation Period Handling
```php
// Mid-month probation transition example
if (probation_ends_mid_month) {
    probation_days = days_before_probation_end;
    position_days = days_after_probation_end;
    
    monthly_salary = (probation_salary * probation_days + pass_probation_salary * position_days) / total_days;
}
```

#### Annual Salary Increase
- Automatic 1% increase after 365 working days
- Pro-rated application based on employment duration
- Configurable increase percentage and timing

#### FTE and LOE Application
```php
final_salary = base_salary * fte_percentage * level_of_effort_percentage;
```

### 3. Multi-Source Funding System

#### Funding Allocation Types
1. **Grant Funding**: Research grants with specific budget allocations
2. **Organizational Funding**: General organizational budget
3. **Mixed Funding**: Combination of grants and organizational funds

#### Level of Effort (LOE) Management
- Must total 100% across all allocations
- Supports decimal precision (e.g., 33.33%, 66.67%)
- Automatic validation and error detection
- Real-time allocation tracking

#### Example Funding Scenario
```json
{
  "employee": "John Doe (SMRU)",
  "pass_probation_salary": 50000,
  "funding_allocations": [
    {
      "source": "Research Grant (BHF)",
      "type": "grant",
      "loe": 60,
      "amount": 30000
    },
    {
      "source": "General Fund (SMRU)", 
      "type": "organization",
      "loe": 40,
      "amount": 20000
    }
  ],
  "total_loe": 100,
  "total_amount": 50000
}
```

---

## Database Schema

### Core Tables

#### 1. Payrolls Table
```sql
CREATE TABLE payrolls (
    id BIGINT PRIMARY KEY,
    employment_id BIGINT FOREIGN KEY,
    employee_funding_allocation_id BIGINT FOREIGN KEY,
    
    -- Encrypted salary fields (13 components)
    gross_salary TEXT ENCRYPTED,
    gross_salary_by_FTE TEXT ENCRYPTED,
    compensation_refund TEXT ENCRYPTED,
    thirteen_month_salary TEXT ENCRYPTED,
    thirteen_month_salary_accured TEXT ENCRYPTED,
    pvd TEXT ENCRYPTED,
    saving_fund TEXT ENCRYPTED,
    employer_social_security TEXT ENCRYPTED,
    employee_social_security TEXT ENCRYPTED,
    employer_health_welfare TEXT ENCRYPTED,
    employee_health_welfare TEXT ENCRYPTED,
    tax TEXT ENCRYPTED,
    net_salary TEXT ENCRYPTED,
    total_salary TEXT ENCRYPTED,
    total_pvd TEXT ENCRYPTED,
    total_saving_fund TEXT ENCRYPTED,
    salary_bonus TEXT ENCRYPTED,
    total_income TEXT ENCRYPTED,
    employer_contribution TEXT ENCRYPTED,
    total_deduction TEXT ENCRYPTED,
    
    -- Metadata
    notes TEXT,
    pay_period_date DATE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 2. Tax Brackets Table
```sql
CREATE TABLE tax_brackets (
    id BIGINT PRIMARY KEY,
    min_income DECIMAL(15,2),
    max_income DECIMAL(15,2) NULL,
    tax_rate DECIMAL(5,2),
    bracket_order INTEGER,
    effective_year INTEGER,
    is_active BOOLEAN,
    description TEXT,
    created_by VARCHAR(100),
    updated_by VARCHAR(100),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 3. Tax Settings Table
```sql
CREATE TABLE tax_settings (
    id BIGINT PRIMARY KEY,
    setting_key VARCHAR(100),
    setting_value DECIMAL(15,2),
    setting_type ENUM('DEDUCTION', 'RATE', 'LIMIT', 'ALLOWANCE'),
    description TEXT,
    effective_year INTEGER,
    is_selected BOOLEAN,
    created_by VARCHAR(100),
    updated_by VARCHAR(100),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### 4. Inter-Subsidiary Advances Table
```sql
CREATE TABLE inter_subsidiary_advances (
    id BIGINT PRIMARY KEY,
    payroll_id BIGINT FOREIGN KEY,
    from_subsidiary VARCHAR(5),
    to_subsidiary VARCHAR(5),
    via_grant_id BIGINT FOREIGN KEY,
    amount DECIMAL(18,2),
    advance_date DATE,
    notes VARCHAR(255),
    settlement_date DATE NULL,
    created_by VARCHAR(100),
    updated_by VARCHAR(100),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Relationships
```
Employee (1) ──→ (1) Employment ──→ (*) Payroll
Employee (1) ──→ (*) EmployeeFundingAllocation ──→ (1) Payroll
Payroll (1) ──→ (0..1) InterSubsidiaryAdvance
Grant (1) ──→ (*) InterSubsidiaryAdvance
TaxBracket (*) ──→ (1) TaxYear
TaxSetting (*) ──→ (1) TaxYear
```

---

## API Endpoints

### Payroll Management Endpoints

#### 1. List Payrolls
```http
GET /api/payrolls
```
**Features:**
- Pagination (configurable per_page)
- Multi-field filtering (subsidiary, department, date range)
- Search by employee details (staff_id, name)
- Sorting by multiple fields
- Performance-optimized queries

**Query Parameters:**
```
?page=1
&per_page=10
&search=EMP001
&filter_subsidiary=SMRU,BHF
&filter_department=IT,HR
&filter_pay_period_date=2025-01-01,2025-12-31
&sort_by=pay_period_date
&sort_order=desc
```

#### 2. Create Payroll
```http
POST /api/payrolls
```
**Request Body:**
```json
{
  "employee_id": 1,
  "pay_period_date": "2025-08-31",
  "allocation_calculations": [
    {
      "allocation_id": 1,
      "employment_id": 1,
      "allocation_type": "grant",
      "level_of_effort": 0.6,
      "funding_source": "Research Grant (BHF)",
      "salary_by_fte": 30000,
      "compensation_refund": 0,
      "thirteen_month_salary": 2500,
      "pvd_employee": 2250,
      "saving_fund": 0,
      "social_security_employee": 750,
      "social_security_employer": 750,
      "health_welfare_employee": 150,
      "health_welfare_employer": 0,
      "income_tax": 1200,
      "total_income": 32500,
      "total_deductions": 4350,
      "net_salary": 28150,
      "employer_contributions": 750
    }
  ],
  "payslip_date": "2025-09-01",
  "payslip_number": "PAY-2025-001",
  "staff_signature": "John Doe",
  "created_by": "admin"
}
```

#### 3. Calculate Payroll (Preview)
```http
POST /api/payrolls/calculate
```
**Request Body:**
```json
{
  "employee_id": 1,
  "gross_salary": 50000,
  "pay_period_date": "2025-01-31",
  "tax_year": 2025,
  "save_payroll": false,
  "additional_income": [
    {
      "type": "bonus",
      "amount": 5000,
      "description": "Performance bonus"
    }
  ],
  "additional_deductions": [
    {
      "type": "loan",
      "amount": 2000,
      "description": "Company loan repayment"
    }
  ]
}
```

#### 4. Employee Employment Details
```http
GET /api/payrolls/employee-employment?employee_id=1
```

#### 5. Employee Employment with Calculations
```http
GET /api/payrolls/employee-employment-calculated?employee_id=1&pay_period_date=2025-01-31
```

#### 6. Preview Inter-Subsidiary Advances
```http
GET /api/payrolls/preview-advances?employee_id=1&pay_period_date=2025-01-31
```

#### 7. Tax Summary
```http
GET /api/payrolls/tax-summary/{payroll_id}
```

#### 8. Bulk Calculate Payroll
```http
POST /api/payrolls/bulk-calculate
```

### Tax Management Endpoints

#### 1. Tax Brackets
```http
GET /api/tax-brackets
POST /api/tax-brackets
GET /api/tax-brackets/{id}
PUT /api/tax-brackets/{id}
DELETE /api/tax-brackets/{id}
GET /api/tax-brackets/calculate/{income}
```

#### 2. Tax Settings
```http
GET /api/tax-settings
POST /api/tax-settings
GET /api/tax-settings/{id}
PUT /api/tax-settings/{id}
DELETE /api/tax-settings/{id}
PATCH /api/tax-settings/{id}/toggle
GET /api/tax-settings/by-year/{year}
GET /api/tax-settings/value/{key}
POST /api/tax-settings/bulk-update
```

#### 3. Tax Calculations
```http
POST /api/tax-calculations/payroll
POST /api/tax-calculations/income-tax
POST /api/tax-calculations/annual-summary
POST /api/tax-calculations/validate-inputs
POST /api/tax-calculations/compliance-check
POST /api/tax-calculations/thai-report
```

### Inter-Subsidiary Advance Endpoints
```http
GET /api/inter-subsidiary-advances
POST /api/inter-subsidiary-advances
GET /api/inter-subsidiary-advances/{id}
PUT /api/inter-subsidiary-advances/{id}
DELETE /api/inter-subsidiary-advances/{id}
```

---

## Tax Calculation System

### Thai Revenue Department Compliance (2025)

The system implements full compliance with Thai tax regulations:

#### 1. Progressive Tax Brackets (8 Levels)
```php
const THAI_2025_BRACKETS = [
    ['min' => 0,       'max' => 150000,   'rate' => 0],    // Tax exempt
    ['min' => 150001,  'max' => 300000,   'rate' => 5],    // 5%
    ['min' => 300001,  'max' => 500000,   'rate' => 10],   // 10%
    ['min' => 500001,  'max' => 750000,   'rate' => 15],   // 15%
    ['min' => 750001,  'max' => 1000000,  'rate' => 20],   // 20%
    ['min' => 1000001, 'max' => 2000000,  'rate' => 25],   // 25%
    ['min' => 2000001, 'max' => 5000000,  'rate' => 30],   // 30%
    ['min' => 5000001, 'max' => null,     'rate' => 35],   // 35% (highest)
];
```

#### 2. Tax Calculation Sequence
```php
// 1. Calculate annual income
$annualIncome = $monthlySalary * $monthsWorking;

// 2. Apply employment deductions (50% max ฿100,000)
$employmentDeductions = min($annualIncome * 0.5, 100000);

// 3. Apply personal allowances
$personalAllowances = $this->calculatePersonalAllowances($employeeData);

// 4. Calculate taxable income
$taxableIncome = $annualIncome - $employmentDeductions - $personalAllowances - $socialSecurity - $providentFund;

// 5. Apply progressive tax brackets
$annualTax = $this->calculateProgressiveIncomeTax($taxableIncome);

// 6. Convert to monthly withholding
$monthlyTax = $annualTax / 12;
```

#### 3. Personal Allowances (2025 Rates)
```php
const THAI_2025_ALLOWANCES = [
    'PERSONAL_ALLOWANCE' => 60000,        // ฿60,000
    'SPOUSE_ALLOWANCE' => 60000,          // ฿60,000 (if spouse has no income)
    'CHILD_ALLOWANCE' => 30000,           // ฿30,000 (first child)
    'CHILD_ALLOWANCE_SUBSEQUENT' => 60000, // ฿60,000 (children born 2018+)
    'PARENT_ALLOWANCE' => 30000,          // ฿30,000 per eligible parent
];
```

#### 4. Social Security Fund (SSF)
```php
const SSF_SETTINGS = [
    'rate' => 5.0,              // 5% mandatory rate
    'min_salary' => 1650,       // ฿1,650 monthly minimum
    'max_salary' => 15000,      // ฿15,000 monthly maximum
    'max_monthly' => 750,       // ฿750 monthly maximum contribution
    'max_yearly' => 9000,       // ฿9,000 annual maximum contribution
];
```

### Tax Calculation Features

#### 1. Mid-Year Employment Handling
```php
public function calculateEmployeeTax(float $grossSalary, array $employeeData = []): array
{
    // Handle mid-year employment
    $monthsWorking = $employeeData['months_working_this_year'] ?? 12;
    $annualIncome = $grossSalary * $monthsWorking;
    
    // Rest of calculation...
}
```

#### 2. Configurable Tax Settings
- Dynamic tax bracket management
- Configurable allowances and deductions
- Year-based tax configurations
- Real-time cache invalidation

#### 3. Compliance Validation
```php
public static function validateThaiCompliance(?int $year = null): array
{
    // Validate tax brackets
    // Check allowance settings
    // Verify SSF rates
    // Return compliance status
}
```

---

## Multi-Source Funding

### Funding Allocation System

#### 1. Allocation Types
- **Grant Funding**: Research grants with budget constraints
- **Organizational Funding**: General organizational budget
- **Position Slots**: Specific funded positions within grants

#### 2. Level of Effort (LOE) Management
```php
// LOE Validation
public function validateTotalEffort(array $allocations): void
{
    $totalLOE = array_sum(array_column($allocations, 'level_of_effort'));
    
    if (abs($totalLOE - 100) > 0.01) {
        throw new ValidationException('Total Level of Effort must equal 100%');
    }
}
```

#### 3. Allocation Processing
```php
foreach ($employee->employeeFundingAllocations as $allocation) {
    $payrollData = $this->calculateAllocationPayroll($employee, $allocation, $payPeriodDate);
    
    if ($savePayroll) {
        $payroll = $this->createPayrollRecord($employee->employment, $allocation, $payrollData, $payPeriodDate);
        
        // Check for inter-subsidiary advance
        $advance = $this->createInterSubsidiaryAdvanceIfNeeded($employee, $allocation, $payroll, $payPeriodDate);
    }
}
```

### Funding Allocation Examples

#### Simple Single Source
```json
{
  "employee_id": 1,
  "allocations": [
    {
      "type": "organization",
      "level_of_effort": 100,
      "funding_source": "General Fund (SMRU)"
    }
  ]
}
```

#### Complex Multi-Source
```json
{
  "employee_id": 1,
  "allocations": [
    {
      "type": "grant",
      "grant_id": 1,
      "position_slot_id": 5,
      "level_of_effort": 40,
      "funding_source": "Malaria Research Grant (BHF)"
    },
    {
      "type": "grant", 
      "grant_id": 2,
      "position_slot_id": 8,
      "level_of_effort": 35,
      "funding_source": "TB Research Grant (NIH)"
    },
    {
      "type": "organization",
      "level_of_effort": 25,
      "funding_source": "General Fund (SMRU)"
    }
  ]
}
```

---

## Inter-Subsidiary Advances

### Automatic Advance Detection

The system automatically creates inter-subsidiary advances when:
1. Employee's subsidiary ≠ Funding source subsidiary
2. Grant has hub subsidiary configuration
3. Payroll amount > 0

#### Detection Logic
```php
private function createInterSubsidiaryAdvanceIfNeeded(
    Employee $employee, 
    EmployeeFundingAllocation $allocation, 
    Payroll $payroll, 
    Carbon $payPeriodDate
): ?InterSubsidiaryAdvance {
    
    $employeeSubsidiary = $employee->subsidiary;
    $fundingSubsidiary = $this->getFundingSubsidiary($allocation);
    
    if ($employeeSubsidiary !== $fundingSubsidiary) {
        return $this->createAdvance($employee, $allocation, $payroll, $payPeriodDate);
    }
    
    return null;
}
```

### Advance Management Features

#### 1. Automatic Creation
- Real-time detection during payroll processing
- Automatic amount calculation
- Hub grant routing
- Settlement tracking

#### 2. Manual Management
- Create manual advances
- Modify advance amounts
- Update settlement dates
- Add notes and documentation

#### 3. Reporting & Tracking
- Outstanding advances report
- Settlement history
- Subsidiary balance tracking
- Aging analysis

### Advance Workflow
```
1. Payroll Created → 2. Advance Detected → 3. Advance Created → 4. Settlement Scheduled
        ↓                     ↓                    ↓                    ↓
   Employee paid      Cross-subsidiary     Financial record      Future settlement
   from local fund    transfer needed      created in system     with hub grant
```

---

## Security & Encryption

### Data Protection

#### 1. Field-Level Encryption
All sensitive payroll data is encrypted at the database level:

```php
protected $casts = [
    'gross_salary' => 'encrypted',
    'net_salary' => 'encrypted',
    'tax' => 'encrypted',
    'total_income' => 'encrypted',
    // ... all financial fields
];
```

#### 2. Encrypted Fields
- All salary amounts
- Tax calculations
- Social security contributions
- Provident fund amounts
- Bonus payments
- Deductions

#### 3. Access Control
```php
// Permission-based access
Route::middleware('permission:payroll.read')->group(function () {
    // Payroll read operations
});

Route::middleware('permission:payroll.create')->group(function () {
    // Payroll creation operations
});
```

### Security Features

#### 1. Authentication & Authorization
- Sanctum-based API authentication
- Role-based permissions (Spatie)
- Fine-grained access control
- Session management

#### 2. Audit Trail
- Complete change tracking
- User activity logging
- Timestamp recording
- Data integrity validation

#### 3. Data Validation
- Input sanitization
- Business rule validation
- Cross-field validation
- Compliance checking

---

## Workflow & Business Logic

### Payroll Processing Workflow

#### 1. Employee Setup
```
Employee Creation → Employment Record → Funding Allocation → Tax Configuration
```

#### 2. Payroll Calculation
```
Salary Calculation → Tax Computation → Deduction Processing → Net Salary Calculation
```

#### 3. Multi-Allocation Processing
```
For Each Allocation:
  ├── Calculate LOE-based salary
  ├── Apply tax calculations
  ├── Process deductions
  ├── Create payroll record
  └── Check for advances
```

#### 4. Advance Processing
```
Advance Detection → Hub Grant Lookup → Advance Creation → Settlement Scheduling
```

### Business Rules

#### 1. Salary Calculations
- Probation salary handling with mid-month transitions
- Annual increase application (1% after 365 days)
- FTE and LOE percentage applications
- 13th month salary eligibility (6+ months employment)

#### 2. Tax Compliance
- Thai Revenue Department sequence compliance
- Progressive bracket application
- Allowance and deduction processing
- Social security contribution limits

#### 3. Funding Validation
- 100% LOE requirement across allocations
- Grant budget availability checking
- Position slot capacity validation
- Subsidiary compatibility verification

#### 4. Advance Management
- Automatic detection and creation
- Hub grant routing requirements
- Settlement date scheduling
- Outstanding balance tracking

---

## Integration Points

### Internal System Integration

#### 1. Employee Management
- Employee data synchronization
- Employment status updates
- Personal information changes
- Family status modifications

#### 2. Grant Management
- Grant budget tracking
- Position slot availability
- Funding source validation
- Budget consumption monitoring

#### 3. Financial Systems
- Accounting system integration
- Bank transfer preparation
- Financial reporting
- Audit trail maintenance

### External Integration Points

#### 1. Banking Systems
- Salary transfer files
- Payment confirmation
- Transaction reconciliation
- Error handling

#### 2. Government Systems
- Social security reporting
- Tax withholding reports
- Compliance submissions
- Regulatory updates

#### 3. Third-Party Services
- Payroll service providers
- Tax calculation services
- Compliance monitoring
- Audit systems

---

## Performance & Caching

### Caching Strategy

#### 1. Tax Configuration Caching
```php
// Cache tax settings and brackets
$cacheKey = "tax_config_{$this->year}";
$this->taxSettings = Cache::remember($cacheKey . '_settings', 3600, function() {
    return TaxSetting::selected()->forYear($this->year)->get();
});
```

#### 2. Query Optimization
```php
// Optimized payroll queries
public function scopeWithOptimizedRelations($query)
{
    return $query->with([
        'employment.employee:id,staff_id,first_name_en,last_name_en,subsidiary',
        'employment.department:id,name',
        'employment.position:id,title,department_id',
        'employeeFundingAllocation:id,employee_id,allocation_type,level_of_effort',
    ]);
}
```

#### 3. Cache Management
- Automatic cache invalidation on data changes
- Tagged cache groups for selective clearing
- TTL-based cache expiration
- Cache warming strategies

### Performance Features

#### 1. Lazy Loading
- Configuration loaded only when needed
- Relationship loading optimization
- Selective field loading
- Pagination support

#### 2. Database Optimization
- Indexed foreign keys
- Composite indexes for filtering
- Query scope optimization
- Bulk operation support

#### 3. Memory Management
- Efficient data structures
- Garbage collection optimization
- Memory usage monitoring
- Resource cleanup

---

## Validation & Error Handling

### Request Validation

#### 1. Payroll Calculation Validation
```php
public function rules(): array
{
    return [
        'employee_id' => 'required|exists:employees,id',
        'gross_salary' => 'required|numeric|min:0|max:999999999.99',
        'pay_period_date' => 'required|date|before_or_equal:today',
        'tax_year' => 'nullable|integer|min:2000|max:2100',
        'additional_income' => 'nullable|array|max:20',
        'additional_income.*.type' => 'required_with:additional_income|string|max:50',
        'additional_income.*.amount' => 'required_with:additional_income|numeric|min:0|max:999999.99',
        'additional_deductions' => 'nullable|array|max:20',
        'save_payroll' => 'boolean',
    ];
}
```

#### 2. Business Logic Validation
```php
public function withValidator($validator): void
{
    $validator->after(function ($validator) {
        // Validate pay period date range
        if ($this->pay_period_date) {
            $payPeriodDate = new \DateTime($this->pay_period_date);
            $twoYearsAgo = new \DateTime('-2 years');
            
            if ($payPeriodDate < $twoYearsAgo) {
                $validator->errors()->add('pay_period_date', 'Pay period date cannot be more than 2 years in the past.');
            }
        }
        
        // Validate total additional income reasonableness
        if ($this->additional_income) {
            $totalAdditionalIncome = array_sum(array_column($this->additional_income, 'amount'));
            if ($totalAdditionalIncome > $this->gross_salary * 5) {
                $validator->errors()->add('additional_income', 'Total additional income seems unusually high compared to gross salary.');
            }
        }
    });
}
```

### Error Handling

#### 1. Exception Management
```php
try {
    DB::beginTransaction();
    
    // Payroll processing logic
    
    DB::commit();
    return $result;
    
} catch (\Exception $e) {
    DB::rollBack();
    
    Log::error('Payroll processing failed', [
        'employee_id' => $employee->id,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    throw new PayrollProcessingException('Failed to process payroll: ' . $e->getMessage());
}
```

#### 2. Validation Messages
```php
public function messages(): array
{
    return [
        'employee_id.required' => 'Employee selection is required.',
        'employee_id.exists' => 'Selected employee does not exist.',
        'gross_salary.required' => 'Gross salary is required.',
        'gross_salary.numeric' => 'Gross salary must be a valid number.',
        'gross_salary.min' => 'Gross salary cannot be negative.',
        'pay_period_date.required' => 'Pay period date is required.',
        'pay_period_date.date' => 'Pay period date must be a valid date.',
        'pay_period_date.before_or_equal' => 'Pay period date cannot be in the future.',
    ];
}
```

---

## Reporting & Analytics

### Built-in Reports

#### 1. Payroll Summary Reports
- Employee payroll summaries
- Department-wise breakdowns
- Subsidiary comparisons
- Period-over-period analysis

#### 2. Tax Reports
- Tax withholding summaries
- Annual tax calculations
- Compliance reports
- Bracket utilization analysis

#### 3. Funding Reports
- Grant utilization tracking
- LOE allocation summaries
- Budget consumption analysis
- Multi-source funding reports

#### 4. Advance Reports
- Outstanding advances
- Settlement tracking
- Subsidiary balance reports
- Aging analysis

### Analytics Features

#### 1. Performance Metrics
- Payroll processing times
- Error rates and types
- System utilization
- User activity patterns

#### 2. Business Intelligence
- Salary trend analysis
- Tax optimization opportunities
- Funding efficiency metrics
- Compliance monitoring

#### 3. Export Capabilities
- Excel/CSV exports
- PDF report generation
- API data access
- Scheduled reporting

---

## Deployment & Configuration

### System Requirements

#### 1. Server Requirements
- PHP 8.2+
- Laravel 11
- MySQL 8.0+ or PostgreSQL 13+
- Redis (for caching)
- Minimum 4GB RAM
- SSD storage recommended

#### 2. PHP Extensions
- OpenSSL (for encryption)
- PDO MySQL/PostgreSQL
- Mbstring
- Tokenizer
- XML
- Ctype
- JSON
- BCMath

### Configuration

#### 1. Environment Variables
```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hrms
DB_USERNAME=hrms_user
DB_PASSWORD=secure_password

# Cache
CACHE_DRIVER=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Encryption
APP_KEY=base64:generated_key_here

# Payroll Settings
PAYROLL_DEFAULT_TAX_YEAR=2025
PAYROLL_CACHE_TTL=3600
PAYROLL_MAX_ALLOCATIONS=10
```

#### 2. Tax Configuration Setup
```php
// Run tax bracket seeder
php artisan db:seed --class=TaxBracketSeeder

// Run tax settings seeder  
php artisan db:seed --class=TaxSettingSeeder

// Verify compliance
php artisan tax:validate-compliance
```

#### 3. Permissions Setup
```php
// Create payroll permissions
php artisan permission:create-payroll-permissions

// Assign to roles
php artisan role:assign-permissions payroll-admin payroll.*
php artisan role:assign-permissions hr-manager payroll.read,payroll.create
```

### Deployment Steps

#### 1. Initial Deployment
```bash
# Clone repository
git clone <repository-url>

# Install dependencies
composer install --no-dev --optimize-autoloader

# Configure environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Seed tax data
php artisan db:seed --class=TaxBracketSeeder
php artisan db:seed --class=TaxSettingSeeder

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
chmod -R 755 storage bootstrap/cache
```

#### 2. Updates & Maintenance
```bash
# Pull latest changes
git pull origin main

# Update dependencies
composer install --no-dev --optimize-autoloader

# Run migrations
php artisan migrate

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear

# Rebuild caches
php artisan config:cache
php artisan route:cache
```

### Monitoring & Maintenance

#### 1. Health Checks
- Database connectivity
- Cache system status
- Tax configuration validation
- Encryption key verification

#### 2. Performance Monitoring
- Query performance analysis
- Cache hit rates
- Memory usage tracking
- Response time monitoring

#### 3. Security Monitoring
- Failed authentication attempts
- Unauthorized access attempts
- Data modification tracking
- Encryption status verification

---

## Conclusion

The HRMS Payroll Management System provides a comprehensive, secure, and compliant solution for complex payroll processing needs. With its multi-source funding capabilities, Thai tax compliance, automated inter-subsidiary advances, and robust security features, it serves as a complete payroll management platform for modern organizations.

### Key Benefits
- **Compliance**: Full Thai Revenue Department compliance
- **Flexibility**: Multi-source funding and complex allocation support
- **Security**: End-to-end encryption and comprehensive audit trails
- **Automation**: Automated calculations and advance detection
- **Scalability**: Performance-optimized for large-scale operations
- **Integration**: Comprehensive API for system integration

### Support & Documentation
- API documentation available via Swagger/OpenAPI
- Comprehensive test coverage
- Detailed error logging and monitoring
- Professional support available

---

*Last Updated: October 2025*
*Version: 1.0*
*System: HRMS Backend API v1*


