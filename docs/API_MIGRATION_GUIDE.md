# API Migration Guide

**Version:** 1.0
**Created:** 2026-01-24
**Sunset Date:** 2026-04-24

---

## Overview

This guide documents the API route changes made during the Controller Method Standardization refactoring. All legacy routes remain functional with backward compatibility but should be migrated to new routes before the sunset date.

---

## Migration Timeline

| Date | Action |
|------|--------|
| 2026-01-24 | New routes available, legacy routes still work |
| 2026-03-24 | Deprecation warnings added to legacy routes |
| 2026-04-24 | **Sunset date** - Legacy routes will be removed |

---

## Route Changes

### 1. Grant Items (Phase 2)

Grant item endpoints were moved from `/grants/items/*` to `/grant-items/*`.

| Legacy Route | New Route | Method |
|--------------|-----------|--------|
| `GET /grants/items` | `GET /grant-items` | `index` |
| `GET /grants/items/{id}` | `GET /grant-items/{id}` | `show` |
| `POST /grants/items` | `POST /grant-items` | `store` |
| `PUT /grants/items/{id}` | `PUT /grant-items/{id}` | `update` |
| `DELETE /grants/items/{id}` | `DELETE /grant-items/{id}` | `destroy` |

**Frontend Migration Example:**
```javascript
// Before
const response = await api.get('/grants/items');
const item = await api.get(`/grants/items/${id}`);
await api.post('/grants/items', data);

// After
const response = await api.get('/grant-items');
const item = await api.get(`/grant-items/${id}`);
await api.post('/grant-items', data);
```

---

### 2. Leave Types (Phase 3)

Leave type endpoints were moved from `/leaves/types/*` to `/leave-types/*`.

| Legacy Route | New Route | Method |
|--------------|-----------|--------|
| `GET /leaves/types` | `GET /leave-types` | `index` |
| `GET /leaves/types/dropdown` | `GET /leave-types/options` | `options` |
| `POST /leaves/types` | `POST /leave-types` | `store` |
| `PUT /leaves/types/{id}` | `PUT /leave-types/{id}` | `update` |
| `DELETE /leaves/types/{id}` | `DELETE /leave-types/{id}` | `destroy` |

**Frontend Migration Example:**
```javascript
// Before
const types = await api.get('/leaves/types');
const dropdown = await api.get('/leaves/types/dropdown');

// After
const types = await api.get('/leave-types');
const dropdown = await api.get('/leave-types/options');
```

---

### 3. Leave Requests (Phase 3)

Leave request endpoints were moved from `/leaves/requests/*` to `/leave-requests/*`.

| Legacy Route | New Route | Method |
|--------------|-----------|--------|
| `GET /leaves/requests` | `GET /leave-requests` | `index` |
| `GET /leaves/requests/{id}` | `GET /leave-requests/{id}` | `show` |
| `POST /leaves/requests` | `POST /leave-requests` | `store` |
| `PUT /leaves/requests/{id}` | `PUT /leave-requests/{id}` | `update` |
| `DELETE /leaves/requests/{id}` | `DELETE /leave-requests/{id}` | `destroy` |

**Frontend Migration Example:**
```javascript
// Before
const requests = await api.get('/leaves/requests');
await api.post('/leaves/requests', leaveData);

// After
const requests = await api.get('/leave-requests');
await api.post('/leave-requests', leaveData);
```

---

### 4. Leave Balances (Phase 3)

Leave balance endpoints were moved from `/leaves/balances/*` to `/leave-balances/*`.

| Legacy Route | New Route | Method |
|--------------|-----------|--------|
| `GET /leaves/balances` | `GET /leave-balances` | `index` |
| `GET /leaves/balance/{empId}/{typeId}` | `GET /leave-balances/{empId}/{typeId}` | `show` |
| `POST /leaves/balances` | `POST /leave-balances` | `store` |
| `PUT /leaves/balances/{id}` | `PUT /leave-balances/{id}` | `update` |

**Frontend Migration Example:**
```javascript
// Before
const balances = await api.get('/leaves/balances');
const balance = await api.get(`/leaves/balance/${empId}/${typeId}`);

// After
const balances = await api.get('/leave-balances');
const balance = await api.get(`/leave-balances/${empId}/${typeId}`);
```

---

## Routes With No URL Changes

The following refactoring changes **do not affect route URLs** - only internal method names changed:

### EmployeeController (Phase 4)
- `PUT /employees/{id}/basic-information` - Method: `updateBasicInfo()`
- `PUT /employees/{id}/personal-information` - Method: `updatePersonalInfo()`
- `PUT /employees/{id}/family-information` - Method: `updateFamilyInfo()`
- `PUT /employees/{id}/bank-information` - Method: `updateBankInfo()`
- `GET /employees/tree-search` - Method: `searchForOrgTree()`

### DashboardController (Phase 4)
- `GET /dashboard/my-widgets` - Method: `show()`
- `GET /dashboard/available-widgets` - Method: `available()`
- `GET /admin/dashboard/widgets` - Method: `index()`
- `GET /admin/dashboard/users/{id}/widgets` - Method: `showUserWidgets()`
- `PUT /admin/dashboard/users/{id}/widgets` - Method: `updateUserWidgets()`
- `GET /admin/dashboard/users/{id}/available-widgets` - Method: `availableForUser()`

### LookupController (Phase 4)
- `GET /lookups/lists` - Method: `lists()`
- `GET /lookups/types` - Method: `types()`
- `GET /lookups/type/{type}` - Method: `byType()`

### GrantController (Phase 4)
- `GET /grants/by-code/{code}` - Method: `showByCode()`

---

## Deprecation Headers

Legacy routes return the following headers to help track migration:

```
X-API-Deprecation: Use {new_route} instead
X-API-Sunset: 2026-04-24
```

Monitor your application logs for these headers to identify code that needs migration.

---

## Testing After Migration

After updating to new routes, verify:

1. **List endpoints** return paginated data correctly
2. **Create endpoints** return 201 status
3. **Update endpoints** return 200 status
4. **Delete endpoints** return 200/204 status
5. **Error handling** works as expected

---

## Support

If you encounter issues during migration:

1. Check the route exists: `php artisan route:list --path={route}`
2. Verify authentication token is valid
3. Check permission middleware requirements
4. Review API documentation at `/api/documentation`

---

## Quick Reference Card

```
+----------------------+-------------------------+
| Resource             | New Base Route          |
+----------------------+-------------------------+
| Grant Items          | /grant-items            |
| Leave Types          | /leave-types            |
| Leave Requests       | /leave-requests         |
| Leave Balances       | /leave-balances         |
+----------------------+-------------------------+
```

---

*This document should be shared with the frontend development team.*
