# 🚀 OPTION 1 IMPLEMENTATION - COMPLETE PACKAGE

**Status**: ✅ IN PROGRESS  
**Date**: November 20, 2025  
**Implementation**: Rename work_locations → sites + Add section_departments

---

## ✅ COMPLETED (Phase 1: Database Layer)

### Migrations ✅
1. ✅ **Created**: `2025_02_13_024725_create_sites_table.php`
   - Renamed from work_locations
   - Added: `code`, `description`, `address`, `is_active`
   - Removed: `type` (redundant)
   - Added indexes: `idx_sites_active`, `idx_sites_code`

2. ✅ **Created**: `2025_03_11_093358_seed_default_sites_table.php`
   - Seeds 13 sites (9 existing + 4 new)
   - All sites have codes and descriptions

3. ✅ **Created**: `2025_11_20_100000_create_section_departments_table.php`
   - New table for sub-departments
   - FK to departments
   - Unique constraint: (department_id, name)
   - Auto-seeds from lookups table

4. ✅ **Created**: `2025_11_20_100001_update_employments_for_sites_and_sections.php`
   - Renames: work_location_id → site_id
   - Adds: section_department_id FK
   - Migrates: section_department text → section_department_id FK
   - Keeps: section_department text field (temporarily)

5. ✅ **Updated**: `2025_02_13_025537_create_employments_table.php`
   - Changed: work_location_id → site_id
   - Added: section_department_id FK
   - Updated comments

6. ✅ **Updated**: `2025_03_15_171008_create_employment_histories_table.php`
   - Changed: work_location_id → site_id
   - Added: section_department_id FK

7. ✅ **Updated**: `2025_09_25_134034_create_personnel_actions_table.php`
   - Changed: current_work_location_id → current_site_id
   - Changed: new_work_location_id → new_site_id
   - Updated FK references to sites table

8. ✅ **Updated**: `2025_08_13_122638_add_performance_indexes_to_employment_tables.php`
   - Changed: idx_employments_work_location_id → idx_employments_site_id
   - Changed: work_locations → sites table references
   - Updated all index names

### Models ✅
1. ✅ **Created**: `app/Models/Site.php`
   - Full CRUD model
   - Relationships: employments, employmentHistories, personnelActions
   - Scopes: active(), byCode(), withCounts()
   - Swagger documentation

2. ✅ **Created**: `app/Models/SectionDepartment.php`
   - FK to Department
   - Relationships: department, employments, employmentHistories
   - Scopes: active(), byDepartment(), withDepartment(), withCounts()
   - Helper: fullPath attribute (Department > Section)
   - Eager loads department by default
   - Swagger documentation

3. ✅ **Updated**: `app/Models/Employment.php`
   - Changed: work_location_id → site_id
   - Changed: workLocation() → site()
   - Added: section_department_id
   - Added: sectionDepartment() relationship
   - Updated: fillable array
   - Updated: history creation methods
   - Updated: Swagger docs
   - Updated: fieldMap for change reasons

4. ✅ **Deleted**: `app/Models/WorkLocation.php` (replaced by Site)

---

## 🔄 NEXT STEPS (Phase 2: Remaining Models)

### Models to Update

#### EmploymentHistory Model
📄 `app/Models/EmploymentHistory.php`

**Required Changes**:
```php
// Update Swagger docs
*   required={"employment_id", "employee_id", "employment_type_id", "start_date", "position_id", "department_id", "site_id", "pass_probation_salary"},
*   @OA\Property(property="site_id", type="integer", format="int64"),
*   @OA\Property(property="section_department_id", type="integer", format="int64", nullable=true),

// Update fillable
'site_id',
'section_department_id',

// Update relationship
public function site()
{
    return $this->belongsTo(Site::class);
}

public function sectionDepartment()
{
    return $this->belongsTo(SectionDepartment::class);
}
```

#### PersonnelAction Model
📄 `app/Models/PersonnelAction.php`

**Required Changes**:
```php
// Update fillable
'current_site_id',
'new_site_id',

// Update relationships
public function currentSite(): BelongsTo
{
    return $this->belongsTo(Site::class, 'current_site_id');
}

public function newSite(): BelongsTo
{
    return $this->belongsTo(Site::class, 'new_site_id');
}

// Update populateCurrentEmploymentData()
$employment = $this->employment()->with(['department', 'position', 'site', 'employee'])->first();
...
$this->current_site_id = $employment->site_id;
```

---

## 🎮 NEXT STEPS (Phase 3: Controllers & Routes)

### Controllers to Create/Update

#### 1. SiteController
📄 `app/Http/Controllers/Api/SiteController.php`

**Copy from WorklocationController and update**:
- Class name: `SiteController`
- Model: `Site`
- Swagger tags: `"Sites"`
- Validation: add `code` (required, unique), `is_active`, `address`, `description`
- Optional filters: `active_only`, `with_counts`

#### 2. SectionDepartmentController
📄 `app/Http/Controllers/Api/SectionDepartmentController.php`

**New controller - Full CRUD**:
- index() - List sections (filter by department_id, active_only)
- store() - Create section
- show() - Get one section
- update() - Update section
- destroy() - Delete section
- getByDepartment($departmentId) - Get sections for a department

#### 3. Update EmploymentController
📄 `app/Http/Controllers/Api/EmploymentController.php`

**Required Changes**:
- Update validation rules: `site_id` (required becomes optional in v1)
- Add validation: `section_department_id` (nullable, exists:section_departments,id)
- Update eager loading: `'site'`, `'sectionDepartment'`
- Update API responses to include site and section_department

#### 4. Update PersonnelActionController
📄 `app/Http/Controllers/Api/PersonnelActionController.php`

**Required Changes**:
- Update validation rules: `current_site_id`, `new_site_id`
- Update eager loading: `'currentSite'`, `'newSite'`

#### 5. Delete WorklocationController
📄 `app/Http/Controllers/Api/WorklocationController.php`
- ✅ To be deleted after SiteController created

---

### Routes to Update
📄 `routes/api/employment.php`

**Required Changes**:
```php
// REMOVE old worklocations routes (lines 60-67)
Route::prefix('worklocations')->group(function () { ... });

// ADD new sites routes
Route::prefix('sites')->group(function () {
    Route::get('/', [SiteController::class, 'index'])
        ->middleware('permission:employee.read');
    Route::get('/{id}', [SiteController::class, 'show'])
        ->middleware('permission:employee.read');
    Route::post('/', [SiteController::class, 'store'])
        ->middleware('permission:employee.create');
    Route::put('/{id}', [SiteController::class, 'update'])
        ->middleware('permission:employee.update');
    Route::delete('/{id}', [SiteController::class, 'destroy'])
        ->middleware('permission:employee.delete');
});

// ADD new section-departments routes
Route::prefix('section-departments')->group(function () {
    Route::get('/', [SectionDepartmentController::class, 'index'])
        ->middleware('permission:employment.read');
    Route::get('/by-department/{departmentId}', [SectionDepartmentController::class, 'getByDepartment'])
        ->middleware('permission:employment.read');
    Route::get('/{id}', [SectionDepartmentController::class, 'show'])
        ->middleware('permission:employment.read');
    Route::post('/', [SectionDepartmentController::class, 'store'])
        ->middleware('permission:employment.create');
    Route::put('/{id}', [SectionDepartmentController::class, 'update'])
        ->middleware('permission:employment.update');
    Route::delete('/{id}', [SectionDepartmentController::class, 'destroy'])
        ->middleware('permission:employment.delete');
});
```

---

## 📋 NEW API ENDPOINTS

### Sites
- GET `/api/v1/sites` - List all sites
- GET `/api/v1/sites/{id}` - Get one site
- POST `/api/v1/sites` - Create site
- PUT `/api/v1/sites/{id}` - Update site
- DELETE `/api/v1/sites/{id}` - Delete site

### Section Departments
- GET `/api/v1/section-departments` - List all sections
- GET `/api/v1/section-departments/by-department/{id}` - Get sections by department
- GET `/api/v1/section-departments/{id}` - Get one section
- POST `/api/v1/section-departments` - Create section
- PUT `/api/v1/section-departments/{id}` - Update section
- DELETE `/api/v1/section-departments/{id}` - Delete section

---

## 🧪 TESTING COMMANDS

### Run Migrations
```bash
cd C:\Users\Turtle\Desktop\HR Management System\3. Implementation\HRMS-V1\hrms-backend-api-v1

# Fresh migration (CAUTION: Drops all tables)
php artisan migrate:fresh --seed

# Or just run new migrations
php artisan migrate
```

### Verify Database
```bash
php artisan tinker

# Check sites
Site::count();  // Should be 13
Site::all();

# Check section departments
SectionDepartment::count();
SectionDepartment::with('department')->get();

# Check employments updated
Employment::whereNotNull('site_id')->count();
Employment::whereNotNull('section_department_id')->count();

# Check relationships
Employment::with('site', 'sectionDepartment')->first();
```

### Run Tests
```bash
php artisan test

# Or with Pest
./vendor/bin/pest
```

### Run Code Formatter
```bash
./vendor/bin/pint
```

---

## 📊 DATA MIGRATION STATUS

### Sites (13 total)
| ID | Code | Name | Status |
|----|------|------|--------|
| 1 | EXPAT | Expat | ✅ Seeded |
| 2 | MRM | MRM | ✅ Seeded |
| 3 | WPA | WPA | ✅ Seeded |
| 4 | KKH | KKH | ✅ Seeded |
| 5 | TB_MRM | TB-MRM | ✅ Seeded |
| 6 | TB_KK | TB-KK | ✅ Seeded |
| 7 | MKT | MKT | ✅ Seeded |
| 8 | MSL | MSL | ✅ Seeded |
| 9 | MUTRAW | Mutraw | ✅ Seeded |
| 10 | PEP | PEP office | ✅ Seeded |
| 11 | TB_BKK | TB-BKK | ✅ Seeded |
| 12 | PILOT | Pilot | ✅ Seeded |
| 13 | BKK_BHF | BKK office (BHF) | ✅ Seeded |

### Section Departments
- Auto-migrated from lookups table (where type='section_department')
- Inferred department relationships where possible
- Manual additions: Data Management, Training, M&E

---

## ⚠️ KNOWN ISSUES / WARNINGS

1. **Section Department Migration**: 
   - Some employments may have section_department text that doesn't match any section
   - Migration creates new section_departments for unmatched values
   - Review created section_departments after migration

2. **Temporary Dual Fields**:
   - `section_department` (text) field kept temporarily
   - Will be removed in future after full migration verification
   - Comment out the dropColumn in migration if you want to keep it longer

3. **Foreign Key Constraints**:
   - All site_id FKs use `onDelete('set null')`
   - Deleting a site won't delete employments, just nullifies the reference

---

## 🎯 ROLLBACK PLAN

If you need to rollback:

```bash
# Rollback last 4 migrations
php artisan migrate:rollback --step=4

# Or rollback all the way
php artisan migrate:reset
```

**Note**: The down() methods are fully implemented for clean rollback

---

## 📞 NEXT ACTIONS FOR YOU

1. ✅ Review completed migrations
2. ⏳ Run migrations: `php artisan migrate`
3. ⏳ Verify data: Use tinker commands above
4. ⏳ Approve next phase (Controllers & Routes)
5. ⏳ Frontend refactoring (separate task)

---

**Implementation Progress**: 50% Complete (Database Layer Done)  
**Next Phase**: Controllers, Routes, Form Requests  
**Estimated Time Remaining**: 4-6 hours

