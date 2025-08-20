# Design Document

## Overview

The Thai Tax Compliance Enhancement project will update the existing Laravel HR Management System's tax calculation functionality to fully align with official Thai Personal Income Tax regulations for 2024-2025. The design focuses on correcting calculation sequence, updating tax brackets, standardizing terminology, and enhancing audit capabilities while maintaining the existing architecture and preserving calculation accuracy.

## Architecture

### Current System Analysis

The existing system has a well-structured architecture with:
- **TaxSetting Model**: Manages configurable tax parameters with comprehensive constants
- **TaxBracket Model**: Handles progressive tax bracket configuration
- **TaxCalculationService**: Core business logic for tax calculations
- **TaxCalculationController**: API endpoints for tax operations
- **TaxCalculationLog**: Audit trail functionality (referenced but needs verification)

### Key Issues Identified

1. **Calculation Sequence**: Current system mixes deductions and allowances instead of following Thai law sequence
2. **Tax Bracket Discrepancy**: Highest bracket incorrectly set at "4M+ (35%)" instead of "5M+ (35%)"
3. **Terminology Inconsistency**: Uses "Personal usage" instead of "Employment deduction"
4. **Missing Deduction Categories**: Some Thai-specific deductions not fully implemented

## Components and Interfaces

### 1. Enhanced TaxSetting Model

**Current State**: Already well-structured with comprehensive constants
**Required Changes**:
- Update constant names for terminology consistency
- Add missing deduction categories for 2024-2025
- Enhance validation methods for Thai compliance

**Key Methods to Update**:
```php
// Update terminology constants
const KEY_EMPLOYMENT_DEDUCTION_RATE = 'EMPLOYMENT_DEDUCTION_RATE'; // Was: PERSONAL_USAGE_RATE
const KEY_EMPLOYMENT_DEDUCTION_MAX = 'EMPLOYMENT_DEDUCTION_MAX';   // Was: PERSONAL_USAGE_MAX

// Add missing 2024-2025 categories
const KEY_SHOPPING_ALLOWANCE_MAX = 'SHOPPING_ALLOWANCE_MAX';
const KEY_CONSTRUCTION_EXPENSE_MAX = 'CONSTRUCTION_EXPENSE_MAX';
```

### 2. Updated TaxBracket Model

**Current State**: Functional with proper structure
**Required Changes**:
- Update tax bracket data to reflect correct 5M+ highest bracket
- Ensure proper bracket progression validation

**Data Updates Required**:
```php
// Current incorrect highest bracket: 4M+ (35%)
// Correct highest bracket: 5M+ (35%)
```

### 3. Enhanced TaxCalculationService

**Current State**: Good structure but incorrect calculation sequence
**Critical Changes Required**:

#### Calculation Sequence Correction
```php
// CURRENT (Incorrect):
// Mixed deductions and allowances

// NEW (Thai Compliant):
// 1. Gross Income
// 2. Employment Deductions FIRST (50%, max ฿100k)
// 3. Personal Allowances SECOND
// 4. Other Deductions
// 5. Taxable Income = Income - Employment Deductions - Personal Allowances - Other Deductions
// 6. Progressive Tax Calculation
```

#### Method Updates Required
- `calculateEmploymentDeduction()`: Already correct, ensure terminology
- `calculatePersonalAllowances()`: Ensure applied after employment deductions
- `calculateTaxableIncome()`: Update sequence logic
- `validateThaiCompliance()`: Enhance validation rules

### 4. Database Migration Strategy

**Approach**: Update existing data without breaking changes
- Create migration to update tax bracket data
- Update tax setting keys with proper mapping
- Preserve existing payroll calculation history
- Add rollback capabilities

### 5. Enhanced Audit and Logging

**Current State**: TaxCalculationLog referenced but needs verification
**Enhancements Required**:
- Detailed calculation step logging
- Thai compliance validation results
- Calculation sequence documentation
- Historical comparison capabilities

## Data Models

### Tax Bracket Updates

**2025 Official Thai Tax Brackets**:
```
Bracket 1: ฿0 - ฿150,000 (0%)
Bracket 2: ฿150,001 - ฿300,000 (5%)
Bracket 3: ฿300,001 - ฿500,000 (10%)
Bracket 4: ฿500,001 - ฿750,000 (15%)
Bracket 5: ฿750,001 - ฿1,000,000 (20%)
Bracket 6: ฿1,000,001 - ฿2,000,000 (25%)
Bracket 7: ฿2,000,001 - ฿5,000,000 (30%)
Bracket 8: ฿5,000,001+ (35%) // CORRECTED from 4M+
```

### Tax Setting Categories

**Employment Deductions** (Applied First):
- Employment deduction rate: 50%
- Employment deduction maximum: ฿100,000

**Personal Allowances** (Applied Second):
- Personal allowance: ฿60,000
- Spouse allowance: ฿60,000
- Child allowances: ฿30,000 (first), ฿60,000 (subsequent, born 2018+)
- Provent Fund (7.5% of gross income)
- Social Security fund (5% of Gross income.)


### Calculation Flow Data Structure

```php
[
    'calculation_sequence' => 'Thai Revenue Department Official',
    'steps' => [
        '1_gross_income' => $totalAnnualIncome,
        '2_employment_deduction' => $employmentDeduction,
        '3_personal_allowances' => $personalAllowances,
        '4_other_deductions' => $otherDeductions,
        '5_taxable_income' => $taxableIncome,
        '6_progressive_tax' => $incomeTax,
        '7_social_security' => $socialSecurity,
        '8_net_salary' => $netSalary
    ],
    'compliance_validation' => $complianceResults
]
```

## Error Handling

### Validation Enhancements

**Thai Compliance Validation**:
- Employment deduction rate must be exactly 50%
- SSF rate must be exactly 5%
- Tax bracket progression validation
- Calculation sequence verification

**Error Categories**:
1. **Critical Errors**: Stop calculation (invalid rates, missing brackets)
2. **Warnings**: Log but continue (minor discrepancies)
3. **Compliance Issues**: Flag for review (sequence violations)

### Rollback Strategy

**Database Changes**:
- All migrations include proper `down()` methods
- Backup existing tax bracket data before updates
- Preserve calculation history integrity

**Configuration Rollback**:
- Maintain previous tax setting versions
- Allow switching between tax years
- Preserve audit trail during rollbacks

## Testing Strategy

### Unit Testing

**TaxCalculationService Tests**:
- Calculation sequence validation
- Individual method accuracy
- Thai compliance validation
- Edge case handling

**Model Tests**:
- TaxSetting validation methods
- TaxBracket progression logic
- Data integrity constraints

### Integration Testing

**API Endpoint Tests**:
- Complete payroll calculation workflows
- Tax bracket application accuracy
- Compliance validation responses

**Database Tests**:
- Migration rollback capabilities
- Data integrity preservation
- Historical calculation accuracy

### Compliance Testing

**Thai Revenue Department Validation**:
- Official calculation examples verification
- Sequence compliance testing
- Rate and bracket accuracy validation

**Regression Testing**:
- Existing calculation accuracy preservation
- Performance impact assessment
- API response consistency

## Implementation Phases

### Phase 1: Database and Model Updates
- Update tax bracket data
- Enhance TaxSetting model constants
- Create migration scripts with rollback

### Phase 2: Service Layer Enhancement
- Correct calculation sequence in TaxCalculationService
- Enhance validation methods
- Update audit logging

### Phase 3: API and Controller Updates
- Update controller responses
- Enhance error handling
- Add compliance validation endpoints

### Phase 4: Testing and Validation
- Comprehensive test suite execution
- Thai compliance verification
- Performance testing

### Phase 5: Documentation and Deployment
- Update API documentation
- Create deployment guides
- Prepare rollback procedures

## Security Considerations

### Data Protection
- Maintain encryption for sensitive payroll data
- Preserve audit trail integrity
- Secure tax calculation logs

### Access Control
- Maintain existing role-based permissions
- Add compliance validation permissions
- Secure migration execution rights

### Compliance Auditing
- Enhanced logging for all tax calculations
- Thai Revenue Department compliance tracking
- Historical calculation preservation

## Performance Considerations

### Optimization Strategies
- Maintain existing caching mechanisms
- Optimize tax bracket lookup performance
- Minimize database queries in calculations

### Monitoring
- Track calculation performance metrics
- Monitor compliance validation overhead
- Alert on calculation sequence violations

This design ensures the Thai tax compliance enhancement maintains system integrity while providing accurate, legally compliant tax calculations following official Thai Revenue Department regulations.