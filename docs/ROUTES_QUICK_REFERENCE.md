# Upload/Download Routes - Quick Reference

## 📌 Quick Access

### Upload Routes (POST)
```
/api/v1/uploads/grant       → Upload grant data
/api/v1/uploads/employee    → Upload employee data
/api/v1/uploads/employment  → Upload employment data
```

### Download Routes (GET)
```
/api/v1/downloads/grant-template      → Download grant template
/api/v1/downloads/employee-template   → Download employee template
/api/v1/downloads/employment-template → Download employment template
```

---

## 🔐 Permissions

| Route | Permission Required |
|-------|-------------------|
| `/uploads/grant` | `grants_list.edit` |
| `/uploads/employee` | `employees.edit` |
| `/uploads/employment` | `employment_records.edit` |
| `/downloads/grant-template` | `grants_list.read` |
| `/downloads/employee-template` | `employees.read` |
| `/downloads/employment-template` | `employment_records.read` |

---

## 📋 Controllers

| Route | Controller | Method |
|-------|-----------|--------|
| `/uploads/grant` | `GrantController` | `upload()` |
| `/uploads/employee` | `EmployeeController` | `uploadEmployeeData()` |
| `/uploads/employment` | `EmploymentController` | `upload()` |
| `/downloads/grant-template` | `GrantController` | `downloadTemplate()` |
| `/downloads/employee-template` | `EmployeeController` | `downloadEmployeeTemplate()` |
| `/downloads/employment-template` | `EmploymentController` | `downloadEmploymentTemplate()` |

---

## 🎯 Frontend API Config

```javascript
// Grant
GRANT: {
    UPLOAD: '/uploads/grant',
    DOWNLOAD_TEMPLATE: '/downloads/grant-template',
}

// Upload Section
UPLOAD: {
    EMPLOYEE: '/uploads/employee',
    EMPLOYEE_TEMPLATE: '/downloads/employee-template',
    EMPLOYMENT: '/uploads/employment',
    EMPLOYMENT_TEMPLATE: '/downloads/employment-template',
}
```

---

## 📂 Route File Location

All upload and download routes are centralized in:
```
routes/api/uploads.php
```

---

## ✅ Verified Routes

### Upload Routes (3 total)
```bash
POST  api/v1/uploads/employee    → uploads.employee
POST  api/v1/uploads/employment  → uploads.employment
POST  api/v1/uploads/grant       → uploads.grant
```

### Download Routes (3 total)
```bash
GET   api/v1/downloads/employee-template   → downloads.employee-template
GET   api/v1/downloads/employment-template → downloads.employment-template
GET   api/v1/downloads/grant-template      → downloads.grant-template
```

### Grant CRUD Routes (12 total)
```bash
GET     api/v1/grants                    → grants.index
POST    api/v1/grants                    → grants.store
GET     api/v1/grants/by-code/{code}     → getGrantByCode
GET     api/v1/grants/by-id/{id}         → grants.show
GET     api/v1/grants/grant-positions    → grants.grant-positions
GET     api/v1/grants/items              → grants.items.index
POST    api/v1/grants/items              → grants.items.store
GET     api/v1/grants/items/{id}         → grants.items.show
PUT     api/v1/grants/items/{id}         → grants.items.update
DELETE  api/v1/grants/items/{id}         → grants.items.destroy
PUT     api/v1/grants/{id}               → grants.update
DELETE  api/v1/grants/{id}               → grants.destroy
```

---

**Last Updated:** December 30, 2025  
**Status:** ✅ Active & Verified

