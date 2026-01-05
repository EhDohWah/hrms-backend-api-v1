# Employment Status Field - Boolean Implementation

## 🎯 Overview

The `status` field in the `employments` table has been implemented as a **BOOLEAN** field for maximum simplicity and efficiency. This design follows the principle that employment is either **Active** or **Inactive** - a simple binary state.

---

## ✅ Why Boolean is Better

### **Performance Comparison**

| Implementation | Storage | Query Speed | Clarity | Database Type |
|----------------|---------|-------------|---------|---------------|
| **Boolean** ✅ | **1 bit** | **Fastest** | **Highest** | `BOOLEAN/TINYINT(1)` |
| Integer (0-5) | 1 byte | Fast | Medium | `TINYINT` |
| String ('active') | 7+ bytes | Slow | Low | `VARCHAR` |

### **Key Benefits**

✅ **Minimal Storage** - Only 1 bit (part of 1 byte)  
✅ **Fastest Queries** - Boolean comparisons are instant  
✅ **Crystal Clear Logic** - `true` = Active, `false` = Inactive  
✅ **Type Safe** - Cannot have invalid values  
✅ **Simple API** - Frontend sends `true`/`false`  
✅ **Industry Standard** - Most systems use boolean for active/inactive  
✅ **Easy to Reason About** - Binary decision, no confusion  

---

## 📊 Database Implementation

### **Migration**

```php
// database/migrations/2025_02_13_025537_create_employments_table.php

Schema::create('employments', function (Blueprint $table) {
    // ... other fields
    
    $table->boolean('status')
        ->default(true)
        ->comment('Employment status: true=Active, false=Inactive');
    
    $table->timestamps();
    $table->string('created_by')->nullable();
    $table->string('updated_by')->nullable();
});
```

### **Database Type**

- **MySQL/MariaDB**: `TINYINT(1)` - 1 byte, stores 0 or 1
- **PostgreSQL**: `BOOLEAN` - native boolean type
- **SQLite**: `INTEGER(1)` - stores 0 or 1
- **SQL Server**: `BIT` - 1 bit

---

## 🔧 Model Implementation

### **Constants**

```php
// app/Models/Employment.php

/** Employment status constants */
public const STATUS_INACTIVE = false;
public const STATUS_ACTIVE = true;
```

### **Fillable Attributes**

```php
protected $fillable = [
    // ... other fields
    'status',
    'created_by',
    'updated_by',
];
```

### **Type Casting**

```php
protected $casts = [
    'start_date' => 'date:Y-m-d',
    'end_date' => 'date:Y-m-d',
    'pass_probation_date' => 'date:Y-m-d',
    'pass_probation_salary' => 'decimal:2',
    'probation_salary' => 'decimal:2',
    'health_welfare' => 'boolean',
    'health_welfare_percentage' => 'decimal:2',
    'pvd' => 'boolean',
    'pvd_percentage' => 'decimal:2',
    'saving_fund' => 'boolean',
    'saving_fund_percentage' => 'decimal:2',
    'status' => 'boolean', // ✅ Boolean casting
];
```

### **Query Scopes**

```php
/**
 * Filter by status
 */
public function scopeByStatus($query, bool $status)
{
    return $query->where('status', $status);
}

/**
 * Get only active employments
 */
public function scopeActiveStatus($query)
{
    return $query->where('status', true);
}

/**
 * Get only inactive employments
 */
public function scopeInactiveStatus($query)
{
    return $query->where('status', false);
}
```

### **Helper Methods**

```php
/**
 * Check if employment is active
 */
public function isActive(): bool
{
    return $this->status === true;
}

/**
 * Check if employment is inactive
 */
public function isInactive(): bool
{
    return $this->status === false;
}

/**
 * Activate employment
 */
public function activate(): bool
{
    return $this->update(['status' => true]);
}

/**
 * Deactivate employment
 */
public function deactivate(): bool
{
    return $this->update(['status' => false]);
}

/**
 * Get status label
 */
public function getStatusLabelAttribute(): string
{
    return $this->status ? 'Active' : 'Inactive';
}
```

---

## 🔐 Validation

### **Store Employment Request**

```php
// app/Http/Requests/StoreEmploymentRequest.php

public function rules(): array
{
    return [
        // ... other rules
        'status' => ['nullable', 'boolean'],
    ];
}

public function messages(): array
{
    return [
        // ... other messages
        'status.boolean' => 'Status must be true (Active) or false (Inactive).',
    ];
}
```

### **Update Employment Request**

```php
// app/Http/Requests/UpdateEmploymentRequest.php

public function rules(): array
{
    return [
        // ... other rules
        'status' => ['sometimes', 'boolean'],
    ];
}

public function messages(): array
{
    return [
        // ... other messages
        'status.boolean' => 'Status must be true (Active) or false (Inactive).',
    ];
}
```

---

## 📡 API Documentation (Swagger)

```php
/**
 * @OA\Schema(
 *   schema="Employment",
 *   // ... other properties
 *   @OA\Property(
 *       property="status",
 *       type="boolean",
 *       default=true,
 *       description="Employment status: true=Active, false=Inactive"
 *   ),
 *   // ... other properties
 * )
 */
```

---

## 💻 Usage Examples

### **Backend (PHP/Laravel)**

#### **Creating Employment**

```php
use App\Models\Employment;

// Create active employment (default)
$employment = Employment::create([
    'employee_id' => 1,
    'department_id' => 5,
    'position_id' => 10,
    'start_date' => '2025-01-15',
    'pass_probation_salary' => 50000,
    'status' => true, // or Employment::STATUS_ACTIVE
    // ... other fields
]);

// Create inactive employment
$employment = Employment::create([
    'employee_id' => 2,
    'status' => false, // or Employment::STATUS_INACTIVE
    // ... other fields
]);
```

#### **Querying by Status**

```php
// Get all active employments
$activeEmployments = Employment::activeStatus()->get();

// Get all inactive employments
$inactiveEmployments = Employment::inactiveStatus()->get();

// Filter by specific status
$active = Employment::byStatus(true)->get();
$inactive = Employment::byStatus(false)->get();

// Get active employments with relationships
$activeWithDetails = Employment::activeStatus()
    ->with(['employee', 'department', 'position'])
    ->get();
```

#### **Updating Status**

```php
// Method 1: Direct update
$employment->update(['status' => false]);

// Method 2: Using helper methods
$employment->activate();   // Sets status to true
$employment->deactivate(); // Sets status to false

// Bulk update - deactivate all expired contracts
Employment::where('end_date', '<', now())
    ->update(['status' => false]);
```

#### **Checking Status**

```php
// Check if active
if ($employment->isActive()) {
    // Handle active employment
    echo "This employment is currently active";
}

// Check if inactive
if ($employment->isInactive()) {
    // Handle inactive employment
    echo "This employment is inactive";
}

// Get status label
echo $employment->status_label; // "Active" or "Inactive"

// Simple ternary
$statusText = $employment->status ? 'Active' : 'Inactive';
```

#### **Complex Queries**

```php
// Active employments in specific department
$activeInDept = Employment::activeStatus()
    ->where('department_id', 5)
    ->get();

// Inactive employments with end date in past
$expiredInactive = Employment::inactiveStatus()
    ->where('end_date', '<', now())
    ->get();

// Count active vs inactive
$activeCount = Employment::activeStatus()->count();
$inactiveCount = Employment::inactiveStatus()->count();

// Get active employments with allocations
$activeWithAllocations = Employment::activeStatus()
    ->with('fundingAllocations')
    ->whereHas('fundingAllocations', function ($query) {
        $query->where('fte', '>', 0);
    })
    ->get();
```

---

### **Frontend (Vue.js/JavaScript)**

#### **API Request Examples**

```javascript
// Create employment (active by default)
const createEmployment = async () => {
  const response = await axios.post('/api/employments', {
    employee_id: 123,
    department_id: 5,
    position_id: 10,
    start_date: '2025-01-15',
    pass_probation_salary: 50000,
    status: true, // Active
    allocations: [
      {
        allocation_type: 'grant',
        fte: 100,
        position_slot_id: 7,
        start_date: '2025-01-15'
      }
    ]
  });
  
  return response.data;
};

// Create inactive employment
const createInactiveEmployment = async () => {
  const response = await axios.post('/api/employments', {
    employee_id: 456,
    status: false, // Inactive
    // ... other fields
  });
  
  return response.data;
};

// Update employment status
const updateStatus = async (employmentId, isActive) => {
  const response = await axios.put(`/api/employments/${employmentId}`, {
    status: isActive // true or false
  });
  
  return response.data;
};

// Activate employment
const activateEmployment = async (employmentId) => {
  return await updateStatus(employmentId, true);
};

// Deactivate employment
const deactivateEmployment = async (employmentId) => {
  return await updateStatus(employmentId, false);
};
```

#### **Vue Component Example**

```vue
<template>
  <div class="employment-form">
    <!-- Status Toggle -->
    <a-form-item label="Employment Status" name="status">
      <a-switch
        v-model:checked="formData.status"
        checked-children="Active"
        un-checked-children="Inactive"
      />
      <span class="status-label">
        {{ formData.status ? 'Active' : 'Inactive' }}
      </span>
    </a-form-item>

    <!-- Status Badge Display -->
    <a-badge
      :status="formData.status ? 'success' : 'default'"
      :text="formData.status ? 'Active' : 'Inactive'"
    />

    <!-- Status Indicator -->
    <div class="status-indicator">
      <span :class="['status-dot', formData.status ? 'active' : 'inactive']"></span>
      {{ formData.status ? 'Active Employment' : 'Inactive Employment' }}
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import { message } from 'ant-design-vue';
import axios from 'axios';

const formData = reactive({
  employee_id: null,
  department_id: null,
  position_id: null,
  start_date: null,
  pass_probation_salary: null,
  status: true, // Default to active
  allocations: []
});

// Submit form
const handleSubmit = async () => {
  try {
    const response = await axios.post('/api/employments', formData);
    
    if (response.data.success) {
      message.success(
        `Employment created successfully! Status: ${
          formData.status ? 'Active' : 'Inactive'
        }`
      );
    }
  } catch (error) {
    message.error('Failed to create employment');
  }
};

// Toggle status
const toggleStatus = () => {
  formData.status = !formData.status;
  message.info(`Status changed to ${formData.status ? 'Active' : 'Inactive'}`);
};
</script>

<style scoped>
.status-label {
  margin-left: 8px;
  font-weight: 500;
}

.status-indicator {
  display: flex;
  align-items: center;
  gap: 8px;
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
}

.status-dot.active {
  background-color: #52c41a; /* Green */
}

.status-dot.inactive {
  background-color: #d9d9d9; /* Gray */
}
</style>
```

#### **Status Display Component**

```vue
<template>
  <a-tag :color="statusColor">
    {{ statusLabel }}
  </a-tag>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  status: {
    type: Boolean,
    required: true
  }
});

const statusColor = computed(() => props.status ? 'success' : 'default');
const statusLabel = computed(() => props.status ? 'Active' : 'Inactive');
</script>
```

#### **Data Table with Status Filter**

```vue
<template>
  <div>
    <!-- Filter Buttons -->
    <a-space style="margin-bottom: 16px">
      <a-button
        :type="statusFilter === null ? 'primary' : 'default'"
        @click="statusFilter = null"
      >
        All
      </a-button>
      <a-button
        :type="statusFilter === true ? 'primary' : 'default'"
        @click="statusFilter = true"
      >
        Active Only
      </a-button>
      <a-button
        :type="statusFilter === false ? 'primary' : 'default'"
        @click="statusFilter = false"
      >
        Inactive Only
      </a-button>
    </a-space>

    <!-- Data Table -->
    <a-table
      :columns="columns"
      :data-source="filteredEmployments"
      :loading="loading"
      row-key="id"
    >
      <template #bodyCell="{ column, record }">
        <template v-if="column.key === 'status'">
          <a-tag :color="record.status ? 'success' : 'default'">
            {{ record.status ? 'Active' : 'Inactive' }}
          </a-tag>
        </template>
        
        <template v-if="column.key === 'actions'">
          <a-space>
            <a-button
              size="small"
              :type="record.status ? 'default' : 'primary'"
              @click="toggleEmploymentStatus(record)"
            >
              {{ record.status ? 'Deactivate' : 'Activate' }}
            </a-button>
          </a-space>
        </template>
      </template>
    </a-table>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { message } from 'ant-design-vue';
import axios from 'axios';

const employments = ref([]);
const loading = ref(false);
const statusFilter = ref(null); // null = all, true = active, false = inactive

const columns = [
  { title: 'Employee', dataIndex: 'employee_name', key: 'employee_name' },
  { title: 'Department', dataIndex: 'department_name', key: 'department_name' },
  { title: 'Position', dataIndex: 'position_title', key: 'position_title' },
  { title: 'Status', key: 'status', align: 'center' },
  { title: 'Actions', key: 'actions', align: 'center' }
];

// Filter employments by status
const filteredEmployments = computed(() => {
  if (statusFilter.value === null) {
    return employments.value; // Show all
  }
  return employments.value.filter(emp => emp.status === statusFilter.value);
});

// Toggle employment status
const toggleEmploymentStatus = async (employment) => {
  try {
    const newStatus = !employment.status;
    
    const response = await axios.put(`/api/employments/${employment.id}`, {
      status: newStatus
    });

    if (response.data.success) {
      employment.status = newStatus;
      message.success(
        `Employment ${newStatus ? 'activated' : 'deactivated'} successfully`
      );
    }
  } catch (error) {
    message.error('Failed to update employment status');
  }
};

// Load employments
const loadEmployments = async () => {
  loading.value = true;
  try {
    const response = await axios.get('/api/employments');
    employments.value = response.data.data;
  } catch (error) {
    message.error('Failed to load employments');
  } finally {
    loading.value = false;
  }
};

// Initial load
loadEmployments();
</script>
```

---

## 🔄 API Response Examples

### **Single Employment Response**

```json
{
  "success": true,
  "message": "Employment retrieved successfully",
  "data": {
    "id": 123,
    "employee_id": 456,
    "department_id": 5,
    "position_id": 10,
    "employment_type": "full_time",
    "start_date": "2025-01-15",
    "end_date": null,
    "pass_probation_salary": 50000.00,
    "probation_salary": 45000.00,
    "status": true,
    "created_at": "2025-01-15T08:00:00.000000Z",
    "updated_at": "2025-01-15T08:00:00.000000Z",
    "employee": {
      "id": 456,
      "staff_id": "EMP001",
      "first_name_en": "John",
      "last_name_en": "Doe"
    },
    "department": {
      "id": 5,
      "name": "Engineering"
    },
    "position": {
      "id": 10,
      "title": "Senior Developer"
    }
  }
}
```

### **List Response with Mixed Status**

```json
{
  "success": true,
  "message": "Employments retrieved successfully",
  "data": [
    {
      "id": 1,
      "employee_name": "John Doe",
      "department_name": "Engineering",
      "position_title": "Senior Developer",
      "status": true,
      "start_date": "2025-01-15"
    },
    {
      "id": 2,
      "employee_name": "Jane Smith",
      "department_name": "Marketing",
      "position_title": "Marketing Manager",
      "status": false,
      "start_date": "2024-06-01",
      "end_date": "2024-12-31"
    },
    {
      "id": 3,
      "employee_name": "Bob Johnson",
      "department_name": "Finance",
      "position_title": "Accountant",
      "status": true,
      "start_date": "2024-03-01"
    }
  ],
  "meta": {
    "total": 3,
    "active_count": 2,
    "inactive_count": 1
  }
}
```

---

## 🎨 Frontend Display Patterns

### **Status Badge Colors**

```javascript
// Ant Design Vue
const getStatusBadge = (status) => {
  return status ? 'success' : 'default';
};

// Custom colors
const getStatusColor = (status) => {
  return status ? '#52c41a' : '#d9d9d9'; // Green : Gray
};

// With icon
const getStatusIcon = (status) => {
  return status ? 'check-circle' : 'minus-circle';
};
```

### **Status Text Localization**

```javascript
// English
const getStatusLabel = (status) => {
  return status ? 'Active' : 'Inactive';
};

// Thai
const getStatusLabelTH = (status) => {
  return status ? 'ใช้งาน' : 'ไม่ใช้งาน';
};

// With emoji
const getStatusEmoji = (status) => {
  return status ? '✅ Active' : '⭕ Inactive';
};
```

---

## 🔍 Database Query Performance

### **Query Examples**

```sql
-- Get all active employments (super fast boolean comparison)
SELECT * FROM employments WHERE status = 1;

-- Get all inactive employments
SELECT * FROM employments WHERE status = 0;

-- Count active vs inactive
SELECT 
  status,
  COUNT(*) as count
FROM employments
GROUP BY status;

-- Active employments with details
SELECT 
  e.*,
  emp.first_name_en,
  emp.last_name_en,
  d.name as department_name,
  p.title as position_title
FROM employments e
INNER JOIN employees emp ON e.employee_id = emp.id
INNER JOIN departments d ON e.department_id = d.id
INNER JOIN positions p ON e.position_id = p.id
WHERE e.status = 1; -- Boolean comparison is fastest

-- Toggle status (very efficient update)
UPDATE employments 
SET status = NOT status 
WHERE id = 123;
```

### **Index Optimization**

```sql
-- Add index for status queries
CREATE INDEX idx_employments_status ON employments(status);

-- Composite index for common filters
CREATE INDEX idx_employments_status_dates 
ON employments(status, start_date, end_date);

-- Covering index for status queries with related fields
CREATE INDEX idx_employments_status_covering 
ON employments(status, employee_id, department_id, position_id);
```

---

## 📋 Migration Guide

### **If Migrating from Integer Status (0-5)**

```php
// Step 1: Add new boolean column
Schema::table('employments', function (Blueprint $table) {
    $table->boolean('status_new')->default(true)->after('status');
});

// Step 2: Migrate data
DB::table('employments')->update([
    'status_new' => DB::raw('CASE WHEN status = 1 THEN 1 ELSE 0 END')
]);

// Step 3: Drop old column and rename
Schema::table('employments', function (Blueprint $table) {
    $table->dropColumn('status');
    $table->renameColumn('status_new', 'status');
});
```

### **If Migrating from String Status ('active'/'inactive')**

```php
// Step 1: Add boolean column
Schema::table('employments', function (Blueprint $table) {
    $table->boolean('status_new')->default(true)->after('status');
});

// Step 2: Migrate data
DB::table('employments')->update([
    'status_new' => DB::raw("CASE WHEN status = 'active' THEN 1 ELSE 0 END")
]);

// Step 3: Drop old and rename
Schema::table('employments', function (Blueprint $table) {
    $table->dropColumn('status');
    $table->renameColumn('status_new', 'status');
});
```

---

## ✅ Testing Examples

### **Feature Tests**

```php
// tests/Feature/EmploymentStatusTest.php

public function test_can_create_active_employment()
{
    $response = $this->postJson('/api/employments', [
        'employee_id' => 1,
        'department_id' => 5,
        'position_id' => 10,
        'start_date' => '2025-01-15',
        'pass_probation_salary' => 50000,
        'status' => true,
        'allocations' => [/* ... */]
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('employments', [
        'employee_id' => 1,
        'status' => true
    ]);
}

public function test_can_create_inactive_employment()
{
    $response = $this->postJson('/api/employments', [
        'employee_id' => 1,
        'status' => false,
        // ... other fields
    ]);

    $response->assertStatus(201);
    $this->assertDatabaseHas('employments', [
        'employee_id' => 1,
        'status' => false
    ]);
}

public function test_can_filter_active_employments()
{
    Employment::factory()->count(5)->create(['status' => true]);
    Employment::factory()->count(3)->create(['status' => false]);

    $active = Employment::activeStatus()->get();
    $inactive = Employment::inactiveStatus()->get();

    $this->assertCount(5, $active);
    $this->assertCount(3, $inactive);
}

public function test_can_toggle_employment_status()
{
    $employment = Employment::factory()->create(['status' => true]);
    
    $this->assertTrue($employment->isActive());
    
    $employment->deactivate();
    $this->assertTrue($employment->isInactive());
    
    $employment->activate();
    $this->assertTrue($employment->isActive());
}

public function test_status_validation_requires_boolean()
{
    $response = $this->postJson('/api/employments', [
        'employee_id' => 1,
        'status' => 'invalid', // Should fail
        // ... other fields
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['status']);
}
```

---

## 📊 Benefits Summary

| Aspect | Boolean Implementation | Previous Implementations |
|--------|----------------------|-------------------------|
| **Storage** | 1 bit | 1-7+ bytes |
| **Query Speed** | Fastest possible | Slower |
| **Clarity** | Extremely clear | Can be confusing |
| **Validation** | Simple | Complex rules |
| **Frontend** | Easy `true`/`false` | Multiple values to handle |
| **Database Type** | Native boolean | Emulated or strings |
| **Type Safety** | Highest | Variable |
| **Maintenance** | Minimal | Higher complexity |

---

## 🎯 Best Practices

### **DO** ✅

- Use `true` for Active, `false` for Inactive
- Use constants: `Employment::STATUS_ACTIVE`
- Use helper methods: `isActive()`, `activate()`
- Use scopes: `activeStatus()`, `inactiveStatus()`
- Cast to boolean in model
- Validate as boolean in requests
- Display with clear UI indicators (badges, colors)

### **DON'T** ❌

- Don't use strings for status
- Don't use multiple integer values for simple active/inactive
- Don't use nullable status (always set default)
- Don't skip validation
- Don't mix boolean with other status types
- Don't forget to cast in model

---

## 🚀 Conclusion

The **BOOLEAN implementation** for employment status is:

✅ **Simplest** - Just two states: Active or Inactive  
✅ **Fastest** - Optimal query performance  
✅ **Clearest** - No ambiguity  
✅ **Most Efficient** - Minimal storage  
✅ **Industry Standard** - Common practice  
✅ **Easy to Maintain** - Minimal complexity  

**Perfect for binary states!** 🎉

---

## 📝 Swagger/OpenAPI Documentation

The `status` field is fully documented in the API Swagger annotations:

### **Store Employment (POST /employments)**

```php
@OA\Property(
    property="status",
    type="boolean",
    default=true,
    description="Employment status: true=Active, false=Inactive",
    nullable=true
)
```

### **Update Employment (PUT /employments/{id})**

```php
@OA\Property(
    property="status",
    type="boolean",
    description="Employment status: true=Active, false=Inactive",
    nullable=true
)
```

### **Get Employment List (GET /employments)**

The `status` field is included in the response:

```json
{
  "id": 123,
  "employee_id": 456,
  "status": true,
  // ... other fields
}
```

---

## 📋 Implementation Checklist

- [x] Database migration updated to boolean
- [x] Employment model constants (true/false)
- [x] Model casts to boolean
- [x] Query scopes (activeStatus, inactiveStatus)
- [x] Helper methods (isActive, activate, deactivate)
- [x] StoreEmploymentRequest validation
- [x] UpdateEmploymentRequest validation
- [x] **EmploymentController - index method includes status** ✅
- [x] **Swagger documentation for store endpoint** ✅
- [x] **Swagger documentation for update endpoint** ✅
- [x] Formatted all files with Laravel Pint
- [x] Created comprehensive documentation

---

## 📚 Related Documentation

- `EMPLOYMENT_MANAGEMENT_SYSTEM_COMPLETE_DOCUMENTATION.md`
- `FRONTEND_EMPLOYMENT_MIGRATION_GUIDE.md`
- `EMPLOYMENT_API_CHANGES_V2.md`
- `STATUS_FIELD_EVOLUTION.md`

---

## 📁 Files Updated

1. ✅ `database/migrations/2025_02_13_025537_create_employments_table.php`
2. ✅ `app/Models/Employment.php`
3. ✅ `app/Http/Requests/StoreEmploymentRequest.php`
4. ✅ `app/Http/Requests/UpdateEmploymentRequest.php`
5. ✅ `app/Http/Controllers/Api/EmploymentController.php` (Swagger docs)
6. ✅ `docs/EMPLOYMENT_STATUS_BOOLEAN_IMPLEMENTATION.md`
7. ✅ `docs/STATUS_FIELD_EVOLUTION.md`

---

**Last Updated:** 2025-10-14  
**Version:** 1.0.0  
**Implementation Type:** Boolean (true/false)  
**Status:** ✅ **COMPLETE - Including Controller & Swagger Documentation**

