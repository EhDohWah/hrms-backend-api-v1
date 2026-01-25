# EMPLOYEE IMPORT/EXPORT ANALYSIS REPORT

**Generated:** 2026-01-23
**Analyst:** Claude Code CLI
**Version:** 1.0

---

## EXECUTIVE SUMMARY

This report provides a complete analysis of the Employee Import/Export system based on thorough investigation of the codebase, database schema, models, controllers, and existing implementations.

---

## 1. EMPLOYEE MODEL STRUCTURE

### File: `app/Models/Employee.php`

**Fillable Properties (41 fields):**
```
user_id, organization, staff_id, initial_en, initial_th, first_name_en,
last_name_en, first_name_th, last_name_th, gender, date_of_birth, status,
nationality, religion, social_security_number, tax_number, bank_name,
bank_branch, bank_account_name, bank_account_number, mobile_phone,
permanent_address, current_address, military_status, marital_status,
spouse_name, spouse_phone_number, emergency_contact_person_name,
emergency_contact_person_relationship, emergency_contact_person_phone,
father_name, father_occupation, father_phone_number, mother_name,
mother_occupation, mother_phone_number, driver_license_number, remark,
created_by, updated_by
```

**Casts:**
- `date_of_birth` → `date`
- `military_status` → `boolean`

**Relationships:**

| Relationship Name | Type | Related Model |
|---|---|---|
| `user()` | belongsTo | User |
| `employment()` | hasOne | Employment |
| `employments()` | hasMany | Employment |
| `employeeBeneficiaries()` | hasMany | EmployeeBeneficiary |
| `employeeIdentification()` | **hasOne** | EmployeeIdentification |
| `employeeFundingAllocations()` | hasMany | EmployeeFundingAllocation |
| `employeeLanguages()` | hasMany | EmployeeLanguage |
| `employeeChildren()` | hasMany | EmployeeChild |
| `employeeEducation()` | hasMany | EmployeeEducation |
| `employeeTrainings()` | hasMany | EmployeeTraining |
| `leaveRequests()` | hasMany | LeaveRequest |
| `leaveBalances()` | hasMany | LeaveBalance |
| `taxCalculationLogs()` | hasMany | TaxCalculationLog |

**Computed Attributes:**
- `getAgeAttribute()` - Calculates age from date_of_birth
- `getIdTypeAttribute()` - Returns `employeeIdentification->id_type`
- `getEligibleParentsCountAttribute()` - Counts parents with names for tax
- `getHasSpouseAttribute()` - Boolean based on marital_status or spouse_name

**Scopes:**
- `scopeForPagination($query)` - Optimized select for pagination
- `scopeWithOptimizedRelations($query)` - Eager loading optimization
- `scopeByOrganization($query, $organizations)`
- `scopeByStatus($query, $statuses)`
- `scopeByGender($query, $genders)`
- `scopeByAge($query, $age)`
- `scopeByIdType($query, $idTypes)`

---

## 2. DATABASE SCHEMA

### Table: `employees`

| Column | Type | Nullable | Index | Notes |
|---|---|---|---|---|
| id | bigint | NO | PK | Auto-increment |
| user_id | bigint | YES | FK→users | SET NULL on delete |
| organization | nvarchar(10) | NO | INDEX | **Required** |
| staff_id | nvarchar(50) | NO | INDEX | Part of unique constraint |
| initial_en | nvarchar(10) | YES | - | |
| initial_th | nvarchar(20) | YES | - | |
| first_name_en | nvarchar(255) | YES | INDEX (composite) | |
| last_name_en | nvarchar(255) | YES | INDEX (composite) | |
| first_name_th | nvarchar(255) | YES | - | |
| last_name_th | nvarchar(255) | YES | - | |
| gender | nvarchar(50) | NO | INDEX | **Required** |
| date_of_birth | date | NO | INDEX | **Required** |
| status | nvarchar(50) | NO | INDEX | **Required** |
| nationality | nvarchar(100) | YES | - | |
| religion | nvarchar(100) | YES | - | |
| social_security_number | nvarchar(50) | YES | - | |
| tax_number | nvarchar(50) | YES | - | |
| bank_name | nvarchar(100) | YES | - | |
| bank_branch | nvarchar(100) | YES | - | |
| bank_account_name | nvarchar(100) | YES | - | |
| bank_account_number | nvarchar(50) | YES | - | |
| mobile_phone | nvarchar(50) | YES | - | |
| permanent_address | nvarchar(max) | YES | - | TEXT type |
| current_address | nvarchar(max) | YES | - | TEXT type |
| military_status | nvarchar(50) | YES | - | String, not boolean |
| marital_status | nvarchar(50) | YES | - | |
| spouse_name | nvarchar(200) | YES | - | |
| spouse_phone_number | nvarchar(50) | YES | - | |
| emergency_contact_person_name | nvarchar(100) | YES | - | |
| emergency_contact_person_relationship | nvarchar(100) | YES | - | |
| emergency_contact_person_phone | nvarchar(50) | YES | - | |
| father_name | nvarchar(200) | YES | - | |
| father_occupation | nvarchar(200) | YES | - | |
| father_phone_number | nvarchar(50) | YES | - | |
| mother_name | nvarchar(200) | YES | - | |
| mother_occupation | nvarchar(200) | YES | - | |
| mother_phone_number | nvarchar(50) | YES | - | |
| driver_license_number | nvarchar(100) | YES | - | |
| remark | nvarchar(255) | YES | - | |
| created_at | datetime | YES | - | |
| updated_at | datetime | YES | - | |
| created_by | nvarchar(100) | YES | - | |
| updated_by | nvarchar(100) | YES | - | |

**Unique Constraint:** `employees_staff_id_organization_unique` on (staff_id, organization)

### Table: `employee_identifications`

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | bigint | NO | PK |
| employee_id | bigint | NO | FK→employees, CASCADE DELETE |
| id_type | nvarchar(50) | NO | Required |
| document_number | nvarchar(50) | NO | Required |
| issue_date | date | YES | |
| expiry_date | date | YES | |
| created_at | datetime | YES | |
| updated_at | datetime | YES | |
| created_by | nvarchar(100) | YES | |
| updated_by | nvarchar(100) | YES | |

**Note:** No unique constraint on employee_id - technically allows multiple identifications per employee, but model relationship is `hasOne`.

### Table: `employee_beneficiaries`

| Column | Type | Nullable | Notes |
|---|---|---|---|
| id | bigint | NO | PK |
| employee_id | bigint | NO | FK→employees, CASCADE DELETE |
| beneficiary_name | nvarchar(255) | NO | Required |
| beneficiary_relationship | nvarchar(255) | NO | Required |
| phone_number | nvarchar(15) | YES | Max 15 chars |
| created_at | datetime | YES | |
| updated_at | datetime | YES | |
| created_by | nvarchar(100) | YES | |
| updated_by | nvarchar(100) | YES | |

**Note:** No limit on number of beneficiaries per employee. No ordering/priority field.

---

## 3. VALID VALUES ENUMERATION

### Organization (DEFINITIVE)
- **Valid Values:** `SMRU`, `BHF`
- **Source:** Model OpenAPI schema, StoreEmployeeRequest, Controller validation
- **Database Current Values:** `SMRU` (only one organization has data)

### Status (INCONSISTENT - CRITICAL ISSUE)
Multiple definitions found:

| Source | Values |
|---|---|
| Model OpenAPI Schema | `Expats`, `Local ID`, `Local non ID` |
| StoreEmployeeRequest | `Expats (Local)`, `Local ID Staff`, `Local non ID Staff` |
| Controller update() | `Expats`, `Local ID`, `Local non ID` |
| Import Template | `Expats (Local)`, `Local ID Staff`, `Local non ID Staff` |
| Database Current | `Local ID Staff` |

**INCONSISTENCY:** Status values differ between OpenAPI schema/controller and StoreEmployeeRequest/template.

### Gender
- **Valid Values:** `M`, `F`
- **Source:** EmployeesImport rules validation

### ID Type
- **Migration Comment:** `ThaiID`, `10YearsID`, `Passport`, `Other`
- **Import Mapping:**
  ```php
  '10 years ID' => '10YearsID',
  'Burmese ID' => 'BurmeseID',
  'CI' => 'CI',
  'Borderpass' => 'Borderpass',
  'Thai ID' => 'ThaiID',
  'Passport' => 'Passport',
  'Other' => 'Other'
  ```
- **Database Current:** No records yet

### Marital Status
- **Template Dropdown:** `Single`, `Married`, `Divorced`, `Widowed`
- **No enforcement** in import validation rules (nullable|string|max:50)

### Military Status
- **Database Type:** nvarchar(50) - String field
- **Model Cast:** boolean (mismatch!)
- **Template Sample:** `Completed`, `Exempt`
- **No validation rules** defined for valid values

---

## 4. CURRENT IMPORT IMPLEMENTATION ANALYSIS

### File: `app/Imports/EmployeesImport.php`

**Structure:**
- Implements: `ToCollection`, `WithHeadingRow`, `WithStartRow`, `WithChunkReading`, `SkipsEmptyRows`, `SkipsOnFailure`, `WithEvents`, `WithCustomValueBinder`, `ShouldQueue`
- **Start Row:** 3 (Row 1 = Headers, Row 2 = Validation rules, Row 3+ = Data)
- **Chunk Size:** 40 rows

**Column Mapping (Import Header → Database Column):**

| Import Header | Database Column | Required |
|---|---|---|
| org | organization | NO |
| staff_id | staff_id | YES |
| initial | initial_en | NO |
| first_name | first_name_en | YES |
| last_name | last_name_en | NO |
| initial_th | initial_th | NO |
| first_name_th | first_name_th | NO |
| last_name_th | last_name_th | NO |
| gender | gender | YES |
| date_of_birth | date_of_birth | YES |
| status | status | NO |
| nationality | nationality | NO |
| religion | religion | NO |
| id_type | → employee_identifications.id_type | NO |
| id_no | → employee_identifications.document_number | NO |
| social_security_no | social_security_number | NO |
| tax_no | tax_number | NO |
| driver_license | driver_license_number | NO |
| bank_name | bank_name | NO |
| bank_branch | bank_branch | NO |
| bank_acc_name | bank_account_name | NO |
| bank_acc_no | bank_account_number | NO |
| mobile_no | mobile_phone | NO |
| current_address | current_address | NO |
| permanent_address | permanent_address | NO |
| marital_status | marital_status | NO |
| spouse_name | spouse_name | NO |
| spouse_mobile_no | spouse_phone_number | NO |
| emergency_name | emergency_contact_person_name | NO |
| relationship | emergency_contact_person_relationship | NO |
| emergency_mobile_no | emergency_contact_person_phone | NO |
| father_name | father_name | NO |
| father_occupation | father_occupation | NO |
| father_mobile_no | father_phone_number | NO |
| mother_name | mother_name | NO |
| mother_occupation | mother_occupation | NO |
| mother_mobile_no | mother_phone_number | NO |
| kin1_name | → employee_beneficiaries.beneficiary_name | NO |
| kin1_relationship | → employee_beneficiaries.beneficiary_relationship | NO |
| kin1_mobile | → employee_beneficiaries.phone_number | NO |
| kin2_name | → employee_beneficiaries.beneficiary_name | NO |
| kin2_relationship | → employee_beneficiaries.beneficiary_relationship | NO |
| kin2_mobile | → employee_beneficiaries.phone_number | NO |
| military_status | military_status | NO |
| remark | remark | NO |

**Validation Rules in Import:**
```php
'*.org' => 'nullable|string|max:10',
'*.staff_id' => 'required|string|max:50',
'*.first_name' => 'required|string|max:255',
'*.gender' => 'required|string|in:M,F',
'*.date_of_birth' => 'required|date',
'*.status' => 'nullable|string|max:20',
// ... all other fields nullable
```

**Key Features:**
- Pre-fetches existing staff_ids for duplicate checking
- Handles Excel numeric date format conversion
- Maps friendly id_type values to database values
- Removes 'age' formula column
- Uses cache for tracking import progress and errors
- Creates employee_identifications when id_type AND id_no both provided
- Creates up to 2 beneficiaries (kin1, kin2)
- Sends notifications via NotificationService

**Gaps Identified:**
1. No organization validation (allows any value)
2. No status validation against allowed values
3. No marital_status validation
4. No military_status validation
5. No cross-field validation (spouse info only when married)
6. staff_id uniqueness is per-import only, not per-organization
7. No issue_date/expiry_date for identifications

---

## 5. CURRENT EXPORT IMPLEMENTATION ANALYSIS

### File: `app/Exports/EmployeesExport.php`

**Structure:**
- Implements: `FromCollection` (basic implementation)
- **No headers row** - just raw data
- **No filtering** - exports ALL employees
- **No styling** - plain data export

**Current Export Mapping:**

| Export Key | Source |
|---|---|
| org | employee.organization |
| staff_id | employee.staff_id |
| initial | employee.initial_en |
| first_name | employee.first_name_en |
| ... | (matches import mapping) |
| id_type | employeeIdentification[0].id_type |
| id_no | employeeIdentification[0].document_number |
| kin1_* | employeeBeneficiaries[0].* |
| kin2_* | employeeBeneficiaries[1].* |

**Critical Bug:**
```php
$identification = $employee->employeeIdentification[0] ?? null;
```
This tries to access `employeeIdentification` as an array, but the relationship is `hasOne` (returns single model, not collection). Should be:
```php
$identification = $employee->employeeIdentification;
```

**Missing Features:**
1. No header row
2. No validation rules row
3. No filtering by organization, status, etc.
4. No Excel formatting/styling
5. No Excel dropdowns for re-import
6. No age formula column
7. No handling of more than 2 beneficiaries
8. Inconsistent date formatting

---

## 6. TEMPLATE STRUCTURE ANALYSIS

### Current Template (from `downloadEmployeeTemplate()`)

**Structure:**
- Row 1: Column headers (styled blue background)
- Row 2: Validation rules (styled yellow, italic)
- Row 3+: Sample data (2 rows)
- Frozen pane at A3

**Columns (46 total):**
A-AT spanning: org, staff_id, initial, first_name, last_name, initial_th, first_name_th, last_name_th, gender, date_of_birth, age (formula), status, nationality, religion, id_type, id_no, social_security_no, tax_no, driver_license, bank_name, bank_branch, bank_acc_name, bank_acc_no, mobile_no, current_address, permanent_address, marital_status, spouse_name, spouse_mobile_no, emergency_name, relationship, emergency_mobile_no, father_name, father_occupation, father_mobile_no, mother_name, mother_occupation, mother_mobile_no, kin1_name, kin1_relationship, kin1_mobile, kin2_name, kin2_relationship, kin2_mobile, military_status, remark

**Excel Dropdowns Configured:**
- Gender (Column I): M, F
- ID Type (Column O): 10 years ID, Burmese ID, CI, Borderpass, Thai ID, Passport, Other
- Status (Column L): Expats (Local), Local ID Staff, Local non ID Staff
- Marital Status (Column AA): Single, Married, Divorced, Widowed

**Age Formula:**
```
=DATEDIF(J{row},TODAY(),"Y")
```

---

## 7. COMPARISON WITH GRANTS IMPORT

### GrantsImport Best Practices Not in EmployeesImport:

| Feature | GrantsImport | EmployeesImport |
|---|---|---|
| Constants for valid values | `VALID_ORGANIZATIONS = ['SMRU', 'BHF']` | None |
| Validation constraints as constants | `GRANT_NAME_MIN_LENGTH = 3`, etc. | Hardcoded in rules |
| Levenshtein typo detection | Yes, for organization | No |
| Two-pass validation | Validate all first, then create | Single-pass |
| Warnings separate from errors | Yes | No |
| Skipped items tracking | Yes (`getSkippedGrants()`) | No |
| Row configuration constants | `DATA_START_ROW = 9`, etc. | Hardcoded `startRow(): 3` |
| Database transaction | Per-sheet atomic operation | Per-chunk |
| Header validation | Validates sheet structure | No structure validation |

---

## 8. ANSWERS TO BUSINESS REQUIREMENT QUESTIONS

### Q1: Organization Validation
- **Valid values:** SMRU, BHF only
- **Required:** YES (database column is NOT NULL)
- **Multiple organizations per employee:** NO (single value)
- **Master table:** NO (hardcoded in validation)
- **Set at import level:** NO (per-row in current implementation)

### Q2: Employee Status Values
- **Valid values:** `Expats (Local)`, `Local ID Staff`, `Local non ID Staff` (per StoreEmployeeRequest)
- **Required:** YES (database is NOT NULL)
- **Business rules:** None enforced currently

### Q3: Marital Status Values
- **Valid values:** `Single`, `Married`, `Divorced`, `Widowed` (template dropdown only)
- **Required:** NO (nullable)
- **Cross-field rules:** None enforced (spouse info allowed regardless of status)

### Q4: ID Type Standardization
- **Valid values (stored):** `10YearsID`, `BurmeseID`, `CI`, `Borderpass`, `ThaiID`, `Passport`, `Other`
- **Valid values (display):** `10 years ID`, `Burmese ID`, `CI`, `Borderpass`, `Thai ID`, `Passport`, `Other`
- **Required:** NO
- **Multiple IDs:** Database allows (no unique constraint), but model relationship is `hasOne`

### Q5: Beneficiary Business Rules
- **Maximum:** No limit in database
- **Required:** NO
- **Export handling for >2:** Only first 2 exported
- **Valid relationships:** Not enforced

### Q6: Spouse Information Rules
- **Only when married:** Not enforced
- **Together validation:** Not enforced

### Q7: Emergency Contact Rules
- **Required:** NO
- **Same as beneficiary:** Not validated
- **Valid relationships:** Not enforced

### Q8: Phone Number Format
- **Format:** Not validated (string only)
- **Max length:** 50 chars for most, 15 for beneficiary phone

### Q9: Bank Information Rules
- **Required:** NO
- **Valid bank names:** Not enforced (free text)
- **Multiple accounts:** NO (single set of fields)

### Q10: Age and Date of Birth Rules
- **Minimum age:** Not enforced
- **Maximum age:** Not enforced
- **Age range validation:** None

### Q11: Gender Values
- **Valid values:** M, F only
- **Required:** YES (in import)
- **Other/X support:** NO

### Q12: Nationality and Religion
- **Valid values:** Free text (not enforced)
- **Required:** NO

### Q13: Staff ID Format
- **Format:** Not enforced (max 50 chars)
- **Auto-generation:** NO
- **Pattern:** None enforced

### Q14: Military Status Values
- **Valid values:** Not enforced (free text)
- **Sample values:** `Completed`, `Exempt`
- **Data type mismatch:** Database is string, model casts to boolean

### Q15: Employment Relationship
- **Standalone employee:** YES (employee can exist without employment)
- **Default employment on import:** NO

---

## 9. GAPS AND INCONSISTENCIES

### Critical Issues

1. **Status value inconsistency** - OpenAPI schema says `Expats`, `Local ID`, `Local non ID` but StoreEmployeeRequest and template use `Expats (Local)`, `Local ID Staff`, `Local non ID Staff`

2. **Military status type mismatch** - Database stores string, model casts to boolean

3. **Export identification bug** - Code accesses `employeeIdentification[0]` but relationship is `hasOne` (not collection)

4. **staff_id uniqueness** - Database constraint is on (staff_id, organization) but import only checks staff_id globally

5. **Organization not validated in import** - Can import invalid organization values

### Missing Features

1. No organization-level import (like Grant Import)
2. No export filtering
3. No export headers/styling to match import template
4. No issue_date/expiry_date for identifications in import
5. No validation for constrained fields (marital_status, military_status, etc.)
6. No cross-field validation rules
7. No age validation
8. No phone format validation
9. No two-pass validation approach

### Inconsistencies

| Area | Import | Export | Database | Controller |
|---|---|---|---|---|
| Status values | Template has `Expats (Local)` | No validation | String | `Expats`, `Local ID` |
| Identification | hasMany behavior | Tries array access | Allows multiple | hasOne relationship |
| Beneficiaries | Creates 2 max | Exports 2 max | No limit | hasMany |
| Organization | No validation | No filtering | NOT NULL | `SMRU`, `BHF` |

---

## 10. RECOMMENDATIONS SUMMARY

### High Priority

1. **Standardize status values** across all sources (Model, Request, Controller, Import, Export)
2. **Fix military_status** - remove boolean cast or change to string-based enum validation
3. **Fix export identification bug** - access as single model, not array
4. **Add organization validation** to import with Levenshtein distance for typo detection
5. **Fix staff_id uniqueness check** - should be per-organization, not global

### Medium Priority

6. **Make export match import template** format exactly (headers, styling, dropdowns)
7. **Add filtering to export** (organization, status, date ranges)
8. **Implement two-pass validation** like GrantsImport
9. **Add constants for valid values** in import class
10. **Add identification dates** (issue_date, expiry_date) to import template

### Low Priority

11. **Consider organization-level import** like Grant Import
12. **Add cross-field validation** (spouse info only when married)
13. **Add age range validation** for date_of_birth
14. **Add phone number format validation**
15. **Add warnings separate from errors** in import

---

## 11. FILES ANALYZED

| File | Path |
|---|---|
| Employee Model | `app/Models/Employee.php` |
| EmployeeIdentification Model | `app/Models/EmployeeIdentification.php` |
| EmployeeBeneficiary Model | `app/Models/EmployeeBeneficiary.php` |
| EmployeesImport | `app/Imports/EmployeesImport.php` |
| EmployeesExport | `app/Exports/EmployeesExport.php` |
| EmployeeController | `app/Http/Controllers/Api/EmployeeController.php` |
| StoreEmployeeRequest | `app/Http/Requests/StoreEmployeeRequest.php` |
| UploadEmployeeImportRequest | `app/Http/Requests/UploadEmployeeImportRequest.php` |
| GrantsImport (reference) | `app/Imports/GrantsImport.php` |
| Employees Migration | `database/migrations/2025_02_12_131510_create_employees_table.php` |
| Identifications Migration | `database/migrations/2025_03_15_150319_create_employee_identifications_table.php` |
| Beneficiaries Migration | `database/migrations/2025_03_15_150402_create_employee_beneficiaries_table.php` |

---

## APPENDIX A: COLUMN MAPPING REFERENCE

### Import Template Columns (A-AT)

| Col | Header | DB Field | Table | Required | Validation |
|---|---|---|---|---|---|
| A | org | organization | employees | NO* | SMRU, BHF |
| B | staff_id | staff_id | employees | YES | unique per org |
| C | initial | initial_en | employees | NO | max:10 |
| D | first_name | first_name_en | employees | YES | max:255 |
| E | last_name | last_name_en | employees | NO | max:255 |
| F | initial_th | initial_th | employees | NO | max:20 |
| G | first_name_th | first_name_th | employees | NO | max:255 |
| H | last_name_th | last_name_th | employees | NO | max:255 |
| I | gender | gender | employees | YES | M, F |
| J | date_of_birth | date_of_birth | employees | YES | date |
| K | age | (formula) | - | NO | calculated |
| L | status | status | employees | NO* | see values |
| M | nationality | nationality | employees | NO | max:100 |
| N | religion | religion | employees | NO | max:100 |
| O | id_type | id_type | employee_identifications | NO | see values |
| P | id_no | document_number | employee_identifications | NO | max:50 |
| Q | social_security_no | social_security_number | employees | NO | max:50 |
| R | tax_no | tax_number | employees | NO | max:50 |
| S | driver_license | driver_license_number | employees | NO | max:100 |
| T | bank_name | bank_name | employees | NO | max:100 |
| U | bank_branch | bank_branch | employees | NO | max:100 |
| V | bank_acc_name | bank_account_name | employees | NO | max:100 |
| W | bank_acc_no | bank_account_number | employees | NO | max:50 |
| X | mobile_no | mobile_phone | employees | NO | max:50 |
| Y | current_address | current_address | employees | NO | text |
| Z | permanent_address | permanent_address | employees | NO | text |
| AA | marital_status | marital_status | employees | NO | see values |
| AB | spouse_name | spouse_name | employees | NO | max:200 |
| AC | spouse_mobile_no | spouse_phone_number | employees | NO | max:50 |
| AD | emergency_name | emergency_contact_person_name | employees | NO | max:100 |
| AE | relationship | emergency_contact_person_relationship | employees | NO | max:100 |
| AF | emergency_mobile_no | emergency_contact_person_phone | employees | NO | max:50 |
| AG | father_name | father_name | employees | NO | max:200 |
| AH | father_occupation | father_occupation | employees | NO | max:200 |
| AI | father_mobile_no | father_phone_number | employees | NO | max:50 |
| AJ | mother_name | mother_name | employees | NO | max:200 |
| AK | mother_occupation | mother_occupation | employees | NO | max:200 |
| AL | mother_mobile_no | mother_phone_number | employees | NO | max:50 |
| AM | kin1_name | beneficiary_name | employee_beneficiaries | NO | max:255 |
| AN | kin1_relationship | beneficiary_relationship | employee_beneficiaries | NO | max:255 |
| AO | kin1_mobile | phone_number | employee_beneficiaries | NO | max:15 |
| AP | kin2_name | beneficiary_name | employee_beneficiaries | NO | max:255 |
| AQ | kin2_relationship | beneficiary_relationship | employee_beneficiaries | NO | max:255 |
| AR | kin2_mobile | phone_number | employee_beneficiaries | NO | max:15 |
| AS | military_status | military_status | employees | NO | max:50 |
| AT | remark | remark | employees | NO | max:255 |

*Note: organization and status are NOT NULL in database but nullable in import validation

---

## APPENDIX B: VALID VALUES QUICK REFERENCE

```php
// Organization
const VALID_ORGANIZATIONS = ['SMRU', 'BHF'];

// Status (use StoreEmployeeRequest version)
const VALID_STATUSES = ['Expats (Local)', 'Local ID Staff', 'Local non ID Staff'];

// Gender
const VALID_GENDERS = ['M', 'F'];

// ID Type (display → stored)
const ID_TYPE_MAP = [
    '10 years ID' => '10YearsID',
    'Burmese ID' => 'BurmeseID',
    'CI' => 'CI',
    'Borderpass' => 'Borderpass',
    'Thai ID' => 'ThaiID',
    'Passport' => 'Passport',
    'Other' => 'Other',
];

// Marital Status
const VALID_MARITAL_STATUSES = ['Single', 'Married', 'Divorced', 'Widowed'];

// Military Status (suggested standardization)
const VALID_MILITARY_STATUSES = ['Completed', 'Exempt', 'In Service', 'Not Applicable'];
```
