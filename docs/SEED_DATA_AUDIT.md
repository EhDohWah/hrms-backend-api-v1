# Seed Data Audit — HRMS Backend

> **Date:** 2026-02-08
> **Purpose:** Comprehensive inventory of all predefined/reference data the system requires, where it currently lives, what's missing, and the recommended seeding strategy for production deployment.

---

## Table of Contents

- [1. Core Organizational Structure](#1-core-organizational-structure)
- [2. Auth & Access Control](#2-auth--access-control)
- [3. Lookup / Reference Tables](#3-lookup--reference-tables)
- [4. Tax & Payroll Configuration](#4-tax--payroll-configuration)
- [5. System / UI Configuration](#5-system--ui-configuration)
- [6. Business Reference Data](#6-business-reference-data)
- [7. Dev / Test Only Data](#7-dev--test-only-data)
- [8. Gaps — Missing Seed Data](#8-gaps--missing-seed-data)
- [9. Current Data Location Summary](#9-current-data-location-summary)
- [10. Recommended Seeding Strategy](#10-recommended-seeding-strategy)
- [11. Enum-like Columns Reference](#11-enum-like-columns-reference)

---

## 1. Core Organizational Structure

Data that defines the organization's physical and logical structure. Required for employee records, reporting, and all HR operations.

### 1.1 Departments (21 records)

| Field | Details |
|-------|---------|
| **Table** | `departments` |
| **Model** | `App\Models\Department` |
| **Seed exists?** | Yes — inline in migration |
| **Location** | `database/migrations/2025_02_12_025437_create_departments_table.php` |
| **Environment** | Production |
| **Dependencies** | None |

**Seeded values:**

| # | Name | Description |
|---|------|-------------|
| 1 | Administration | Administrative operations and support services |
| 2 | Finance | Financial management and accounting operations |
| 3 | Grant | Grant management and funding oversight |
| 4 | Human Resources | Human Resources operations and employee management |
| 5 | Logistics | Logistics and transportation operations |
| 6 | Procurement & Store | Procurement and inventory management |
| 7 | Data Management | Data operations and management systems |
| 8 | IT | Information Technology services and support |
| 9 | Clinical | Clinical services and healthcare delivery |
| 10 | Medical | Medical services and physician oversight |
| 11 | Research/Study | Research operations and clinical studies |
| 12 | Training | Training programs and capacity building |
| 13 | Research/Study M&E | Research monitoring and evaluation |
| 14 | MCH | Maternal and Child Health programs |
| 15 | M&E | Monitoring and Evaluation operations |
| 16 | Laboratory | Laboratory services and testing |
| 17 | Malaria | Malaria prevention and control programs |
| 18 | Public Engagement | Public engagement and community outreach |
| 19 | TB | Tuberculosis prevention and treatment programs |
| 20 | Media Group | Media and communications management |
| 21 | Referral | Patient referral services and coordination |

---

### 1.2 Positions (~150 records)

| Field | Details |
|-------|---------|
| **Table** | `positions` |
| **Model** | `App\Models\Position` |
| **Seed exists?** | Yes — inline in migration |
| **Location** | `database/migrations/2025_02_12_025438_create_positions_table.php` |
| **Environment** | Production |
| **Dependencies** | `departments` (FK: `department_id`) |

Approximately 150 positions are seeded across all 21 departments with:
- Hierarchical `level` (1 = top, 4 = junior)
- `is_manager` flag
- `reports_to_position_id` relationships set up after initial insert

Each department has a complete reporting chain (e.g., Manager → Senior → Officer → Assistant).

---

### 1.3 Sites (11 records)

| Field | Details |
|-------|---------|
| **Table** | `sites` |
| **Model** | `App\Models\Site` |
| **Seed exists?** | Yes — inline in migration |
| **Location** | `database/migrations/2025_02_13_024725_create_sites_table.php` |
| **Environment** | Production |
| **Dependencies** | None |

**Seeded values:**

| Code | Name | Description |
|------|------|-------------|
| EXPAT | Expat | Expatriate Staff |
| KK_MCH | KK-MCH | KK-MCH site |
| TB_KK | TB-KK | TB-Koh Kong |
| MKT | MKT | MKT site |
| MRM | MRM | Mae Ramat |
| MSL | MSL | MSL site |
| MUTRAW | Mutraw | Mutraw site |
| TB_MRM | TB-MRM | TB-MRM |
| WP | WP | WP site |
| WPA | WPA | WPA site |
| YANGON | Yangon | Yangon site |

---

### 1.4 Section Departments

| Field | Details |
|-------|---------|
| **Table** | `section_departments` |
| **Model** | `App\Models\SectionDepartment` |
| **Seed exists?** | Partial — `SectionDepartmentSeeder` exists but is commented out in `DatabaseSeeder` |
| **Location** | `database/seeders/SectionDepartmentSeeder.php` |
| **Environment** | Production |
| **Dependencies** | `departments`, `lookups` (optional) |

The seeder creates sub-department sections. Falls back to 10 common sections if lookups aren't available: Training, Data Management, M&E, Administration, Finance, HR, IT Support, Procurement, Research, Outreach.

---

### 1.5 Organizations

| Field | Details |
|-------|---------|
| **Table** | `organizations` |
| **Model** | — |
| **Seed exists?** | **No** — SMRU and BHF only exist in the `lookups` table (type: `organization`), not in the dedicated `organizations` table |
| **Location** | — |
| **Environment** | Production |
| **Dependencies** | None |

---

## 2. Auth & Access Control

Data required for the system to be usable after deployment. Without this data, no one can log in.

### 2.1 Roles (2 core roles)

| Field | Details |
|-------|---------|
| **Table** | Spatie `roles` |
| **Seed exists?** | Yes |
| **Location** | `database/seeders/PermissionRoleSeeder.php` |
| **Environment** | Production |
| **Dependencies** | None |
| **Idempotent?** | Yes — uses `firstOrCreate` |

**Seeded values:**

| Role Slug | Display Name |
|-----------|-------------|
| `admin` | System Administrator |
| `hr-manager` | HR Manager |

> Additional roles (hr-assistant-senior, hr-assistant-junior, site-admin, etc.) are created dynamically via the Role Management UI by Admin/HR Manager.

---

### 2.2 Permissions (50+ records)

| Field | Details |
|-------|---------|
| **Table** | Spatie `permissions` |
| **Seed exists?** | Yes |
| **Location** | `database/seeders/PermissionRoleSeeder.php` |
| **Environment** | Production |
| **Dependencies** | `modules` (optional — has hardcoded fallback) |
| **Idempotent?** | Yes — uses `firstOrCreate` |

Permissions follow the format `{module}.{action}` with two actions per module: `read` and `edit`.

**Module permission groups:**

| Category | Modules | Permissions |
|----------|---------|-------------|
| Dashboard | `dashboard` | 2 |
| Grants | `grants_list`, `grant_position` | 4 |
| Recruitment | `interviews`, `job_offers` | 4 |
| Employee | `employees`, `employment_records`, `employee_resignation`, `employee_funding_allocations` | 8 |
| HRM | `holidays`, `resignation`, `termination` | 6 |
| Leaves | `leaves_admin`, `leaves_employee`, `leave_settings`, `leave_types`, `leave_balances` | 10 |
| Travel | `travel_admin`, `travel_employee` | 4 |
| Attendance | `attendance_admin`, `attendance_employee`, `timesheets`, `shift_schedule`, `overtime` | 10 |
| Training | `training_list`, `employee_training` | 4 |
| Payroll | `employee_salary`, `tax_settings`, `benefit_settings`, `payslip`, `payroll_items` | 10 |
| Lookups | `lookup_list` | 2 |
| Org Structure | `sites`, `departments`, `positions`, `section_departments` | 8 |
| User Mgmt | `users`, `roles` | 4 |
| Reports | 12 report modules | 24 |
| Administration | `file_uploads` | 2 |
| Recycle Bin | `recycle_bin_list` | 2 |

---

### 2.3 Modules (50+ records)

| Field | Details |
|-------|---------|
| **Table** | `modules` |
| **Model** | `App\Models\Module` |
| **Seed exists?** | Yes |
| **Location** | `database/seeders/ModuleSeeder.php` |
| **Environment** | Production |
| **Dependencies** | None |
| **Idempotent?** | Yes — uses `updateOrCreate` |

Each module record defines: `name`, `display_name`, `route`, `icon`, `read_permission`, `edit_permissions[]`, and `category`. These drive the sidebar menu and permission accordion UI on the frontend.

---

### 2.4 Default Users (2 records)

| Field | Details |
|-------|---------|
| **Table** | `users` |
| **Model** | `App\Models\User` |
| **Seed exists?** | Yes |
| **Location** | `database/seeders/UserSeeder.php` |
| **Environment** | Production |
| **Dependencies** | `roles`, `permissions` (from PermissionRoleSeeder) |
| **Idempotent?** | Yes — uses `firstOrCreate` |

**Seeded values:**

| Email | Name | Role | Permissions |
|-------|------|------|-------------|
| `admin@hrms.com` | System Administrator | `admin` | ALL |
| `hrmanager@hrms.com` | HR Manager | `hr-manager` | ALL |

> **Security note:** Default password is `password`. Must be changed immediately on first production deployment.

---

## 3. Lookup / Reference Tables

Predefined values used in dropdowns, validation, and business logic throughout the system.

### 3.1 Lookups (100+ records across 18 types)

| Field | Details |
|-------|---------|
| **Table** | `lookups` |
| **Model** | `App\Models\Lookup` |
| **Seed exists?** | Yes — inline in migration |
| **Location** | `database/migrations/2025_03_20_021311_create_lookups_table.php` |
| **Environment** | Production |
| **Dependencies** | None |

**Seeded lookup types and values:**

#### Gender
| Value | Label |
|-------|-------|
| M | Male |
| F | Female |

#### Organization
| Value |
|-------|
| SMRU |
| BHF |

#### Employee Status
| Value |
|-------|
| Expats (Local) |
| Local ID Staff |
| Local non ID Staff |

#### Nationality (7 values)
American, Australian, Burmese, N/A, Stateless, Taiwanese, Thai

#### Religion (5 values)
Buddhist, Hindu, Christian, Muslim, Other

#### Marital Status
Single, Married

#### Site (9 values)
Expat, MRM, WPA, KKH, TB-MRM, TB-KK, MKT, MSL, Mutraw

#### User Status
Active, Inactive

#### Interview Mode (4 values)
In-person, Virtual, Phone, Hybrid

#### Interview Status (3 values)
scheduled, completed, cancelled

#### Identification Types (6 values)
Certificate of Identity, Thai ID, 10 Years Card, Passport, Myanmar ID, N/A

#### Employee Language (5 values)
English, Thai, Burmese, Karen, French

#### Employee Education (3 values)
Bachelor, Master, PhD

#### Employee Initial — English (4 values)
Mr, Mrs, Ms, Dr

#### Employee Initial — Thai (4 values)
นาย, นางสาว, นาง, ดร

#### Pay Method (2 values)
Transferred to bank, Cash cheque

#### Section Department (12 values)
Training, Procurement & Stores, Data, Malaria Invitro, Entomology, Research, Clinical, M&E, Security, Transportation, Ultrasound, Delivery

#### Bank Name (7 values)
Bangkok Bank, Kasikorn Bank, Siam Commercial Bank, Krung Thai Bank, Bank of Ayudhya, TMBThanachart Bank, Government Savings Bank

---

### 3.2 Leave Types (11 records)

| Field | Details |
|-------|---------|
| **Table** | `leave_types` |
| **Model** | `App\Models\LeaveType` |
| **Seed exists?** | Yes — inline in migration |
| **Location** | `database/migrations/2025_03_16_021936_create_leave_management_tables.php` |
| **Environment** | Production |
| **Dependencies** | None |

**Seeded values:**

| Leave Type | Default Duration (Days) |
|-----------|------------------------|
| Annual Leave | 26 |
| Unpaid Leave | 0 |
| Traditional day-off | 13 |
| Sick Leave | 30 |
| Maternity Leave | 98 |
| Compassionate Leave | 5 |
| Career Development Training | 14 |
| Personal Leave | 3 |
| Military Leave | 60 |
| Sterilization Leave | 0 |
| Other | 0 |

---

### 3.3 Holidays

| Field | Details |
|-------|---------|
| **Table** | `holidays` |
| **Model** | `App\Models\Holiday` |
| **Seed exists?** | **No** |
| **Location** | — |
| **Environment** | Production |
| **Dependencies** | None |

> **Gap:** No public holidays are seeded. This affects leave balance calculations and attendance tracking. Holidays are typically added per year via the admin UI, but a baseline set of Thai/Myanmar public holidays should be seeded for the initial deployment.

---

## 4. Tax & Payroll Configuration

Financial configuration data required for payroll calculations. Based on Thai Revenue Department regulations.

### 4.1 Tax Brackets (8 records)

| Field | Details |
|-------|---------|
| **Table** | `tax_brackets` |
| **Model** | `App\Models\TaxBracket` |
| **Seed exists?** | Yes — inline in migration |
| **Location** | `database/migrations/2025_08_06_224759_create_tax_brackets_table.php` |
| **Environment** | Production |
| **Dependencies** | None |

**Thai Progressive Tax Brackets (2025):**

| Bracket | Income Range (THB) | Rate | Base Tax |
|---------|-------------------|------|----------|
| B1_EXEMPT | 0 – 150,000 | 0% | 0 |
| B2_5PCT | 150,001 – 300,000 | 5% | 0 |
| B3_10PCT | 300,001 – 500,000 | 10% | 7,500 |
| B4_15PCT | 500,001 – 750,000 | 15% | 27,500 |
| B5_20PCT | 750,001 – 1,000,000 | 20% | 65,000 |
| B6_25PCT | 1,000,001 – 2,000,000 | 25% | 115,000 |
| B7_30PCT | 2,000,001 – 5,000,000 | 30% | 365,000 |
| B8_35PCT | 5,000,001+ | 35% | 1,265,000 |

---

### 4.2 Tax Settings (15+ records)

| Field | Details |
|-------|---------|
| **Table** | `tax_settings` |
| **Model** | `App\Models\TaxSetting` |
| **Seed exists?** | Yes — inline in migration |
| **Location** | `database/migrations/2025_08_06_224808_create_tax_settings_table.php` |
| **Environment** | Production |
| **Dependencies** | None |

**Key settings seeded:**

| Setting Key | Value | Category |
|-------------|-------|----------|
| Employment Deduction Rate | 50% (max 100,000) | EMPLOYMENT |
| Personal Allowance | 60,000 | ALLOWANCE |
| Spouse Allowance | 60,000 (disabled) | ALLOWANCE |
| Child Allowance | 30,000 (disabled) | ALLOWANCE |
| Child Allowance (2018+) | 60,000 (disabled) | ALLOWANCE |
| SSF Rate | 5% | SOCIAL_SECURITY |
| SSF Min Salary | 1,650/month | SOCIAL_SECURITY |
| SSF Max Salary | 15,000/month | SOCIAL_SECURITY |
| SSF Max Monthly | 750/month | SOCIAL_SECURITY |
| SSF Max Yearly | 9,000/year | SOCIAL_SECURITY |
| PVD Fund Rate | 7.5% (max 500,000) | PROVIDENT_FUND |
| Saving Fund Rate | 7.5% (max 500,000) | PROVIDENT_FUND |
| Health Insurance Max | 25,000 | DEDUCTION |
| Life Insurance Max | 100,000 | DEDUCTION |
| Mortgage Interest Max | 100,000 | DEDUCTION |

---

### 4.3 Benefit Settings (5 records)

| Field | Details |
|-------|---------|
| **Table** | `benefit_settings` |
| **Model** | `App\Models\BenefitSetting` |
| **Seed exists?** | Yes |
| **Location** | `database/seeders/BenefitSettingSeeder.php` |
| **Environment** | Production |
| **Dependencies** | None |
| **Idempotent?** | Yes — uses `updateOrCreate` |

**Seeded values (effective 2025-01-01):**

| Setting Key | Value | Type |
|-------------|-------|------|
| `health_welfare_percentage` | 5.00% | percentage |
| `pvd_percentage` | 7.50% | percentage |
| `saving_fund_percentage` | 7.50% | percentage |
| `social_security_percentage` | 5.00% | percentage |
| `social_security_max_amount` | 750.00 THB | numeric |

---

### 4.4 Health Welfare Rate Tiers (hardcoded)

| Field | Details |
|-------|---------|
| **Table** | — (not in database) |
| **Location** | `app/Services/PayrollService.php` (hardcoded) |
| **Environment** | — |

These rates are hardcoded in the PayrollService, not stored in the database:

**Employee contribution (salary-based):**
| Salary Range | Monthly Contribution |
|-------------|---------------------|
| > 15,000 THB | 150 THB |
| 5,001 – 15,000 THB | 100 THB |
| ≤ 5,000 THB | 60 THB |

**Employer contribution:**
- **SMRU:** Pays for Non-Thai ID and Expat employees (same tier rates)
- **BHF:** Does NOT pay employer health welfare (0 THB)

> **Note:** Consider whether these should move to a database table (`health_welfare_tiers`) so they can be updated without code changes.

---

## 5. System / UI Configuration

### 5.1 Dashboard Widgets (17 records)

| Field | Details |
|-------|---------|
| **Table** | `dashboard_widgets` |
| **Model** | `App\Models\DashboardWidget` |
| **Seed exists?** | Yes |
| **Location** | `database/seeders/DashboardWidgetSeeder.php` |
| **Environment** | Production |
| **Dependencies** | None |
| **Idempotent?** | Yes — uses `updateOrCreate` |

**Seeded widgets:**

| Category | Widgets |
|----------|---------|
| General | welcome_card, quick_actions |
| HR | employee_stats, recent_hires, department_overview, probation_tracker, user_activity |
| Leave | leave_summary, pending_leave_requests, leave_calendar |
| Payroll | payroll_summary, payroll_upcoming |
| Attendance | attendance_today |
| Recruitment | open_positions, pending_interviews |
| Training | training_overview |
| Reports | reports_quick_access |

Each widget has a `required_permission` (null for general widgets), `is_default` flag, and size (`small`, `medium`, `large`, `full`).

---

## 6. Business Reference Data

### 6.1 Hub Grants (2 records)

| Field | Details |
|-------|---------|
| **Table** | `grants` |
| **Model** | `App\Models\Grant` |
| **Seed exists?** | Yes — inline in migration |
| **Location** | `database/migrations/2025_02_13_025153_create_grants_table.php` |
| **Environment** | Production |
| **Dependencies** | None |

**Seeded values:**

| Grant Code | Name | Organization |
|-----------|------|-------------|
| S0031 | Other Fund | SMRU |
| S22001 | General Fund | BHF |

These are the "hub" grants that serve as umbrella funding sources for each organization.

---

### 6.2 Grant Items (for hub grants)

| Field | Details |
|-------|---------|
| **Table** | `grant_items` |
| **Model** | `App\Models\GrantItem` |
| **Seed exists?** | **No** — code is commented out in migration |
| **Location** | `database/migrations/2025_02_13_025154_create_grant_items_table.php` (line 45, commented out) |
| **Environment** | Production |
| **Dependencies** | `grants` |

> **Gap:** The hub grants (S0031, S22001) exist but have no line items. Default items like "SMRU Staff" and "BHF Staff" were planned but commented out.

---

### 6.3 Letter Templates

| Field | Details |
|-------|---------|
| **Table** | `letter_templates` |
| **Model** | — |
| **Seed exists?** | **No** |
| **Location** | — |
| **Environment** | Production (optional) |
| **Dependencies** | None |

> **Gap:** No HR letter templates are seeded. Templates for offer letters, termination letters, etc. would need to be created via the admin UI or seeded.

---

## 7. Dev / Test Only Data

These seeders create fake data for development and testing. They should **never** run in production.

| # | Seeder | Table(s) | Records | Idempotent? | Known Issues |
|---|--------|----------|---------|-------------|--------------|
| 7.1 | `EmployeeSeeder` | `employees` | 100 fake employees | Yes (skips if data exists) | Uses invalid gender ('Other') and nationality ('British') values not in lookups |
| 7.2 | `GrantSeeder` | `grants` | 26 test grants (active, expired, ending soon) | **No** — duplicates on re-run | — |
| 7.3 | `InterviewSeeder` | `interviews` | 300 fake interviews | **No** — duplicates on re-run | — |
| 7.4 | `JobOfferSeeder` | `job_offers` | 100 fake job offers (mixed statuses) | **No** — duplicates on re-run | — |
| 7.5 | `ProbationAllocationSeeder` | Multiple (employees, employments, grants, probation_records, etc.) | 3 complex scenarios | **No** — duplicates on re-run | Creates its own departments, positions, grants, and employees for the scenarios |
| 7.6 | `SectionDepartmentSeeder` | `section_departments` | ~10 sections | Yes (checks existing) | Falls back to generic sections if lookups unavailable |

---

## 8. Gaps — Missing Seed Data

| # | What's Missing | Table | Impact | Priority |
|---|---------------|-------|--------|----------|
| 1 | **Holidays** | `holidays` | Leave balance calculations and attendance tracking have no public holiday awareness | High |
| 2 | **Organizations table data** | `organizations` | SMRU/BHF only exist in `lookups` — the dedicated `organizations` table is empty | Medium |
| 3 | **Grant items for hub grants** | `grant_items` | Hub grants exist but can't be used for funding allocations without line items | Medium |
| 4 | **Letter templates** | `letter_templates` | No HR letter templates available out of the box | Low |
| 5 | **Section departments** | `section_departments` | Seeder exists but commented out — sub-departments not created on fresh deploy | Low |

---

## 9. Current Data Location Summary

### Where production-required data lives today

| Location | What's There | Runs Automatically? |
|----------|-------------|-------------------|
| **Migrations** | Departments, Positions, Sites, Lookups (18 types), Leave Types, Tax Brackets, Tax Settings, Hub Grants | Yes — `php artisan migrate` |
| **Seeders** | Roles, Permissions, Modules, Default Users, Dashboard Widgets, Benefit Settings | **No** — requires explicit `php artisan db:seed` |
| **Hardcoded** | Health welfare rate tiers (in PayrollService) | N/A |

### The problem

Running only `php artisan migrate` on a fresh production server gives you:
- Departments, positions, sites, lookups, leave types, tax brackets, tax settings, hub grants

But **missing** (system is unusable):
- No users (can't log in)
- No roles or permissions (no access control)
- No modules (no sidebar menu)
- No dashboard widgets
- No benefit settings (payroll calculations incomplete)

---

## 10. Recommended Seeding Strategy

### Approach: `ProductionSeeder` + `DatabaseSeeder`

Following Laravel conventions, separate production-required data from dev/test data:

```
database/seeders/
├── ProductionSeeder.php            ← Production-required data
│   ├── PermissionRoleSeeder.php    ← Roles & permissions
│   ├── ModuleSeeder.php            ← Sidebar modules
│   ├── UserSeeder.php              ← Admin & HR Manager
│   ├── DashboardWidgetSeeder.php   ← Widget definitions
│   └── BenefitSettingSeeder.php    ← Payroll benefit settings
│
├── DatabaseSeeder.php              ← Dev/test (calls ProductionSeeder + fake data)
│   ├── ProductionSeeder            ← All production data
│   ├── EmployeeSeeder              ← 100 fake employees
│   ├── GrantSeeder                 ← 26 test grants
│   ├── SectionDepartmentSeeder     ← Sub-departments
│   ├── InterviewSeeder             ← 300 fake interviews
│   ├── JobOfferSeeder              ← 100 fake job offers
│   └── ProbationAllocationSeeder   ← 3 complex scenarios
```

### Deployment commands

```bash
# Production
php artisan migrate
php artisan db:seed --class=ProductionSeeder

# Local development
php artisan migrate --seed    # runs DatabaseSeeder (includes everything)
```

### Execution order and dependencies

```
1. php artisan migrate
   └── Creates schema + inline data:
       departments → positions → sites → lookups → leave_types
       → tax_brackets → tax_settings → hub grants

2. ProductionSeeder (run in this order):
   ├── 1. PermissionRoleSeeder  ← No dependencies
   ├── 2. ModuleSeeder          ← No dependencies
   ├── 3. UserSeeder            ← Depends on: roles, permissions
   ├── 4. DashboardWidgetSeeder ← No dependencies
   └── 5. BenefitSettingSeeder  ← No dependencies
```

---

## 11. Enum-like Columns Reference

These columns use string values constrained by CHECK constraints or application-level validation. Not stored in lookup tables — defined as constants on models or enforced by form requests.

### Models with Constants

| Model | Column | Valid Values |
|-------|--------|-------------|
| `PersonnelAction` | `action_type` | appointment, fiscal_increment, title_change, voluntary_separation, position_change, transfer |
| `PersonnelAction` | `action_subtype` | re_evaluated_pay_adjustment, promotion, demotion, end_of_contract, work_allocation |
| `PersonnelAction` | `transfer_type` | internal_department, site_to_site, attachment_position |
| `PersonnelAction` | `status` | pending, partial_approved, fully_approved, implemented |
| `ProbationRecord` | `event_type` | initial, extension, passed, failed |
| `LeaveRequest` | `status` | pending, approved, declined, cancelled |
| `Resignation` | `acknowledgement_status` | Pending, Acknowledged, Rejected |
| `HolidayCompensationRecord` | `status` | available, partially_used, exhausted, expired |
| `TravelRequest` | `transportation` | smru_vehicle, public_transportation, air, other |
| `TravelRequest` | `accommodation` | smru_arrangement, self_arrangement, other |
| `EmployeeFundingAllocation` | `status` | active, historical, terminated |
| `EmployeeFundingAllocation` | `salary_type` | probation_salary, pass_probation_salary |
| `AllocationChangeLog` | `change_type` | created, updated, deleted, transferred |
| `AllocationChangeLog` | `approval_status` | pending, approved, rejected |
| `AllocationChangeLog` | `change_source` | manual, system, import, api |
| `BulkPayrollBatch` | `status` | pending, processing, completed, failed |
| `TaxSetting` | `setting_type` | DEDUCTION, RATE, LIMIT, ALLOWANCE |
| `TaxSetting` | `category` | EMPLOYMENT, ALLOWANCE, SOCIAL_SECURITY, PROVIDENT_FUND, DEDUCTION, TEMPORARY |

### Enums

| Enum Class | Location | Values |
|-----------|----------|--------|
| `NotificationCategory` | `app/Enums/NotificationCategory.php` | 21 categories: DASHBOARD, GRANTS, RECRUITMENT, EMPLOYEE, HOLIDAYS, LEAVES, TRAVEL, ATTENDANCE, TRAINING, RESIGNATION, TERMINATION, PAYROLL, LOOKUPS, ORGANIZATION, USER_MANAGEMENT, REPORTS, FILE_UPLOADS, RECYCLE_BIN, IMPORT, SYSTEM, GENERAL |

### DashboardWidget Static Arrays

| Array | Values |
|-------|--------|
| Categories | general, hr, payroll, leave, attendance, recruitment, training, reports |
| Sizes | small, medium, large, full |

---

## Appendix: File Reference

| File | Purpose |
|------|---------|
| `database/migrations/2025_02_12_025437_create_departments_table.php` | Departments schema + seed |
| `database/migrations/2025_02_12_025438_create_positions_table.php` | Positions schema + seed |
| `database/migrations/2025_02_13_024725_create_sites_table.php` | Sites schema + seed |
| `database/migrations/2025_02_13_025153_create_grants_table.php` | Grants schema + hub grant seed |
| `database/migrations/2025_02_13_025154_create_grant_items_table.php` | Grant items schema (seed commented out) |
| `database/migrations/2025_03_16_021936_create_leave_management_tables.php` | Leave types schema + seed |
| `database/migrations/2025_03_20_021311_create_lookups_table.php` | Lookups schema + seed (18 types) |
| `database/migrations/2025_08_06_224759_create_tax_brackets_table.php` | Tax brackets schema + seed |
| `database/migrations/2025_08_06_224808_create_tax_settings_table.php` | Tax settings schema + seed |
| `database/seeders/PermissionRoleSeeder.php` | Roles + permissions |
| `database/seeders/ModuleSeeder.php` | Sidebar modules |
| `database/seeders/UserSeeder.php` | Default admin users |
| `database/seeders/DashboardWidgetSeeder.php` | Dashboard widget definitions |
| `database/seeders/BenefitSettingSeeder.php` | Benefit calculation settings |
| `database/seeders/EmployeeSeeder.php` | Dev only — 100 fake employees |
| `database/seeders/GrantSeeder.php` | Dev only — 26 test grants |
| `database/seeders/InterviewSeeder.php` | Dev only — 300 fake interviews |
| `database/seeders/JobOfferSeeder.php` | Dev only — 100 fake job offers |
| `database/seeders/ProbationAllocationSeeder.php` | Dev only — 3 complex probation scenarios |
| `database/seeders/SectionDepartmentSeeder.php` | Sub-department sections |
| `app/Services/PayrollService.php` | Hardcoded health welfare rate tiers |
| `app/Models/TaxSetting.php` | Tax setting constants and Thai 2025 defaults |
| `app/Models/TaxBracket.php` | Tax bracket constants |
| `app/Models/PersonnelAction.php` | Action type/subtype/transfer/status constants |
| `app/Enums/NotificationCategory.php` | Notification category enum |
