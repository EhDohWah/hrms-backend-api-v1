# Transfer & Action Change — Research Document

## Table of Contents

1. [Overview: Dual Organization Structure](#1-overview-dual-organization-structure)
2. [Current State: Where Organization Lives](#2-current-state-where-organization-lives)
3. [Current State: Personnel Action (Action Change)](#3-current-state-personnel-action-action-change)
4. [How Organization Affects the System](#4-how-organization-affects-the-system)
5. [Transfer vs Action Change — The Distinction](#5-transfer-vs-action-change--the-distinction)
6. [Known Bugs in Current Implementation](#6-known-bugs-in-current-implementation)
7. [Transfer Implementation Analysis](#7-transfer-implementation-analysis)
8. [Action Change Improvement Analysis](#8-action-change-improvement-analysis)
9. [Cascading Effects of an Organization Transfer](#9-cascading-effects-of-an-organization-transfer)
10. [Data Flow Diagrams](#10-data-flow-diagrams)
11. [Implementation Recommendations](#11-implementation-recommendations)

---

## 1. Overview: Dual Organization Structure

This HRMS serves **two organizations** (SMRU and BHF) managed by a **single HR department**. Employees belong to one organization but can be funded by grants from either. This creates two distinct types of employee changes:

| Change Type | What Changes | Scope |
|---|---|---|
| **Transfer** | Employee's home `organization` (SMRU ↔ BHF) | Changes the employee's identity — affects payslip template, health welfare eligibility, inter-org advances, dashboard counts |
| **Action Change** | Employment details (position, department, site, salary, etc.) | Changes employment conditions — no organization change |

A transfer **can include** an action change (e.g., employee moves from SMRU to BHF AND gets a new position), but an action change **never** includes a transfer.

---

## 2. Current State: Where Organization Lives

### Employee Table

`organization` is a column on the `employees` table (not `employments`):

```
employees.organization  VARCHAR(10)  — values: 'SMRU' or 'BHF'
```

**File:** `database/migrations/2025_02_12_131510_create_employees_table.php`

```php
$table->string('organization', 10);
```

There is a composite unique index:
```sql
CREATE UNIQUE INDEX [employees_staff_id_organization_unique]
ON [employees] ([staff_id], [organization])
WHERE [deleted_at] IS NULL
```

This means the same `staff_id` can technically exist in both organizations as separate employee records. However in practice, each employee has ONE record with ONE organization.

### Key Implication

Organization is an **employee-level attribute**, not an employment attribute. An employee cannot be "half SMRU, half BHF". Their organization applies to ALL their employments and funding allocations.

### Grant Table

Grants also have their own independent `organization` column:

```
grants.organization  — values: 'SMRU' or 'BHF'
```

A SMRU employee CAN be funded by a BHF grant (and vice versa). The system handles this via **inter-organization advances** during payroll.

---

## 3. Current State: Personnel Action (Action Change)

### What Exists

The Personnel Action system implements the **SMRU-SF038 form** — a formal HR document for recording and approving changes to employment conditions.

**Backend: Fully implemented.** Controller, service, model, migration, routes, validation — all complete.

**Frontend: Not yet built.** No Vue components, no API module, no router entry, no sidebar link.

### Action Types (Constants)

```php
// PersonnelAction.php

ACTION_TYPES = [
    'appointment'           => 'Appointment',
    'fiscal_increment'      => 'Fiscal Increment',
    'title_change'          => 'Title Change',
    'voluntary_separation'  => 'Voluntary Separation',
    'position_change'       => 'Position Change',
    'transfer'              => 'Transfer',
];

ACTION_SUBTYPES = [
    're_evaluated_pay_adjustment' => 'Re-Evaluated Pay Adjustment',
    'promotion'                   => 'Promotion',
    'demotion'                    => 'Demotion',
    'end_of_contract'             => 'End of Contract',
    'work_allocation'             => 'Work Allocation',
];

TRANSFER_TYPES = [
    'internal_department'  => 'Internal Department',
    'site_to_site'         => 'From Site to Site',
    'attachment_position'  => 'Attachment Position',
];
```

### What Each Action Type Does When Fully Approved

| Action Type | Updates on Employment |
|---|---|
| `appointment` | `position_id`, `department_id`, `pass_probation_salary`, `site_id` |
| `fiscal_increment` | `position_id`, `department_id`, `pass_probation_salary` |
| `position_change` | `position_id`, `department_id`, `pass_probation_salary` |
| `transfer` | `department_id`, `site_id`, `position_id` |
| `voluntary_separation` | `end_date` = effective_date |
| `title_change` | `position_id` only |

**Critical gap:** The `transfer` action type only handles **internal transfers** (department-to-department, site-to-site). It does NOT change `employee.organization`. There is no mechanism to transfer an employee between SMRU and BHF.

### Approval Workflow

Four independent boolean approvals:

```
dept_head_approved   → Department Head
coo_approved         → Chief Operating Officer
hr_approved          → HR Department
accountant_approved  → Accountant
```

When all four = `true`, `implementAction()` fires automatically and mutates the Employment record.

### Database Schema

**File:** `database/migrations/2025_09_25_134034_create_personnel_actions_table.php`

```
personnel_actions
├── id, form_number, reference_number
├── employment_id (FK → employments)
├── Section 1: Current state snapshot
│   ├── current_employee_no
│   ├── current_department_id (FK)
│   ├── current_position_id (FK)
│   ├── current_site_id (FK)          ← NOTE: column name mismatch with model
│   ├── current_salary
│   └── current_employment_date
├── Section 2: Action classification
│   ├── action_type, action_subtype
│   ├── is_transfer, transfer_type
│   └── effective_date
├── Section 3: Proposed new values
│   ├── new_department_id (FK)
│   ├── new_position_id (FK)
│   ├── new_site_id (FK)              ← NOTE: column name mismatch with model
│   ├── new_salary
│   ├── new_work_schedule, new_report_to
│   ├── new_pay_plan, new_phone_ext, new_email
│   └── comments, change_details
└── Section 4: Approvals
    ├── dept_head_approved, coo_approved
    ├── hr_approved, accountant_approved
    └── created_by, updated_by, timestamps, soft_deletes
```

---

## 4. How Organization Affects the System

### 4.1 Payslip PDF Template

**File:** `app/Services/PayrollService.php` line 335

```php
$view = $employee?->organization === 'BHF' ? 'pdf.bhf-payslip' : 'pdf.smru-payslip';
```

SMRU and BHF have different payslip layouts, logos, employer names, and addresses.

### 4.2 Health Welfare Employer Contribution

**File:** `app/Services/PayrollService.php` line 1583-1598

```php
private function calculateHealthWelfareEmployer(Employee $employee, float $fullMonthlySalary, float $fte): float
{
    $eligibility = BenefitSetting::getEmployerHWEligibility();
    $eligibleOrgs = $eligibility['eligible_organizations'] ?? [];

    if (! in_array($employee->organization, $eligibleOrgs) || ...) {
        return 0.0;
    }
    // ... tier calculation
}
```

The employer health welfare contribution is only paid for employees in eligible organizations. If an employee transfers from SMRU to BHF (or vice versa), their eligibility may change.

### 4.3 Inter-Organization Advances

**File:** `app/Services/PayrollService.php` line 1222-1278

```php
$fundingOrganization = $projectGrant->organization;   // Grant's org
$employeeOrganization = $employee->organization;       // Employee's org

if ($fundingOrganization === $employeeOrganization) {
    return null;  // No advance needed
}

// Create advance record through hub grant
$hubGrant = Grant::getHubGrantForOrganization($fundingOrganization);
$advance = InterOrganizationAdvance::create([
    'from_organization' => $fundingOrganization,
    'to_organization'   => $employeeOrganization,
    'via_grant_id'      => $hubGrant->id,
    'amount'            => $payroll->net_salary,
]);
```

When an employee's organization differs from their funding grant's organization, the system creates an advance record through a hub grant:
- SMRU hub grant: `S0031` (Other Fund)
- BHF hub grant: `S22001` (General Fund)

**Transfer impact:** If employee moves from SMRU to BHF, ALL their grant allocations may flip from same-org to cross-org (or vice versa), changing which payrolls create advances.

### 4.4 Bulk Payroll Filtering

**File:** `app/Services/BulkPayrollService.php` line 607

```php
if (! empty($filters['subsidiaries'])) {
    $query->whereHas('employee', function ($q) use ($filters) {
        $q->whereIn('organization', $filters['subsidiaries']);
    });
}
```

HR generates payroll per organization. If an employee transfers mid-month, they would appear under the new organization in the next payroll run.

### 4.5 Bulk Payslip Generation

**File:** `app/Services/PayrollService.php` line 354

```php
$payrolls = Payroll::query()
    ->whereHas('employment.employee', fn ($q) => $q->where('organization', $organization))
    ...
```

### 4.6 Dashboard Statistics

**File:** `app/Models/Employee.php`

`getStatistics()` groups employee counts by organization. Cache TTL = 300 seconds.

### 4.7 Employee List Filtering

**File:** `app/Services/EmployeeDataService.php`

All employee list views can be filtered by `organization`. The org tree view groups employees by organization first.

### Complete Reference: All Places Organization Is Read

| Location | File | Purpose |
|---|---|---|
| `Employee::scopeByOrganization()` | `Employee.php` | Filter employee list |
| `Employee::getStatistics()` | `Employee.php` | Dashboard counts by org |
| `Payroll::scopeByOrganization()` | `Payroll.php` | Filter payroll list |
| `Payroll::scopeOrderByField('organization')` | `Payroll.php` | Sort payrolls by org |
| `Payroll::getUniqueSubsidiaries()` | `Payroll.php` | Org dropdown list |
| `Grant::scopeBySubsidiary()` | `Grant.php` | Filter grants by org |
| `Grant::getHubGrantForOrganization()` | `Grant.php` | Hub grant lookup |
| `PayrollService::generatePayslipPdf()` | `PayrollService.php:335` | Template selection |
| `PayrollService::generateBulkPayslips()` | `PayrollService.php:354` | Bulk PDF filter |
| `PayrollService::calculateHealthWelfareEmployer()` | `PayrollService.php:1595` | HW eligibility |
| `PayrollService::createInterOrganizationAdvanceIfNeeded()` | `PayrollService.php:1234` | Advance detection |
| `PayrollService::budgetHistory()` | `PayrollService.php:253` | Budget history filter |
| `BulkPayrollService::buildEmploymentQuery()` | `BulkPayrollService.php:607` | Bulk payroll filter |
| `BulkPayrollService::needsInterOrganizationAdvance()` | `BulkPayrollService.php:632` | Preview advance check |
| `EmployeeDataService::list()` | `EmployeeDataService.php:45` | Employee list filter |
| `EmployeeDataService::searchForOrgTree()` | `EmployeeDataService.php:250` | Tree grouped by org |
| `StoreEmployeeRequest` | Validation | Enforce SMRU/BHF enum |
| `UpdateEmployeeRequest` | Validation | Same on update |

---

## 5. Transfer vs Action Change — The Distinction

### Action Change (Already Implemented)

An **Action Change** modifies an employee's **employment conditions** without changing their organization:

```
BEFORE: Employee #101 (SMRU) → Position: Lab Tech, Dept: Research, Site: Mae Sot
AFTER:  Employee #101 (SMRU) → Position: Senior Lab Tech, Dept: Research, Site: Mae Sot
                                          ^^^^^^^^^^^^^^^^ only position changed
```

Action change covers:
- **Appointment** — new hire into a position (position + department + salary + site)
- **Fiscal Increment** — salary increase with possible position/department change
- **Title Change** — position change only (e.g., promoted title)
- **Position Change** — position + department + salary
- **Voluntary Separation** — sets employment end date
- **Internal Transfer** — department and/or site change within the SAME organization

Action subtypes (can accompany any action type):
- Promotion, Demotion
- Re-Evaluated Pay Adjustment
- End of Contract
- Work Allocation

### Transfer (Not Yet Implemented)

A **Transfer** changes the employee's **home organization**:

```
BEFORE: Employee #101 (SMRU) → Position: Lab Tech, Dept: Research, Site: Mae Sot
AFTER:  Employee #101 (BHF)  → Position: Lab Tech, Dept: Research, Site: Bangkok
                     ^^^^^^^ organization changed (+ possibly position/site/salary)
```

Transfer is a superset — it ALWAYS changes `employee.organization`, and MAY also change employment details (position, department, site, salary) simultaneously.

### How They Relate

```
┌─────────────────────────────────────────────────────────┐
│                        TRANSFER                          │
│  Changes: employee.organization (SMRU ↔ BHF)            │
│                                                          │
│  ┌─────────────────────────────────────────────────┐     │
│  │          ACTION CHANGE (optional)                │     │
│  │  Changes: position, department, site, salary     │     │
│  └─────────────────────────────────────────────────┘     │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│              ACTION CHANGE (standalone)                   │
│  Changes: position, department, site, salary             │
│  Does NOT change organization                            │
└─────────────────────────────────────────────────────────┘
```

---

## 6. Known Bugs in Current Implementation

### Bug 1: Column Name Mismatch (site_id vs work_location_id)

The migration creates columns `current_site_id` and `new_site_id`, but the Eloquent model uses `current_work_location_id` and `new_work_location_id` in `$fillable`, relationships, and the resource.

**Impact:** Writing to `new_work_location_id` silently fails because the actual database column is `new_site_id`. All site/location saves via personnel actions are broken.

**Files affected:**
- Migration: `current_site_id`, `new_site_id` (correct DB columns)
- Model `$fillable`: `current_work_location_id`, `new_work_location_id` (wrong names)
- `PersonnelActionResource`: `current_work_location_id`, `new_work_location_id` (wrong names)
- `PersonnelActionService::handleAppointment()` line 191: writes `work_location_id` to Employment (Employment uses `site_id`)
- `PersonnelActionService::handleTransfer()` line 220: writes `work_location_id` to Employment (Employment uses `site_id`)

**Fix:** Either rename DB columns to match model, or rename model fields to match DB. Since the migration uses `site_id` (matching Employment's `site_id` convention), the model/service should be updated to use `site_id`.

### Bug 2: handleAppointment Writes Wrong Column to Employment

```php
// PersonnelActionService.php line 191
'work_location_id' => $action->new_work_location_id,
```

Employment model uses `site_id`, not `work_location_id`. This update is silently ignored because `work_location_id` is not in Employment's `$fillable`.

### Bug 3: "Implemented" Status Never Set

`STATUSES` includes `'implemented' => 'Implemented'`, but `getStatusAttribute()` only returns `'pending'`, `'partial_approved'`, or `'fully_approved'`. After `implementAction()` runs, the record still shows `fully_approved` with no indication that changes were applied.

### Bug 4: StorePersonnelActionRequest Used for Updates

The controller's `update()` method reuses `StorePersonnelActionRequest`, which has `'effective_date' => 'required', 'after_or_equal:today'`. This prevents correcting effective dates on historical records.

### Bug 5: No Dedicated Update Form Request

All fields are `required` in `StorePersonnelActionRequest` (e.g., `employment_id`, `effective_date`, `action_type`). For partial updates, these should be `sometimes`.

---

## 7. Transfer Implementation Analysis

### What Needs to Happen During an Organization Transfer

When employee moves from SMRU → BHF (or BHF → SMRU):

#### Required Changes (Atomic)

1. **Update `employee.organization`** — the core change
2. **Create audit trail** — record the transfer with before/after state

#### Cascading Effects (Must Be Handled)

3. **Funding allocations** — existing allocations reference grants. If the employee was on an SMRU grant and moves to BHF:
   - The allocation itself doesn't change (still funded by the same grant item)
   - But future payrolls will now create inter-organization advances (or stop creating them if the grant matches the new org)

4. **Future payrolls** — next payroll run automatically picks up the new organization:
   - Payslip template changes (SMRU vs BHF layout)
   - Health welfare eligibility may change
   - Inter-org advance logic inverts for all existing allocations
   - Employee appears in different org filter in bulk payroll

5. **Historical payrolls** — past payroll records are NOT affected. They were correctly calculated with the old organization at the time of generation.

6. **Dashboard statistics cache** — `employee_statistics` cache (300s TTL) will be stale

#### Optional Concurrent Changes (Action Change)

7. **Position change** — employee may get a new position at the new organization
8. **Department change** — may move to a different department
9. **Site change** — may relocate to a different site
10. **Salary change** — may get salary adjustment with transfer

### Transfer Timing Rule

A transfer should only take effect on payroll boundaries. If an employee transfers mid-month:
- Current month's payroll should be under the OLD organization (already generated)
- Next month's payroll will be under the NEW organization
- The `effective_date` on the personnel action determines when the transfer takes effect

### Design Options for Transfer

#### Option A: Extend Personnel Action System

Add transfer as a special personnel action that also changes `employee.organization`.

**Pros:**
- Reuses existing approval workflow (4-level)
- Reuses existing audit trail (employment history)
- Single system for all employee changes
- Personnel action already has `action_type = 'transfer'` and `TRANSFER_TYPES`

**Cons:**
- Personnel actions are linked to `employment_id` (employment level), but organization lives on `employee` (employee level)
- Need to add `new_organization` field to personnel_actions table
- `implementAction()` must be extended to update the employee record too

#### Option B: Separate Transfer System

Create a dedicated `employee_transfers` table and workflow.

**Pros:**
- Clean separation of concerns
- Can have transfer-specific fields (transfer reason, receiving department head approval)
- Independent audit trail

**Cons:**
- Duplicates the approval workflow
- More tables, more code, more maintenance
- Two places to look for employee history

#### Option C: Extend Personnel Action + Add new_organization Field (Recommended)

The personnel action system already has `action_type = 'transfer'` with `TRANSFER_TYPES` that include `'attachment_position'`. We extend it minimally:

1. Add `new_organization` column to `personnel_actions` table
2. Add `current_organization` column for snapshot
3. Modify `handleTransfer()` to also update `employee.organization` when `new_organization` is set
4. Add a new transfer type: `'organization_transfer' => 'Organization Transfer (SMRU ↔ BHF)'`

This way:
- Internal transfers (dept-to-dept, site-to-site) work as before — no `new_organization` set
- Organization transfers set `new_organization` — triggers the org change cascade
- A transfer can include both org change AND position/dept/site/salary changes simultaneously

---

## 8. Action Change Improvement Analysis

### Current Gaps

1. **No frontend** — backend is complete, frontend needs to be built
2. **Column name bugs** — `work_location_id` vs `site_id` mismatch throughout
3. **No "implemented" status** — can't tell if changes were applied
4. **No UpdatePersonnelActionRequest** — update uses create validation
5. **No delete endpoint** — can't remove personnel actions
6. **No organization transfer support** — `handleTransfer()` only changes dept/site/position

### What the Action Change Form (SMRU-SF038) Should Capture

Based on the user's description, the form is a paper-based document with checkboxes:

**Action Types (radio — pick one):**
- [ ] Appointment
- [ ] Fiscal Increment
- [ ] Title Change
- [ ] Voluntary Separation
- [ ] Re-Evaluated Pay Adjustment
- [ ] Promotion
- [ ] Demotion
- [ ] End of contract
- [ ] Work allocation

**Transfer section (checkbox — optional, can combine with action type):**
- [ ] Transfer
  - [ ] Internal Department
  - [ ] From site to site
  - [ ] Attachment Position (see attach position)
  - [ ] Organization Transfer (SMRU ↔ BHF) ← NEW

**Current implementation maps this correctly:**
- `action_type` = the primary radio selection
- `action_subtype` = secondary classification (promotion, demotion, etc.)
- `is_transfer` = boolean checkbox
- `transfer_type` = which transfer sub-option

### Improvements Needed

1. **Move subtypes to be selectable alongside action types** — currently `action_subtype` is independent of `action_type`, but promotion/demotion should work WITH position_change
2. **Add `new_organization` field** — for org transfers
3. **Fix site_id column naming** — align model with migration
4. **Add `implemented_at` timestamp** — track when changes were applied
5. **Create UpdatePersonnelActionRequest** — proper partial update validation
6. **Add delete route** — for cancelling personnel actions

---

## 9. Cascading Effects of an Organization Transfer

### Immediate Effects (Applied When Transfer Is Implemented)

| System | Effect | Automatic? |
|---|---|---|
| `employees.organization` | Updated to new org | Yes (in `implementAction`) |
| `employment.department_id` | May change if specified | Yes (if `new_department_id` provided) |
| `employment.position_id` | May change if specified | Yes (if `new_position_id` provided) |
| `employment.site_id` | May change if specified | Yes (if `new_site_id` provided) |
| `employment.pass_probation_salary` | May change if specified | Yes (if `new_salary` provided) |
| Employment History | Snapshot recorded | Yes (via Employment::booted() observer) |
| Dashboard Statistics Cache | Stale until expiry | Need to invalidate |

### Future Payroll Effects (Automatic — No Code Changes Needed)

| System | Effect | Why |
|---|---|---|
| Bulk payroll filter | Employee appears under new org | Filters by `employee.organization` |
| Payslip template | Uses new org template | Reads `employee.organization` at generation time |
| Health welfare | Re-evaluated against new org eligibility | Reads `employee.organization` at calculation time |
| Inter-org advances | Inverted for all allocations | Compares `employee.organization` vs `grant.organization` |
| Bulk payslip PDF | Appears in new org batch | Filters by `employee.organization` |

### What Does NOT Change Automatically (Manual Review Needed)

| Item | Concern |
|---|---|
| Funding allocations | Still linked to same grants. HR may need to reassign to new-org grants |
| Previous payroll records | Stay as-is (correct for their time period) |
| Leave balances | Tied to employee, not org — no impact |
| Inter-org advance history | Past advances reference old org — correct historical record |
| `staff_id` uniqueness | New org may already have an employee with same staff_id (unique index: `staff_id` + `organization`) |

### The staff_id Problem

The unique index on `employees` is `(staff_id, organization)`. This means:
- If employee has `staff_id = 'E001'` in SMRU, and we change org to BHF...
- ...it will fail if BHF already has an `E001`
- In practice this should be fine since staff IDs are unique across both orgs
- But the constraint should be checked before transfer

---

## 10. Data Flow Diagrams

### Current Flow: Action Change (Position Change)

```
1. HR opens personnel action form
   ↓
2. Selects employment → auto-populates current state
   ↓
3. Fills in: action_type=position_change, new_position, new_department
   ↓
4. POST /api/v1/personnel-actions
   ↓
5. PersonnelActionService::createPersonnelAction()
   → Snapshots current employment into current_* fields
   → Generates reference number (PA-2026-000001)
   → Creates employment history entry
   ↓
6. Four approvers independently approve
   PATCH /api/v1/personnel-actions/{id}/approve
   ↓
7. When all 4 approved → implementAction() fires
   → handlePositionChange():
     employment.position_id = new_position_id
     employment.department_id = new_department_id
     employment.pass_probation_salary = new_salary
   → Employment::booted() creates history snapshot
   → Caches cleared
```

### Proposed Flow: Organization Transfer

```
1. HR opens personnel action form
   ↓
2. Selects employment → auto-populates current state + current org
   ↓
3. Fills in:
   - action_type = position_change (or any type)
   - is_transfer = true
   - transfer_type = organization_transfer
   - new_organization = BHF
   - (optional) new_position, new_department, new_site, new_salary
   ↓
4. POST /api/v1/personnel-actions
   ↓
5. PersonnelActionService::createPersonnelAction()
   → Snapshots current_organization from employee.organization
   → Same as before + stores new_organization
   ↓
6. Approval workflow (same 4 approvers)
   ↓
7. When all 4 approved → implementAction() fires
   → handleTransfer():
     IF new_organization is set:
       employee.organization = new_organization  ← THE KEY CHANGE
     employment.department_id = new_department_id (if set)
     employment.site_id = new_site_id (if set)
     employment.position_id = new_position_id (if set)
   → Employment::booted() creates history snapshot
   → Employee change recorded
   → Caches cleared (employee stats + employment)
   ↓
8. Next payroll run picks up new organization automatically
   → Different payslip template
   → Different advance logic
   → Different HW eligibility
```

---

## 11. Implementation Recommendations

### Phase 1: Fix Existing Bugs

Before adding transfer functionality, fix the current issues:

1. **Fix column name mismatch** — rename `current_work_location_id`/`new_work_location_id` to `current_site_id`/`new_site_id` in model, resource, service, and request
2. **Fix handleAppointment/handleTransfer** — write to `site_id` not `work_location_id` on Employment
3. **Add `implemented_at` column** — track when changes were applied, update `getStatusAttribute()` to return `'implemented'`
4. **Create UpdatePersonnelActionRequest** — with `sometimes` rules for partial updates
5. **Add delete route** — `DELETE /api/v1/personnel-actions/{id}`

### Phase 2: Add Organization Transfer Support

1. **Add columns to migration** — `current_organization VARCHAR(10)`, `new_organization VARCHAR(10)`
2. **Update model** — add to `$fillable`, add validation
3. **Add transfer type** — `'organization_transfer' => 'Organization Transfer (SMRU ↔ BHF)'` to `TRANSFER_TYPES`
4. **Extend `handleTransfer()`** — when `new_organization` is set, also update `employee.organization`
5. **Add validation** — check `staff_id` uniqueness in new organization before allowing transfer
6. **Add `populateCurrentEmploymentData()`** — also populate `current_organization` from employee
7. **Clear employee caches** — invalidate employee statistics cache after org change

### Phase 3: Build Frontend

1. **Personnel Action list page** — table with filters (action type, status, employee)
2. **Personnel Action create/edit form** — matches SMRU-SF038 paper form layout
3. **Approval workflow UI** — toggle buttons for each approver, visual status indicators
4. **Organization transfer section** — conditional UI when `is_transfer = true` and `transfer_type = organization_transfer`
5. **Integration with Employee Detail** — action history tab showing all personnel actions for an employee

### Phase 4: Funding Allocation Review

After an organization transfer, HR may need to:
1. Review existing funding allocations (may need to move to new-org grants)
2. The system should show a warning/notification after transfer is implemented
3. Consider adding an "Allocation Review" step in the transfer workflow

---

## Files Referenced

| File | Purpose |
|---|---|
| `app/Models/PersonnelAction.php` | Model with constants, relationships, status accessor |
| `app/Services/PersonnelActionService.php` | Business logic, approval workflow, implementAction() |
| `app/Http/Controllers/Api/V1/PersonnelActionController.php` | API endpoints |
| `app/Http/Requests/StorePersonnelActionRequest.php` | Validation rules |
| `app/Http/Resources/PersonnelActionResource.php` | API response shape |
| `database/migrations/2025_09_25_134034_create_personnel_actions_table.php` | Table schema |
| `routes/api/personnel_actions.php` | Route definitions |
| `app/Models/Employee.php` | Organization field, relationships |
| `app/Models/Employment.php` | Employment fields, history hooks |
| `app/Models/Grant.php` | Grant organization, hub grant lookup |
| `app/Services/PayrollService.php` | Org-dependent calculations |
| `app/Services/BulkPayrollService.php` | Org-based payroll filtering |
| `app/Observers/EmploymentObserver.php` | Employment change validation |
