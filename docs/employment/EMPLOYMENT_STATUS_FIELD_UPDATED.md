# Employment Status Field Implementation (Integer-Based)

## Date: January 2025
## Status: ✅ COMPLETED - UPDATED TO INTEGER

---

## Overview

Added a `status` field to the `employments` table using **TINYINT (integer)** values for optimal database performance and standard practices. This field tracks the current state of each employment record.

---

## Status Field Details

### Database Column

**Table:** `employments`  
**Column:** `status`  
**Type:** `TINYINT (integer)`  
**Default:** `1` (Active)  
**Nullable:** No  
**Comment:** Employment status: 0=Inactive, 1=Active, 2=Pending, 3=Expired, 4=Terminated, 5=Suspended

### Status Values (Integer-Based)

| Value | Constant | Label | Description | Use Case |
|-------|----------|-------|-------------|----------|
| `0` | `STATUS_INACTIVE` | Inactive | Employment is inactive | Deactivated or not in use |
| `1` | `STATUS_ACTIVE` | Active | Employment is currently active | **Default** - Current employments |
| `2` | `STATUS_PENDING` | Pending | Employment not yet started | Future start date or awaiting approval |
| `3` | `STATUS_EXPIRED` | Expired | Employment has ended | Past end_date reached |
| `4` | `STATUS_TERMINATED` | Terminated | Employment terminated early | Resignation, termination before end_date |
| `5` | `STATUS_SUSPENDED` | Suspended | Employment temporarily suspended | Leave of absence, temporary suspension |

---

## Why Integer Instead of String?

### ✅ Benefits of Integer Status:

1. **Performance** - Integer comparisons are faster than string comparisons
2. **Storage** - TINYINT uses 1 byte vs VARCHAR uses multiple bytes
3. **Indexing** - Integer indexes are more efficient
4. **Database Standard** - Industry best practice
5. **Type Safety** - Less prone to typos (can't type "actve" instead of "active")
6. **Memory** - Uses less memory in queries and caching

### 📊 Performance Comparison:

| Aspect | String ('active') | Integer (1) |
|--------|-------------------|-------------|
| Storage | 7+ bytes | 1 byte |
| Index Size | Larger | Smaller |
| Comparison Speed | Slower | Faster |
| Memory Usage | Higher | Lower |
| Type Safety | Lower | Higher |

---

## Files Modified

### 1. Database Migration ✅
**File:** `database/migrations/2025_02_13_025537_create_employments_table.php`

**Change:**
```php
$table->tinyInteger('status')->default(1)->comment('Employment status: 0=Inactive, 1=Active, 2=Pending, 3=Expired, 4=Terminated, 5=Suspended');
```

### 2. Employment Model ✅
**File:** `app/Models/Employment.php`

**Changes:**

#### A. Status Constants (Integer-Based)
```php
public const STATUS_INACTIVE = 0;
public const STATUS_ACTIVE = 1;
public const STATUS_PENDING = 2;
public const STATUS_EXPIRED = 3;
public const STATUS_TERMINATED = 4;
public const STATUS_SUSPENDED = 5;
```

#### B. Added to Fillable
```php
protected $fillable = [
    // ... existing fields
    'status',
    'created_by',
    'updated_by',
];
```

#### C. Added to Casts
```php
protected $casts = [
    // ... existing casts
    'status' => 'integer',
];
```

#### D. Updated Swagger Documentation
```php
@OA\Property(property="status", type="integer", default=1, description="Employment status: 0=Inactive, 1=Active, 2=Pending, 3=Expired, 4=Terminated, 5=Suspended")
```

#### E. Query Scopes
```php
public function scopeByStatus($query, int $status);
public function scopeInactiveStatus($query);  // NEW
public function scopeActiveStatus($query);
public function scopePendingStatus($query);
public function scopeExpiredStatus($query);
public function scopeTerminatedStatus($query);
public function scopeSuspendedStatus($query);
```

#### F. Helper Methods
```php
public function isInactive(): bool;  // NEW
public function isActive(): bool;
public function isPending(): bool;
public function isExpired(): bool;
public function isTerminated(): bool;
public function isSuspended(): bool;
public static function getStatusOptions(): array;
public function getStatusLabelAttribute(): string;  // NEW - Get human-readable label
```

### 3. Form Requests ✅

#### Store Employment Request
**File:** `app/Http/Requests/StoreEmploymentRequest.php`

```php
'status' => ['nullable', 'integer', Rule::in([0, 1, 2, 3, 4, 5])],
```

**Message:**
```php
'status.in' => 'Invalid status. Must be 0 (Inactive), 1 (Active), 2 (Pending), 3 (Expired), 4 (Terminated), or 5 (Suspended).',
```

#### Update Employment Request
**File:** `app/Http/Requests/UpdateEmploymentRequest.php`

```php
'status' => ['sometimes', 'integer', Rule::in([0, 1, 2, 3, 4, 5])],
```

### 4. Employment Controller ✅
**File:** `app/Http/Controllers/Api/EmploymentController.php`

- Added `'status'` to select array
- Returns integer status in API responses

---

## Usage Examples

### Using Constants

```php
use App\Models\Employment;

// Create employment with specific status
$employment = Employment::create([
    'status' => Employment::STATUS_ACTIVE,  // 1
    // ... other fields
]);

// Update status
$employment->update(['status' => Employment::STATUS_PENDING]);  // 2

// Set to inactive
$employment->update(['status' => Employment::STATUS_INACTIVE]);  // 0
```

### Using Query Scopes

```php
// Get all active employments
$activeEmployments = Employment::activeStatus()->get();

// Get inactive employments
$inactiveEmployments = Employment::inactiveStatus()->get();

// Get by specific status number
$pending = Employment::byStatus(2)->get();  // 2 = Pending

// Get by constant
$terminated = Employment::byStatus(Employment::STATUS_TERMINATED)->get();

// Combine with other scopes
$activeFullTime = Employment::activeStatus()
    ->byEmploymentType('Full-time')
    ->get();
```

### Using Helper Methods

```php
$employment = Employment::find(1);

if ($employment->isActive()) {  // status === 1
    // Handle active employment
}

if ($employment->isInactive()) {  // status === 0
    // Handle inactive employment
}

if ($employment->isPending()) {  // status === 2
    // Handle pending employment
}

// Get human-readable label
echo $employment->status_label;  // "Active"
```

### Get Status Options

```php
$statusOptions = Employment::getStatusOptions();
// Returns:
// [
//     0 => 'Inactive',
//     1 => 'Active',
//     2 => 'Pending',
//     3 => 'Expired',
//     4 => 'Terminated',
//     5 => 'Suspended',
// ]

// Use in dropdown
foreach (Employment::getStatusOptions() as $value => $label) {
    echo "<option value='{$value}'>{$label}</option>";
}
```

---

## API Changes

### Create Employment

**Endpoint:** `POST /api/employments`

**Request (Integer Status):**
```json
{
  "status": 1,
  "employee_id": 123,
  // ... other fields
}
```

**Validation:**
- Optional (defaults to `1` = Active)
- Must be integer: 0, 1, 2, 3, 4, or 5

### Update Employment

**Endpoint:** `PUT /api/employments/{id}`

**Request:**
```json
{
  "status": 4
  // ... other fields
}
```

### Get Employments

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "status": 1,
      "employee": { ... },
      // ... other fields
    }
  ]
}
```

---

## Frontend Integration

### Form Field (Dropdown)

```vue
<template>
  <div class="form-group">
    <label>Employment Status</label>
    <select v-model.number="formData.status" class="form-control">
      <option :value="0">Inactive</option>
      <option :value="1">Active</option>
      <option :value="2">Pending</option>
      <option :value="3">Expired</option>
      <option :value="4">Terminated</option>
      <option :value="5">Suspended</option>
    </select>
  </div>
</template>

<script>
export default {
  data() {
    return {
      formData: {
        status: 1, // Default to Active (integer)
        // ... other fields
      },
      statusOptions: [
        { value: 0, label: 'Inactive' },
        { value: 1, label: 'Active' },
        { value: 2, label: 'Pending' },
        { value: 3, label: 'Expired' },
        { value: 4, label: 'Terminated' },
        { value: 5, label: 'Suspended' },
      ]
    };
  }
};
</script>
```

### Status Badge Display

```vue
<template>
  <span :class="['badge', `badge-status-${employment.status}`]">
    {{ getStatusLabel(employment.status) }}
  </span>
</template>

<script>
export default {
  methods: {
    getStatusLabel(status) {
      const labels = {
        0: 'Inactive',
        1: 'Active',
        2: 'Pending',
        3: 'Expired',
        4: 'Terminated',
        5: 'Suspended'
      };
      return labels[status] || 'Unknown';
    }
  }
};
</script>

<style scoped>
.badge-status-0 { background-color: #6c757d; color: white; } /* Inactive - Gray */
.badge-status-1 { background-color: #28a745; color: white; } /* Active - Green */
.badge-status-2 { background-color: #ffc107; color: black; } /* Pending - Yellow */
.badge-status-3 { background-color: #6c757d; color: white; } /* Expired - Gray */
.badge-status-4 { background-color: #dc3545; color: white; } /* Terminated - Red */
.badge-status-5 { background-color: #17a2b8; color: white; } /* Suspended - Cyan */
</style>
```

### Filter by Status

```vue
<template>
  <div class="filter-group">
    <label>Filter by Status:</label>
    <select v-model.number="filters.status" @change="fetchEmployments">
      <option :value="null">All Statuses</option>
      <option :value="0">Inactive</option>
      <option :value="1">Active</option>
      <option :value="2">Pending</option>
      <option :value="3">Expired</option>
      <option :value="4">Terminated</option>
      <option :value="5">Suspended</option>
    </select>
  </div>
</template>

<script>
export default {
  data() {
    return {
      filters: {
        status: null
      }
    };
  },
  
  methods: {
    async fetchEmployments() {
      const params = {};
      if (this.filters.status !== null) {
        params.filter_status = this.filters.status;
      }
      
      const response = await employmentService.getAllEmployments(params);
      this.employments = response.data;
    }
  }
};
</script>
```

### Status Constants (Frontend)

```javascript
// constants/employmentStatus.js
export const EMPLOYMENT_STATUS = {
  INACTIVE: 0,
  ACTIVE: 1,
  PENDING: 2,
  EXPIRED: 3,
  TERMINATED: 4,
  SUSPENDED: 5
};

export const EMPLOYMENT_STATUS_LABELS = {
  [EMPLOYMENT_STATUS.INACTIVE]: 'Inactive',
  [EMPLOYMENT_STATUS.ACTIVE]: 'Active',
  [EMPLOYMENT_STATUS.PENDING]: 'Pending',
  [EMPLOYMENT_STATUS.EXPIRED]: 'Expired',
  [EMPLOYMENT_STATUS.TERMINATED]: 'Terminated',
  [EMPLOYMENT_STATUS.SUSPENDED]: 'Suspended'
};

// Usage
import { EMPLOYMENT_STATUS, EMPLOYMENT_STATUS_LABELS } from '@/constants/employmentStatus';

// Create employment
formData.status = EMPLOYMENT_STATUS.ACTIVE;

// Display label
const label = EMPLOYMENT_STATUS_LABELS[employment.status];
```

---

## Migration Notes

### No Data Migration Required

✅ The status field has a default value of `1` (Active), so:
- Existing records will automatically have `status=1`
- No manual data update needed
- No downtime required

### Running the Migration

```bash
# Run migrations
php artisan migrate

# Rollback if needed
php artisan migrate:rollback
```

---

## Testing

### Unit Tests

```php
use App\Models\Employment;

/** @test */
public function it_has_correct_default_status()
{
    $employment = Employment::factory()->create();
    
    $this->assertEquals(1, $employment->status);
    $this->assertEquals(Employment::STATUS_ACTIVE, $employment->status);
}

/** @test */
public function it_can_filter_by_status()
{
    Employment::factory()->create(['status' => Employment::STATUS_ACTIVE]);
    Employment::factory()->create(['status' => Employment::STATUS_PENDING]);
    Employment::factory()->create(['status' => Employment::STATUS_TERMINATED]);
    
    $active = Employment::activeStatus()->count();
    $pending = Employment::pendingStatus()->count();
    $inactive = Employment::inactiveStatus()->count();
    
    $this->assertEquals(1, $active);
    $this->assertEquals(1, $pending);
    $this->assertEquals(0, $inactive);
}

/** @test */
public function it_validates_status_values()
{
    $this->postJson('/api/employments', [
        'status' => 99, // Invalid status
        // ... other required fields
    ])
    ->assertStatus(422)
    ->assertJsonValidationErrors('status');
}

/** @test */
public function it_casts_status_to_integer()
{
    $employment = Employment::factory()->create(['status' => '2']);
    
    $this->assertIsInt($employment->status);
    $this->assertEquals(2, $employment->status);
}

/** @test */
public function it_returns_correct_status_label()
{
    $employment = Employment::factory()->create(['status' => Employment::STATUS_ACTIVE]);
    
    $this->assertEquals('Active', $employment->status_label);
}
```

### Feature Tests

```php
/** @test */
public function it_can_create_employment_with_integer_status()
{
    $response = $this->postJson('/api/employments', [
        'employee_id' => 1,
        'status' => 2,  // Pending
        // ... other required fields
    ]);
    
    $response->assertStatus(201);
    $this->assertEquals(2, $response->json('data.status'));
}

/** @test */
public function it_can_update_employment_status()
{
    $employment = Employment::factory()->create(['status' => 2]);  // Pending
    
    $response = $this->putJson("/api/employments/{$employment->id}", [
        'status' => 1  // Active
    ]);
    
    $response->assertStatus(200);
    $this->assertEquals(1, $employment->fresh()->status);
}
```

---

## Database Query Examples

```php
// All employments with status
Employment::all();

// Only active
Employment::where('status', 1)->get();
Employment::activeStatus()->get();

// Multiple statuses
Employment::whereIn('status', [1, 2])->get();  // Active or Pending

// Not inactive
Employment::where('status', '!=', 0)->get();

// Inactive or expired
Employment::whereIn('status', [0, 3])->get();

// Status with other conditions
Employment::where('status', 1)
    ->where('employment_type', 'Full-time')
    ->where('start_date', '>=', now()->subYear())
    ->get();
```

---

## Benefits Summary

### ✅ Performance Benefits
- Faster queries (integer comparison)
- Smaller database size (1 byte vs 7+ bytes per record)
- More efficient indexes
- Less memory usage

### ✅ Developer Benefits
- Type safety with constants
- Clear, self-documenting code
- Easier to work with in code
- Standard database practice

### ✅ Maintenance Benefits
- Easy to extend (add new status = add new number)
- No typo errors
- Better IDE autocomplete
- Cleaner API responses

---

## Status Transition Logic (Optional Enhancement)

```php
// In Employment Model
public function canTransitionTo(int $newStatus): bool
{
    $transitions = [
        self::STATUS_INACTIVE => [self::STATUS_ACTIVE],
        self::STATUS_PENDING => [self::STATUS_ACTIVE, self::STATUS_TERMINATED],
        self::STATUS_ACTIVE => [self::STATUS_EXPIRED, self::STATUS_TERMINATED, self::STATUS_SUSPENDED, self::STATUS_INACTIVE],
        self::STATUS_SUSPENDED => [self::STATUS_ACTIVE, self::STATUS_TERMINATED],
        self::STATUS_EXPIRED => [self::STATUS_INACTIVE],
        self::STATUS_TERMINATED => [self::STATUS_INACTIVE],
    ];
    
    return in_array($newStatus, $transitions[$this->status] ?? []);
}

public function transitionTo(int $newStatus, ?string $reason = null): bool
{
    if (!$this->canTransitionTo($newStatus)) {
        throw new \InvalidArgumentException(
            "Cannot transition from status {$this->status} to {$newStatus}"
        );
    }
    
    $this->update(['status' => $newStatus]);
    
    if ($reason) {
        $this->addHistoryEntry($reason);
    }
    
    return true;
}
```

---

## Automated Status Updates (Command)

```php
// app/Console/Commands/UpdateEmploymentStatuses.php

use App\Models\Employment;
use Carbon\Carbon;
use Illuminate\Console\Command;

class UpdateEmploymentStatuses extends Command
{
    protected $signature = 'employments:update-statuses';
    protected $description = 'Update employment statuses based on dates';

    public function handle()
    {
        $today = Carbon::today();
        
        // Update pending to active (start date reached)
        $pending = Employment::where('status', Employment::STATUS_PENDING)
            ->where('start_date', '<=', $today)
            ->update(['status' => Employment::STATUS_ACTIVE]);
        
        // Update active to expired (end date reached)
        $expired = Employment::where('status', Employment::STATUS_ACTIVE)
            ->whereNotNull('end_date')
            ->where('end_date', '<', $today)
            ->update(['status' => Employment::STATUS_EXPIRED]);
        
        $this->info("Updated {$pending} pending to active");
        $this->info("Updated {$expired} active to expired");
        $this->info('Employment statuses updated successfully.');
        
        return Command::SUCCESS;
    }
}

// Schedule in routes/console.php or app/Console/Kernel.php
Schedule::command('employments:update-statuses')->daily();
```

---

## Summary

✅ **Status field implemented using INTEGER for optimal performance**

**Key Points:**
- Uses TINYINT (1 byte storage)
- Default value: `1` (Active)
- Constants for type safety
- Query scopes for easy filtering
- Helper methods for status checks
- Validation rules for API requests
- Fully backward compatible
- Production-ready

**Status Values:**
- `0` = Inactive
- `1` = Active (default)
- `2` = Pending
- `3` = Expired
- `4` = Terminated
- `5` = Suspended

---

**Implementation Date:** January 2025  
**Status:** ✅ Complete - Using Integer Values  
**Performance:** ✅ Optimized for Speed and Storage

