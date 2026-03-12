# Database Design Research: Employment Dates, Transfers & Personnel Actions

## Overview

This document analyzes the complex date relationships across the HRMS database — specifically how `employment.start_date`, personnel action `effective_date`, and transfer timing interact with payroll calculations. This is critical because the system serves a **dual-organization** (SMRU + BHF) environment where employees can transfer between organizations while maintaining continuous employment history.

---

## 1. The Core Date Problem

### The Scenario

> Employee X joined SMRU in 2005. In 2025, they transfer from SMRU to BHF. Their position and salary also change (personnel action). The original `start_date` (2005) must be preserved because it determines:
> - 13-month salary calculation (YTD aggregation)
> - Annual salary increment eligibility (must have started before the pay year)
> - Probation status (long passed)
> - Years of service for HR reporting

### Current Schema Reality

The `employments` table has **ONE** `start_date` field and no `transfer_date` or `effective_date` field:

```
employments
├── start_date (DATE, NOT NULL)      ← Original employment commencement
├── end_date (DATE, nullable)        ← Only set on resignation/separation
├── pass_probation_date (DATE)       ← First day at post-probation salary
├── end_probation_date (DATE)        ← Last day of probation
├── position_id (FK)                 ← Current position (overwritten on transfer)
├── department_id (FK)               ← Current department (overwritten on transfer)
├── site_id (FK)                     ← Current site (overwritten on transfer)
└── pass_probation_salary (DECIMAL)  ← Current salary (overwritten on transfer)
```

**Key observation**: When a transfer or action change happens, `position_id`, `department_id`, `site_id`, and `pass_probation_salary` are **overwritten in-place**. The `start_date` is never modified.

---

## 2. How Each Date Is Used in the System

### 2.1 Employment `start_date`

| Usage | Location | Logic |
|-------|----------|-------|
| **Mid-month pro-rating** | `PayrollService::calculateGrossSalaryCurrentYearByFTE()` | Compares `start_date` year+month with pay period to determine first-month pro-rating |
| **Deferred salary** | `PayrollService::calculateDeferredSalary()` | If start day >= 16, salary deferred to next month |
| **Annual increment eligibility** | `PayrollService::calculateAnnualSalaryIncrease()` | `start_date.year` must be < `payPeriodDate.year` for January increase |
| **Probation calculation** | `ProbationTransitionService` | `pass_probation_date` is typically `start_date + 3 months` |
| **Employment history audit** | `EmploymentHistory` | Snapshot of `start_date` at each change |

**Critical rule**: `start_date` represents the **original employment commencement date** — the very first day the employee joined the organization. It is NEVER changed by transfers or action changes.

### 2.2 Personnel Action `effective_date`

| Usage | Location | Logic |
|-------|----------|-------|
| **Separation end date** | `PersonnelActionService::handleSeparation()` | Sets `employment.end_date = effective_date` |
| **Audit trail** | `personnel_actions` table | Records when the action is intended to take effect |
| **Approval timing** | `StorePersonnelActionRequest` | Must be `after_or_equal:today` on creation |

**Key gap**: For non-separation actions (transfer, position change, fiscal increment), the `effective_date` is stored for audit purposes but is **not used to update any employment date field**. The employment changes take effect immediately when the action is fully approved (`implemented_at = now()`), regardless of what `effective_date` says.

### 2.3 `implemented_at` (Personnel Action)

| Usage | Location | Logic |
|-------|----------|-------|
| **Change application timestamp** | `PersonnelActionService::implementAction()` | Set to `now()` when all 4 approvals complete |
| **Immutability gate** | `PersonnelActionController` | Blocks update/delete once set |
| **Status computation** | `PersonnelAction::getStatusAttribute()` | Determines 'implemented' status |

**Key gap**: `implemented_at` is a system timestamp (when approvals completed), NOT the business-effective date of the change. There's a disconnect between "when the change was approved" and "when it should take effect."

### 2.4 Employment History `change_date`

| Usage | Location | Logic |
|-------|----------|-------|
| **Audit timestamp** | `Employment::addHistoryEntry()` | Records when each employment change was made |
| **Change tracking** | `employment_histories` table | Full snapshot with `changes_made` and `previous_values` JSON |

---

## 3. Current Table Schemas

### 3.1 `employees` Table

```sql
CREATE TABLE employees (
    id              BIGINT PRIMARY KEY IDENTITY,
    user_id         BIGINT NULL REFERENCES users(id),
    organization    VARCHAR(10) NOT NULL,           -- 'SMRU' or 'BHF'
    staff_id        VARCHAR(50) NOT NULL,
    -- personal info fields ...
    status          VARCHAR(50) NOT NULL,           -- 'Local ID Staff', 'Local non ID Staff', 'Expats (Local)'
    -- UNIQUE(staff_id, organization) WHERE deleted_at IS NULL
);
```

**Organization is at the EMPLOYEE level**, not employment level. This means:
- An employee "belongs to" one organization
- When they transfer SMRU → BHF, the `employees.organization` field needs to be updated
- The `employments` table has NO organization field

### 3.2 `employments` Table

```sql
CREATE TABLE employments (
    id                      BIGINT PRIMARY KEY IDENTITY,
    employee_id             BIGINT NOT NULL REFERENCES employees(id) CASCADE,
    position_id             BIGINT NULL REFERENCES positions(id),
    department_id           BIGINT NULL REFERENCES departments(id),
    section_department_id   BIGINT NULL REFERENCES section_departments(id),
    site_id                 BIGINT NULL REFERENCES sites(id),
    pay_method              VARCHAR(255) NULL,
    start_date              DATE NOT NULL,
    end_date                DATE NULL,
    pass_probation_date     DATE NULL,
    end_probation_date      DATE NULL,
    probation_required      BIT DEFAULT 1,
    probation_salary        DECIMAL(10,2) NULL,
    pass_probation_salary   DECIMAL(10,2) NOT NULL,
    previous_year_salary    DECIMAL(10,2) NULL,
    health_welfare          BIT DEFAULT 0,
    pvd                     BIT DEFAULT 0,
    saving_fund             BIT DEFAULT 0,
    study_loan              DECIMAL(10,2) DEFAULT 0,
    retroactive_salary      DECIMAL(10,2) DEFAULT 0
);
```

**No transfer_date, no effective_date, no organization field.**

### 3.3 `personnel_actions` Table

```sql
CREATE TABLE personnel_actions (
    id                      BIGINT PRIMARY KEY IDENTITY,
    form_number             VARCHAR(255) DEFAULT 'SMRU-SF038',
    reference_number        VARCHAR(255) UNIQUE,
    employment_id           BIGINT REFERENCES employments(id),

    -- Section 1: Current state snapshot (audit trail at creation time)
    current_employee_no     VARCHAR(255) NULL,
    current_department_id   BIGINT NULL,
    current_position_id     BIGINT NULL,
    current_salary          DECIMAL(12,2) NULL,
    current_site_id         BIGINT NULL,
    current_employment_date DATE NULL,       -- Snapshot of start_date
    effective_date          DATE NOT NULL,    -- When action should take effect

    -- Section 2: Action type
    action_type             VARCHAR(255),     -- appointment, fiscal_increment, title_change,
                                              -- voluntary_separation, position_change, transfer
    action_subtype          VARCHAR(255) NULL, -- promotion, demotion, etc.
    is_transfer             BIT DEFAULT 0,
    transfer_type           VARCHAR(255) NULL, -- internal_department, site_to_site, attachment_position

    -- Section 3: New state (proposed changes)
    new_department_id       BIGINT NULL,
    new_position_id         BIGINT NULL,
    new_site_id             BIGINT NULL,
    new_salary              DECIMAL(12,2) NULL,

    -- Section 4: Approval workflow
    dept_head_approved      BIT DEFAULT 0,
    coo_approved            BIT DEFAULT 0,
    hr_approved             BIT DEFAULT 0,
    accountant_approved     BIT DEFAULT 0,
    implemented_at          DATETIME NULL,    -- System timestamp when auto-implemented

    -- Audit
    created_by              BIGINT REFERENCES users(id),
    updated_by              BIGINT NULL,
    deleted_at              DATETIME NULL     -- Soft delete
);
```

### 3.4 `employee_funding_allocations` Table

```sql
CREATE TABLE employee_funding_allocations (
    id                  BIGINT PRIMARY KEY IDENTITY,
    employee_id         BIGINT REFERENCES employees(id),
    employment_id       BIGINT NULL REFERENCES employments(id),
    grant_item_id       BIGINT NULL REFERENCES grant_items(id),
    fte                 DECIMAL(4,2),           -- 0.00 - 1.00
    allocated_amount    DECIMAL(15,2) NULL,
    salary_type         VARCHAR(50) NULL,       -- probation_salary, pass_probation_salary
    status              VARCHAR(20) DEFAULT 'active',  -- active, inactive, closed
    created_by          VARCHAR(100) NULL,
    updated_by          VARCHAR(100) NULL
);
```

**No start_date or effective_date on allocations.** Allocation lifecycle is tracked by `status` field only.

### 3.5 `employment_histories` Table

```sql
CREATE TABLE employment_histories (
    id                      BIGINT PRIMARY KEY IDENTITY,
    employment_id           BIGINT REFERENCES employments(id),
    employee_id             BIGINT REFERENCES employees(id),
    start_date              DATE NOT NULL,           -- Snapshot
    pass_probation_date     DATE NULL,               -- Snapshot
    pay_method              VARCHAR(255) NULL,
    department_id           BIGINT NULL,
    section_department_id   BIGINT NULL,
    position_id             BIGINT NULL,
    site_id                 BIGINT NULL,
    pass_probation_salary   DECIMAL(10,2),
    probation_salary        DECIMAL(10,2) NULL,
    active                  BIT DEFAULT 1,
    health_welfare          BIT DEFAULT 0,
    pvd                     BIT DEFAULT 0,
    saving_fund             BIT DEFAULT 0,
    study_loan              DECIMAL(10,2) DEFAULT 0,
    retroactive_salary      DECIMAL(10,2) DEFAULT 0,
    change_date             DATE NULL,               -- When change occurred
    change_reason           VARCHAR(255) NULL,
    changed_by_user         VARCHAR(255) NULL,
    changes_made            JSON NULL,               -- What changed
    previous_values         JSON NULL,               -- Previous state
    notes                   TEXT NULL
);
```

---

## 4. How Transfers Currently Work

### 4.1 The Transfer Flow

```
1. HR creates PersonnelAction (action_type='transfer', is_transfer=true)
   ├── Captures current state (current_department_id, current_salary, etc.)
   ├── Sets proposed new state (new_department_id, new_site_id, etc.)
   ├── Sets effective_date (business date)
   └── Auto-generates reference_number (PA-YYYY-XXXXXX)

2. Approval workflow (4 approvers: dept_head, coo, hr, accountant)
   └── Each approval sets _approved = true

3. When ALL 4 approvals complete → auto-implementAction()
   ├── Updates employment.department_id = new_department_id
   ├── Updates employment.site_id = new_site_id
   ├── Updates employment.position_id = new_position_id (if provided)
   ├── Updates employment.pass_probation_salary = new_salary (if provided)
   ├── Sets implemented_at = now()
   └── Clears caches

4. Employment start_date is NEVER touched
```

### 4.2 What's NOT Handled in Transfers

| Gap | Impact |
|-----|--------|
| **No cross-organization transfer logic** | `employees.organization` is not updated by `handleTransfer()`. HR must manually change it. |
| **No transfer effective date on employment** | Cannot distinguish "when did this employment's current configuration begin" from "when did employment start" |
| **No allocation date tracking** | `employee_funding_allocations` has no start/end dates. Uses `status` field (active/inactive) but no temporal data. |
| **Effective date ignored for non-separation actions** | A transfer with `effective_date = 2025-06-01` is applied whenever approvals complete, not on June 1st |
| **No payroll impact date** | Payroll can't determine "salary X applies until date Y, salary Z applies from date Y" within a single month |

---

## 5. Impact on Payroll Calculations

### 5.1 The 13-Month Salary Problem

**Current logic** (`PayrollService::calculateThirteenthMonthSalaryAmount()`):

```php
// Only paid in December
// Formula: SUM(gross_salary_by_FTE for all months this allocation was active) / 12
$ytdGrossByFTE = $this->getYtdGrossSalaryByFTE($employment, $allocation, $payPeriodDate);
$totalYearGrossByFTE = $ytdGrossByFTE + $grossSalaryCurrentYearByFTE;
return round($totalYearGrossByFTE / 12);
```

**How it handles transfers correctly**:
- 13th month is calculated **per-allocation** (per grant/funding source)
- If employee was on Grant A (Jan-May) then Grant B (Jun-Dec):
  - Grant A's 13th month = `SUM(Jan-May gross_salary_by_FTE) / 12`
  - Grant B's 13th month = `SUM(Jun-Dec gross_salary_by_FTE) / 12`
- The original `start_date` (e.g., 2005) is **not directly used** in 13th month calculation — it uses actual payroll records

**But the start_date IS used indirectly**:
- `calculateGrossSalaryCurrentYearByFTE()` checks `start_date` for first-month pro-rating
- For a 2005 employee, `start_date` is years in the past, so `isStartMonth` is always `false` — full 30-day pay every month
- This is correct behavior: the start_date preserves continuity

### 5.2 The Annual Salary Increment Problem

**Current logic** (`PayrollService::calculateAnnualSalaryIncrease()`):

```php
// Only January
if ($payPeriodDate->month !== 1) return 0.0;

// Start year must be BEFORE pay year
$startDate = Carbon::parse($employment->start_date);
if ($startDate->year >= $payPeriodDate->year) return 0.0;

// Base salary for increase calculation
$baseSalary = $employment->previous_year_salary ?? $employment->pass_probation_salary;
return round($baseSalary * $rate);
```

**How transfers affect this**:
- Employee started 2005, transferred in 2025
- `start_date` = 2005 (preserved), so `2005 < 2026` → eligible for January 2026 increase
- `pass_probation_salary` reflects the NEW salary (post-transfer) — the increase is calculated on the current salary
- This is **correct behavior** because the employee's continuous service is maintained

### 5.3 The Pro-Rating Problem

**Current logic** (`PayrollService::calculateGrossSalaryCurrentYearByFTE()`):

```php
$startDate = Carbon::parse($employment->start_date);
$isStartMonth = ($startDate->year == $payPeriodDate->year && $startDate->month == $payPeriodDate->month);
```

**For mid-year transfers**: Because `start_date` stays as the original date (2005), the pro-rating check will never trigger for current payroll periods. This is correct — the employee has been working continuously.

**Potential issue**: If a transfer happens mid-month with a salary change, there's no mechanism to pro-rate the old salary for the first half and the new salary for the second half of the month. The payroll will use whichever salary is on the employment record when payroll runs.

### 5.4 The Organization Filter Problem

**Payroll queries for bulk operations**:

```php
// In generateBulkPayslips():
Payroll::query()
    ->whereHas('employment.employee', fn($q) => $q->where('organization', $organization))
    ->whereYear('pay_period_date', $periodDate->year)
    ->whereMonth('pay_period_date', $periodDate->month)
    ->get();
```

**Problem**: If an employee transferred from SMRU to BHF mid-year, their `employees.organization` field is now 'BHF'. When generating January-May payslips for SMRU, this employee's historical SMRU payrolls would be **missed** because the filter checks current organization, not organization at payroll time. The payroll records themselves don't store which organization they belonged to.

---

## 6. The Dual-Organization Complexity

### 6.1 Current Architecture

```
employees.organization = 'SMRU' or 'BHF'
                ↓
         (one-to-one)
                ↓
        employments (no org field)
                ↓
         (one-to-many)
                ↓
    employee_funding_allocations (no org field)
                ↓
         (one-to-many)
                ↓
         payrolls (no org field)
```

**Organization is stored ONLY on the employee record.** No downstream table tracks which organization a specific payroll or allocation belonged to.

### 6.2 Transfer SMRU → BHF: What Should Happen

When Employee X (SMRU since 2005) transfers to BHF in June 2025:

| Aspect | Expected Behavior | Current System Behavior |
|--------|-------------------|-------------------------|
| `employees.organization` | Change to 'BHF' | **Not auto-updated by PersonnelAction** |
| `employments.start_date` | Stay as 2005 | Stays as 2005 (correct) |
| `employments.department_id` | Update to BHF department | Updated on implement |
| `employments.site_id` | Update to BHF site | Updated on implement |
| `employments.pass_probation_salary` | Update to new salary | Updated on implement |
| Old SMRU allocations | Set to inactive | **Must be done manually** |
| New BHF allocations | Create with BHF grants | **Must be done manually** |
| Historical SMRU payrolls | Remain queryable under SMRU | **Lost** — employee now shows as BHF |
| 13th month for SMRU grants | Paid in December via old allocations | Works via `calculateHistoricalAllocation13thMonth()` |
| Payslip organization header | SMRU for Jan-May, BHF for Jun-Dec | **Uses current employee org for all** |

### 6.3 What the System Gets Right

1. **Employment continuity**: `start_date` is preserved, so years-of-service calculations work
2. **Per-allocation 13th month**: Each grant/allocation tracks its own YTD, so SMRU grants pay their share and BHF grants pay theirs
3. **Historical allocation 13th month**: Inactive allocations still get their December 13th month payout
4. **Salary snapshots**: `previous_year_salary` captures pre-increase salary regardless of transfers
5. **Audit trail**: `employment_histories` and `personnel_actions` both capture before/after states

### 6.4 What the System Gets Wrong or Lacks

1. **No organization on payrolls**: Cannot determine which organization a historical payroll belonged to
2. **No transfer date on employment**: Cannot answer "when did this employee's current position/salary begin"
3. **No effective date enforcement**: Personnel action `effective_date` is recorded but not enforced for non-separation actions
4. **No allocation temporal data**: `employee_funding_allocations` has no start/end dates — only a status flag
5. **Cross-org transfer not automated**: HR must manually update `employees.organization`, deactivate old allocations, create new allocations
6. **Bulk payslip org filter**: Uses current `employees.organization`, missing historical payrolls for transferred employees

---

## 7. Date Relationship Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                        EMPLOYEE (organization)                       │
│                                                                       │
│  organization: 'SMRU' ──(transfer)──> 'BHF'                         │
│  [manually changed, not tracked when]                                 │
└───────────────┬───────────────────────────────────────────────────────┘
                │ 1:1
                ▼
┌───────────────────────────────────────────────────────────────────────┐
│                    EMPLOYMENT (the single record)                     │
│                                                                       │
│  start_date: 2005-03-15  ← NEVER changes (original hire date)        │
│  end_date: NULL          ← Only set on resignation                    │
│  pass_probation_date: 2005-06-15                                     │
│  pass_probation_salary: 45,000  ← OVERWRITTEN on each change        │
│  previous_year_salary: 42,000   ← Snapshot before annual increase    │
│  position_id: 15        ← OVERWRITTEN on transfer                    │
│  department_id: 8       ← OVERWRITTEN on transfer                    │
│  site_id: 3             ← OVERWRITTEN on transfer                    │
│                                                                       │
│  [NO transfer_date, NO effective_date, NO organization field]        │
└───────┬───────────────────────────────────────────────┬───────────────┘
        │ 1:N                                           │ 1:N
        ▼                                               ▼
┌────────────────────────┐              ┌────────────────────────────────┐
│ FUNDING ALLOCATIONS     │              │ PERSONNEL ACTIONS               │
│                         │              │                                 │
│ Grant A (SMRU):         │              │ PA-2025-000042                  │
│   fte: 0.60             │              │   effective_date: 2025-06-01   │
│   status: inactive      │              │   action_type: 'transfer'      │
│   [NO start/end dates]  │              │   current_salary: 40,000       │
│                         │              │   new_salary: 45,000           │
│ Grant B (BHF):          │              │   implemented_at: 2025-05-28   │
│   fte: 1.00             │              │   [effective_date ≠             │
│   status: active        │              │    implemented_at]              │
│   [NO start/end dates]  │              │                                 │
└───────┬─────────────────┘              └─────────────────────────────────┘
        │ 1:N
        ▼
┌─────────────────────────────┐
│ PAYROLLS                     │
│                              │
│ Jan-May: allocation=Grant A  │
│   pay_period_date: 2025-01   │
│   [NO organization field]    │
│                              │
│ Jun-Dec: allocation=Grant B  │
│   pay_period_date: 2025-06   │
│   [NO organization field]    │
└──────────────────────────────┘
```

---

## 8. Payroll Calculation Flow with Dates

```
For each active EmployeeFundingAllocation:
│
├── Step 1: Determine Salary
│   ├── getSalaryTypeForDate(payPeriodDate)
│   │   └── Compares payPeriodDate with pass_probation_date
│   ├── calculateProRatedSalaryForProbation()
│   │   └── Handles transition month (probation ends mid-month)
│   └── calculateAnnualSalaryIncrease()
│       ├── ONLY in January
│       ├── CHECK: start_date.year < payPeriodDate.year
│       └── Base: previous_year_salary ?? pass_probation_salary
│
├── Step 2: Calculate Gross by FTE
│   ├── calculateGrossSalaryCurrentYearByFTE()
│   │   ├── CHECK: Is this the start month? (start_date vs payPeriodDate)
│   │   ├── Pro-rate if mid-month start (day 2-15)
│   │   ├── Defer if late start (day >= 16)
│   │   └── Cap if resignation month (end_date vs payPeriodDate)
│   └── calculateDeferredSalary()
│       └── CHECK: Did employee start last month on day >= 16?
│
├── Step 3: 13th Month (December only)
│   ├── getYtdGrossSalaryByFTE(employment, THIS_allocation, payPeriodDate)
│   │   └── SUM payrolls WHERE allocation_id = THIS AND year = THIS AND month < current
│   └── Formula: (YTD + currentMonth) / 12
│
├── Step 4: Benefits (PVD/SF, SSF, Health Welfare)
│   ├── PVD/SF: Requires pass_probation_date <= payPeriodDate.endOfMonth()
│   ├── SSF: Applied to full salary × FTE
│   └── Health Welfare: Threshold-based × FTE
│
└── Step 5: Tax (assigned to ONE allocation only)
    ├── Calculated on FULL salary (not per-allocation)
    ├── Uses YTD data across ALL allocations
    └── Assigned to highest-FTE allocation
```

---

## 9. Identified Issues & Risks

### 9.1 Critical Issues

| # | Issue | Severity | Impact |
|---|-------|----------|--------|
| 1 | **No organization on payroll records** | High | Cannot generate historical org-specific reports after cross-org transfer |
| 2 | **`effective_date` not enforced** | High | Transfer with `effective_date = June 1` may be applied in May if approvals complete early |
| 3 | **No allocation date tracking** | High | Cannot determine when a funding allocation became active/inactive for auditing |
| 4 | **Cross-org transfer not atomic** | High | Requires manual coordination: update employee org + deactivate old allocations + create new allocations |

### 9.2 Medium Issues

| # | Issue | Severity | Impact |
|---|-------|----------|--------|
| 5 | **No transfer date on employment** | Medium | Cannot query "who transferred in the last 6 months" without joining personnel_actions |
| 6 | **Mid-month salary change not pro-rated** | Medium | If salary changes mid-month via action, entire month uses new salary |
| 7 | **`implemented_at` ≠ `effective_date`** | Medium | Audit confusion: action says effective June 1 but was implemented May 28 |
| 8 | **No previous position/department tracking on employment** | Medium | Only employment_histories captures old values; no quick lookup |

### 9.3 Low Issues (Current Workarounds Exist)

| # | Issue | Severity | Impact |
|---|-------|----------|--------|
| 9 | **`previous_year_salary` only snapshots one year back** | Low | Cannot trace salary history beyond last increase without employment_histories |
| 10 | **Allocation `status` has no timestamp** | Low | Status changes (active → inactive) are tracked by `updated_at` but not explicitly |

---

## 10. How the System Currently Handles the Complex Scenario

### Scenario: Employee 0087, SMRU since 2005, transfers to BHF June 2025

**Step-by-step what HR must do today:**

1. **Create Personnel Action** (action_type = 'transfer', is_transfer = true)
   - Sets effective_date = 2025-06-01
   - Captures current SMRU state (department, position, salary, site)
   - Sets new BHF state (department, position, salary, site)

2. **Get all 4 approvals** (dept_head, coo, hr, accountant)
   - When 4th approval completes → `implementAction()` runs automatically
   - Employment record updated with BHF department/position/salary/site
   - `implemented_at` set to current timestamp

3. **Manually update employee organization** (not automated)
   - HR edits `employees.organization` from 'SMRU' to 'BHF'

4. **Manually manage funding allocations** (not automated)
   - Deactivate SMRU grant allocations (set status = 'inactive')
   - Create new BHF grant allocations (new grant_item_id, fte, allocated_amount)

5. **Run payroll for June onwards**
   - Payroll uses new BHF allocations
   - `start_date` still 2005 — no pro-rating triggered
   - Employee eligible for January 2026 annual increment (2005 < 2026)

6. **December: 13th month calculation**
   - Active BHF allocations: YTD from Jun-Dec payrolls → 13th month = `sum / 12`
   - Inactive SMRU allocations: `calculateHistoricalAllocation13thMonth()` → Jan-May payrolls → 13th month = `sum / 12`
   - Both allocations' 13th months are paid

**What works**: The per-allocation YTD basis correctly handles the split year. `start_date` preservation ensures increment eligibility.

**What's fragile**: Steps 3-4 are manual. If HR forgets to deactivate SMRU allocations, the employee would get payroll from both organizations. If they forget to update `employees.organization`, bulk payslip PDFs would still show SMRU headers.

---

## 11. Relationship Between Dates Across Tables

```
Timeline for Employee 0087:
═══════════════════════════════════════════════════════════════

2005-03-15  employment.start_date (NEVER CHANGES)
    │
    ├── 2005-06-15  employment.pass_probation_date
    │       └── Salary: probation → pass_probation
    │
    │   ... years of service ...
    │
    ├── 2025-05-20  personnel_action CREATED
    │       ├── effective_date: 2025-06-01
    │       ├── current_salary: 40,000
    │       └── new_salary: 45,000
    │
    ├── 2025-05-28  personnel_action IMPLEMENTED (all 4 approvals done)
    │       ├── implemented_at: 2025-05-28 14:32:00
    │       ├── employment.pass_probation_salary → 45,000 (OVERWRITTEN)
    │       ├── employment.department_id → BHF dept (OVERWRITTEN)
    │       ├── employment.site_id → BHF site (OVERWRITTEN)
    │       └── employment.start_date → 2005-03-15 (UNCHANGED)
    │
    ├── 2025-06-01  INTENDED effective_date (not enforced by system)
    │       └── Note: Change already applied on May 28th
    │
    ├── 2026-01-01  January payroll
    │       ├── Annual increment check: 2005 < 2026 → ELIGIBLE
    │       ├── Base salary: 45,000 (post-transfer)
    │       └── Increase: 45,000 × rate%
    │
    └── 2026-12-31  December payroll
            ├── BHF allocations: 13th month from Jun-Dec
            └── Old SMRU allocations: 13th month from Jan-May
```

---

## 12. Summary of Findings

### What Works Well

1. **`start_date` preservation** — Original hire date drives eligibility calculations correctly regardless of transfers
2. **Per-allocation payroll tracking** — Each funding source independently tracks its contribution, enabling accurate 13th month splits
3. **Employment history audit trail** — Every change is captured with before/after snapshots
4. **Personnel action workflow** — Structured approval process with immutability after implementation

### What Needs Attention

1. **No organization tracking on payrolls/allocations** — Cross-org transfers lose the historical org association
2. **No transfer/effective date on employment** — Can't distinguish "employee configuration start date" from "employment start date" at the employment level
3. **Effective date not enforced** — Changes apply immediately on approval, not on the intended effective date
4. **Manual steps for cross-org transfers** — Organization update, allocation lifecycle, and payslip headers require manual coordination
5. **No allocation date lifecycle** — Allocations have status but no temporal boundaries (when did this allocation become active/inactive?)

### Design Philosophy Confirmed

The current design intentionally treats **one employee = one employment record** with in-place updates. This is simpler than creating new employment records on each change (which would fragment payroll history). The trade-off is that point-in-time queries require joining with `employment_histories` or `personnel_actions`.

The `start_date` is the **anchor date** — it represents continuous employment tenure and must never be modified by transfers, action changes, or salary adjustments. All payroll date logic correctly uses `start_date` only for the original hire context (pro-rating, deferred salary, increment eligibility) and never confuses it with transfer timing.

---

*Document created: 2026-03-07*
*Last updated: 2026-03-07*
