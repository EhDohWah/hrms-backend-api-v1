# HRMS Workflow Improvement Implementation Plan

## Executive Summary

This document provides detailed implementation plans for 10 improvement areas identified in the HRMS Employee-to-Payroll workflow analysis. Each area includes technical specifications, database changes, API contracts, frontend modifications, and testing strategies.

---

# PRIORITY 1: Permission-Based UI Implementation

## Problem Statement

While the backend permission system (Spatie Laravel Permission) and frontend infrastructure (usePermissions composable, v-permission directive) are well-implemented, there are inconsistencies in how components apply permission checks. Some modules still show edit/delete buttons to read-only users, causing 403 errors when clicked.

## Current State Analysis

**What Already Exists:**
- `usePermissions()` composable with `canRead`, `canEdit`, `isReadOnly` methods
- `v-permission`, `v-can-edit`, `v-can-read` directives
- Real-time permission sync via WebSocket + BroadcastChannel
- Permission storage in Pinia authStore and localStorage

**Gaps Identified:**
- Inconsistent usage patterns across components (some use composable, some use direct localStorage)
- No TypeScript type safety for permission strings
- Missing read-only form mode implementation
- Some modules lack permission checks entirely

## Proposed Solution Architecture

### 1. Permission Constants File

**File:** `src/constants/permissions.js`

```javascript
/**
 * Centralized permission constants to prevent magic strings
 * and provide type safety for permission checks
 */
export const PERMISSIONS = {
  // Employee Module
  EMPLOYEES: {
    READ: 'employees.read',
    EDIT: 'employees.edit',
  },
  EMPLOYMENT_RECORDS: {
    READ: 'employment_records.read',
    EDIT: 'employment_records.edit',
  },

  // Recruitment Module
  INTERVIEWS: {
    READ: 'interviews.read',
    EDIT: 'interviews.edit',
  },
  JOB_OFFERS: {
    READ: 'job_offers.read',
    EDIT: 'job_offers.edit',
  },

  // HRM Module
  HOLIDAYS: {
    READ: 'holidays.read',
    EDIT: 'holidays.edit',
  },

  // Payroll Module
  EMPLOYEE_SALARY: {
    READ: 'employee_salary.read',
    EDIT: 'employee_salary.edit',
  },
  TAX_SETTINGS: {
    READ: 'tax_settings.read',
    EDIT: 'tax_settings.edit',
  },

  // Grants Module
  GRANTS_LIST: {
    READ: 'grants_list.read',
    EDIT: 'grants_list.edit',
  },

  // Leave Module
  LEAVES_ADMIN: {
    READ: 'leaves_admin.read',
    EDIT: 'leaves_admin.edit',
  },

  // User Management
  USERS: {
    READ: 'users.read',
    EDIT: 'users.edit',
  },
  ROLES: {
    READ: 'roles.read',
    EDIT: 'roles.edit',
  },
};

// Helper to get all permissions for a module
export const getModulePermissions = (module) => {
  return PERMISSIONS[module] || null;
};

// Get permission string
export const P = PERMISSIONS;
```

### 2. Enhanced usePermissions Composable

**File:** `src/composables/usePermissions.js` (Updates)

```javascript
import { computed, ref, watch, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '@/stores/authStore';
import { PERMISSIONS } from '@/constants/permissions';

/**
 * Enhanced usePermissions composable with form mode support
 * @param {string} moduleName - The module to check permissions for
 * @returns {Object} Permission utilities
 */
export function usePermissions(moduleName = null) {
  const authStore = useAuthStore();
  const formMode = ref('view'); // 'view' | 'edit' | 'create'

  // Normalize module name
  const normalizedModule = computed(() => {
    if (!moduleName) return null;
    return MODULE_NAME_MAP[moduleName.toLowerCase()] || moduleName.toLowerCase();
  });

  // Core permission checks
  const canRead = computed(() => {
    if (!normalizedModule.value) return false;
    return hasPermission(`${normalizedModule.value}.read`);
  });

  const canEdit = computed(() => {
    if (!normalizedModule.value) return false;
    return hasPermission(`${normalizedModule.value}.edit`);
  });

  const isReadOnly = computed(() => {
    return canRead.value && !canEdit.value;
  });

  // Form mode management
  const effectiveFormMode = computed(() => {
    if (isReadOnly.value) return 'view';
    return formMode.value;
  });

  const isFormDisabled = computed(() => {
    return effectiveFormMode.value === 'view' || isReadOnly.value;
  });

  const setFormMode = (mode) => {
    if (isReadOnly.value && mode !== 'view') {
      console.warn(`[usePermissions] Cannot set mode to ${mode} - user has read-only access`);
      return;
    }
    formMode.value = mode;
  };

  // Button visibility helpers
  const showCreateButton = computed(() => canEdit.value);
  const showEditButton = computed(() => canEdit.value);
  const showDeleteButton = computed(() => canEdit.value);
  const showImportButton = computed(() => canEdit.value);
  const showExportButton = computed(() => canRead.value); // Export only needs read

  // Access level display
  const accessLevelBadge = computed(() => {
    if (canEdit.value) {
      return { text: 'Full Access', class: 'badge bg-success', icon: 'ti-edit' };
    }
    if (canRead.value) {
      return { text: 'Read Only', class: 'badge bg-warning text-dark', icon: 'ti-eye' };
    }
    return { text: 'No Access', class: 'badge bg-danger', icon: 'ti-lock' };
  });

  // Form field props generator
  const getFieldProps = (additionalProps = {}) => {
    return {
      disabled: isFormDisabled.value,
      readonly: isReadOnly.value,
      ...additionalProps,
    };
  };

  // Button props generator
  const getButtonProps = (action = 'edit') => {
    const disabled = action === 'export' ? !canRead.value : !canEdit.value;
    return {
      disabled,
      class: disabled ? 'btn-secondary opacity-50' : '',
      title: disabled ? 'You do not have permission to perform this action' : '',
    };
  };

  return {
    // Core checks
    canRead,
    canEdit,
    isReadOnly,
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,

    // Form mode
    formMode,
    effectiveFormMode,
    isFormDisabled,
    setFormMode,

    // Button visibility
    showCreateButton,
    showEditButton,
    showDeleteButton,
    showImportButton,
    showExportButton,

    // Display helpers
    accessLevelBadge,

    // Prop generators
    getFieldProps,
    getButtonProps,

    // Constants reference
    PERMISSIONS,
  };
}
```

### 3. Read-Only Form Component Wrapper

**File:** `src/components/common/PermissionAwareForm.vue`

```vue
<template>
  <div class="permission-aware-form">
    <!-- Access Level Banner -->
    <div v-if="showAccessBanner && isReadOnly" class="alert alert-warning d-flex align-items-center mb-3">
      <i class="ti ti-eye me-2 fs-4"></i>
      <div>
        <strong>Read-Only Mode</strong>
        <p class="mb-0 small">You have view-only access to this module. Contact an administrator for edit permissions.</p>
      </div>
    </div>

    <!-- Form Content -->
    <fieldset :disabled="isFormDisabled">
      <slot
        :is-read-only="isReadOnly"
        :is-form-disabled="isFormDisabled"
        :can-edit="canEdit"
        :get-field-props="getFieldProps"
      />
    </fieldset>

    <!-- Action Buttons -->
    <div v-if="showActionButtons" class="form-actions mt-3">
      <slot name="actions" :can-edit="canEdit" :is-read-only="isReadOnly">
        <button
          v-if="showSubmitButton && canEdit"
          type="submit"
          class="btn btn-primary"
          :disabled="isSubmitting"
        >
          <span v-if="isSubmitting" class="spinner-border spinner-border-sm me-2"></span>
          {{ submitLabel }}
        </button>
        <button
          type="button"
          class="btn btn-secondary ms-2"
          @click="$emit('cancel')"
        >
          {{ isReadOnly ? 'Close' : 'Cancel' }}
        </button>
      </slot>
    </div>
  </div>
</template>

<script setup>
import { defineProps, defineEmits, toRefs } from 'vue';
import { usePermissions } from '@/composables/usePermissions';

const props = defineProps({
  module: { type: String, required: true },
  showAccessBanner: { type: Boolean, default: true },
  showActionButtons: { type: Boolean, default: true },
  showSubmitButton: { type: Boolean, default: true },
  submitLabel: { type: String, default: 'Save' },
  isSubmitting: { type: Boolean, default: false },
});

const emit = defineEmits(['cancel']);

const { module } = toRefs(props);
const { canEdit, isReadOnly, isFormDisabled, getFieldProps } = usePermissions(module.value);
</script>

<style scoped>
.permission-aware-form fieldset:disabled {
  opacity: 0.8;
}

.permission-aware-form fieldset:disabled input,
.permission-aware-form fieldset:disabled select,
.permission-aware-form fieldset:disabled textarea {
  background-color: #f8f9fa;
  cursor: not-allowed;
}
</style>
```

### 4. Module-Specific Implementation Examples

#### Employee List Component Update

**File:** `src/views/employees/employees-list.vue` (Key sections)

```vue
<template>
  <div class="employees-list">
    <!-- Access Level Badge -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4>Employees</h4>
      <span :class="accessLevelBadge.class">
        <i :class="['ti', accessLevelBadge.icon, 'me-1']"></i>
        {{ accessLevelBadge.text }}
      </span>
    </div>

    <!-- Action Buttons - Only show if user can edit -->
    <div class="action-buttons mb-3">
      <button
        v-if="showCreateButton"
        class="btn btn-primary me-2"
        @click="openCreateModal"
      >
        <i class="ti ti-plus me-1"></i> Add Employee
      </button>

      <button
        v-if="showImportButton"
        class="btn btn-outline-primary me-2"
        @click="openImportModal"
      >
        <i class="ti ti-upload me-1"></i> Import
      </button>

      <!-- Export always visible for read users -->
      <button
        v-if="showExportButton"
        class="btn btn-outline-secondary"
        @click="exportEmployees"
      >
        <i class="ti ti-download me-1"></i> Export
      </button>
    </div>

    <!-- Data Table -->
    <DataTable :data="employees" :columns="columns">
      <template #actions="{ row }">
        <!-- View button always visible -->
        <button class="btn btn-sm btn-info me-1" @click="viewEmployee(row)">
          <i class="ti ti-eye"></i>
        </button>

        <!-- Edit/Delete only if can edit -->
        <template v-if="canEdit">
          <button class="btn btn-sm btn-warning me-1" @click="editEmployee(row)">
            <i class="ti ti-edit"></i>
          </button>
          <button class="btn btn-sm btn-danger" @click="confirmDelete(row)">
            <i class="ti ti-trash"></i>
          </button>
        </template>
      </template>
    </DataTable>
  </div>
</template>

<script setup>
import { usePermissions } from '@/composables/usePermissions';
import { P } from '@/constants/permissions';

// Use permissions for employees module
const {
  canEdit,
  isReadOnly,
  showCreateButton,
  showEditButton,
  showDeleteButton,
  showImportButton,
  showExportButton,
  accessLevelBadge,
} = usePermissions('employees');

// Component logic...
</script>
```

## Implementation Steps

### Phase 1: Foundation (Day 1-2)
1. Create `src/constants/permissions.js` with all permission constants
2. Update `usePermissions.js` composable with enhanced features
3. Create `PermissionAwareForm.vue` wrapper component
4. Add unit tests for permission utilities

### Phase 2: Priority Modules (Day 3-5)
1. **Employees Module** - Apply permission checks to list, create, edit views
2. **Interviews Module** - Apply permission checks
3. **Holidays Module** - Apply permission checks
4. **Job Offers Module** - Apply permission checks
5. **Attendance Admin Module** - Apply permission checks

### Phase 3: Remaining Modules (Day 6-8)
1. Apply to all remaining modules systematically
2. Test each module with read-only and edit users
3. Fix any edge cases discovered

### Phase 4: Testing & Documentation (Day 9-10)
1. End-to-end testing with different user roles
2. Update user documentation
3. Create permission matrix documentation

## Test Scenarios

| Scenario | Expected Behavior |
|----------|------------------|
| Read-only user views employee list | See employees, no Add/Edit/Delete buttons |
| Read-only user opens employee modal | Form fields disabled, "Read Only" badge shown |
| Edit user views employee list | All buttons visible and functional |
| User permissions change in real-time | UI updates immediately via WebSocket |
| Read-only user tries direct URL to edit | Redirected or shown access denied |

## User Acceptance Criteria

1. Users with `module.read` only see view buttons and export options
2. Users with `module.edit` see full CRUD buttons
3. Read-only badge clearly visible when in read-only mode
4. Form fields visually indicate disabled state
5. No 403 errors from UI actions (buttons hidden appropriately)
6. Permission changes reflect immediately without page refresh

---

# PRIORITY 2: Payroll Calculation Transparency

## Problem Statement

The payroll calculation process involves complex calculations (13 items including Thai tax brackets) but users cannot see the step-by-step breakdown of how the final net salary was calculated. This makes auditing difficult and reduces user confidence in the system.

## Proposed Solution Architecture

### 1. Database Changes

**Migration:** `database/migrations/2025_12_28_000001_add_calculation_breakdown_to_payrolls.php`

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            // Calculation breakdown stored as encrypted JSON
            $table->text('calculation_breakdown')->nullable()->after('notes');

            // Tax calculation details
            $table->text('tax_breakdown')->nullable()->after('calculation_breakdown');

            // Allocation breakdown per funding source
            $table->text('allocation_breakdown')->nullable()->after('tax_breakdown');

            // Calculation version for audit trail
            $table->string('calculation_version', 20)->default('1.0')->after('allocation_breakdown');

            // Timestamp when calculation was last updated
            $table->timestamp('calculated_at')->nullable()->after('calculation_version');
        });
    }

    public function down(): void
    {
        Schema::table('payrolls', function (Blueprint $table) {
            $table->dropColumn([
                'calculation_breakdown',
                'tax_breakdown',
                'allocation_breakdown',
                'calculation_version',
                'calculated_at',
            ]);
        });
    }
};
```

### 2. Calculation Breakdown Data Structure

```php
// calculation_breakdown JSON structure
{
    "version": "1.0",
    "calculated_at": "2025-12-28T10:30:00Z",
    "calculated_by": "system",
    "steps": [
        {
            "step": 1,
            "name": "Base Salary",
            "description": "Monthly base salary from employment record",
            "source": "employment.base_salary",
            "value": 50000.00,
            "currency": "THB"
        },
        {
            "step": 2,
            "name": "FTE Adjustment",
            "description": "Salary adjusted for Full-Time Equivalent allocation",
            "calculation": "50000.00 × 1.0000",
            "fte": 1.0000,
            "value": 50000.00
        },
        {
            "step": 3,
            "name": "Probation Adjustment",
            "description": "No adjustment - employee passed probation",
            "probation_status": "completed",
            "adjustment": 0.00,
            "value": 50000.00
        },
        {
            "step": 4,
            "name": "Gross Salary",
            "description": "Total gross salary before deductions",
            "value": 50000.00
        },
        {
            "step": 5,
            "name": "PVD Contribution",
            "description": "Provident Fund contribution (employee portion)",
            "rate": "5%",
            "calculation": "50000.00 × 0.05",
            "value": -2500.00
        },
        {
            "step": 6,
            "name": "Social Security (Employee)",
            "description": "Social security contribution capped at 750 THB",
            "rate": "5%",
            "uncapped": 2500.00,
            "cap": 750.00,
            "value": -750.00
        },
        {
            "step": 7,
            "name": "Income Tax",
            "description": "Thai progressive income tax",
            "reference": "tax_breakdown",
            "value": -3500.00
        },
        {
            "step": 8,
            "name": "Net Salary",
            "description": "Final take-home pay",
            "calculation": "50000.00 - 2500.00 - 750.00 - 3500.00",
            "value": 43250.00
        }
    ],
    "summary": {
        "gross_salary": 50000.00,
        "total_deductions": 6750.00,
        "net_salary": 43250.00,
        "employer_contributions": {
            "social_security": 750.00,
            "pvd_match": 2500.00
        }
    }
}

// tax_breakdown JSON structure
{
    "tax_year": 2025,
    "annual_income": 600000.00,
    "deductions": {
        "employment_expense": {
            "description": "50% of income, max 100,000 THB",
            "calculation": "min(600000 × 0.5, 100000)",
            "value": 100000.00
        },
        "personal_allowance": {
            "description": "Personal tax allowance",
            "value": 60000.00
        },
        "spouse_allowance": {
            "description": "Spouse allowance (if applicable)",
            "eligible": false,
            "value": 0.00
        },
        "child_allowance": {
            "description": "Child education allowance",
            "children": 2,
            "per_child": 30000.00,
            "value": 60000.00
        },
        "parent_allowance": {
            "description": "Parent care allowance",
            "parents": 1,
            "per_parent": 30000.00,
            "value": 30000.00
        },
        "social_security": {
            "description": "Annual social security contributions",
            "value": 9000.00
        },
        "pvd": {
            "description": "Provident fund contributions",
            "value": 30000.00
        }
    },
    "total_deductions": 289000.00,
    "taxable_income": 311000.00,
    "tax_brackets_applied": [
        {
            "bracket": "0 - 150,000",
            "rate": "0%",
            "income_in_bracket": 150000.00,
            "tax": 0.00
        },
        {
            "bracket": "150,001 - 300,000",
            "rate": "5%",
            "income_in_bracket": 150000.00,
            "tax": 7500.00
        },
        {
            "bracket": "300,001 - 500,000",
            "rate": "10%",
            "income_in_bracket": 11000.00,
            "tax": 1100.00
        }
    ],
    "annual_tax": 8600.00,
    "monthly_tax": 716.67,
    "withholding_this_period": 3500.00
}
```

### 3. Backend Implementation

**Updated PayrollService:** `app/Services/PayrollService.php`

```php
<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Payroll;
use Carbon\Carbon;

class PayrollService
{
    protected array $calculationSteps = [];
    protected array $taxBreakdown = [];
    protected array $allocationBreakdown = [];

    public function processEmployeePayroll(
        Employee $employee,
        Carbon $payPeriodDate,
        bool $savePayroll = true,
        bool $includeBreakdown = true
    ): array {
        $this->resetCalculationTracking();

        $employment = $employee->latestEmployment;
        if (!$employment) {
            throw new \Exception("No active employment found for employee {$employee->staff_id}");
        }

        // Step 1: Get base salary
        $baseSalary = $employment->getCurrentSalary();
        $this->addCalculationStep(1, 'Base Salary', 'Monthly base salary from employment record', [
            'source' => 'employment.base_salary',
            'value' => $baseSalary,
            'currency' => 'THB',
        ]);

        // Step 2: Calculate FTE-adjusted salary
        $allocations = $employee->activeFundingAllocations;
        $totalFte = $allocations->sum('fte');
        $fteSalary = $baseSalary * $totalFte;
        $this->addCalculationStep(2, 'FTE Adjustment', 'Salary adjusted for Full-Time Equivalent allocation', [
            'calculation' => sprintf('%.2f × %.4f', $baseSalary, $totalFte),
            'fte' => $totalFte,
            'value' => $fteSalary,
        ]);

        // Step 3: Probation adjustment
        $probationAdjustment = $this->calculateProbationAdjustment($employment, $payPeriodDate);
        $this->addCalculationStep(3, 'Probation Adjustment', $probationAdjustment['description'], [
            'probation_status' => $employment->probation_status,
            'adjustment' => $probationAdjustment['amount'],
            'value' => $fteSalary + $probationAdjustment['amount'],
        ]);

        $grossSalary = $fteSalary + $probationAdjustment['amount'];

        // Step 4: Record gross salary
        $this->addCalculationStep(4, 'Gross Salary', 'Total gross salary before deductions', [
            'value' => $grossSalary,
        ]);

        // Step 5: PVD contribution
        $pvdRate = $this->getPvdRate($employee);
        $pvdAmount = $grossSalary * $pvdRate;
        $this->addCalculationStep(5, 'PVD Contribution', 'Provident Fund contribution (employee portion)', [
            'rate' => ($pvdRate * 100) . '%',
            'calculation' => sprintf('%.2f × %.2f', $grossSalary, $pvdRate),
            'value' => -$pvdAmount,
        ]);

        // Step 6: Social Security
        $ssRate = 0.05;
        $ssCap = 750;
        $ssUncapped = $grossSalary * $ssRate;
        $ssAmount = min($ssUncapped, $ssCap);
        $this->addCalculationStep(6, 'Social Security (Employee)', 'Social security contribution capped at 750 THB', [
            'rate' => '5%',
            'uncapped' => $ssUncapped,
            'cap' => $ssCap,
            'value' => -$ssAmount,
        ]);

        // Step 7: Income Tax (detailed calculation)
        $taxResult = $this->calculateTaxWithBreakdown($employee, $grossSalary, $pvdAmount, $ssAmount);
        $this->taxBreakdown = $taxResult['breakdown'];
        $this->addCalculationStep(7, 'Income Tax', 'Thai progressive income tax', [
            'reference' => 'tax_breakdown',
            'value' => -$taxResult['monthly_tax'],
        ]);

        // Step 8: Net Salary
        $netSalary = $grossSalary - $pvdAmount - $ssAmount - $taxResult['monthly_tax'];
        $this->addCalculationStep(8, 'Net Salary', 'Final take-home pay', [
            'calculation' => sprintf('%.2f - %.2f - %.2f - %.2f', $grossSalary, $pvdAmount, $ssAmount, $taxResult['monthly_tax']),
            'value' => $netSalary,
        ]);

        // Build allocation breakdown
        $this->buildAllocationBreakdown($allocations, $grossSalary);

        // Prepare payroll data
        $payrollData = [
            'employee_id' => $employee->id,
            'employment_id' => $employment->id,
            'pay_period_start' => $payPeriodDate->copy()->startOfMonth(),
            'pay_period_end' => $payPeriodDate->copy()->endOfMonth(),
            'gross_salary' => $grossSalary,
            'gross_salary_by_fte' => $fteSalary,
            'pvd_saving_fund' => $pvdAmount,
            'employee_social_security' => $ssAmount,
            'employer_social_security' => $ssAmount, // Employer matches
            'income_tax' => $taxResult['monthly_tax'],
            'net_salary' => $netSalary,
            'total_salary' => $grossSalary,
            'calculation_breakdown' => $includeBreakdown ? $this->getCalculationBreakdown() : null,
            'tax_breakdown' => $includeBreakdown ? $this->taxBreakdown : null,
            'allocation_breakdown' => $includeBreakdown ? $this->allocationBreakdown : null,
            'calculation_version' => '1.0',
            'calculated_at' => now(),
        ];

        if ($savePayroll) {
            $payroll = Payroll::create($payrollData);
            return ['payroll' => $payroll, 'breakdown' => $this->getFullBreakdown()];
        }

        return ['data' => $payrollData, 'breakdown' => $this->getFullBreakdown()];
    }

    protected function addCalculationStep(int $step, string $name, string $description, array $data): void
    {
        $this->calculationSteps[] = array_merge([
            'step' => $step,
            'name' => $name,
            'description' => $description,
        ], $data);
    }

    protected function getCalculationBreakdown(): array
    {
        return [
            'version' => '1.0',
            'calculated_at' => now()->toIso8601String(),
            'calculated_by' => auth()->user()?->name ?? 'system',
            'steps' => $this->calculationSteps,
            'summary' => $this->buildSummary(),
        ];
    }

    protected function getFullBreakdown(): array
    {
        return [
            'calculation' => $this->getCalculationBreakdown(),
            'tax' => $this->taxBreakdown,
            'allocations' => $this->allocationBreakdown,
        ];
    }

    // ... additional helper methods
}
```

### 4. New API Endpoints

**Route:** `routes/api/payroll.php`

```php
// Payroll calculation breakdown
Route::get('/payrolls/{id}/breakdown', [PayrollController::class, 'getBreakdown'])
    ->middleware('permission:employee_salary.read');

Route::get('/payrolls/{id}/tax-breakdown', [PayrollController::class, 'getTaxBreakdown'])
    ->middleware('permission:employee_salary.read');

Route::get('/payrolls/{id}/comparison/{compareId}', [PayrollController::class, 'comparePayrolls'])
    ->middleware('permission:employee_salary.read');

Route::post('/payrolls/{id}/recalculate', [PayrollController::class, 'recalculate'])
    ->middleware('permission:employee_salary.edit');

Route::get('/payrolls/{id}/export-breakdown', [PayrollController::class, 'exportBreakdown'])
    ->middleware('permission:employee_salary.read');
```

**Controller Methods:** `app/Http/Controllers/Api/PayrollController.php`

```php
/**
 * Get detailed calculation breakdown for a payroll
 */
public function getBreakdown(int $id): JsonResponse
{
    $payroll = Payroll::with(['employee', 'employment'])->findOrFail($id);

    return response()->json([
        'success' => true,
        'data' => [
            'payroll_id' => $payroll->id,
            'employee' => $payroll->employee->full_name,
            'pay_period' => $payroll->pay_period_start->format('F Y'),
            'calculation_breakdown' => $payroll->calculation_breakdown,
            'tax_breakdown' => $payroll->tax_breakdown,
            'allocation_breakdown' => $payroll->allocation_breakdown,
            'calculation_version' => $payroll->calculation_version,
            'calculated_at' => $payroll->calculated_at,
        ],
    ]);
}

/**
 * Compare two payroll periods for variance analysis
 */
public function comparePayrolls(int $id, int $compareId): JsonResponse
{
    $current = Payroll::findOrFail($id);
    $previous = Payroll::findOrFail($compareId);

    $comparison = [
        'current_period' => $current->pay_period_start->format('F Y'),
        'previous_period' => $previous->pay_period_start->format('F Y'),
        'variances' => [
            'gross_salary' => [
                'current' => $current->gross_salary,
                'previous' => $previous->gross_salary,
                'change' => $current->gross_salary - $previous->gross_salary,
                'change_percent' => $this->calculatePercentChange($previous->gross_salary, $current->gross_salary),
            ],
            'net_salary' => [
                'current' => $current->net_salary,
                'previous' => $previous->net_salary,
                'change' => $current->net_salary - $previous->net_salary,
                'change_percent' => $this->calculatePercentChange($previous->net_salary, $current->net_salary),
            ],
            'income_tax' => [
                'current' => $current->income_tax,
                'previous' => $previous->income_tax,
                'change' => $current->income_tax - $previous->income_tax,
                'change_percent' => $this->calculatePercentChange($previous->income_tax, $current->income_tax),
            ],
            // ... more fields
        ],
        'explanations' => $this->generateVarianceExplanations($current, $previous),
    ];

    return response()->json(['success' => true, 'data' => $comparison]);
}

/**
 * Export payroll breakdown to Excel with formulas
 */
public function exportBreakdown(int $id): BinaryFileResponse
{
    $payroll = Payroll::with(['employee', 'employment', 'employee.activeFundingAllocations.grant'])
        ->findOrFail($id);

    return Excel::download(
        new PayrollBreakdownExport($payroll),
        "payroll_breakdown_{$payroll->employee->staff_id}_{$payroll->pay_period_start->format('Y_m')}.xlsx"
    );
}
```

### 5. Frontend Component

**File:** `src/components/payroll/PayrollBreakdownModal.vue`

```vue
<template>
  <Modal v-model="isVisible" title="Payroll Calculation Breakdown" size="xl">
    <template #body>
      <!-- Summary Cards -->
      <div class="row mb-4">
        <div class="col-md-3">
          <div class="card bg-primary text-white">
            <div class="card-body text-center">
              <h6>Gross Salary</h6>
              <h3>{{ formatCurrency(breakdown?.summary?.gross_salary) }}</h3>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card bg-danger text-white">
            <div class="card-body text-center">
              <h6>Deductions</h6>
              <h3>{{ formatCurrency(breakdown?.summary?.total_deductions) }}</h3>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card bg-success text-white">
            <div class="card-body text-center">
              <h6>Net Salary</h6>
              <h3>{{ formatCurrency(breakdown?.summary?.net_salary) }}</h3>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="card bg-info text-white">
            <div class="card-body text-center">
              <h6>Employer Cost</h6>
              <h3>{{ formatCurrency(employerTotal) }}</h3>
            </div>
          </div>
        </div>
      </div>

      <!-- Tabs for different breakdowns -->
      <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item">
          <a class="nav-link active" data-bs-toggle="tab" href="#calculation">
            <i class="ti ti-calculator me-1"></i> Calculation Steps
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-bs-toggle="tab" href="#tax">
            <i class="ti ti-receipt-tax me-1"></i> Tax Details
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-bs-toggle="tab" href="#allocations">
            <i class="ti ti-chart-pie me-1"></i> Funding Allocations
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link" data-bs-toggle="tab" href="#comparison">
            <i class="ti ti-arrows-diff me-1"></i> Period Comparison
          </a>
        </li>
      </ul>

      <div class="tab-content p-3">
        <!-- Calculation Steps Tab -->
        <div class="tab-pane fade show active" id="calculation">
          <div class="calculation-timeline">
            <div
              v-for="step in breakdown?.steps"
              :key="step.step"
              class="timeline-item"
              :class="{ 'text-danger': step.value < 0, 'text-success': step.value > 0 }"
            >
              <div class="timeline-marker">{{ step.step }}</div>
              <div class="timeline-content">
                <h6>{{ step.name }}</h6>
                <p class="text-muted small mb-1">{{ step.description }}</p>
                <div v-if="step.calculation" class="font-monospace small">
                  {{ step.calculation }}
                </div>
                <div class="h5 mb-0">
                  <span v-if="step.value < 0">-</span>
                  {{ formatCurrency(Math.abs(step.value)) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Tax Details Tab -->
        <div class="tab-pane fade" id="tax">
          <div class="row">
            <div class="col-md-6">
              <h6>Deductions</h6>
              <table class="table table-sm">
                <tbody>
                  <tr v-for="(deduction, key) in taxBreakdown?.deductions" :key="key">
                    <td>{{ deduction.description }}</td>
                    <td class="text-end">{{ formatCurrency(deduction.value) }}</td>
                  </tr>
                  <tr class="table-active fw-bold">
                    <td>Total Deductions</td>
                    <td class="text-end">{{ formatCurrency(taxBreakdown?.total_deductions) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <div class="col-md-6">
              <h6>Tax Brackets Applied</h6>
              <table class="table table-sm">
                <thead>
                  <tr>
                    <th>Bracket</th>
                    <th>Rate</th>
                    <th class="text-end">Tax</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="bracket in taxBreakdown?.tax_brackets_applied" :key="bracket.bracket">
                    <td>{{ bracket.bracket }}</td>
                    <td>{{ bracket.rate }}</td>
                    <td class="text-end">{{ formatCurrency(bracket.tax) }}</td>
                  </tr>
                  <tr class="table-warning fw-bold">
                    <td colspan="2">Monthly Withholding</td>
                    <td class="text-end">{{ formatCurrency(taxBreakdown?.monthly_tax) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Allocations Tab -->
        <div class="tab-pane fade" id="allocations">
          <div class="allocation-chart mb-3">
            <canvas ref="allocationChart"></canvas>
          </div>
          <table class="table">
            <thead>
              <tr>
                <th>Funding Source</th>
                <th>Grant Code</th>
                <th class="text-center">FTE %</th>
                <th class="text-end">Amount</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="allocation in allocationBreakdown" :key="allocation.id">
                <td>
                  <span class="badge" :style="{ backgroundColor: allocation.color }">
                    {{ allocation.source_type }}
                  </span>
                  {{ allocation.source_name }}
                </td>
                <td>{{ allocation.grant_code || 'N/A' }}</td>
                <td class="text-center">{{ (allocation.fte * 100).toFixed(2) }}%</td>
                <td class="text-end">{{ formatCurrency(allocation.amount) }}</td>
              </tr>
            </tbody>
            <tfoot>
              <tr class="table-primary fw-bold">
                <td colspan="2">Total</td>
                <td class="text-center">{{ totalFte }}%</td>
                <td class="text-end">{{ formatCurrency(breakdown?.summary?.gross_salary) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>

        <!-- Comparison Tab -->
        <div class="tab-pane fade" id="comparison">
          <div v-if="comparison" class="comparison-view">
            <table class="table table-bordered">
              <thead>
                <tr>
                  <th>Item</th>
                  <th class="text-end">{{ comparison.previous_period }}</th>
                  <th class="text-end">{{ comparison.current_period }}</th>
                  <th class="text-end">Change</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(variance, key) in comparison.variances" :key="key">
                  <td>{{ formatLabel(key) }}</td>
                  <td class="text-end">{{ formatCurrency(variance.previous) }}</td>
                  <td class="text-end">{{ formatCurrency(variance.current) }}</td>
                  <td class="text-end" :class="varianceClass(variance.change)">
                    {{ variance.change >= 0 ? '+' : '' }}{{ formatCurrency(variance.change) }}
                    <small>({{ variance.change_percent }}%)</small>
                  </td>
                </tr>
              </tbody>
            </table>

            <div v-if="comparison.explanations?.length" class="alert alert-info mt-3">
              <h6><i class="ti ti-info-circle me-1"></i> Variance Explanations</h6>
              <ul class="mb-0">
                <li v-for="(exp, idx) in comparison.explanations" :key="idx">{{ exp }}</li>
              </ul>
            </div>
          </div>
          <div v-else class="text-center py-4 text-muted">
            <p>Select a previous period to compare</p>
            <select v-model="selectedComparePeriod" class="form-select w-auto mx-auto">
              <option value="">Choose period...</option>
              <option v-for="period in availablePeriods" :key="period.id" :value="period.id">
                {{ period.label }}
              </option>
            </select>
          </div>
        </div>
      </div>
    </template>

    <template #footer>
      <button class="btn btn-outline-secondary" @click="exportToExcel">
        <i class="ti ti-file-spreadsheet me-1"></i> Export to Excel
      </button>
      <button class="btn btn-outline-primary" @click="printBreakdown">
        <i class="ti ti-printer me-1"></i> Print
      </button>
      <button class="btn btn-secondary" @click="close">Close</button>
    </template>
  </Modal>
</template>
```

## Implementation Steps

### Phase 1: Backend (Days 1-3)
1. Create database migration for breakdown columns
2. Update PayrollService with calculation tracking
3. Create PayrollBreakdownExport class for Excel export
4. Add new API endpoints
5. Write unit tests for calculation breakdown

### Phase 2: Frontend (Days 4-6)
1. Create PayrollBreakdownModal component
2. Add Chart.js integration for allocation pie chart
3. Implement comparison view with previous periods
4. Add export functionality
5. Integration testing

### Phase 3: Testing & Documentation (Days 7-8)
1. End-to-end testing with various payroll scenarios
2. Verify calculation accuracy
3. Document API endpoints
4. Update user guide

## Test Scenarios

| Scenario | Expected Result |
|----------|----------------|
| View breakdown for standard employee | All 8 steps shown with correct values |
| View breakdown for probation employee | Step 3 shows probation adjustment |
| View tax breakdown | All deductions and brackets displayed |
| Compare two periods | Variances calculated with explanations |
| Export to Excel | Formulas visible, calculations match |

---

# PRIORITY 3: Funding Allocation Validation Improvements

## Problem Statement

The current 100% FTE validation works but users cannot see real-time grant capacity when selecting funding sources. Over-allocation can occur if multiple users create allocations simultaneously.

## Proposed Solution

### 1. Real-Time Grant Capacity API

**Endpoint:** `GET /api/v1/grants/items/{id}/capacity`

**Response:**
```json
{
  "success": true,
  "data": {
    "grant_item_id": 123,
    "grant_code": "GF2024-001",
    "position_title": "Research Assistant",
    "total_fte": 2.0,
    "allocated_fte": 1.5,
    "remaining_fte": 0.5,
    "utilization_percent": 75,
    "warning_threshold": 90,
    "is_near_capacity": false,
    "active_allocations": [
      {
        "employee_id": 1,
        "employee_name": "John Doe",
        "fte": 0.5,
        "effective_date": "2025-01-01"
      }
    ]
  }
}
```

### 2. Optimistic Locking for Concurrent Allocations

**Migration:**
```php
Schema::table('employee_funding_allocations', function (Blueprint $table) {
    $table->unsignedInteger('lock_version')->default(0);
    $table->timestamp('locked_until')->nullable();
    $table->unsignedBigInteger('locked_by')->nullable();
});
```

**Service Update:**
```php
public function createAllocation(array $data): EmployeeFundingAllocation
{
    return DB::transaction(function () use ($data) {
        // Lock the grant item row for update
        $grantItem = GrantItem::lockForUpdate()->find($data['grant_item_id']);

        // Validate capacity
        $currentAllocated = $grantItem->allocations()->active()->sum('fte');
        $newTotal = $currentAllocated + $data['fte'];

        if ($newTotal > $grantItem->total_fte) {
            throw new OverAllocationException(
                "Cannot allocate {$data['fte']} FTE. Only " .
                ($grantItem->total_fte - $currentAllocated) . " FTE remaining."
            );
        }

        // Create allocation
        $allocation = EmployeeFundingAllocation::create($data);

        // Update grant item remaining FTE
        $grantItem->update(['remaining_fte' => $grantItem->total_fte - $newTotal]);

        return $allocation;
    });
}
```

### 3. Frontend Capacity Indicator

```vue
<template>
  <div class="grant-capacity-indicator">
    <div class="progress" style="height: 20px;">
      <div
        class="progress-bar"
        :class="capacityClass"
        :style="{ width: utilization + '%' }"
      >
        {{ utilization }}% Used
      </div>
    </div>
    <div class="d-flex justify-content-between small mt-1">
      <span>Allocated: {{ allocated }} FTE</span>
      <span :class="{ 'text-danger': remaining < requestedFte }">
        Remaining: {{ remaining }} FTE
      </span>
    </div>
    <div v-if="remaining < requestedFte" class="alert alert-danger mt-2 py-2">
      <i class="ti ti-alert-triangle me-1"></i>
      Insufficient capacity. Maximum available: {{ remaining }} FTE
    </div>
  </div>
</template>
```

---

# PRIORITY 4: Probation Workflow Enhancement

## Problem Statement

The probation system exists but lacks automated reminders, approval workflow, and dashboard visibility.

## Current State

**Exists:**
- ProbationRecord model with full lifecycle
- ProbationRecordService and ProbationTransitionService
- API endpoints for history, completion, status updates
- Console command for batch processing

**Missing:**
- Automated reminder notifications
- Scheduled job for reminder dispatch
- Dashboard widget for probation overview
- Approval workflow integration

## Proposed Enhancements

### 1. Probation Reminder Notification

**File:** `app/Notifications/ProbationReminderNotification.php`

```php
<?php

namespace App\Notifications;

use App\Models\Employment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProbationReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Employment $employment,
        public int $daysRemaining,
        public string $reminderType // '30_day', '15_day', '7_day', 'due_today'
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $employee = $this->employment->employee;

        return (new MailMessage)
            ->subject("Probation Ending Soon: {$employee->full_name}")
            ->greeting("Probation Period Alert")
            ->line("{$employee->full_name}'s probation period ends in {$this->daysRemaining} days.")
            ->line("End Date: " . $this->employment->pass_probation_date->format('F j, Y'))
            ->line("Position: {$this->employment->position}")
            ->action('Review Employee', url("/employees/{$employee->id}"))
            ->line('Please complete the probation evaluation before the end date.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'probation_reminder',
            'employment_id' => $this->employment->id,
            'employee_id' => $this->employment->employee_id,
            'employee_name' => $this->employment->employee->full_name,
            'days_remaining' => $this->daysRemaining,
            'end_date' => $this->employment->pass_probation_date->toDateString(),
            'reminder_type' => $this->reminderType,
        ];
    }
}
```

### 2. Scheduled Job for Reminders

**File:** `app/Console/Commands/SendProbationReminders.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\Employment;
use App\Models\User;
use App\Notifications\ProbationReminderNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SendProbationReminders extends Command
{
    protected $signature = 'probation:send-reminders {--dry-run}';
    protected $description = 'Send probation period reminder notifications';

    public function handle(): int
    {
        $reminderDays = [30, 15, 7, 0];
        $today = Carbon::today();
        $sentCount = 0;

        foreach ($reminderDays as $days) {
            $targetDate = $today->copy()->addDays($days);

            $employments = Employment::query()
                ->where('probation_status', 'in_probation')
                ->whereDate('pass_probation_date', $targetDate)
                ->with(['employee', 'employee.user'])
                ->get();

            foreach ($employments as $employment) {
                $reminderType = $days === 0 ? 'due_today' : "{$days}_day";

                // Notify HR managers
                $hrManagers = User::role(['admin', 'hr-manager'])->get();

                if ($this->option('dry-run')) {
                    $this->info("Would notify about {$employment->employee->full_name} - {$days} days remaining");
                    continue;
                }

                foreach ($hrManagers as $manager) {
                    $manager->notify(new ProbationReminderNotification(
                        $employment,
                        $days,
                        $reminderType
                    ));
                }

                // Also notify direct supervisor if set
                if ($employment->reporting_to) {
                    $supervisor = $employment->supervisor;
                    $supervisor?->user?->notify(new ProbationReminderNotification(
                        $employment,
                        $days,
                        $reminderType
                    ));
                }

                $sentCount++;
            }
        }

        $this->info("Sent {$sentCount} probation reminders");
        return Command::SUCCESS;
    }
}
```

### 3. Schedule Registration

**File:** `routes/console.php`

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('probation:send-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('employment:process-probation-transitions')
    ->dailyAt('00:01')
    ->withoutOverlapping()
    ->onOneServer();
```

### 4. Dashboard Widget API

**Endpoint:** `GET /api/v1/dashboard/probation-summary`

```json
{
  "success": true,
  "data": {
    "summary": {
      "currently_on_probation": 15,
      "ending_this_week": 3,
      "ending_this_month": 7,
      "passed_this_month": 5,
      "failed_this_month": 1
    },
    "upcoming": [
      {
        "employee_id": 1,
        "employee_name": "John Doe",
        "position": "Research Assistant",
        "department": "Research",
        "probation_end_date": "2025-01-15",
        "days_remaining": 5,
        "status": "pending_evaluation"
      }
    ],
    "recent_completions": [
      {
        "employee_id": 2,
        "employee_name": "Jane Smith",
        "completed_date": "2025-01-10",
        "result": "passed"
      }
    ]
  }
}
```

---

# PRIORITY 5: Reporting and Analytics

## Problem Statement

The system lacks comprehensive reporting for payroll analysis, funding utilization, and compliance requirements.

## Proposed Reports

### 1. Payroll Summary Report

**Endpoint:** `POST /api/v1/reports/payroll-summary`

**Request:**
```json
{
  "period_start": "2025-01-01",
  "period_end": "2025-12-31",
  "group_by": ["organization", "department"],
  "include_breakdown": true
}
```

**Response includes:**
- Total payroll cost by organization/department
- Employee count by category
- Average salary statistics
- Year-over-year comparison
- Drill-down capability

### 2. Funding Utilization Report

**Endpoint:** `POST /api/v1/reports/funding-utilization`

Shows:
- Allocated vs. remaining FTE per grant
- Budget utilization percentage
- Projected exhaustion dates
- Under-utilized grants alerts

### 3. Tax Compliance Report

**Endpoint:** `POST /api/v1/reports/tax-compliance`

For Thai government submission:
- Monthly withholding tax summary
- Social security contributions
- POR.NOR.DOR.1 format export

### 4. Dashboard Analytics

**New Dashboard Widgets:**
- Headcount trend chart (12 months)
- Payroll cost trend chart
- Funding utilization pie chart
- Probation status summary
- Upcoming renewals/expirations

---

# PRIORITIES 6-10: Additional Improvements

## Priority 6: Bulk Payroll Optimization

**Current State:**
- Batch size: 10 records
- Timeout: 1 hour
- No retry mechanism

**Improvements:**
- Configurable batch size (10-100)
- Checkpoint-based resume capability
- Performance benchmarking endpoint
- Detailed timing metrics

## Priority 7: Inter-Organization Advance Tracking

**Enhancements:**
- Settlement workflow with approvals
- Outstanding balance dashboard
- Automated reminder for pending settlements
- Reconciliation report

## Priority 8: Data Encryption Security Audit

**Actions:**
- Audit all PII fields for encryption
- Implement encryption key rotation
- Add field-level access logging
- Create compliance report

## Priority 9: Soft Delete Enhancement

**Improvements:**
- Configurable retention periods
- Scheduled permanent deletion job
- Cascade delete handling rules
- Restore dependency validation

## Priority 10: Real-Time Sync Robustness

**Enhancements:**
- HTTP polling fallback
- Connection recovery strategy
- Multi-tab conflict resolution
- Offline queue for pending updates

---

# Implementation Timeline

| Week | Priority | Deliverables |
|------|----------|--------------|
| 1-2 | P1 | Permission-based UI complete |
| 3-4 | P2 | Payroll transparency complete |
| 5 | P3 | Funding validation improvements |
| 6-7 | P4 | Probation workflow enhancements |
| 8-9 | P5 | Core reports implemented |
| 10+ | P6-P10 | Remaining improvements |

---

# Risk Mitigation

| Risk | Mitigation |
|------|------------|
| Breaking existing functionality | Feature flags for gradual rollout |
| Performance degradation | Benchmark before/after each change |
| Data migration issues | Comprehensive backup before migrations |
| User adoption | Training sessions and documentation |

---

*Document Version: 1.0*
*Created: 2025-12-27*
*Last Updated: 2025-12-27*
