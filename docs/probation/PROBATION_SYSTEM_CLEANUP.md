# Probation System Cleanup - MSSQL Compatibility & Architecture ✅

## Overview

This document explains the cleanup and fixes applied to the probation tracking system to ensure:
1. **MSSQL Compatibility** - Removed ENUM types (not supported in MSSQL)
2. **Clean Architecture** - Removed redundant `probation_status` field from employments table
3. **Single Source of Truth** - All probation status tracked in `probation_records` table

---

## Problems Identified

### 1. ❌ ENUM Type Incompatibility with MSSQL

**Issue**: The `probation_records` table used ENUM for `event_type`:
```php
$table->enum('event_type', ['initial', 'extension', 'passed', 'failed']);
```

**Problem**: Microsoft SQL Server does not support ENUM types. This would cause migration failures.

**Solution**: Changed to VARCHAR with comment documentation:
```php
$table->string('event_type', 20)->comment('Type of probation event: initial, extension, passed, failed');
```

---

### 2. ❌ Redundant Probation Status Field

**Issue**: The `employments` table had a `probation_status` column that duplicated information already tracked in `probation_records`:

```php
// In employments table
$table->string('probation_status', 20)->nullable()
    ->comment('Current probation status: ongoing, passed, failed, extended');
```

**Problems**:
- **Data Duplication**: Same information stored in two places
- **Inconsistency Risk**: Could get out of sync
- **Maintenance Overhead**: Must update both tables
- **Unclear Responsibility**: Which table is the source of truth?

**Solution**: Removed `probation_status` from employments table. The active probation record's `event_type` is the single source of truth.

---

## Clean Architecture

### Before Cleanup (❌ Redundant)

```
employments table:
├── pass_probation_date (target end date)
└── probation_status    ← ❌ REDUNDANT!

probation_records table:
├── event_type          ← ✅ Source of truth
├── is_active           ← Identifies current record
└── Full history...
```

### After Cleanup (✅ Clean)

```
employments table:
└── pass_probation_date (target end date only)

probation_records table:
├── event_type          ← ✅ SINGLE source of truth
├── is_active           ← Identifies current record
└── Full history with all events
```

---

## Changes Made

### 1. Fixed `probation_records` Migration

**File**: `database/migrations/2025_11_10_204213_create_probation_records_table.php`

**Change**:
```php
// BEFORE (Line 20):
$table->enum('event_type', ['initial', 'extension', 'passed', 'failed'])
    ->comment('Type of probation event');

// AFTER (Line 20):
$table->string('event_type', 20)
    ->comment('Type of probation event: initial, extension, passed, failed');
```

**Why**: MSSQL doesn't support ENUM. VARCHAR is compatible with all databases.

---

### 2. Updated `employments` Table Migration

**File**: `database/migrations/2025_02_13_025537_create_employments_table.php`

**Change**:
```php
// BEFORE (Lines 33-35):
$table->boolean('status')->default(true)->comment('Employment status: true=Active, false=Inactive');
$table->string('probation_status', 20)->nullable()
    ->comment('Current probation status: ongoing, passed, failed, extended');
$table->timestamps();

// AFTER (Lines 32-34):
$table->boolean('status')->default(true)->comment('Employment status: true=Active, false=Inactive');
// NOTE: Probation status is now tracked in probation_records table via event_type field
$table->timestamps();
```

**Why**: Removed redundant field. Probation status is tracked in probation_records.

---

### 3. Updated Employment Model

**File**: `app/Models/Employment.php`

**Change A - Fillable Array** (Line 69):
```php
// BEFORE:
protected $fillable = [
    // ...
    'status',
    'probation_status',  // ❌ REMOVED
    'created_by',
];

// AFTER:
protected $fillable = [
    // ...
    'status',
    // NOTE: probation_status removed - use probation_records table instead
    'created_by',
];
```

**Change B - isReadyForTransition() Method** (Lines 382-395):
```php
// BEFORE:
public function isReadyForTransition(): bool
{
    if (! $this->pass_probation_date) {
        return false;
    }

    return $this->pass_probation_date->isToday() &&
           ! $this->end_date &&
           $this->probation_status !== 'passed';  // ❌ Used probation_status
}

// AFTER:
public function isReadyForTransition(): bool
{
    if (! $this->pass_probation_date) {
        return false;
    }

    // Check if probation already passed by checking active probation record
    $activeProbation = $this->activeProbationRecord;
    $alreadyPassed = $activeProbation &&
                     $activeProbation->event_type === \App\Models\ProbationRecord::EVENT_PASSED;

    return $this->pass_probation_date->isToday() &&
           ! $this->end_date &&
           ! $alreadyPassed;  // ✅ Uses probation_records
}
```

**Why**: Now checks probation status via relationship to probation_records table.

---

### 4. Updated EmploymentController

**File**: `app/Http/Controllers/Api/EmploymentController.php`

**Change A - Index Method SELECT** (Line 230):
```php
// BEFORE:
'status',
'probation_status',  // ❌ REMOVED
'created_at',

// AFTER:
'status',
// NOTE: probation_status removed - use probation_records table
'created_at',
```

**Change B - Store Method** (Lines 729-743):
```php
// BEFORE:
$employmentData = array_merge(
    collect($validated)->except('allocations')->toArray(),
    [
        'probation_status' => 'ongoing',  // ❌ REMOVED
        'created_by' => $currentUser,
        'updated_by' => $currentUser,
    ]
);

// AFTER:
$employmentData = array_merge(
    collect($validated)->except('allocations')->toArray(),
    [
        'created_by' => $currentUser,
        'updated_by' => $currentUser,
    ]
);

$employment = Employment::create($employmentData);

// Create initial probation record
// NOTE: Probation status is now tracked in probation_records table, not employments.probation_status
if ($employment->pass_probation_date) {
    app(\App\Services\ProbationRecordService::class)->createInitialRecord($employment);
}
```

**Why**: No longer sets probation_status. Status is tracked via probation_records.

---

### 5. Created Drop Column Migration

**File**: `database/migrations/2025_11_11_103342_drop_probation_status_column_from_employments_table.php`

**Purpose**: Drop the `probation_status` column from existing databases

```php
public function up(): void
{
    Schema::table('employments', function (Blueprint $table) {
        // Check if column exists before dropping (in case migration is run multiple times)
        if (Schema::hasColumn('employments', 'probation_status')) {
            $table->dropColumn('probation_status');
        }
    });
}

public function down(): void
{
    Schema::table('employments', function (Blueprint $table) {
        if (! Schema::hasColumn('employments', 'probation_status')) {
            $table->string('probation_status', 20)
                ->nullable()
                ->after('status')
                ->comment('Current probation status: ongoing, passed, failed, extended (DEPRECATED - use probation_records table)');
        }
    });
}
```

**Why**: Safely removes the column with rollback support.

---

### 6. Updated Test Data Script

**File**: `create_test_data.php`

**Changes**:
```php
// BEFORE (All 3 employments):
'probation_status' => 'ongoing',  // ❌ REMOVED
'status' => true,

// AFTER (All 3 employments):
'status' => true,
// NOTE: probation_status removed - tracked in probation_records table

// Also updated echo statements:
// BEFORE:
echo "     - Probation Status: {$employment->probation_status}\n";

// AFTER:
echo "     - Probation Status: Tracked in probation_records table\n";
```

**Why**: Test data script now compatible with cleaned schema.

---

## Database Schema (After Cleanup)

### employments Table

```sql
CREATE TABLE employments (
    id BIGINT PRIMARY KEY,
    employee_id BIGINT FOREIGN KEY,
    employment_type VARCHAR(255),
    start_date DATE,
    end_date DATE NULL,
    pass_probation_date DATE NULL,        -- ✅ Only date field
    probation_salary DECIMAL(10,2) NULL,
    pass_probation_salary DECIMAL(10,2),
    status BIT DEFAULT 1,                 -- ✅ Only employment status
    -- NOTE: probation_status REMOVED
    created_at DATETIME,
    updated_at DATETIME
);
```

**Fields Related to Probation**:
- `pass_probation_date` - Target end date for probation period
- `probation_salary` - Salary during probation
- `pass_probation_salary` - Salary after passing probation

**No Status Field**: Status is tracked in `probation_records` table.

---

### probation_records Table

```sql
CREATE TABLE probation_records (
    id BIGINT PRIMARY KEY,
    employment_id BIGINT FOREIGN KEY,
    employee_id BIGINT FOREIGN KEY,

    -- Event Details
    event_type VARCHAR(20),               -- ✅ initial, extension, passed, failed
    event_date DATE,
    decision_date DATE NULL,

    -- Probation Dates
    probation_start_date DATE,
    probation_end_date DATE,
    previous_end_date DATE NULL,

    -- Extension Tracking
    extension_number INT DEFAULT 0,

    -- Decision Details
    decision_reason VARCHAR(500) NULL,
    evaluation_notes TEXT NULL,
    approved_by VARCHAR(255) NULL,

    -- Current Status
    is_active BIT DEFAULT 1,              -- ✅ Identifies current record

    created_at DATETIME,
    updated_at DATETIME
);
```

**How to Get Current Probation Status**:
```php
// Get active probation record
$activeProbation = $employment->activeProbationRecord;

// Check status
if (!$activeProbation) {
    // No probation
} elseif ($activeProbation->event_type === 'initial') {
    // Probation ongoing
} elseif ($activeProbation->event_type === 'extension') {
    // Probation extended (still ongoing)
} elseif ($activeProbation->event_type === 'passed') {
    // Probation passed
} elseif ($activeProbation->event_type === 'failed') {
    // Probation failed
}
```

---

## How to Get Probation Status

### Old Way (❌ Removed)
```php
// Direct field access
$status = $employment->probation_status;  // ❌ Field doesn't exist anymore
```

### New Way (✅ Correct)

**Option 1: Via Active Probation Record**
```php
$activeProbation = $employment->activeProbationRecord;

if ($activeProbation) {
    $status = $activeProbation->event_type;  // 'initial', 'extension', 'passed', 'failed'
    $isOngoing = in_array($status, ['initial', 'extension']);
    $hasPassed = $status === 'passed';
    $hasFailed = $status === 'failed';
}
```

**Option 2: Via Helper Methods**
```php
// Check if ready for transition
$isReady = $employment->isReadyForTransition();

// Get probation history
$service = app(\App\Services\ProbationRecordService::class);
$history = $service->getHistory($employment);
```

**Option 3: Via Computed Property (Add to Employment Model)**
```php
// In Employment model, add:
public function getProbationStatusAttribute(): ?string
{
    $activeProbation = $this->activeProbationRecord;

    if (!$activeProbation) {
        return null;
    }

    return match($activeProbation->event_type) {
        'initial', 'extension' => 'ongoing',
        'passed' => 'passed',
        'failed' => 'failed',
        default => null
    };
}

// Then use:
$status = $employment->probation_status;  // Returns 'ongoing', 'passed', 'failed', or null
```

---

## Migration Path

### For Fresh Installations

Simply run `php artisan migrate:fresh` - the employments table will be created without the `probation_status` column.

### For Existing Databases

Run migrations in order:

```bash
# Step 1: Run all migrations (includes the drop column migration)
php artisan migrate

# Step 2: Verify column was dropped
php artisan tinker
>>> Schema::hasColumn('employments', 'probation_status')
=> false  // ✅ Column successfully dropped
```

---

## Benefits of This Cleanup

### ✅ 1. Database Compatibility
- **Works with MSSQL**: No ENUM types
- **Works with MySQL**: VARCHAR compatible
- **Works with PostgreSQL**: VARCHAR compatible
- **Works with SQLite**: VARCHAR compatible

### ✅ 2. Clean Architecture
- **Single Source of Truth**: Only `probation_records` tracks status
- **No Data Duplication**: Status stored once
- **No Sync Issues**: Can't get out of sync
- **Clear Responsibility**: `probation_records` owns status

### ✅ 3. Better History Tracking
- Full event history preserved
- Extension tracking with numbers
- Decision reasons and notes
- Clear audit trail

### ✅ 4. More Flexible
- Support multiple extensions
- Track approval chain
- Rich evaluation notes
- Effective dating for decisions

### ✅ 5. Maintainable
- Fewer fields to update
- Clearer code
- Less confusion
- Easier to test

---

## Testing

### Test 1: Create Employment with Probation

```php
$employment = Employment::create([
    'employee_id' => 1,
    'start_date' => now(),
    'pass_probation_date' => now()->addMonths(3),
    'probation_salary' => 20000,
    'pass_probation_salary' => 25000,
    'status' => true,
    // NOTE: No probation_status field!
]);

// Probation record is auto-created via observer/service
$probationRecord = $employment->activeProbationRecord;

echo $probationRecord->event_type;  // 'initial'
echo $probationRecord->is_active;   // true
```

### Test 2: Check Probation Status

```php
$employment = Employment::find(1);

// Get active probation record
$activeProbation = $employment->activeProbationRecord;

if ($activeProbation) {
    echo "Event Type: {$activeProbation->event_type}\n";
    echo "End Date: {$activeProbation->probation_end_date}\n";
    echo "Is Active: " . ($activeProbation->is_active ? 'Yes' : 'No') . "\n";
}
```

### Test 3: Extend Probation

```php
$employment = Employment::find(1);
$service = app(\App\Services\ProbationRecordService::class);

// Extend probation by 1 month
$newEndDate = $employment->pass_probation_date->addMonth();
$extensionRecord = $service->createExtensionRecord(
    $employment,
    $newEndDate,
    'Performance needs improvement',
    'Employee shows potential but needs more time'
);

echo $extensionRecord->event_type;        // 'extension'
echo $extensionRecord->extension_number;  // 1
echo $extensionRecord->is_active;         // true

// Old initial record is now inactive
$initialRecord = $employment->probationRecords()->where('event_type', 'initial')->first();
echo $initialRecord->is_active;  // false
```

### Test 4: Pass Probation

```php
$employment = Employment::find(1);
$service = app(\App\Services\ProbationRecordService::class);

$passedRecord = $service->markAsPassed(
    $employment,
    'Excellent performance throughout probation'
);

echo $passedRecord->event_type;  // 'passed'
echo $passedRecord->is_active;   // true
```

---

## Files Modified

### Created:
1. ✅ `database/migrations/2025_11_11_103342_drop_probation_status_column_from_employments_table.php`
2. ✅ `PROBATION_SYSTEM_CLEANUP.md` (this document)

### Modified:
1. ✅ `database/migrations/2025_11_10_204213_create_probation_records_table.php`
   - Changed ENUM to VARCHAR for event_type
2. ✅ `database/migrations/2025_02_13_025537_create_employments_table.php`
   - Removed probation_status column
3. ✅ `app/Models/Employment.php`
   - Removed probation_status from fillable
   - Updated isReadyForTransition() to use probation_records
4. ✅ `app/Http/Controllers/Api/EmploymentController.php`
   - Removed probation_status from SELECT
   - Removed probation_status from store method
5. ✅ `create_test_data.php`
   - Removed probation_status from employment creation
   - Updated echo statements

---

## Summary

| Aspect | Before | After |
|--------|--------|-------|
| **event_type Field** | ENUM (incompatible) | VARCHAR (compatible) ✅ |
| **Probation Status Storage** | Two places (redundant) | One place (probation_records) ✅ |
| **Source of Truth** | Unclear | Clear (probation_records) ✅ |
| **Data Consistency** | Risk of sync issues | Always consistent ✅ |
| **Architecture** | Messy | Clean ✅ |
| **MSSQL Compatible** | ❌ No | ✅ Yes |

---

## Conclusion

✅ **MSSQL Compatible**: Removed ENUM, using VARCHAR
✅ **Clean Architecture**: Single source of truth in probation_records
✅ **No Redundancy**: Removed probation_status from employments
✅ **Maintainable**: Clear responsibility and relationships
✅ **Backward Compatible**: Old code can be easily migrated

**Status**: Complete and Ready for Production

**Date**: 2025-11-11
**Version**: 2.0
