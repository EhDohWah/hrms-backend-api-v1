# Frontend Employment Management System Migration Guide

## Document Version: 2.0
**Last Updated:** January 2025  
**Backend API Version:** v1 (Updated)  
**Target Frontend:** Vue 3 Composition API + Ant Design Vue

---

## Table of Contents
1. [Overview](#overview)
2. [Critical Backend Changes](#critical-backend-changes)
3. [Migration Strategy](#migration-strategy)
4. [API Endpoint Changes](#api-endpoint-changes)
5. [New Real-time Calculation API](#new-real-time-calculation-api)
6. [Component Updates Required](#component-updates-required)
7. [Implementation Guide](#implementation-guide)
8. [AI Prompt for Frontend Implementation](#ai-prompt-for-frontend-implementation)
9. [Testing Checklist](#testing-checklist)
10. [Rollback Plan](#rollback-plan)

---

## Overview

The backend Employment Management System has been completely refactored to improve:
- **Calculation Logic**: All funding allocation calculations now happen on the backend
- **Data Consistency**: Single source of truth for salary calculations
- **Real-time Feedback**: New API endpoint for instant allocation amount calculations
- **Simplified Data Flow**: Frontend no longer needs to duplicate calculation logic
- **Performance**: Optimized queries and caching strategies

### What Changed?
1. ✅ **Backend now calculates `allocated_amount`** - Frontend should not calculate
2. ✅ **New real-time calculation API endpoint** - `/api/employments/calculate-allocation`
3. ✅ **`end_date` remains optional** - No removal, kept for contract management
4. ✅ **FTE stored as decimal** - Backend converts percentage to decimal (0.60 for 60%)
5. ✅ **Salary field selection** - Backend uses `probation_salary` or `pass_probation_salary` automatically

---

## Critical Backend Changes

### 1. Funding Allocation Calculation Logic

**OLD Frontend Approach (DEPRECATED):**
```javascript
// ❌ DON'T DO THIS ANYMORE
getCalculatedSalary(ftePercentage) {
  const salary = (this.formData.position_salary * ftePercentage) / 100;
  return this.formatCurrency(salary);
}
```

**NEW Backend Approach (CORRECT):**
```php
// ✅ Backend handles calculation
$baseSalary = $employment->probation_salary ?? $employment->pass_probation_salary;
$allocatedAmount = ($baseSalary * $allocationData['fte']) / 100;
```

**Why This Matters:**
- Frontend was using wrong field (`position_salary` instead of `probation_salary`)
- Inconsistent calculations between frontend and backend
- Backend calculation is authoritative for payroll

### 2. FTE Storage Format

**Backend Storage:**
- Database stores FTE as **decimal** (e.g., 0.60 for 60%)
- API accepts FTE as **percentage** (e.g., 60)
- Backend converts: `$fteDecimal = $ftePercentage / 100`

**Frontend Handling:**
```javascript
// Send to API as percentage
const payload = {
  fte: 60  // ✅ Percentage
};

// Backend returns decimal in response
response.data.fte_decimal // 0.60
```

### 3. Salary Field Selection

**Backend Logic:**
```php
// Automatically selects correct salary
$baseSalary = $employment->probation_salary ?? $employment->pass_probation_salary;
```

**Frontend Impact:**
- No need to determine which salary field to use
- Backend handles probation period logic
- Consistent with payroll calculations

---

## Migration Strategy

### Phase 1: Add New API Integration (Non-Breaking)
1. Add new real-time calculation API service method
2. Keep existing calculation logic as fallback
3. Test with feature flag

### Phase 2: Update Components (Breaking Changes)
1. Remove local calculation methods
2. Implement real-time API calls
3. Update form submission to not send `allocated_amount`

### Phase 3: Cleanup (Final)
1. Remove deprecated calculation code
2. Update documentation
3. Remove feature flags

---

## API Endpoint Changes

### New Endpoints

#### 1. Calculate Allocation Amount (NEW)
```http
POST /api/employments/calculate-allocation
```

**Purpose:** Real-time calculation of allocation amount based on FTE

**Request:**
```json
{
  "employment_id": 123,
  "fte": 60
}
```

**Response:**
```json
{
  "success": true,
  "message": "Allocation amount calculated successfully",
  "data": {
    "employment_id": 123,
    "fte": 60,
    "fte_decimal": 0.60,
    "base_salary": 50000,
    "salary_type": "probation_salary",
    "allocated_amount": 30000,
    "formatted_amount": "฿30,000.00",
    "calculation_formula": "(50000 × 60) / 100 = 30000"
  }
}
```

**Error Responses:**
- `404`: Employment not found
- `422`: Validation error (invalid FTE)
- `401`: Unauthenticated

### Modified Endpoints

#### Create Employment
```http
POST /api/employments
```

**Changes:**
- `allocated_amount` in allocations array is now **OPTIONAL**
- Backend calculates if not provided
- `end_date` remains optional (no change)

**Request Example:**
```json
{
  "employee_id": 123,
  "employment_type": "Full-Time",
  "start_date": "2024-01-01",
  "end_date": null,
  "pass_probation_date": "2024-04-01",
  "department_id": 5,
  "position_id": 12,
  "work_location_id": 2,
  "pass_probation_salary": 50000,
  "probation_salary": 45000,
  "allocations": [
    {
      "allocation_type": "grant",
      "position_slot_id": 45,
      "fte": 60
      // ❌ NO allocated_amount - backend calculates
    },
    {
      "allocation_type": "org_funded",
      "grant_id": 2,
      "department_id": 5,
      "position_id": 12,
      "fte": 40
      // ❌ NO allocated_amount - backend calculates
    }
  ]
}
```

---

## New Real-time Calculation API

### Service Layer Implementation

**File:** `src/services/employment.service.js`

```javascript
class EmploymentService extends BaseService {
  constructor() {
    super();
  }

  /**
   * Calculate allocation amount in real-time
   * @param {Object} data - Calculation parameters
   * @param {number} data.employment_id - Employment record ID
   * @param {number} data.fte - FTE percentage (0-100)
   * @returns {Promise<Object>} Calculation result
   */
  async calculateAllocationAmount(data) {
    return this.post('/employments/calculate-allocation', data);
  }

  // ... other methods
}

export const employmentService = new EmploymentService();
```

### Usage in Components

```javascript
// In employment-modal.vue
async calculateAllocationAmount(employmentId, ftePercentage) {
  if (!employmentId || !ftePercentage || ftePercentage <= 0) {
    return {
      allocated_amount: 0,
      formatted_amount: '฿0.00'
    };
  }

  try {
    const response = await employmentService.calculateAllocationAmount({
      employment_id: employmentId,
      fte: ftePercentage
    });

    if (response.success) {
      return response.data;
    }
  } catch (error) {
    console.error('Error calculating allocation amount:', error);
    this.$message.error('Failed to calculate allocation amount');
    return {
      allocated_amount: 0,
      formatted_amount: '฿0.00'
    };
  }
}
```

---

## Component Updates Required

### 1. Employment Modal (employment-modal.vue)

#### Changes Required:

**A. Remove Local Calculation Method**
```javascript
// ❌ REMOVE THIS
getCalculatedSalary(ftePercentage) {
  if (!ftePercentage || !this.formData.position_salary) {
    return this.formatCurrency(0);
  }
  const salary = (this.formData.position_salary * ftePercentage) / 100;
  return this.formatCurrency(salary);
}
```

**B. Add Real-time Calculation Method**
```javascript
// ✅ ADD THIS
data() {
  return {
    // ... existing data
    currentCalculation: {
      allocated_amount: 0,
      formatted_amount: '฿0.00',
      calculating: false
    }
  };
},

methods: {
  async fetchAllocationCalculation() {
    // Only calculate if we have employment_id from successful employment creation
    if (!this.createdEmploymentId || !this.currentAllocation.fte) {
      this.currentCalculation = {
        allocated_amount: 0,
        formatted_amount: '฿0.00',
        calculating: false
      };
      return;
    }

    this.currentCalculation.calculating = true;

    try {
      const result = await this.calculateAllocationAmount(
        this.createdEmploymentId,
        this.currentAllocation.fte
      );

      this.currentCalculation = {
        allocated_amount: result.allocated_amount,
        formatted_amount: result.formatted_amount,
        calculating: false
      };
    } catch (error) {
      this.currentCalculation = {
        allocated_amount: 0,
        formatted_amount: '฿0.00',
        calculating: false
      };
    }
  },

  // Debounced version for better UX
  debouncedFetchCalculation: debounce(function() {
    this.fetchAllocationCalculation();
  }, 500),

  onFteChange() {
    // Trigger debounced calculation
    this.debouncedFetchCalculation();
    this.saveFormState();
  }
}
```

**C. Update Form Submission**
```javascript
// ❌ OLD - Don't send calculated amount
buildPayloadForAPI() {
  return {
    // ... other fields
    allocations: this.fundingAllocations.map(allocation => ({
      allocation_type: allocation.allocation_type,
      position_slot_id: allocation.position_slot_id,
      grant_id: allocation.grant_id,
      department_id: allocation.department_id,
      position_id: allocation.position_id,
      fte: allocation.fte,
      allocated_amount: this.calculateSalaryFromFte(allocation.fte) // ❌ REMOVE
    }))
  };
}

// ✅ NEW - Let backend calculate
buildPayloadForAPI() {
  return {
    // ... other fields
    allocations: this.fundingAllocations.map(allocation => ({
      allocation_type: allocation.allocation_type,
      position_slot_id: allocation.position_slot_id,
      grant_id: allocation.grant_id,
      department_id: allocation.department_id,
      position_id: allocation.position_id,
      fte: allocation.fte
      // ✅ NO allocated_amount - backend calculates
    }))
  };
}
```

**D. Update Template**
```vue
<!-- OLD Template -->
<div class="fte-input-group">
  <label>FTE Percentage:</label>
  <input 
    v-model.number="currentAllocation.fte" 
    @input="onFteChange"
    type="number"
  />
  <span>{{ getCalculatedSalary(currentAllocation.fte) }}</span>
</div>

<!-- ✅ NEW Template -->
<div class="fte-input-group">
  <label>FTE Percentage:</label>
  <input 
    v-model.number="currentAllocation.fte" 
    @input="onFteChange"
    type="number"
    :disabled="!createdEmploymentId"
  />
  
  <!-- Real-time calculated amount display -->
  <div class="calculated-amount-display">
    <a-spin v-if="currentCalculation.calculating" size="small" />
    <span v-else class="amount-value">
      {{ currentCalculation.formatted_amount }}
    </span>
  </div>
</div>
```

### 2. Employment List (employment-list.vue)

#### Changes Required:

**A. Update Data Fetching**
```javascript
// Ensure we're not relying on frontend-calculated allocated_amount
async fetchEmployments(params = {}) {
  try {
    const response = await employmentService.getAllEmployments({
      ...params,
      include_allocations: true // Get allocations from backend
    });

    // Backend provides allocated_amount already calculated
    this.employments = response.data.map(emp => ({
      ...emp,
      // Use backend-calculated amounts
      total_allocated: emp.employee_funding_allocations?.reduce(
        (sum, alloc) => sum + parseFloat(alloc.allocated_amount || 0),
        0
      ) || 0
    }));
  } catch (error) {
    console.error('Error fetching employments:', error);
  }
}
```

### 3. Edit Modal (employment-edit-modal.vue)

#### Changes Required:

**A. Load Existing Calculations from Backend**
```javascript
async loadEmploymentData(employmentId) {
  try {
    const response = await employmentService.getEmploymentById(employmentId);
    
    if (response.success) {
      this.formData = response.data;
      
      // Load funding allocations with backend-calculated amounts
      this.fundingAllocations = response.data.employee_funding_allocations.map(alloc => ({
        id: alloc.id,
        allocation_type: alloc.allocation_type,
        fte: alloc.fte * 100, // Convert decimal to percentage for display
        allocated_amount: alloc.allocated_amount, // ✅ From backend
        formatted_amount: this.formatCurrency(alloc.allocated_amount),
        // ... other fields
      }));
    }
  } catch (error) {
    console.error('Error loading employment:', error);
  }
}
```

**B. Handle Real-time Calculation During Edit**
```javascript
// When editing allocations, use the same real-time API
async onEditFteChange(allocationIndex) {
  const allocation = this.fundingAllocations[allocationIndex];
  
  if (this.formData.id && allocation.fte) {
    const result = await this.calculateAllocationAmount(
      this.formData.id,
      allocation.fte
    );
    
    // Update the allocation with backend-calculated amount
    this.fundingAllocations[allocationIndex].allocated_amount = result.allocated_amount;
    this.fundingAllocations[allocationIndex].formatted_amount = result.formatted_amount;
  }
}
```

---

## Implementation Guide

### Step 1: Update Employment Service

**File:** `src/services/employment.service.js`

```javascript
import BaseService from './base.service';

class EmploymentService extends BaseService {
  constructor() {
    super();
  }

  /**
   * Get all employments with pagination and filtering
   */
  async getAllEmployments(params = {}) {
    return this.get('/employments', { params });
  }

  /**
   * Get employment by ID
   */
  async getEmploymentById(id) {
    return this.get(`/employments/${id}`);
  }

  /**
   * Create new employment
   */
  async createEmployment(data) {
    return this.post('/employments', data);
  }

  /**
   * Update employment
   */
  async updateEmployment(id, data) {
    return this.put(`/employments/${id}`, data);
  }

  /**
   * Delete employment
   */
  async deleteEmployment(id) {
    return this.delete(`/employments/${id}`);
  }

  /**
   * Search employments by staff ID
   */
  async searchEmploymentsByStaffId(staffId) {
    return this.get(`/employments/search/staff-id/${staffId}`);
  }

  /**
   * Get funding allocations for employment
   */
  async getFundingAllocations(employmentId) {
    return this.get(`/employments/${employmentId}/funding-allocations`);
  }

  /**
   * Calculate allocation amount in real-time (NEW)
   * @param {Object} data - Calculation parameters
   * @param {number} data.employment_id - Employment record ID
   * @param {number} data.fte - FTE percentage (0-100)
   * @returns {Promise<Object>} Calculation result
   */
  async calculateAllocationAmount(data) {
    return this.post('/employments/calculate-allocation', data);
  }
}

export const employmentService = new EmploymentService();
export default EmploymentService;
```

### Step 2: Create Composable for Calculations

**File:** `src/composables/useAllocationCalculation.js`

```javascript
import { ref, computed } from 'vue';
import { employmentService } from '@/services/employment.service';
import { message } from 'ant-design-vue';

export function useAllocationCalculation() {
  const calculating = ref(false);
  const calculationResult = ref(null);
  const calculationError = ref(null);

  /**
   * Calculate allocation amount
   * @param {number} employmentId - Employment ID
   * @param {number} ftePercentage - FTE percentage (0-100)
   */
  const calculateAmount = async (employmentId, ftePercentage) => {
    if (!employmentId || !ftePercentage || ftePercentage <= 0) {
      calculationResult.value = {
        allocated_amount: 0,
        formatted_amount: '฿0.00'
      };
      return calculationResult.value;
    }

    calculating.value = true;
    calculationError.value = null;

    try {
      const response = await employmentService.calculateAllocationAmount({
        employment_id: employmentId,
        fte: ftePercentage
      });

      if (response.success) {
        calculationResult.value = response.data;
        return response.data;
      } else {
        throw new Error(response.message || 'Calculation failed');
      }
    } catch (error) {
      console.error('Error calculating allocation amount:', error);
      calculationError.value = error;
      message.error('Failed to calculate allocation amount');
      
      calculationResult.value = {
        allocated_amount: 0,
        formatted_amount: '฿0.00'
      };
      
      return calculationResult.value;
    } finally {
      calculating.value = false;
    }
  };

  const formattedAmount = computed(() => {
    return calculationResult.value?.formatted_amount || '฿0.00';
  });

  const allocatedAmount = computed(() => {
    return calculationResult.value?.allocated_amount || 0;
  });

  return {
    calculating,
    calculationResult,
    calculationError,
    calculateAmount,
    formattedAmount,
    allocatedAmount
  };
}
```

### Step 3: Update Employment Modal Component

**File:** `src/components/modal/employment-modal.vue`

**Key Changes:**

```vue
<script>
import { debounce } from '@/utils/performance';
import { useAllocationCalculation } from '@/composables/useAllocationCalculation';
import { employmentService } from '@/services/employment.service';

export default {
  name: 'EmploymentModal',
  
  setup() {
    const {
      calculating,
      calculationResult,
      calculateAmount,
      formattedAmount,
      allocatedAmount
    } = useAllocationCalculation();

    return {
      calculating,
      calculationResult,
      calculateAmount,
      formattedAmount,
      allocatedAmount
    };
  },

  data() {
    return {
      // ... existing data
      createdEmploymentId: null, // Store employment ID after creation
      currentAllocation: {
        allocation_type: '',
        grant_id: '',
        fte: 100
      }
    };
  },

  created() {
    // Create debounced calculation function
    this.debouncedCalculateAmount = debounce(async () => {
      if (this.createdEmploymentId && this.currentAllocation.fte) {
        await this.calculateAmount(
          this.createdEmploymentId,
          this.currentAllocation.fte
        );
      }
    }, 500);
  },

  methods: {
    onFteChange() {
      // Trigger debounced real-time calculation
      this.debouncedCalculateAmount();
      this.saveFormState();
    },

    async handleSubmit() {
      // Validate form
      if (!this.validateForm()) {
        return;
      }

      this.submitting = true;

      try {
        // Build payload WITHOUT allocated_amount
        const payload = this.buildPayloadForAPI();

        // Submit to backend
        const response = await employmentService.createEmployment(payload);

        if (response.success) {
          // Store employment ID for allocation calculations
          this.createdEmploymentId = response.data.id;

          this.$message.success('Employment created successfully');
          
          // Clear draft
          this.clearFormDraft();

          // Emit event to parent
          this.$emit('employment-added', response.data);

          // Close modal after delay
          setTimeout(() => {
            this.closeModal();
          }, 2200);
        }
      } catch (error) {
        this.handleSubmissionError(error);
      } finally {
        this.submitting = false;
      }
    },

    buildPayloadForAPI() {
      return {
        employee_id: this.formData.employee_id,
        employment_type: this.formData.employment_type,
        pay_method: this.formData.pay_method,
        start_date: this.formatDateForAPI(this.formData.start_date),
        end_date: this.formatDateForAPI(this.formData.end_date), // Optional
        pass_probation_date: this.formatDateForAPI(this.formData.pass_probation_date),
        department_id: this.formData.department_id,
        position_id: this.formData.position_id,
        section_department: this.formData.section_department,
        work_location_id: this.formData.work_location_id,
        pass_probation_salary: this.formData.pass_probation_salary,
        probation_salary: this.formData.probation_salary,
        health_welfare: !!this.formData.health_welfare,
        health_welfare_percentage: this.formData.health_welfare_percentage,
        pvd: !!this.formData.pvd,
        pvd_percentage: this.formData.pvd_percentage,
        saving_fund: !!this.formData.saving_fund,
        saving_fund_percentage: this.formData.saving_fund_percentage,
        allocations: this.fundingAllocations.map(allocation => ({
          allocation_type: allocation.allocation_type,
          position_slot_id: allocation.allocation_type === 'grant' 
            ? allocation.position_slot_id 
            : null,
          grant_id: allocation.allocation_type === 'org_funded' 
            ? allocation.grant_id 
            : null,
          department_id: allocation.allocation_type === 'org_funded' 
            ? allocation.department_id 
            : null,
          position_id: allocation.allocation_type === 'org_funded' 
            ? allocation.position_id 
            : null,
          fte: allocation.fte
          // ✅ NO allocated_amount - backend calculates it
        }))
      };
    }
  }
};
</script>

<template>
  <div class="employment-modal">
    <!-- ... existing form fields ... -->

    <!-- Funding Allocation Section -->
    <div class="funding-allocation-section">
      <h3>Funding Allocations</h3>

      <!-- FTE Input with Real-time Calculation -->
      <div class="fte-input-group">
        <label>FTE Percentage:</label>
        <a-input-number
          v-model:value="currentAllocation.fte"
          :min="0"
          :max="100"
          :step="1"
          :disabled="!createdEmploymentId"
          @change="onFteChange"
        />
        <span class="percentage-symbol">%</span>
      </div>

      <!-- Real-time Calculated Amount Display -->
      <div class="calculated-amount-display">
        <label>Calculated Amount:</label>
        <div class="amount-wrapper">
          <a-spin v-if="calculating" size="small" />
          <span v-else class="amount-value">
            {{ formattedAmount }}
          </span>
        </div>
        <div v-if="calculationResult" class="calculation-info">
          <small class="text-muted">
            Using {{ calculationResult.salary_type }}: ฿{{ calculationResult.base_salary.toLocaleString() }}
          </small>
          <small class="text-muted">
            {{ calculationResult.calculation_formula }}
          </small>
        </div>
      </div>

      <!-- ... rest of allocation form ... -->
    </div>
  </div>
</template>

<style scoped>
.calculated-amount-display {
  margin: 12px 0;
  padding: 12px;
  background: #f8f9fa;
  border: 1px solid #e9ecef;
  border-radius: 6px;
}

.calculated-amount-display label {
  display: block;
  margin-bottom: 8px;
  font-weight: 600;
  color: #3c4257;
}

.amount-wrapper {
  display: flex;
  align-items: center;
  gap: 8px;
}

.amount-value {
  font-size: 1.25em;
  font-weight: 700;
  color: #28a745;
}

.calculation-info {
  margin-top: 8px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.calculation-info small {
  font-size: 0.85em;
  color: #6c757d;
}
</style>
```

---

## AI Prompt for Frontend Implementation

Use this prompt with your AI coding assistant (Claude, ChatGPT, Cursor, etc.) to automatically update your Vue.js frontend:

```
You are tasked with updating a Vue 3 Employment Management System frontend to work with a completely refactored backend API. The backend now handles all funding allocation calculations server-side.

# CONTEXT
The Employment Management System manages employee employment records with funding allocations. The backend has been updated to:
1. Calculate allocation amounts server-side (no longer done in frontend)
2. Provide a real-time calculation API endpoint
3. Use correct salary fields (probation_salary vs pass_probation_salary)
4. Store FTE as decimal in database but accept percentage in API

# CURRENT ISSUES IN FRONTEND
1. Frontend calculates allocated_amount using wrong salary field (position_salary)
2. Calculations are duplicated and inconsistent with backend
3. No real-time feedback when user enters FTE percentage
4. Frontend sends allocated_amount to API (should let backend calculate)

# YOUR TASK
Update the following files according to the migration guide provided:

## Files to Update:
1. `src/services/employment.service.js` - Add calculateAllocationAmount method
2. `src/composables/useAllocationCalculation.js` - Create new composable
3. `src/components/modal/employment-modal.vue` - Remove calculation logic, add real-time API calls
4. `src/components/modal/employment-edit-modal.vue` - Update to use backend calculations
5. `src/views/pages/hrm/employment/employment-list.vue` - Use backend-calculated amounts

## Key Changes Required:

### 1. Employment Service (employment.service.js)
Add this method:
```javascript
async calculateAllocationAmount(data) {
  return this.post('/employments/calculate-allocation', data);
}
```

### 2. Create Composable (useAllocationCalculation.js)
Create a Vue 3 composable that:
- Calls the calculation API
- Manages loading state
- Handles errors gracefully
- Returns formatted amount and calculated amount
- Uses debouncing for performance

### 3. Employment Modal (employment-modal.vue)
REMOVE:
- getCalculatedSalary() method
- calculateSalaryFromFte() method
- Any local calculation logic

ADD:
- Import useAllocationCalculation composable
- Call API when FTE changes (debounced)
- Display real-time calculated amount
- Show loading spinner during calculation
- Display calculation formula and salary type used

UPDATE:
- buildPayloadForAPI() - Remove allocated_amount from allocations array
- Template to show real-time calculated amount

### 4. Edit Modal (employment-edit-modal.vue)
UPDATE:
- Load allocated_amount from backend response
- Use real-time calculation API when editing FTE
- Don't calculate locally

### 5. Employment List (employment-list.vue)
UPDATE:
- Use allocated_amount from backend response
- Don't recalculate amounts

## API Endpoint Details:

### New Endpoint: Calculate Allocation Amount
```
POST /api/employments/calculate-allocation

Request:
{
  "employment_id": 123,
  "fte": 60
}

Response:
{
  "success": true,
  "message": "Allocation amount calculated successfully",
  "data": {
    "employment_id": 123,
    "fte": 60,
    "fte_decimal": 0.60,
    "base_salary": 50000,
    "salary_type": "probation_salary",
    "allocated_amount": 30000,
    "formatted_amount": "฿30,000.00",
    "calculation_formula": "(50000 × 60) / 100 = 30000"
  }
}
```

### Updated Endpoint: Create Employment
```
POST /api/employments

Request allocations format (allocated_amount is OPTIONAL):
{
  "allocations": [
    {
      "allocation_type": "grant",
      "position_slot_id": 45,
      "fte": 60
      // NO allocated_amount - backend calculates
    }
  ]
}
```

## Requirements:
1. Use Vue 3 Composition API where possible
2. Implement proper error handling
3. Add loading states for better UX
4. Use Ant Design Vue components
5. Maintain existing design system
6. Add debouncing (500ms) to API calls
7. Preserve form persistence (auto-save draft)
8. Keep end_date field (it's optional, not removed)

## Success Criteria:
✅ Frontend no longer calculates allocated_amount
✅ Real-time calculation works when user enters FTE
✅ Display shows calculated amount with loading state
✅ Form submission doesn't send allocated_amount
✅ Edit mode loads backend-calculated amounts
✅ All existing functionality preserved
✅ No console errors
✅ Proper error handling for API failures

## Additional Notes:
- The backend uses probation_salary OR pass_probation_salary automatically
- FTE is sent as percentage (60) but stored as decimal (0.60)
- end_date is optional (keep it, don't remove)
- Maintain backward compatibility during migration

Please update the code following this guide and the detailed implementation examples provided in the FRONTEND_EMPLOYMENT_MIGRATION_GUIDE.md document.
```

---

## Testing Checklist

### Unit Tests

- [ ] calculateAllocationAmount service method
- [ ] useAllocationCalculation composable
- [ ] Error handling for API failures
- [ ] Debouncing logic

### Integration Tests

- [ ] Real-time calculation updates display
- [ ] Form submission without allocated_amount
- [ ] Backend returns calculated amount
- [ ] Edit mode loads backend amounts

### E2E Tests

- [ ] Create employment with single allocation
- [ ] Create employment with multiple allocations
- [ ] Total FTE validation (must equal 100%)
- [ ] Real-time calculation feedback
- [ ] Edit existing allocation FTE
- [ ] Form auto-save with new calculation

### Manual Testing

- [ ] Enter FTE → See calculated amount update
- [ ] Submit form → Backend calculates amount
- [ ] Edit employment → Shows backend amounts
- [ ] Network error handling
- [ ] Loading states display correctly
- [ ] Calculation formula shown correctly

---

## Rollback Plan

### If Migration Fails:

1. **Revert Frontend Changes**
   ```bash
   git checkout HEAD~1 src/services/employment.service.js
   git checkout HEAD~1 src/components/modal/employment-modal.vue
   ```

2. **Keep Backend Changes**
   - Backend is backward compatible
   - Accepts allocated_amount as optional
   - Falls back to calculation if not provided

3. **Feature Flag Approach**
   ```javascript
   // In component
   const USE_BACKEND_CALCULATION = false; // Toggle feature

   if (USE_BACKEND_CALCULATION) {
     // New approach
     await this.calculateAmount(employmentId, fte);
   } else {
     // Old approach (fallback)
     this.getCalculatedSalary(fte);
   }
   ```

### Backward Compatibility:

The backend changes are **backward compatible**:
- Still accepts `allocated_amount` in request (optional)
- Calculates if not provided
- Frontend can gradually migrate
- No breaking changes for existing deployments

---

## Support & Resources

### Documentation:
- Backend API: `/docs/COMPLETE_PAYROLL_MANAGEMENT_SYSTEM_DOCUMENTATION.md`
- Swagger API: `/api/documentation` (when server running)
- Frontend Guide: This document

### Key Backend Files:
- Controller: `app/Http/Controllers/Api/EmploymentController.php`
- Service: `app/Services/PayrollService.php`
- Model: `app/Models/EmployeeFundingAllocation.php`
- Migration: `database/migrations/2025_04_07_090015_create_employee_funding_allocations_table.php`

### Key Frontend Files:
- Service: `src/services/employment.service.js`
- Composable: `src/composables/useAllocationCalculation.js`
- Modal: `src/components/modal/employment-modal.vue`
- List: `src/views/pages/hrm/employment/employment-list.vue`

---

## Conclusion

This migration moves calculation logic to the backend where it belongs, ensuring:
- ✅ Consistent calculations across system
- ✅ Single source of truth
- ✅ Better separation of concerns
- ✅ Improved maintainability
- ✅ Real-time user feedback
- ✅ Correct salary field usage

The changes are backward compatible and can be implemented gradually using feature flags if needed.

For questions or issues, refer to the backend documentation or contact the development team.

---

**Document Prepared By:** AI Assistant  
**Review Status:** Ready for Implementation  
**Last Updated:** January 2025

