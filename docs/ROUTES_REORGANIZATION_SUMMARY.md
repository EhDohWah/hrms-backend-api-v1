# Routes Reorganization - Upload/Download Centralization

## 📋 Overview

**Date:** December 30, 2025  
**Change Type:** Route Organization & Centralization  
**Impact:** Backend routes and frontend API config updated  
**Breaking Changes:** ⚠️ Yes - Route paths changed

---

## 🎯 Objective

Centralize all file upload and template download routes into a single location (`routes/api/uploads.php`) with clear prefixes:
- `/uploads` - All file upload routes
- `/downloads` - All template download routes

---

## 📁 Files Modified

### Backend

1. **`routes/api/uploads.php`** - Centralized upload/download routes
2. **`routes/api/grants.php`** - Removed upload/download routes

### Frontend

3. **`src/config/api.config.js`** - Updated API endpoints

---

## 🔄 Route Changes

### Before Reorganization

**Grant Routes (`routes/api/grants.php`):**
```php
POST   /api/v1/grants/upload              → Upload grant data
GET    /api/v1/grants/download-template   → Download grant template
```

**Employee Routes (via `routes/api/uploads.php`):**
```php
POST   /api/v1/uploads/employee            → Upload employee data
GET    /api/v1/uploads/employee/template   → Download employee template
```

**Employment Routes (via `routes/api/uploads.php`):**
```php
POST   /api/v1/uploads/employment          → Upload employment data
GET    /api/v1/uploads/employment/template → Download employment template
```

### After Reorganization

**All Upload/Download Routes (`routes/api/uploads.php`):**

#### UPLOADS PREFIX
```php
POST   /api/v1/uploads/grant       → Upload grant data
POST   /api/v1/uploads/employee    → Upload employee data
POST   /api/v1/uploads/employment  → Upload employment data
```

#### DOWNLOADS PREFIX
```php
GET    /api/v1/downloads/grant-template      → Download grant template
GET    /api/v1/downloads/employee-template   → Download employee template
GET    /api/v1/downloads/employment-template → Download employment template
```

---

## 📊 Route Structure Comparison

### Old Structure (Mixed)

```
routes/api/
├── grants.php
│   ├── /grants/upload              ← Upload here
│   ├── /grants/download-template   ← Download here
│   └── /grants/*                   ← Other grant routes
│
└── uploads.php
    ├── /uploads/employee           ← Upload here
    ├── /uploads/employee/template  ← Download here
    ├── /uploads/employment         ← Upload here
    └── /uploads/employment/template← Download here
```

### New Structure (Centralized)

```
routes/api/
├── grants.php
│   └── /grants/*                   ← Only CRUD routes
│
└── uploads.php                     ← ALL uploads & downloads
    ├── UPLOADS PREFIX
    │   ├── /uploads/grant
    │   ├── /uploads/employee
    │   └── /uploads/employment
    │
    └── DOWNLOADS PREFIX
        ├── /downloads/grant-template
        ├── /downloads/employee-template
        └── /downloads/employment-template
```

---

## 🔧 Implementation Details

### Backend Changes

#### 1. Updated `routes/api/uploads.php`

**Added:**
- Grant upload route: `POST /uploads/grant`
- Grant template download: `GET /downloads/grant-template`
- Reorganized with clear prefixes

**Structure:**
```php
Route::middleware('auth:sanctum')->group(function () {
    
    // UPLOADS PREFIX - All file upload routes
    Route::prefix('uploads')->group(function () {
        Route::post('/grant', [GrantController::class, 'upload']);
        Route::post('/employee', [EmployeeController::class, 'uploadEmployeeData']);
        Route::post('/employment', [EmploymentController::class, 'upload']);
    });

    // DOWNLOADS PREFIX - All template download routes
    Route::prefix('downloads')->group(function () {
        Route::get('/grant-template', [GrantController::class, 'downloadTemplate']);
        Route::get('/employee-template', [EmployeeController::class, 'downloadEmployeeTemplate']);
        Route::get('/employment-template', [EmploymentController::class, 'downloadEmploymentTemplate']);
    });
});
```

#### 2. Updated `routes/api/grants.php`

**Removed:**
- `POST /grants/upload`
- `GET /grants/download-template`

**Added Comment:**
```php
// Note: Upload and download-template routes moved to routes/api/uploads.php
```

### Frontend Changes

#### 3. Updated `src/config/api.config.js`

**Grant Endpoints:**
```javascript
// Before
UPLOAD: '/grants/upload',
DOWNLOAD_TEMPLATE: '/grants/download-template',

// After
UPLOAD: '/uploads/grant',
DOWNLOAD_TEMPLATE: '/downloads/grant-template',
```

**Upload Section:**
```javascript
// Before
EMPLOYEE_TEMPLATE: '/uploads/employee/template',
EMPLOYMENT_TEMPLATE: '/uploads/employment/template',

// After
EMPLOYEE_TEMPLATE: '/downloads/employee-template',
EMPLOYMENT_TEMPLATE: '/downloads/employment-template',
```

---

## ✅ Benefits

### 1. **Centralization**
- All upload routes in one place
- All download routes in one place
- Easier to maintain and locate

### 2. **Consistency**
- Uniform URL structure
- Clear naming convention
- Predictable patterns

### 3. **Scalability**
- Easy to add new upload types
- Easy to add new template downloads
- Clear separation of concerns

### 4. **Clarity**
- `/uploads/*` = File uploads
- `/downloads/*` = Template downloads
- No confusion about route location

---

## 🧪 Testing

### Test Upload Routes

```bash
# Grant upload
curl -X POST http://localhost:8000/api/v1/uploads/grant \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@grant_data.xlsx"

# Employee upload
curl -X POST http://localhost:8000/api/v1/uploads/employee \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@employee_data.xlsx"

# Employment upload
curl -X POST http://localhost:8000/api/v1/uploads/employment \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -F "file=@employment_data.xlsx"
```

### Test Download Routes

```bash
# Grant template
curl -X GET http://localhost:8000/api/v1/downloads/grant-template \
  -H "Authorization: Bearer YOUR_TOKEN" \
  --output grant_template.xlsx

# Employee template
curl -X GET http://localhost:8000/api/v1/downloads/employee-template \
  -H "Authorization: Bearer YOUR_TOKEN" \
  --output employee_template.xlsx

# Employment template
curl -X GET http://localhost:8000/api/v1/downloads/employment-template \
  -H "Authorization: Bearer YOUR_TOKEN" \
  --output employment_template.xlsx
```

---

## ⚠️ Breaking Changes

### Impact

**Frontend Applications:**
- Must update API endpoint references
- Old routes will return 404 errors
- Update required before deployment

**API Consumers:**
- Any external applications using these endpoints must update
- Old endpoints no longer exist

### Migration Steps

1. **Update Frontend Config**
   - ✅ Already updated in `api.config.js`

2. **Clear Route Cache (Backend)**
   ```bash
   php artisan route:clear
   php artisan route:cache
   ```

3. **Test All Upload/Download Features**
   - Grant upload/download
   - Employee upload/download
   - Employment upload/download

4. **Update API Documentation**
   - Swagger/OpenAPI specs
   - Postman collections
   - Internal documentation

---

## 📝 Route Reference

### Complete Route List

| Method | Old Route | New Route | Controller | Permission |
|--------|-----------|-----------|------------|------------|
| POST | `/grants/upload` | `/uploads/grant` | GrantController@upload | grants_list.edit |
| GET | `/grants/download-template` | `/downloads/grant-template` | GrantController@downloadTemplate | grants_list.read |
| POST | `/uploads/employee` | `/uploads/employee` | EmployeeController@uploadEmployeeData | employees.edit |
| GET | `/uploads/employee/template` | `/downloads/employee-template` | EmployeeController@downloadEmployeeTemplate | employees.read |
| POST | `/uploads/employment` | `/uploads/employment` | EmploymentController@upload | employment_records.edit |
| GET | `/uploads/employment/template` | `/downloads/employment-template` | EmploymentController@downloadEmploymentTemplate | employment_records.read |

---

## 🔐 Permissions

**No changes to permissions:**
- Grant routes: `grants_list.read` / `grants_list.edit`
- Employee routes: `employees.read` / `employees.edit`
- Employment routes: `employment_records.read` / `employment_records.edit`

---

## 📚 Related Files

### Backend
- `routes/api/uploads.php` - Centralized upload/download routes
- `routes/api/grants.php` - Grant CRUD routes only
- `routes/api/employees.php` - Employee CRUD routes only
- `routes/api/employment.php` - Employment CRUD routes only

### Frontend
- `src/config/api.config.js` - API endpoint configuration
- `src/services/grant.service.js` - Uses GRANT.UPLOAD and GRANT.DOWNLOAD_TEMPLATE
- `src/services/upload-employee.service.js` - Uses UPLOAD.EMPLOYEE and UPLOAD.EMPLOYEE_TEMPLATE
- `src/components/uploads/grant-upload.vue` - Grant upload component
- `src/components/uploads/employee-upload.vue` - Employee upload component

---

## ✅ Checklist

- [x] Moved grant upload route to uploads.php
- [x] Moved grant download route to uploads.php
- [x] Organized routes with /uploads and /downloads prefixes
- [x] Updated grants.php (removed upload/download routes)
- [x] Updated frontend API config
- [x] Added comments for clarity
- [x] Maintained all permissions
- [x] Maintained all controller methods
- [x] Created documentation

---

## 🚀 Deployment Notes

### Before Deployment

1. ✅ Update frontend API config
2. ✅ Update backend routes
3. ⏳ Clear route cache
4. ⏳ Test all endpoints
5. ⏳ Update API documentation

### After Deployment

1. Monitor for 404 errors
2. Check application logs
3. Verify upload/download functionality
4. Update any external integrations

---

## 📖 Summary

**What Changed:**
- Centralized all upload/download routes in `routes/api/uploads.php`
- Introduced clear prefixes: `/uploads` and `/downloads`
- Updated frontend API config to match new routes

**What Stayed the Same:**
- Controller methods unchanged
- Permissions unchanged
- Functionality unchanged
- Response formats unchanged

**Benefits:**
- Better organization
- Easier maintenance
- Clearer structure
- Consistent patterns

---

**Status:** ✅ **COMPLETE**  
**Version:** 2.0.0 (Route Reorganization)  
**Date:** December 30, 2025  
**Implemented By:** AI Assistant

