# HRMS Payroll Creation Guide

## 📋 **Prerequisites Check**

Before creating payroll, ensure you have:

1. ✅ **Employee** record created
2. ✅ **Employment** record with salary information
3. ✅ **EmployeeFundingAllocation(s)** that total 100% LOE
4. ✅ **Tax configuration** (brackets & settings) for the year

## 🚀 **Method 1: Using API Endpoint (Recommended)**

### **Calculate & Save Payroll in One Step:**

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

**Response:**
```json
{
    "success": true,
    "message": "Payroll calculated successfully",
    "data": {
        "gross_salary": 50000,
        "total_income": 55000,
        "net_salary": 48275,
        "income_tax": 975,
        "saved_payroll_id": 123,
        "deductions": {
            "personal_allowance": 60000,
            "spouse_allowance": 60000,
            "child_allowance": 60000,
            "provident_fund": 18000,
            "total_deductions": 258000
        },
        "social_security": {
            "employee_contribution": 750,
            "employer_contribution": 750
        },
        "tax_breakdown": [...]
    }
}
```

## 🛠️ **Method 2: Manual Database Creation**

### **Step-by-Step Process:**

#### **1. Get Employee Employment & Funding Data:**
```php
$employee = Employee::with(['employment', 'employeeFundingAllocations'])->find(1);
$employment = $employee->employment;
$fundingAllocations = $employee->employeeFundingAllocations;

// Verify LOE totals 100%
$totalLOE = $fundingAllocations->sum('level_of_effort');
if ($totalLOE != 1.00) {
    throw new Exception("LOE must total 100%, currently: " . ($totalLOE * 100) . "%");
}
```

#### **2. Calculate Tax Using Service:**
```php
use App\Services\TaxCalculationService;

$taxService = new TaxCalculationService(2025);
$payrollData = $taxService->calculatePayroll(
    employeeId: 1,
    grossSalary: 50000,
    additionalIncome: [
        ['type' => 'bonus', 'amount' => 5000, 'description' => 'Performance bonus']
    ],
    additionalDeductions: [
        ['type' => 'loan', 'amount' => 2000, 'description' => 'Company loan']
    ]
);
```

#### **3. Create Payroll Record:**
```php
$payroll = Payroll::create([
    'employment_id' => $employment->id,
    'employee_funding_allocation_id' => $fundingAllocations->first()->id,
    'pay_period_date' => '2025-01-31',
    
    // Salary Information
    'gross_salary' => $payrollData['gross_salary'],
    'gross_salary_by_FTE' => $payrollData['gross_salary'] * $employment->fte,
    'net_salary' => $payrollData['net_salary'],
    'total_income' => $payrollData['total_income'],
    
    // Tax & Deductions
    'tax' => $payrollData['income_tax'],
    'total_deduction' => $payrollData['income_tax'] + $payrollData['social_security']['employee_contribution'],
    
    // Social Security
    'employee_social_security' => $payrollData['social_security']['employee_contribution'],
    'employer_social_security' => $payrollData['social_security']['employer_contribution'],
    
    // Provident Fund
    'pvd' => $payrollData['deductions']['provident_fund'],
    'total_pvd' => $payrollData['deductions']['provident_fund'],
    
    // Benefits (from Employment record)
    'employer_health_welfare' => $employment->health_welfare ? 500 : 0,
    'employee_health_welfare' => $employment->health_welfare ? 200 : 0,
    
    // 13th Month Salary
    'thirteen_month_salary' => $payrollData['gross_salary'] / 12,
    'thirteen_month_salary_accured' => $payrollData['gross_salary'] / 12,
    
    // Additional Fields
    'compensation_refund' => 0,
    'saving_fund' => $employment->saving_fund ? 1000 : 0,
    'total_saving_fund' => $employment->saving_fund ? 1000 : 0,
    'salary_bonus' => 5000, // From additional income
    'employer_contribution' => $payrollData['social_security']['employer_contribution'],
    'notes' => 'Generated with tax calculation system'
]);
```

## 💡 **Key Features Explained:**

### **🎯 Funding Allocation Integration:**
- Each payroll links to a specific `EmployeeFundingAllocation`
- LOE (Level of Effort) determines cost distribution across grants/org funding
- Multiple allocations per employee supported (must total 100%)

### **🧮 Automatic Tax Calculation:**
- **Progressive Tax Brackets** (0%, 5%, 10%, 15%, 20%, 25%, 30%, 35%)
- **Deductions:**
  - Personal Allowance: ฿60,000
  - Spouse Allowance: ฿60,000  
  - Child Allowance: ฿60,000 (per child)
  - Personal Expenses: ฿60,000
  - Provident Fund: Up to ฿18,000
- **Social Security:** 5% employee + 5% employer (max ฿750/month each)

### **🔐 Security Features:**
- All salary fields automatically **encrypted** in database
- Audit trail through `EmploymentHistory`
- User tracking (`created_by`, `updated_by`)

### **📊 Grant Allocation Tracking:**
- Links payroll to specific funding sources
- Supports cost distribution across multiple grants
- Maintains funding compliance and reporting

## 🎯 **Example: Complete Payroll Workflow**

### **Scenario:** 
- Employee: John Doe (ID: 1)
- Position Salary: ฿50,000/month
- FTE: 1.0 (100%)
- Funding: 60% Grant A, 40% Org Funded
- Benefits: Health & Welfare, PVD
- Additional: ฿5,000 performance bonus

### **API Call:**
```bash
curl -X POST "http://your-domain/api/payrolls/calculate" \
-H "Content-Type: application/json" \
-H "Authorization: Bearer YOUR_TOKEN" \
-d '{
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
        }
    ]
}'
```

### **Result:**
- ✅ **Gross Salary:** ฿50,000
- ✅ **Total Income:** ฿55,000 (with bonus)
- ✅ **Tax Calculated:** ฿975 (progressive brackets)
- ✅ **Social Security:** ฿750 employee + ฿750 employer
- ✅ **PVD Deduction:** ฿1,500 (3% of gross)
- ✅ **Net Salary:** ฿48,275
- ✅ **Funding Split:** Auto-allocated per LOE percentages
- ✅ **Audit Trail:** Complete history maintained

## 🚨 **Important Notes:**

1. **LOE Validation:** Total funding allocations must equal 100%
2. **Tax Year:** Ensure tax brackets/settings exist for the year
3. **Employment Status:** Employee must have active employment record
4. **Encryption:** All financial data automatically encrypted
5. **Compliance:** System follows Thai tax regulations

## 🔗 **API Endpoints Available:**

- `POST /api/payrolls/calculate` - Calculate & optionally save payroll
- `POST /api/payrolls/bulk-calculate` - Bulk payroll calculation
- `GET /api/payrolls/{id}/tax-summary` - Detailed tax breakdown
- `GET /api/payrolls` - List all payroll records
- `POST /api/payrolls` - Create payroll manually
- `PUT /api/payrolls/{id}` - Update payroll
- `DELETE /api/payrolls/{id}` - Delete payroll

Your HRMS system is fully equipped for comprehensive payroll management with automated tax calculations, funding allocation tracking, and regulatory compliance! 🎉