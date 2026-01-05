# User Management System - Complete Overview

## Table of Contents
1. [System Architecture](#system-architecture)
2. [Technology Stack](#technology-stack)
3. [Key Features](#key-features)
4. [Authentication Flow](#authentication-flow)
5. [Authorization Model](#authorization-model)
6. [Data Flow](#data-flow)
7. [File Structure](#file-structure)
8. [Quick Links](#quick-links)

---

## System Architecture

The HRMS User Management System is a full-stack solution with:
- **Backend**: Laravel 11 REST API with Sanctum authentication
- **Frontend**: Vue 3 with Pinia state management
- **Authorization**: Spatie Permission package (role-based + permission-based)
- **Real-time**: Laravel Reverb with Echo for notifications

```
┌─────────────────────────────────────────────────────────────┐
│                    FRONTEND (Vue 3)                         │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐           │
│  │   Pages    │  │   Stores   │  │  Services  │           │
│  │  (Views)   │─▶│  (Pinia)   │─▶│ (API Calls)│           │
│  └────────────┘  └────────────┘  └────────────┘           │
│         │              │                 │                  │
│         └──────────────┴─────────────────┘                  │
│                        │                                     │
└────────────────────────┼─────────────────────────────────────┘
                         │ HTTP/JSON + Sanctum Token
┌────────────────────────┼─────────────────────────────────────┐
│                        │                                     │
│  ┌─────────────────────▼──────────────────────────┐         │
│  │         API Routes (Middleware)                │         │
│  │   auth:sanctum, role:admin, permission:*      │         │
│  └─────────────────────┬──────────────────────────┘         │
│                        │                                     │
│  ┌─────────────────────▼──────────────────────────┐         │
│  │            Controllers                          │         │
│  │  AuthController │ UserController │ AdminController       │
│  └─────────────────────┬──────────────────────────┘         │
│                        │                                     │
│  ┌─────────────────────▼──────────────────────────┐         │
│  │            Models + Relationships                │         │
│  │   User ──▶ Roles ──▶ Permissions               │         │
│  └─────────────────────┬──────────────────────────┘         │
│                        │                                     │
│  ┌─────────────────────▼──────────────────────────┐         │
│  │              Database (MySQL)                   │         │
│  │  users, roles, permissions, model_has_roles    │         │
│  └─────────────────────────────────────────────────┘         │
│                                                              │
│                  BACKEND (Laravel 11)                        │
└──────────────────────────────────────────────────────────────┘
```

---

## Technology Stack

### Backend
- **Framework**: Laravel 11
- **Authentication**: Laravel Sanctum (token-based API auth)
- **Authorization**: Spatie Laravel-Permission v6
- **Database**: MySQL
- **PHP Version**: 8.2.29
- **Activity Logging**: Custom LogsActivity trait

### Frontend
- **Framework**: Vue 3 (Composition + Options API)
- **State Management**: Pinia
- **Routing**: Vue Router 4
- **UI Framework**: Bootstrap 5 + Ant Design Vue 4
- **Form Validation**: Vuelidate 2.0
- **HTTP Client**: Custom Fetch-based API Service
- **Real-time**: Laravel Echo with Reverb

---

## Key Features

### Authentication
- ✅ Email/Password login with rate limiting (5 attempts/minute)
- ✅ Token-based authentication (6-hour expiration)
- ✅ Token refresh mechanism
- ✅ Auto-logout on token expiration
- ✅ Login tracking (last_login_at, last_login_ip)
- ✅ Password strength validation (min 8 chars, uppercase, lowercase, number, special char)
- ✅ Remember me functionality
- ✅ Intended route preservation

### User Management (CRUD)
- ✅ List all users with pagination and filtering
- ✅ Create new users with role and permission assignment
- ✅ Update user profile, email, password
- ✅ Update user roles and permissions
- ✅ Delete users (with role/permission cleanup)
- ✅ Profile picture upload and management

### Role & Permission Management
- ✅ 5 predefined roles (admin, hr-manager, hr-assistant-senior, hr-assistant-junior, site-admin)
- ✅ 147 default permissions (21 modules × 7 actions)
- ✅ Role-based permission inheritance
- ✅ Direct permission assignment to users
- ✅ Permission grid UI for easy management

### Authorization
- ✅ Route-level authorization with middleware
- ✅ Role-based access control (RBAC)
- ✅ Permission-based access control (PBAC)
- ✅ Combined role OR permission checks
- ✅ Frontend route guards

### Activity Logging
- ✅ Automatic logging of all user actions
- ✅ Track create, update, delete operations
- ✅ IP address tracking
- ✅ Old/new value comparison
- ✅ Activity log filtering and search

### Security Features
- ✅ Rate limiting on authentication endpoints
- ✅ Password hashing (Laravel Hash facade)
- ✅ Token expiration with auto-refresh
- ✅ CORS configuration
- ✅ SQL injection prevention (Eloquent ORM)
- ✅ XSS protection (Laravel sanitization)

---

## Authentication Flow

### 1. Login Process

```
┌─────────────┐
│   User      │
│  Submits    │
│ Credentials │
└──────┬──────┘
       │
       ▼
┌─────────────────────────┐
│  Frontend Validation    │
│  (Vuelidate)            │
│  - Email format         │
│  - Password min length  │
└──────┬──────────────────┘
       │
       ▼
┌─────────────────────────┐
│  POST /api/v1/login     │
│  {email, password}      │
└──────┬──────────────────┘
       │
       ▼
┌─────────────────────────┐
│  AuthController@login   │
│  - Rate limiting check  │
│  - Validate credentials │
│  - Update login tracking│
└──────┬──────────────────┘
       │
       ▼
┌─────────────────────────┐
│  Create Sanctum Token   │
│  - 6 hour expiration    │
│  - Load roles/perms     │
└──────┬──────────────────┘
       │
       ▼
┌─────────────────────────┐
│  Response               │
│  {                      │
│    access_token,        │
│    token_type,          │
│    expires_in,          │
│    user: {              │
│      name, email,       │
│      roles: [],         │
│      permissions: []    │
│    }                    │
│  }                      │
└──────┬──────────────────┘
       │
       ▼
┌─────────────────────────┐
│  Frontend Processing    │
│  - Store in localStorage│
│  - Set auth header      │
│  - Initialize Echo      │
│  - Set auto-logout timer│
└──────┬──────────────────┘
       │
       ▼
┌─────────────────────────┐
│  Redirect to Dashboard  │
│  (Role-based routing)   │
└─────────────────────────┘
```

### 2. Token Refresh Flow

```
API Request with expired token (401)
    ↓
Frontend detects 401
    ↓
POST /refresh-token with current token
    ↓
Backend validates and creates new token
    ↓
Frontend stores new token
    ↓
Retry original request with new token
```

### 3. Logout Flow

```
POST /logout
    ↓
Backend revokes Sanctum token
    ↓
Frontend clears localStorage
    ↓
Reset all Pinia stores
    ↓
Disconnect Echo
    ↓
Clear auto-logout timer
    ↓
Redirect to /login
```

---

## Authorization Model

### Permission Structure

**Format**: `{module}.{action}`

**Modules** (21 total):
- admin, user, grant, interview, employee, employment, employment_history
- children, questionnaire, language, reference, education
- payroll, attendance, training, reports
- travel_request, leave_request, job_offer, budget_line
- tax, personnel_action

**Actions** (7 per module):
- create, read, update, delete, import, export, bulk_create

**Total Permissions**: 21 modules × 7 actions = **147 permissions**

### Roles & Default Permissions

| Role | Permissions | Dashboard Access |
|------|-------------|------------------|
| **admin** | ALL (147 permissions) | Admin Dashboard (full access) |
| **hr-manager** | ALL (147 permissions) | HR Manager Dashboard |
| **hr-assistant-senior** | All EXCEPT grant.* | HR Assistant Senior Dashboard |
| **hr-assistant-junior** | All EXCEPT grant.*, employment.*, payroll.*, reports.* | HR Assistant Junior Dashboard |
| **site-admin** | Only leave_request.*, travel_request.*, training.* | Site Admin Dashboard |

### Middleware Authorization

**Backend (Laravel)**:
```php
// Single permission check
Route::middleware(['auth:sanctum', 'permission:user.create']);

// Single role check
Route::middleware(['auth:sanctum', 'role:admin']);

// Combined (role OR permission)
Route::middleware(['auth:sanctum', 'role_or_permission:admin,user.read']);
```

**Frontend (Vue Router)**:
```javascript
// Role guard
beforeEnter: roleGuard(['admin', 'hr-manager'])

// Permission guard
beforeEnter: permissionGuard('user.create')

// Global auth guard
router.beforeEach(authGuard)
```

---

## Data Flow

### User Creation Flow

```
Frontend Form (user-list-modal.vue)
    ↓
FormData: {
  name, email, password, password_confirmation,
  role, permissions[], profile_picture
}
    ↓
adminStore.createUser(formData)
    ↓
adminService.createUser(formData)
    ↓
apiService.postFormData('/admin/users', formData)
    ↓
POST /api/v1/admin/users
    ↓
AdminController@store
    ↓
Validation:
  - name: required|string|max:255
  - email: required|email|unique:users
  - password: required|min:8|regex (strength)
  - role: required|string|in:[roles]
    ↓
Database Transaction:
  1. Create User record
  2. Hash password
  3. Save profile picture
  4. Assign role (assignRole)
  5. Sync permissions
  6. Set created_by
    ↓
Response: { message, user }
    ↓
Frontend refreshes user list
    ↓
Modal closes with success message
```

### User Update Flow

```
Frontend Edit Form
    ↓
FormData with _method=PUT: {
  role, permissions[], password?, profile_picture?
}
    ↓
adminStore.updateUser(id, formData)
    ↓
PUT /api/v1/admin/users/{id}
    ↓
AdminController@update
    ↓
Database Transaction:
  1. Find user
  2. Detach all roles
  3. Assign new role
  4. Sync new permissions
  5. Update password (if provided)
  6. Update profile picture (if provided)
    ↓
Response: { message, user }
    ↓
Frontend refreshes user list
```

### Role-Based Dashboard Redirect

```
User logs in successfully
    ↓
authStore.setAuthData(response)
    ↓
Extract primary role from user.roles[0]
    ↓
authStore.getRedirectPath()
    ↓
Role mapping:
  - admin → /dashboard/admin-dashboard
  - hr-manager → /dashboard/hr-manager-dashboard
  - hr-assistant-senior → /dashboard/hr-assistant-senior-dashboard
  - hr-assistant-junior → /dashboard/hr-assistant-junior-dashboard
  - site-admin → /dashboard/site-admin-dashboard
  - default → /dashboard
    ↓
router.replace(redirectPath || intendedRoute || '/dashboard')
```

---

## File Structure

### Backend Structure

```
hrms-backend-api-v1/
├── app/
│   ├── Http/
│   │   ├── Controllers/Api/
│   │   │   ├── AuthController.php         # Login, logout, refresh token
│   │   │   ├── UserController.php         # User profile operations
│   │   │   ├── AdminController.php        # User CRUD, roles, permissions
│   │   │   └── ActivityLogController.php  # Activity logging
│   │   ├── Requests/                      # (Not implemented - inline validation)
│   │   └── Resources/                     # (Not implemented - raw models)
│   ├── Models/
│   │   ├── User.php                       # User model with HasRoles trait
│   │   └── ActivityLog.php                # Activity logging model
│   ├── Traits/
│   │   └── LogsActivity.php               # Automatic activity logging
│   └── Services/                          # (No user service - logic in controllers)
├── database/
│   ├── migrations/
│   │   ├── 0001_01_01_000000_create_users_table.php
│   │   ├── 2025_02_12_015944_create_permission_tables.php
│   │   └── 2025_03_03_092449_create_default_user_and_roles.php
│   ├── seeders/
│   │   ├── UserSeeder.php                 # Default users
│   │   └── DatabaseSeeder.php
│   └── factories/
│       └── UserFactory.php                # User testing factory
├── routes/
│   ├── api.php                            # Main API routes
│   └── api/
│       ├── admin.php                      # Admin & user management routes
│       └── administration.php             # Administration routes
├── bootstrap/
│   └── app.php                            # Middleware configuration
└── docs/
    └── user-management/                   # This documentation
```

### Frontend Structure

```
hrms-frontend-dev/
├── src/
│   ├── views/pages/
│   │   ├── authentication/
│   │   │   ├── login-index.vue            # Login page
│   │   │   ├── forgot-password.vue        # Password recovery
│   │   │   └── reset-password.vue         # Password reset
│   │   └── administration/user-management/
│   │       ├── user-management.vue        # Router outlet
│   │       ├── user-list.vue              # User listing page
│   │       ├── roles-permission.vue       # Roles management
│   │       └── permission-index.vue       # Permission grid
│   ├── components/modal/
│   │   ├── user-list-modal.vue            # User CRUD modal
│   │   └── roles-modal.vue                # Role CRUD modal
│   ├── stores/
│   │   ├── authStore.js                   # Auth state (Pinia)
│   │   ├── adminStore.js                  # Admin/user state (Pinia)
│   │   └── userStore.js                   # Basic user store (fallback)
│   ├── services/
│   │   ├── api.service.js                 # Base HTTP client
│   │   ├── auth.service.js                # Auth API calls
│   │   └── admin.service.js               # Admin API calls
│   ├── router/
│   │   ├── index.js                       # Route definitions
│   │   └── guards.js                      # Auth/role/permission guards
│   ├── composables/
│   │   └── useEventBus.js                 # Event bus composable
│   ├── constants/
│   │   └── storageKeys.js                 # localStorage key constants
│   ├── config/
│   │   └── api.config.js                  # API endpoints configuration
│   └── plugins/
│       └── echo.js                        # Laravel Echo configuration
└── package.json
```

---

## Quick Links

### Documentation
- [Backend API Reference](./BACKEND_API_REFERENCE.md)
- [Frontend Components Guide](./FRONTEND_COMPONENTS_GUIDE.md)
- [API Integration Guide](./API_INTEGRATION_GUIDE.md)
- [Quick Reference](./QUICK_REFERENCE.md)

### Key Files
- Backend User Model: `app/Models/User.php`
- Backend Auth Controller: `app/Http/Controllers/Api/AuthController.php`
- Backend Admin Controller: `app/Http/Controllers/Api/AdminController.php`
- Frontend Auth Store: `src/stores/authStore.js`
- Frontend User List: `src/views/pages/administration/user-management/user-list.vue`
- Frontend Login Page: `src/views/pages/authentication/login-index.vue`

### API Endpoints
```
POST   /api/v1/login                    # Login
POST   /api/v1/logout                   # Logout
POST   /api/v1/refresh-token            # Refresh token
GET    /api/v1/user/user                # Get current user
GET    /api/v1/admin/users              # List all users
POST   /api/v1/admin/users              # Create user
PUT    /api/v1/admin/users/{id}         # Update user
DELETE /api/v1/admin/users/{id}         # Delete user
GET    /api/v1/admin/roles              # List all roles (NOT IMPLEMENTED)
GET    /api/v1/admin/permissions        # List all permissions (NOT IMPLEMENTED)
```

---

## Known Issues & Gaps

### Backend
1. ❌ `AdminController@show($id)` - Get specific user details (route exists, method missing)
2. ❌ `AdminController@getRoles()` - List all roles (route exists, method missing)
3. ❌ `AdminController@getPermissions()` - List all permissions (route exists, method missing)
4. ❌ No dedicated Form Request classes (validation is inline in controllers)
5. ❌ No API Resource classes (returning raw models)
6. ❌ No Policy classes (authorization via middleware only)
7. ❌ No User Service class (business logic in controllers)
8. ❌ UserSeeder not called in DatabaseSeeder

### Frontend
9. ❌ Basic HTML5 validation only (no comprehensive Vuelidate on modals)
10. ❌ No dedicated error handling service
11. ❌ Limited TypeScript typing
12. ❌ No user list pagination (loads all users)

---

## Next Steps

### Immediate Priorities
1. Implement missing AdminController methods (show, getRoles, getPermissions)
2. Add pagination to user listing (backend + frontend)
3. Create Form Request classes for proper validation
4. Add API Resource classes for consistent data transformation
5. Implement comprehensive error handling

### Future Enhancements
1. Two-factor authentication (2FA)
2. OAuth social login (Google, GitHub, etc.)
3. Password reset via email
4. Email verification
5. Session management (active sessions, logout all devices)
6. User activity dashboard
7. Audit trail with detailed diff view
8. Role hierarchy and inheritance
9. Custom permission creation UI
10. User impersonation for admins

---

**Last Updated**: 2025-12-17
**Version**: 1.0
**Maintainer**: HRMS Development Team
