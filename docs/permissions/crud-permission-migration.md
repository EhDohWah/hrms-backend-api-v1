# CRUD Permission Migration — Frontend Views

> **Completed:** March 6, 2026
> **Frontend Directory:** `C:\Users\Turtle\Downloads\hrms-screenshot\hrms-frontend`

---

## Background

The backend permission system uses **42 modules x 4 actions** (read, create, update, delete) = **168 permissions**.

The frontend originally used a single `canEdit()` helper (a union of `create || update || delete`) to gate **all** write actions. This meant a user with only `create` permission could still see Edit and Delete buttons — a critical security gap.

### The Bug

```js
// OLD: canEdit() returns true if ANY write permission exists
canEdit(module) {
  return canCreate(module) || canUpdate(module) || canDelete(module)
}

// Used for ALL actions — Add, Edit, Delete buttons all checked canEdit()
// A user with {create: true, update: false, delete: false} saw Edit + Delete buttons
```

### The Fix

Each action now uses its specific permission check:

| UI Action | Permission Check |
|-----------|-----------------|
| Add / Create button | `canCreate(module)` |
| Edit button | `canUpdate(module)` |
| Delete button | `canDelete(module)` |
| Toggle / Status change | `canUpdate(module)` |
| Import / Upload | `canCreate(module)` |
| Restore (recycle bin) | `canUpdate(module)` |
| Permanent delete | `canDelete(module)` |
| Row selection (for bulk delete) | `canDelete(module)` |
| Download / Export | No gate (read operation) |

---

## Auth Store Methods

**File:** `src/stores/auth.js`

| Method | Purpose |
|--------|---------|
| `canRead(module)` | Check read permission |
| `canCreate(module)` | Check create permission |
| `canUpdate(module)` | Check update permission |
| `canDelete(module)` | Check delete permission |
| `canEdit(module)` | **Deprecated** — backward-compat union of create\|\|update\|\|delete. No longer used in templates. |

---

## Files Updated (28 views)

### Administration Views (built with granular CRUD from the start)

| File | Module |
|------|--------|
| `views/admin/UserListView.vue` | `users` |
| `views/admin/RoleListView.vue` | `roles` |
| `views/admin/UserPermissionsView.vue` | `users` |

### Employee Views

| File | Module | Notes |
|------|--------|-------|
| `views/employees/EmployeeListView.vue` | `employees` | Standard CRUD gating |
| `views/employees/EmployeeFormView.vue` | `employees` | Context-aware `canSave`: uses `canCreate` in add mode, `canUpdate` in edit mode |
| `views/employees/tabs/EmploymentTab.vue` | `employment_records` | Split `canEditEmployment` into 3 computed properties |
| `views/employees/tabs/FundingTab.vue` | `employee_funding_allocations` | Changed module from `employees` to `employee_funding_allocations` |
| `views/employees/tabs/RecordsTab.vue` | `employees` | Added `showActions` computed for actions column visibility |

### Organization Views

| File | Module |
|------|--------|
| `views/organization/DepartmentListView.vue` | `departments` |
| `views/organization/SiteListView.vue` | `sites` |
| `views/organization/PositionListView.vue` | `positions` |

### Attendance & Leave Views

| File | Module |
|------|--------|
| `views/attendance/AttendanceListView.vue` | `attendance` |
| `views/attendance/HolidayListView.vue` | `holidays` |
| `views/leave/LeaveRequestListView.vue` | `leave_requests` |
| `views/leave/LeaveTypeListView.vue` | `leave_types` |

### Recruitment Views

| File | Module |
|------|--------|
| `views/recruitment/InterviewListView.vue` | `interviews` |
| `views/recruitment/JobOfferListView.vue` | `job_offers` |

### Training & Resignation Views

| File | Module | Notes |
|------|--------|-------|
| `views/training/TrainingListView.vue` | `training` | |
| `views/resignation/ResignationListView.vue` | `resignations` | |
| `views/resignation/ResignationDetailView.vue` | `resignations` | Download = read-only, no gate |

### Personnel Action & Transfer Views

| File | Module | Notes |
|------|--------|-------|
| `views/personnel-actions/PersonnelActionListView.vue` | `employees` | Create/Edit/Delete + approval toggles = `canUpdate` |
| `views/employees/EmployeeFormView.vue` | `employees` | Transfer button gated by `canSave` (= `canUpdate` in edit mode) |

### Grant Views

| File | Module |
|------|--------|
| `views/grants/GrantListView.vue` | `grants` |
| `views/grants/GrantDetailView.vue` | `grants` |

### Payroll & Settings Views

| File | Module | Notes |
|------|--------|-------|
| `views/payroll/PayrollListView.vue` | `employee_salary` | Bulk Payroll = canCreate, Delete = canDelete |
| `views/settings/BenefitSettingsView.vue` | `benefit_settings` | Update only (no add/delete) |
| `views/settings/TaxSettingsView.vue` | `tax_settings` | Update only |
| `views/settings/TaxBracketsView.vue` | `tax_brackets` | Full CRUD |
| `views/settings/PayrollPolicySettingsView.vue` | `payroll_policy_settings` | Update only |

### Utility Views

| File | Module | Notes |
|------|--------|-------|
| `views/recycle-bin/RecycleBinView.vue` | Per-module | Restore = `canUpdate`, Permanent delete = `canDelete` |
| `views/data-import/DataImportView.vue` | Per-module | Upload/Import = `canCreate` |

---

## Files Not Requiring Changes (23 views)

### Read-Only Views (no write operations)

| File | Reason |
|------|--------|
| `views/employees/tabs/LeaveTab.vue` | Display-only leave balances |
| `views/grants/GrantPositionListView.vue` | Read-only list, "View" button only |
| `views/leave/LeaveBalanceListView.vue` | Read-only list with filters |
| `views/payroll/PayrollDetailDrawer.vue` | Read-only detail view |
| `views/payroll/PayrollBudgetView.vue` | Read-only budget history |
| `views/dashboard/DashboardView.vue` | Read-only widgets |
| `views/payroll/BulkPayslipModal.vue` | Export/download (read operation) |
| `views/reports/ReportsView.vue` | Export/download (read operation) |

### Child Components (permission inherited from parent)

| File | Parent Gate |
|------|------------|
| `views/payroll/BulkPayrollModal.vue` | PayrollListView gates with `canCreate('employee_salary')` |
| `views/employees/components/EmployeeSidebar.vue` | Receives `canUpload` prop from EmployeeFormView |
| `views/employees/components/TransferModal.vue` | EmployeeFormView gates Transfer button with `canSave` |

### Self-Edit / Own Data (no module permission needed)

| File | Reason |
|------|--------|
| `views/profile/ProfileView.vue` | User edits own profile |
| `views/notifications/NotificationsView.vue` | User manages own notifications |

### Auth / Layout Views

| File | Reason |
|------|--------|
| `views/auth/LoginView.vue` | Authentication page |
| `views/auth/ForgotPasswordView.vue` | Authentication page |
| `views/layout/DefaultLayout.vue` | Layout wrapper |
| `views/layout/NotFoundView.vue` | 404 page |

### Sidebar

| File | Reason |
|------|--------|
| `components/layout/AppSidebar.vue` | Uses `canRead(module)` for menu visibility (correct) |

---

## Common Patterns

### Standard List View

```vue
<script setup>
const canCreate = computed(() => authStore.canCreate('module_name'))
const canUpdate = computed(() => authStore.canUpdate('module_name'))
const canDelete = computed(() => authStore.canDelete('module_name'))
</script>

<template>
  <!-- Add button -->
  <a-button v-if="canCreate" @click="showAddModal">Add</a-button>

  <!-- Row actions -->
  <a-button v-if="canUpdate" @click="edit(record)">Edit</a-button>
  <a-popconfirm v-if="canDelete" @confirm="destroy(record.id)">
    <a-button>Delete</a-button>
  </a-popconfirm>

  <!-- Bulk delete row selection -->
  <a-table :row-selection="canDelete ? rowSelection : null" />
</template>
```

### Context-Aware Form View (EmployeeFormView pattern)

```vue
<script setup>
const isEditMode = computed(() => !!route.params.id)
const canSave = computed(() =>
  isEditMode.value
    ? authStore.canUpdate('employees')
    : authStore.canCreate('employees')
)
</script>
```

### Actions Column Visibility (RecordsTab pattern)

```vue
<script setup>
const showActions = computed(() => canUpdate.value || canDelete.value)
const columns = computed(() => {
  const cols = [/* data columns */]
  if (showActions.value) cols.push({ title: 'Actions', key: 'actions' })
  return cols
})
</script>
```

---

## Verification

Run these greps to verify the migration is complete:

```bash
# Should return ZERO results (no canEdit in templates)
grep -r "canEdit" src/views/ src/components/

# Should only appear in src/stores/auth.js (definition)
grep -rn "canEdit" src/stores/

# Check all granular permission usages
grep -rn "canCreate\|canUpdate\|canDelete\|canRead" src/views/
```

---

## Known Optional Enhancement

**ProfileView.vue** — The "My Permissions" section displays `Read` and `Edit` badges per module. It still shows the legacy `edit` label instead of showing 4 granular badges (`Read`, `Create`, `Update`, `Delete`). This is a **display-only issue** — it does not affect actual permission enforcement.
