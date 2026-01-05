# Permission System Architecture

> **Last Updated:** December 22, 2025  
> **Version:** 1.0  
> **Author:** AI Assistant

## Table of Contents

1. [Overview](#overview)
2. [Architecture Diagram](#architecture-diagram)
3. [Permission Model: Read/Edit](#permission-model-readedit)
4. [Technology Stack](#technology-stack)
5. [Database Structure](#database-structure)
6. [Backend Implementation](#backend-implementation)
7. [Frontend Implementation](#frontend-implementation)
8. [Adding New Modules](#adding-new-modules)
9. [API Reference](#api-reference)
10. [Troubleshooting](#troubleshooting)

---

## Overview

This HRMS uses a **simplified Read/Edit permission model** built on top of **Spatie Laravel-Permission**. Instead of granular CRUD permissions (create, read, update, delete), we use only two permission levels per module:

| Permission | Allows | HTTP Methods |
|------------|--------|--------------|
| `{module}.read` | View data | GET, HEAD |
| `{module}.edit` | Full CRUD | POST, PUT, PATCH, DELETE |

### Why This Approach?

1. **Simplicity** - Easier for HR managers to understand and assign
2. **Fewer Errors** - Less chance of misconfiguration
3. **Faster Setup** - New users can be configured in seconds
4. **Maintainable** - Simple to audit and troubleshoot

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           FRONTEND (Vue.js)                              │
├─────────────────────────────────────────────────────────────────────────┤
│  User Management Page                                                    │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  Module Permissions                                              │    │
│  │  ┌──────────────────┬──────────┬──────────┐                     │    │
│  │  │ Module           │ Read     │ Edit     │                     │    │
│  │  ├──────────────────┼──────────┼──────────┤                     │    │
│  │  │ Employee         │ [✓]      │ [✓]      │                     │    │
│  │  │ Leave            │ [✓]      │ [ ]      │                     │    │
│  │  │ Payroll          │ [ ]      │ [ ]      │                     │    │
│  │  │ User Management  │ [✓]      │ [✓]      │                     │    │
│  │  └──────────────────┴──────────┴──────────┘                     │    │
│  └─────────────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼ API Calls
┌─────────────────────────────────────────────────────────────────────────┐
│                           BACKEND (Laravel 11)                           │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                          │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  Route Definition                                                │    │
│  │  Route::get('/employees', [EmployeeController::class, 'index']) │    │
│  │      ->middleware('module.permission:employee');                 │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                    │                                     │
│                                    ▼                                     │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  DynamicModulePermission Middleware                              │    │
│  │  ┌─────────────────────────────────────────────────────────┐    │    │
│  │  │  1. Get HTTP method (GET, POST, PUT, DELETE)            │    │    │
│  │  │  2. Determine required permission:                       │    │    │
│  │  │     - GET/HEAD → {module}.read                          │    │    │
│  │  │     - POST/PUT/PATCH/DELETE → {module}.edit             │    │    │
│  │  │  3. Check: $user->can($permission)                      │    │    │
│  │  │  4. Allow or return 403                                  │    │    │
│  │  └─────────────────────────────────────────────────────────┘    │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                    │                                     │
│                                    ▼                                     │
│  ┌─────────────────────────────────────────────────────────────────┐    │
│  │  Spatie Permission (Storage Layer)                               │    │
│  │  ┌─────────────────────────────────────────────────────────┐    │    │
│  │  │  Tables:                                                 │    │    │
│  │  │  - roles (admin, hr-manager, employee)                  │    │    │
│  │  │  - permissions (employee.read, employee.edit, ...)      │    │    │
│  │  │  - model_has_roles (user ↔ role)                        │    │    │
│  │  │  - model_has_permissions (user ↔ permission)            │    │    │
│  │  │  - role_has_permissions (role ↔ permission)             │    │    │
│  │  └─────────────────────────────────────────────────────────┘    │    │
│  └─────────────────────────────────────────────────────────────────┘    │
│                                                                          │
└─────────────────────────────────────────────────────────────────────────┘
```

---

## Permission Model: Read/Edit

### Permission Naming Convention

```
{module_name}.{permission_type}
```

Examples:
- `employee.read` - Can view employees
- `employee.edit` - Can create, update, delete employees
- `user_management.read` - Can view users
- `user_management.edit` - Can manage users
- `payroll.read` - Can view payroll data
- `payroll.edit` - Can process payroll

### Permission Hierarchy

```
Edit permission implies Read permission
┌─────────────────────────────────────┐
│  {module}.edit                      │
│  ┌─────────────────────────────┐   │
│  │  {module}.read              │   │
│  │  • GET /module              │   │
│  │  • GET /module/:id          │   │
│  └─────────────────────────────┘   │
│  • POST /module                     │
│  • PUT /module/:id                  │
│  • DELETE /module/:id               │
└─────────────────────────────────────┘
```

---

## Technology Stack

| Component | Technology | Purpose |
|-----------|------------|---------|
| Permission Storage | Spatie Laravel-Permission v6 | Stores roles, permissions, and relationships |
| Permission Logic | DynamicModulePermission Middleware | Implements Read/Edit business logic |
| User Model | Laravel + HasRoles Trait | Provides `$user->can()`, `assignRole()` methods |
| Module Registry | `modules` Database Table | Defines available modules and their permissions |
| Caching | Laravel Cache | Caches module data for 24 hours |
| API Auth | Laravel Sanctum | Token-based API authentication |

---

## Database Structure

### Core Tables

#### `modules` Table
Defines all system modules and their permissions.

```sql
CREATE TABLE modules (
    id BIGINT PRIMARY KEY,
    name VARCHAR(255),           -- e.g., 'employee', 'user_management'
    display_name VARCHAR(255),   -- e.g., 'Employee', 'User Management'
    description TEXT,
    icon VARCHAR(100),
    category VARCHAR(100),       -- e.g., 'HRM', 'Administration'
    sort_order INT,
    is_active BOOLEAN,
    read_permission VARCHAR(255), -- e.g., 'employee.read'
    edit_permission VARCHAR(255), -- e.g., 'employee.edit'
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

#### Spatie Permission Tables

```sql
-- roles: Stores role definitions
-- Example: admin, hr-manager, employee

-- permissions: Stores all permissions
-- Example: employee.read, employee.edit, user_management.read

-- model_has_roles: Links users to roles
-- user_id → role_id

-- model_has_permissions: Direct user permissions (optional)
-- user_id → permission_id

-- role_has_permissions: Links roles to permissions
-- role_id → permission_id
```

### Entity Relationship Diagram

```
┌──────────────┐       ┌──────────────────┐       ┌──────────────────┐
│    users     │       │  model_has_roles │       │      roles       │
├──────────────┤       ├──────────────────┤       ├──────────────────┤
│ id           │◄──────│ model_id         │       │ id               │
│ name         │       │ model_type       │       │ name             │
│ email        │       │ role_id          │──────►│ guard_name       │
│ password     │       └──────────────────┘       └──────────────────┘
│ status       │                                          │
└──────────────┘                                          │
                                                          ▼
┌──────────────┐       ┌──────────────────┐       ┌──────────────────┐
│   modules    │       │role_has_permissions     │   permissions    │
├──────────────┤       ├──────────────────┤       ├──────────────────┤
│ id           │       │ role_id          │──────►│ id               │
│ name         │       │ permission_id    │◄──────│ name             │
│ display_name │       └──────────────────┘       │ guard_name       │
│ read_permission      │                          └──────────────────┘
│ edit_permission      │
└──────────────┘
```

---

## Backend Implementation

### Key Files

| File | Purpose |
|------|---------|
| `app/Http/Middleware/DynamicModulePermission.php` | Core permission checking logic |
| `app/Models/Module.php` | Module model with permission attributes |
| `app/Models/User.php` | User model with `HasRoles` trait |
| `database/seeders/ModuleSeeder.php` | Seeds all system modules |
| `database/seeders/PermissionRoleSeeder.php` | Seeds permissions and roles |
| `routes/api/admin.php` | Admin API routes with middleware |

### DynamicModulePermission Middleware

```php
// app/Http/Middleware/DynamicModulePermission.php

public function handle(Request $request, Closure $next, string $moduleName): Response
{
    $user = $request->user();

    // Get module from cache or database
    $module = Cache::remember(
        "module:{$moduleName}",
        now()->addHours(24),
        fn () => Module::where('name', $moduleName)->where('is_active', true)->first()
    );

    // Determine required permission based on HTTP method
    $method = $request->method();
    $requiredPermissions = $this->getRequiredPermissions($module, $method);

    // Check if user has permission
    foreach ($requiredPermissions as $permission) {
        if ($user->can($permission)) {
            return $next($request);
        }
    }

    return response()->json([
        'success' => false,
        'message' => 'Permission denied',
    ], 403);
}

protected function getRequiredPermissions(Module $module, string $method): array
{
    return match ($method) {
        'GET', 'HEAD' => [$module->read_permission],
        'POST', 'PUT', 'PATCH', 'DELETE' => ["{$module->name}.edit"],
        default => [],
    };
}
```

### Route Definition Example

```php
// routes/api/admin.php

Route::middleware(['auth:sanctum'])->group(function () {
    
    // Employee routes - protected by module permission
    Route::prefix('employees')
        ->middleware('module.permission:employee')
        ->group(function () {
            Route::get('/', [EmployeeController::class, 'index']);      // Requires employee.read
            Route::get('/{id}', [EmployeeController::class, 'show']);   // Requires employee.read
            Route::post('/', [EmployeeController::class, 'store']);     // Requires employee.edit
            Route::put('/{id}', [EmployeeController::class, 'update']); // Requires employee.edit
            Route::delete('/{id}', [EmployeeController::class, 'destroy']); // Requires employee.edit
        });
    
    // User Management routes
    Route::prefix('admin/users')
        ->middleware('module.permission:user_management')
        ->group(function () {
            Route::get('/', [UserController::class, 'index']);
            Route::post('/', [UserController::class, 'store']);
            // ...
        });
});
```

### Registering Middleware

```php
// bootstrap/app.php

->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'module.permission' => \App\Http\Middleware\DynamicModulePermission::class,
    ]);
})
```

---

## Frontend Implementation

### Key Files

| File | Purpose |
|------|---------|
| `src/components/modal/user-list-modal.vue` | User create/edit with permission assignment |
| `src/services/role.service.js` | API calls for roles |
| `src/services/permission.service.js` | API calls for permissions |
| `src/config/api.config.js` | API endpoint definitions |

### Permission UI Component

```vue
<!-- Module Permissions section in user-list-modal.vue -->
<template>
  <div class="module-permissions">
    <h6>Module Permissions</h6>
    
    <!-- Group by category -->
    <div v-for="category in moduleCategories" :key="category.name">
      <button @click="toggleCategory(category)">
        {{ category.name }} ({{ category.modules.length }})
      </button>
      
      <div v-if="category.expanded">
        <div v-for="module in category.modules" :key="module.id" class="module-row">
          <span>{{ module.display_name }}</span>
          
          <!-- Read toggle -->
          <input 
            type="checkbox" 
            :checked="hasPermission(module.read_permission)"
            @change="togglePermission(module.read_permission)"
          />
          
          <!-- Edit toggle -->
          <input 
            type="checkbox" 
            :checked="hasPermission(module.edit_permission)"
            @change="togglePermission(module.edit_permission)"
          />
        </div>
      </div>
    </div>
  </div>
</template>
```

### API Service

```javascript
// src/services/permission.service.js

export const permissionService = {
  // Get all module permissions for admin UI
  async getAllModulePermissions() {
    return await apiService.get('/admin/module-permissions');
  },
  
  // Update user permissions
  async updateUserPermissions(userId, permissions) {
    return await apiService.put(`/admin/user-permissions/${userId}`, { permissions });
  },
  
  // Get current user's permissions (for menu building)
  async getMyPermissions() {
    return await apiService.get('/me/permissions');
  }
};
```

---

## Adding New Modules

### Step 1: Add Module to Database

```php
// database/seeders/ModuleSeeder.php

[
    'name' => 'new_module',
    'display_name' => 'New Module',
    'description' => 'Description of the new module',
    'icon' => 'ti ti-box',
    'category' => 'Administration',
    'sort_order' => 100,
    'is_active' => true,
    'read_permission' => 'new_module.read',
    'edit_permission' => 'new_module.edit',
],
```

### Step 2: Create Permissions

```php
// database/seeders/PermissionRoleSeeder.php

$modulePermissions = [
    // ... existing permissions
    'new_module' => ['read', 'edit'],
];
```

### Step 3: Protect Routes

```php
// routes/api/admin.php

Route::prefix('new-module')
    ->middleware('module.permission:new_module')
    ->group(function () {
        Route::get('/', [NewModuleController::class, 'index']);
        Route::post('/', [NewModuleController::class, 'store']);
        // ...
    });
```

### Step 4: Run Seeders

```bash
php artisan db:seed --class=ModuleSeeder
php artisan db:seed --class=PermissionRoleSeeder
php artisan cache:clear
```

---

## API Reference

### Get All Roles

```http
GET /api/v1/admin/roles
Authorization: Bearer {token}
```

Response:
```json
{
  "success": true,
  "message": "Roles retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "admin",
      "display_name": "System Administrator",
      "guard_name": "web",
      "is_protected": true
    }
  ]
}
```

### Get Module Permissions

```http
GET /api/v1/admin/module-permissions
Authorization: Bearer {token}
```

Response:
```json
{
  "success": true,
  "data": {
    "categories": [
      {
        "name": "HRM",
        "modules": [
          {
            "id": 1,
            "name": "employee",
            "display_name": "Employee",
            "read_permission": "employee.read",
            "edit_permission": "employee.edit"
          }
        ]
      }
    ]
  }
}
```

### Update User Permissions

```http
PUT /api/v1/admin/user-permissions/{userId}
Authorization: Bearer {token}
Content-Type: application/json

{
  "permissions": ["employee.read", "employee.edit", "leave.read"]
}
```

### Get Current User Permissions

```http
GET /api/v1/me/permissions
Authorization: Bearer {token}
```

Response:
```json
{
  "success": true,
  "data": {
    "permissions": ["employee.read", "employee.edit", "leave.read"],
    "modules": {
      "employee": { "read": true, "edit": true },
      "leave": { "read": true, "edit": false }
    }
  }
}
```

---

## Troubleshooting

### Common Issues

#### 1. User gets 403 on all requests

**Cause:** User has no permissions assigned.

**Solution:**
```php
// Assign role with permissions
$user->assignRole('employee');

// Or assign direct permissions
$user->givePermissionTo('employee.read');
```

#### 2. Permission changes not taking effect

**Cause:** Permission cache needs clearing.

**Solution:**
```bash
php artisan cache:clear
php artisan permission:cache-reset
```

#### 3. Module not found error

**Cause:** Module not in database or marked inactive.

**Solution:**
```bash
php artisan db:seed --class=ModuleSeeder
```

#### 4. Role dropdown shows duplicates

**Cause:** Roles exist in both 'web' and 'api' guards.

**Solution:** Frontend de-duplicates using:
```javascript
const uniqueRoles = [...new Set(rolesData.map(role => role.name))];
```

### Debug Commands

```bash
# Check user permissions
php artisan tinker
>>> $user = User::find(1);
>>> $user->getAllPermissions()->pluck('name');

# Check role permissions
>>> $role = Role::findByName('admin');
>>> $role->permissions->pluck('name');

# List all modules
>>> Module::where('is_active', true)->pluck('name');
```

---

## Summary

| Aspect | Implementation |
|--------|----------------|
| **Storage** | Spatie Laravel-Permission tables |
| **Logic** | Custom `DynamicModulePermission` middleware |
| **Permission Types** | Only `.read` and `.edit` per module |
| **Route Protection** | `->middleware('module.permission:{module}')` |
| **User Check** | `$user->can('{module}.read')` or `$user->can('{module}.edit')` |
| **Frontend** | Checkbox toggles per module (Read/Edit columns) |

This hybrid approach gives you the **reliability of Spatie** with the **simplicity of Read/Edit permissions**.
