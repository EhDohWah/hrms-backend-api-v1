# User Management, Role Management & Permission Assignment — Frontend Implementation Plan

> **Last Updated:** March 6, 2026
> **Status:** ALL STEPS IMPLEMENTED
> **Frontend Directory:** `C:\Users\Turtle\Downloads\hrms-screenshot\hrms-frontend`
> **Backend API Base:** `/api/v1/admin/`
> **Prerequisite:** Backend CRUD permission system (42 modules x 4 permissions = 168 total)

---

## Table of Contents

1. [Overview](#1-overview)
2. [Step 1: Auth Store CRUD Migration](#step-1-auth-store-crud-migration)
3. [Step 2: Admin API Service](#step-2-admin-api-service)
4. [Step 3: Routes](#step-3-routes)
5. [Step 4: Sidebar Navigation](#step-4-sidebar-navigation)
6. [Step 5: User List View](#step-5-user-list-view)
7. [Step 6: Role List View](#step-6-role-list-view)
8. [Step 7: User Permissions View](#step-7-user-permissions-view)
9. [Implementation Sequence](#implementation-sequence)
10. [Testing Checklist](#testing-checklist)

---

## 1. Overview

### What We're Building

| Feature | Page | Backend API | Module Permission |
|---|---|---|---|
| User List (CRUD) | `UserListView.vue` | `GET/POST/PUT/DELETE /admin/users` | `users` |
| Role List (CRUD) | `RoleListView.vue` | `GET/POST/PUT/DELETE /admin/roles` | `roles` |
| Permission Assignment | `UserPermissionsView.vue` | `GET/PUT /admin/user-permissions/{id}` | `users` |

### New Files to Create

```
src/
  api/
    adminApi.js                          ← NEW: Admin API service
  views/
    admin/
      UserListView.vue                   ← NEW: User management page
      RoleListView.vue                   ← NEW: Role management page
      UserPermissionsView.vue            ← NEW: Permission assignment page
```

### Files to Modify

```
src/stores/auth.js                       ← Add CRUD permission methods
src/api/index.js                         ← Export adminApi
src/router/routes.js                     ← Add admin routes
src/components/layout/AppSidebar.vue     ← Add Administration section
```

---

## Step 1: Auth Store CRUD Migration [COMPLETED]

**File:** `src/stores/auth.js`

### The Problem

The backend `GET /me/permissions` now returns CRUD flags:

```json
{
  "employees": {
    "read": true,
    "create": true,
    "update": true,
    "delete": false,
    "display_name": "Employees"
  }
}
```

But the current `canEdit()` checks for `permissions[module].edit` which **no longer exists**. This will break all 60+ write-permission gates across the entire frontend.

### The Fix

Replace the permission methods section (lines 36-48) with:

```javascript
// ---- Nested object permission checks ----
// Backend /me/permissions returns: { module_name: { read, create, update, delete, ... } }
function hasPermission(module, type = 'read') {
  return permissions.value?.[module]?.[type] === true
}

function canRead(module) {
  return permissions.value?.[module]?.read === true
}

// Granular CRUD permission checks
function canCreate(module) {
  return permissions.value?.[module]?.create === true
}

function canUpdate(module) {
  return permissions.value?.[module]?.update === true
}

function canDelete(module) {
  return permissions.value?.[module]?.delete === true
}

// Backward-compatible: returns true if user has ANY write permission (create OR update OR delete).
// All 60+ existing canEdit() calls in views will continue to work.
function canEdit(module) {
  const m = permissions.value?.[module]
  return m?.create === true || m?.update === true || m?.delete === true
}
```

Update the return block to export the new methods (line ~240):

```javascript
return {
  // State
  user, permissions, loading, error,
  // Computed
  isAuthenticated, userName, userEmail, userAvatar, userRoles,
  // Permission checks
  hasPermission, canRead, canEdit, canCreate, canUpdate, canDelete, hasRole,
  // Actions
  login, logout, fetchUser, fetchPermissions, initialize, checkAuth,
  updateUserFromEvent,
  // Cross-tab sync
  initCrossTabSync, broadcastPermissionUpdate, broadcastProfileUpdate,
}
```

### Why This Works

- **`canEdit()`** becomes a union of all write permissions (`create || update || delete`). Every existing call like `v-if="authStore.canEdit('sites')"` continues to work.
- **New granular methods** (`canCreate`, `canUpdate`, `canDelete`) are used only in the new admin views for fine-grained button control.
- **Zero breaking changes** to the existing 20+ view files.

---

## Step 2: Admin API Service [COMPLETED]

**File:** `src/api/adminApi.js` (NEW)

```javascript
import client from './axios'

export const adminApi = {
  // ---- User CRUD ----
  list: (params) => client.get('/admin/users', { params }),
  show: (id) => client.get(`/admin/users/${id}`),
  store: (payload) => client.post('/admin/users', payload),
  update: (id, payload) => client.put(`/admin/users/${id}`, payload),
  destroy: (id) => client.delete(`/admin/users/${id}`),

  // ---- Role CRUD ----
  listRoles: (params) => client.get('/admin/roles', { params }),
  showRole: (id) => client.get(`/admin/roles/${id}`),
  storeRole: (payload) => client.post('/admin/roles', payload),
  updateRole: (id, payload) => client.put(`/admin/roles/${id}`, payload),
  destroyRole: (id) => client.delete(`/admin/roles/${id}`),
  roleOptions: () => client.get('/admin/roles/options'),

  // ---- User Permissions ----
  getUserPermissions: (userId) => client.get(`/admin/user-permissions/${userId}`),
  updateUserPermissions: (userId, payload) => client.put(`/admin/user-permissions/${userId}`, payload),
  getUserPermissionSummary: (userId) => client.get(`/admin/user-permissions/${userId}/summary`),

  // ---- Modules ----
  getModulesByCategory: () => client.get('/admin/modules/by-category'),
}
```

### Export from barrel file

**File:** `src/api/index.js` — add this line:

```javascript
export { adminApi } from './adminApi'
```

---

## Step 3: Routes [COMPLETED]

**File:** `src/router/routes.js`

Add these routes inside the `DefaultLayout` children array, before the catch-all route:

```javascript
// ---- Administration ----
{
  path: 'admin/users',
  name: 'admin-users',
  component: () => import('@/views/admin/UserListView.vue'),
  meta: { title: 'User Management', permission: 'users' },
},
{
  path: 'admin/users/:id/permissions',
  name: 'admin-user-permissions',
  component: () => import('@/views/admin/UserPermissionsView.vue'),
  meta: { title: 'User Permissions', permission: 'users' },
},
{
  path: 'admin/roles',
  name: 'admin-roles',
  component: () => import('@/views/admin/RoleListView.vue'),
  meta: { title: 'Role Management', permission: 'roles' },
},
```

The `permission: 'users'` / `permission: 'roles'` meta ensures the route guard checks `authStore.canRead('users')` before allowing navigation.

---

## Step 4: Sidebar Navigation [COMPLETED]

**File:** `src/components/layout/AppSidebar.vue`

### Add icon imports

Add to the existing icon imports (line ~89):

```javascript
import {
  // ... existing icons ...
  UserOutlined,
  LockOutlined,
} from '@ant-design/icons-vue'
```

### Add Administration section

Add this section to the `navSections` array, just before the existing "System" section:

```javascript
{
  title: 'Administration',
  items: [
    { key: 'admin-users', label: 'Users', icon: UserOutlined, route: 'admin-users', permission: 'users' },
    { key: 'admin-roles', label: 'Roles', icon: LockOutlined, route: 'admin-roles', permission: 'roles' },
  ],
},
```

The `permission` key ensures sidebar items are only visible to users with `canRead('users')` / `canRead('roles')` permission.

---

## Step 5: User List View [COMPLETED]

**File:** `src/views/admin/UserListView.vue` (NEW)

This follows the exact same pattern as `SiteListView.vue` with additions for role filter, status filter, and a "Manage Permissions" action.

```vue
<template>
  <div class="page-container">
    <div class="page-header">
      <div class="page-header-stats">
        <a-tag color="default">{{ pagination.total || 0 }} Total</a-tag>
      </div>
      <div class="filter-bar">
        <a-input
          v-model:value="search"
          placeholder="Search users..."
          allow-clear
          class="filter-input"
          style="width: 220px"
          @pressEnter="onSearchOrFilterChange"
          @clear="onSearchOrFilterChange"
        >
          <template #prefix><SearchOutlined /></template>
        </a-input>
        <a-select
          v-model:value="roleFilter"
          placeholder="All Roles"
          allow-clear
          style="width: 160px"
          @change="onSearchOrFilterChange"
        >
          <a-select-option v-for="r in roleOptions" :key="r.id" :value="r.name">
            {{ r.display_name }}
          </a-select-option>
        </a-select>
        <a-select
          v-model:value="statusFilter"
          placeholder="All Status"
          allow-clear
          style="width: 130px"
          @change="onSearchOrFilterChange"
        >
          <a-select-option value="active">Active</a-select-option>
          <a-select-option value="inactive">Inactive</a-select-option>
        </a-select>
        <a-button v-if="authStore.canCreate('users')" type="primary" @click="openCreate">
          <PlusOutlined /> Add User
        </a-button>
      </div>
    </div>

    <a-card :body-style="{ padding: 0 }">
      <a-table
        :columns="columns"
        :data-source="items"
        :loading="loading"
        :pagination="tablePagination"
        :row-key="(r) => r.id"
        @change="handleTableChange"
        :scroll="{ x: 'max-content' }"
        size="middle"
      >
        <template #bodyCell="{ column, record }">
          <template v-if="column.key === 'name'">
            <div style="display: flex; align-items: center; gap: 8px">
              <a-avatar :src="record.profile_picture" :size="28">
                {{ record.name?.charAt(0)?.toUpperCase() }}
              </a-avatar>
              <span>{{ record.name }}</span>
            </div>
          </template>
          <template v-else-if="column.key === 'role'">
            <a-tag color="blue">{{ record.roles?.[0]?.name || 'No Role' }}</a-tag>
          </template>
          <template v-else-if="column.key === 'status'">
            <a-tag :color="record.status === 'active' ? 'green' : 'red'" size="small">
              {{ record.status || 'active' }}
            </a-tag>
          </template>
          <template v-else-if="column.key === 'last_login_at'">
            {{ record.last_login_at ? new Date(record.last_login_at).toLocaleDateString() : 'Never' }}
          </template>
          <template v-else-if="column.key === 'actions'">
            <a-space>
              <a-button
                v-if="authStore.canUpdate('users')"
                size="small"
                type="link"
                @click="openEdit(record)"
              >
                Edit
              </a-button>
              <a-button
                v-if="authStore.canUpdate('users')"
                size="small"
                type="link"
                @click="$router.push({ name: 'admin-user-permissions', params: { id: record.id } })"
              >
                Permissions
              </a-button>
              <a-button
                v-if="authStore.canDelete('users')"
                size="small"
                type="link"
                danger
                @click="handleDelete(record)"
              >
                Delete
              </a-button>
            </a-space>
          </template>
        </template>
      </a-table>
    </a-card>

    <!-- Create/Edit Modal -->
    <a-modal
      v-model:open="modalVisible"
      :title="editingItem ? 'Edit User' : 'Add User'"
      @ok="handleSave"
      :confirm-loading="saving"
      :width="520"
    >
      <a-form :model="form" layout="vertical" class="modal-form">
        <a-form-item label="Full Name" required>
          <a-input v-model:value="form.name" placeholder="Enter full name" />
        </a-form-item>
        <a-form-item label="Email" required>
          <a-input v-model:value="form.email" placeholder="Enter email address" type="email" />
        </a-form-item>
        <a-form-item v-if="!editingItem" label="Password" required>
          <a-input-password v-model:value="form.password" placeholder="Min 8 chars, upper, lower, digit, special" />
        </a-form-item>
        <a-form-item v-if="!editingItem" label="Confirm Password" required>
          <a-input-password v-model:value="form.password_confirmation" placeholder="Confirm password" />
        </a-form-item>
        <a-form-item v-if="editingItem" label="New Password (leave blank to keep current)">
          <a-input-password v-model:value="form.password" placeholder="Enter new password" />
        </a-form-item>
        <a-form-item label="Role" required>
          <a-select v-model:value="form.role" placeholder="Select role">
            <a-select-option v-for="r in roleOptions" :key="r.id" :value="r.name">
              {{ r.display_name }}
            </a-select-option>
          </a-select>
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { Modal, message } from 'ant-design-vue'
import { SearchOutlined, PlusOutlined } from '@ant-design/icons-vue'
import { useAppStore } from '@/stores/uiStore'
import { useAuthStore } from '@/stores/auth'
import { adminApi } from '@/api'

const appStore = useAppStore()
const authStore = useAuthStore()

const items = ref([])
const roleOptions = ref([])
const loading = ref(false)
const saving = ref(false)
const search = ref('')
const roleFilter = ref(undefined)
const statusFilter = ref(undefined)
const pagination = reactive({ current_page: 1, per_page: 20, total: 0 })
const modalVisible = ref(false)
const editingItem = ref(null)
const form = reactive({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
  role: undefined,
})

const columns = [
  { title: 'Name', key: 'name', width: 220 },
  { title: 'Email', dataIndex: 'email', width: 240 },
  { title: 'Role', key: 'role', width: 150 },
  { title: 'Status', key: 'status', width: 100, align: 'center' },
  { title: 'Last Login', key: 'last_login_at', width: 120 },
  { title: '', key: 'actions', width: 220, align: 'right' },
]

const tablePagination = computed(() => ({
  current: pagination.current_page,
  pageSize: pagination.per_page,
  total: pagination.total,
  showSizeChanger: true,
  showTotal: (total) => `${total} users`,
  pageSizeOptions: ['10', '20', '50'],
}))

async function fetchItems() {
  loading.value = true
  try {
    const params = {
      page: pagination.current_page,
      per_page: pagination.per_page,
      ...(search.value && { search: search.value }),
      ...(roleFilter.value && { role: roleFilter.value }),
      ...(statusFilter.value && { status: statusFilter.value }),
    }
    const { data } = await adminApi.list(params)
    items.value = data.data || []
    if (data.pagination) Object.assign(pagination, data.pagination)
  } catch { /* silent */ }
  loading.value = false
}

async function fetchRoleOptions() {
  try {
    const { data } = await adminApi.roleOptions()
    roleOptions.value = data.data || data || []
  } catch { /* silent */ }
}

function onSearchOrFilterChange() {
  pagination.current_page = 1
  fetchItems()
}

function handleTableChange(pag) {
  pagination.current_page = pag.current
  pagination.per_page = pag.pageSize
  fetchItems()
}

function resetForm() {
  Object.assign(form, { name: '', email: '', password: '', password_confirmation: '', role: undefined })
}

function openCreate() {
  editingItem.value = null
  resetForm()
  modalVisible.value = true
}

function openEdit(record) {
  editingItem.value = record
  Object.assign(form, {
    name: record.name || '',
    email: record.email || '',
    password: '',
    password_confirmation: '',
    role: record.roles?.[0]?.name || undefined,
  })
  modalVisible.value = true
}

async function handleSave() {
  if (!form.name || !form.email || !form.role) return message.warning('Name, email, and role are required')

  if (!editingItem.value) {
    if (!form.password) return message.warning('Password is required')
    if (form.password !== form.password_confirmation) return message.warning('Passwords do not match')
  }

  saving.value = true
  try {
    const payload = { name: form.name, email: form.email, role: form.role }
    if (form.password) {
      payload.password = form.password
      payload.password_confirmation = form.password_confirmation
    }

    if (editingItem.value) {
      await adminApi.update(editingItem.value.id, payload)
      message.success('User updated')
    } else {
      await adminApi.store(payload)
      message.success('User created')
    }
    modalVisible.value = false
    fetchItems()
  } catch (err) {
    message.error(err.response?.data?.message || 'Failed to save')
  }
  saving.value = false
}

function handleDelete(record) {
  Modal.confirm({
    title: 'Delete User',
    content: `Are you sure you want to delete "${record.name}"? This action cannot be undone.`,
    okType: 'danger',
    onOk: async () => {
      try {
        await adminApi.destroy(record.id)
        message.success('User deleted')
        fetchItems()
      } catch (err) {
        message.error(err.response?.data?.message || 'Failed to delete')
      }
    },
  })
}

onMounted(() => {
  appStore.setPageMeta('User Management')
  fetchItems()
  fetchRoleOptions()
})
</script>

<style scoped>
.page-header {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 12px;
  margin-bottom: 16px;
}
@media (min-width: 640px) {
  .page-header {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}
.page-header-stats { display: flex; gap: 6px; }
.filter-bar { display: flex; gap: 8px; flex-wrap: wrap; }
.modal-form { margin-top: 16px; }
</style>
```

### Key Design Decisions

1. **Granular CRUD gating**: Add button uses `canCreate('users')`, Edit uses `canUpdate('users')`, Delete uses `canDelete('users')`. This is the primary use case for the new CRUD permissions.
2. **"Permissions" link**: Takes the user to `UserPermissionsView` for granular module-level permission assignment.
3. **Role filter + Status filter**: Leverages the backend's `role` and `status` query parameters.
4. **Password handling**: Required for create, optional for edit. Backend validates password regex: `^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])`.

---

## Step 6: Role List View [COMPLETED]

**File:** `src/views/admin/RoleListView.vue` (NEW)

```vue
<template>
  <div class="page-container">
    <div class="page-header">
      <div class="page-header-stats">
        <a-tag color="default">{{ items.length }} Roles</a-tag>
      </div>
      <div class="filter-bar">
        <a-button v-if="authStore.canCreate('roles')" type="primary" @click="openCreate">
          <PlusOutlined /> Add Role
        </a-button>
      </div>
    </div>

    <a-card :body-style="{ padding: 0 }">
      <a-table
        :columns="columns"
        :data-source="items"
        :loading="loading"
        :row-key="(r) => r.id"
        :pagination="false"
        :scroll="{ x: 'max-content' }"
        size="middle"
      >
        <template #bodyCell="{ column, record }">
          <template v-if="column.key === 'name'">
            <div style="display: flex; align-items: center; gap: 8px">
              <LockOutlined v-if="record.is_protected" style="color: #faad14" />
              <span>{{ record.display_name }}</span>
            </div>
          </template>
          <template v-else-if="column.key === 'slug'">
            <code style="font-size: 12px; background: #f5f5f5; padding: 2px 6px; border-radius: 4px">
              {{ record.name }}
            </code>
          </template>
          <template v-else-if="column.key === 'users_count'">
            <a-tag>{{ record.users_count ?? 0 }} users</a-tag>
          </template>
          <template v-else-if="column.key === 'protected'">
            <a-tag :color="record.is_protected ? 'orange' : 'default'" size="small">
              {{ record.is_protected ? 'Protected' : 'Custom' }}
            </a-tag>
          </template>
          <template v-else-if="column.key === 'actions'">
            <a-space v-if="!record.is_protected">
              <a-button
                v-if="authStore.canUpdate('roles')"
                size="small"
                type="link"
                @click="openEdit(record)"
              >
                Edit
              </a-button>
              <a-button
                v-if="authStore.canDelete('roles')"
                size="small"
                type="link"
                danger
                @click="handleDelete(record)"
              >
                Delete
              </a-button>
            </a-space>
            <a-tag v-else color="default" size="small">System Role</a-tag>
          </template>
        </template>
      </a-table>
    </a-card>

    <!-- Create/Edit Modal -->
    <a-modal
      v-model:open="modalVisible"
      :title="editingItem ? 'Edit Role' : 'Add Role'"
      @ok="handleSave"
      :confirm-loading="saving"
    >
      <a-form :model="form" layout="vertical" class="modal-form">
        <a-form-item label="Role Name" required>
          <a-input v-model:value="form.name" placeholder="e.g. finance-officer" />
          <div style="margin-top: 4px; font-size: 12px; color: #8c8c8c">
            Use lowercase with hyphens. This becomes the internal slug.
          </div>
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Modal, message } from 'ant-design-vue'
import { PlusOutlined, LockOutlined } from '@ant-design/icons-vue'
import { useAppStore } from '@/stores/uiStore'
import { useAuthStore } from '@/stores/auth'
import { adminApi } from '@/api'

const appStore = useAppStore()
const authStore = useAuthStore()

const items = ref([])
const loading = ref(false)
const saving = ref(false)
const modalVisible = ref(false)
const editingItem = ref(null)
const form = reactive({ name: '' })

const columns = [
  { title: 'Role', key: 'name', width: 220 },
  { title: 'Slug', key: 'slug', width: 180 },
  { title: 'Users', key: 'users_count', width: 120, align: 'center' },
  { title: 'Type', key: 'protected', width: 120, align: 'center' },
  { title: '', key: 'actions', width: 160, align: 'right' },
]

async function fetchItems() {
  loading.value = true
  try {
    const { data } = await adminApi.listRoles()
    items.value = data.data || []
  } catch { /* silent */ }
  loading.value = false
}

function openCreate() {
  editingItem.value = null
  form.name = ''
  modalVisible.value = true
}

function openEdit(record) {
  editingItem.value = record
  form.name = record.name || ''
  modalVisible.value = true
}

async function handleSave() {
  if (!form.name) return message.warning('Role name is required')

  saving.value = true
  try {
    if (editingItem.value) {
      await adminApi.updateRole(editingItem.value.id, { name: form.name })
      message.success('Role updated')
    } else {
      await adminApi.storeRole({ name: form.name })
      message.success('Role created')
    }
    modalVisible.value = false
    fetchItems()
  } catch (err) {
    message.error(err.response?.data?.message || 'Failed to save')
  }
  saving.value = false
}

function handleDelete(record) {
  Modal.confirm({
    title: 'Delete Role',
    content: `Are you sure you want to delete the "${record.display_name}" role? Users assigned this role will lose it.`,
    okType: 'danger',
    onOk: async () => {
      try {
        await adminApi.destroyRole(record.id)
        message.success('Role deleted')
        fetchItems()
      } catch (err) {
        message.error(err.response?.data?.message || 'Failed to delete')
      }
    },
  })
}

onMounted(() => {
  appStore.setPageMeta('Role Management')
  fetchItems()
})
</script>

<style scoped>
.page-header {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  gap: 12px;
  margin-bottom: 16px;
}
@media (min-width: 640px) {
  .page-header {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}
.page-header-stats { display: flex; gap: 6px; }
.filter-bar { display: flex; gap: 8px; }
.modal-form { margin-top: 16px; }
</style>
```

### Key Design Decisions

1. **Protected roles** (`admin`, `hr-manager`) show a lock icon and "System Role" tag — cannot be edited or deleted. This matches `RoleResource::isProtected()`.
2. **No pagination**: Role count is typically small (< 20). Uses `pagination: false` on the table.
3. **Slug display**: Shows the internal `name` as a code badge for clarity.

---

## Step 7: User Permissions View [COMPLETED]

**File:** `src/views/admin/UserPermissionsView.vue` (NEW)

This is the most complex page. It shows a **CRUD checkbox grid** grouped by module category, with auto-check behavior and a summary stats bar.

```vue
<template>
  <div class="page-container">
    <!-- Back link + User info -->
    <div style="margin-bottom: 16px">
      <a-button type="link" @click="$router.push({ name: 'admin-users' })" style="padding: 0">
        <ArrowLeftOutlined /> Back to Users
      </a-button>
    </div>

    <div v-if="userData" style="margin-bottom: 20px">
      <h2 style="margin: 0 0 4px">{{ userData.name }}</h2>
      <div style="color: #8c8c8c; font-size: 13px">
        {{ userData.email }} &middot;
        <a-tag color="blue" size="small">{{ userData.roles?.[0] || 'No Role' }}</a-tag>
      </div>
    </div>

    <!-- Summary stats -->
    <div v-if="summary" class="summary-bar">
      <a-tag color="green">{{ summary.full_access }} Full Access</a-tag>
      <a-tag color="blue">{{ summary.partial_access }} Partial</a-tag>
      <a-tag color="orange">{{ summary.read_only }} Read Only</a-tag>
      <a-tag color="default">{{ summary.no_access }} No Access</a-tag>
      <span style="margin-left: auto; color: #8c8c8c; font-size: 12px">
        {{ summary.total_permissions }} / {{ summary.total_modules * 4 }} permissions
      </span>
    </div>

    <!-- Permission grid by category -->
    <a-spin :spinning="loading">
      <div v-for="(modules, category) in modulesByCategory" :key="category" style="margin-bottom: 24px">
        <a-card
          :title="category"
          size="small"
          :body-style="{ padding: 0 }"
          :head-style="{ background: '#fafafa' }"
        >
          <template #extra>
            <a-space>
              <a-button size="small" type="link" @click="selectAllCategory(category)">Select All</a-button>
              <a-button size="small" type="link" @click="deselectAllCategory(category)">Deselect All</a-button>
            </a-space>
          </template>

          <a-table
            :columns="permissionColumns"
            :data-source="modules"
            :row-key="(r) => r.name"
            :pagination="false"
            size="small"
          >
            <template #bodyCell="{ column, record }">
              <template v-if="column.key === 'module'">
                {{ record.display_name }}
              </template>
              <template v-else-if="column.key === 'read'">
                <a-checkbox
                  :checked="getPermission(record.name, 'read')"
                  @change="(e) => onPermissionChange(record.name, 'read', e.target.checked)"
                />
              </template>
              <template v-else-if="column.key === 'create'">
                <a-checkbox
                  :checked="getPermission(record.name, 'create')"
                  @change="(e) => onPermissionChange(record.name, 'create', e.target.checked)"
                />
              </template>
              <template v-else-if="column.key === 'update'">
                <a-checkbox
                  :checked="getPermission(record.name, 'update')"
                  @change="(e) => onPermissionChange(record.name, 'update', e.target.checked)"
                />
              </template>
              <template v-else-if="column.key === 'delete'">
                <a-checkbox
                  :checked="getPermission(record.name, 'delete')"
                  @change="(e) => onPermissionChange(record.name, 'delete', e.target.checked)"
                />
              </template>
            </template>
          </a-table>
        </a-card>
      </div>
    </a-spin>

    <!-- Save button -->
    <div class="save-bar">
      <a-button type="primary" :loading="saving" @click="handleSave" size="large">
        Save Permissions
      </a-button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { message } from 'ant-design-vue'
import { ArrowLeftOutlined } from '@ant-design/icons-vue'
import { useAppStore } from '@/stores/uiStore'
import { adminApi } from '@/api'

const route = useRoute()
const router = useRouter()
const appStore = useAppStore()

const userId = route.params.id
const userData = ref(null)
const summary = ref(null)
const modulesByCategory = ref({})
const permissionState = reactive({})  // { module_name: { read, create, update, delete } }
const loading = ref(false)
const saving = ref(false)

const permissionColumns = [
  { title: 'Module', key: 'module', width: 220 },
  { title: 'Read', key: 'read', width: 80, align: 'center' },
  { title: 'Create', key: 'create', width: 80, align: 'center' },
  { title: 'Update', key: 'update', width: 80, align: 'center' },
  { title: 'Delete', key: 'delete', width: 80, align: 'center' },
]

function getPermission(moduleName, action) {
  return permissionState[moduleName]?.[action] === true
}

function onPermissionChange(moduleName, action, checked) {
  if (!permissionState[moduleName]) {
    permissionState[moduleName] = { read: false, create: false, update: false, delete: false }
  }

  permissionState[moduleName][action] = checked

  // Auto-check behavior:
  // 1. Checking any write permission auto-checks Read
  if (checked && action !== 'read') {
    permissionState[moduleName].read = true
  }

  // 2. Unchecking Read unchecks all write permissions
  if (!checked && action === 'read') {
    permissionState[moduleName].create = false
    permissionState[moduleName].update = false
    permissionState[moduleName].delete = false
  }
}

function selectAllCategory(category) {
  const modules = modulesByCategory.value[category] || []
  for (const mod of modules) {
    if (!permissionState[mod.name]) {
      permissionState[mod.name] = { read: false, create: false, update: false, delete: false }
    }
    permissionState[mod.name].read = true
    permissionState[mod.name].create = true
    permissionState[mod.name].update = true
    permissionState[mod.name].delete = true
  }
}

function deselectAllCategory(category) {
  const modules = modulesByCategory.value[category] || []
  for (const mod of modules) {
    if (!permissionState[mod.name]) {
      permissionState[mod.name] = { read: false, create: false, update: false, delete: false }
    }
    permissionState[mod.name].read = false
    permissionState[mod.name].create = false
    permissionState[mod.name].update = false
    permissionState[mod.name].delete = false
  }
}

async function fetchData() {
  loading.value = true
  try {
    // Fetch user permissions and modules in parallel
    const [permRes, modulesRes, summaryRes] = await Promise.all([
      adminApi.getUserPermissions(userId),
      adminApi.getModulesByCategory(),
      adminApi.getUserPermissionSummary(userId),
    ])

    // User info
    const permData = permRes.data.data || permRes.data
    userData.value = permData.user

    // Summary stats
    const summaryData = summaryRes.data.data || summaryRes.data
    summary.value = summaryData.summary

    // Modules grouped by category
    const categoriesData = modulesRes.data.data || modulesRes.data
    modulesByCategory.value = categoriesData

    // Initialize permission state from user's current permissions
    const userModules = permData.modules || {}
    for (const [moduleName, access] of Object.entries(userModules)) {
      permissionState[moduleName] = {
        read: access.read || false,
        create: access.create || false,
        update: access.update || false,
        delete: access.delete || false,
      }
    }

    // Ensure all modules have an entry in permissionState (even if no permissions)
    for (const modules of Object.values(categoriesData)) {
      for (const mod of modules) {
        if (!permissionState[mod.name]) {
          permissionState[mod.name] = { read: false, create: false, update: false, delete: false }
        }
      }
    }
  } catch (err) {
    message.error('Failed to load permission data')
    console.error(err)
  }
  loading.value = false
}

async function handleSave() {
  saving.value = true
  try {
    await adminApi.updateUserPermissions(userId, { modules: { ...permissionState } })
    message.success('Permissions saved successfully')

    // Refresh summary
    const { data } = await adminApi.getUserPermissionSummary(userId)
    const summaryData = data.data || data
    summary.value = summaryData.summary
  } catch (err) {
    message.error(err.response?.data?.message || 'Failed to save permissions')
  }
  saving.value = false
}

onMounted(() => {
  appStore.setPageMeta('User Permissions')
  fetchData()
})
</script>

<style scoped>
.summary-bar {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 16px;
  background: #fafafa;
  border: 1px solid #f0f0f0;
  border-radius: 8px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}
.save-bar {
  position: sticky;
  bottom: 0;
  background: white;
  padding: 16px 0;
  border-top: 1px solid #f0f0f0;
  display: flex;
  justify-content: flex-end;
}
</style>
```

### Auto-Check Behavior

The `onPermissionChange()` function enforces two rules:

1. **Checking any write permission (create/update/delete) auto-checks Read** — because you can't write to a module you can't see.
2. **Unchecking Read unchecks all write permissions** — if you remove view access, all write access must go too.

These rules are enforced in the UI only (not the backend), giving the admin visual feedback as they click.

### API Payload Shape

The save button sends:

```json
PUT /api/v1/admin/user-permissions/{userId}

{
  "modules": {
    "employees": { "read": true, "create": true, "update": true, "delete": false },
    "payroll":   { "read": true, "create": false, "update": false, "delete": false },
    "users":     { "read": false, "create": false, "update": false, "delete": false }
  }
}
```

This matches the `UpdateUserPermissionsRequest` validation:
```php
'modules'          => ['required', 'array'],
'modules.*.read'   => ['required', 'boolean'],
'modules.*.create' => ['required', 'boolean'],
'modules.*.update' => ['required', 'boolean'],
'modules.*.delete' => ['required', 'boolean'],
```

---

## Implementation Sequence

Build in this order (each step depends on the previous):

| # | Step | Estimated Size | Dependencies |
|---|---|---|---|
| 1 | Auth store CRUD methods | ~20 lines changed | None |
| 2 | `adminApi.js` + barrel export | ~25 lines new | None |
| 3 | Routes in `routes.js` | ~15 lines added | Steps 5-7 files exist |
| 4 | Sidebar section | ~10 lines added | Step 3 routes exist |
| 5 | `UserListView.vue` | ~250 lines new | Steps 1, 2 |
| 6 | `RoleListView.vue` | ~180 lines new | Steps 1, 2 |
| 7 | `UserPermissionsView.vue` | ~260 lines new | Steps 1, 2 |

**Recommended build order:** 1 → 2 → 5 → 6 → 7 → 3 → 4

Do Steps 1-2 first (auth store + API service), then build the three views, then wire up routes and sidebar last.

---

## Testing Checklist

### Auth Store Migration
- [ ] `canRead('employees')` returns `true` for user with `employees.read` permission
- [ ] `canEdit('employees')` returns `true` if user has ANY of `create`, `update`, or `delete`
- [ ] `canEdit('employees')` returns `false` if user has only `read`
- [ ] `canCreate('employees')` returns `true` only for `employees.create`
- [ ] All existing views (EmployeeListView, SiteListView, etc.) still show correct buttons

### User Management
- [ ] User list loads with pagination
- [ ] Search filters by name/email
- [ ] Role filter dropdown works
- [ ] Status filter works
- [ ] Create user with all required fields → success
- [ ] Create user with weak password → validation error
- [ ] Edit user role → success
- [ ] Delete non-admin user → success
- [ ] Delete admin user as non-admin → 403 error
- [ ] "Permissions" button navigates to permission page

### Role Management
- [ ] Role list loads
- [ ] Protected roles show lock icon and "System Role" tag
- [ ] Cannot edit/delete protected roles
- [ ] Create custom role → success
- [ ] Delete custom role → success

### Permission Assignment
- [ ] CRUD checkbox grid shows all modules grouped by category
- [ ] Current user permissions are pre-checked
- [ ] Checking "Create" auto-checks "Read"
- [ ] Unchecking "Read" unchecks all write checkboxes
- [ ] "Select All" / "Deselect All" per category
- [ ] Summary bar shows correct counts
- [ ] Save → success message + summary refreshes
- [ ] Saving permissions for admin user as non-admin → 403 error

### Navigation
- [ ] Sidebar shows "Administration" section with Users + Roles
- [ ] Sidebar items hidden for users without `users.read` / `roles.read`
- [ ] Direct URL `/admin/users` redirects to dashboard if no `users.read` permission
- [ ] "Permissions" link from user list navigates correctly
- [ ] Back button on permissions page returns to user list
