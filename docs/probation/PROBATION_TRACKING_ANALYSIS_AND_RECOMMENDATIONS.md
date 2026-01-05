# Probation Tracking System - Analysis and Recommendations

## 📋 Executive Summary

This document analyzes the current probation tracking implementation and provides recommendations for improving probation history tracking through database normalization.

**Analysis Date**: 2025-11-10
**Status**: Awaiting User Approval Before Implementation

---

## 🔍 Current Implementation Analysis

### 1. Current Database Schema

#### **employments table** (Primary Table)
```sql
- id
- employee_id
- employment_type
- start_date
- end_date
- pass_probation_date          // When probation ends
- pay_method
- department_id
- position_id
- work_location_id
- pass_probation_salary         // Salary after probation ✅
- probation_salary              // Salary during probation ✅
- health_welfare
- pvd
- saving_fund
- probation_status              // Current status: ongoing, passed, failed, extended
- status                        // Employment status: active/inactive
- created_by
- updated_by
- timestamps
```

#### **employment_histories table** (General Audit Trail)
```sql
- id
- employment_id                 // FK to employments
- employee_id                   // FK to employees
- [All employment fields snapshot]
- change_date                   // When change occurred
- change_reason                 // Reason for change
- changed_by_user               // Who made the change
- changes_made                  // JSON: Details of changes
- previous_values               // JSON: Previous values
- notes                         // Additional notes
- timestamps
```

### 2. Current Probation Workflow

#### **Creating Employment** (EmploymentController.php:657-930)
```php
// Line 666-670: Auto-calculate pass_probation_date
if (!isset($validated['pass_probation_date']) && isset($validated['start_date'])) {
    $validated['pass_probation_date'] = Carbon::parse($validated['start_date'])
        ->addMonths(3)->format('Y-m-d');
}

// Line 719: Set initial probation_status
'probation_status' => 'ongoing'

// Line 728-729: Determine salary type for allocations
$salaryType = $employment->getSalaryTypeForDate($startDate);

// Creates funding allocations with appropriate salary
```

#### **Editing Employment** (Employment Model Observer)
```php
// Employment.php:103-118: Updated event
static::updated(function (self $employment): void {
    $changes = collect($employment->getChanges())->except('updated_at')->all();
    $original = collect($employment->getOriginal())
        ->only(array_keys($changes))
        ->all();

    if (count($changes) > 0) {
        $employment->createHistoryRecord(
            type: 'updated',
            reason: $employment->generateChangeReason($changes),
            changesMade: $changes,
            previousValues: $original
        );
    }
});
```

#### **Probation Transitions** (ProbationTransitionService.php)
- **processTransitions()**: Runs daily to find employments ready for transition
- **transitionEmploymentAllocations()**: Marks old allocations as historical, creates new ones with pass_probation_salary
- **handleProbationExtension()**: Updates probation_status to 'extended', creates history entry
- **handleEarlyTermination()**: Marks probation as 'failed', terminates allocations

### 3. Current Probation History Tracking

✅ **What's Currently Tracked** (via employment_histories):
- General employment changes including probation date changes
- Changes captured in `changes_made` JSON field
- Previous values in `previous_values` JSON field
- Generic `change_reason` like "Probation period update"

❌ **What's NOT Tracked**:
- **Specific probation events** (initial, extension #1, extension #2, etc.)
- **Extension count** (how many times probation was extended)
- **Reason for extension/failure** (specific probation-related reason)
- **Expected vs actual probation end date**
- **Probation evaluation notes** (performance during probation)
- **Decision date** (when decision was made vs when it takes effect)
- **Clear probation timeline** (hard to reconstruct probation history from mixed general changes)

---

## 🚨 Problems Identified

### Problem 1: Loss of Probation History
**Issue**: When `pass_probation_date` is updated multiple times (extensions), we cannot easily see:
- How many times was probation extended?
- What were the previous probation end dates?
- Specific reasons for each extension

**Current Behavior**:
```
Initial: pass_probation_date = 2025-04-01
Extension 1: pass_probation_date = 2025-05-01  // Overwrites, history in JSON
Extension 2: pass_probation_date = 2025-06-01  // Overwrites, history in JSON
```

**Why It's a Problem**:
- Probation history is buried in JSON fields
- No easy way to query "How many employees are on their 2nd extension?"
- Difficult to generate reports on probation trends
- Hard to see probation timeline at a glance

### Problem 2: Mixed History Tracking
**Issue**: `employment_histories` table tracks ALL employment changes, not just probation events.

**Current Behavior**:
```
Entry 1: Department change
Entry 2: Salary adjustment
Entry 3: Probation extension         // Mixed with other changes
Entry 4: Work location change
Entry 5: Probation passed             // Mixed with other changes
```

**Why It's a Problem**:
- Probation events are not easily filterable
- Cannot generate probation-specific reports efficiently
- Business logic for probation is spread across general employment history

### Problem 3: Limited Probation Metadata
**Issue**: Current schema doesn't capture probation-specific context:
- Why was probation extended? (Performance issues? Documentation delay?)
- Who approved the extension?
- What was the evaluation result?
- When was the decision made vs when does it take effect?

---

## 💡 Recommended Solution

### Option 1: Create Dedicated `probation_records` Table (RECOMMENDED ✅)

#### **New Schema Design**

```sql
-- NEW TABLE: probation_records
CREATE TABLE probation_records (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    employment_id BIGINT NOT NULL,
    employee_id BIGINT NOT NULL,

    -- Probation Event Details
    event_type ENUM('initial', 'extension', 'passed', 'failed') NOT NULL,
    event_date DATE NOT NULL,                    // When this event occurred
    decision_date DATE NULL,                      // When decision was made

    -- Probation Dates
    probation_start_date DATE NOT NULL,          // When this probation period started
    probation_end_date DATE NOT NULL,            // When this probation period should end
    previous_end_date DATE NULL,                  // Previous end date (for extensions)

    -- Extension Tracking
    extension_number INT DEFAULT 0,              // 0=initial, 1=first extension, etc.

    -- Decision Details
    decision_reason VARCHAR(500) NULL,           // Why extension/pass/fail
    evaluation_notes TEXT NULL,                  // Performance evaluation notes
    approved_by VARCHAR(255) NULL,               // Who approved this decision

    -- Current Status
    is_active BOOLEAN DEFAULT true,              // Is this the current probation record?

    -- Audit Trail
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,

    -- Foreign Keys
    FOREIGN KEY (employment_id) REFERENCES employments(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,

    -- Indexes
    INDEX idx_employment_id (employment_id),
    INDEX idx_employee_id (employee_id),
    INDEX idx_event_type (event_type),
    INDEX idx_is_active (is_active),
    INDEX idx_probation_end_date (probation_end_date)
);
```

#### **Keep in employments table**:
```sql
-- These fields STAY in employments table ✅
- probation_salary               // Used throughout system (payroll, allocations)
- pass_probation_salary          // Used throughout system (payroll, allocations)
- pass_probation_date            // Current probation end date (for quick lookup)
- probation_status               // Current status (for quick filtering)
```

**Why Keep Salaries in employments?**
1. ✅ **System-wide usage**: Used in PayrollService, funding allocations, calculations
2. ✅ **Performance**: No need to join to probation table for every salary lookup
3. ✅ **Simplicity**: Salaries are fundamental to employment, not just probation
4. ✅ **Backward compatibility**: Minimal changes to existing code
5. ✅ **Flexibility**: Employee may have different salary structure independent of probation

---

## 📊 Comparison: Before vs After

### Before (Current Implementation)

**Creating Employment**:
```
employments table:
  pass_probation_date: 2025-04-01
  probation_salary: 30000
  pass_probation_salary: 35000
  probation_status: 'ongoing'

employment_histories table:
  change_reason: "Initial employment record"
  changes_made: {...all fields...}
```

**Extending Probation** (1st extension):
```
employments table:
  pass_probation_date: 2025-05-01     // Overwrites
  probation_status: 'extended'         // Overwrites

employment_histories table:
  change_reason: "Probation period update"
  changes_made: {"pass_probation_date": "2025-05-01"}
  previous_values: {"pass_probation_date": "2025-04-01"}
```

**Problem**: Hard to query "Show me all employees on 2nd+ extension"

### After (Recommended Implementation)

**Creating Employment**:
```
employments table:
  pass_probation_date: 2025-04-01
  probation_salary: 30000
  pass_probation_salary: 35000
  probation_status: 'ongoing'

probation_records table:
  event_type: 'initial'
  event_date: 2025-01-01
  probation_start_date: 2025-01-01
  probation_end_date: 2025-04-01
  extension_number: 0
  is_active: true
```

**Extending Probation** (1st extension):
```
employments table:
  pass_probation_date: 2025-05-01     // Still updated for quick access
  probation_status: 'extended'

probation_records table (old record):
  is_active: false                     // Mark old record as inactive

probation_records table (new record):
  event_type: 'extension'
  event_date: 2025-04-01
  probation_start_date: 2025-01-01
  probation_end_date: 2025-05-01
  previous_end_date: 2025-04-01
  extension_number: 1
  decision_reason: "Needs more time to demonstrate skills"
  evaluation_notes: "Performance improving but needs one more month"
  approved_by: "HR Manager"
  is_active: true
```

**Benefit**: Easy query:
```sql
SELECT * FROM probation_records
WHERE extension_number >= 2 AND is_active = true
```

---

## 🎯 Implementation Plan

### Phase 1: Database Changes

#### 1.1 Update Existing Migration
**File**: `database/migrations/2025_02_13_025537_create_employments_table.php`

**Changes**: ✅ NO CHANGES NEEDED
- Keep `probation_salary` and `pass_probation_salary` in employments table
- Keep `pass_probation_date` for current probation end date
- Keep `probation_status` for current status

#### 1.2 Create New Migration
**File**: `database/migrations/YYYY_MM_DD_HHMMSS_create_probation_records_table.php`

**Action**: CREATE NEW MIGRATION for `probation_records` table

### Phase 2: Model Changes

#### 2.1 Create ProbationRecord Model
**File**: `app/Models/ProbationRecord.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProbationRecord extends Model
{
    protected $fillable = [
        'employment_id',
        'employee_id',
        'event_type',
        'event_date',
        'decision_date',
        'probation_start_date',
        'probation_end_date',
        'previous_end_date',
        'extension_number',
        'decision_reason',
        'evaluation_notes',
        'approved_by',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'event_date' => 'date',
        'decision_date' => 'date',
        'probation_start_date' => 'date',
        'probation_end_date' => 'date',
        'previous_end_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    public function scopeExtensions($query)
    {
        return $query->where('event_type', 'extension');
    }
}
```

#### 2.2 Update Employment Model
**File**: `app/Models/Employment.php`

**Add Relationship**:
```php
public function probationRecords()
{
    return $this->hasMany(ProbationRecord::class);
}

public function activeProbationRecord()
{
    return $this->hasOne(ProbationRecord::class)->where('is_active', true);
}

public function probationHistory()
{
    return $this->hasMany(ProbationRecord::class)->orderBy('event_date');
}
```

### Phase 3: Service Layer Changes

#### 3.1 Create ProbationRecordService
**File**: `app/Services/ProbationRecordService.php`

```php
<?php

namespace App\Services;

use App\Models\Employment;
use App\Models\ProbationRecord;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ProbationRecordService
{
    /**
     * Create initial probation record when employment is created
     */
    public function createInitialRecord(Employment $employment): ProbationRecord
    {
        return ProbationRecord::create([
            'employment_id' => $employment->id,
            'employee_id' => $employment->employee_id,
            'event_type' => 'initial',
            'event_date' => $employment->start_date,
            'probation_start_date' => $employment->start_date,
            'probation_end_date' => $employment->pass_probation_date,
            'extension_number' => 0,
            'is_active' => true,
            'created_by' => Auth::user()?->name ?? 'system',
            'updated_by' => Auth::user()?->name ?? 'system',
        ]);
    }

    /**
     * Create probation extension record
     */
    public function createExtensionRecord(
        Employment $employment,
        Carbon $newEndDate,
        ?string $reason = null,
        ?string $notes = null
    ): ProbationRecord {
        DB::beginTransaction();
        try {
            // Get current active probation record
            $currentRecord = $employment->activeProbationRecord;

            if (!$currentRecord) {
                throw new \Exception('No active probation record found');
            }

            // Mark current record as inactive
            $currentRecord->update(['is_active' => false]);

            // Create new extension record
            $newRecord = ProbationRecord::create([
                'employment_id' => $employment->id,
                'employee_id' => $employment->employee_id,
                'event_type' => 'extension',
                'event_date' => now(),
                'decision_date' => now(),
                'probation_start_date' => $currentRecord->probation_start_date,
                'probation_end_date' => $newEndDate,
                'previous_end_date' => $currentRecord->probation_end_date,
                'extension_number' => $currentRecord->extension_number + 1,
                'decision_reason' => $reason,
                'evaluation_notes' => $notes,
                'approved_by' => Auth::user()?->name ?? 'system',
                'is_active' => true,
                'created_by' => Auth::user()?->name ?? 'system',
                'updated_by' => Auth::user()?->name ?? 'system',
            ]);

            // Update employment table
            $employment->update([
                'pass_probation_date' => $newEndDate,
                'probation_status' => 'extended',
            ]);

            DB::commit();
            return $newRecord;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Mark probation as passed
     */
    public function markAsPassed(
        Employment $employment,
        ?string $notes = null
    ): ProbationRecord {
        DB::beginTransaction();
        try {
            $currentRecord = $employment->activeProbationRecord;

            if (!$currentRecord) {
                throw new \Exception('No active probation record found');
            }

            // Mark current record as inactive
            $currentRecord->update(['is_active' => false]);

            // Create passed record
            $passedRecord = ProbationRecord::create([
                'employment_id' => $employment->id,
                'employee_id' => $employment->employee_id,
                'event_type' => 'passed',
                'event_date' => now(),
                'decision_date' => now(),
                'probation_start_date' => $currentRecord->probation_start_date,
                'probation_end_date' => $currentRecord->probation_end_date,
                'previous_end_date' => $currentRecord->probation_end_date,
                'extension_number' => $currentRecord->extension_number,
                'evaluation_notes' => $notes,
                'approved_by' => Auth::user()?->name ?? 'system',
                'is_active' => true,
                'created_by' => Auth::user()?->name ?? 'system',
                'updated_by' => Auth::user()?->name ?? 'system',
            ]);

            // Update employment table
            $employment->update([
                'probation_status' => 'passed',
            ]);

            DB::commit();
            return $passedRecord;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Mark probation as failed
     */
    public function markAsFailed(
        Employment $employment,
        ?string $reason = null,
        ?string $notes = null
    ): ProbationRecord {
        DB::beginTransaction();
        try {
            $currentRecord = $employment->activeProbationRecord;

            if (!$currentRecord) {
                throw new \Exception('No active probation record found');
            }

            // Mark current record as inactive
            $currentRecord->update(['is_active' => false]);

            // Create failed record
            $failedRecord = ProbationRecord::create([
                'employment_id' => $employment->id,
                'employee_id' => $employment->employee_id,
                'event_type' => 'failed',
                'event_date' => now(),
                'decision_date' => now(),
                'probation_start_date' => $currentRecord->probation_start_date,
                'probation_end_date' => $currentRecord->probation_end_date,
                'previous_end_date' => $currentRecord->probation_end_date,
                'extension_number' => $currentRecord->extension_number,
                'decision_reason' => $reason,
                'evaluation_notes' => $notes,
                'approved_by' => Auth::user()?->name ?? 'system',
                'is_active' => true,
                'created_by' => Auth::user()?->name ?? 'system',
                'updated_by' => Auth::user()?->name ?? 'system',
            ]);

            // Update employment table
            $employment->update([
                'probation_status' => 'failed',
            ]);

            DB::commit();
            return $failedRecord;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get probation history for employment
     */
    public function getHistory(Employment $employment): array
    {
        $records = $employment->probationHistory;

        return [
            'total_extensions' => $records->where('event_type', 'extension')->count(),
            'current_extension_number' => $employment->activeProbationRecord?->extension_number ?? 0,
            'probation_start_date' => $records->first()?->probation_start_date,
            'initial_end_date' => $records->where('event_type', 'initial')->first()?->probation_end_date,
            'current_end_date' => $employment->pass_probation_date,
            'current_status' => $employment->probation_status,
            'records' => $records,
        ];
    }
}
```

#### 3.2 Update ProbationTransitionService
**File**: `app/Services/ProbationTransitionService.php`

**Add to transitionEmploymentAllocations() method**:
```php
// After line 146 (after updating probation_status to 'passed')
// Create probation record for passed status
app(ProbationRecordService::class)->markAsPassed(
    $employment,
    sprintf(
        'Transition date: %s. %d allocations marked historical, %d new active allocations created.',
        Carbon::today()->format('Y-m-d'),
        $activeAllocations->count(),
        count($newAllocations)
    )
);
```

**Update handleProbationExtension() method**:
```php
// Replace lines 287-302 with:
public function handleProbationExtension(
    Employment $employment,
    string $oldDate,
    string $newDate,
    ?string $reason = null,
    ?string $notes = null
): array {
    try {
        DB::beginTransaction();

        // Create probation extension record
        app(ProbationRecordService::class)->createExtensionRecord(
            $employment,
            Carbon::parse($newDate),
            $reason ?? sprintf('Probation date changed from %s to %s', $oldDate, $newDate),
            $notes ?? 'Active allocations remain unchanged.'
        );

        DB::commit();
        // ... rest of method
    }
}
```

### Phase 4: Controller Changes

#### 4.1 Update EmploymentController
**File**: `app/Http/Controllers/Api/EmploymentController.php`

**In store() method** (after line 725):
```php
$employment = Employment::create($employmentData);

// Create initial probation record
app(\App\Services\ProbationRecordService::class)->createInitialRecord($employment);
```

**Add new endpoint for probation history**:
```php
/**
 * Get probation history for employment
 */
public function getProbationHistory($id)
{
    try {
        $employment = Employment::findOrFail($id);
        $service = app(\App\Services\ProbationRecordService::class);
        $history = $service->getHistory($employment);

        return response()->json([
            'success' => true,
            'message' => 'Probation history retrieved successfully',
            'data' => $history,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to retrieve probation history',
            'error' => $e->getMessage(),
        ], 500);
    }
}
```

### Phase 5: API Routes

**File**: `routes/api/employment.php`

**Add new route**:
```php
Route::get('/employments/{id}/probation-history', [EmploymentController::class, 'getProbationHistory']);
```

### Phase 6: Data Migration Script

Create script to migrate existing employment data to probation_records:

**File**: `app/Console/Commands/MigrateProbationRecords.php`

```php
<?php

namespace App\Console\Commands;

use App\Models\Employment;
use App\Models\ProbationRecord;
use Illuminate\Console\Command;

class MigrateProbationRecords extends Command
{
    protected $signature = 'probation:migrate-records';
    protected $description = 'Migrate existing employment probation data to probation_records table';

    public function handle()
    {
        $this->info('Starting probation records migration...');

        $employments = Employment::whereNotNull('pass_probation_date')->get();

        $created = 0;
        foreach ($employments as $employment) {
            // Check if probation record already exists
            $exists = ProbationRecord::where('employment_id', $employment->id)
                ->where('event_type', 'initial')
                ->exists();

            if (!$exists) {
                // Determine event type based on current probation_status
                $eventType = match ($employment->probation_status) {
                    'passed' => 'passed',
                    'failed' => 'failed',
                    'extended' => 'extension',
                    default => 'initial'
                };

                ProbationRecord::create([
                    'employment_id' => $employment->id,
                    'employee_id' => $employment->employee_id,
                    'event_type' => $eventType,
                    'event_date' => $employment->start_date,
                    'probation_start_date' => $employment->start_date,
                    'probation_end_date' => $employment->pass_probation_date,
                    'extension_number' => $eventType === 'extension' ? 1 : 0,
                    'is_active' => true,
                    'created_by' => 'migration_script',
                    'updated_by' => 'migration_script',
                ]);

                $created++;
            }
        }

        $this->info("Migration completed! Created {$created} probation records.");
    }
}
```

---

## 🎨 Frontend Changes (Phase 7)

### Changes Required in Frontend

#### 1. Employment Edit Modal
**File**: `hrms-frontend-dev/src/components/modal/employment-edit-modal.vue` (or similar)

**Add Probation Section**:
```vue
<template>
  <!-- Existing employment fields -->

  <!-- NEW: Probation Section -->
  <div class="row mt-4" v-if="formData.pass_probation_date">
    <div class="col-12">
      <h6 class="fw-semibold mb-3">Probation Management</h6>
    </div>

    <!-- Current Probation Status -->
    <div class="col-md-4">
      <label class="form-label">Current Probation End Date</label>
      <input type="date" class="form-control" v-model="formData.pass_probation_date" />
    </div>

    <div class="col-md-4">
      <label class="form-label">Probation Status</label>
      <select class="form-select" v-model="formData.probation_status">
        <option value="ongoing">Ongoing</option>
        <option value="extended">Extended</option>
        <option value="passed">Passed</option>
        <option value="failed">Failed</option>
      </select>
    </div>

    <div class="col-md-4">
      <label class="form-label">Extension Number</label>
      <input type="text" class="form-control"
        :value="probationHistory.current_extension_number || 0" readonly />
    </div>

    <!-- Extension Details (shown when extending) -->
    <div class="col-12 mt-3" v-if="isExtendingProbation">
      <div class="card bg-warning-subtle">
        <div class="card-body">
          <h6 class="fw-semibold mb-3">Probation Extension Details</h6>

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Reason for Extension</label>
              <input type="text" class="form-control"
                v-model="probationExtension.reason"
                placeholder="e.g., Needs more time to demonstrate skills" />
            </div>

            <div class="col-md-6">
              <label class="form-label">New Probation End Date</label>
              <input type="date" class="form-control"
                v-model="probationExtension.newEndDate" />
            </div>

            <div class="col-12">
              <label class="form-label">Evaluation Notes</label>
              <textarea class="form-control" rows="3"
                v-model="probationExtension.notes"
                placeholder="Performance evaluation notes"></textarea>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Probation History Button -->
    <div class="col-12 mt-3">
      <button type="button" class="btn btn-outline-primary btn-sm"
        @click="showProbationHistory">
        <i class="ti ti-history me-1"></i>View Probation History
      </button>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      probationHistory: {},
      isExtendingProbation: false,
      probationExtension: {
        reason: '',
        newEndDate: '',
        notes: ''
      }
    };
  },

  watch: {
    'formData.pass_probation_date'(newDate, oldDate) {
      if (oldDate && newDate !== oldDate) {
        this.isExtendingProbation = true;
      }
    }
  },

  methods: {
    async showProbationHistory() {
      try {
        const response = await this.$axios.get(
          `/employments/${this.employment.id}/probation-history`
        );

        if (response.data.success) {
          this.probationHistory = response.data.data;
          // Show modal with probation history
          this.$refs.probationHistoryModal.show();
        }
      } catch (error) {
        this.$toast.error('Failed to load probation history');
      }
    }
  }
};
</script>
```

#### 2. Probation History Modal Component
**File**: `hrms-frontend-dev/src/components/modal/probation-history-modal.vue`

```vue
<template>
  <div class="modal fade" id="probationHistoryModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Probation History</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <!-- Summary Cards -->
          <div class="row mb-4">
            <div class="col-md-3">
              <div class="card">
                <div class="card-body">
                  <small class="text-muted">Total Extensions</small>
                  <h4>{{ history.total_extensions || 0 }}</h4>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card">
                <div class="card-body">
                  <small class="text-muted">Current Extension #</small>
                  <h4>{{ history.current_extension_number || 0 }}</h4>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card">
                <div class="card-body">
                  <small class="text-muted">Initial End Date</small>
                  <h6>{{ formatDate(history.initial_end_date) }}</h6>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card">
                <div class="card-body">
                  <small class="text-muted">Current End Date</small>
                  <h6>{{ formatDate(history.current_end_date) }}</h6>
                </div>
              </div>
            </div>
          </div>

          <!-- Timeline of Probation Events -->
          <h6 class="fw-semibold mb-3">Probation Timeline</h6>
          <div class="timeline">
            <div v-for="(record, index) in history.records" :key="record.id"
              class="timeline-item">
              <div class="timeline-marker" :class="getEventClass(record.event_type)">
                <i :class="getEventIcon(record.event_type)"></i>
              </div>
              <div class="timeline-content">
                <div class="d-flex justify-content-between">
                  <h6 class="mb-1">{{ getEventTitle(record.event_type) }}</h6>
                  <small class="text-muted">{{ formatDate(record.event_date) }}</small>
                </div>
                <p class="mb-1">
                  <strong>Probation Period:</strong>
                  {{ formatDate(record.probation_start_date) }}
                  to
                  {{ formatDate(record.probation_end_date) }}
                </p>
                <p v-if="record.previous_end_date" class="mb-1 text-warning">
                  <i class="ti ti-arrow-right me-1"></i>
                  Extended from {{ formatDate(record.previous_end_date) }}
                </p>
                <p v-if="record.decision_reason" class="mb-1">
                  <strong>Reason:</strong> {{ record.decision_reason }}
                </p>
                <p v-if="record.evaluation_notes" class="mb-1 text-muted">
                  {{ record.evaluation_notes }}
                </p>
                <p v-if="record.approved_by" class="mb-0 small text-muted">
                  Approved by: {{ record.approved_by }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  name: 'ProbationHistoryModal',
  props: {
    history: {
      type: Object,
      default: () => ({})
    }
  },
  methods: {
    getEventClass(eventType) {
      return {
        'initial': 'bg-primary',
        'extension': 'bg-warning',
        'passed': 'bg-success',
        'failed': 'bg-danger'
      }[eventType] || 'bg-secondary';
    },

    getEventIcon(eventType) {
      return {
        'initial': 'ti ti-play',
        'extension': 'ti ti-clock',
        'passed': 'ti ti-check',
        'failed': 'ti ti-x'
      }[eventType] || 'ti ti-circle';
    },

    getEventTitle(eventType) {
      return {
        'initial': 'Probation Started',
        'extension': 'Probation Extended',
        'passed': 'Probation Passed',
        'failed': 'Probation Failed'
      }[eventType] || eventType;
    },

    formatDate(date) {
      if (!date) return 'N/A';
      return new Date(date).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
      });
    }
  }
};
</script>

<style scoped>
.timeline {
  position: relative;
  padding-left: 30px;
}

.timeline-item {
  position: relative;
  padding-bottom: 20px;
  border-left: 2px solid #dee2e6;
}

.timeline-item:last-child {
  border-left: none;
}

.timeline-marker {
  position: absolute;
  left: -31px;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 16px;
}

.timeline-content {
  background: #f8f9fa;
  padding: 15px;
  border-radius: 8px;
  margin-left: 20px;
}
</style>
```

---

## 📈 Benefits of Recommended Solution

### 1. **Clear Probation History** ✅
- Dedicated table for probation events
- Easy to see timeline of probation changes
- Extension count tracked explicitly

### 2. **Better Reporting** ✅
```sql
-- Find all employees on 2nd+ extension
SELECT e.*, pr.extension_number
FROM employments e
JOIN probation_records pr ON e.id = pr.employment_id
WHERE pr.is_active = true
AND pr.extension_number >= 2;

-- Find all probation extensions in last month
SELECT * FROM probation_records
WHERE event_type = 'extension'
AND event_date >= DATE_SUB(NOW(), INTERVAL 1 MONTH);
```

### 3. **Minimal Code Changes** ✅
- Salaries stay in employments table (no massive refactoring)
- Existing functionality continues to work
- New probation tracking is additive, not destructive

### 4. **Rich Probation Context** ✅
- Reason for extension/failure
- Evaluation notes
- Who approved decision
- When decision was made vs when it takes effect

### 5. **Backward Compatible** ✅
- Existing employment queries work unchanged
- Probation records are supplementary
- Migration script handles existing data

---

## 🎯 Decision Required from User

### Questions for User:

1. **Do you approve this recommended approach?**
   - ✅ Create separate `probation_records` table
   - ✅ Keep salaries in `employments` table
   - ✅ Add probation-specific fields (reason, notes, approved_by, etc.)

2. **Should we track probation evaluation scores?**
   - Option: Add `evaluation_score` field (1-5 rating)
   - Option: Add `evaluation_criteria` JSON field

3. **Should we limit maximum probation extensions?**
   - Option: Add validation rule (max 2 extensions)
   - Option: Add warning notification for HR

4. **Frontend probation management preferences?**
   - Inline editing in employment modal?
   - Separate probation management modal?
   - Both options available?

---

## 🚀 Next Steps

Once you approve, I will:

1. ✅ Create the `probation_records` migration file
2. ✅ Create the `ProbationRecord` model
3. ✅ Create the `ProbationRecordService`
4. ✅ Update `Employment` model with relationships
5. ✅ Update `EmploymentController` to create initial probation records
6. ✅ Update `ProbationTransitionService` to use new service
7. ✅ Create data migration command
8. ✅ Add API routes for probation history
9. ✅ Update frontend components (if you want me to)

**Please review and provide feedback or approval to proceed!** 🎯

---

**Document Version**: 1.0
**Author**: HRMS Development Team
**Status**: ⏳ Awaiting User Approval
