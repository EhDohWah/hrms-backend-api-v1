# Complete HRMS Payroll Creation Workflow

## 🎯 **Current System Status:**
- ✅ Tax Brackets & Settings: **CONFIGURED** (8 brackets, all settings)
- ✅ Tax Calculation Service: **READY**
- ✅ Payroll API Endpoints: **READY**
- ❌ Employee Data: **NEEDS CREATION**
- ❌ Employment Records: **NEEDS CREATION**
- ❌ Funding Allocations: **NEEDS CREATION**

## 🚀 **Step-by-Step Creation Process:**

### **Step 1: Create an Employee**

```bash
POST /api/employees
```

**Request Body:**
```json
{
    "staff_id": "EMP001",
    "subsidiary": "SMRU",
    "first_name_en": "John",
    "last_name_en": "Doe",
    "first_name_th": "จอห์น",
    "last_name_th": "โด",
    "gender": "Male",
    "date_of_birth": "1990-01-15",
    "nationality": "Thai",
    "phone": "+66812345678",
    "email": "john.doe@company.com",
    "status": "Active"
}
```

### **Step 2: Create Employment Record**

```bash
POST /api/employments
```

**Request Body:**
```json
{
    "employee_id": 1,
    "employment_type": "Full-time",
    "start_date": "2025-01-01",
    "pay_method": "Bank Transfer",
    "department_position_id": 1,
    "work_location_id": 1,
    "pass_probation_salary": 50000.00,
    "probation_salary": 45000.00,
    "probation_pass_date": "2025-04-01",
    "fte": 1.00,
    "health_welfare": true,
    "pvd": true,
    "saving_fund": false,

}
```

### **Step 3: Create Funding Allocations (Must Total 100% LOE)**

#### **Option A: Single Grant Funding (100%)**
```bash
POST /api/employee-funding-allocations
```

**Request Body:**
```json
{
    "employee_id": 1,
    "employment_id": 1,
    "position_slot_id": 1,
    "level_of_effort": 1.00,
    "allocation_type": "grant",
    "allocated_amount": 50000.00,
    "start_date": "2025-01-01",
    "end_date": "2025-12-31"
}
```

#### **Option B: Mixed Funding (Grant 60% + Org 40%)**

**Grant Allocation (60%):**
```json
{
    "employee_id": 1,
    "employment_id": 1,
    "position_slot_id": 1,
    "level_of_effort": 0.60,
    "allocation_type": "grant",
    "allocated_amount": 30000.00,
    "start_date": "2025-01-01",
    "end_date": "2025-12-31"
}
```

**Org Funded Allocation (40%):**
```json
{
    "employee_id": 1,
    "employment_id": 1,
    "org_funded_id": 1,
    "level_of_effort": 0.40,
    "allocation_type": "org_funded",
    "allocated_amount": 20000.00,
    "start_date": "2025-01-01",
    "end_date": "2025-12-31"
}
```

### **Step 4: Create Payroll Record**

Now you can create payroll using the automated calculation API:

```bash
POST /api/payrolls/calculate
```

**Request Body:**
```json
{
    "employee_id": 1,
    "gross_salary": 50000,
    "pay_period_date": "2025-01-31",
    "tax_year": 2025,
    "save_payroll": true,
    "additional_income": [
        {
            "type": "performance_bonus",
            "amount": 5000,
            "description": "Q4 Performance Bonus"
        },
        {
            "type": "overtime",
            "amount": 2000,
            "description": "Overtime January 2025"
        }
    ],
    "additional_deductions": [
        {
            "type": "company_loan",
            "amount": 1000,
            "description": "Monthly loan repayment"
        }
    ]
}
```

**Expected Response:**
```json
{
    "success": true,
    "message": "Payroll calculated successfully",
    "data": {
        "gross_salary": 50000,
        "total_income": 57000,
        "net_salary": 50525,
        "taxable_income": 342000,
        "income_tax": 975,
        "saved_payroll_id": 1,
        "deductions": {
            "personal_allowance": 60000,
            "spouse_allowance": 60000,
            "child_allowance": 60000,
            "personal_expenses": 60000,
            "provident_fund": 18000,
            "additional_deductions": 1000,
            "total_deductions": 259000
        },
        "social_security": {
            "employee_contribution": 750,
            "employer_contribution": 750,
            "total_contribution": 1500
        },
        "tax_breakdown": [
            {
                "bracket_order": 1,
                "income_range": "฿0 - ฿150,000",
                "tax_rate": "0%",
                "taxable_income": 150000,
                "tax_amount": 0,
                "monthly_tax": 0
            },
            {
                "bracket_order": 2,
                "income_range": "฿150,001 - ฿300,000",
                "tax_rate": "5%",
                "taxable_income": 192000,
                "tax_amount": 9600,
                "monthly_tax": 800
            }
        ],
        "calculation_date": "2025-08-07T10:30:00Z",
        "tax_year": 2025
    }
}
```

## 🏗️ **Database Structure Created:**

After following these steps, you'll have:

```
Employee (ID: 1)
└── Employment (ID: 1)
    ├── Salary: ฿50,000/month
    ├── FTE: 1.00 (100%)
    ├── Benefits: Health & Welfare, PVD
    └── EmployeeFundingAllocations
        ├── Grant: 60% LOE (฿30,000)
        └── Org Funded: 40% LOE (฿20,000)
        └── Total: 100% LOE ✅
└── Payroll (ID: 1)
    ├── Gross: ฿50,000
    ├── Total Income: ฿57,000 (with bonuses)
    ├── Tax: ฿975
    ├── Social Security: ฿750
    ├── Net: ฿50,525
    └── Funding: Auto-distributed per LOE
```

## 💡 **Key Points:**

### **🎯 Funding Allocation Rules:**
- **MUST total 100% LOE** (level_of_effort)
- Can be single source (1.00) or multiple sources (0.60 + 0.40)
- Each allocation links to grants or organizational funding
- Payroll costs automatically distributed per LOE percentages

### **🧮 Tax Calculation Features:**
- **Automatic progressive tax** calculation (Thai tax brackets)
- **Multiple deductions** supported:
  - Personal allowance: ฿60,000
  - Spouse allowance: ฿60,000
  - Child allowance: ฿60,000 per child
  - Personal expenses: ฿60,000
  - Provident fund: Up to ฿18,000
- **Social Security**: 5% employee + 5% employer (max ฿750 each)
- **Additional income/deductions** supported

### **🔐 Security & Compliance:**
- All salary data **automatically encrypted**
- Complete **audit trail** maintained
- **Employment history** tracking
- **Thai tax regulation** compliance

## 🎯 **Quick Test Commands:**

Once you have the data, you can test the system:

```bash
# Get employee with all relationships
GET /api/employees/1?include=employment,employeeFundingAllocations

# Calculate payroll without saving
POST /api/payrolls/calculate
{
    "employee_id": 1,
    "gross_salary": 50000,
    "pay_period_date": "2025-01-31",
    "save_payroll": false
}

# Get detailed tax breakdown
GET /api/payrolls/1/tax-summary

# List all payroll records
GET /api/payrolls
```

## 🚀 **Next Steps:**

1. **Create prerequisites** (Employee, Employment, Funding Allocations)
2. **Test payroll calculation** (without saving first)
3. **Create actual payroll** (with save_payroll: true)
4. **Verify funding distribution** across grants/org funding
5. **Generate payroll reports** and tax summaries

Your HRMS system is ready for comprehensive payroll management with automated tax calculations and funding allocation tracking! 🎉

## 📊 **Sample Business Scenarios:**

### **Scenario 1: Research Staff (Grant Funded)**
- 100% grant funding from research project
- Higher salary tier (฿60,000+)
- Full benefits package
- Complex tax calculations with multiple deductions

### **Scenario 2: Administrative Staff (Mixed Funding)**
- 70% organizational funding
- 30% administrative support grant
- Standard benefits
- Regular tax deductions

### **Scenario 3: Project Manager (Multi-Grant)**
- 40% Grant A (research)
- 35% Grant B (implementation)
- 25% organizational funding
- Executive level salary and benefits

Each scenario automatically handles funding distribution, tax calculations, and compliance requirements through your HRMS system!