# Upload System Documentation

> **HRMS Backend API v1 - File Upload & Bulk Import System**  
> **Last Updated:** January 8, 2026

---

## 📚 Table of Contents

1. [Overview](#overview)
2. [Documentation Structure](#documentation-structure)
3. [Quick Links](#quick-links)
4. [Existing Uploads](#existing-uploads)
5. [Creating New Uploads](#creating-new-uploads)
6. [Architecture](#architecture)

---

## Overview

The HRMS upload system provides bulk data import functionality via Excel files. Users can download templates, fill them with data, and upload them for batch processing.

### Key Features

✅ **Asynchronous Processing** - Large uploads processed in background  
✅ **Progress Tracking** - Real-time upload progress  
✅ **Validation** - Client and server-side validation  
✅ **Error Handling** - Detailed error reporting per row  
✅ **Duplicate Detection** - Smart handling of existing records  
✅ **Auto-Calculations** - Automatic field calculations  
✅ **Notifications** - User notified upon completion  
✅ **Permission-Based** - Read/Edit permissions per module  

---

## Documentation Structure

### 📖 For Users Creating New Uploads

| Document | Purpose | When to Use |
|----------|---------|-------------|
| [QUICK_START_NEW_UPLOAD.md](./QUICK_START_NEW_UPLOAD.md) | Quick reference & checklist | Start here for overview |
| [NEW_UPLOAD_REQUEST_TEMPLATE.md](./NEW_UPLOAD_REQUEST_TEMPLATE.md) | Request form to fill out | Fill this before requesting implementation |
| [UPLOAD_MENU_CREATION_GUIDE.md](./UPLOAD_MENU_CREATION_GUIDE.md) | Complete implementation guide | For developers implementing the upload |

### 📖 For Understanding Existing Uploads

| Document | Purpose |
|----------|---------|
| [EMPLOYEE_FUNDING_ALLOCATION_UPLOAD_IMPLEMENTATION.md](./EMPLOYEE_FUNDING_ALLOCATION_UPLOAD_IMPLEMENTATION.md) | Example implementation walkthrough |
| [EMPLOYEE_FUNDING_ALLOCATION_TEMPLATE_FIELDS.md](./EMPLOYEE_FUNDING_ALLOCATION_TEMPLATE_FIELDS.md) | Detailed field documentation example |
| [TEMPLATE_UPDATE_SUMMARY.md](./TEMPLATE_UPDATE_SUMMARY.md) | Example of template update process |
| [PERMISSIONS_SETUP.md](./PERMISSIONS_SETUP.md) | Permission configuration guide |

---

## Quick Links

### 🚀 I want to...

**Create a new upload menu:**
1. Read: [QUICK_START_NEW_UPLOAD.md](./QUICK_START_NEW_UPLOAD.md)
2. Fill out: [NEW_UPLOAD_REQUEST_TEMPLATE.md](./NEW_UPLOAD_REQUEST_TEMPLATE.md)
3. Submit to developer

**Implement a new upload (Developer):**
1. Review filled request template
2. Follow: [UPLOAD_MENU_CREATION_GUIDE.md](./UPLOAD_MENU_CREATION_GUIDE.md)
3. Use existing uploads as reference

**Understand how uploads work:**
1. Read: [Architecture](#architecture) section below
2. Study: [EMPLOYEE_FUNDING_ALLOCATION_UPLOAD_IMPLEMENTATION.md](./EMPLOYEE_FUNDING_ALLOCATION_UPLOAD_IMPLEMENTATION.md)

**Update an existing upload:**
1. Review: [TEMPLATE_UPDATE_SUMMARY.md](./TEMPLATE_UPDATE_SUMMARY.md)
2. Follow same process as creating new upload

**Fix permission issues:**
1. Check: [PERMISSIONS_SETUP.md](./PERMISSIONS_SETUP.md)
2. Run seeder scripts documented there

---

## Existing Uploads

### Current Upload Menus

| Module | Category | Template Columns | Status | Documentation |
|--------|----------|------------------|--------|---------------|
| **Grants** | Grant Data | 15 columns | ✅ Active | - |
| **Employees** | Employee Data | 25+ columns | ✅ Active | - |
| **Employment Records** | Employment | 19 columns | ✅ Active | - |
| **Employee Funding Allocations** | Employee | 10 columns | ✅ Active | [Docs](./EMPLOYEE_FUNDING_ALLOCATION_TEMPLATE_FIELDS.md) |
| **Payroll** | Payroll | Multiple | ✅ Active | - |

### File Locations

**Backend:**
- Controllers: `app/Http/Controllers/Api/`
  - `GrantController.php`
  - `EmployeeController.php`
  - `EmploymentController.php`
  - `EmployeeFundingAllocationController.php`
  
- Import Classes: `app/Imports/`
  - `GrantsImport.php`
  - `EmployeesImport.php`
  - `EmploymentsImport.php`
  - `EmployeeFundingAllocationsImport.php`
  
- Routes: `routes/api/uploads.php`

**Frontend:**
- Services: `src/services/`
  - `upload-grant.service.js`
  - `upload-employee.service.js`
  - `upload-employment.service.js`
  - `upload-funding-allocation.service.js`
  
- Components: `src/components/uploads/`
  - `grant-upload.vue`
  - `employee-upload.vue`
  - `employment-upload.vue`
  - `funding-allocation-upload.vue`
  - `upload-row.vue` (shared component)
  
- Upload List Page: `src/views/pages/administration/file-uploads/file-uploads-list.vue`

---

## Creating New Uploads

### Step-by-Step Process

#### Phase 1: Planning (You)
1. **Identify Need**
   - What data needs bulk import?
   - What table/model will store it?
   - What fields are required?

2. **Fill Request Template**
   - Copy `NEW_UPLOAD_REQUEST_TEMPLATE.md`
   - Fill out ALL sections (especially column definitions)
   - Provide sample data (3+ rows)
   - Define duplicate detection strategy

3. **Review & Submit**
   - Ensure all required info provided
   - Check for completeness
   - Submit to developer

#### Phase 2: Implementation (Developer)
1. **Backend Implementation** (~30 mins)
   - Create Import class
   - Add Controller methods
   - Register routes
   - Add module to seeder

2. **Frontend Implementation** (~20 mins)
   - Create service
   - Create component
   - Update API config
   - Integrate into upload list

3. **Permission Setup** (~10 mins)
   - Run seeders
   - Clear cache
   - Verify permissions

#### Phase 3: Testing (Both)
1. **Functional Testing**
   - Download template
   - Verify template structure
   - Upload sample data
   - Verify import results

2. **Edge Case Testing**
   - Invalid data
   - Duplicate records
   - Large files (1000+ rows)
   - Permission checks

3. **Acceptance**
   - Sign off on functionality
   - Document any issues
   - Deploy to production

### Time Estimates

| Phase | Duration | Responsibility |
|-------|----------|----------------|
| Fill Request Template | 10-15 mins | You |
| Backend Implementation | 20-30 mins | Developer |
| Frontend Implementation | 15-20 mins | Developer |
| Permission Setup | 5-10 mins | Developer |
| Testing | 15-30 mins | Both |
| **Total** | **65-105 mins** | **~1-2 hours** |

---

## Architecture

### System Flow

```
User Downloads Template
        ↓
Excel Template Generated (PhpSpreadsheet)
        ↓
User Fills Template with Data
        ↓
User Uploads File
        ↓
Frontend Validates (File type, size)
        ↓
Backend Validates (File format, required fields)
        ↓
Import Queued (Laravel Queue)
        ↓
Background Processing (Chunked, 50 rows at a time)
        ↓
Row Validation → Pass: Insert/Update | Fail: Log Error
        ↓
Import Complete
        ↓
User Notified (Success count, Error count)
```

### Technical Stack

**Backend:**
- Laravel 11 (PHP 8.2)
- Maatwebsite/Laravel-Excel (PhpSpreadsheet)
- Laravel Queue (Asynchronous processing)
- Laravel Notifications
- Spatie Permissions

**Frontend:**
- Vue 3
- Ant Design Vue
- Axios (API calls)
- File upload handling

### Key Components

#### 1. Import Class Pattern

```php
class YourModuleImport implements 
    ShouldQueue,           // Background processing
    SkipsEmptyRows,        // Skip blank rows
    SkipsOnFailure,        // Continue on validation errors
    ToCollection,          // Process as collection
    WithChunkReading,      // Process in chunks
    WithCustomValueBinder, // Custom value handling
    WithEvents,            // Import lifecycle events
    WithHeadingRow         // First row is headers
{
    // ... implementation
}
```

#### 2. Controller Methods

```php
// Generate and download Excel template
public function downloadTemplate() { }

// Accept file upload and queue import
public function upload(Request $request) { }
```

#### 3. Frontend Service Pattern

```javascript
class UploadService {
    // Upload file with progress tracking
    async uploadData(file, onProgress) { }
    
    // Download template
    async downloadTemplate() { }
    
    // Client-side validation
    validateFile(file) { }
}
```

#### 4. Upload Component Pattern

```vue
<template>
    <UploadRow 
        :upload="config"
        :uploading="uploading"
        :upload-progress="uploadProgress"
        @upload="handleUpload"
        @download-template="downloadTemplate"
    />
</template>

<script>
export default {
    // Component handles UI
    // Service handles API calls
}
</script>
```

### Performance Optimizations

1. **Chunk Processing** - Process 50 rows at a time to prevent memory issues
2. **Prefetch Lookups** - Load foreign key lookups once at start
3. **Bulk Insert** - Use batch inserts instead of individual saves
4. **Queue Processing** - Run in background to not block user
5. **Cache Management** - Store import progress and errors in cache

### Error Handling

**Levels:**
1. **Client-Side** - File type, size validation
2. **Server-Side** - Laravel validation rules
3. **Business Logic** - Custom validation in import class
4. **Row-Level** - Individual row errors logged and skipped

**User Notification:**
- Real-time progress during upload
- Notification upon completion
- Detailed error report (row numbers, reasons)

---

## Permission System

### Permission Structure

Each upload requires 2 permissions:

1. **Read Permission** (`{module}.read`)
   - Download template
   - View upload page

2. **Edit Permission** (`{module}.edit`)
   - Upload data
   - Process imports

### Permission Flow

```
Module Definition (ModuleSeeder)
        ↓
Permission Creation (PermissionRoleSeeder)
        ↓
Role Assignment (UserSeeder)
        ↓
Cache Clear
        ↓
Permission Middleware on Routes
```

### Default Permissions

- **Admin** - All read and edit permissions
- **HR Manager** - All read and edit permissions
- **Other Roles** - Assigned via UI

---

## Best Practices

### For Template Design

✅ **DO:**
- Include validation rules row
- Provide sample data (3+ rows)
- Use descriptive column names
- Include NOT NULL indicators
- Set appropriate column widths
- Use consistent date format (YYYY-MM-DD)

❌ **DON'T:**
- Use technical database names
- Skip validation descriptions
- Forget to document enum values
- Make all fields required unnecessarily

### For Import Logic

✅ **DO:**
- Prefetch lookup data in constructor
- Use chunked processing
- Validate before processing
- Log detailed errors
- Handle duplicates intelligently
- Clean up cache after import

❌ **DON'T:**
- Query in loops
- Process entire file at once
- Skip validation
- Silently fail
- Create duplicates
- Leave cache filled indefinitely

### For Error Messages

✅ **DO:**
- Include row number
- Specify which field failed
- Explain why it failed
- Suggest correction

❌ **DON'T:**
- Show generic errors
- Skip row numbers
- Use technical jargon
- Omit helpful context

**Good Example:**
```
Row 5 [effective_date]: Invalid date format. 
Expected YYYY-MM-DD, received '01/15/2025'.
```

**Bad Example:**
```
Validation failed
```

---

## Troubleshooting

### Common Issues

| Issue | Symptom | Solution |
|-------|---------|----------|
| Permission Denied | 403 error on download/upload | Run permission seeders, clear cache |
| Import Not Processing | Upload succeeds but no data | Check queue is running: `php artisan queue:work` |
| Template Missing Columns | Template has fewer columns than expected | Check controller `downloadTemplate()` method |
| Duplicate Records | Records duplicated instead of updated | Check duplicate detection logic in import |
| Slow Imports | Import takes very long | Reduce chunk size, add indexes to lookup tables |
| Memory Issues | Import crashes on large files | Increase PHP memory_limit, use chunking |

### Debug Commands

```bash
# Check routes registered
php artisan route:list --path=your-module

# Check queue is running
php artisan queue:work

# Check permissions exist
php artisan tinker
>>> \Spatie\Permission\Models\Permission::where('name', 'like', '%your_module%')->get();

# Check user has permission
>>> $user = \App\Models\User::find(1);
>>> $user->hasPermissionTo('your_module.read');

# Clear caches
php artisan cache:clear
php artisan permission:cache-reset
php artisan config:clear

# View logs
tail -f storage/logs/laravel.log
```

---

## FAQ

**Q: How long does it take to create a new upload menu?**  
A: 1-2 hours total if all information is provided upfront.

**Q: What's the maximum file size for uploads?**  
A: 10MB by default. Can be increased in validation rules.

**Q: How many rows can be imported at once?**  
A: Tested up to 10,000 rows. Uses chunked processing to handle large files.

**Q: What happens if the import fails midway?**  
A: Already processed chunks are saved. Failed rows are logged. User is notified with error details.

**Q: Can I update existing records via upload?**  
A: Yes, if duplicate detection is configured. Define which fields identify duplicates.

**Q: How do I know when import is complete?**  
A: User receives an in-app notification with counts (created, updated, errors).

**Q: Can I undo an import?**  
A: No automatic undo. Must be done manually via database or UI.

**Q: What Excel formats are supported?**  
A: .xlsx, .xls, and .csv files.

---

## Support & Contribution

### Getting Help

1. **Check documentation** - Start with Quick Start guide
2. **Review examples** - Study existing uploads
3. **Check logs** - Look at `storage/logs/laravel.log`
4. **Ask developer** - Provide filled request template

### Contributing

When adding new documentation:
1. Follow existing structure
2. Include code examples
3. Provide screenshots if UI-related
4. Update this README with links

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | 2026-01-08 | Initial documentation created |
| - | - | Employee Funding Allocation upload implemented |
| - | - | Complete documentation suite created |
| - | - | Request template created |
| - | - | Implementation guide created |

---

## Related Documentation

**Project-Wide:**
- `/docs/general/` - General architecture
- `/docs/authentication/` - Auth and permissions
- `/database/seeders/` - Seeder files

**Upload-Specific:**
- This folder contains all upload-related docs

---

**Last Updated:** January 8, 2026  
**Maintained By:** HRMS Development Team  
**Questions?** Check QUICK_START or UPLOAD_MENU_CREATION_GUIDE
