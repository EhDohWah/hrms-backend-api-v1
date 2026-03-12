# User Management UI — Frontend Implementation Research

> **Last Updated:** March 6, 2026
> **Scope:** How to implement User Management, Role Management, and Permission Assignment UI in the HRMS frontend, aligned with the new CRUD permission backend.

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [Critical: Auth Store CRUD Migration](#2-critical-auth-store-crud-migration)
3. [Frontend Architecture & Patterns](#3-frontend-architecture--patterns)
4. [New Files Required](#4-new-files-required)
5. [API Service Layer](#5-api-service-layer)
6. [User List Page](#6-user-list-page)
7. [User Create/Edit Modal](#7-user-createedit-modal)
8. [Permission Assignment UI](#8-permission-assignment-ui)
9. [Role Management Page](#9-role-management-page)
10. [Sidebar & Route Integration](#10-sidebar--route-integration)
11. [Auth Store CRUD Methods](#11-auth-store-crud-methods)
12. [WebSocket Permission Sync](#12-websocket-permission-sync)
13. [Implementation Sequence](#13-implementation-sequence)
14. [Risks & Edge Cases](#14-risks--edge-cases)

---

## 1. Executive Summary

The backend has a **fully implemented** User Management system with CRUD permissions. The frontend has **zero** user management pages. This document covers everything needed to build the UI.

### What Needs to Be Built

| Feature | Description | Backend Status |
|---|---|---|
| **User List** | Table of system users with search, role filter, status filter | Ready |
| **Create User** | Form with name, email, password, role, profile picture | Ready |
| **Edit User** | Update role, password, status | Ready |
| **Delete User** | Confirmation + deletion | Ready |
| **Permission Assignment** | CRUD checkbox grid per module, grouped by category | Ready |
| **Permission Summary** | Stats showing full/partial/read-only/no access counts | Ready |
| **Role List** | Table of roles with user counts | Ready |
| **Create/Edit/Delete Role** | CRUD for custom roles (protected roles blocked) | Ready |

### Critical Prerequisite

The backend now returns **CRUD flags** (`read`, `create`, `update`, `delete`) from `GET /me/permissions`. The frontend's `canEdit()` method checks for an `edit` key that **no longer exists**. This must be fixed first or all write-permission gates across the entire app will break.

---

## 2. Critical: Auth Store CRUD Migration

### The Problem

The backend `UserProfileService::myPermissions()` now returns:

```json
{
  "employees": {
    "read": true,
    "create": true,
    "update": true,
    "delete": true,
    "display_name": "Employees",
    "category": "Employee",
    "icon": "users",
    "route": "/employee/employee-list"
  }
}
```

The frontend auth store (`src/stores/auth.js`) currently has:

```javascript
function canEdit(module) {
  return permissions.value?.[module]?.edit === true  // ❌ 'edit' key no longer exists
}
```

### The Fix

The auth store must be updated to support CRUD methods. Since the existing codebase uses `canEdit()` extensively (60+ usages across 20+ view files), the safest approach is to **keep `canEdit()` as a convenience alias** that returns `true` if the user has ANY write permission (create OR update OR delete):

```javascript
// ---- CRUD permission checks ----
function canRead(module) {
  return permissions.value?.[module]?.read === true
}

function canCreate(module) {
  return permissions.value?.[module]?.create === true
}

function canUpdate(module) {
  return permissions.value?.[module]?.update === true
}

function canDelete(module) {
  return permissions.value?.[module]?.delete === true
}

// Backward-compatible: true if user has ANY write permission
function canEdit(module) {
  const p = permissions.value?.[module]
  return p?.create === true || p?.update === true || p?.delete === true
}
```

### Migration Impact on Existing Views

The 60+ `canEdit()` calls across views currently gate:

| Usage Pattern | Current Behavior | After Migration |
|---|---|---|
| "Add [Item]" button | `canEdit('module')` | Works (canEdit = any write) |
| "Edit" button in table | `canEdit('module')` | Works, but ideally should use `canUpdate` |
| "Delete" button in table | `canEdit('module')` | Works, but ideally should use `canDelete` |
| Row selection checkboxes | `canEdit('module')` | Works |
| Bulk delete button | `canEdit('module')` | Works |
| Form readonly mode | `!canEdit` | Works |

**Phase 1 (required):** Add `canCreate`, `canUpdate`, `canDelete` methods and make `canEdit` a union of all three. This is backward-compatible — no existing views break.

**Phase 2 (optional, later):** Gradually refine existing views to use granular methods where appropriate. For example, change "Add Employee" from `canEdit('employees')` to `canCreate('employees')`, and "Delete" buttons from `canEdit('employees')` to `canDelete('employees')`. This is a UX improvement, not a requirement.

### Files Affected by Auth Store Change

Only **1 file** needs to change for Phase 1:
- `src/stores/auth.js` — add `canCreate`, `canUpdate`, `canDelete`, update `canEdit`

All 20+ view files with `canEdit()` calls continue to work unchanged.

---

## 3. Frontend Architecture & Patterns

### Project Structure

```
src/
├── api/              # API service files (one per resource)
├── components/       # Reusable components
│   ├── common/       # AppEmpty, AppLoading, InfoField
│   └── layout/       # AppSidebar, AppHeader, DefaultLayout
├── composables/      # useApi, usePagination, usePermission, useDebounce
├── router/           # routes.js (route definitions), guards.js (auth + permission guards)
├── stores/           # Pinia stores (auth, uiStore, notifications)
├── styles/           # global.less (CSS variables, Ant Design overrides)
├── views/            # Page components organized by domain
└── main.js           # App entry, plugins, global directives
```

### How New Features Are Added (Checklist)

Every new feature follows this exact pattern:

1. **Create API service** in `src/api/` — export CRUD methods using shared axios client
2. **Export from barrel** in `src/api/index.js`
3. **Create view component(s)** in `src/views/{feature}/` — use Ant Design components, auth store permission checks
4. **Add route(s)** in `src/router/routes.js` — lazy-loaded, with `meta.permission`
5. **Add sidebar item(s)** in `src/components/layout/AppSidebar.vue` — with `permission` key

### Key Conventions

| Convention | Details |
|---|---|
| **Component framework** | Vue 3 Composition API (`<script setup>`) |
| **UI library** | Ant Design Vue 4.x |
| **State management** | Pinia |
| **Routing** | Vue Router 4 with lazy loading |
| **API calls** | Axios with HttpOnly cookie auth, `withCredentials: true` |
| **Permission gating** | `authStore.canRead()` for visibility, `authStore.canEdit()` for write actions |
| **Page title** | `appStore.setPageMeta('Title')` in `onMounted` |
| **Table pagination** | Computed `tablePagination` with `showSizeChanger`, `showTotal` |
| **Delete confirmation** | `Modal.confirm()` with `okType: 'danger'`, `centered: true` |
| **Success/error feedback** | `message.success()` / `message.error()` from Ant Design |
| **CSS** | CSS variables from design system, never hardcoded colors |
| **Forms** | `layout="vertical"`, `<a-row :gutter="16">` + `<a-col>` |
| **Empty values** | Display `—` (em dash), never empty string |

### Existing Reference Implementations

Best pages to study for patterns:

| Page | File | Why Study It |
|---|---|---|
| **Recycle Bin** | `src/views/recycle-bin/RecycleBinView.vue` | Client-side filtering, bulk actions, dual record types |
| **Employee List** | `src/views/employees/EmployeeListView.vue` | Server pagination, search, avatar cells, row selection |
| **Holiday List** | `src/views/holidays/HolidayListView.vue` | Simple CRUD with modal create/edit, clean pattern |
| **Data Import** | `src/views/uploads/DataImportView.vue` | Card grid, per-module permission gating, file upload |
| **Tax Settings** | `src/views/settings/TaxSettingsView.vue` | Expandable rows, nested sub-tables, toggle switches |

---

## 4. New Files Required

### Files to Create

```
src/
├── api/
│   └── adminApi.js                          # User, role, permission, module API methods
├── views/
│   └── admin/
│       ├── UserListView.vue                 # User list with search, filters, table
│       ├── UserPermissionsView.vue          # CRUD checkbox grid for a specific user
│       └── RoleListView.vue                 # Role list with create/edit/delete
```

### Files to Modify

```
src/
├── api/
│   └── index.js                             # Add adminApi export
├── stores/
│   └── auth.js                              # Add canCreate, canUpdate, canDelete methods
├── router/
│   └── routes.js                            # Add admin routes
├── components/
│   └── layout/
│       └── AppSidebar.vue                   # Add User Management sidebar section
```

### Total: 3 new files + 4 modified files

---

## 5. API Service Layer

### `src/api/adminApi.js`

```javascript
import client from './axios'

export const adminApi = {
  // ---- Users ----
  listUsers: (params) => client.get('/admin/users', { params }),
  showUser: (id) => client.get(`/admin/users/${id}`),
  storeUser: (payload) => {
    // Use FormData if profile_picture is included
    if (payload instanceof FormData) {
      return client.post('/admin/users', payload, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    }
    return client.post('/admin/users', payload)
  },
  updateUser: (id, payload) => client.put(`/admin/users/${id}`, payload),
  destroyUser: (id) => client.delete(`/admin/users/${id}`),

  // ---- User Permissions ----
  getUserPermissions: (userId) => client.get(`/admin/user-permissions/${userId}`),
  updateUserPermissions: (userId, payload) => client.put(`/admin/user-permissions/${userId}`, payload),
  getUserPermissionSummary: (userId) => client.get(`/admin/user-permissions/${userId}/summary`),

  // ---- Roles ----
  listRoles: (params) => client.get('/admin/roles', { params }),
  roleOptions: () => client.get('/admin/roles/options'),
  showRole: (id) => client.get(`/admin/roles/${id}`),
  storeRole: (payload) => client.post('/admin/roles', payload),
  updateRole: (id, payload) => client.put(`/admin/roles/${id}`, payload),
  destroyRole: (id) => client.delete(`/admin/roles/${id}`),

  // ---- Modules ----
  modulesByCategory: () => client.get('/admin/modules/by-category'),
  modulePermissions: () => client.get('/admin/modules/permissions'),
}
```

### Backend Route Reference

| Method | URL | Middleware Permission |
|---|---|---|
| GET | `/api/v1/admin/users` | `users.read` |
| POST | `/api/v1/admin/users` | `users.create` |
| GET | `/api/v1/admin/users/{id}` | `users.read` |
| PUT | `/api/v1/admin/users/{id}` | `users.update` |
| DELETE | `/api/v1/admin/users/{id}` | `users.delete` |
| GET | `/api/v1/admin/user-permissions/{userId}` | `users.read` |
| PUT | `/api/v1/admin/user-permissions/{userId}` | `users.update` |
| GET | `/api/v1/admin/user-permissions/{userId}/summary` | `users.read` |
| GET | `/api/v1/admin/roles` | `roles.read` |
| POST | `/api/v1/admin/roles` | `roles.create` |
| PUT | `/api/v1/admin/roles/{id}` | `roles.update` |
| DELETE | `/api/v1/admin/roles/{id}` | `roles.delete` |
| GET | `/api/v1/admin/modules/by-category` | Auth only (no module permission) |

---

## 6. User List Page

### `src/views/admin/UserListView.vue`

**Layout:** Standard list page with search, role filter, status filter, and a paginated table.

### Page Header

```
+------------------------------------------------------------------------+
| [Total] users    [Search by name, email...] [Role ▾] [Status ▾] [+ Add User]  |
+------------------------------------------------------------------------+
```

- **Search**: `<a-input>` with `SearchOutlined` prefix, width 240px, `@pressEnter` + `@clear`
- **Role filter**: `<a-select>` populated from `adminApi.roleOptions()`, with `allow-clear`
- **Status filter**: `<a-select>` with options: Active, Inactive
- **Add User**: Primary button, gated by `authStore.canCreate('users')`

### Table Columns

| Column | Key | Width | Content |
|---|---|---|---|
| User | `name` | 260 | Avatar + name stacked with email subtitle |
| Role | `role` | 160 | Role display name as `<a-tag>` |
| Status | `status` | 110 | Status tag (green=active, default=inactive) |
| Last Login | `last_login_at` | 150 | Formatted date or `—` |
| Created | `created_at` | 130 | Formatted date |
| Actions | `actions` | 180 | View Permissions / Edit / Delete links |

### User Name Cell Pattern

```vue
<template v-if="column.key === 'name'">
  <div class="cell-user">
    <a-avatar :size="32" :style="{ backgroundColor: getAvatarColor(record.name) }">
      {{ getInitials(record.name) }}
    </a-avatar>
    <div>
      <div class="cell-name">{{ record.name }}</div>
      <div class="cell-sub">{{ record.email }}</div>
    </div>
  </div>
</template>
```

### Action Buttons (per row)

```vue
<template v-if="column.key === 'actions'">
  <a-space :size="0">
    <a-button size="small" type="link" @click="goToPermissions(record)">Permissions</a-button>
    <a-button v-if="authStore.canUpdate('users')" size="small" type="link" @click="openEdit(record)">Edit</a-button>
    <a-button v-if="authStore.canDelete('users') && !isProtectedUser(record)" size="small" type="link" danger @click="handleDelete(record)">Delete</a-button>
  </a-space>
</template>
```

### Data Fetching

```javascript
const items = ref([])
const loading = ref(false)
const search = ref('')
const filters = reactive({ role: undefined, status: undefined })
const pagination = reactive({ current_page: 1, per_page: 15, total: 0 })

async function fetchUsers() {
  loading.value = true
  try {
    const { data } = await adminApi.listUsers({
      search: search.value || undefined,
      role: filters.role || undefined,
      status: filters.status || undefined,
      page: pagination.current_page,
      per_page: pagination.per_page,
    })
    items.value = data.data || []
    Object.assign(pagination, data.meta || {})
  } catch (err) {
    message.error(err.response?.data?.message || 'Failed to load users')
  } finally {
    loading.value = false
  }
}
```

### Backend Response Shape (GET /admin/users)

```json
{
  "success": true,
  "message": "Users retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "System Administrator",
      "email": "admin@hrms.com",
      "status": "active",
      "profile_picture": null,
      "last_login_at": "2026-03-06T10:30:00Z",
      "roles": [{ "id": 1, "name": "admin", "guard_name": "web" }],
      "permissions": [{ "id": 1, "name": "dashboard.read", "guard_name": "web" }]
    }
  ],
  "meta": { "current_page": 1, "per_page": 15, "total": 42, "last_page": 3 }
}
```

---

## 7. User Create/Edit Modal

### Create User Modal (640px width)

```
+---------------------------------------------------+
|  Create User                                   [X] |
+---------------------------------------------------+
|  [Name                ] [Email               ]     |
|  [Password            ] [Confirm Password    ]     |
|  [Role ▾              ] [Profile Picture ⬆]       |
|                                                     |
|  ─────────────────────────────────────────────      |
|                          [Cancel] [Create User]     |
+---------------------------------------------------+
```

### Form Fields

| Field | Component | Validation | Notes |
|---|---|---|---|
| Name | `<a-input>` | Required, max 255, unique | |
| Email | `<a-input>` | Required, email format, unique | |
| Password | `<a-input-password>` | Required (create only), min 8, uppercase+lowercase+digit+special | Pattern: `^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])` |
| Confirm Password | `<a-input-password>` | Must match password | |
| Role | `<a-select>` | Required | Options from `adminApi.roleOptions()` |
| Profile Picture | `<a-upload>` | Optional, image, max 2MB | |

### Edit User Modal (480px width)

Simpler — only role and optional password change:

```
+-------------------------------------------+
|  Edit User: John Doe                   [X] |
+-------------------------------------------+
|  [Role ▾              ]                    |
|                                            |
|  Change Password (optional)                |
|  [New Password        ]                    |
|  [Confirm Password    ]                    |
|                                            |
|  ──────────────────────────────────────    |
|                       [Cancel] [Save]      |
+-------------------------------------------+
```

### Role Options Response (GET /admin/roles/options)

```json
{
  "success": true,
  "data": [
    { "id": 1, "name": "admin", "display_name": "System Administrator", "is_protected": true, "users_count": 2 },
    { "id": 2, "name": "hr-manager", "display_name": "HR Manager", "is_protected": true, "users_count": 1 },
    { "id": 3, "name": "hr-assistant", "display_name": "HR Assistant", "is_protected": false, "users_count": 5 }
  ]
}
```

### Protected User Logic

Admin users (`role = 'admin'`) cannot be modified or deleted by non-admin users. The backend returns 403, but the frontend should also hide the Edit/Delete buttons:

```javascript
function isProtectedUser(user) {
  return user.roles?.some(r => r.name === 'admin')
}
```

---

## 8. Permission Assignment UI

This is the most complex new UI — a **CRUD checkbox grid** for assigning granular permissions to a user, grouped by module category.

### `src/views/admin/UserPermissionsView.vue`

### Layout

```
+------------------------------------------------------------------------+
| ← Back to Users                                                        |
|                                                                         |
| [Avatar] Sarah Wilson                                                   |
|          sarah@example.com · HR Assistant                               |
|                                                                         |
| Permission Summary                                                      |
| [42 Total] [3 Full Access] [2 Partial] [4 Read Only] [33 No Access]    |
|                                                                         |
| +--------------------------------------------------------------------+ |
| | Module                        | Read | Create | Update | Delete    | |
| +--------------------------------------------------------------------+ |
| | ── Dashboard ──────────────────────────────────────────────────     | |
| | My Dashboard                  | [✓]  | [✓]    | [✓]    | [✓]      | |
| | ── Grants ─────────────────────────────────────────────────────     | |
| | Grants                        | [✓]  | [✓]    | [ ]    | [ ]      | |
| | Grant Positions               | [✓]  | [ ]    | [ ]    | [ ]      | |
| | ── Employee ───────────────────────────────────────────────────     | |
| | Employees                     | [✓]  | [✓]    | [✓]    | [ ]      | |
| | Employment Records            | [✓]  | [ ]    | [ ]    | [ ]      | |
| | ...                           |      |        |        |           | |
| +--------------------------------------------------------------------+ |
|                                                                         |
|                          [Cancel] [Save Permissions]                    |
+------------------------------------------------------------------------+
```

### Data Flow

```
1. Page loads → GET /admin/user-permissions/{userId}
   Returns: { user: {...}, modules: { module_name: { read, create, update, delete, display_name, category, icon, order } } }

2. Also fetch → GET /admin/modules/by-category
   Returns: { "Dashboard": [...], "Grants": [...], "Employee": [...], ... }
   (This gives ALL modules, even ones the target user has no access to)

3. Build checkbox grid:
   For each category → for each module in category → show 4 checkboxes
   Pre-check based on user's current permissions

4. On save → PUT /admin/user-permissions/{userId}
   Send: { modules: { module_name: { read: bool, create: bool, update: bool, delete: bool } } }
```

### Checkbox Grid Component Design

```vue
<div class="permissions-grid">
  <!-- Table header -->
  <div class="permissions-header">
    <div class="perm-module-col">Module</div>
    <div class="perm-check-col">Read</div>
    <div class="perm-check-col">Create</div>
    <div class="perm-check-col">Update</div>
    <div class="perm-check-col">Delete</div>
  </div>

  <!-- Category groups -->
  <template v-for="(modules, category) in modulesByCategory" :key="category">
    <div class="perm-category-row">
      <span>{{ category }}</span>
      <!-- Category-level toggle all -->
      <a-button size="small" type="link" @click="toggleCategory(category, true)">All</a-button>
      <a-button size="small" type="link" @click="toggleCategory(category, false)">None</a-button>
    </div>

    <div v-for="mod in modules" :key="mod.name" class="perm-module-row">
      <div class="perm-module-col">
        <span class="perm-module-name">{{ mod.display_name }}</span>
      </div>
      <div class="perm-check-col">
        <a-checkbox v-model:checked="form[mod.name].read" @change="onReadChange(mod.name)" />
      </div>
      <div class="perm-check-col">
        <a-checkbox v-model:checked="form[mod.name].create" @change="onWriteChange(mod.name)" />
      </div>
      <div class="perm-check-col">
        <a-checkbox v-model:checked="form[mod.name].update" @change="onWriteChange(mod.name)" />
      </div>
      <div class="perm-check-col">
        <a-checkbox v-model:checked="form[mod.name].delete" @change="onWriteChange(mod.name)" />
      </div>
    </div>
  </template>
</div>
```

### Auto-Check Read When Write Is Checked

When a user checks any write permission (create, update, delete), `read` should auto-check:

```javascript
function onWriteChange(moduleName) {
  const mod = form[moduleName]
  // If any write permission is checked, auto-check read
  if (mod.create || mod.update || mod.delete) {
    mod.read = true
  }
}

function onReadChange(moduleName) {
  const mod = form[moduleName]
  // If read is unchecked, uncheck all write permissions
  if (!mod.read) {
    mod.create = false
    mod.update = false
    mod.delete = false
  }
}
```

### Category Toggle

```javascript
function toggleCategory(category, enable) {
  const modules = modulesByCategory[category]
  for (const mod of modules) {
    form[mod.name].read = enable
    form[mod.name].create = enable
    form[mod.name].update = enable
    form[mod.name].delete = enable
  }
}
```

### Permission Summary Stats

Fetch from `GET /admin/user-permissions/{userId}/summary`:

```json
{
  "user": { "id": 5, "name": "Sarah Wilson", "email": "sarah@example.com" },
  "summary": {
    "total_modules": 42,
    "full_access": 3,
    "partial_access": 2,
    "read_only": 4,
    "no_access": 33,
    "total_permissions": 18
  }
}
```

Display as stat tags above the grid:

```vue
<div class="permission-stats">
  <a-tag>{{ summary.total_modules }} Total Modules</a-tag>
  <a-tag color="green">{{ summary.full_access }} Full Access</a-tag>
  <a-tag color="orange">{{ summary.partial_access }} Partial</a-tag>
  <a-tag color="blue">{{ summary.read_only }} Read Only</a-tag>
  <a-tag color="default">{{ summary.no_access }} No Access</a-tag>
</div>
```

### CSS for Permission Grid

```css
.permissions-grid {
  border: 1px solid var(--color-border);
  border-radius: var(--radius-lg);
  overflow: hidden;
}

.permissions-header {
  display: grid;
  grid-template-columns: 1fr 70px 70px 70px 70px;
  padding: 12px 16px;
  background: var(--color-bg-subtle);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-secondary);
  border-bottom: 1px solid var(--color-border);
}

.perm-category-row {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: var(--color-bg-muted);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  color: var(--color-text-secondary);
  border-bottom: 1px solid var(--color-border-light);
}

.perm-module-row {
  display: grid;
  grid-template-columns: 1fr 70px 70px 70px 70px;
  padding: 8px 16px;
  border-bottom: 1px solid var(--color-border-light);
  align-items: center;
}
.perm-module-row:hover {
  background: var(--color-bg-hover);
}

.perm-module-col {
  font-size: 13.5px;
}

.perm-check-col {
  text-align: center;
}

.perm-module-name {
  font-weight: 500;
  color: var(--color-text);
}
```

### Dirty State Detection

Track whether the user has made changes to show a warning before navigating away:

```javascript
const originalPermissions = ref({})

function buildFormFromResponse(userModules, allModules) {
  const result = {}
  for (const [category, modules] of Object.entries(allModules)) {
    for (const mod of modules) {
      result[mod.name] = {
        read: userModules[mod.name]?.read || false,
        create: userModules[mod.name]?.create || false,
        update: userModules[mod.name]?.update || false,
        delete: userModules[mod.name]?.delete || false,
      }
    }
  }
  return result
}

const isDirty = computed(() => {
  return JSON.stringify(form) !== JSON.stringify(originalPermissions.value)
})
```

---

## 9. Role Management Page

### `src/views/admin/RoleListView.vue`

Simpler than user management — a table of roles with create/edit/delete modals.

### Table Columns

| Column | Key | Width | Content |
|---|---|---|---|
| Role Name | `name` | 200 | Display name (e.g., "HR Manager") |
| Slug | `slug` | 160 | Raw name in monospace (e.g., `hr-manager`) |
| Protected | `is_protected` | 100 | Yes/No tag |
| Users | `users_count` | 80 | Count badge |
| Created | `created_at` | 130 | Formatted date |
| Actions | `actions` | 120 | Edit / Delete links |

### Create Role Modal (480px)

```
+-------------------------------------------+
|  Create Role                           [X] |
+-------------------------------------------+
|  Role Name                                 |
|  [payroll-specialist                   ]   |
|  Lowercase letters, numbers, hyphens only  |
|                                            |
|  ──────────────────────────────────────    |
|                    [Cancel] [Create Role]   |
+-------------------------------------------+
```

**Validation:**
- Required, max 255 characters
- Pattern: `^[a-z0-9-]+$` (lowercase, numbers, hyphens)
- Unique (backend validates)
- Cannot use `admin` or `hr-manager`

### Role Response Shape

```json
{
  "id": 3,
  "name": "hr-assistant",
  "display_name": "HR Assistant",
  "guard_name": "web",
  "is_protected": false,
  "users_count": 5,
  "created_at": "2026-01-10T08:00:00Z",
  "updated_at": "2026-01-10T08:00:00Z"
}
```

### Delete Safety

The backend blocks deleting roles that have users assigned. The error message explains this:

```javascript
function handleDeleteRole(role) {
  if (role.is_protected) {
    message.warning('Protected roles cannot be deleted')
    return
  }
  Modal.confirm({
    title: 'Delete Role',
    content: `Are you sure you want to delete "${role.display_name}"? This role must have no users assigned.`,
    okText: 'Delete',
    okType: 'danger',
    centered: true,
    async onOk() {
      try {
        await adminApi.destroyRole(role.id)
        message.success('Role deleted successfully')
        fetchRoles()
      } catch (err) {
        message.error(err.response?.data?.message || 'Cannot delete role with assigned users')
      }
    },
  })
}
```

---

## 10. Sidebar & Route Integration

### Route Definitions (`src/router/routes.js`)

Add inside the `children` array of the `'/'` route:

```javascript
// ---- User Management ----
{
  path: 'admin/users',
  name: 'admin-users',
  component: () => import('@/views/admin/UserListView.vue'),
  meta: { title: 'Users', permission: 'users' },
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
  meta: { title: 'Roles', permission: 'roles' },
},
```

### Sidebar Section (`src/components/layout/AppSidebar.vue`)

Add a new section in `navSections` — place it in the "System" section or create a new "User Management" section:

```javascript
{
  title: 'User Management',
  items: [
    { key: 'admin-users', label: 'Users', icon: TeamOutlined, route: 'admin-users', permission: 'users' },
    { key: 'admin-roles', label: 'Roles', icon: SafetyCertificateOutlined, route: 'admin-roles', permission: 'roles' },
  ],
},
```

Place this section **before** the "System" section (which has Recycle Bin).

### Icon Choices

| Item | Icon | Reasoning |
|---|---|---|
| Users | `TeamOutlined` | Consistent with employee icon pattern |
| Roles | `SafetyCertificateOutlined` | Already imported in sidebar, represents access control |

---

## 11. Auth Store CRUD Methods

### Updated `src/stores/auth.js`

The complete permission section becomes:

```javascript
// ---- CRUD permission checks ----

function hasPermission(module, type = 'read') {
  return permissions.value?.[module]?.[type] === true
}

function canRead(module) {
  return permissions.value?.[module]?.read === true
}

function canCreate(module) {
  return permissions.value?.[module]?.create === true
}

function canUpdate(module) {
  return permissions.value?.[module]?.update === true
}

function canDelete(module) {
  return permissions.value?.[module]?.delete === true
}

// Backward-compatible convenience: true if user has ANY write permission
function canEdit(module) {
  const p = permissions.value?.[module]
  return p?.create === true || p?.update === true || p?.delete === true
}
```

And the return statement:

```javascript
return {
  // ...existing...
  // Permission checks
  hasPermission, canRead, canCreate, canUpdate, canDelete, canEdit, hasRole,
  // ...existing...
}
```

### Route Guard (No Change Needed)

The route guard in `src/router/guards.js` only uses `canRead()`:

```javascript
if (to.meta.permission && !auth.canRead(to.meta.permission)) {
  // redirect to dashboard or 403
}
```

This works correctly — `canRead` checks the `read` key which hasn't changed.

---

## 12. WebSocket Permission Sync

When an admin updates another user's permissions, the backend broadcasts a WebSocket event. The frontend should listen for this and refresh permissions.

### Current Setup

The `DefaultLayout.vue` already subscribes to Echo channels. The `UserPermissionsUpdated` event is broadcast on:

```
Channel: private-App.Models.User.{userId}
Event:   .user.permissions-updated
```

### Frontend Integration

In `DefaultLayout.vue` (or wherever Echo is initialized), add a listener:

```javascript
// Listen for permission updates on the current user's private channel
Echo.private(`App.Models.User.${authStore.user.id}`)
  .listen('.user.permissions-updated', (event) => {
    // Show notification to user
    notification.info({
      message: 'Permissions Updated',
      description: event.message || 'Your permissions have been updated.',
      placement: 'topRight',
      duration: 5,
    })
    // Re-fetch permissions from server
    authStore.fetchPermissions()
  })
```

This ensures that when an admin changes a user's permissions via the Permission Assignment UI, the affected user's sidebar and access controls update in real-time without requiring a page refresh.

---

## 13. Implementation Sequence

### Recommended Build Order

| Step | What | Why First |
|---|---|---|
| **1** | Update `auth.js` — add CRUD methods, update `canEdit` | **CRITICAL** — must be done first or all existing views break when the backend returns CRUD instead of `edit` |
| **2** | Create `adminApi.js` + export from `index.js` | Foundation for all new views |
| **3** | Add routes + sidebar items | Infrastructure |
| **4** | Build `UserListView.vue` | Core feature, simpler than permissions grid |
| **5** | Build `RoleListView.vue` | Simpler CRUD, needed for user create modal's role dropdown |
| **6** | Build `UserPermissionsView.vue` | Most complex, depends on everything above |
| **7** | Add WebSocket listener | Polish — real-time sync |

### Testing Checklist

After each step, verify:

- [ ] Existing pages still work (canEdit backward compatibility)
- [ ] Sidebar shows/hides items based on `users.read` / `roles.read` permissions
- [ ] Route guard blocks unauthorized access
- [ ] Admin can create, edit, delete users (with proper role/permission)
- [ ] Admin can create, edit, delete custom roles (protected roles blocked)
- [ ] Permission grid loads all modules grouped by category
- [ ] Checking a write permission auto-checks read
- [ ] Unchecking read unchecks all write permissions
- [ ] Save permissions correctly syncs to backend
- [ ] Permission summary stats update after save
- [ ] Admin cannot delete their own account
- [ ] Admin cannot modify other admin accounts (backend 403 returned)
- [ ] WebSocket updates trigger permission refresh for affected user

---

## 14. Risks & Edge Cases

### 1. `canEdit()` Backward Compatibility

**Risk:** If `canEdit()` is not updated before the backend deploys CRUD permissions, ALL write-action buttons across the entire app will disappear.

**Mitigation:** Deploy the auth store update (Step 1) before or simultaneously with the backend CRUD migration. The updated `canEdit()` (union of create/update/delete) ensures zero visual change for existing pages.

### 2. Self-Modification Prevention

**Risk:** An admin could revoke their own `users` permissions, locking themselves out of user management.

**Mitigation:** The backend already prevents modifying admin users by non-admins. Consider also adding a frontend warning when the admin tries to modify their own permissions.

### 3. Protected Role Display

**Risk:** Users might try to edit or delete the `admin` or `hr-manager` roles.

**Mitigation:** Hide Edit/Delete buttons for protected roles. Show a tooltip or tag indicating "Protected" status.

### 4. Large Permission Grid Performance

**Risk:** With 42 modules x 4 checkboxes = 168 checkboxes, the grid could be sluggish if using reactive objects incorrectly.

**Mitigation:** Use `reactive({})` for the form state (one object, not 168 refs). Avoid deep watchers. Use `JSON.stringify` for dirty detection only on save, not on every change.

### 5. Permission Sync Race Condition

**Risk:** If two admins edit the same user's permissions simultaneously, the last save wins (`syncPermissions` is destructive).

**Mitigation:** Show a "last modified by" indicator on the permissions page. Consider adding `updated_at` checking (optimistic locking) in a future iteration.

### 6. Role Change Resets Permissions

**Risk:** When changing a user's role via the Edit User modal, the backend auto-syncs permissions for the new role, potentially overwriting custom permission assignments.

**Mitigation:** Show a warning in the Edit User modal: "Changing the role will reset this user's permissions to the default for the new role." Add a confirmation step.

### 7. Module-Permission Middleware on User Management Routes

**Risk:** The backend uses `module.permission:users` middleware on user management routes. The current frontend auth store checks `users.read` — but `read` must exist in the CRUD permission set.

**Status:** Already handled. The `PermissionRoleSeeder` creates `users.read`, `users.create`, `users.update`, `users.delete`. The `UserSeeder` assigns all 4 to admin users. No issue.

---

## Appendix A: Backend API Quick Reference

### User CRUD

```
GET    /api/v1/admin/users                           → List (paginated)
POST   /api/v1/admin/users                           → Create
GET    /api/v1/admin/users/{id}                      → Show
PUT    /api/v1/admin/users/{id}                      → Update
DELETE /api/v1/admin/users/{id}                      → Delete
```

### User Permissions

```
GET    /api/v1/admin/user-permissions/{userId}            → Get permissions
PUT    /api/v1/admin/user-permissions/{userId}            → Update permissions
GET    /api/v1/admin/user-permissions/{userId}/summary    → Summary stats
```

### Roles

```
GET    /api/v1/admin/roles                           → List
GET    /api/v1/admin/roles/options                   → Dropdown options
POST   /api/v1/admin/roles                           → Create
GET    /api/v1/admin/roles/{id}                      → Show
PUT    /api/v1/admin/roles/{id}                      → Update
DELETE /api/v1/admin/roles/{id}                      → Delete
```

### Modules

```
GET    /api/v1/admin/modules/by-category             → Grouped by category
GET    /api/v1/admin/modules/permissions              → All permission strings
```

## Appendix B: Password Validation Requirements

For the Create User form, the password must satisfy:

```
Minimum 8 characters
At least one lowercase letter (a-z)
At least one uppercase letter (A-Z)
At least one digit (0-9)
At least one special character (@$!%*?&)
```

Backend regex: `^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$`

Frontend should validate locally before submit for better UX, and also display backend validation errors.

## Appendix C: Role Display Name Mapping

The backend maps role slugs to display names:

| Slug | Display Name |
|---|---|
| `admin` | System Administrator |
| `hr-manager` | HR Manager |
| `hr-assistant-senior` | Senior HR Assistant |
| `hr-assistant` | HR Assistant |
| `hr-assistant-junior-senior` | Senior HR Junior Assistant |
| `hr-assistant-junior` | HR Junior Assistant |
| *(any other)* | `ucwords(str_replace('-', ' ', $name))` |

The `RoleResource` already returns `display_name` — use it directly in the frontend.
