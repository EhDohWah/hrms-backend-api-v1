# Complete Removal Plan: position_slots & org_funded_allocations Tables

**Date**: 2025-11-20
**Objective**: Remove unnecessary intermediary tables and simplify funding allocation architecture
**Impact**: Database schema, Models, Controllers, Services, Routes, APIs

---

## Table of Contents
1. [Executive Summary](#executive-summary)
2. [Affected Files Complete List](#affected-files-complete-list)
3. [Database Changes](#database-changes)
4. [Step-by-Step Implementation](#step-by-step-implementation)
5. [Code Changes by File](#code-changes-by-file)
6. [Testing Requirements](#testing-requirements)
7. [Rollback Plan](#rollback-plan)

---

## Executive Summary

### Tables to Remove
1. **`position_slots`** - Intermediary between grant_items and employee_funding_allocations
2. **`org_funded_allocations`** - Intermediary between grants and employee_funding_allocations

### Reason for Removal
- **position_slots**: No unique attributes, just counts. Can be replaced with direct link to grant_items
- **org_funded_allocations**: Duplicates department_id & position_id from employments table

### New Architecture
```
Before:
grants → grant_items → position_slots → employee_funding_allocations
grants → org_funded_allocations → employee_funding_allocations

After:
grants → grant_items → employee_funding_allocations (direct)
grants → employee_funding_allocations (direct)
```

---

## Affected Files Complete List

### 📁 Models (DELETE + MODIFY)

| File | Action | Description |
|------|--------|-------------|
| `app/Models/PositionSlot.php` | ❌ DELETE | Remove entire model |
| `app/Models/OrgFundedAllocation.php` | ❌ DELETE | Remove entire model |
| `app/Models/EmployeeFundingAllocation.php` | ✏️ MODIFY | Update relationships, add grant_item_id & grant_id |
| `app/Models/GrantItem.php` | ✏️ MODIFY | Remove positionSlots relationship |
| `app/Models/Grant.php` | ✏️ MODIFY | Remove orgFundedAllocations relationship |

### 📁 Controllers (DELETE + MODIFY)

| File | Action | Description |
|------|--------|-------------|
| `app/Http/Controllers/Api/PositionSlotController.php` | ❌ DELETE | Remove entire controller |
| `app/Http/Controllers/Api/OrgFundedAllocationController.php` | ❌ DELETE | Remove entire controller |
| `app/Http/Controllers/Api/EmploymentController.php` | ✏️ MODIFY | Update allocation creation logic |

### 📁 Requests (DELETE)

| File | Action | Description |
|------|--------|-------------|
| `app/Http/Requests/StoreOrgFundedAllocationRequest.php` | ❌ DELETE | No longer needed |
| `app/Http/Requests/UpdateOrgFundedAllocationRequest.php` | ❌ DELETE | No longer needed |
| `app/Http/Requests/StoreEmploymentRequest.php` | ✏️ MODIFY | Update validation rules |

### 📁 Resources (DELETE + MODIFY)

| File | Action | Description |
|------|--------|-------------|
| `app/Http/Resources/PositionSlotResource.php` | ❌ DELETE | Remove entire resource |
| `app/Http/Resources/EmployeeFundingAllocationResource.php` | ✏️ MODIFY | Update to use direct relationships |

### 📁 Services (MODIFY)

| File | Action | Description |
|------|--------|-------------|
| `app/Services/FundingAllocationService.php` | ✏️ MODIFY | Simplify allocation creation, remove slot/org_funded logic |
| `app/Services/PayrollService.php` | ✏️ MODIFY | Update queries if using old relationships |
| `app/Services/ProbationTransitionService.php` | ✏️ MODIFY | Update if using position_slot references |

### 📁 Migrations (DELETE + CREATE)

| File | Action | Description |
|------|--------|-------------|
| `database/migrations/2025_04_06_113035_create_position_slots_table.php` | ❌ DELETE | Remove migration file |
| `database/migrations/2025_04_06_224915_create_org_funded_allocations_table.php` | ❌ DELETE | Remove migration file |
| `database/migrations/2025_04_07_090015_create_employee_funding_allocations_table.php` | ✏️ MODIFY | Add grant_item_id, grant_id; remove position_slot_id, org_funded_id |
| `database/migrations/NEW_simplify_funding_allocations.php` | ➕ CREATE | Migration to update schema (if not fresh) |

### 📁 Routes (MODIFY)

| File | Action | Description |
|------|--------|-------------|
| `routes/api/grants.php` | ✏️ MODIFY | Remove position-slots and org-funded-allocations routes |

### 📁 Seeders (MODIFY/DELETE)

| File | Action | Description |
|------|--------|-------------|
| `database/seeders/ProbationAllocationSeeder.php` | ✏️ MODIFY | Update to use new structure |
| Any seeders creating position_slots | ❌ DELETE or MODIFY | Remove or update |

### 📁 Tests (MODIFY)

| File | Action | Description |
|------|--------|-------------|
| `tests/Feature/EmploymentProbationAllocationTest.php` | ✏️ MODIFY | Update test assertions |
| Any tests referencing position_slots or org_funded | ✏️ MODIFY | Update to new structure |

### 📁 Utility Scripts (DELETE/MODIFY)

| File | Action | Description |
|------|--------|-------------|
| `create_test_data.php` | ✏️ MODIFY | Update if using old structure |
| `fix_org_funded_allocation.php` | ❌ DELETE | No longer needed |

### 📁 Documentation (UPDATE)

| File | Action | Description |
|------|--------|-------------|
| All docs in `docs/` folder | ✏️ UPDATE | Update to reflect new architecture |

**Total Files Affected**: 35+ files

---

## Database Changes

### Current Schema (employee_funding_allocations)

```sql
CREATE TABLE employee_funding_allocations (
    id BIGINT UNSIGNED PRIMARY KEY,
    employee_id BIGINT UNSIGNED,
    employment_id BIGINT UNSIGNED,
    org_funded_id BIGINT UNSIGNED NULL,      -- TO BE REMOVED
    position_slot_id BIGINT UNSIGNED NULL,   -- TO BE REMOVED
    fte DECIMAL(4,2),
    allocation_type VARCHAR(20),
    allocated_amount DECIMAL(15,2),
    salary_type VARCHAR(50),
    status VARCHAR(20) DEFAULT 'active',
    start_date DATE,
    end_date DATE,
    created_by VARCHAR(100),
    updated_by VARCHAR(100),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (org_funded_id) REFERENCES org_funded_allocations(id),
    FOREIGN KEY (position_slot_id) REFERENCES position_slots(id)
);
```

### New Schema (employee_funding_allocations)

```sql
CREATE TABLE employee_funding_allocations (
    id BIGINT UNSIGNED PRIMARY KEY,
    employee_id BIGINT UNSIGNED,
    employment_id BIGINT UNSIGNED,
    grant_item_id BIGINT UNSIGNED NULL,      -- NEW: Direct link for grant allocations
    grant_id BIGINT UNSIGNED NULL,           -- NEW: Direct link for org_funded allocations
    fte DECIMAL(4,2),
    allocation_type VARCHAR(20),
    allocated_amount DECIMAL(15,2),
    salary_type VARCHAR(50),
    status VARCHAR(20) DEFAULT 'active',
    start_date DATE,
    end_date DATE,
    created_by VARCHAR(100),
    updated_by VARCHAR(100),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,

    FOREIGN KEY (grant_item_id) REFERENCES grant_items(id) ON DELETE SET NULL,
    FOREIGN KEY (grant_id) REFERENCES grants(id) ON DELETE SET NULL,

    INDEX idx_grant_item_status (grant_item_id, status),
    INDEX idx_grant_status (grant_id, status)
);
```

### Tables to DROP

```sql
DROP TABLE IF EXISTS position_slots;
DROP TABLE IF EXISTS org_funded_allocations;
```

---

## Step-by-Step Implementation

### Phase 1: Backup (CRITICAL)

```bash
# 1. Backup database
php artisan db:backup  # Or your backup command

# 2. Export current data
mysqldump -u root -p hrms_db > hrms_backup_$(date +%Y%m%d).sql

# 3. Commit current code
git add .
git commit -m "Backup before intermediary table removal"
git tag backup-before-simplification
```

### Phase 2: Modify employee_funding_allocations Table

**Since you're using `php artisan migrate:fresh`, directly edit the migration file:**

```php
// database/migrations/2025_04_07_090015_create_employee_funding_allocations_table.php

public function up(): void
{
    Schema::create('employee_funding_allocations', function (Blueprint $table) {
        $table->id();
        $table->foreignId('employee_id')->constrained('employees');
        $table->foreignId('employment_id')->nullable()->constrained('employments');

        // NEW: Direct references (replacing intermediaries)
        $table->foreignId('grant_item_id')
            ->nullable()
            ->constrained('grant_items')
            ->nullOnDelete()
            ->comment('Direct link to grant_items for grant allocations');

        $table->foreignId('grant_id')
            ->nullable()
            ->constrained('grants')
            ->nullOnDelete()
            ->comment('Direct link to grants for org_funded allocations');

        // REMOVED:
        // $table->foreignId('org_funded_id')->nullable()->constrained('org_funded_allocations');
        // $table->foreignId('position_slot_id')->nullable()->constrained('position_slots');

        $table->decimal('fte', 4, 2)->comment('Full-Time Equivalent percentage (0.00 to 1.00)');
        $table->string('allocation_type', 20); // 'grant' or 'org_funded'
        $table->decimal('allocated_amount', 15, 2)->nullable();
        $table->string('salary_type', 50)->nullable();
        $table->string('status', 20)->default('active');
        $table->date('start_date')->nullable();
        $table->date('end_date')->nullable();
        $table->string('created_by', 100)->nullable();
        $table->string('updated_by', 100)->nullable();
        $table->timestamps();

        // Indexes
        $table->index(['employee_id', 'employment_id']);
        $table->index(['employment_id', 'status'], 'idx_employment_status');
        $table->index(['status', 'end_date'], 'idx_status_end_date');
        $table->index(['grant_item_id', 'status'], 'idx_grant_item_status');
        $table->index(['grant_id', 'status'], 'idx_grant_status');
    });
}
```

### Phase 3: Delete Migration Files

```bash
rm database/migrations/2025_04_06_113035_create_position_slots_table.php
rm database/migrations/2025_04_06_224915_create_org_funded_allocations_table.php
```

### Phase 4: Delete Models

```bash
rm app/Models/PositionSlot.php
rm app/Models/OrgFundedAllocation.php
```

### Phase 5: Delete Controllers

```bash
rm app/Http/Controllers/Api/PositionSlotController.php
rm app/Http/Controllers/Api/OrgFundedAllocationController.php
```

### Phase 6: Delete Requests

```bash
rm app/Http/Requests/StoreOrgFundedAllocationRequest.php
rm app/Http/Requests/UpdateOrgFundedAllocationRequest.php
```

### Phase 7: Delete Resources

```bash
rm app/Http/Resources/PositionSlotResource.php
```

### Phase 8: Update Routes

```php
// routes/api/grants.php

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('grants')->group(function () {
        Route::get('/', [GrantController::class, 'index'])->name('grants.index')->middleware('permission:grant.read');
        Route::get('/items', [GrantController::class, 'getGrantItems'])->name('grants.items.index')->middleware('permission:grant.read');
        // ... rest of grant routes ...
    });

    // REMOVE THESE SECTIONS:
    // Route::prefix('position-slots')->group(...);  ← DELETE
    // Route::prefix('org-funded-allocations')->group(...);  ← DELETE
});
```

---

## Code Changes by File

### 1. EmployeeFundingAllocation Model

**File**: `app/Models/EmployeeFundingAllocation.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeFundingAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'employment_id',
        'grant_item_id',  // NEW
        'grant_id',       // NEW
        // REMOVED: 'org_funded_id', 'position_slot_id'
        'fte',
        'allocation_type',
        'allocated_amount',
        'salary_type',
        'status',
        'start_date',
        'end_date',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'fte' => 'decimal:4',
        'allocated_amount' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    // Relationships
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function employment()
    {
        return $this->belongsTo(Employment::class);
    }

    // NEW: Direct relationship to grant_items
    public function grantItem()
    {
        return $this->belongsTo(GrantItem::class);
    }

    // NEW: Direct relationship to grants (for org_funded)
    public function grant()
    {
        return $this->belongsTo(Grant::class);
    }

    // REMOVED:
    // public function positionSlot() { ... }
    // public function orgFunded() { ... }

    // Helper methods

    /**
     * Get grant information regardless of allocation type
     */
    public function getGrantInfo()
    {
        if ($this->allocation_type === 'grant' && $this->grantItem) {
            return $this->grantItem->grant;
        }
        return $this->grant;
    }

    /**
     * Get grant position name
     */
    public function getGrantPositionName(): string
    {
        if ($this->allocation_type === 'grant' && $this->grantItem) {
            return $this->grantItem->grant_position ?? 'N/A';
        }
        // For org_funded, use employment's organizational position
        return $this->employment?->position?->title ?? 'N/A';
    }

    /**
     * Get department (from employment)
     */
    public function getDepartmentName(): string
    {
        return $this->employment?->department?->name ?? 'N/A';
    }

    // Query scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeHistorical($query)
    {
        return $query->where('status', 'historical');
    }

    public function scopeTerminated($query)
    {
        return $query->where('status', 'terminated');
    }

    public function scopeForDate($query, $date)
    {
        return $query->where('start_date', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $date);
            });
    }

    public function scopeGrant($query)
    {
        return $query->where('allocation_type', 'grant');
    }

    public function scopeOrgFunded($query)
    {
        return $query->where('allocation_type', 'org_funded');
    }

    // UPDATED: Eager loading scope
    public function scopeWithFullDetails($query)
    {
        return $query->with([
            'employee:id,staff_id,first_name_en,last_name_en,subsidiary',
            'employment:id,employee_id,start_date,end_date,pass_probation_salary,department_id,position_id',
            'employment.department:id,name',
            'employment.position:id,title',
            'grantItem:id,grant_id,grant_position,grant_salary,budgetline_code',
            'grantItem.grant:id,name,code',
            'grant:id,name,code',
        ]);
    }

    public function scopeForPayrollCalculation($query)
    {
        return $query->active()
            ->select([
                'id', 'employee_id', 'employment_id', 'allocation_type',
                'fte', 'allocated_amount', 'grant_item_id', 'grant_id',
            ])
            ->with([
                'grantItem:id,grant_id,grant_position',
                'grantItem.grant:id,name,code',
                'grant:id,name,code',
            ]);
    }

    // Status helpers
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isHistorical(): bool
    {
        return $this->status === 'historical';
    }

    public function isTerminated(): bool
    {
        return $this->status === 'terminated';
    }

    // Accessor attributes
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'active' => 'Currently Active',
            'historical' => 'Historical (Probation Period)',
            'terminated' => 'Terminated',
            default => 'Unknown'
        };
    }

    public function getSalaryTypeLabelAttribute(): string
    {
        return match ($this->salary_type) {
            'probation_salary' => 'Probation Salary',
            'pass_probation_salary' => 'Pass Probation Salary',
            default => 'Unknown'
        };
    }
}
```

---

### 2. FundingAllocationService

**File**: `app/Services/FundingAllocationService.php`

**Key Changes:**

```php
<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
use App\Models\Employment;
use App\Models\Grant;
use App\Models\GrantItem;  // ADD
// REMOVE: use App\Models\OrgFundedAllocation;
// REMOVE: use App\Models\PositionSlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FundingAllocationService
{
    public function __construct(
        private readonly EmployeeFundingAllocationService $allocationSalaryService
    ) {}

    /**
     * Create grant-based allocation (SIMPLIFIED)
     */
    private function createGrantAllocation(Employee $employee, Employment $employment, array $data, int $index, string $currentUser): array
    {
        // Validate grant_item exists
        $grantItem = GrantItem::find($data['grant_item_id']);
        if (!$grantItem) {
            return ['error' => "Allocation #{$index}: Grant item not found"];
        }

        // Check capacity
        $capacityCheck = $this->validateGrantCapacity($grantItem, $employment->id);
        if (!$capacityCheck['valid']) {
            return ['error' => "Allocation #{$index}: ".$capacityCheck['message']];
        }

        $fteDecimal = $data['fte'] / 100;
        $effectiveDate = $employment->start_date instanceof Carbon
            ? $employment->start_date
            : Carbon::parse($employment->start_date);
        $salaryContext = $this->allocationSalaryService->deriveSalaryContext($employment, $fteDecimal, $effectiveDate);

        $allocation = EmployeeFundingAllocation::create([
            'employee_id' => $employee->id,
            'employment_id' => $employment->id,
            'grant_item_id' => $grantItem->id,  // DIRECT LINK
            'grant_id' => null,
            'fte' => $fteDecimal,
            'allocation_type' => 'grant',
            'allocated_amount' => $salaryContext['allocated_amount'],
            'salary_type' => $salaryContext['salary_type'],
            'start_date' => $employment->start_date,
            'end_date' => $employment->end_date ?? null,
            'created_by' => $currentUser,
            'updated_by' => $currentUser,
        ]);

        return ['allocation' => $allocation];
    }

    /**
     * Create organization-funded allocation (SIMPLIFIED)
     */
    private function createOrgFundedAllocation(Employee $employee, Employment $employment, array $data, int $index, string $currentUser): array
    {
        if (empty($data['grant_id'])) {
            return ['error' => "Allocation #{$index}: grant_id is required for org_funded allocations"];
        }

        $fteDecimal = $data['fte'] / 100;
        $effectiveDate = $employment->start_date instanceof Carbon
            ? $employment->start_date
            : Carbon::parse($employment->start_date);
        $salaryContext = $this->allocationSalaryService->deriveSalaryContext($employment, $fteDecimal, $effectiveDate);

        // SIMPLIFIED: No more org_funded_allocations record!
        $allocation = EmployeeFundingAllocation::create([
            'employee_id' => $employee->id,
            'employment_id' => $employment->id,
            'grant_item_id' => null,
            'grant_id' => $data['grant_id'],  // DIRECT LINK
            'fte' => $fteDecimal,
            'allocation_type' => 'org_funded',
            'allocated_amount' => $salaryContext['allocated_amount'],
            'salary_type' => $salaryContext['salary_type'],
            'start_date' => $employment->start_date,
            'end_date' => $employment->end_date ?? null,
            'created_by' => $currentUser,
            'updated_by' => $currentUser,
        ]);

        return ['allocation' => $allocation];
    }

    /**
     * Validate grant capacity (SIMPLIFIED)
     */
    public function validateGrantCapacity(GrantItem $grantItem, ?int $excludeEmploymentId = null): array
    {
        if (!$grantItem->grant_position_number || $grantItem->grant_position_number <= 0) {
            return ['valid' => true]; // No capacity constraints
        }

        $today = Carbon::today();
        $query = EmployeeFundingAllocation::where('grant_item_id', $grantItem->id)
            ->where('allocation_type', 'grant')
            ->where('status', 'active')
            ->where('start_date', '<=', $today)
            ->where(function ($q) use ($today) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', $today);
            });

        if ($excludeEmploymentId) {
            $query->where('employment_id', '!=', $excludeEmploymentId);
        }

        $currentAllocations = $query->count();

        if ($currentAllocations >= $grantItem->grant_position_number) {
            return [
                'valid' => false,
                'message' => "Grant position '{$grantItem->grant_position}' has reached its maximum capacity of {$grantItem->grant_position_number} allocations. Currently allocated: {$currentAllocations}",
            ];
        }

        return [
            'valid' => true,
            'available_slots' => $grantItem->grant_position_number - $currentAllocations,
            'total_slots' => $grantItem->grant_position_number,
        ];
    }

    /**
     * Update allocations (SIMPLIFIED - no org_funded cleanup needed)
     */
    public function updateAllocations(Employment $employment, array $newAllocations): array
    {
        $this->validateTotalEffort($newAllocations);

        DB::beginTransaction();

        try {
            // Delete existing allocations (no cascade cleanup needed)
            EmployeeFundingAllocation::where('employment_id', $employment->id)->delete();

            // Create new allocations
            $result = $this->allocateEmployee($employment->employee, $employment, $newAllocations);

            if (!$result['success']) {
                DB::rollBack();
                return $result;
            }

            DB::commit();
            return $result;

        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'errors' => ['Update failed: '.$e->getMessage()]];
        }
    }

    // Rest of methods remain the same...
}
```

---

### 3. GrantItem Model

**File**: `app/Models/GrantItem.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrantItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'grant_id',
        'grant_position',
        'grant_salary',
        'grant_benefit',
        'grant_level_of_effort',
        'grant_position_number',
        'budgetline_code',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'grant_salary' => 'decimal:2',
        'grant_benefit' => 'decimal:2',
        'grant_level_of_effort' => 'decimal:2',
        'grant_position_number' => 'integer',
    ];

    public function grant()
    {
        return $this->belongsTo(Grant::class);
    }

    // REMOVED:
    // public function positionSlots() { ... }

    // NEW: Direct relationship to allocations
    public function employeeFundingAllocations()
    {
        return $this->hasMany(EmployeeFundingAllocation::class);
    }

    // Helper to get active allocations count
    public function getActiveAllocationsCount(): int
    {
        return $this->employeeFundingAllocations()
            ->where('status', 'active')
            ->count();
    }

    // Helper to get available capacity
    public function getAvailableCapacity(): int
    {
        if (!$this->grant_position_number) {
            return PHP_INT_MAX; // Unlimited
        }

        $used = $this->getActiveAllocationsCount();
        return max(0, $this->grant_position_number - $used);
    }

    // Validation remains the same
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($grantItem) {
            static::validateUniqueness($grantItem);
        });

        static::updating(function ($grantItem) {
            static::validateUniqueness($grantItem);
        });
    }

    protected static function validateUniqueness(GrantItem $grantItem): void
    {
        if (is_null($grantItem->grant_position) || is_null($grantItem->budgetline_code) || is_null($grantItem->grant_id)) {
            return;
        }

        $query = static::where('grant_id', $grantItem->grant_id)
            ->where('grant_position', $grantItem->grant_position)
            ->where('budgetline_code', $grantItem->budgetline_code);

        if ($grantItem->exists) {
            $query->where('id', '!=', $grantItem->id);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'grant_position' => [
                    'The combination of grant position "'.$grantItem->grant_position.
                    '" and budget line code "'.$grantItem->budgetline_code.
                    '" already exists for this grant.',
                ],
            ]);
        }
    }
}
```

---

### 4. Grant Model

**File**: `app/Models/Grant.php`

**Changes:**

```php
// REMOVE this relationship:
// public function orgFundedAllocations() {
//     return $this->hasMany(OrgFundedAllocation::class);
// }

// ADD this if not exists:
public function employeeFundingAllocations()
{
    return $this->hasMany(EmployeeFundingAllocation::class);
}
```

---

### 5. EmployeeFundingAllocationResource

**File**: `app/Http/Resources/EmployeeFundingAllocationResource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeFundingAllocationResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employment_id' => $this->employment_id,
            'allocation_type' => $this->allocation_type,
            'fte' => $this->fte,
            'allocated_amount' => $this->allocated_amount,
            'salary_type' => $this->salary_type,
            'status' => $this->status,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),

            // Grant information (works for both types)
            'grant' => $this->allocation_type === 'grant'
                ? optional($this->grantItem)->grant
                : $this->grant,

            // Grant position
            'grant_position' => $this->getGrantPositionName(),

            // Budget line code (only for grant allocations)
            'budgetline_code' => $this->allocation_type === 'grant'
                ? optional($this->grantItem)->budgetline_code
                : null,

            // Department and position from employment
            'department' => optional($this->employment)->department,
            'position' => optional($this->employment)->position,

            // Employee info
            'employee' => [
                'id' => $this->employee->id,
                'staff_id' => $this->employee->staff_id,
                'name' => $this->employee->first_name_en . ' ' . $this->employee->last_name_en,
            ],

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

---

### 6. StoreEmploymentRequest

**File**: `app/Http/Requests/StoreEmploymentRequest.php`

**Update validation rules:**

```php
'allocations.*.grant_item_id' => 'required_if:allocations.*.allocation_type,grant|exists:grant_items,id',
'allocations.*.grant_id' => 'required_if:allocations.*.allocation_type,org_funded|exists:grants,id',

// REMOVE:
// 'allocations.*.position_slot_id' => ...
// 'allocations.*.org_funded_id' => ...
```

---

## Testing Requirements

### Unit Tests

```php
// tests/Unit/Models/EmployeeFundingAllocationTest.php

class EmployeeFundingAllocationTest extends TestCase
{
    /** @test */
    public function it_can_link_directly_to_grant_item()
    {
        $grantItem = GrantItem::factory()->create();
        $allocation = EmployeeFundingAllocation::factory()->create([
            'allocation_type' => 'grant',
            'grant_item_id' => $grantItem->id,
        ]);

        $this->assertEquals($grantItem->id, $allocation->grantItem->id);
    }

    /** @test */
    public function it_can_link_directly_to_grant_for_org_funded()
    {
        $grant = Grant::factory()->create();
        $allocation = EmployeeFundingAllocation::factory()->create([
            'allocation_type' => 'org_funded',
            'grant_id' => $grant->id,
        ]);

        $this->assertEquals($grant->id, $allocation->grant->id);
    }

    /** @test */
    public function it_counts_capacity_correctly()
    {
        $grantItem = GrantItem::factory()->create([
            'grant_position_number' => 3,
        ]);

        // Create 2 allocations
        EmployeeFundingAllocation::factory()->count(2)->create([
            'grant_item_id' => $grantItem->id,
            'allocation_type' => 'grant',
            'status' => 'active',
        ]);

        $this->assertEquals(2, $grantItem->getActiveAllocationsCount());
        $this->assertEquals(1, $grantItem->getAvailableCapacity());
    }
}
```

### Feature Tests

```php
// tests/Feature/FundingAllocationTest.php

class FundingAllocationTest extends TestCase
{
    /** @test */
    public function it_creates_grant_allocation_without_position_slot()
    {
        $employee = Employee::factory()->create();
        $employment = Employment::factory()->create(['employee_id' => $employee->id]);
        $grantItem = GrantItem::factory()->create(['grant_position_number' => 5]);

        $service = app(FundingAllocationService::class);

        $result = $service->allocateEmployee($employee, $employment, [
            [
                'allocation_type' => 'grant',
                'grant_item_id' => $grantItem->id,
                'fte' => 100,
            ]
        ]);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('employee_funding_allocations', [
            'grant_item_id' => $grantItem->id,
            'allocation_type' => 'grant',
        ]);
    }

    /** @test */
    public function it_creates_org_funded_allocation_without_intermediary()
    {
        $employee = Employee::factory()->create();
        $employment = Employment::factory()->create(['employee_id' => $employee->id]);
        $grant = Grant::factory()->create();

        $service = app(FundingAllocationService::class);

        $result = $service->allocateEmployee($employee, $employment, [
            [
                'allocation_type' => 'org_funded',
                'grant_id' => $grant->id,
                'fte' => 100,
            ]
        ]);

        $this->assertTrue($result['success']);
        $this->assertDatabaseHas('employee_funding_allocations', [
            'grant_id' => $grant->id,
            'allocation_type' => 'org_funded',
        ]);
        $this->assertDatabaseMissing('org_funded_allocations', []);
    }
}
```

---

## Rollback Plan

If issues arise:

### 1. Restore from backup

```bash
git checkout backup-before-simplification
mysql -u root -p hrms_db < hrms_backup_YYYYMMDD.sql
```

### 2. Revert code changes

```bash
git revert HEAD
php artisan migrate:fresh --seed
```

---

## Summary Checklist

Before running `migrate:fresh`:

- [ ] Backup database
- [ ] Commit code to git with tag
- [ ] Delete migration files (position_slots, org_funded_allocations)
- [ ] Update employee_funding_allocations migration (add grant_item_id, grant_id)
- [ ] Delete models (PositionSlot, OrgFundedAllocation)
- [ ] Delete controllers (PositionSlotController, OrgFundedAllocationController)
- [ ] Delete requests (StoreOrgFundedAllocationRequest, UpdateOrgFundedAllocationRequest)
- [ ] Delete resources (PositionSlotResource)
- [ ] Update EmployeeFundingAllocation model (relationships, fillable)
- [ ] Update FundingAllocationService (allocation creation logic)
- [ ] Update GrantItem model (remove positionSlots relationship)
- [ ] Update Grant model (remove orgFundedAllocations relationship)
- [ ] Update routes (remove position-slots, org-funded-allocations)
- [ ] Update EmployeeFundingAllocationResource
- [ ] Update StoreEmploymentRequest validation
- [ ] Update tests
- [ ] Run `php artisan migrate:fresh --seed`
- [ ] Test allocation creation
- [ ] Verify capacity counting works

---

**END OF IMPLEMENTATION PLAN**

This document provides complete guidance for removing the two intermediary tables from your HRMS system.
