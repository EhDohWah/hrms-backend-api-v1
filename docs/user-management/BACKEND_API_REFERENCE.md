# Backend User Management API Reference

## Table of Contents
1. [Authentication Endpoints](#authentication-endpoints)
2. [User Profile Endpoints](#user-profile-endpoints)
3. [Admin User Management Endpoints](#admin-user-management-endpoints)
4. [Activity Log Endpoints](#activity-log-endpoints)
5. [Models & Database Schema](#models--database-schema)
6. [Middleware & Authorization](#middleware--authorization)
7. [Seeders & Factories](#seeders--factories)

---

## Authentication Endpoints

Base URL: `/api/v1`

### 1. Login

**Endpoint**: `POST /login`

**Access**: Public (no authentication required)

**Rate Limiting**: 5 attempts per minute (per email + IP combination)

**Request Body**:
```json
{
  "email": "admin@hrms.com",
  "password": "password123"
}
```

**Validation Rules**:
- `email`: required|email
- `password`: required|string

**Success Response** (200 OK):
```json
{
  "access_token": "1|abc123xyz...",
  "token_type": "Bearer",
  "expires_in": 21600,
  "user": {
    "id": 1,
    "name": "Admin User",
    "email": "admin@hrms.com",
    "status": "active",
    "last_login_at": "2025-12-17T10:30:00.000000Z",
    "last_login_ip": "192.168.1.100",
    "profile_picture": "profile_pictures/user1.jpg",
    "created_at": "2025-01-01T00:00:00.000000Z",
    "updated_at": "2025-12-17T10:30:00.000000Z",
    "roles": [
      {
        "id": 1,
        "name": "admin",
        "guard_name": "web"
      }
    ],
    "permissions": [
      {
        "id": 1,
        "name": "user.create",
        "guard_name": "web"
      },
      ...
    ]
  }
}
```

**Error Responses**:
- **401 Unauthorized**: Invalid credentials
  ```json
  {
    "message": "The provided credentials are incorrect."
  }
  ```
- **429 Too Many Requests**: Rate limit exceeded
  ```json
  {
    "message": "Too many login attempts. Please try again in 60 seconds."
  }
  ```

**Implementation Details**:
- Location: `app/Http/Controllers/Api/AuthController.php:12`
- Updates `last_login_at` and `last_login_ip` on success
- Creates Sanctum token with 6-hour expiration
- Eager loads `roles` and `permissions` relationships
- Logs all login attempts via activity logging

---

### 2. Logout

**Endpoint**: `POST /logout`

**Access**: Authenticated users only (`auth:sanctum`)

**Request Headers**:
```
Authorization: Bearer {access_token}
```

**Success Response** (200 OK):
```json
{
  "message": "Successfully logged out"
}
```

**Implementation Details**:
- Location: `app/Http/Controllers/Api/AuthController.php:52`
- Revokes current access token (not all tokens)
- Does not delete user session data

---

### 3. Refresh Token

**Endpoint**: `POST /refresh-token`

**Access**: Authenticated users only (`auth:sanctum`)

**Request Headers**:
```
Authorization: Bearer {access_token}
```

**Success Response** (200 OK):
```json
{
  "access_token": "2|def456uvw...",
  "token_type": "Bearer",
  "expires_in": 21600
}
```

**Implementation Details**:
- Location: `app/Http/Controllers/Api/AuthController.php:63`
- Revokes old token
- Creates new token with fresh 6-hour expiration
- Maintains user session without re-authentication

---

## User Profile Endpoints

All user profile endpoints require authentication (`auth:sanctum`) and `user.read` or `user.update` permissions.

### 1. Get Current User

**Endpoint**: `GET /user/user`

**Access**: Authenticated users (any role)

**Request Headers**:
```
Authorization: Bearer {access_token}
```

**Success Response** (200 OK):
```json
{
  "id": 1,
  "name": "Admin User",
  "email": "admin@hrms.com",
  "status": "active",
  "profile_picture": "profile_pictures/user1.jpg",
  "roles": [
    {
      "id": 1,
      "name": "admin",
      "guard_name": "web"
    }
  ],
  "permissions": [
    {
      "id": 1,
      "name": "user.create",
      "guard_name": "web"
    }
  ]
}
```

**Implementation Details**:
- Location: `app/Http/Controllers/Api/UserController.php:16`
- Returns authenticated user with eager-loaded relationships

---

### 2. Update Profile Picture

**Endpoint**: `POST /user/profile-picture`

**Access**: Authenticated users with `user.update` permission

**Request Headers**:
```
Authorization: Bearer {access_token}
Content-Type: multipart/form-data
```

**Request Body** (FormData):
```
profile_picture: [File] (image file, max 2MB)
```

**Validation Rules**:
- `profile_picture`: required|image|max:2048 (KB)

**Success Response** (200 OK):
```json
{
  "message": "Profile picture updated successfully",
  "profile_picture_url": "profile_pictures/user1_1234567890.jpg"
}
```

**Implementation Details**:
- Location: `app/Http/Controllers/Api/UserController.php:28`
- Stores image in `public/profile_pictures` directory
- Deletes old profile picture if exists
- Returns public URL to new image

---

### 3. Update Username

**Endpoint**: `POST /user/username`

**Access**: Authenticated users with `user.update` permission

**Request Body**:
```json
{
  "name": "John Doe Updated"
}
```

**Validation Rules**:
- `name`: required|string|max:255

**Success Response** (200 OK):
```json
{
  "message": "Username updated successfully",
  "name": "John Doe Updated"
}
```

**Implementation Details**:
- Location: `app/Http/Controllers/Api/UserController.php:48`

---

### 4. Update Email

**Endpoint**: `POST /user/email`

**Access**: Authenticated users with `user.update` permission

**Request Body**:
```json
{
  "email": "newemail@hrms.com"
}
```

**Validation Rules**:
- `email`: required|email|unique:users,email,{user_id}

**Success Response** (200 OK):
```json
{
  "message": "Email updated successfully",
  "email": "newemail@hrms.com"
}
```

**Implementation Details**:
- Location: `app/Http/Controllers/Api/UserController.php:64`
- Validates uniqueness excluding current user

---

### 5. Update Password

**Endpoint**: `POST /user/password`

**Access**: Authenticated users with `user.update` permission

**Request Body**:
```json
{
  "current_password": "oldPassword123!",
  "new_password": "newPassword456!",
  "confirm_password": "newPassword456!"
}
```

**Validation Rules**:
- `current_password`: required|string (must match existing password)
- `new_password`: required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/
- `confirm_password`: required|string|same:new_password

**Password Requirements**:
- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character (@$!%*?&)

**Success Response** (200 OK):
```json
{
  "message": "Password updated successfully"
}
```

**Error Response** (422 Unprocessable Entity):
```json
{
  "message": "Current password is incorrect"
}
```

**Implementation Details**:
- Location: `app/Http/Controllers/Api/UserController.php:81`
- Verifies current password before update
- Automatically hashes new password (Laravel 11 `password` cast)

---

## Admin User Management Endpoints

All admin endpoints require `auth:sanctum` and specific admin permissions.

### 1. List All Users

**Endpoint**: `GET /admin/users`

**Access**: `admin.read` permission required

**Request Headers**:
```
Authorization: Bearer {access_token}
```

**Success Response** (200 OK):
```json
[
  {
    "id": 1,
    "name": "Admin User",
    "email": "admin@hrms.com",
    "status": "active",
    "profile_picture": "profile_pictures/admin.jpg",
    "created_at": "2025-01-01T00:00:00.000000Z",
    "updated_at": "2025-12-17T10:30:00.000000Z",
    "roles": [
      {
        "id": 1,
        "name": "admin",
        "guard_name": "web"
      }
    ],
    "permissions": [...]
  },
  ...
]
```

**Implementation Details**:
- Location: `app/Http/Controllers/Api/AdminController.php:18`
- Eager loads `roles` and `permissions` relationships
- **No pagination implemented** (returns all users)
- **TODO**: Add pagination, filtering, and search

---

### 2. Get User Details

**Endpoint**: `GET /admin/users/{id}`

**Access**: `admin.read` permission required

**Request Headers**:
```
Authorization: Bearer {access_token}
```

**Success Response** (200 OK):
```json
{
  "id": 1,
  "name": "Admin User",
  "email": "admin@hrms.com",
  "status": "active",
  "profile_picture": "profile_pictures/admin.jpg",
  "last_login_at": "2025-12-17T10:30:00.000000Z",
  "last_login_ip": "192.168.1.100",
  "created_by": 1,
  "updated_by": 1,
  "created_at": "2025-01-01T00:00:00.000000Z",
  "updated_at": "2025-12-17T10:30:00.000000Z",
  "roles": [...],
  "permissions": [...]
}
```

**Implementation Details**:
- Location: `app/Http/Controllers/Api/AdminController.php` (method NOT IMPLEMENTED)
- Route exists but controller method missing
- **TODO**: Implement this method

---

### 3. Create User

**Endpoint**: `POST /admin/users`

**Access**: `admin.create` permission required

**Request Headers**:
```
Authorization: Bearer {access_token}
Content-Type: multipart/form-data
```

**Request Body** (FormData):
```json
{
  "name": "John Doe",
  "email": "john@hrms.com",
  "password": "Password123!",
  "password_confirmation": "Password123!",
  "role": "hr-manager",
  "permissions": ["user.create", "user.read", "user.update"],
  "profile_picture": [File] (optional)
}
```

**Validation Rules**:
- `name`: required|string|max:255
- `email`: required|email|unique:users,email
- `password`: required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/
- `password_confirmation`: required|string
- `role`: required|string|in:admin,hr-manager,hr-assistant-senior,hr-assistant-junior,site-admin
- `permissions`: nullable|array
- `profile_picture`: nullable|image|max:2048

**Success Response** (201 Created):
```json
{
  "message": "User created successfully",
  "user": {
    "id": 10,
    "name": "John Doe",
    "email": "john@hrms.com",
    "status": "active",
    "created_by": 1,
    "roles": [
      {
        "id": 2,
        "name": "hr-manager"
      }
    ],
    "permissions": [...]
  }
}
```

**Error Response** (422 Unprocessable Entity):
```json
{
  "message": "The email has already been taken.",
  "errors": {
    "email": [
      "The email has already been taken."
    ]
  }
}
```

**Implementation Details**:
- Location: `app/Http/Controllers/Api/AdminController.php:29`
- Uses database transaction for atomicity
- Automatically hashes password
- Sets `created_by` to current authenticated user ID
- Assigns role using `assignRole()` method
- Syncs permissions if provided
- Stores profile picture if uploaded
- Rolls back on any error

---

### 4. Update User

**Endpoint**: `PUT /admin/users/{id}` or `POST /admin/users/{id}` (with `_method=PUT`)

**Access**: `admin.update` permission required

**Request Headers**:
```
Authorization: Bearer {access_token}
Content-Type: multipart/form-data (if file upload)
```

**Request Body** (FormData):
```json
{
  "_method": "PUT",
  "role": "hr-assistant-senior",
  "permissions": ["user.read", "employee.create"],
  "password": "NewPassword123!" (optional),
  "profile_picture": [File] (optional)
}
```

**Validation Rules**:
- `role`: nullable|string|in:admin,hr-manager,hr-assistant-senior,hr-assistant-junior,site-admin
- `permissions`: nullable|array
- `password`: nullable|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/
- `profile_picture`: nullable|image|max:2048

**Success Response** (200 OK):
```json
{
  "message": "User updated successfully",
  "user": {
    "id": 10,
    "name": "John Doe",
    "email": "john@hrms.com",
    "roles": [
      {
        "id": 3,
        "name": "hr-assistant-senior"
      }
    ],
    "permissions": [...]
  }
}
```

**Implementation Details**:
- Location: `app/Http/Controllers/Api/AdminController.php:87`
- Uses database transaction
- Detaches all existing roles before assigning new one
- Syncs new permissions (replaces all previous permissions)
- Updates password only if provided
- Updates profile picture only if provided
- Rolls back on error

---

### 5. Delete User

**Endpoint**: `DELETE /admin/users/{id}`

**Access**: `admin.delete` permission required

**Request Headers**:
```
Authorization: Bearer {access_token}
```

**Success Response** (200 OK):
```json
{
  "message": "User deleted successfully"
}
```

**Error Response** (404 Not Found):
```json
{
  "message": "User not found"
}
```

**Implementation Details**:
- Location: `app/Http/Controllers/Api/AdminController.php:128`
- Uses database transaction
- Detaches all roles using `roles()->detach()`
- Detaches all permissions using `permissions()->detach()`
- Deletes user record
- Rolls back on error
- **Note**: Does not soft delete (permanent deletion)

---

### 6. Get All Roles

**Endpoint**: `GET /admin/roles`

**Access**: `admin.read` permission required

**Request Headers**:
```
Authorization: Bearer {access_token}
```

**Expected Response** (200 OK):
```json
[
  {
    "id": 1,
    "name": "admin",
    "guard_name": "web",
    "created_at": "2025-01-01T00:00:00.000000Z",
    "updated_at": "2025-01-01T00:00:00.000000Z"
  },
  {
    "id": 2,
    "name": "hr-manager",
    "guard_name": "web",
    "created_at": "2025-01-01T00:00:00.000000Z",
    "updated_at": "2025-01-01T00:00:00.000000Z"
  },
  ...
]
```

**Implementation Details**:
- Location: `app/Http/Controllers/Api/AdminController.php` (method NOT IMPLEMENTED)
- Route exists: `routes/api/admin.php:38`
- **TODO**: Implement `AdminController@getRoles()`
- Should query `Spatie\Permission\Models\Role::all()`

---

### 7. Get All Permissions

**Endpoint**: `GET /admin/permissions`

**Access**: `admin.read` permission required

**Request Headers**:
```
Authorization: Bearer {access_token}
```

**Expected Response** (200 OK):
```json
[
  {
    "id": 1,
    "name": "user.create",
    "guard_name": "web",
    "created_at": "2025-01-01T00:00:00.000000Z",
    "updated_at": "2025-01-01T00:00:00.000000Z"
  },
  {
    "id": 2,
    "name": "user.read",
    "guard_name": "web",
    "created_at": "2025-01-01T00:00:00.000000Z",
    "updated_at": "2025-01-01T00:00:00.000000Z"
  },
  ...
]
```

**Implementation Details**:
- Location: `app/Http/Controllers/Api/AdminController.php` (method NOT IMPLEMENTED)
- Route exists: `routes/api/admin.php:39`
- **TODO**: Implement `AdminController@getPermissions()`
- Should query `Spatie\Permission\Models\Permission::all()`

---

## Activity Log Endpoints

### 1. Get Activity Logs (Paginated)

**Endpoint**: `GET /activity-logs`

**Access**: `admin.read` permission required

**Query Parameters**:
- `page`: integer (default: 1)
- `per_page`: integer (default: 20, max: 100)
- `subject_type`: string (optional, e.g., "App\Models\User")
- `subject_id`: integer (optional)
- `user_id`: integer (optional)
- `action`: string (optional, e.g., "created", "updated", "deleted")
- `date_from`: date (optional, format: Y-m-d)
- `date_to`: date (optional, format: Y-m-d)

**Example Request**:
```
GET /api/v1/activity-logs?page=1&per_page=20&subject_type=App\Models\User&action=updated
```

**Success Response** (200 OK):
```json
{
  "current_page": 1,
  "data": [
    {
      "id": 1,
      "user_id": 1,
      "action": "updated",
      "subject_type": "App\\Models\\User",
      "subject_id": 10,
      "subject_name": "John Doe",
      "description": "User updated",
      "properties": {
        "old": {
          "email": "john.old@hrms.com"
        },
        "new": {
          "email": "john@hrms.com"
        }
      },
      "ip_address": "192.168.1.100",
      "created_at": "2025-12-17T10:30:00.000000Z"
    },
    ...
  ],
  "first_page_url": "http://api.hrms.com/api/v1/activity-logs?page=1",
  "from": 1,
  "last_page": 5,
  "last_page_url": "http://api.hrms.com/api/v1/activity-logs?page=5",
  "links": [...],
  "next_page_url": "http://api.hrms.com/api/v1/activity-logs?page=2",
  "path": "http://api.hrms.com/api/v1/activity-logs",
  "per_page": 20,
  "prev_page_url": null,
  "to": 20,
  "total": 95
}
```

**Implementation Details**:
- Location: `app/Http/Controllers/Api/ActivityLogController.php:13`

---

### 2. Get Recent Activity Logs

**Endpoint**: `GET /activity-logs/recent`

**Access**: `admin.read` permission required

**Query Parameters**:
- `limit`: integer (default: 50, max: 100)

**Example Request**:
```
GET /api/v1/activity-logs/recent?limit=20
```

**Success Response** (200 OK):
```json
[
  {
    "id": 100,
    "user_id": 1,
    "action": "created",
    "subject_type": "App\\Models\\User",
    "subject_id": 11,
    "subject_name": "New User",
    "description": "User created",
    "properties": null,
    "ip_address": "192.168.1.100",
    "created_at": "2025-12-17T11:00:00.000000Z"
  },
  ...
]
```

**Implementation Details**:
- Location: `app/Http/Controllers/Api/ActivityLogController.php:63`
- Returns non-paginated array (not pagination object)
- Orders by `created_at DESC`

---

### 3. Get Activity Logs for Specific Subject

**Endpoint**: `GET /activity-logs/subject/{type}/{id}`

**Access**: `admin.read` permission required

**Path Parameters**:
- `type`: string (subject type, e.g., "User", "Employee")
- `id`: integer (subject ID)

**Example Request**:
```
GET /api/v1/activity-logs/subject/User/10
```

**Success Response** (200 OK):
```json
[
  {
    "id": 50,
    "user_id": 1,
    "action": "created",
    "subject_type": "App\\Models\\User",
    "subject_id": 10,
    "subject_name": "John Doe",
    "description": "User created",
    "properties": null,
    "ip_address": "192.168.1.100",
    "created_at": "2025-12-15T09:00:00.000000Z"
  },
  {
    "id": 75,
    "user_id": 1,
    "action": "updated",
    "subject_type": "App\\Models\\User",
    "subject_id": 10,
    "subject_name": "John Doe",
    "description": "User updated",
    "properties": {
      "old": {"email": "old@hrms.com"},
      "new": {"email": "john@hrms.com"}
    },
    "ip_address": "192.168.1.100",
    "created_at": "2025-12-17T10:30:00.000000Z"
  }
]
```

**Implementation Details**:
- Location: `app/Http/Controllers/Api/ActivityLogController.php:49`
- Constructs full class name: `App\Models\{type}`
- Orders by `created_at ASC`

---

## Models & Database Schema

### User Model

**File**: `app/Models/User.php`

**Table**: `users`

**Traits**:
- `HasApiTokens` (Laravel Sanctum)
- `HasFactory`
- `HasRoles` (Spatie Permission)
- `Notifiable`

**Fillable Attributes**:
```php
[
    'name',
    'email',
    'password',
    'status',
    'last_login_at',
    'last_login_ip',
    'profile_picture',
    'created_by',
    'updated_by',
]
```

**Hidden Attributes**:
```php
[
    'password',
    'remember_token',
]
```

**Casts**:
```php
[
    'email_verified_at' => 'datetime',
    'password' => 'hashed', // Laravel 11 auto-hashing
]
```

**Relationships**:
```php
// One-to-One
employee(): HasOne

// Many-to-Many (via Spatie Permission)
roles(): BelongsToMany
permissions(): BelongsToMany
```

**Methods**:
- Inherits all Spatie Permission methods:
  - `assignRole($role)`
  - `removeRole($role)`
  - `syncRoles($roles)`
  - `hasRole($role)`
  - `givePermissionTo($permission)`
  - `revokePermissionTo($permission)`
  - `syncPermissions($permissions)`
  - `hasPermissionTo($permission)`
  - `getPermissionNames()`

---

### Users Table Schema

**Migration**: `database/migrations/0001_01_01_000000_create_users_table.php`

```sql
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    phone VARCHAR(255) NULL,
    status VARCHAR(255) DEFAULT 'active',
    last_login_at TIMESTAMP NULL,
    last_login_ip VARCHAR(255) NULL,
    profile_picture VARCHAR(255) NULL,
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

CREATE TABLE password_reset_tokens (
    email VARCHAR(255) PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL
);

CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload LONGTEXT NOT NULL,
    last_activity INT NOT NULL,
    INDEX sessions_user_id_index (user_id),
    INDEX sessions_last_activity_index (last_activity)
);
```

---

### Permission Tables Schema

**Migration**: `database/migrations/2025_02_12_015944_create_permission_tables.php`

Uses **Spatie Laravel-Permission** package schema:

```sql
CREATE TABLE permissions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY permissions_name_guard_name_unique (name, guard_name)
);

CREATE TABLE roles (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    guard_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    UNIQUE KEY roles_name_guard_name_unique (name, guard_name)
);

CREATE TABLE model_has_permissions (
    permission_id BIGINT UNSIGNED NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (permission_id, model_id, model_type),
    INDEX model_has_permissions_model_id_model_type_index (model_id, model_type),
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
);

CREATE TABLE model_has_roles (
    role_id BIGINT UNSIGNED NOT NULL,
    model_type VARCHAR(255) NOT NULL,
    model_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (role_id, model_id, model_type),
    INDEX model_has_roles_model_id_model_type_index (model_id, model_type),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

CREATE TABLE role_has_permissions (
    permission_id BIGINT UNSIGNED NOT NULL,
    role_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (permission_id, role_id),
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);
```

---

### Activity Log Model & Schema

**File**: `app/Models/ActivityLog.php`

**Table**: `activity_logs`

```sql
CREATE TABLE activity_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    action VARCHAR(255) NOT NULL, -- created, updated, deleted, processed, imported
    subject_type VARCHAR(255) NOT NULL, -- Polymorphic type
    subject_id BIGINT UNSIGNED NOT NULL, -- Polymorphic ID
    subject_name VARCHAR(255) NULL,
    description TEXT NULL,
    properties JSON NULL, -- {old: {...}, new: {...}}
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX activity_logs_subject_type_subject_id_index (subject_type, subject_id),
    INDEX activity_logs_user_id_index (user_id),
    INDEX activity_logs_action_index (action),
    INDEX activity_logs_created_at_index (created_at)
);
```

**Relationships**:
```php
user(): BelongsTo // User who performed the action
subject(): MorphTo // Polymorphic relationship to logged entity
```

**LogsActivity Trait**:
**File**: `app/Traits/LogsActivity.php`

Apply to any model to automatically log changes:
```php
use LogsActivity;
```

**Automatic Logging**:
- `created` event → logs model creation
- `updated` event → logs model updates (with old/new values)
- `deleted` event → logs model deletion

**Manual Logging**:
```php
$model->logActivity('custom_action', ['key' => 'value'], 'Custom description');
```

---

## Middleware & Authorization

### Middleware Configuration

**File**: `bootstrap/app.php`

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
        'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
    ]);
})
```

### Route Middleware Usage

**Example 1: Single Permission**
```php
Route::get('/admin/users', [AdminController::class, 'index'])
    ->middleware(['auth:sanctum', 'permission:admin.read']);
```

**Example 2: Single Role**
```php
Route::get('/dashboard/admin', [DashboardController::class, 'admin'])
    ->middleware(['auth:sanctum', 'role:admin']);
```

**Example 3: Multiple Permissions (AND)**
```php
Route::post('/admin/users', [AdminController::class, 'store'])
    ->middleware(['auth:sanctum', 'permission:admin.create,admin.read']);
```

**Example 4: Role OR Permission**
```php
Route::get('/reports', [ReportController::class, 'index'])
    ->middleware(['auth:sanctum', 'role_or_permission:admin,reports.read']);
```

### Default Roles & Permissions

**Created in**: `database/migrations/2025_03_03_092449_create_default_user_and_roles.php`

**Modules** (21):
```
admin, user, grant, interview, employee, employment, employment_history,
children, questionnaire, language, reference, education, payroll,
attendance, training, reports, travel_request, leave_request, job_offer,
budget_line, tax, personnel_action
```

**Actions per Module** (7):
```
create, read, update, delete, import, export, bulk_create
```

**Total Permissions**: 21 × 7 = 147 permissions

**Role Definitions**:

1. **admin**
   - Permissions: ALL (147 permissions)
   - Description: Full system access

2. **hr-manager**
   - Permissions: ALL (147 permissions)
   - Description: Full HR management access

3. **hr-assistant-senior**
   - Permissions: ALL EXCEPT `grant.*` (140 permissions)
   - Description: Senior HR operations (no grant management)

4. **hr-assistant-junior**
   - Permissions: ALL EXCEPT `grant.*`, `employment.*`, `payroll.*`, `reports.*` (112 permissions)
   - Description: Junior HR operations (limited financial access)

5. **site-admin**
   - Permissions: ONLY `leave_request.*`, `travel_request.*`, `training.*` (21 permissions)
   - Description: Site-level operations only

### Default Users

Created in same migration:

| Email | Password | Role |
|-------|----------|------|
| admin@hrms.com | password | admin |
| hrmanager@hrms.com | password | hr-manager |
| hrassistant.senior@hrms.com | password | hr-assistant-senior |
| hrassistant.junior@hrms.com | password | hr-assistant-junior |
| siteadmin@hrms.com | password | site-admin |

---

## Seeders & Factories

### UserSeeder

**File**: `database/seeders/UserSeeder.php`

Creates 4 test users:
```php
User::create([
    'name' => 'Admin User',
    'email' => 'admin@hrms.com',
    'password' => bcrypt('password'),
])->assignRole('admin');

User::create([
    'name' => 'HR Manager User',
    'email' => 'hrmanager@hrms.com',
    'password' => bcrypt('password'),
])->assignRole('hr-manager');

User::create([
    'name' => 'HR Assistant User',
    'email' => 'hrassistant@hrms.com',
    'password' => bcrypt('password'),
])->assignRole('hr-assistant-senior');

User::create([
    'name' => 'Employee User',
    'email' => 'employee@hrms.com',
    'password' => bcrypt('password'),
])->assignRole('site-admin');
```

**Note**: Not currently called in DatabaseSeeder (commented out)

---

### UserFactory

**File**: `database/factories/UserFactory.php`

```php
public function definition(): array
{
    return [
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => 'password', // Auto-hashed by Laravel 11
        'remember_token' => Str::random(10),
        'status' => 'active',
    ];
}
```

**Usage in Tests**:
```php
// Create single user
$user = User::factory()->create();

// Create with role
$admin = User::factory()->create()->assignRole('admin');

// Create multiple
$users = User::factory()->count(10)->create();
```

---

## API Error Responses

### Standard Error Format

```json
{
  "message": "Error message",
  "errors": {
    "field_name": [
      "Validation error message"
    ]
  }
}
```

### Common HTTP Status Codes

| Code | Meaning | When Used |
|------|---------|-----------|
| 200 | OK | Successful request |
| 201 | Created | Resource created successfully |
| 400 | Bad Request | Invalid request format |
| 401 | Unauthorized | Missing or invalid token |
| 403 | Forbidden | Valid token but insufficient permissions |
| 404 | Not Found | Resource doesn't exist |
| 422 | Unprocessable Entity | Validation failed |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server-side error |

---

## Postman Collection Examples

### Environment Variables
```json
{
  "base_url": "http://localhost:8000/api/v1",
  "token": "{{access_token}}"
}
```

### 1. Login Request
```http
POST {{base_url}}/login
Content-Type: application/json

{
  "email": "admin@hrms.com",
  "password": "password"
}
```

### 2. Get Current User
```http
GET {{base_url}}/user/user
Authorization: Bearer {{token}}
```

### 3. Create User
```http
POST {{base_url}}/admin/users
Authorization: Bearer {{token}}
Content-Type: multipart/form-data

name: John Doe
email: john@hrms.com
password: Password123!
password_confirmation: Password123!
role: hr-manager
permissions[]: user.create
permissions[]: user.read
profile_picture: [file]
```

### 4. Update User
```http
POST {{base_url}}/admin/users/10
Authorization: Bearer {{token}}
Content-Type: multipart/form-data

_method: PUT
role: hr-assistant-senior
permissions[]: user.read
permissions[]: employee.create
```

### 5. Delete User
```http
DELETE {{base_url}}/admin/users/10
Authorization: Bearer {{token}}
```

---

**Last Updated**: 2025-12-17
**Laravel Version**: 11
**API Version**: v1
