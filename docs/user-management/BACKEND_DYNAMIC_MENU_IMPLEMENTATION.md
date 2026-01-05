# Backend Implementation - Dynamic Permission-Based Menu System

## 📋 Overview

This document outlines the backend changes made to support the dynamic permission-based menu system in the frontend.

**Status**: ✅ **Backend Already Supports Dynamic Permissions!**

The backend was already well-configured with Spatie Permission package and Laravel Sanctum. Only **minimal changes** were needed to ensure proper permission format.

---

## 🔧 What Was Changed

### File Modified: `app/Models/User.php`

**Purpose**: Transform permissions from relationship objects to flat arrays of strings

#### Changes Made:

1. **Added `$appends` property** (line 89)
```php
protected $appends = ['permission_names'];
```
- Automatically includes `permission_names` attribute in JSON responses

2. **Added `getPermissionNamesAttribute()` accessor** (lines 98-102)
```php
public function getPermissionNamesAttribute(): array
{
    // Get all permissions (direct + role-based) and return only names
    return $this->getAllPermissions()->pluck('name')->toArray();
}
```
- Returns permissions as `['user.read', 'user.create', 'user.update']`
- Combines direct permissions + role-based permissions
- Used by frontend as fallback

3. **Added `getPermissionsAttribute()` override** (lines 110-118)
```php
public function getPermissionsAttribute($value)
{
    // If permissions relationship is loaded
    if ($this->relationLoaded('permissions')) {
        return $this->getAllPermissions()->pluck('name')->toArray();
    }

    return [];
}
```
- Overrides default Spatie relationship behavior
- When `$user->load('permissions')` is called, returns flat array instead of objects
- Frontend expects: `["user.read", "user.create"]` ✅
- Without this: `[{"id": 1, "name": "user.read", ...}, ...]` ❌

---

## ✅ Backend Features Already Working

The backend already had these features implemented correctly:

### 1. **User Authentication** (`AuthController.php`)

**Login Endpoint**: `POST /api/v1/login`

```php
public function login(Request $request)
{
    // ... authentication logic

    $user->load('permissions', 'roles');

    return response()->json([
        'success' => true,
        'access_token' => $token,
        'token_type' => 'Bearer',
        'expires_in' => 21600,  // 6 hours
        'user' => $user,         // ✅ Includes permissions as array
    ]);
}
```

**Response Format**:
```json
{
  "success": true,
  "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1...",
  "token_type": "Bearer",
  "expires_in": 21600,
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "roles": [
      { "id": 1, "name": "admin", ... }
    ],
    "permissions": [  // ✅ Now returns flat array
      "user.read",
      "user.create",
      "user.update",
      "user.delete",
      "employee.read"
    ]
  }
}
```

### 2. **Get User Endpoint** (`UserController.php`)

**Endpoint**: `GET /api/v1/user/user`

```php
public function getUser(Request $request)
{
    $user = $request->user();
    $user->load('roles', 'permissions');

    return response()->json($user);
}
```

**Response Format**:
```json
{
  "id": 1,
  "name": "John Doe",
  "email": "john@example.com",
  "roles": [
    { "id": 1, "name": "admin", ... }
  ],
  "permissions": [  // ✅ Now returns flat array
    "user.read",
    "user.create",
    "user.update"
  ]
}
```

### 3. **Admin Update User Permissions** (`AdminController.php`)

**Endpoint**: `PUT /api/v1/admin/users/{id}`

```php
public function update(Request $request, $id)
{
    // Validate permissions exist in database
    $validationRules = [
        'role' => 'nullable|string|in:admin,hr-manager,hr-assistant,employee',
        'permissions' => 'nullable|array',
        'permissions.*' => 'string|exists:permissions,name',  // ✅ Validates permission names
    ];

    // ...

    // Update permissions
    if (isset($validated['permissions']) && is_array($validated['permissions'])) {
        $user->syncPermissions($validated['permissions']);
    }

    // Return updated user with permissions
    $user->load('roles', 'permissions');

    return response()->json([
        'success' => true,
        'message' => 'User updated successfully',
        'data' => $user,  // ✅ Includes updated permissions
    ]);
}
```

**Request Format**:
```json
{
  "role": "hr-manager",
  "permissions": [
    "user.read",
    "user.update",
    "employee.read",
    "employee.create"
  ]
}
```

**Response Format**:
```json
{
  "success": true,
  "message": "User updated successfully",
  "data": {
    "id": 5,
    "name": "HR Assistant",
    "email": "hr@example.com",
    "roles": [
      { "id": 2, "name": "hr-manager", ... }
    ],
    "permissions": [  // ✅ Updated permissions
      "user.read",
      "user.update",
      "employee.read",
      "employee.create"
    ]
  }
}
```

---

## 🔐 Permission System

### Permission Format

**Convention**: `{module}.{action}`

**Modules**:
- `user` - User management
- `employee` - Employee records
- `grant` - Grant management
- `payroll` - Payroll operations
- `leave_request` - Leave management
- `travel_request` - Travel requests
- `training` - Training records
- `admin` - Administrative functions
- etc.

**Actions**:
- `create` - Create new records
- `read` - View/read records
- `update` - Edit existing records
- `delete` - Delete records
- `import` - Import data
- `export` - Export data
- `bulk_create` - Bulk operations

**Examples**:
```
user.read           # Can view users
user.create         # Can create users
user.update         # Can edit users
user.delete         # Can delete users
employee.read       # Can view employees
payroll.create      # Can create payroll
grant.export        # Can export grants
```

### Spatie Permission Package

The backend uses **Spatie Laravel Permission** package which provides:

1. **Direct Permissions**: Assigned directly to a user
2. **Role Permissions**: Inherited from user's role
3. **Combined Permissions**: `getAllPermissions()` returns both

**Example**:
```php
// User has role "HR Manager" with permissions: ['employee.read', 'employee.update']
// User also has direct permission: ['payroll.create']

$user->getAllPermissions()->pluck('name')->toArray();
// Returns: ['employee.read', 'employee.update', 'payroll.create']
```

---

## 🔄 Permission Update Flow

When admin updates a user's permissions:

```
1. Frontend: PUT /api/v1/admin/users/{id}
   Body: { "permissions": ["user.read", "user.create"] }

2. Backend: AdminController@update
   - Validates permissions exist in database
   - Calls $user->syncPermissions()
   - Loads updated user with permissions
   - Returns user with new permissions

3. Frontend: authStore.updateUserData()
   - Receives updated user object
   - Stores permissions in localStorage
   - Emits 'permissions-updated' event

4. Frontend: sidebar-menu.vue
   - Listens for event
   - Re-filters menu based on new permissions
   - Updates visual badges automatically
```

---

## 📊 API Endpoints Summary

### Authentication Endpoints

| Method | Endpoint | Description | Permissions Required |
|--------|----------|-------------|---------------------|
| POST | `/api/v1/login` | Login user | None (public) |
| POST | `/api/v1/logout` | Logout user | Authenticated |
| POST | `/api/v1/refresh-token` | Refresh token | Authenticated |

### User Management Endpoints

| Method | Endpoint | Description | Permissions Required |
|--------|----------|-------------|---------------------|
| GET | `/api/v1/user/user` | Get current user | `user.read` |
| POST | `/api/v1/user/profile-picture` | Update profile picture | `user.update` |
| POST | `/api/v1/user/username` | Update username | `user.update` |
| POST | `/api/v1/user/email` | Update email | `user.update` |
| POST | `/api/v1/user/password` | Update password | `user.update` |

### Admin Endpoints

| Method | Endpoint | Description | Permissions Required |
|--------|----------|-------------|---------------------|
| GET | `/api/v1/admin/users` | List all users | `admin.read` |
| POST | `/api/v1/admin/users` | Create new user | `admin.create` |
| PUT | `/api/v1/admin/users/{id}` | Update user permissions/role | `admin.update` |
| DELETE | `/api/v1/admin/users/{id}` | Delete user | `admin.delete` |
| GET | `/api/v1/admin/roles` | Get all roles | `admin.read` |
| GET | `/api/v1/admin/permissions` | Get all permissions | `admin.read` |

---

## 🧪 Testing the Backend

### Test 1: Login Returns Correct Permission Format

**Request**:
```bash
curl -X POST http://localhost:8000/api/v1/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "admin@hrms.com",
    "password": "password"
  }'
```

**Expected Response**:
```json
{
  "success": true,
  "access_token": "token...",
  "user": {
    "id": 1,
    "permissions": [   // ✅ Should be flat array of strings
      "user.read",
      "user.create",
      "user.update"
    ]
  }
}
```

### Test 2: Get User Returns Permissions

**Request**:
```bash
curl -X GET http://localhost:8000/api/v1/user/user \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Expected Response**:
```json
{
  "id": 1,
  "name": "Admin User",
  "permissions": [   // ✅ Should be flat array
    "user.read",
    "employee.read"
  ]
}
```

### Test 3: Update User Permissions

**Request**:
```bash
curl -X PUT http://localhost:8000/api/v1/admin/users/5 \
  -H "Authorization: Bearer ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "permissions": ["employee.read", "payroll.read"]
  }'
```

**Expected Response**:
```json
{
  "success": true,
  "message": "User updated successfully",
  "data": {
    "id": 5,
    "permissions": [   // ✅ Should reflect new permissions
      "employee.read",
      "payroll.read"
    ]
  }
}
```

---

## 🐛 Troubleshooting

### Issue 1: Permissions Return as Objects

**Symptom**: Frontend receives permissions as objects instead of strings

**Response**:
```json
{
  "permissions": [
    { "id": 1, "name": "user.read", "guard_name": "api", ... },  // ❌ Wrong
    { "id": 2, "name": "user.create", "guard_name": "api", ... }
  ]
}
```

**Solution**: Verify the `getPermissionsAttribute()` accessor in `User.php` is properly implemented (should be fixed now)

### Issue 2: Empty Permissions Array

**Symptom**: User has permissions in database but API returns `[]`

**Possible Causes**:
1. Permissions not loaded: Check if `$user->load('permissions')` is called
2. User has no direct permissions (only role permissions): Use `getAllPermissions()` instead of `permissions` relationship

**Solution**: The `getPermissionsAttribute()` accessor uses `getAllPermissions()` which includes both direct and role permissions

### Issue 3: Permission Validation Fails

**Symptom**: `PUT /admin/users/{id}` returns validation error for valid permissions

**Error**:
```json
{
  "message": "The selected permissions.0 is invalid."
}
```

**Possible Causes**:
1. Permission doesn't exist in `permissions` table
2. Permission name typo (case-sensitive)

**Solution**:
- Check permissions table: `SELECT name FROM permissions;`
- Ensure permission names match exactly (e.g., `user.read` not `User.Read`)

---

## 📝 Database Schema

### Permissions Table

```sql
CREATE TABLE permissions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,        -- e.g., 'user.read'
    guard_name VARCHAR(255) NOT NULL,  -- e.g., 'api'
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY permissions_name_guard_name_unique (name, guard_name)
);
```

### Roles Table

```sql
CREATE TABLE roles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,        -- e.g., 'admin', 'hr-manager'
    guard_name VARCHAR(255) NOT NULL,  -- e.g., 'api'
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY roles_name_guard_name_unique (name, guard_name)
);
```

### Model Has Permissions Table (User Permissions)

```sql
CREATE TABLE model_has_permissions (
    permission_id BIGINT UNSIGNED NOT NULL,
    model_type VARCHAR(255) NOT NULL,   -- 'App\Models\User'
    model_id BIGINT UNSIGNED NOT NULL,  -- User ID
    PRIMARY KEY (permission_id, model_id, model_type),
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);
```

### Model Has Roles Table (User Roles)

```sql
CREATE TABLE model_has_roles (
    role_id BIGINT UNSIGNED NOT NULL,
    model_type VARCHAR(255) NOT NULL,   -- 'App\Models\User'
    model_id BIGINT UNSIGNED NOT NULL,  -- User ID
    PRIMARY KEY (role_id, model_id, model_type),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);
```

---

## ✅ Checklist

**Backend Implementation Checklist**:

- [x] Spatie Permission package installed and configured
- [x] User model uses `HasRoles` trait
- [x] Permissions stored in database with correct format
- [x] Login endpoint returns user with permissions
- [x] GET /user endpoint returns permissions
- [x] Admin endpoints validate permission names
- [x] Admin endpoints sync user permissions
- [x] **NEW**: User model transforms permissions to flat array
- [x] **NEW**: `getPermissionsAttribute()` accessor added
- [x] **NEW**: `getPermissionNamesAttribute()` accessor added

---

## 🎯 Key Takeaways

1. **Minimal Backend Changes**: Only modified `User.php` to change permission format
2. **Spatie Does the Heavy Lifting**: Permission management, role inheritance, validation all handled by Spatie package
3. **Automatic Transformation**: Accessors ensure permissions are always returned as flat arrays
4. **Already Production-Ready**: Backend permission system was already well-implemented

---

## 📚 Related Documentation

- **Frontend Implementation**: See `DYNAMIC_MENU_IMPLEMENTATION.md` in `hrms-frontend-dev/`
- **Spatie Permission Package**: https://spatie.be/docs/laravel-permission/v6/introduction
- **Laravel Sanctum**: https://laravel.com/docs/11.x/sanctum

---

**Last Updated**: 2025-12-18
**Version**: 1.0
**Maintained by**: HRMS Development Team

---

**Summary**: The backend required only **one file change** (User.php) to support the dynamic menu system. All other necessary features (permission validation, storage, retrieval) were already correctly implemented! ✅
