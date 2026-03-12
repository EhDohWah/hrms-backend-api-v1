# Permission System Research — Complete Deep Dive

> **Last Updated:** March 6, 2026
> **Scope:** Backend API permission/role system, user management, and frontend gaps

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [CRUD Permission Model](#2-crud-permission-model)
3. [The Three Layers of Permission Enforcement](#3-the-three-layers-of-permission-enforcement)
4. [Module System — The Foundation](#4-module-system--the-foundation)
5. [Permission Seeding Pipeline](#5-permission-seeding-pipeline)
6. [Role Architecture](#6-role-architecture)
7. [Permission Assignment Flow (How Users Get Permissions)](#7-permission-assignment-flow)
8. [API Endpoints — Complete Reference](#8-api-endpoints--complete-reference)
9. [Real-Time Permission Updates (WebSocket)](#9-real-time-permission-updates)
10. [User Management — Backend vs Frontend Gap Analysis](#10-user-management--backend-vs-frontend-gap)
11. [Code File Map](#11-code-file-map)
12. [Potential Issues & Edge Cases](#12-potential-issues--edge-cases)

---

## 1. Executive Summary

The HRMS uses a **standard CRUD permission model** built on Spatie Laravel-Permission v6. Each module has 4 granular permissions allowing fine-grained access control:

| Permission | What It Controls | HTTP Methods |
|---|---|---|
| `{module}.read` | View/list data | GET, HEAD |
| `{module}.create` | Create new records | POST |
| `{module}.update` | Update existing records | PUT, PATCH |
| `{module}.delete` | Delete records | DELETE |

There are **~42 modules**, producing **~168 total permissions** (42 x 4). Permissions are enforced at the route level via `DynamicModulePermission` middleware.

This granular model allows HR managers to give users fine-grained access — for example, allowing a user to create records but not delete them.

---

## 2. CRUD Permission Model

### Design Philosophy

The system uses industry-standard CRUD terminology (`create`, `read`, `update`, `delete`) for permissions. Each permission maps directly to HTTP methods, making the middleware enforcement straightforward and predictable.

### HTTP Method to Permission Mapping

| HTTP Method | Permission Required | Example |
|---|---|---|
| GET, HEAD | `{module}.read` | `employees.read` |
| POST | `{module}.create` | `employees.create` |
| PUT, PATCH | `{module}.update` | `employees.update` |
| DELETE | `{module}.delete` | `employees.delete` |

### Permission Independence

All 4 CRUD permissions are **independent**. Having one does not imply any of the others:

```php
// From SimplifiedPermissionSystemTest.php
it('all CRUD permissions are independent', function () {
    // Give only create permission
    $this->user->givePermissionTo("{$permissionPrefix}.create");

    // User should have create but NOT read, update, or delete
    expect($this->user->can("{$permissionPrefix}.create"))->toBeTrue()
        ->and($this->user->can("{$permissionPrefix}.read"))->toBeFalse()
        ->and($this->user->can("{$permissionPrefix}.update"))->toBeFalse()
        ->and($this->user->can("{$permissionPrefix}.delete"))->toBeFalse();
});
```

**Important:** The frontend should auto-check `read` when any write permission is checked (UI enforcement, not backend). The backend treats all 4 permissions as fully independent.

### Historical Context

The system originally used a simplified Read/Edit model with only 2 permissions per module:
- `{module}.read` — View/list data
- `{module}.edit` — All write operations (create, update, delete)

This was expanded to the current 4-permission CRUD model to enable more granular access control, particularly for HR managers who need to allow certain users to create records without being able to delete them.

A one-time conversion seeder (`ConvertPermissionsToReadEditSeeder`) exists for migrating from the old format but is no longer needed for fresh installations.

---

## 3. The Three Layers of Permission Enforcement

### Layer 1: Route-Level Middleware (`DynamicModulePermission`)

**File:** `app/Http/Middleware/DynamicModulePermission.php`

This is the **primary enforcement layer**. Applied to route groups:

```php
Route::prefix('admin')->middleware('module.permission:users')->group(function () {
    Route::get('/users', [AdminController::class, 'index']);       // needs users.read
    Route::post('/users', [AdminController::class, 'store']);      // needs users.create
    Route::put('/users/{user}', [AdminController::class, 'update']); // needs users.update
    Route::delete('/users/{user}', [AdminController::class, 'destroy']); // needs users.delete
});
```

**How it works:**

1. Receives the module name as parameter (e.g., `users`)
2. Looks up the `Module` record from DB (cached for 24 hours)
3. Determines required permission based on HTTP method:
   - `GET`/`HEAD` → checks `$module->read_permission` (e.g., `users.read`)
   - `POST` → checks `{module_name}.create` (e.g., `users.create`)
   - `PUT`/`PATCH` → checks `{module_name}.update` (e.g., `users.update`)
   - `DELETE` → checks `{module_name}.delete` (e.g., `users.delete`)
4. Calls `$user->can($permission)` (Spatie's method)
5. Returns 403 with descriptive message if denied

**Registration:**
```php
// bootstrap/app.php
$middleware->alias([
    'module.permission' => \App\Http\Middleware\DynamicModulePermission::class,
]);
```

**Module cache key:** `module:{moduleName}` — cached for 24 hours. Must clear cache (`php artisan cache:clear`) when modules change.

### Layer 2: Controller-Level Trait (`HasModulePermissions`)

**File:** `app/Traits/HasModulePermissions.php`

Optional trait for controllers needing inline permission checks:

```php
class EmployeeController extends Controller
{
    use HasModulePermissions;
    protected string $moduleName = 'employees';

    public function store(Request $request)
    {
        $this->authorizeCreate(); // throws 403 if user lacks employees.create
        // ...
    }

    public function update(Request $request, $id)
    {
        $this->authorizeUpdate(); // throws 403 if user lacks employees.update
        // ...
    }
}
```

Provides helper methods:
- `userCanReadModule()` / `userCanCreateModule()` / `userCanUpdateModule()` / `userCanDeleteModule()`
- `userHasReadOnlyAccess()` / `userHasFullAccess()`
- `authorizeRead()` / `authorizeCreate()` / `authorizeUpdate()` / `authorizeDelete()` — abort(403) variants
- `unauthorizedResponse($action)` — returns JsonResponse with descriptive message

### Layer 3: Model-Level Methods (User Model)

**File:** `app/Models/User.php`

The User model has custom helper methods for permission checks:

```php
$user->canReadModule('employees');      // bool
$user->canCreateModule('employees');    // bool
$user->canUpdateModule('employees');    // bool
$user->canDeleteModule('employees');    // bool
$user->getModuleAccess('employees');    // ['read' => true, 'create' => false, 'update' => true, 'delete' => false]
$user->hasModuleAccess('employees');    // bool (any access)
$user->hasReadOnlyAccess('employees'); // bool (read but no write permissions)
$user->hasFullAccess('employees');     // bool (all 4 CRUD permissions)
$user->getAccessibleModules();         // array of all modules with access levels
```

These methods accept either:
- Module name: `'employees'`
- Permission prefix: `'employees'` (same in this case since module name = permission prefix)

They look up the Module record by name, then check via `$this->can()` (Spatie).

---

## 4. Module System — The Foundation

### What Is a Module?

A module represents a **navigable page/feature** in the system. Modules are stored in the `modules` database table and serve two purposes:

1. **Permission mapping**: Each module defines which permissions control access to it
2. **Dynamic menu generation**: Frontend uses module data to build the sidebar

### Module Table Schema

**Migration:** `database/migrations/2025_12_18_115016_create_modules_table.php`

```
id              - bigint PK
name            - string, unique (e.g., 'employees', 'users', 'payslip')
display_name    - string (e.g., 'Employees', 'Users', 'Payslip')
description     - text, nullable
icon            - string, nullable (Tabler icon name, e.g., 'users')
category        - string, nullable (e.g., 'Employee', 'User Management', 'Payroll')
route           - string, nullable (frontend route path)
active_link     - string, nullable (menu highlighting)
parent_module   - string, nullable (FK to modules.name for hierarchy)
is_parent       - boolean, default false
read_permission - string (e.g., 'employees.read')
edit_permissions- JSON array (e.g., ['employees.create', 'employees.update', 'employees.delete'])
order           - integer (display order)
is_active       - boolean, default true
timestamps
soft_deletes
```

### Key Design: `edit_permissions` Is a JSON Array

Each module stores its 3 write permissions in the `edit_permissions` JSON array:
```json
["employees.create", "employees.update", "employees.delete"]
```

This field is a JSON array to allow flexibility. The Module model's `getEditActions()` method extracts the action names from this array.

### Module Categories (42 Modules Total)

| Category | Modules | Count |
|---|---|---|
| Dashboard | `dashboard` | 1 |
| Grants | `grants_list`, `grant_position` | 2 |
| Recruitment | `interviews`, `job_offers` | 2 |
| Employee | `employees`, `employment_records`, `employee_funding_allocations`, `employee_resignation` | 4 |
| HRM | `holidays`, `resignation`, `termination` | 3 |
| Leaves | `leaves_admin`, `leave_types`, `leave_balances` | 3 |
| Travel | `travel_admin` | 1 |
| Attendance | `attendance_admin`, `timesheets`, `shift_schedule`, `overtime` | 4 |
| Training | `training_list`, `employee_training` | 2 |
| Payroll | `employee_salary`, `tax_settings`, `benefit_settings`, `payslip`, `payroll_items` | 5 |
| Lookups | `lookup_list` | 1 |
| Organization Structure | `sites`, `departments`, `positions`, `section_departments` | 4 |
| User Management | `users`, `roles` | 2 |
| Reports | `report_list`, `expense_report`, `invoice_report`, `payment_report`, `project_report`, `task_report`, `user_report`, `employee_report`, `payslip_report`, `attendance_report`, `leave_report`, `daily_report` | 12 |
| Administration | `file_uploads`, `letter_templates` | 2 |
| Recycle Bin | `recycle_bin_list` | 1 |

**Note:** `attendance_employee` exists in `PermissionRoleSeeder` hardcoded fallback but NOT in `ModuleSeeder`. This is a minor inconsistency — permissions are created for it but no module record exists.

### Module Model Features

**File:** `app/Models/Module.php`

Key scopes:
- `Module::active()` — only `is_active = true`
- `Module::ordered()` — ordered by `order` column
- `Module::parentModules()` / `Module::submenus()` — hierarchy filtering
- `Module::accessibleBy($user)` — modules user has permission to access

Key methods:
- `$module->getAllPermissions()` — returns `[read_permission, ...edit_permissions]` (4 items)
- `$module->userCanRead($user)` / `$module->userCanWrite($user)` — permission checks
- `$module->getUserAccess($user)` — returns `['read' => bool, 'create' => bool, 'update' => bool, 'delete' => bool]`
- `$module->getPermissionForAction('create')` — maps CRUD action to permission name
- `$module->getEditActions()` — returns `['create', 'update', 'delete']`

---

## 5. Permission Seeding Pipeline

### Execution Order

The `DatabaseSeeder` runs seeders in this order:

1. **`ModuleSeeder`** — Creates/updates all 42 module records in `modules` table
2. **`PermissionRoleSeeder`** — Creates permission records and core roles
3. **`UserSeeder`** — Creates admin and HR manager users with their permissions

### Step 1: ModuleSeeder

**File:** `database/seeders/ModuleSeeder.php`

Uses `updateOrCreate` keyed on `name`, so it's safe to re-run. Creates all 42 module records with their `read_permission` and `edit_permissions` fields.

Example module entry:
```php
[
    'name' => 'employees',
    'display_name' => 'Employees',
    'read_permission' => 'employees.read',
    'edit_permissions' => ['employees.create', 'employees.update', 'employees.delete'],
    // ...
]
```

### Step 2: PermissionRoleSeeder

**File:** `database/seeders/PermissionRoleSeeder.php`

**How it determines module names:**

1. First, tries to read from the `modules` table (extracts prefix from `read_permission`)
2. If `modules` table is empty (first-time setup), falls back to a hardcoded list

**Permission creation:**
```php
$defaultActions = ['read', 'create', 'update', 'delete'];

foreach ($modules as $module => $actions) {
    foreach ($actions as $action) {
        Permission::firstOrCreate(['name' => "{$module}.{$action}"]);
    }
}
```

This creates exactly 4 permissions per module: `{module}.read`, `{module}.create`, `{module}.update`, `{module}.delete`.

**Role creation:**
Only creates two "core" protected roles:
```php
$coreRoles = [
    'admin' => 'System Administrator',
    'hr-manager' => 'HR Manager',
];
```

**Why only two roles?** Other roles (e.g., `hr-assistant`, `payroll-specialist`) are meant to be created dynamically through the Role Management UI. The seeder only bootstraps the minimum needed roles.

### Step 3: UserSeeder

**File:** `database/seeders/UserSeeder.php`

Creates two seed users:

**Admin (`admin@hrms.com`):**
- Role: `admin`
- Permissions: Administration-only modules (dashboard, lookups, org structure, user management, file uploads, recycle bin) — 40 permissions total (10 modules x 4 CRUD actions)

**HR Manager (`hrmanager@hrms.com`):**
- Role: `hr-manager`
- Permissions: **ALL** permissions (`Permission::all()`) — full access to every module

### The ConvertPermissionsToReadEditSeeder (Legacy)

**File:** `database/seeders/ConvertPermissionsToReadEditSeeder.php`

This is a **legacy manual-only** seeder (NOT in `DatabaseSeeder`). It was previously used to convert between permission models. For fresh installations with the current CRUD model, this seeder is not needed.

---

## 6. Role Architecture

### Core Roles (Protected)

| Role | Slug | Can Be Modified | Can Be Deleted |
|---|---|---|---|
| System Administrator | `admin` | No | No |
| HR Manager | `hr-manager` | No | No |

Protection is enforced in `RoleService`:
```php
private const PROTECTED_ROLES = ['admin', 'hr-manager'];
```

### How Roles Interact with Permissions

**Critical distinction:** In this system, roles are primarily used for **identity** (who is this user?) rather than for **permission inheritance** (what can they do?).

Permissions are assigned **directly to users** via `model_has_permissions` table, NOT through role-based permission inheritance via `role_has_permissions`.

This means:
- When a user is assigned the `admin` role via the Admin UI (`AdminService::create()`), the service **explicitly** calls `syncPermissionsForRole()` to give the user the correct direct permissions
- The role itself does NOT carry permissions via `role_has_permissions`
- Changing a user's role triggers re-syncing their direct permissions

**Why this matters:** If you query `$role->permissions`, it may return empty. The permissions live on the user directly, not on the role. The role is essentially a label.

**Exception:** The test file `SimplifiedPermissionSystemTest.php` syncs permissions TO roles for testing purposes, but production code assigns permissions directly to users.

### Dynamic Role Creation

New roles can be created via the Role Management API:

```
POST /api/v1/admin/roles  { "name": "payroll-specialist" }
```

**Validation rules:**
- Must be lowercase letters, numbers, and hyphens only (`/^[a-z0-9-]+$/`)
- Must be unique
- Cannot use protected role names (`admin`, `hr-manager`)
- Max 255 characters

### Display Name Mapping

Roles are stored as slugs but displayed with friendly names:

```php
// RoleService + RoleResource
'admin'                     → 'System Administrator'
'hr-manager'                → 'HR Manager'
'hr-assistant-senior'       → 'Senior HR Assistant'
'hr-assistant'              → 'HR Assistant'
'hr-assistant-junior-senior'→ 'Senior HR Junior Assistant'
'hr-assistant-junior'       → 'HR Junior Assistant'
default                     → ucwords(str_replace('-', ' ', $name))
```

**Note:** This mapping is duplicated in both `RoleService::getDisplayName()` and `RoleResource::getDisplayName()`. Any new role name mappings must be added in both places.

---

## 7. Permission Assignment Flow

### Flow 1: Creating a New User (via Admin UI)

**Endpoint:** `POST /api/v1/admin/users`
**Controller:** `AdminController::store()`
**Service:** `AdminService::create()`

```
1. Create User record
2. Assign role: $user->assignRole($data['role'])
3. Sync permissions based on role:
   - If role = 'admin':
     Auto-assign admin module permissions (dashboard, lookups, org structure, user mgmt, etc.)
     Each module gets all 4 CRUD permissions
   - If role = 'hr-manager':
     Auto-assign ALL module permissions (full access to everything)
   - If role = anything else:
     Use permissions from request data (if provided)
4. Return user with roles & permissions loaded
```

### Flow 2: Updating a User's Role (via Admin UI)

**Endpoint:** `PUT /api/v1/admin/users/{user}`
**Controller:** `AdminController::update()`
**Service:** `AdminService::update()`

```
1. Guard: non-admins cannot modify admin users
2. If role is changing:
   a. Detach ALL current roles
   b. Assign new role
   c. Re-sync permissions for new role (same logic as create)
3. If only permissions changing (no role change):
   a. Extract permissions from request (supports module format)
   b. Sync permissions directly
4. If password changing:
   a. Hash and save new password
5. Broadcast UserPermissionsUpdated event
6. Log the action
7. Return updated user
```

### Flow 3: Updating Permissions via Permission Manager

**Endpoint:** `PUT /api/v1/admin/user-permissions/{user}`
**Controller:** `UserPermissionController::updateUserPermissions()`
**Service:** `UserPermissionService::updatePermissions()`

This is the **dedicated permission editing endpoint** (separate from user update).

**Request format:**
```json
{
  "modules": {
    "employees": { "read": true, "create": true, "update": true, "delete": false },
    "payslip": { "read": true, "create": false, "update": false, "delete": false },
    "users": { "read": false, "create": false, "update": false, "delete": false }
  }
}
```

**Validation (`UpdateUserPermissionsRequest`):**
```php
'modules' => ['required', 'array'],
'modules.*.read' => ['required', 'boolean'],
'modules.*.create' => ['required', 'boolean'],
'modules.*.update' => ['required', 'boolean'],
'modules.*.delete' => ['required', 'boolean'],
```

**Processing:**
```
1. Guard: non-admins cannot modify admin user permissions
2. For each module in request:
   a. Look up Module record by name
   b. If module.read = true → add "{moduleName}.read" to list
   c. If module.create = true → add "{moduleName}.create" to list
   d. If module.update = true → add "{moduleName}.update" to list
   e. If module.delete = true → add "{moduleName}.delete" to list
3. syncPermissions() — replaces ALL user permissions with the new set
4. Broadcast UserPermissionsUpdated event
5. Log the change
```

**Key behavior: `syncPermissions()` replaces everything.** When updating via this endpoint, you must send the FULL desired state for all modules. Any module not included gets its permissions revoked.

### Flow 4: Frontend Self-Check (My Permissions)

**Endpoint:** `GET /api/v1/me/permissions`
**Controller:** `UserController::myPermissions()`
**Service:** `UserProfileService::myPermissions()`

Returns only modules the user has access to:

```json
{
  "success": true,
  "data": {
    "employees": {
      "read": true,
      "create": true,
      "update": true,
      "delete": false,
      "display_name": "Employees",
      "category": "Employee",
      "icon": "users",
      "route": "/employee/employee-list"
    },
    "dashboard": {
      "read": true,
      "create": false,
      "update": false,
      "delete": false,
      "display_name": "My Dashboard",
      "category": "Dashboard",
      "icon": "smart-home",
      "route": "/dashboard"
    }
  }
}
```

Modules with no access (all 4 flags false) are **excluded** from the response.

---

## 8. API Endpoints — Complete Reference

### Module Management (No Permission Middleware — Auth Only)

These endpoints are outside `module.permission` middleware to avoid circular dependency (can't check permission for the module that tells you what permissions exist):

| Method | Path | Controller | Description |
|---|---|---|---|
| GET | `/api/v1/admin/modules` | `ModuleController::index` | List all active modules (paginated) |
| GET | `/api/v1/admin/modules/hierarchical` | `ModuleController::hierarchical` | Modules in tree structure |
| GET | `/api/v1/admin/modules/by-category` | `ModuleController::byCategory` | Modules grouped by category |
| GET | `/api/v1/admin/modules/permissions` | `ModuleController::permissions` | All unique permissions from modules |
| GET | `/api/v1/admin/modules/{module}` | `ModuleController::show` | Single module details |

### User Management (requires `users` module permissions)

| Method | Path | Controller | Permission Required |
|---|---|---|---|
| GET | `/api/v1/admin/users` | `AdminController::index` | `users.read` |
| GET | `/api/v1/admin/users/{user}` | `AdminController::show` | `users.read` |
| POST | `/api/v1/admin/users` | `AdminController::store` | `users.create` |
| PUT | `/api/v1/admin/users/{user}` | `AdminController::update` | `users.update` |
| DELETE | `/api/v1/admin/users/{user}` | `AdminController::destroy` | `users.delete` |
| GET | `/api/v1/admin/all-roles` | `AdminController::roles` | `users.read` |
| GET | `/api/v1/admin/permissions` | `AdminController::permissions` | `users.read` |

### Role Management (requires `roles` module permissions)

| Method | Path | Controller | Permission Required |
|---|---|---|---|
| GET | `/api/v1/admin/roles` | `RoleController::index` | `roles.read` |
| GET | `/api/v1/admin/roles/options` | `RoleController::options` | `roles.read` |
| GET | `/api/v1/admin/roles/{role}` | `RoleController::show` | `roles.read` |
| POST | `/api/v1/admin/roles` | `RoleController::store` | `roles.create` |
| PUT | `/api/v1/admin/roles/{role}` | `RoleController::update` | `roles.update` |
| DELETE | `/api/v1/admin/roles/{role}` | `RoleController::destroy` | `roles.delete` |

### User Permission Management (requires `users` module permissions)

| Method | Path | Controller | Permission Required |
|---|---|---|---|
| GET | `/api/v1/admin/user-permissions/{user}` | `UserPermissionController::show` | `users.read` |
| PUT | `/api/v1/admin/user-permissions/{user}` | `UserPermissionController::updateUserPermissions` | `users.update` |
| GET | `/api/v1/admin/user-permissions/{user}/summary` | `UserPermissionController::summary` | `users.read` |

### Self-Service (Auth only, no module permission)

| Method | Path | Controller | Description |
|---|---|---|---|
| GET | `/api/v1/user` | `UserController::me` | Get authenticated user profile |
| GET | `/api/v1/me/permissions` | `UserController::myPermissions` | Get own permissions for menu building |

---

## 9. Real-Time Permission Updates

### Event: `UserPermissionsUpdated`

**File:** `app/Events/UserPermissionsUpdated.php`

When an admin updates a user's permissions, a WebSocket event is broadcast to notify the affected user in real-time.

**Broadcast details:**
- **Implements:** `ShouldBroadcastNow` (synchronous, no queue)
- **Channel:** `private-App.Models.User.{userId}` (private channel per user)
- **Event name:** `user.permissions-updated`
- **Payload:**
  ```json
  {
    "user_id": 123,
    "updated_by": "System Administrator",
    "updated_at": "2026-03-06T10:30:00+00:00",
    "reason": "Permissions updated via permission manager",
    "message": "Your permissions have been updated. Please wait while we refresh your access."
  }
  ```

**Design:** The event sends a lightweight signal. The frontend should then call `GET /api/v1/me/permissions` to fetch the full updated permissions.

**Triggered from:**
1. `UserPermissionService::updatePermissions()` — permission manager
2. `AdminService::update()` — user update endpoint

**Testing command:**
```bash
php artisan test:permission-broadcast {userId}
```

---

## 10. User Management — Backend vs Frontend Gap

### What the Backend Provides (Fully Implemented)

The backend has a **complete** user management system:

#### User CRUD (`AdminController` + `AdminService`)
- **List users** with search (name/email), role filter, status filter, sorting, pagination
- **Create user** with name, email, password, role, profile picture
- **View user** with roles and all permissions
- **Update user** role, permissions, and/or password
- **Delete user** with role/permission cleanup

#### Role Management (`RoleController` + `RoleService`)
- **List roles** ordered by name
- **Role options** for dropdown selection (with display names and protected flags)
- **Create role** with validation (lowercase-hyphen format, unique, not protected names)
- **Update role** name (protected roles blocked)
- **Delete role** with safety check (cannot delete if users assigned)

#### Permission Management (`UserPermissionController` + `UserPermissionService`)
- **View user permissions** grouped by module with CRUD flags and metadata
- **Update user permissions** via module-based CRUD checkboxes (read, create, update, delete)
- **Permission summary** showing total modules, full access count, partial access count, read-only count, no access count

#### Module Management (`ModuleController` + `ModuleService`)
- **List modules** paginated with filters
- **Hierarchical view** (tree structure)
- **By category** (grouped)
- **All permissions** from all modules

#### Auto-Permission Assignment on Role Change
When assigning a role, the system auto-assigns appropriate permissions:
- `admin` role → admin-only modules (dashboard, org structure, user management, lookups, etc.) with all 4 CRUD permissions
- `hr-manager` role → ALL module permissions (full access)
- Any other role → permissions from request payload (or empty)

### What the Frontend Is Missing

The frontend does NOT currently have pages/components for:

1. **User List Page** — A page to view all system users with search, filter by role, filter by status
2. **Create User Modal/Form** — Form to create new users with name, email, password, role assignment, profile picture upload
3. **Edit User Modal/Form** — Form to update user role, reset password, change status
4. **Delete User** — Confirmation dialog and deletion
5. **Role Management Page** — A page to list, create, edit, delete roles
6. **Permission Assignment UI** — The CRUD checkbox grid for assigning per-module permissions to a user

### Frontend Implementation Requirements

Based on the backend API, the frontend needs:

#### User Management Page (`/user-management/users`)

**API calls needed:**
```javascript
// List users
GET /api/v1/admin/users?search=&role=&status=&sort_by=created_at&sort_order=desc&per_page=15

// Role options for dropdown
GET /api/v1/admin/roles/options

// Create user
POST /api/v1/admin/users  (multipart/form-data for profile picture)

// View user details
GET /api/v1/admin/users/{id}

// Update user
PUT /api/v1/admin/users/{id}

// Delete user
DELETE /api/v1/admin/users/{id}
```

#### Role Management Page (`/user-management/roles`)

**API calls needed:**
```javascript
// List roles
GET /api/v1/admin/roles

// Create role
POST /api/v1/admin/roles  { "name": "payroll-specialist" }

// Update role
PUT /api/v1/admin/roles/{id}  { "name": "payroll-lead" }

// Delete role
DELETE /api/v1/admin/roles/{id}

// Show role with user count
GET /api/v1/admin/roles/{id}
```

#### Permission Assignment UI (within User Edit)

**API calls needed:**
```javascript
// Get user's current permissions
GET /api/v1/admin/user-permissions/{userId}

// Get all modules grouped by category (for building the checkbox grid)
GET /api/v1/admin/modules/by-category

// Update permissions
PUT /api/v1/admin/user-permissions/{userId}
{
  "modules": {
    "employees": { "read": true, "create": true, "update": true, "delete": false },
    "payslip": { "read": true, "create": false, "update": false, "delete": false },
    "users": { "read": false, "create": false, "update": false, "delete": false }
  }
}

// Get summary
GET /api/v1/admin/user-permissions/{userId}/summary
```

**Permission response shape (for user):**
```json
{
  "user": {
    "id": 2,
    "name": "HR Manager",
    "email": "hrmanager@hrms.com",
    "roles": ["hr-manager"]
  },
  "modules": {
    "dashboard": {
      "read": true,
      "create": true,
      "update": true,
      "delete": true,
      "display_name": "My Dashboard",
      "category": "Dashboard",
      "icon": "smart-home",
      "order": 1
    },
    "employees": {
      "read": true,
      "create": true,
      "update": true,
      "delete": false,
      "display_name": "Employees",
      "category": "Employee",
      "icon": "users",
      "order": 30
    }
  }
}
```

#### WebSocket Integration

The frontend should listen for real-time permission updates:

```javascript
// Using Laravel Echo (Reverb)
Echo.private(`App.Models.User.${userId}`)
    .listen('.user.permissions-updated', (event) => {
        // event.user_id, event.updated_by, event.message
        // Show notification to user
        // Re-fetch permissions: GET /api/v1/me/permissions
        // Update sidebar menu visibility
    });
```

---

## 11. Code File Map

### Core Permission Files

| File | Purpose |
|---|---|
| `app/Http/Middleware/DynamicModulePermission.php` | Route-level permission enforcement |
| `app/Models/Module.php` | Module model (permission mapping, menu data) |
| `app/Models/User.php` | User model with `HasRoles` trait + custom permission helpers |
| `app/Traits/HasModulePermissions.php` | Controller trait for inline permission checks |
| `app/Events/UserPermissionsUpdated.php` | WebSocket event for real-time permission sync |

### Controllers

| File | Purpose |
|---|---|
| `app/Http/Controllers/Api/V1/AdminController.php` | User CRUD + legacy role/permission listing |
| `app/Http/Controllers/Api/V1/RoleController.php` | Role CRUD |
| `app/Http/Controllers/Api/V1/UserPermissionController.php` | Per-user permission management |
| `app/Http/Controllers/Api/V1/ModuleController.php` | Module listing (read-only) |
| `app/Http/Controllers/Api/V1/UserController.php` | Self-service profile + my permissions |

### Services

| File | Purpose |
|---|---|
| `app/Services/AdminService.php` | User CRUD logic, role-based permission auto-assignment |
| `app/Services/RoleService.php` | Role CRUD logic, protected role guard |
| `app/Services/UserPermissionService.php` | Permission show/update/summary logic |
| `app/Services/UserProfileService.php` | `myPermissions()` for self-service |

### Form Requests

| File | Purpose |
|---|---|
| `app/Http/Requests/StoreRoleRequest.php` | Validates role creation (lowercase-hyphen, unique, not protected) |
| `app/Http/Requests/UpdateRoleRequest.php` | Validates role update (same rules, unique ignoring self) |
| `app/Http/Requests/UserPermission/UpdateUserPermissionsRequest.php` | Validates module permission assignment (CRUD booleans) |
| `app/Http/Requests/StoreUserRequest.php` | Validates user creation |
| `app/Http/Requests/UpdateUserRequest.php` | Validates user update |

### Resources

| File | Purpose |
|---|---|
| `app/Http/Resources/RoleResource.php` | Role JSON transformation (includes display_name, is_protected) |
| `app/Http/Resources/PermissionResource.php` | Permission JSON transformation |
| `app/Http/Resources/UserResource.php` | User JSON transformation (includes roles, permissions arrays) |

### Seeders

| File | Purpose |
|---|---|
| `database/seeders/ModuleSeeder.php` | Seeds 42 module records with CRUD edit_permissions |
| `database/seeders/PermissionRoleSeeder.php` | Creates CRUD permissions (read, create, update, delete) + core roles |
| `database/seeders/UserSeeder.php` | Creates admin + HR manager seed users with CRUD permissions |
| `database/seeders/ConvertPermissionsToReadEditSeeder.php` | Legacy: one-time migration from old format |

### Routes

| File | Key Groups |
|---|---|
| `routes/api/admin.php` | User mgmt, role mgmt, permission mgmt, module mgmt, lookups |
| `routes/api/user.php` | Self-service: `/user`, `/me/permissions` |

### Tests

| File | Tests |
|---|---|
| `tests/Feature/Api/SimplifiedPermissionSystemTest.php` | Permission structure, CRUD assignment, middleware, cache, model helpers |
| `tests/Feature/Api/RoleApiTest.php` | Role CRUD, auth, validation |
| `tests/Feature/DynamicModulePermissionTest.php` | Middleware CRUD behavior, User model helpers, Module model helpers |
| `tests/Feature/InterviewPermissionTest.php` | Interview-specific permission checks |
| `tests/Unit/PermissionConversionServiceTest.php` | Legacy conversion logic |

---

## 12. Potential Issues & Edge Cases

### 1. `attendance_employee` Module Mismatch

`PermissionRoleSeeder` hardcoded fallback includes `attendance_employee`, but `ModuleSeeder` does NOT define this module. This means:
- Permissions `attendance_employee.read`, `attendance_employee.create`, `attendance_employee.update`, `attendance_employee.delete` exist in the `permissions` table
- But no `Module` record exists for `attendance_employee`
- The `DynamicModulePermission` middleware would return 404 if a route used `module.permission:attendance_employee`

### 2. Role Display Name Duplication

`RoleService::getDisplayName()` and `RoleResource::getDisplayName()` contain the same hardcoded mapping. Adding a new role name mapping requires updating both files.

### 3. Permission Cache TTL

Module lookups in `DynamicModulePermission` are cached for **24 hours**. If a module's `is_active` status changes or permissions are modified, you must run `php artisan cache:clear`. There is no automatic cache invalidation when modules are updated.

### 4. Admin Permission Drift

When a new module is added to `ModuleSeeder`, the admin user does NOT automatically get access to it. The `AdminService::getAdminModulePermissions()` has a hardcoded list of admin modules. If you add a new admin-facing module, you must also update this list.

Similarly, `UserSeeder::run()` has its own hardcoded admin module list.

### 5. `syncPermissions()` Is Destructive

Both `UserPermissionService::updatePermissions()` and `AdminService::syncPermissionsForRole()` use `$user->syncPermissions()` which **replaces ALL direct permissions**. If a user has permissions from a different source (e.g., directly assigned via tinker), those will be wiped when the admin UI updates them.

### 6. No Permission-to-Role Assignment

The current system assigns permissions directly to users, not to roles. The `role_has_permissions` table is essentially unused in production. This means:
- You cannot set "all payroll-specialists get X permissions" at the role level
- Each user's permissions must be managed individually
- Bulk permission changes require updating each user separately

### 7. Guard Name

All permissions and roles use guard `web` (Spatie's default). Sanctum authentication uses the `sanctum` guard for API auth, but Spatie permissions are registered under `web`. This works because Spatie falls back to the default guard when checking `$user->can()`.

### 8. Write Without Read

The system allows assigning write permissions (create, update, delete) without `{module}.read`. The middleware would allow POST/PUT/DELETE but block GET on the same module. The frontend should enforce that checking any write permission also checks "Read" (implying read), but this is a UI concern — the backend treats all 4 permissions as independent.

---

## Appendix A: Spatie Tables Used

| Table | Purpose | Key Columns |
|---|---|---|
| `roles` | Stores role definitions | id, name, guard_name |
| `permissions` | Stores permission definitions | id, name, guard_name |
| `model_has_roles` | User-to-role assignments | model_type, model_id, role_id |
| `model_has_permissions` | User-to-permission direct assignments | model_type, model_id, permission_id |
| `role_has_permissions` | Role-to-permission assignments (largely unused) | role_id, permission_id |

## Appendix B: Complete Permission List (168 Permissions)

```
dashboard.read              dashboard.create              dashboard.update              dashboard.delete
grants_list.read            grants_list.create            grants_list.update            grants_list.delete
grant_position.read         grant_position.create         grant_position.update         grant_position.delete
interviews.read             interviews.create             interviews.update             interviews.delete
job_offers.read             job_offers.create             job_offers.update             job_offers.delete
employees.read              employees.create              employees.update              employees.delete
employment_records.read     employment_records.create     employment_records.update     employment_records.delete
employee_funding_allocations.read  employee_funding_allocations.create  employee_funding_allocations.update  employee_funding_allocations.delete
employee_resignation.read   employee_resignation.create   employee_resignation.update   employee_resignation.delete
holidays.read               holidays.create               holidays.update               holidays.delete
resignation.read            resignation.create            resignation.update            resignation.delete
termination.read            termination.create            termination.update            termination.delete
leaves_admin.read           leaves_admin.create           leaves_admin.update           leaves_admin.delete
leave_types.read            leave_types.create            leave_types.update            leave_types.delete
leave_balances.read         leave_balances.create         leave_balances.update         leave_balances.delete
travel_admin.read           travel_admin.create           travel_admin.update           travel_admin.delete
attendance_admin.read       attendance_admin.create       attendance_admin.update       attendance_admin.delete
attendance_employee.read    attendance_employee.create    attendance_employee.update    attendance_employee.delete  (*)
timesheets.read             timesheets.create             timesheets.update             timesheets.delete
shift_schedule.read         shift_schedule.create         shift_schedule.update         shift_schedule.delete
overtime.read               overtime.create               overtime.update               overtime.delete
training_list.read          training_list.create          training_list.update          training_list.delete
employee_training.read      employee_training.create      employee_training.update      employee_training.delete
employee_salary.read        employee_salary.create        employee_salary.update        employee_salary.delete
tax_settings.read           tax_settings.create           tax_settings.update           tax_settings.delete
benefit_settings.read       benefit_settings.create       benefit_settings.update       benefit_settings.delete
payslip.read                payslip.create                payslip.update                payslip.delete
payroll_items.read          payroll_items.create          payroll_items.update          payroll_items.delete
lookup_list.read            lookup_list.create            lookup_list.update            lookup_list.delete
sites.read                  sites.create                  sites.update                  sites.delete
departments.read            departments.create            departments.update            departments.delete
positions.read              positions.create              positions.update              positions.delete
section_departments.read    section_departments.create    section_departments.update    section_departments.delete
users.read                  users.create                  users.update                  users.delete
roles.read                  roles.create                  roles.update                  roles.delete
report_list.read            report_list.create            report_list.update            report_list.delete
expense_report.read         expense_report.create         expense_report.update         expense_report.delete
invoice_report.read         invoice_report.create         invoice_report.update         invoice_report.delete
payment_report.read         payment_report.create         payment_report.update         payment_report.delete
project_report.read         project_report.create         project_report.update         project_report.delete
task_report.read            task_report.create            task_report.update            task_report.delete
user_report.read            user_report.create            user_report.update            user_report.delete
employee_report.read        employee_report.create        employee_report.update        employee_report.delete
payslip_report.read         payslip_report.create         payslip_report.update         payslip_report.delete
attendance_report.read      attendance_report.create      attendance_report.update      attendance_report.delete
leave_report.read           leave_report.create           leave_report.update           leave_report.delete
daily_report.read           daily_report.create           daily_report.update           daily_report.delete
file_uploads.read           file_uploads.create           file_uploads.update           file_uploads.delete
letter_templates.read       letter_templates.create       letter_templates.update       letter_templates.delete
recycle_bin_list.read       recycle_bin_list.create       recycle_bin_list.update       recycle_bin_list.delete
```

(*) `attendance_employee` permissions exist in DB but have no corresponding Module record — see Issue #1 above.
