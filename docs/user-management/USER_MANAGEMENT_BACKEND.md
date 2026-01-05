# User Management - Backend Documentation

## Overview

The User Management system in the HRMS backend provides comprehensive functionality for managing users, their roles, permissions, and profile information. The system uses Laravel Sanctum for authentication and Spatie Permission package for role-based access control (RBAC).

## Table of Contents

1. [Architecture](#architecture)
2. [Models](#models)
3. [Controllers](#controllers)
4. [API Endpoints](#api-endpoints)
5. [Authentication & Authorization](#authentication--authorization)
6. [Role-Based Permissions](#role-based-permissions)
7. [Profile Management](#profile-management)
8. [Validation Rules](#validation-rules)
9. [Error Handling](#error-handling)

## Architecture

### Key Components

- **User Model** (`app/Models/User.php`): Core user model with relationships
- **AdminController** (`app/Http/Controllers/Api/AdminController.php`): Handles user CRUD operations
- **UserController** (`app/Http/Controllers/Api/UserController.php`): Handles user profile operations
- **Routes** (`routes/api/admin.php`): API route definitions

### Dependencies

- **Laravel Sanctum**: Token-based authentication
- **Spatie Permission**: Role and permission management
- **Laravel Storage**: File upload handling for profile pictures

## Models

### User Model

**Location**: `app/Models/User.php`

The User model extends Laravel's `Authenticatable` and uses the following traits:

- `HasApiTokens`: For Sanctum token management
- `HasRoles`: For Spatie Permission role management
- `Notifiable`: For notifications

#### Key Attributes

```php
protected $fillable = [
    'name',
    'email',
    'password',
    'status',
    'last_login_at',
    'last_login_ip',
    'profile_picture',
    'created_by',
    'updated_by',
];

protected $hidden = [
    'password',
    'remember_token',
];
```

#### Relationships

- `employee()`: HasOne relationship with Employee model
- `roles()`: Many-to-many relationship via Spatie Permission
- `permissions()`: Many-to-many relationship via Spatie Permission

#### Casts

```php
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];
}
```

## Controllers

### AdminController

**Location**: `app/Http/Controllers/Api/AdminController.php`

Handles administrative user management operations. All methods require appropriate permissions.

#### Methods

##### `index(Request $request)`

Retrieves a list of all users with their roles and permissions.

- **Route**: `GET /api/admin/users`
- **Permission**: `admin.read`
- **Response**: JSON array of users with roles and permissions

**Example Response**:
```json
{
    "success": true,
    "message": "Users retrieved successfully",
    "data": [
        {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com",
            "roles": [{"id": 1, "name": "admin"}],
            "permissions": [{"id": 1, "name": "user.read"}]
        }
    ]
}
```

##### `store(Request $request)`

Creates a new user with role and permissions.

- **Route**: `POST /api/admin/users`
- **Permission**: `admin.create`
- **Request Type**: `multipart/form-data`

**Request Parameters**:
- `name` (required, string, max:255): User's full name
- `email` (required, email, unique): User's email address
- `password` (required, string, min:8, confirmed): Password with strength requirements
- `password_confirmation` (required, string): Password confirmation
- `role` (required, string, in:admin,hr-manager,hr-assistant,employee): User role
- `permissions` (optional, array): Array of permission strings
- `profile_picture` (optional, image, max:2048KB): Profile picture file

**Password Requirements**:
- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character (@$!%*?&)

**Validation Rules**:
- Email must be unique
- Name must be unique
- Password must match confirmation
- Role must be one of: admin, hr-manager, hr-assistant, employee

**Process Flow**:
1. Validates email and name uniqueness
2. Validates request data
3. Starts database transaction
4. Stores profile picture if provided
5. Creates user with hashed password
6. Assigns role using Spatie Permission
7. Syncs permissions if provided
8. Commits transaction
9. Returns created user with roles and permissions

##### `update(Request $request, $id)`

Updates user's role, permissions, and optionally password.

- **Route**: `PUT /api/admin/users/{id}`
- **Permission**: `admin.update`
- **Request Type**: `application/json` or `multipart/form-data`

**Request Parameters**:
- `role` (optional, string): New role
- `permissions` (optional, array): Array of permission strings
- `password` (optional, string): New password
- `password_confirmation` (required if password provided): Password confirmation
- `profile_picture` (optional, image): New profile picture

**Process Flow**:
1. Finds user by ID
2. Validates request data
3. Starts database transaction
4. Updates role if provided (removes old roles, assigns new)
5. Syncs permissions if provided
6. Updates password if provided
7. Commits transaction
8. Returns updated user

##### `destroy(Request $request, $id)`

Deletes a user and all associated roles/permissions.

- **Route**: `DELETE /api/admin/users/{id}`
- **Permission**: `admin.delete`

**Process Flow**:
1. Finds user by ID
2. Starts database transaction
3. Detaches all roles
4. Detaches all permissions
5. Deletes user record
6. Commits transaction
7. Returns success message

##### `show(Request $request, $id)`

Retrieves a specific user's details.

- **Route**: `GET /api/admin/users/{id}`
- **Permission**: `admin.read`

### UserController

**Location**: `app/Http/Controllers/Api/UserController.php`

Handles authenticated user's profile management operations.

#### Methods

##### `getUser(Request $request)`

Retrieves the authenticated user's details with roles and permissions.

- **Route**: `GET /api/user/user`
- **Permission**: `user.read`
- **Authentication**: Required (via Sanctum)

**Response**: Returns authenticated user with roles and permissions

##### `updateProfilePicture(Request $request)`

Updates the authenticated user's profile picture.

- **Route**: `POST /api/user/profile-picture`
- **Permission**: `user.update`
- **Request Type**: `multipart/form-data`

**Request Parameters**:
- `profile_picture` (required, image, max:2048KB): Profile picture file

**Process Flow**:
1. Validates image file
2. Deletes old profile picture if exists
3. Stores new profile picture
4. Updates user record
5. Returns new profile picture path and URL

##### `updateUsername(Request $request)`

Updates the authenticated user's name.

- **Route**: `POST /api/user/username`
- **Permission**: `user.update`

**Request Parameters**:
- `name` (required, string, max:255): New name

##### `updateEmail(Request $request)`

Updates the authenticated user's email.

- **Route**: `POST /api/user/email`
- **Permission**: `user.update`

**Request Parameters**:
- `email` (required, email, unique): New email address

**Validation**: Email must be unique (excluding current user)

##### `updatePassword(Request $request)`

Updates the authenticated user's password.

- **Route**: `POST /api/user/password`
- **Permission**: `user.update`

**Request Parameters**:
- `current_password` (required, string): Current password
- `new_password` (required, string): New password (must meet strength requirements)
- `confirm_password` (required, string): Password confirmation

**Password Requirements**: Same as user creation

**Process Flow**:
1. Validates current password
2. Validates new password strength
3. Hashes and updates password
4. Returns success message

## API Endpoints

### Admin Endpoints

All admin endpoints are prefixed with `/api/admin` and require authentication.

| Method | Endpoint | Permission | Description |
|--------|----------|------------|-------------|
| GET | `/admin/users` | `admin.read` | List all users |
| GET | `/admin/users/{id}` | `admin.read` | Get user details |
| POST | `/admin/users` | `admin.create` | Create new user |
| PUT | `/admin/users/{id}` | `admin.update` | Update user |
| DELETE | `/admin/users/{id}` | `admin.delete` | Delete user |
| GET | `/admin/roles` | `admin.read` | List all roles |
| GET | `/admin/permissions` | `admin.read` | List all permissions |

### User Profile Endpoints

All user endpoints are prefixed with `/api/user` and require authentication.

| Method | Endpoint | Permission | Description |
|--------|----------|------------|-------------|
| GET | `/user/user` | `user.read` | Get authenticated user |
| POST | `/user/profile-picture` | `user.update` | Update profile picture |
| POST | `/user/username` | `user.update` | Update username |
| POST | `/user/email` | `user.update` | Update email |
| POST | `/user/password` | `user.update` | Update password |

## Authentication & Authorization

### Authentication

All endpoints require authentication via Laravel Sanctum. The authentication token must be included in the request header:

```
Authorization: Bearer {token}
```

### Authorization

The system uses permission-based authorization. Each endpoint requires specific permissions:

- **Admin Operations**: Require `admin.*` permissions
- **User Profile Operations**: Require `user.*` permissions

### Middleware

Routes are protected by:
1. `auth:sanctum`: Ensures user is authenticated
2. `permission:*`: Ensures user has required permission

**Example Route Definition**:
```php
Route::get('/users', [AdminController::class, 'index'])
    ->middleware('permission:admin.read');
```

## Role-Based Permissions

### Available Roles

1. **admin**: Full system access
2. **hr-manager**: HR management access
3. **hr-assistant**: HR assistant access
4. **employee**: Limited employee access

### Default Permissions

The system assigns default permissions based on roles:

#### Employee Role
- `user.read`
- `user.update`
- `attendance.create`
- `attendance.read`
- `travel_request.create`
- `travel_request.read`
- `leave_request.create`
- `leave_request.read`

#### Admin, HR Manager, HR Assistant Roles
- All permissions (full access)

### Permission Structure

Permissions follow the pattern: `{module}.{action}`

**Examples**:
- `user.read`: Read user data
- `user.create`: Create users
- `user.update`: Update users
- `user.delete`: Delete users
- `admin.read`: Read admin data
- `attendance.create`: Create attendance records

## Profile Management

### Profile Picture Storage

- **Storage Disk**: `public`
- **Storage Path**: `profile_pictures/{filename}`
- **Max File Size**: 2MB (2048KB)
- **Allowed Types**: Image files (validated by Laravel)

### Profile Picture URL

Profile pictures are accessible via:
```
{APP_URL}/storage/profile_pictures/{filename}
```

## Validation Rules

### User Creation

```php
[
    'name' => 'required|string|max:255',
    'email' => 'required|email|unique:users,email',
    'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
    'password_confirmation' => 'required|string',
    'role' => 'required|string|in:admin,hr-manager,hr-assistant,employee',
    'permissions' => 'nullable|array',
    'permissions.*' => 'string',
    'profile_picture' => 'nullable|image|max:2048',
]
```

### User Update

```php
[
    'role' => 'nullable|string|in:admin,hr-manager,hr-assistant,employee',
    'permissions' => 'nullable|array',
    'permissions.*' => 'string|exists:permissions,name',
    'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
    'password_confirmation' => 'required|string',
]
```

### Profile Updates

**Username**:
```php
['name' => 'required|string|max:255']
```

**Email**:
```php
['email' => ['required', 'email', Rule::unique('users')->ignore($user->id)]]
```

**Password**:
```php
[
    'current_password' => 'required|string',
    'new_password' => 'required|string|min:8|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
    'confirm_password' => 'required|string|same:new_password',
]
```

**Profile Picture**:
```php
['profile_picture' => 'required|image|max:2048']
```

## Error Handling

### Standard Error Responses

All endpoints return consistent error responses:

**Validation Error (422)**:
```json
{
    "success": false,
    "message": "The given data was invalid",
    "errors": {
        "email": ["The email has already been taken."]
    }
}
```

**Not Found (404)**:
```json
{
    "message": "User not found"
}
```

**Server Error (500)**:
```json
{
    "success": false,
    "message": "Error creating user",
    "data": {
        "error": "Error message details"
    }
}
```

**Unauthorized (401)**:
```json
{
    "message": "Unauthenticated"
}
```

**Forbidden (403)**:
```json
{
    "message": "Forbidden"
}
```

### Transaction Management

All database operations that modify user data use database transactions to ensure data integrity:

```php
DB::beginTransaction();
try {
    // Operations
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
    // Error handling
}
```

## Database Transactions

The system uses database transactions for:
- User creation (with role and permission assignment)
- User updates (role, permissions, password changes)
- User deletion (with role/permission cleanup)

This ensures data consistency and allows rollback in case of errors.

## File Storage

### Profile Pictures

- **Storage**: Laravel's public disk
- **Path**: `storage/app/public/profile_pictures/`
- **Public Access**: Via `storage/profile_pictures/` symlink
- **Cleanup**: Old profile pictures are deleted when new ones are uploaded

## Security Considerations

1. **Password Hashing**: All passwords are hashed using Laravel's bcrypt
2. **Token Authentication**: Sanctum tokens for API authentication
3. **Permission Checks**: All endpoints verify user permissions
4. **Input Validation**: Comprehensive validation on all inputs
5. **File Upload Security**: Image validation and size limits
6. **SQL Injection Protection**: Eloquent ORM prevents SQL injection
7. **XSS Protection**: Laravel's built-in XSS protection

## Testing

When testing user management endpoints:

1. Ensure test user has appropriate permissions
2. Use factories for creating test users
3. Clean up test data after tests
4. Test both success and failure scenarios
5. Verify permission checks work correctly

## Best Practices

1. **Always use transactions** for multi-step operations
2. **Validate permissions** before performing operations
3. **Hash passwords** before storing
4. **Clean up files** when deleting users or updating profile pictures
5. **Use eager loading** to prevent N+1 query problems
6. **Return consistent response formats**
7. **Handle errors gracefully** with appropriate HTTP status codes

## Related Documentation

- [Laravel Sanctum Documentation](https://laravel.com/docs/sanctum)
- [Spatie Permission Documentation](https://spatie.be/docs/laravel-permission)
- [Laravel Authentication](https://laravel.com/docs/authentication)
- [Laravel File Storage](https://laravel.com/docs/filesystem)

