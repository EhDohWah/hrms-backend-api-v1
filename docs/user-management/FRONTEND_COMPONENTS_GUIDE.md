# Frontend User Management Components Guide

## Table of Contents
1. [Overview](#overview)
2. [Pages & Views](#pages--views)
3. [Components](#components)
4. [State Management (Pinia Stores)](#state-management-pinia-stores)
5. [Services](#services)
6. [Routing](#routing)
7. [Form Validation](#form-validation)
8. [Utilities & Helpers](#utilities--helpers)

---

## Overview

The frontend user management system is built with **Vue 3** using both Composition API and Options API patterns. It provides a complete user interface for authentication, user CRUD operations, role/permission management, and activity logging.

### Technology Stack
- **Framework**: Vue 3.4+
- **State Management**: Pinia 2
- **Router**: Vue Router 4
- **UI Libraries**: Bootstrap 5, Ant Design Vue 4
- **Form Validation**: Vuelidate 2.0
- **HTTP Client**: Custom Fetch-based API Service
- **Real-time**: Laravel Echo with Reverb

---

## Pages & Views

### Authentication Pages

#### 1. Login Page

**File**: `src/views/pages/authentication/login-index.vue`

**Route**: `/login`

**Purpose**: Main authentication entry point

**Features**:
- Email/password login form
- Form validation (Vuelidate)
- Remember me checkbox
- Password visibility toggle
- Error message display
- Loading state indicator
- Token verification on mount
- Intended route preservation

**Data Properties**:
```javascript
{
  formData: {
    email: '',
    password: ''
  },
  showPassword: false,
  error: null,
  loading: false,
  rememberMe: false
}
```

**Validation Rules** (Vuelidate):
```javascript
validations() {
  return {
    formData: {
      email: {
        required,
        email,
        $autoDirty: true
      },
      password: {
        required,
        minLength: minLength(6),
        $autoDirty: true
      }
    }
  }
}
```

**Methods**:
```javascript
async handleLogin() {
  // Validate form
  const isValid = await this.v$.$validate()
  if (!isValid) return

  this.loading = true
  this.error = null

  try {
    // Call auth store login action
    await authStore.login(this.formData)

    // Redirect to intended route or dashboard
    const intendedRoute = localStorage.getItem('intendedRoute')
    const redirectPath = authStore.getRedirectPath()

    this.$router.replace(intendedRoute || redirectPath)

    // Clear intended route
    localStorage.removeItem('intendedRoute')
  } catch (err) {
    this.error = err.message || 'Login failed'
  } finally {
    this.loading = false
  }
}

async verifyToken(token) {
  // Check if existing token is valid
  try {
    await authService.getCurrentUser()
    // Token valid, redirect to dashboard
    this.$router.replace(authStore.getRedirectPath())
  } catch {
    // Token invalid, stay on login
    authStore.clearAuthData()
  }
}

togglePassword() {
  this.showPassword = !this.showPassword
}
```

**Lifecycle**:
```javascript
mounted() {
  // Check for existing valid token
  const token = authStore.token
  if (token) {
    this.verifyToken(token)
  }
}
```

**Template Highlights**:
```vue
<template>
  <div class="login-container">
    <form @submit.prevent="handleLogin">
      <!-- Email Input -->
      <input
        v-model="v$.formData.email.$model"
        type="email"
        :class="{ 'is-invalid': v$.formData.email.$error }"
      />
      <div v-if="v$.formData.email.$error" class="invalid-feedback">
        {{ v$.formData.email.$errors[0].$message }}
      </div>

      <!-- Password Input with Toggle -->
      <div class="password-field">
        <input
          v-model="v$.formData.password.$model"
          :type="showPassword ? 'text' : 'password'"
          :class="{ 'is-invalid': v$.formData.password.$error }"
        />
        <button type="button" @click="togglePassword">
          <i :class="showPassword ? 'eye-slash' : 'eye'"></i>
        </button>
      </div>

      <!-- Error Alert -->
      <div v-if="error" class="alert alert-danger">
        {{ error }}
      </div>

      <!-- Submit Button -->
      <button type="submit" :disabled="loading">
        <span v-if="loading">Logging in...</span>
        <span v-else>Login</span>
      </button>
    </form>
  </div>
</template>
```

---

#### 2. Forgot Password Page

**File**: `src/views/pages/authentication/forgot-password.vue`

**Route**: `/forgot-password`

**Purpose**: Password reset request

**Features**:
- Email input for password reset
- Email validation
- Success/error message display
- Link back to login

---

#### 3. Reset Password Page

**File**: `src/views/pages/authentication/reset-password.vue`

**Route**: `/reset-password`

**Purpose**: Complete password reset with token

**Features**:
- Token validation from URL
- New password input
- Password confirmation
- Password strength validation

---

### User Management Pages

#### 1. User Management Container

**File**: `src/views/pages/administration/user-management/user-management.vue`

**Route**: `/user-management`

**Purpose**: Router outlet for user management sub-routes

**Template**:
```vue
<template>
  <div class="user-management-container">
    <router-view />
  </div>
</template>
```

**Child Routes**:
- `/user-management` → redirects to `/user-management/users`
- `/user-management/users` → UserList component
- `/user-management/roles-permissions` → RolesPermission component
- `/user-management/permission` → PermissionIndex component

---

#### 2. User List Page

**File**: `src/views/pages/administration/user-management/user-list.vue`

**Route**: `/user-management/users`

**Purpose**: Display and manage users with table interface

**Features**:
- Ant Design Table with pagination
- Column filtering and sorting
- Batch selection
- Search functionality
- Export options (PDF/Excel)
- Add/Edit/Delete operations
- Profile picture display

**Data Properties**:
```javascript
{
  selectedRowKeys: [], // For batch selection
  isHeaderCollapsed: false,
  filters: {
    name: null,
    role: null,
    status: null
  },
  sorters: {},
  tableColumns: [...] // Column definitions
}
```

**Computed Properties**:
```javascript
users() {
  return adminStore.users
}

filteredUsers() {
  // Apply client-side filters
  let users = this.users

  if (this.filters.name) {
    users = users.filter(u =>
      u.name.toLowerCase().includes(this.filters.name.toLowerCase())
    )
  }

  if (this.filters.role) {
    users = users.filter(u =>
      u.roles.some(r => r.name === this.filters.role)
    )
  }

  if (this.filters.status) {
    users = users.filter(u => u.status === this.filters.status)
  }

  return users
}

userRole() {
  return authStore.userRole
}
```

**Table Columns Configuration**:
```javascript
tableColumns: [
  {
    title: 'Name',
    dataIndex: 'name',
    key: 'name',
    sorter: (a, b) => a.name.localeCompare(b.name),
    filteredValue: this.filters.name,
    filterSearch: true,
    filters: this.getNameFilters() // Dynamic name filters
  },
  {
    title: 'Email',
    dataIndex: 'email',
    key: 'email',
    sorter: (a, b) => a.email.localeCompare(b.email)
  },
  {
    title: 'Created Date',
    dataIndex: 'created_at',
    key: 'created_at',
    sorter: (a, b) => new Date(a.created_at) - new Date(b.created_at),
    customRender: ({ record }) => {
      return this.formatDate(record.created_at) // MM DD YYYY
    }
  },
  {
    title: 'Role',
    dataIndex: 'roles',
    key: 'role',
    filters: [
      { text: 'Admin', value: 'admin' },
      { text: 'HR Manager', value: 'hr-manager' },
      { text: 'HR Assistant', value: 'hr-assistant-senior' },
      { text: 'Employee', value: 'site-admin' }
    ],
    customRender: ({ record }) => {
      // Display role badge with color
      const role = record.roles[0]?.name || 'No Role'
      return h('span', { class: `badge role-${role}` }, role)
    }
  },
  {
    title: 'Status',
    dataIndex: 'status',
    key: 'status',
    filters: [
      { text: 'Active', value: 'active' },
      { text: 'Inactive', value: 'inactive' }
    ],
    customRender: ({ record }) => {
      return h('span', {
        class: `badge ${record.status === 'active' ? 'badge-success' : 'badge-danger'}`
      }, record.status)
    }
  },
  {
    title: 'Action',
    key: 'action',
    width: 100,
    customRender: ({ record }) => {
      return h('div', { class: 'action-buttons' }, [
        h('button', {
          class: 'btn btn-sm btn-primary',
          onClick: () => this.editUser(record)
        }, 'Edit'),
        h('button', {
          class: 'btn btn-sm btn-danger',
          onClick: () => this.confirmDeleteUser(record.id)
        }, 'Delete')
      ])
    }
  }
]
```

**Methods**:
```javascript
async fetchUsers() {
  await adminStore.fetchUsers()
}

editUser(record) {
  // Pass user to modal for editing
  this.$refs.userListModal.setEditUser(record)
}

confirmDeleteUser(userId) {
  // Show delete confirmation modal
  this.$refs.userListModal.confirmDelete(userId)
}

handleChange(pagination, filters, sorter) {
  // Handle table filter/sort changes
  this.filters = filters
  this.sorters = sorter
}

clearFilters() {
  this.filters = {
    name: null,
    role: null,
    status: null
  }
}

clearAll() {
  this.clearFilters()
  this.sorters = {}
}

toggleHeader() {
  this.isHeaderCollapsed = !this.isHeaderCollapsed
}

getNameFilters() {
  // Generate dynamic name filter options
  const names = this.users.map(u => u.name)
  return [...new Set(names)].map(name => ({
    text: name,
    value: name
  }))
}

formatDate(date) {
  // Format: MM DD YYYY
  const d = new Date(date)
  return d.toLocaleDateString('en-US', {
    month: '2-digit',
    day: '2-digit',
    year: 'numeric'
  })
}

onSelectChange(selectedRowKeys) {
  this.selectedRowKeys = selectedRowKeys
}

exportPDF() {
  // TODO: Implement PDF export
  console.log('Export PDF')
}

exportExcel() {
  // TODO: Implement Excel export
  console.log('Export Excel')
}
```

**Lifecycle**:
```javascript
async mounted() {
  await this.fetchUsers()
}
```

**Template Structure**:
```vue
<template>
  <div class="user-list-page">
    <!-- Header Section -->
    <div class="page-header" :class="{ collapsed: isHeaderCollapsed }">
      <div class="header-actions">
        <button @click="$refs.userListModal.openAddModal()" class="btn btn-primary">
          <i class="plus-icon"></i> Add User
        </button>
        <button @click="toggleHeader" class="btn btn-outline">
          <i :class="isHeaderCollapsed ? 'expand-icon' : 'collapse-icon'"></i>
        </button>
      </div>

      <div class="filter-section" v-if="!isHeaderCollapsed">
        <!-- Filter controls -->
        <button @click="clearAll" class="btn btn-secondary">Clear All</button>
      </div>
    </div>

    <!-- User Table -->
    <a-table
      :columns="tableColumns"
      :data-source="filteredUsers"
      :row-selection="{
        selectedRowKeys: selectedRowKeys,
        onChange: onSelectChange
      }"
      :pagination="{ pageSize: 10 }"
      :loading="adminStore.loading"
      @change="handleChange"
    >
      <!-- Custom column templates -->
    </a-table>

    <!-- User Management Modal -->
    <UserListModal
      ref="userListModal"
      @user-added="fetchUsers"
      @user-updated="fetchUsers"
      @user-deleted="fetchUsers"
    />
  </div>
</template>
```

---

#### 3. Roles & Permissions Page

**File**: `src/views/pages/administration/user-management/roles-permission.vue`

**Route**: `/user-management/roles-permissions`

**Purpose**: Manage roles

**Features**:
- List all roles
- Add/Edit/Delete roles
- Navigate to permission management

**Data Properties**:
```javascript
{
  roles: [],
  loading: false
}
```

**Methods**:
```javascript
async fetchRoles() {
  this.loading = true
  try {
    this.roles = await adminService.getAllRoles()
  } catch (err) {
    console.error('Failed to fetch roles:', err)
  } finally {
    this.loading = false
  }
}

openPermissionsFor(role) {
  this.$router.push({
    name: 'permission-index',
    query: { role: role.id }
  })
}
```

---

#### 4. Permission Management Page

**File**: `src/views/pages/administration/user-management/permission-index.vue`

**Route**: `/user-management/permission`

**Purpose**: Manage permissions for roles

**Features**:
- Permission grid by module
- Module-level "Allow All" checkbox
- Individual permission toggles
- Role selector

**Data Properties**:
```javascript
{
  selectedRole: null,
  modules: [
    'admin', 'user', 'grant', 'interview', 'employee',
    'employment', 'employment_history', 'children',
    'questionnaire', 'language', 'reference', 'education',
    'payroll', 'attendance', 'training', 'reports',
    'travel_request', 'leave_request', 'job_offer',
    'budget_line', 'tax', 'personnel_action'
  ],
  actions: ['create', 'read', 'update', 'delete', 'import', 'export'],
  permissions: {} // {module: {action: boolean}}
}
```

**Methods**:
```javascript
toggleModuleAll(module) {
  // Toggle all actions for a module
  const allEnabled = this.isModuleAllEnabled(module)

  this.actions.forEach(action => {
    this.permissions[module][action] = !allEnabled
  })
}

isModuleAllEnabled(module) {
  return this.actions.every(action =>
    this.permissions[module][action]
  )
}

async savePermissions() {
  // Convert permissions object to array
  const permissionArray = []

  Object.keys(this.permissions).forEach(module => {
    Object.keys(this.permissions[module]).forEach(action => {
      if (this.permissions[module][action]) {
        permissionArray.push(`${module}.${action}`)
      }
    })
  })

  // Save to backend
  await adminService.updateRolePermissions(
    this.selectedRole,
    permissionArray
  )
}
```

**Template**:
```vue
<template>
  <div class="permission-grid">
    <div class="role-selector">
      <select v-model="selectedRole" @change="loadPermissions">
        <option v-for="role in roles" :key="role.id" :value="role.id">
          {{ role.name }}
        </option>
      </select>
    </div>

    <table class="permission-table">
      <thead>
        <tr>
          <th>Module</th>
          <th>Allow All</th>
          <th v-for="action in actions" :key="action">{{ action }}</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="module in modules" :key="module">
          <td>{{ module }}</td>
          <td>
            <input
              type="checkbox"
              :checked="isModuleAllEnabled(module)"
              @change="toggleModuleAll(module)"
            />
          </td>
          <td v-for="action in actions" :key="action">
            <input
              type="checkbox"
              v-model="permissions[module][action]"
            />
          </td>
        </tr>
      </tbody>
    </table>

    <button @click="savePermissions" class="btn btn-primary">
      Save Permissions
    </button>
  </div>
</template>
```

---

## Components

### User List Modal

**File**: `src/components/modal/user-list-modal.vue`

**Purpose**: Unified modal for Add/Edit/Delete user operations

**Features**:
- Three modal dialogs (Add, Edit, Delete)
- Form validation
- Profile picture upload
- Role selection
- Permission assignment
- Password management

**Data Properties**:
```javascript
{
  // Add User Form
  newUser: {
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '',
    permissions: [],
    profile_picture: null
  },

  // Edit User Form
  editingUser: null,
  editForm: {
    role: '',
    permissions: [],
    password: '',
    confirm_password: ''
  },

  // Delete Confirmation
  userToDelete: null,

  // Module Permissions
  modules: [...],
  actions: [...],

  // UI State
  loading: false,
  alert: {
    show: false,
    message: '',
    type: 'success' // or 'danger'
  },

  // File Upload
  selectedFile: null
}
```

**Computed Properties**:
```javascript
rolePermissions() {
  // Get default permissions for selected role
  return this.initializeRolePermissions(this.newUser.role)
}

isFormValid() {
  // Check if add user form is valid
  return (
    this.newUser.name &&
    this.newUser.email &&
    this.newUser.password &&
    this.newUser.password_confirmation &&
    this.newUser.password === this.newUser.password_confirmation &&
    this.newUser.role
  )
}
```

**Methods**:

```javascript
// Modal Control
openAddModal() {
  // Reset form and open add modal
  this.resetAddForm()
  const modal = new bootstrap.Modal(document.getElementById('add_users'))
  modal.show()
}

setEditUser(user) {
  // Populate edit form with user data
  this.editingUser = user
  this.editForm.role = user.roles[0]?.name || ''
  this.editForm.permissions = user.permissions.map(p => p.name)
  this.editForm.password = ''
  this.editForm.confirm_password = ''

  // Open edit modal
  const modal = new bootstrap.Modal(document.getElementById('edit_user'))
  modal.show()
}

confirmDelete(userId) {
  this.userToDelete = userId
  const modal = new bootstrap.Modal(document.getElementById('delete_modal'))
  modal.show()
}

closeModal(modalId) {
  const modalElement = document.getElementById(modalId)
  const modal = bootstrap.Modal.getInstance(modalElement)
  if (modal) {
    modal.hide()
  }
}

// Form Submission
async submitNewUser() {
  if (!this.isFormValid) {
    this.showAlert('Please fill all required fields', 'danger')
    return
  }

  this.loading = true

  try {
    // Create FormData for file upload
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

    // Append profile picture if selected
    if (this.selectedFile) {
      formData.append('profile_picture', this.selectedFile)
    }

    // Call admin store action
    await adminStore.createUser(formData)

    this.showAlert('User created successfully', 'success')
    this.closeModal('add_users')
    this.$emit('user-added')
    this.resetAddForm()

  } catch (err) {
    this.showAlert(err.message || 'Failed to create user', 'danger')
  } finally {
    this.loading = false
  }
}

async updateExistingUser() {
  this.loading = true

  try {
    const formData = new FormData()
    formData.append('_method', 'PUT') // Laravel method override
    formData.append('role', this.editForm.role)

    // Append permissions
    this.editForm.permissions.forEach((perm, index) => {
      formData.append(`permissions[${index}]`, perm)
    })

    // Append password if provided
    if (this.editForm.password) {
      formData.append('password', this.editForm.password)
    }

    // Append profile picture if changed
    if (this.selectedFile) {
      formData.append('profile_picture', this.selectedFile)
    }

    await adminStore.updateUser(this.editingUser.id, formData)

    this.showAlert('User updated successfully', 'success')
    this.closeModal('edit_user')
    this.$emit('user-updated')

  } catch (err) {
    this.showAlert(err.message || 'Failed to update user', 'danger')
  } finally {
    this.loading = false
  }
}

async deleteUser() {
  this.loading = true

  try {
    await adminStore.deleteUser(this.userToDelete)

    this.showAlert('User deleted successfully', 'success')
    this.closeModal('delete_modal')
    this.$emit('user-deleted')
    this.userToDelete = null

  } catch (err) {
    this.showAlert(err.message || 'Failed to delete user', 'danger')
  } finally {
    this.loading = false
  }
}

// Permission Management
updatePermissionsByRole(role) {
  // Auto-update permissions when role changes
  const rolePerms = this.initializeRolePermissions(role)
  this.newUser.permissions = rolePerms
}

initializeRolePermissions(role) {
  // Return default permissions for role
  switch(role) {
    case 'admin':
    case 'hr-manager':
      // All permissions
      return this.getAllPermissions()

    case 'hr-assistant-senior':
      // All except grant.*
      return this.getAllPermissions().filter(p =>
        !p.startsWith('grant.')
      )

    case 'hr-assistant-junior':
      // All except grant.*, employment.*, payroll.*, reports.*
      return this.getAllPermissions().filter(p =>
        !p.startsWith('grant.') &&
        !p.startsWith('employment.') &&
        !p.startsWith('payroll.') &&
        !p.startsWith('reports.')
      )

    case 'site-admin':
      // Only leave_request.*, travel_request.*, training.*
      return this.getAllPermissions().filter(p =>
        p.startsWith('leave_request.') ||
        p.startsWith('travel_request.') ||
        p.startsWith('training.')
      )

    default:
      return []
  }
}

getAllPermissions() {
  // Generate all possible permissions
  const permissions = []

  this.modules.forEach(module => {
    this.actions.forEach(action => {
      permissions.push(`${module}.${action}`)
    })
  })

  return permissions
}

// File Handling
handleFileUpload(event) {
  const file = event.target.files[0]
  if (file) {
    // Validate file type
    if (!file.type.startsWith('image/')) {
      this.showAlert('Please select an image file', 'danger')
      return
    }

    // Validate file size (2MB max)
    if (file.size > 2048 * 1024) {
      this.showAlert('File size must be less than 2MB', 'danger')
      return
    }

    this.selectedFile = file
  }
}

// Alert Management
showAlert(message, type = 'success') {
  this.alert.show = true
  this.alert.message = message
  this.alert.type = type

  // Auto-dismiss after 5 seconds
  setTimeout(() => {
    this.alert.show = false
  }, 5000)
}

// Form Reset
resetAddForm() {
  this.newUser = {
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    role: '',
    permissions: [],
    profile_picture: null
  }
  this.selectedFile = null
}
```

**Template Structure**:
```vue
<template>
  <!-- Add User Modal -->
  <div id="add_users" class="modal fade" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5>Add New User</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <!-- Alert -->
          <div v-if="alert.show" :class="`alert alert-${alert.type}`">
            {{ alert.message }}
          </div>

          <form>
            <!-- Name -->
            <div class="form-group">
              <label>Name <span class="text-danger">*</span></label>
              <input
                v-model="newUser.name"
                type="text"
                class="form-control"
                required
              />
            </div>

            <!-- Email -->
            <div class="form-group">
              <label>Email <span class="text-danger">*</span></label>
              <input
                v-model="newUser.email"
                type="email"
                class="form-control"
                required
              />
            </div>

            <!-- Password -->
            <div class="form-group">
              <label>Password <span class="text-danger">*</span></label>
              <input
                v-model="newUser.password"
                type="password"
                class="form-control"
                minlength="8"
                required
              />
              <small class="form-text text-muted">
                Min 8 chars, 1 uppercase, 1 lowercase, 1 number, 1 special char
              </small>
            </div>

            <!-- Confirm Password -->
            <div class="form-group">
              <label>Confirm Password <span class="text-danger">*</span></label>
              <input
                v-model="newUser.password_confirmation"
                type="password"
                class="form-control"
                required
              />
            </div>

            <!-- Profile Picture -->
            <div class="form-group">
              <label>Profile Picture</label>
              <input
                type="file"
                class="form-control"
                accept="image/*"
                @change="handleFileUpload"
              />
            </div>

            <!-- Role Selection -->
            <div class="form-group">
              <label>Role <span class="text-danger">*</span></label>
              <select
                v-model="newUser.role"
                class="form-control"
                @change="updatePermissionsByRole(newUser.role)"
                required
              >
                <option value="">Select Role</option>
                <option value="admin">Admin</option>
                <option value="hr-manager">HR Manager</option>
                <option value="hr-assistant-senior">HR Assistant Senior</option>
                <option value="hr-assistant-junior">HR Assistant Junior</option>
                <option value="site-admin">Site Admin</option>
              </select>
            </div>

            <!-- Permission Grid -->
            <div class="form-group">
              <label>Module Permissions</label>
              <div class="permission-grid">
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th>Module</th>
                      <th v-for="action in actions" :key="action">
                        {{ action }}
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="module in modules" :key="module">
                      <td>{{ module }}</td>
                      <td v-for="action in actions" :key="action">
                        <input
                          type="checkbox"
                          :value="`${module}.${action}`"
                          v-model="newUser.permissions"
                        />
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </form>
        </div>

        <div class="modal-footer">
          <button
            type="button"
            class="btn btn-secondary"
            data-bs-dismiss="modal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="btn btn-primary"
            @click="submitNewUser"
            :disabled="loading || !isFormValid"
          >
            <span v-if="loading">Creating...</span>
            <span v-else>Create User</span>
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit User Modal -->
  <!-- Similar structure to Add Modal -->

  <!-- Delete Confirmation Modal -->
  <div id="delete_modal" class="modal fade" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5>Confirm Deletion</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body">
          <p>Are you sure you want to delete this user?</p>
          <p class="text-danger">This action cannot be undone.</p>
        </div>

        <div class="modal-footer">
          <button
            type="button"
            class="btn btn-secondary"
            data-bs-dismiss="modal"
          >
            Cancel
          </button>
          <button
            type="button"
            class="btn btn-danger"
            @click="deleteUser"
            :disabled="loading"
          >
            <span v-if="loading">Deleting...</span>
            <span v-else>Delete</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
```

---

### Roles Modal

**File**: `src/components/modal/roles-modal.vue`

**Purpose**: Add/Edit/Delete roles

**Features**:
- Role name input
- Guard name selection
- Validation

---

## State Management (Pinia Stores)

### Auth Store

**File**: `src/stores/authStore.js`

**Purpose**: Manage authentication state and user session

See [BACKEND_API_REFERENCE.md](./BACKEND_API_REFERENCE.md) for detailed auth store documentation.

---

### Admin Store

**File**: `src/stores/adminStore.js`

**Purpose**: Manage admin operations and user data

See [BACKEND_API_REFERENCE.md](./BACKEND_API_REFERENCE.md) for detailed admin store documentation.

---

## Services

### API Service

**File**: `src/services/api.service.js`

See [API_INTEGRATION_GUIDE.md](./API_INTEGRATION_GUIDE.md) for complete API service documentation.

---

### Auth Service

**File**: `src/services/auth.service.js`

See [API_INTEGRATION_GUIDE.md](./API_INTEGRATION_GUIDE.md) for complete auth service documentation.

---

### Admin Service

**File**: `src/services/admin.service.js`

See [API_INTEGRATION_GUIDE.md](./API_INTEGRATION_GUIDE.md) for complete admin service documentation.

---

## Routing

### Router Configuration

**File**: `src/router/index.js`

See [BACKEND_API_REFERENCE.md](./BACKEND_API_REFERENCE.md) for complete routing documentation.

---

### Route Guards

**File**: `src/router/guards.js`

See [BACKEND_API_REFERENCE.md](./BACKEND_API_REFERENCE.md) for complete route guards documentation.

---

## Form Validation

### Vuelidate Setup

Install Vuelidate:
```bash
npm install @vuelidate/core @vuelidate/validators
```

### Usage Example

```vue
<script>
import { useVuelidate } from '@vuelidate/core'
import { required, email, minLength, sameAs } from '@vuelidate/validators'

export default {
  setup() {
    return { v$: useVuelidate() }
  },

  data() {
    return {
      formData: {
        email: '',
        password: '',
        password_confirmation: ''
      }
    }
  },

  validations() {
    return {
      formData: {
        email: {
          required,
          email
        },
        password: {
          required,
          minLength: minLength(8)
        },
        password_confirmation: {
          required,
          sameAsPassword: sameAs(this.formData.password)
        }
      }
    }
  },

  methods: {
    async submit() {
      const isValid = await this.v$.$validate()
      if (!isValid) return

      // Submit form
    }
  }
}
</script>

<template>
  <form @submit.prevent="submit">
    <input
      v-model="v$.formData.email.$model"
      :class="{ 'is-invalid': v$.formData.email.$error }"
    />
    <div v-if="v$.formData.email.$error" class="invalid-feedback">
      {{ v$.formData.email.$errors[0].$message }}
    </div>
  </form>
</template>
```

---

## Utilities & Helpers

### Storage Keys

**File**: `src/constants/storageKeys.js`

```javascript
export const STORAGE_KEYS = {
  TOKEN: 'token',
  USER: 'user',
  USER_ROLE: 'userRole',
  PERMISSIONS: 'permissions',
  TOKEN_EXPIRATION: 'tokenExpiration',
  JUST_LOGGED_IN: 'justLoggedIn',
  INTENDED_ROUTE: 'intendedRoute'
}
```

### Event Bus

**File**: `src/composables/useEventBus.js`

```javascript
import { ref } from 'vue'

const events = ref({})

export function useEventBus() {
  function on(event, callback) {
    if (!events.value[event]) {
      events.value[event] = []
    }
    events.value[event].push(callback)
  }

  function off(event, callback) {
    if (!events.value[event]) return

    events.value[event] = events.value[event].filter(
      cb => cb !== callback
    )
  }

  function emit(event, data) {
    if (!events.value[event]) return

    events.value[event].forEach(callback => {
      callback(data)
    })
  }

  return {
    on,
    off,
    emit
  }
}
```

**Usage**:
```javascript
import { useEventBus } from '@/composables/useEventBus'

const eventBus = useEventBus()

// Listen for event
eventBus.on('user-updated', (user) => {
  console.log('User updated:', user)
})

// Emit event
eventBus.emit('user-updated', updatedUser)

// Remove listener
eventBus.off('user-updated', callback)
```

---

**Last Updated**: 2025-12-17
**Framework**: Vue 3
**State Management**: Pinia
