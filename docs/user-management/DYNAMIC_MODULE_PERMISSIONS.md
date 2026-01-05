# Dynamic Module-Based Permission System

## Overview

The HRMS implements a **dynamic, module-based permission system** with **Read** and **Edit** capabilities. This system allows admins and HR managers to configure user access to different modules without hardcoding permission mappings.

### Key Features

- **Two-Level Access Control**: Read-only vs Full Edit access per module
- **Dynamic Permission Enforcement**: Middleware automatically checks permissions based on HTTP methods
- **No Hardcoded Mappings**: All permission rules stored in the `modules` table
- **User-Friendly Interface**: Simple Read/Edit checkboxes for each module
- **Granular Control**: Each module can have different edit permissions (create, update, delete, import, export, bulk_create)

---

## Permission Model

### Access Levels

Each module can have **two types of access** for a user:

| Access Level | Description | User Can |
|--------------|-------------|----------|
| **Read** | View-only access | - View lists<br>- View details<br>- Export data (if `module.export` is in edit_permissions) |
| **Edit** | Full CRUD access | - All Read capabilities<br>- Create new records<br>- Update existing records<br>- Delete records<br>- Import data<br>- Perform bulk operations |

### Permission Combinations

| Read Checkbox | Edit Checkbox | Result |
|---------------|---------------|--------|
| ☐ Unchecked | ☐ Unchecked | No access to module |
| ☑ Checked | ☐ Unchecked | **Read-only access** |
| ☐ Unchecked | ☑ Checked | Edit access (typically includes read) |
| ☑ Checked | ☑ Checked | **Full access** (recommended) |

---

## Database Schema

### Modules Table

```sql
CREATE TABLE modules (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL,              -- e.g., 'employee', 'user_management'
    display_name VARCHAR(255) NOT NULL,             -- e.g., 'Employee Management'
    description TEXT,
    icon VARCHAR(255),                              -- Icon class
    category VARCHAR(255),                          -- e.g., 'HR', 'Administration'
    route VARCHAR(255),                             -- Frontend route
    read_permission VARCHAR(255) NOT NULL,          -- e.g., 'employee.read'
    edit_permissions JSON,                          -- e.g., ["employee.create", "employee.update", "employee.delete"]
    `order` INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    parent_id BIGINT,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);
```

### Example Module Record

```json
{
  "id": 5,
  "name": "employee",
  "display_name": "Employee Management",
  "category": "HR",
  "icon": "users",
  "route": "/employees",
  "read_permission": "employee.read",
  "edit_permissions": [
    "employee.create",
    "employee.update",
    "employee.delete",
    "employee.import",
    "employee.export",
    "employee.bulk_create"
  ],
  "order": 5,
  "is_active": true
}
```

---

## How It Works

### 1. Permission Assignment (Admin/HR Manager)

Admins use the UserPermissionController to assign permissions via Read/Edit checkboxes:

**Request:**
```json
PUT /api/v1/admin/user-permissions/15
{
  "modules": {
    "employee": {
      "read": true,
      "edit": false
    },
    "leave_request": {
      "read": true,
      "edit": true
    }
  }
}
```

**What Happens:**
1. Controller looks up each module in the `modules` table
2. For `employee` module (read: true, edit: false):
   - Grants `employee.read` permission
3. For `leave_request` module (read: true, edit: true):
   - Grants `leave_request.read` permission
   - Grants ALL permissions from `edit_permissions` array
4. Uses `user->syncPermissions()` to replace all user permissions

**Result:**
User #15 now has:
- ✅ `employee.read` (view-only)
- ✅ `leave_request.read`
- ✅ `leave_request.create`
- ✅ `leave_request.update`
- ✅ `leave_request.delete`
- ✅ `leave_request.import`
- ✅ `leave_request.export`
- ✅ `leave_request.bulk_create`

### 2. Permission Enforcement (Runtime)

When a user makes a request, the `DynamicModulePermission` middleware checks permissions:

**Example:** User tries to create an employee

```
POST /api/v1/employees
Headers: Authorization: Bearer {token}
```

**Middleware Logic:**
1. Identifies HTTP method: `POST` (write operation)
2. Looks up module: `employee`
3. Determines required permissions: `module.edit_permissions` array
4. Checks if user has ANY permission from the array
5. **Result:** ❌ **403 Forbidden** (user only has `employee.read`)

**Example:** User tries to view employees

```
GET /api/v1/employees
Headers: Authorization: Bearer {token}
```

**Middleware Logic:**
1. Identifies HTTP method: `GET` (read operation)
2. Looks up module: `employee`
3. Determines required permission: `module.read_permission`
4. Checks if user has `employee.read`
5. **Result:** ✅ **200 OK** (user can view)

### 3. HTTP Method Mapping

The middleware automatically maps HTTP methods to permission types:

| HTTP Method | Required Permission | Example Action |
|-------------|---------------------|----------------|
| **GET** | `read_permission` | View list, view details |
| **HEAD** | `read_permission` | Check resource existence |
| **POST** | `edit_permissions` (any) | Create new record |
| **PUT** | `edit_permissions` (any) | Update record (full) |
| **PATCH** | `edit_permissions` (any) | Update record (partial) |
| **DELETE** | `edit_permissions` (any) | Delete record |

---

## Implementation Guide

### Step 1: Use Middleware in Routes

Apply the `module.permission` middleware to your routes:

```php
// routes/api/employees.php
use App\Http\Controllers\Api\EmployeeController;

Route::middleware(['auth:sanctum', 'module.permission:employee'])->group(function () {
    Route::get('/employees', [EmployeeController::class, 'index']);
    Route::get('/employees/{id}', [EmployeeController::class, 'show']);
    Route::post('/employees', [EmployeeController::class, 'store']);
    Route::put('/employees/{id}', [EmployeeController::class, 'update']);
    Route::delete('/employees/{id}', [EmployeeController::class, 'destroy']);
});
```

**That's it!** The middleware handles everything automatically:
- GET requests require `employee.read`
- POST/PUT/PATCH/DELETE require any permission from `employee.edit_permissions`

### Step 2: Use Helper Methods in Controllers (Optional)

For additional checks within controller logic, use the `HasModulePermissions` trait:

```php
use App\Traits\HasModulePermissions;

class EmployeeController extends Controller
{
    use HasModulePermissions;

    protected string $moduleName = 'employee';

    public function index(Request $request)
    {
        // Additional check (optional, middleware already handles this)
        if (!$this->userCanReadModule()) {
            return $this->unauthorizedResponse('view');
        }

        $employees = Employee::paginate(15);
        return response()->json($employees);
    }

    public function exportSensitiveData(Request $request)
    {
        // Custom permission check for sensitive operations
        if (!$this->userHasFullAccess()) {
            return response()->json([
                'message' => 'Full access required for sensitive data export'
            ], 403);
        }

        // ... export logic
    }
}
```

### Step 3: Use Helper Methods in User Model

Check permissions programmatically:

```php
$user = auth()->user();

// Check specific module access
if ($user->canReadModule('employee')) {
    // User can view employees
}

if ($user->canEditModule('employee')) {
    // User can create/update/delete employees
}

// Get access level
$access = $user->getModuleAccess('employee');
// Returns: ['read' => true, 'edit' => false]

// Check access types
if ($user->hasReadOnlyAccess('employee')) {
    // User can only view, not edit
}

if ($user->hasFullAccess('leave_request')) {
    // User can both read and edit
}

// Get all accessible modules
$accessibleModules = $user->getAccessibleModules();
```

---

## API Endpoints

### Get User Permissions

**Endpoint:** `GET /api/v1/admin/user-permissions/{userId}`

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 15,
      "name": "John Doe",
      "email": "john@example.com",
      "roles": ["hr-assistant-junior"]
    },
    "modules": {
      "employee": {
        "read": true,
        "edit": false,
        "display_name": "Employee Management",
        "category": "HR",
        "icon": "users",
        "order": 5
      },
      "leave_request": {
        "read": true,
        "edit": true,
        "display_name": "Leave Requests",
        "category": "Leaves & Travel",
        "icon": "calendar",
        "order": 18
      }
    }
  }
}
```

### Update User Permissions

**Endpoint:** `PUT /api/v1/admin/user-permissions/{userId}`

**Request:**
```json
{
  "modules": {
    "employee": {
      "read": true,
      "edit": false
    },
    "leave_request": {
      "read": true,
      "edit": true
    },
    "grant": {
      "read": false,
      "edit": false
    }
  }
}
```

**Response:**
```json
{
  "success": true,
  "message": "User permissions updated successfully",
  "data": {
    "user": { ... },
    "permissions_count": 8
  }
}
```

### Get Permission Summary

**Endpoint:** `GET /api/v1/admin/user-permissions/{userId}/summary`

**Response:**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 15,
      "name": "John Doe",
      "email": "john@example.com"
    },
    "summary": {
      "total_modules": 25,
      "full_access": 5,
      "read_only": 10,
      "no_access": 10,
      "total_permissions": 47
    }
  }
}
```

---

## Frontend Integration

### 1. Fetch User Permissions

```javascript
const response = await axios.get(`/api/v1/admin/user-permissions/${userId}`);
const { modules } = response.data.data;

// modules = {
//   employee: { read: true, edit: false, display_name: "Employee Management", ... },
//   leave_request: { read: true, edit: true, display_name: "Leave Requests", ... }
// }
```

### 2. Render Permission Checkboxes

```vue
<template>
  <div v-for="(module, moduleName) in modules" :key="moduleName">
    <h3>{{ module.display_name }}</h3>

    <label>
      <input type="checkbox" v-model="module.read" />
      Read
    </label>

    <label>
      <input type="checkbox" v-model="module.edit" />
      Edit
    </label>
  </div>

  <button @click="savePermissions">Save Permissions</button>
</template>

<script>
export default {
  data() {
    return {
      userId: null,
      modules: {}
    };
  },
  methods: {
    async savePermissions() {
      await axios.put(`/api/v1/admin/user-permissions/${this.userId}`, {
        modules: this.modules
      });
      // Show success message
    }
  }
};
</script>
```

### 3. Check Permissions in Frontend

```javascript
// Store user permissions in Vuex/Pinia
const userPermissions = {
  employee: { read: true, edit: false },
  leave_request: { read: true, edit: true }
};

// Hide/disable buttons based on permissions
const canCreateEmployee = userPermissions.employee?.edit ?? false;
const canViewEmployee = userPermissions.employee?.read ?? false;

// In template
<button v-if="canCreateEmployee">Create Employee</button>
<router-link v-if="canViewEmployee" to="/employees">View Employees</router-link>
```

---

## Module Model Methods

### Permission Checking

```php
$module = Module::where('name', 'employee')->first();
$user = auth()->user();

// Check user access
$canRead = $module->userCanRead($user);
$canEdit = $module->userCanEdit($user);
$access = $module->getUserAccess($user); // ['read' => true, 'edit' => false]

// Get permissions
$allPermissions = $module->getAllPermissions();
// ['employee.read', 'employee.create', 'employee.update', ...]

$createPermission = $module->getPermissionForAction('create');
// 'employee.create'

$editActions = $module->getEditActions();
// ['create', 'update', 'delete', 'import', 'export', 'bulk_create']
```

### Scopes

```php
// Get modules by category
$hrModules = Module::byCategory('HR')->get();

// Get modules accessible by user
$accessibleModules = Module::accessibleBy($user)->get();

// Get active root modules
$rootModules = Module::active()->rootModules()->ordered()->get();
```

---

## User Model Methods

### Permission Checking

```php
$user = auth()->user();

// Basic checks
$user->canReadModule('employee');        // true
$user->canEditModule('employee');        // false
$user->hasModuleAccess('employee');      // true
$user->hasReadOnlyAccess('employee');    // true
$user->hasFullAccess('leave_request');   // true

// Get access info
$access = $user->getModuleAccess('employee');
// ['read' => true, 'edit' => false]

// Get all accessible modules
$modules = $user->getAccessibleModules();
// [
//   'employee' => ['read' => true, 'edit' => false, 'display_name' => 'Employee Management', ...],
//   'leave_request' => ['read' => true, 'edit' => true, 'display_name' => 'Leave Requests', ...]
// ]
```

---

## Controller Trait Methods

### HasModulePermissions Trait

```php
use App\Traits\HasModulePermissions;

class EmployeeController extends Controller
{
    use HasModulePermissions;

    protected string $moduleName = 'employee';

    public function index()
    {
        // Check permissions
        if (!$this->userCanReadModule()) {
            return $this->unauthorizedResponse('view');
        }

        // Or use authorization methods that abort automatically
        $this->authorizeRead(); // Aborts with 403 if unauthorized

        // ... controller logic
    }

    public function store(Request $request)
    {
        $this->authorizeEdit(); // Aborts with 403 if unauthorized

        // ... controller logic
    }

    // Available methods:
    // - userCanReadModule()
    // - userCanEditModule()
    // - userHasModuleAccess()
    // - userHasReadOnlyAccess()
    // - userHasFullAccess()
    // - getUserModuleAccess()
    // - getModule()
    // - unauthorizedResponse($action)
    // - authorizeRead()
    // - authorizeEdit()
}
```

---

## Middleware Usage

### DynamicModulePermission Middleware

**Alias:** `module.permission`

**Usage:**
```php
Route::middleware(['auth:sanctum', 'module.permission:employee'])->group(function () {
    // All routes protected
});

// Or single route
Route::get('/employees', [EmployeeController::class, 'index'])
    ->middleware(['auth:sanctum', 'module.permission:employee']);
```

**How It Works:**
1. Extracts module name parameter (e.g., `employee`)
2. Looks up module in database (with 24-hour cache)
3. Checks HTTP method:
   - `GET/HEAD` → Requires `read_permission`
   - `POST/PUT/PATCH/DELETE` → Requires any permission from `edit_permissions`
4. Checks if user has required permission(s)
5. Returns 403 with user-friendly message if unauthorized

**Error Responses:**
```json
// 401 Unauthenticated
{
  "success": false,
  "message": "Unauthenticated"
}

// 404 Module Not Found
{
  "success": false,
  "message": "Module 'employee' not found or inactive"
}

// 403 Forbidden - Read
{
  "success": false,
  "message": "You do not have permission to view Employee Management records",
  "required_permissions": ["employee.read"]
}

// 403 Forbidden - Edit
{
  "success": false,
  "message": "You do not have permission to create Employee Management records",
  "required_permissions": ["employee.create", "employee.update", "employee.delete", ...]
}
```

---

## Adding a New Module

### Step 1: Create Migration/Seeder

```php
// database/seeders/ModuleSeeder.php
Module::create([
    'name' => 'performance_review',
    'display_name' => 'Performance Reviews',
    'description' => 'Employee performance review management',
    'icon' => 'star',
    'category' => 'HR',
    'route' => '/performance-reviews',
    'read_permission' => 'performance_review.read',
    'edit_permissions' => [
        'performance_review.create',
        'performance_review.update',
        'performance_review.delete',
        'performance_review.import',
        'performance_review.export',
    ],
    'order' => 30,
    'is_active' => true,
]);
```

### Step 2: Create Permissions

```php
// In the same migration or seeder that creates roles
$permissions = [
    'performance_review.create',
    'performance_review.read',
    'performance_review.update',
    'performance_review.delete',
    'performance_review.import',
    'performance_review.export',
    'performance_review.bulk_create',
];

foreach ($permissions as $permission) {
    Permission::create(['name' => $permission]);
}

// Assign to roles
$adminRole = Role::findByName('admin');
$adminRole->givePermissionTo($permissions);
```

### Step 3: Protect Routes

```php
// routes/api/performance-reviews.php
Route::middleware(['auth:sanctum', 'module.permission:performance_review'])->group(function () {
    Route::get('/performance-reviews', [PerformanceReviewController::class, 'index']);
    Route::get('/performance-reviews/{id}', [PerformanceReviewController::class, 'show']);
    Route::post('/performance-reviews', [PerformanceReviewController::class, 'store']);
    Route::put('/performance-reviews/{id}', [PerformanceReviewController::class, 'update']);
    Route::delete('/performance-reviews/{id}', [PerformanceReviewController::class, 'destroy']);
});
```

**Done!** The new module is now protected with Read/Edit permissions.

---

## Best Practices

### 1. Always Use Middleware

✅ **Good:**
```php
Route::middleware(['auth:sanctum', 'module.permission:employee'])->group(function () {
    Route::apiResource('employees', EmployeeController::class);
});
```

❌ **Bad:**
```php
// No permission middleware - bypasses dynamic permissions
Route::middleware(['auth:sanctum'])->group(function () {
    Route::apiResource('employees', EmployeeController::class);
});
```

### 2. Keep Permission Names Consistent

All permissions for a module should follow the pattern: `{module_name}.{action}`

✅ **Good:**
- `employee.read`
- `employee.create`
- `employee.update`
- `employee.delete`

❌ **Bad:**
- `read_employee`
- `create-employee`
- `employee_update`

### 3. Grant Both Read and Edit for Full Access

When granting full access, always check **both** checkboxes:

✅ **Good:**
- Read: ☑ Checked
- Edit: ☑ Checked

❌ **Bad (Edit only):**
- Read: ☐ Unchecked
- Edit: ☑ Checked
- **Problem:** User can edit but not view (confusing UX)

### 4. Use Caching for Module Lookups

The middleware already caches modules for 24 hours. For custom lookups, use caching:

```php
use Illuminate\Support\Facades\Cache;

$module = Cache::remember(
    "module:employee",
    now()->addHours(24),
    fn() => Module::where('name', 'employee')->first()
);
```

### 5. Clear Cache After Module Changes

```php
// After creating/updating/deleting modules
Cache::forget("module:{$moduleName}");

// Or clear all module caches
Cache::tags(['modules'])->flush(); // If using tagged cache
```

---

## Troubleshooting

### User Can't Access Module Despite Having Permission

**Check:**
1. Is the module active? `Module::where('name', 'employee')->where('is_active', true)`
2. Does the permission exist? `Permission::where('name', 'employee.read')->exists()`
3. Does the user have the permission? `$user->can('employee.read')`
4. Is the module's `read_permission` field correct? It should match exactly

**Solution:**
```php
// Verify module configuration
$module = Module::where('name', 'employee')->first();
dd([
    'is_active' => $module->is_active,
    'read_permission' => $module->read_permission,
    'edit_permissions' => $module->edit_permissions,
]);

// Verify user permissions
$user = User::find(15);
dd($user->getAllPermissions()->pluck('name')->toArray());
```

### Module Not Found Error

**Error:** `Module 'employee' not found or inactive`

**Causes:**
- Module name doesn't match (check spelling)
- Module is inactive (`is_active = false`)
- Module doesn't exist in database

**Solution:**
```bash
# Run seeder
php artisan db:seed --class=ModuleSeeder

# Or create manually
php artisan tinker
>>> Module::create(['name' => 'employee', 'display_name' => 'Employees', 'read_permission' => 'employee.read', ...]);
```

### 403 Forbidden Despite Correct Permissions

**Check:**
1. Is Spatie permission cache stale?
2. Is the user authenticated?
3. Does the route have correct middleware?

**Solution:**
```bash
# Clear Spatie permission cache
php artisan cache:forget spatie.permission.cache
php artisan optimize:clear
```

---

## Security Considerations

### 1. Never Trust Frontend Checks Alone

Always enforce permissions on the backend. Frontend checks are for UX only.

✅ **Good:**
- Backend: Middleware + controller checks
- Frontend: Hide/disable buttons for better UX

❌ **Bad:**
- Backend: No checks
- Frontend: Only hide buttons (user can bypass with API calls)

### 2. Use Middleware for Route Protection

Apply `module.permission` middleware to **all** protected routes.

### 3. Validate Permission Changes

Only allow admins and HR managers to modify permissions:

```php
// In UserPermissionController
Route::put('/user-permissions/{userId}', [UserPermissionController::class, 'updateUserPermissions'])
    ->middleware(['auth:sanctum', 'permission:admin.update']);
```

### 4. Audit Permission Changes

Log all permission changes for security auditing:

```php
Log::info('User permissions updated', [
    'target_user_id' => $userId,
    'updated_by' => auth()->id(),
    'permissions_count' => count($permissions),
    'timestamp' => now(),
]);
```

---

## Testing

See `tests/Feature/DynamicModulePermissionTest.php` for complete test suite.

**Example Test:**
```php
test('user with read-only access cannot create records', function () {
    $user = User::factory()->create();

    // Grant only read permission
    $user->givePermissionTo('employee.read');

    $response = $this->actingAs($user)
        ->postJson('/api/v1/employees', [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);

    $response->assertForbidden();
    $response->assertJson([
        'success' => false,
        'message' => 'You do not have permission to create Employee Management records'
    ]);
});
```

---

## Summary

The dynamic module-based permission system provides:

✅ **Simple UI**: Two checkboxes (Read/Edit) per module
✅ **Dynamic Enforcement**: Middleware checks permissions based on HTTP methods
✅ **No Hardcoding**: All rules stored in database
✅ **Flexible**: Easy to add new modules
✅ **Secure**: Backend enforcement with frontend UX helpers
✅ **Scalable**: Cached for performance

**Key Components:**
- **Module Model**: Stores permission configuration
- **DynamicModulePermission Middleware**: Enforces permissions at runtime
- **UserPermissionController**: Manages user permissions via API
- **User Model Methods**: Helper methods for permission checks
- **HasModulePermissions Trait**: Reusable controller methods

**For Admins:** Use the Read/Edit checkboxes to configure user access
**For Developers:** Apply `module.permission` middleware to routes
**For Users:** Access is automatically enforced based on assigned permissions
