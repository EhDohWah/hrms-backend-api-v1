# Tax System Implementation Guide

## Overview

This document provides a comprehensive overview of the Thai tax calculation system implemented in the HRMS backend API. The system supports progressive income tax calculations, deductions, social security contributions, and automated payroll processing.

## 🎯 Features Implemented

### ✅ Core Tax Models
- **TaxBracket**: Progressive tax brackets with income ranges and rates
- **TaxSetting**: Configurable tax settings (deductions, rates, limits)
- Year-based configurations for tax planning
- Active/inactive status management

### ✅ Tax Calculation Service
- **TaxCalculationService**: Comprehensive tax calculation engine
- Progressive income tax calculation using tax brackets
- Personal allowances and deductions processing
- Social Security Fund (SSF) calculations
- Provident Fund (PF) calculations
- Support for additional income and deductions

### ✅ API Controllers
- **TaxBracketController**: CRUD operations for tax brackets
- **TaxSettingController**: CRUD operations for tax settings
- **TaxCalculationController**: Tax calculation endpoints
- Enhanced **PayrollController**: Automated payroll calculations

### ✅ API Resources
- **TaxBracketResource**: Formatted tax bracket responses
- **TaxSettingResource**: Formatted tax setting responses
- **PayrollCalculationResource**: Comprehensive payroll calculation responses

### ✅ Request Validation
- **StoreTaxBracketRequest**: Tax bracket creation validation
- **UpdateTaxBracketRequest**: Tax bracket update validation
- **StoreTaxSettingRequest**: Tax setting creation validation
- **CalculatePayrollRequest**: Payroll calculation validation

### ✅ Database Seeders
- **TaxBracketSeeder**: Thai tax brackets for 2025-2026
- **TaxSettingSeeder**: Thai tax settings and allowances

### ✅ API Routes
- Tax bracket management: `/api/tax-brackets`
- Tax setting management: `/api/tax-settings`
- Tax calculations: `/api/tax-calculations`
- Enhanced payroll: `/api/payrolls/calculate`

### ✅ Testing
- **TaxCalculationServiceTest**: Unit tests for tax calculations
- **TaxApiTest**: Feature tests for API endpoints

## 📊 Thai Tax System Configuration (2025)

### Tax Brackets
| Income Range (THB) | Tax Rate | Description |
|-------------------|----------|-------------|
| 0 - 150,000 | 0% | Tax-free bracket |
| 150,001 - 300,000 | 5% | First tax bracket |
| 300,001 - 500,000 | 10% | Second tax bracket |
| 500,001 - 750,000 | 15% | Third tax bracket |
| 750,001 - 1,000,000 | 20% | Fourth tax bracket |
| 1,000,001 - 2,000,000 | 25% | Fifth tax bracket |
| 2,000,001 - 5,000,000 | 30% | Sixth tax bracket |
| Above 5,000,000 | 35% | Highest tax bracket |

### Tax Settings
| Setting | Value | Type | Description |
|---------|-------|------|-------------|
| PERSONAL_ALLOWANCE | 60,000 | DEDUCTION | Personal allowance |
| SPOUSE_ALLOWANCE | 60,000 | DEDUCTION | Spouse allowance |
| CHILD_ALLOWANCE | 30,000 | DEDUCTION | Child allowance (per child, max 3) |
| PERSONAL_EXPENSE_RATE | 40% | RATE | Personal expense deduction rate |
| PERSONAL_EXPENSE_MAX | 60,000 | LIMIT | Maximum personal expense deduction |
| SSF_RATE | 5% | RATE | Social Security Fund rate |
| SSF_MAX_MONTHLY | 750 | LIMIT | Maximum monthly SSF contribution |
| PF_MIN_RATE | 3% | RATE | Minimum Provident Fund rate |
| PF_MAX_RATE | 15% | RATE | Maximum Provident Fund rate |

## 🚀 API Usage Examples

### 1. Calculate Payroll with Taxes
```bash
POST /api/payrolls/calculate
Content-Type: application/json

{
  "employee_id": 1,
  "gross_salary": 50000,
  "pay_period_date": "2025-01-31",
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
  ],
  "save_payroll": false
}
```

### 2. Get Tax Brackets
```bash
GET /api/tax-brackets?year=2025&active_only=true
```

### 3. Calculate Income Tax Only
```bash
POST /api/tax-calculations/income-tax
Content-Type: application/json

{
  "taxable_income": 600000,
  "tax_year": 2025
}
```

### 4. Bulk Calculate Payroll
```bash
POST /api/payrolls/bulk-calculate
Content-Type: application/json

{
  "pay_period_date": "2025-01-31",
  "employees": [
    {
      "employee_id": 1,
      "gross_salary": 50000
    },
    {
      "employee_id": 2,
      "gross_salary": 60000
    }
  ],
  "save_payrolls": true
}
```

## 🔧 Configuration

### Environment Variables
No additional environment variables are required. The system uses the existing database configuration.

### Permissions
The following permissions are used:
- `tax.read` - View tax configurations
- `tax.create` - Create tax configurations
- `tax.update` - Update tax configurations
- `tax.delete` - Delete tax configurations
- `payroll.read` - Calculate payroll
- `payroll.create` - Create payroll records

## 🧪 Testing

### Run Unit Tests
```bash
php artisan test tests/Unit/TaxCalculationServiceTest.php
```

### Run Feature Tests
```bash
php artisan test tests/Feature/TaxApiTest.php
```

### Run All Tax Tests
```bash
php artisan test --filter=Tax
```

## 📈 Tax Calculation Example

For an employee with:
- Monthly Salary: ฿50,000
- Marital Status: Married
- Children: 2
- Annual Salary: ฿600,000

**Deductions:**
- Personal Allowance: ฿60,000
- Spouse Allowance: ฿60,000
- Child Allowance: ฿60,000 (2 × ฿30,000)
- Personal Expenses: ฿60,000 (40% of ฿600,000, capped)
- Provident Fund: ฿18,000 (3% of ฿600,000)
- **Total Deductions: ฿258,000**

**Taxable Income:** ฿600,000 - ฿258,000 = ฿342,000

**Tax Calculation:**
- ฿0 - ฿150,000: 0% = ฿0
- ฿150,001 - ฿300,000: 5% = ฿7,500
- ฿300,001 - ฿342,000: 10% = ฿4,200
- **Annual Tax: ฿11,700**
- **Monthly Tax: ฿975**

**Social Security:**
- Employee Contribution: ฿750 (5% of ฿50,000, capped)
- Employer Contribution: ฿750

**Net Monthly Salary:** ฿50,000 - ฿975 - ฿750 = ฿48,275

## 📋 Database Schema

### tax_brackets Table
```sql
- id (primary key)
- min_income (decimal)
- max_income (decimal, nullable)
- tax_rate (decimal)
- bracket_order (integer)
- effective_year (integer)
- is_active (boolean)
- description (string)
- created_by, updated_by (string)
- timestamps
```

### tax_settings Table
```sql
- id (primary key)
- setting_key (string)
- setting_value (decimal)
- setting_type (enum: DEDUCTION, RATE, LIMIT)
- description (string)
- effective_year (integer)
- is_active (boolean)
- created_by, updated_by (string)
- timestamps
- unique(setting_key, effective_year)
```

## 🔄 Data Seeding

### Seed Tax Data
```bash
# Seed tax brackets
php artisan db:seed --class=TaxBracketSeeder

# Seed tax settings
php artisan db:seed --class=TaxSettingSeeder

# Seed all
php artisan db:seed
```

## 🔐 Security Features

- Request validation for all tax operations
- Permission-based access control
- Input sanitization and validation
- Encrypted payroll data storage
- Audit trails (created_by, updated_by)

## 🔄 Maintenance

### Yearly Tax Updates
1. Create new tax brackets for the upcoming year
2. Create new tax settings for the upcoming year
3. Set previous year brackets/settings to inactive
4. Activate new year configurations on January 1st

### Bulk Updates
Use the bulk update endpoints to efficiently update multiple tax settings:
```bash
POST /api/tax-settings/bulk-update
```

## 🐛 Troubleshooting

### Common Issues

1. **Tax calculation returns 0**
   - Check if tax brackets exist for the specified year
   - Verify tax brackets are active
   - Ensure income is above the tax-free threshold

2. **Validation errors**
   - Check request format matches the validation rules
   - Verify employee exists in the database
   - Ensure required fields are provided

3. **Seeder failures**
   - Run migrations first: `php artisan migrate`
   - Check for unique constraint violations
   - Clear existing data if re-seeding

## 📚 Additional Resources

- [Thai Revenue Department Tax Rates](https://www.rd.go.th)
- [Social Security Office Contribution Rates](https://www.sso.go.th)
- [Laravel API Documentation](https://laravel.com/docs)

## 🎉 Implementation Status: 100% Complete

All planned features have been successfully implemented:
- ✅ Tax Models and Migrations
- ✅ Tax Calculation Service
- ✅ API Controllers and Routes
- ✅ Request Validation
- ✅ API Resources
- ✅ Database Seeders
- ✅ Payroll Integration
- ✅ Unit and Feature Tests
- ✅ Documentation

The tax system is ready for production use and can handle complex Thai tax calculations with full API support.