# HRMS Database Seeders Documentation

## Overview
This document describes all database seeders, their purpose, dependencies, and execution order.

## Seeder Execution Order (DatabaseSeeder.php)

The seeders must be executed in this specific order due to foreign key dependencies:

1. **SubsidiarySeeder** - Creates company subsidiaries (SMRU, BHF)
2. **LeaveTypeSeeder** - Creates leave types with default durations
3. **TrainingSeeder** - Creates sample training programs
4. **UserSeeder** - Creates default system users with roles
5. **EmployeeSeeder** - Creates sample employee records
6. **SectionDepartmentSeeder** - Creates sub-departments from lookups
7. **ProbationAllocationSeeder** - Creates complex employment scenarios
8. **BenefitSettingSeeder** - Creates benefit percentage settings
9. **GrantSeeder** (optional) - Creates additional test grants

## Detailed Seeder Descriptions

### 1. SubsidiarySeeder
**Purpose:** Seeds the two main subsidiaries
**Creates:**
- SMRU (Shoklo Malaria Research Unit)
- BHF (Burnet Health Foundation)

**Dependencies:** None

**Note:** Hub grants (S0031 for SMRU, S22001 for BHF) are created in migrations

---

### 2. LeaveTypeSeeder
**Purpose:** Seeds all leave types with default durations
**Creates 11 Leave Types:**
1. Annual Leave (26 days)
2. Unpaid Leave (0 days)
3. Traditional day-off (13 days)
4. Sick Leave (30 days)
5. Maternity Leave (98 days)
6. Compassionate Leave (5 days)
7. Career Development Training (14 days)
8. Personal Leave (3 days)
9. Military Leave (60 days)
10. Sterilization Leave (0 days)
11. Other (0 days)

**Dependencies:** None

**Note:** Currently created in migration, but this seeder provides explicit control

---

### 3. TrainingSeeder
**Purpose:** Creates sample training programs for testing
**Creates:**
- 10 diverse training programs with realistic titles, organizers, dates

**Dependencies:** None

---

### 4. UserSeeder
**Purpose:** Creates default system users for all roles
**Creates 5 Users:**

| Email | Password | Role | Permissions |
|-------|----------|------|-------------|
| admin@hrms.com | password | admin | All |
| hrmanager@hrms.com | password | hr-manager | All |
| hrassistant.senior@hrms.com | password | hr-assistant-senior | All |
| hrassistant.junior@hrms.com | password | hr-assistant-junior | Limited |
| siteadmin@hrms.com | password | site-admin | Site-specific |

**Dependencies:**
- Requires roles to exist (created in migration: 2025_03_03_092449)
- Requires permissions to exist

**Important:**
- Role names are lowercase with hyphens
- Migration already creates these users, so seeder uses `firstOrCreate` to avoid duplicates

---

### 5. EmployeeSeeder
**Purpose:** Creates 100 sample employee records for testing
**Creates:**
- 100 employees with randomized but valid data
- Uses Faker for realistic data generation
- Only runs if employees table is empty

**Dependencies:**
- User (optional - user_id is nullable)

**Field Validation:**
- Subsidiary: Only 'SMRU' or 'BHF'
- Gender: Only 'Male' or 'Female' (matches lookups)
- Status: 'Expats', 'Local ID', 'Local non ID'
- Nationality: Thai, Myanmar, American, British, Australian (common values)
- Religion: Buddhist, Hindu, Christian, Muslim, Other

---

### 6. SectionDepartmentSeeder
**Purpose:** Creates sub-departments under departments
**Logic:**
1. Attempts to link section_department lookups to matching departments
2. Falls back to creating 10 common sections under first department

**Dependencies:**
- Department (created in migration)
- Lookups (optional - has fallback)

**Common Sections Created:**
- Training, Data Management, M&E, Administration, Finance, HR, IT Support, Procurement, Research, Outreach

---

### 7. ProbationAllocationSeeder
**Purpose:** Creates comprehensive employment scenarios with probation tracking
**Creates 3 Complex Scenarios:**

#### Scenario 1: Org-Funded 100% Probation (Initial)
- Department: Org Probation Dept
- Position: Org Funded Analyst
- Site: Org Probation HQ
- Employee: Olivia Orgseed (EMP-ORG-100)
- Employment: Full-time, probation salary 24,000 → post-probation 36,000
- Funding: 100% direct grant allocation (simplified architecture)
- Probation: Initial record only

#### Scenario 2: Multi-Source 70/30 Split with Extension & Failure
- Department: Hybrid Funding Dept
- Position: Hybrid Support Officer
- Site: Hybrid Campus
- Employee: Harper Hybrid (EMP-HYB-7030)
- Employment: Full-time, probation 26,000 → post-probation 40,000
- Funding:
  - 70% direct grant allocation
  - 30% grant item allocation
- Probation: Initial → Extended → Failed
- Extension: 45 days extension, then failed probation

#### Scenario 3: Tri-Source 30/30/30 with Passed Probation
- Department: Advanced Research Dept
- Position: Research Fellow
- Site: R&D Lab
- Employee: Riley Researcher (EMP-RES-303030)
- Employment: Full-time, probation 36,000 → post-probation 52,000
- Funding:
  - 30% direct grant allocation
  - 30% grant item A allocation
  - 30% grant item B allocation
- Probation: Initial → Passed
- Benefits: All enabled (health_welfare, pvd, saving_fund)

**Dependencies:**
- Department (creates new test departments)
- Position (creates new test positions)
- Site (creates new test sites)
- Grant (creates test grants)
- GrantItem (creates grant items)
- Employee (creates test employees)
- Employment (creates employment records)
- EmployeeFundingAllocation (creates allocations)
- ProbationRecord (creates probation events)

**Important Changes from Old Version:**
- ✅ Removed: `WorkLocation` → Replaced with `Site`
- ✅ Removed: `OrgFundedAllocation` → Uses direct grant allocation
- ✅ Removed: `PositionSlot` → Direct `GrantItem` reference
- ✅ Updated: Uses new `employee_funding_allocations` architecture

---

### 8. BenefitSettingSeeder
**Purpose:** Seeds benefit percentage settings
**Creates 5 Settings:**
1. Health & Welfare: 5.00%
2. PVD (Provident Fund): 7.5%
3. Saving Fund: 7.5%
4. Social Security: 5.00%
5. Social Security Max: 750 THB

**Dependencies:** None

**Features:**
- Uses `updateOrCreate` to avoid duplicates
- Settings are effective from 2025-01-01
- All settings marked as active

---

### 9. GrantSeeder (Optional - Commented Out)
**Purpose:** Creates additional diverse grants for testing
**Creates:**
- 8 Active Research Grants (4 SMRU, 4 BHF)
- 6 Operational Grants (3 SMRU, 3 BHF)
- 8 Expired Grants (4 SMRU, 4 BHF)
- 4 Ending Soon Grants (2 SMRU, 2 BHF)
- **Total: 26 test grants**

**Dependencies:**
- Grant factory
- Subsidiary

**Note:**
- Migration already creates 2 hub grants (S0031, S22001)
- This seeder adds additional grants for testing
- Uses factory states for variety

---

## Additional Seeders (Permission-Related)

### PersonnelActionPermissionSeeder
**Purpose:** Seeds permissions for personnel action module
**Run:** Manually when personnel action feature is deployed

### BulkPayrollPermissionSeeder
**Purpose:** Seeds permissions for bulk payroll creation
**Run:** Manually when bulk payroll feature is deployed

### BenefitSettingPermissionSeeder
**Purpose:** Seeds permissions for benefit settings management
**Run:** Manually when benefit settings feature is deployed

### PermissionRoleSeeder
**Purpose:** Seeds all system permissions and assigns to roles
**Note:** May be redundant since migration 2025_03_03_092449 creates roles & users

---

## Data Already Seeded in Migrations

These data sets are created in migrations and don't need separate seeders:

### 1. Departments (19 departments)
**Migration:** 2025_02_12_025437_create_departments_table.php
- Administration, Finance, Grant, Human Resources, Logistics, Procurement & Store, Data management, IT, Clinical, Research/Study, Training, Research/Study M&E, MCH, M&E, Laboratory, Malaria, Public Engagement, TB, Media Group

### 2. Positions (150+ positions)
**Migration:** 2025_02_12_025438_create_positions_table.php
- Complete organizational hierarchy across all departments
- Reporting structures established

### 3. Sites (13 sites)
**Migration:** 2025_02_13_024725_create_sites_table.php
- MRM, Expat, TB-KK, TB-TK, TB-KDL, TB-PP, TB-SMR, TB-SR, TB-SHV, Pailin (TB), TB-BMC, Battambang (TB), Preah Vihear (TB)

### 4. Lookups (100+ lookup values)
**Migration:** 2025_03_20_021311_create_lookups_table.php
- gender, organization, employee_status, nationality, religion, marital_status, site, user_status, interview_mode, interview_status, identification_types, employment_type, employee_language, employee_education, employee_initial_en, employee_initial_th, pay_method, section_department, bank_name

### 5. Grants (2 hub grants)
**Migration:** 2025_02_13_025153_create_grants_table.php
- S0031 (SMRU hub grant - Other Fund)
- S22001 (BHF hub grant - General Fund)

### 6. Leave Types (11 types)
**Migration:** 2025_03_16_021936_create_leave_management_tables.php
- All 11 leave types with default durations

### 7. Tax Brackets (8 Thai brackets)
**Migration:** 2025_08_06_224759_create_tax_brackets_table.php
- Thai progressive tax system 2025 (0% to 35%)

### 8. Tax Settings (10+ settings)
**Migration:** 2025_08_06_224808_create_tax_settings_table.php
- Personal allowances, PVD limits, SSF rates, employment deductions

### 9. Default Users & Roles (5 users, 5 roles)
**Migration:** 2025_03_03_092449_create_default_user_and_roles.php
- Creates all default users with correct roles
- admin, hr-manager, hr-assistant-senior, hr-assistant-junior, site-admin

---

## Running Seeders

### Run All Seeders:
```bash
php artisan db:seed
```

### Run Specific Seeder:
```bash
php artisan db:seed --class=EmployeeSeeder
php artisan db:seed --class=ProbationAllocationSeeder
```

### Fresh Migration + Seed:
```bash
php artisan migrate:fresh --seed
```

---

## Important Notes

### 1. Idempotency
Most seeders check if data exists before creating:
- **EmployeeSeeder**: Only runs if employees table is empty
- **BenefitSettingSeeder**: Uses `updateOrCreate`
- **UserSeeder**: Uses `firstOrCreate`

### 2. Audit Fields
All seeders populate:
- `created_by = 'Seeder'` or `'system'`
- `updated_by = 'Seeder'` or `'system'`

### 3. Encryption
- Payroll seeder is commented out because payroll fields are encrypted
- Payroll should be created through PayrollService, not direct seeding

### 4. Simplified Architecture (Nov 2025)
The funding architecture was simplified from 5 tables to 3:
- **Removed:** `position_slots`, `org_funded_allocations`
- **Direct:** `grants` → `grant_items` → `employee_funding_allocations`

**ProbationAllocationSeeder has been updated to reflect this change**

### 5. Foreign Key Dependencies
Seeders must run in order to satisfy foreign key constraints:
- Subsidiary → Grant → GrantItem → EmployeeFundingAllocation
- Department → Position → Employment
- Employee → Employment → ProbationRecord

---

## Troubleshooting

### Issue: Foreign Key Constraint Fails
**Solution:** Ensure dependent data exists (check migration order)

### Issue: Duplicate Entry Error
**Solution:** Run `php artisan migrate:fresh --seed` to reset database

### Issue: User Seeder Fails (Role not found)
**Solution:** Ensure migration 2025_03_03_092449 has run (creates roles)

### Issue: ProbationAllocationSeeder Fails (Class not found)
**Solution:** Old classes removed - use updated version in this commit

---

## Testing Data Summary

After running all seeders, you'll have:
- ✅ 2 Subsidiaries (SMRU, BHF)
- ✅ 19 Departments (from migration)
- ✅ 150+ Positions (from migration)
- ✅ 13 Sites (from migration)
- ✅ 11 Leave Types (from migration/seeder)
- ✅ 100 Employees (faker data)
- ✅ 5 Users with roles (from migration/seeder)
- ✅ 3 Complex employment scenarios with probation tracking
- ✅ 5 Benefit settings
- ✅ 10 Training programs
- ✅ 2 Hub grants (from migration) + optional 26 test grants
- ✅ 8 Tax brackets (from migration)
- ✅ 10+ Tax settings (from migration)
- ✅ 100+ Lookup values (from migration)

This provides a comprehensive testing environment covering all HRMS modules.
