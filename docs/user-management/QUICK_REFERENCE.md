# User Management Quick Reference

## API Endpoints Cheat Sheet

### Authentication
```
POST   /api/v1/login                    # Login
POST   /api/v1/logout                   # Logout (auth required)
POST   /api/v1/refresh-token            # Refresh token (auth required)
```

### User Profile
```
GET    /api/v1/user/user                # Get current user (auth required)
POST   /api/v1/user/profile-picture     # Update profile picture (auth required)
POST   /api/v1/user/username            # Update username (auth required)
POST   /api/v1/user/email               # Update email (auth required)
POST   /api/v1/user/password            # Update password (auth required)
```

### Admin User Management
```
GET    /api/v1/admin/users              # List all users (admin.read required)
POST   /api/v1/admin/users              # Create user (admin.create required)
GET    /api/v1/admin/users/{id}         # Get user details (NOT IMPLEMENTED)
PUT    /api/v1/admin/users/{id}         # Update user (admin.update required)
DELETE /api/v1/admin/users/{id}         # Delete user (admin.delete required)
GET    /api/v1/admin/roles              # List roles (NOT IMPLEMENTED)
GET    /api/v1/admin/permissions        # List permissions (NOT IMPLEMENTED)
```

### Activity Logs
```
GET    /api/v1/activity-logs            # Paginated logs (admin.read required)
GET    /api/v1/activity-logs/recent     # Recent logs (admin.read required)
GET    /api/v1/activity-logs/subject/{type}/{id}  # Logs for specific entity
```

---

## Default Users

| Email | Password | Role | Access Level |
|-------|----------|------|--------------|
| admin@hrms.com | password | admin | Full access |
| hrmanager@hrms.com | password | hr-manager | Full access |
| hrassistant.senior@hrms.com | password | hr-assistant-senior | All except grants |
| hrassistant.junior@hrms.com | password | hr-assistant-junior | Limited access |
| siteadmin@hrms.com | password | site-admin | Site operations only |

---

## Roles & Permissions

### Permission Format
`{module}.{action}`

**Modules** (21):
admin, user, grant, interview, employee, employment, employment_history, children, questionnaire, language, reference, education, payroll, attendance, training, reports, travel_request, leave_request, job_offer, budget_line, tax, personnel_action

**Actions** (7):
create, read, update, delete, import, export, bulk_create

**Total**: 147 permissions

### Role Permissions

| Role | Permissions | Count |
|------|-------------|-------|
| admin | ALL | 147 |
| hr-manager | ALL | 147 |
| hr-assistant-senior | ALL EXCEPT grant.* | 140 |
| hr-assistant-junior | ALL EXCEPT grant.*, employment.*, payroll.*, reports.* | 112 |
| site-admin | ONLY leave_request.*, travel_request.*, training.* | 21 |

---

## Frontend File Paths

### Pages
```
src/views/pages/authentication/
  ├── login-index.vue              # Login page
  ├── forgot-password.vue          # Password recovery
  └── reset-password.vue           # Password reset

src/views/pages/administration/user-management/
  ├── user-management.vue          # Router outlet
  ├── user-list.vue                # User listing
  ├── roles-permission.vue         # Roles management
  └── permission-index.vue         # Permission grid
```

### Components
```
src/components/modal/
  ├── user-list-modal.vue          # User CRUD modal
  └── roles-modal.vue              # Role CRUD modal
```

### State Management
```
src/stores/
  ├── authStore.js                 # Authentication state
  ├── adminStore.js                # Admin/user state
  └── userStore.js                 # Basic user store
```

### Services
```
src/services/
  ├── api.service.js               # Base HTTP client
  ├── auth.service.js              # Auth API calls
  └── admin.service.js             # Admin API calls
```

### Router
```
src/router/
  ├── index.js                     # Route definitions
  └── guards.js                    # Auth/role/permission guards
```

---

## Backend File Paths

### Controllers
```
app/Http/Controllers/Api/
  ├── AuthController.php           # Login, logout, refresh
  ├── UserController.php           # User profile operations
  ├── AdminController.php          # User CRUD (admin)
  └── ActivityLogController.php    # Activity logging
```

### Models
```
app/Models/
  ├── User.php                     # User model
  └── ActivityLog.php              # Activity log model
```

### Migrations
```
database/migrations/
  ├── 0001_01_01_000000_create_users_table.php
  ├── 2025_02_12_015944_create_permission_tables.php
  └── 2025_03_03_092449_create_default_user_and_roles.php
```

### Routes
```
routes/api/
  ├── admin.php                    # Admin routes
  └── administration.php           # Administration routes
```

---

## Common Code Snippets

### Frontend - Login Component

```vue
<script>
import { useAuthStore } from '@/stores/authStore'
import { useVuelidate } from '@vuelidate/core'
import { required, email, minLength } from '@vuelidate/validators'

export default {
  setup() {
    const authStore = useAuthStore()
    return { authStore, v$: useVuelidate() }
  },

  data() {
    return {
      formData: { email: '', password: '' },
      loading: false,
      error: null
    }
  },

  validations() {
    return {
      formData: {
        email: { required, email },
        password: { required, minLength: minLength(6) }
      }
    }
  },

  methods: {
    async handleLogin() {
      const isValid = await this.v$.$validate()
      if (!isValid) return

      this.loading = true
      try {
        await this.authStore.login(this.formData)
        this.$router.replace(this.authStore.getRedirectPath())
      } catch (err) {
        this.error = err.message
      } finally {
        this.loading = false
      }
    }
  }
}
</script>
```

### Frontend - Create User with FormData

```javascript
async submitNewUser() {
  const formData = new FormData()
  formData.append('name', this.newUser.name)
  formData.append('email', this.newUser.email)
  formData.append('password', this.newUser.password)
  formData.append('password_confirmation', this.newUser.password_confirmation)
  formData.append('role', this.newUser.role)

  // Append permissions array
  this.newUser.permissions.forEach((perm, index) => {
    formData.append(`permissions[${index}]`, perm)
  })

  // Append file if selected
  if (this.selectedFile) {
    formData.append('profile_picture', this.selectedFile)
  }

  try {
    await adminStore.createUser(formData)
    this.showAlert('User created successfully', 'success')
  } catch (err) {
    this.showAlert(err.message, 'danger')
  }
}
```

### Backend - Create User

```php
// app/Http/Controllers/Api/AdminController.php

public function store(Request $request): JsonResponse
{
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
        'role' => 'required|string|in:admin,hr-manager,hr-assistant-senior,hr-assistant-junior,site-admin',
        'permissions' => 'nullable|array',
        'profile_picture' => 'nullable|image|max:2048',
    ]);

    DB::beginTransaction();

    try {
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'], // Auto-hashed
            'created_by' => auth()->id(),
        ]);

        // Store profile picture
        if ($request->hasFile('profile_picture')) {
            $path = $request->file('profile_picture')
                ->store('profile_pictures', 'public');
            $user->profile_picture = $path;
            $user->save();
        }

        // Assign role
        $user->assignRole($validated['role']);

        // Sync permissions
        if (!empty($validated['permissions'])) {
            $user->syncPermissions($validated['permissions']);
        }

        DB::commit();

        return response()->json([
            'message' => 'User created successfully',
            'user' => $user->load('roles', 'permissions')
        ], 201);

    } catch (\Exception $e) {
        DB::rollBack();
        return response()->json([
            'message' => 'Failed to create user',
            'error' => $e->getMessage()
        ], 500);
    }
}
```

### Backend - Check Permission

```php
// In routes
Route::get('/admin/users', [AdminController::class, 'index'])
    ->middleware(['auth:sanctum', 'permission:admin.read']);

// In controller
if (!auth()->user()->hasPermissionTo('user.create')) {
    abort(403, 'Unauthorized');
}

// In model
if ($user->hasRole('admin')) {
    // Admin logic
}
```

### Frontend - Route Guard

```javascript
// src/router/guards.js

export function roleGuard(allowedRoles) {
  return (to, from, next) => {
    const userRole = localStorage.getItem('userRole')

    if (!userRole) {
      next('/login')
      return
    }

    const hasRole = allowedRoles.some(role =>
      role.toLowerCase() === userRole.toLowerCase()
    )

    if (hasRole) {
      next()
    } else {
      next('/unauthorized')
    }
  }
}

// Usage in routes
{
  path: '/dashboard/admin-dashboard',
  component: AdminDashboard,
  beforeEnter: roleGuard(['admin']),
  meta: { requiresAuth: true }
}
```

---

## Validation Rules

### Password Requirements

**Backend Regex**:
```php
'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/'
```

**Requirements**:
- Minimum 8 characters
- At least one uppercase letter
- At least one lowercase letter
- At least one number
- At least one special character (@$!%*?&)

### Email Validation

```php
'email' => 'required|email|unique:users,email'
```

### Profile Picture

```php
'profile_picture' => 'nullable|image|max:2048' // Max 2MB
```

---

## Database Schema Quick Reference

### Users Table

```sql
id                    BIGINT UNSIGNED PRIMARY KEY
name                  VARCHAR(255) NOT NULL
email                 VARCHAR(255) UNIQUE NOT NULL
email_verified_at     TIMESTAMP NULL
password              VARCHAR(255) NOT NULL
phone                 VARCHAR(255) NULL
status                VARCHAR(255) DEFAULT 'active'
last_login_at         TIMESTAMP NULL
last_login_ip         VARCHAR(255) NULL
profile_picture       VARCHAR(255) NULL
created_by            VARCHAR(255) NULL
updated_by            VARCHAR(255) NULL
remember_token        VARCHAR(100) NULL
created_at            TIMESTAMP NULL
updated_at            TIMESTAMP NULL
```

### Roles Table (Spatie)

```sql
id            BIGINT UNSIGNED PRIMARY KEY
name          VARCHAR(255) NOT NULL
guard_name    VARCHAR(255) NOT NULL
created_at    TIMESTAMP NULL
updated_at    TIMESTAMP NULL
UNIQUE (name, guard_name)
```

### Permissions Table (Spatie)

```sql
id            BIGINT UNSIGNED PRIMARY KEY
name          VARCHAR(255) NOT NULL
guard_name    VARCHAR(255) NOT NULL
created_at    TIMESTAMP NULL
updated_at    TIMESTAMP NULL
UNIQUE (name, guard_name)
```

### Activity Logs Table

```sql
id              BIGINT UNSIGNED PRIMARY KEY
user_id         BIGINT UNSIGNED NULL (FK to users)
action          VARCHAR(255) NOT NULL
subject_type    VARCHAR(255) NOT NULL (polymorphic)
subject_id      BIGINT UNSIGNED NOT NULL (polymorphic)
subject_name    VARCHAR(255) NULL
description     TEXT NULL
properties      JSON NULL
ip_address      VARCHAR(45) NULL
created_at      TIMESTAMP NULL
```

---

## Environment Variables

### Backend (.env)

```env
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hrms
DB_USERNAME=root
DB_PASSWORD=

SANCTUM_STATEFUL_DOMAINS=localhost:8080,127.0.0.1:8080

REVERB_APP_ID=your-app-id
REVERB_APP_KEY=your-app-key
REVERB_APP_SECRET=your-app-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
```

### Frontend (.env)

```env
VUE_APP_API_BASE_URL=http://localhost:8000/api/v1

VUE_APP_REVERB_APP_KEY=your-app-key
VUE_APP_REVERB_HOST=localhost
VUE_APP_REVERB_PORT=8080
VUE_APP_REVERB_SCHEME=http
```

---

## Common Issues & Solutions

### 1. 401 Unauthorized After Login

**Cause**: Token not set in API service headers

**Solution**:
```javascript
// In authStore.setAuthData
apiService.setAuthToken(access_token)
```

### 2. CORS Errors

**Cause**: Backend not configured for frontend origin

**Solution** (Laravel `config/cors.php`):
```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => [
    'http://localhost:8080',
    'http://127.0.0.1:8080'
],
'supports_credentials' => true,
```

### 3. File Upload Fails

**Cause**: Sending FormData with JSON Content-Type header

**Solution**:
```javascript
// Remove Content-Type header - browser sets it automatically
delete headers['Content-Type']

return fetch(url, {
  method: 'POST',
  headers,
  body: formData
})
```

### 4. Token Expiration Not Working

**Cause**: Timer not set or cleared on logout

**Solution**:
```javascript
setTokenTimer(duration) {
  if (this.tokenTimer) {
    clearTimeout(this.tokenTimer)
  }

  this.tokenTimer = setTimeout(() => {
    this.logout()
  }, duration)
}
```

### 5. Permission Check Fails

**Cause**: Permissions not loaded with user

**Solution** (Backend):
```php
$user->load('roles', 'permissions');
return $user;
```

---

## Testing

### Backend - Artisan Commands

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/UserManagementTest.php

# Run with filter
php artisan test --filter=test_user_can_login

# Create test
php artisan make:test UserManagementTest
php artisan make:test UserTest --unit
```

### Frontend - Jest/Vitest

```bash
# Run all tests
npm run test

# Run specific test
npm run test -- UserList.spec.js

# Run with coverage
npm run test:coverage

# Watch mode
npm run test:watch
```

### Example Pest Test (Backend)

```php
it('can create a user with role', function () {
    $admin = User::factory()->create()->assignRole('admin');

    $response = $this->actingAs($admin)->postJson('/api/v1/admin/users', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'role' => 'hr-manager'
    ]);

    $response->assertCreated()
        ->assertJsonStructure(['message', 'user']);

    $this->assertDatabaseHas('users', [
        'email' => 'test@example.com'
    ]);
});
```

---

## Performance Tips

### Backend

1. **Eager Load Relationships**
```php
$users = User::with('roles', 'permissions')->get();
```

2. **Use Pagination**
```php
$users = User::paginate(20);
```

3. **Cache Roles & Permissions**
```php
$permissions = Cache::remember('permissions', 3600, function () {
    return Permission::all();
});
```

### Frontend

1. **Lazy Load Routes**
```javascript
{
  path: '/user-management',
  component: () => import('@/views/pages/administration/user-management/user-list.vue')
}
```

2. **Debounce Search**
```javascript
import { debounce } from 'lodash'

methods: {
  searchUsers: debounce(function(query) {
    // Search logic
  }, 300)
}
```

3. **Virtual Scrolling for Large Lists**
```vue
<virtual-list
  :data-sources="users"
  :data-component="UserListItem"
  :page-mode="true"
/>
```

---

## Useful Commands

### Laravel

```bash
# Clear all caches
php artisan optimize:clear

# Create migration
php artisan make:migration create_users_table

# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Create controller
php artisan make:controller Api/UserController

# Create model with migration & factory
php artisan make:model User -mf

# Run Pint (code formatter)
vendor/bin/pint

# List all routes
php artisan route:list
```

### Vue

```bash
# Install dependencies
npm install

# Run dev server
npm run dev

# Build for production
npm run build

# Lint and fix files
npm run lint
```

---

## Security Checklist

### Backend
- [ ] Validate all input (never trust client)
- [ ] Use prepared statements (Eloquent does this)
- [ ] Hash passwords (Laravel 11 auto-hashes)
- [ ] Protect against XSS (escape output)
- [ ] Implement CSRF protection (Sanctum handles this)
- [ ] Use HTTPS in production
- [ ] Rate limit API endpoints
- [ ] Log security events

### Frontend
- [ ] Validate input before sending
- [ ] Don't store sensitive data in localStorage
- [ ] Sanitize user input before display
- [ ] Use HTTPS for API calls
- [ ] Implement timeout for idle sessions
- [ ] Clear auth data on logout
- [ ] Check permissions before rendering UI

---

## Resources

### Documentation
- [Laravel 11 Docs](https://laravel.com/docs/11.x)
- [Vue 3 Docs](https://vuejs.org/guide/introduction.html)
- [Pinia Docs](https://pinia.vuejs.org/)
- [Spatie Permission Docs](https://spatie.be/docs/laravel-permission/v6/introduction)
- [Laravel Sanctum Docs](https://laravel.com/docs/11.x/sanctum)
- [Vuelidate Docs](https://vuelidate-next.netlify.app/)

### Internal Documentation
- [User Management Overview](./USER_MANAGEMENT_OVERVIEW.md)
- [Backend API Reference](./BACKEND_API_REFERENCE.md)
- [Frontend Components Guide](./FRONTEND_COMPONENTS_GUIDE.md)
- [API Integration Guide](./API_INTEGRATION_GUIDE.md)

---

**Last Updated**: 2025-12-17
**Version**: 1.0
