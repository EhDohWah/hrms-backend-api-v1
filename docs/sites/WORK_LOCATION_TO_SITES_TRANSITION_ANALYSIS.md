# 🔍 COMPREHENSIVE ANALYSIS: work_locations → sites Transition

**Date**: November 20, 2025  
**Purpose**: Analyze impact of renaming/replacing `work_locations` with `sites` and adding `section_departments`  
**Status**: Analysis Complete - Ready for Implementation Decision

---

## 📊 Executive Summary

### Current Situation
- ✅ **work_locations** table exists with **9 seeded sites** (Expat, MRM, WPA, KKH, TB-MRM, TB-KK, MKT, MSL, Mutraw)
- ✅ Simple schema: `id`, `name`, `type`, timestamps
- ✅ Fully integrated: Used in Employment, PersonnelAction, EmploymentHistory
- ✅ API endpoints active at `/api/v1/worklocations`
- ✅ Frontend integration complete with services and components

### Proposed Changes
1. **Rename** `work_locations` → `sites` (keep structure, just rename)
2. **Add** `section_departments` table (new sub-department tracking)
3. **Update** `employments` table to reference both

### Recommendation: **OPTION 1 - RENAME EXISTING MIGRATION** ✅

---

## 🗂️ Current Database State

### work_locations Table (Current)
```sql
CREATE TABLE work_locations (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(255),
    type VARCHAR(255),  -- All set to "Site"
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

**Seeded Data (9 sites)**:
| ID | Name | Type |
|----|------|------|
| 1 | Expat | Site |
| 2 | MRM | Site |
| 3 | WPA | Site |
| 4 | KKH | Site |
| 5 | TB-MRM | Site |
| 6 | TB-KK | Site |
| 7 | MKT | Site |
| 8 | MSL | Site |
| 9 | Mutraw | Site |

**Observation**: The `type` column is redundant (always "Site"). All records are organizational sites.

---

## 🔗 Current Dependencies Analysis

### 1. Database Migrations (6 files)

#### Primary Migration
📄 **2025_02_13_024725_create_work_locations_table.php**
- Creates the table
- Seeds NO data (empty)

#### Seeder Migration
📄 **2025_03_11_093358_create_default_worklocations_table.php**
- Seeds 9 work locations
- Hardcoded site names

#### Foreign Key References
📄 **2025_02_13_025537_create_employments_table.php**
```php
$table->foreignId('work_location_id')
      ->nullable()
      ->constrained('work_locations')
      ->nullOnDelete();
```

📄 **2025_03_15_171008_create_employment_histories_table.php**
```php
$table->foreignId('work_location_id')
      ->constrained('work_locations');
```

📄 **2025_09_25_134034_create_personnel_actions_table.php**
```php
$table->foreignId('current_work_location_id')
      ->nullable()
      ->constrained('work_locations');
$table->foreignId('new_work_location_id')
      ->nullable()
      ->constrained('work_locations');
```

📄 **2025_08_13_122638_add_performance_indexes_to_employment_tables.php**
- May have indexes referencing work_location_id

---

### 2. Models (4 files)

#### Primary Model
📄 **app/Models/WorkLocation.php**
```php
class WorkLocation extends Model
{
    protected $fillable = ['name', 'type', 'created_by', 'updated_by'];
    
    public function employments() {
        return $this->hasMany(Employment::class);
    }
}
```

#### Related Models Using work_location_id
📄 **app/Models/Employment.php**
- ✅ Relationship: `workLocation()` method
- ✅ Fillable: `work_location_id`
- ✅ History tracking includes work_location_id changes
- ✅ Swagger docs reference work_location_id

📄 **app/Models/EmploymentHistory.php**
- ✅ Relationship: `workLocation()` method
- ✅ Fillable: `work_location_id`
- ✅ Required field in schema

📄 **app/Models/PersonnelAction.php**
- ✅ Relationships: `currentWorkLocation()`, `newWorkLocation()`
- ✅ Fillable: `current_work_location_id`, `new_work_location_id`
- ✅ Populated in `populateCurrentEmploymentData()` method

---

### 3. Controllers (5 files)

#### Primary Controller
📄 **app/Http/Controllers/Api/WorklocationController.php**
- ✅ Full CRUD: index, show, store, update, destroy
- ✅ Swagger documented
- ✅ Validation rules for name & type
- ✅ Auth user tracking (created_by, updated_by)

#### Controllers Using WorkLocation
📄 **app/Http/Controllers/Api/EmploymentController.php**
- ✅ Returns work_location_id in responses
- ✅ Validates work_location_id on create/update
- ✅ Eager loads `workLocation` relationship

📄 **app/Http/Controllers/Api/EmployeeController.php**
- ✅ May use in employee employment details

📄 **app/Http/Controllers/Api/PersonnelActionController.php**
- ✅ Uses current_work_location_id and new_work_location_id
- ✅ Populates work location data

📄 **app/Http/Controllers/Api/Reports/LeaveRequestReportController.php**
- ✅ May include work location in reports

---

### 4. API Routes (1 file)

📄 **routes/api/employment.php** (Lines 60-67)
```php
Route::prefix('worklocations')->group(function () {
    Route::get('/', [WorklocationController::class, 'index'])
        ->middleware('permission:employee.read');
    Route::get('/{id}', [WorklocationController::class, 'show'])
        ->middleware('permission:employee.read');
    Route::post('/', [WorklocationController::class, 'store'])
        ->middleware('permission:employee.create');
    Route::put('/{id}', [WorklocationController::class, 'update'])
        ->middleware('permission:employee.update');
    Route::delete('/{id}', [WorklocationController::class, 'destroy'])
        ->middleware('permission:employee.delete');
});
```

**API Endpoints Active**:
- GET `/api/v1/worklocations` - List all
- GET `/api/v1/worklocations/{id}` - Show one
- POST `/api/v1/worklocations` - Create
- PUT `/api/v1/worklocations/{id}` - Update
- DELETE `/api/v1/worklocations/{id}` - Delete

---

### 5. Frontend Integration (13 files)

#### Services
📄 **src/services/worklocation.service.js**
- ✅ API service for work locations
- ✅ CRUD operations

📄 **src/services/employment.service.js**
- ✅ Uses work_location_id

📄 **src/services/api.service.js**
- ✅ May have work location endpoints

#### Components
📄 **src/components/modal/work-location-modal.vue**
- ✅ Dedicated modal for work location management

📄 **src/components/modal/employment-modal.vue**
- ✅ Work location dropdown

📄 **src/components/modal/employment-edit-modal.vue**
- ✅ Work location dropdown

📄 **src/components/reports/leave-report.vue**
- ✅ May display work location

📄 **src/components/reports/leave-report-updated.vue**
- ✅ May display work location

#### Pages
📄 **src/views/pages/hrm/employment/employment-list.vue**
- ✅ Displays work location

📄 **src/views/pages/hrm/employees/employee-details.vue**
- ✅ Shows work location

#### Stores & Composables
📄 **src/stores/employeeStore.js**
- ✅ May cache work locations

📄 **src/composables/useDropdownData.js**
- ✅ Work location dropdown data

#### Configuration
📄 **src/config/api.config.js** (Lines 198-205)
```javascript
WORK_LOCATION: {
    LIST: '/worklocations',
    CREATE: '/worklocations',
    UPDATE: '/worklocations/:id',
    DELETE: '/worklocations/:id',
    DETAILS: '/worklocations/:id'
}
```

---

## 🎯 Two Implementation Options

### **OPTION 1: RENAME EXISTING MIGRATION** ✅ RECOMMENDED

#### Advantages
1. ✅ **Clean migration history** - No orphaned tables
2. ✅ **Preserves data** - Existing work_location records become sites
3. ✅ **Simpler refactoring** - One-to-one rename across codebase
4. ✅ **No migration conflicts** - No duplicate tables
5. ✅ **Fresh database compatible** - Works for new installs
6. ✅ **Follows Laravel naming** - Migration filename matches table name

#### Steps Required
1. Rename migration file:
   - `2025_02_13_024725_create_work_locations_table.php`
   - → `2025_02_13_024725_create_sites_table.php`

2. Rename seeder migration:
   - `2025_03_11_093358_create_default_worklocations_table.php`
   - → `2025_03_11_093358_create_default_sites_table.php`

3. Update table name in migrations (work_locations → sites)

4. Update foreign keys in dependent migrations

5. Rename model (WorkLocation → Site)

6. Update model relationships

7. Rename controller (WorklocationController → SiteController)

8. Update route prefix (worklocations → sites)

9. Update frontend (13 files)

#### Complexity: **MEDIUM** ⚠️
- Requires global find/replace
- Must update all references
- Single commit, clean history

---

### **OPTION 2: NEW MIGRATION + DROP OLD TABLE**

#### Advantages
1. ✅ **Migration history preserved** - Shows evolution
2. ✅ **Existing migrations untouched** - Less risk of breaking past
3. ✅ **Data migration explicit** - Clear data transfer step

#### Disadvantages
1. ❌ **Orphaned migration** - Old work_locations migration still exists
2. ❌ **Two-step process** - Create sites, migrate data, drop work_locations
3. ❌ **Migration complexity** - Need data migration + cleanup
4. ❌ **Fresh install confusion** - Creates then drops table
5. ❌ **Rollback complexity** - Need to reverse data migration

#### Steps Required
1. Create new migration: `2025_11_20_100000_create_sites_table.php`

2. Migrate data from work_locations → sites

3. Update foreign keys: work_location_id → site_id

4. Rename model, controller, routes

5. Drop work_locations table (separate migration or same)

6. Update frontend

#### Complexity: **HIGH** 🔴
- More complex migration logic
- Data migration + FK updates
- Cleanup step required

---

## 📝 Proposed New Schema (Both Options)

### sites Table (Enhanced)
```sql
CREATE TABLE sites (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) UNIQUE,  -- NEW: Short code (e.g., MRM, WPA)
    description TEXT NULL,     -- NEW: Detailed description
    address TEXT NULL,         -- NEW: Physical address
    is_active BOOLEAN DEFAULT TRUE,  -- NEW: Active/inactive
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    INDEX idx_sites_active (is_active),
    INDEX idx_sites_code (code)
);
```

**Removed**: `type` column (redundant - all are "Site")  
**Added**: `code`, `description`, `address`, `is_active`

### section_departments Table (NEW)
```sql
CREATE TABLE section_departments (
    id BIGINT UNSIGNED PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    department_id BIGINT UNSIGNED NOT NULL,  -- FK → departments
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    
    FOREIGN KEY (department_id) 
        REFERENCES departments(id) 
        ON DELETE CASCADE,
    
    UNIQUE KEY unique_section_dept (department_id, name),
    INDEX idx_section_dept_active (department_id, is_active)
);
```

### employments Table Updates
```sql
-- BEFORE (Option 1):
ALTER TABLE employments 
    RENAME COLUMN work_location_id TO site_id;

-- OR (Option 2):
ALTER TABLE employments 
    DROP FOREIGN KEY fk_employments_work_location;
ALTER TABLE employments 
    ADD COLUMN site_id BIGINT UNSIGNED NULL AFTER position_id;
ALTER TABLE employments 
    ADD FOREIGN KEY (site_id) REFERENCES sites(id) ON DELETE SET NULL;
ALTER TABLE employments 
    DROP COLUMN work_location_id;

-- BOTH OPTIONS ADD:
ALTER TABLE employments 
    ADD COLUMN section_department_id BIGINT UNSIGNED NULL AFTER site_id;
ALTER TABLE employments 
    ADD FOREIGN KEY (section_department_id) 
        REFERENCES section_departments(id) 
        ON DELETE SET NULL;

-- Remove old text field:
ALTER TABLE employments 
    DROP COLUMN section_department;
```

---

## 🔄 Data Migration Strategy

### 13 Sites to Create (Based on Excel)
```php
$sites = [
    ['name' => 'Expat', 'code' => 'EXPAT'],
    ['name' => 'MRM', 'code' => 'MRM'],
    ['name' => 'WPA', 'code' => 'WPA'],
    ['name' => 'KKH', 'code' => 'KKH'],
    ['name' => 'TB-MRM', 'code' => 'TB_MRM'],
    ['name' => 'TB-KK', 'code' => 'TB_KK'],
    ['name' => 'MKT', 'code' => 'MKT'],
    ['name' => 'MSL', 'code' => 'MSL'],
    ['name' => 'Mutraw', 'code' => 'MUTRAW'],
    ['name' => 'PEP office', 'code' => 'PEP'],
    ['name' => 'TB-BKK', 'code' => 'TB_BKK'],
    ['name' => 'Pilot', 'code' => 'PILOT'],
    ['name' => 'BKK office (BHF)', 'code' => 'BKK_BHF'],
];
```

**Existing Data**: 9 sites already in work_locations  
**New Sites**: 4 additional sites from Excel (PEP office, TB-BKK, Pilot, BKK office)

---

## 📋 Complete Refactoring Checklist

### Backend Changes (Option 1 - Rename)

#### Database Layer
- [ ] Rename migration: `create_work_locations_table.php` → `create_sites_table.php`
- [ ] Rename seeder migration: `create_default_worklocations_table.php` → `create_default_sites_table.php`
- [ ] Update table name in both migrations: `work_locations` → `sites`
- [ ] Add enhanced fields (`code`, `description`, `address`, `is_active`)
- [ ] Remove `type` column
- [ ] Update seeded data to include all 13 sites
- [ ] Create `section_departments` migration
- [ ] Update `employments` migration: `work_location_id` → `site_id`
- [ ] Update `employment_histories` migration foreign keys
- [ ] Update `personnel_actions` migration foreign keys
- [ ] Update performance indexes migration

#### Model Layer
- [ ] Rename `WorkLocation.php` → `Site.php`
- [ ] Update class name: `WorkLocation` → `Site`
- [ ] Update table name: `protected $table = 'sites';`
- [ ] Update fillable array
- [ ] Update Swagger schema
- [ ] Create `SectionDepartment.php` model
- [ ] Update `Employment.php`:
  - [ ] Rename `workLocation()` → `site()`
  - [ ] Rename fillable `work_location_id` → `site_id`
  - [ ] Add `sectionDepartment()` relationship
  - [ ] Add `section_department_id` to fillable
  - [ ] Update history tracking
  - [ ] Update Swagger docs
- [ ] Update `EmploymentHistory.php` relationships
- [ ] Update `PersonnelAction.php` relationships

#### Controller Layer
- [ ] Rename `WorklocationController.php` → `SiteController.php`
- [ ] Update class name
- [ ] Update Swagger tags: "Work Locations" → "Sites"
- [ ] Update model references: `WorkLocation` → `Site`
- [ ] Create `SectionDepartmentController.php`
- [ ] Update `EmploymentController.php` references
- [ ] Update `PersonnelActionController.php` references
- [ ] Update report controllers

#### Routes Layer
- [ ] Update `routes/api/employment.php`:
  - [ ] Rename prefix: `worklocations` → `sites`
  - [ ] Update controller reference
  - [ ] Add `section-departments` routes

#### Validation/Requests
- [ ] Check for Form Request classes using `work_location_id`
- [ ] Update validation rules if any

---

### Frontend Changes

#### Services
- [ ] Rename `worklocation.service.js` → `site.service.js`
- [ ] Update API endpoints: `/worklocations` → `/sites`
- [ ] Update method names
- [ ] Create `sectionDepartment.service.js`
- [ ] Update `employment.service.js` references
- [ ] Update `api.service.js`

#### Config
- [ ] Update `api.config.js`:
  - [ ] Rename `WORK_LOCATION` → `SITE`
  - [ ] Update endpoints
  - [ ] Add `SECTION_DEPARTMENT` config

#### Components
- [ ] Rename `work-location-modal.vue` → `site-modal.vue`
- [ ] Update component internal references
- [ ] Create `section-department-modal.vue`
- [ ] Update `employment-modal.vue` dropdowns
- [ ] Update `employment-edit-modal.vue` dropdowns
- [ ] Update report components

#### Pages
- [ ] Update `employment-list.vue` display
- [ ] Update `employee-details.vue` display

#### Stores
- [ ] Update `employeeStore.js` cache keys

#### Composables
- [ ] Update `useDropdownData.js` work location fetching

---

## 🎨 New Organizational Structure

```
Employment Record:
├── Site (site_id → sites)
│   └── Physical/organizational location (MRM, Expat, TB-KK)
│
├── Department (department_id → departments)
│   └── Main department (Laboratory, HR, Clinical)
│
├── Section Department (section_department_id → section_departments)
│   └── Sub-department within department (Data, Training, M&E)
│
└── Position (position_id → positions)
    ├── Job title (Lab Technician, HR Manager)
    └── Reports To (reports_to_position_id → positions)
```

**Complete Organizational Path Example**:
```
Site: MRM
└─ Department: Laboratory
   └─ Section: Data Management
      └─ Position: Lab Technician
         └─ Reports To: Laboratory Manager
```

---

## ⚡ Migration Filename Format

Based on your positions migration: `2025_02_12_025438_create_positions_table.php`

### For Option 1 (Rename):
- Existing: `2025_02_13_024725_create_work_locations_table.php`
- Rename to: `2025_02_13_024725_create_sites_table.php`
- New: `2025_11_20_100000_create_section_departments_table.php`
- New: `2025_11_20_100001_update_employments_for_sites_and_sections.php`

### For Option 2 (New + Drop):
- Keep: `2025_02_13_024725_create_work_locations_table.php`
- New: `2025_11_20_100000_create_sites_table.php`
- New: `2025_11_20_100001_create_section_departments_table.php`
- New: `2025_11_20_100002_migrate_worklocation_to_sites.php`
- New: `2025_11_20_100003_drop_work_locations_table.php`

---

## ⚠️ Risks & Mitigation

### Option 1 Risks:
1. **Risk**: Breaking existing production data
   - **Mitigation**: Test on staging, backup database, create rollback migration

2. **Risk**: Frontend breaks before update deployed
   - **Mitigation**: Deploy backend first (keep both APIs active temporarily)

3. **Risk**: Global find/replace misses edge cases
   - **Mitigation**: Use IDE refactoring tools, run tests, manual verification

### Option 2 Risks:
1. **Risk**: Data migration fails mid-process
   - **Mitigation**: Transaction-based migration, test on staging

2. **Risk**: Orphaned work_locations table confusion
   - **Mitigation**: Clear documentation, cleanup migration

3. **Risk**: Complex rollback
   - **Mitigation**: Well-tested down() methods

---

## 📊 Impact Analysis Summary

| Aspect | Files Affected | Complexity | Breaking Change |
|--------|----------------|------------|-----------------|
| **Migrations** | 6 files | Medium | Yes (FK updates) |
| **Models** | 4 files | Low | No (internal only) |
| **Controllers** | 5 files | Low | No (internal only) |
| **Routes** | 1 file | Medium | **Yes** (API path change) |
| **Frontend Services** | 3 files | Medium | Yes (API paths) |
| **Frontend Components** | 8 files | Medium | Yes (data binding) |
| **Frontend Config** | 1 file | Low | Yes (API paths) |
| **Frontend Stores** | 1 file | Low | Yes (cache keys) |

**Total Files to Modify**: ~29 files

---

## 🎯 Final Recommendation

### ✅ **OPTION 1: RENAME EXISTING MIGRATION**

**Rationale**:
1. **Cleaner architecture** - No orphaned tables or migrations
2. **Simpler refactoring** - One-to-one rename pattern
3. **Fresh install friendly** - No create→drop confusion
4. **Laravel best practice** - Migration name matches table name
5. **Your existing data** - Only 9 sites, easy to preserve/migrate

**Timeline Estimate**:
- Backend refactoring: 4-6 hours
- Frontend refactoring: 3-4 hours
- Testing: 2-3 hours
- **Total**: 9-13 hours

**Deployment Strategy**:
1. Deploy backend (keeping both APIs active for transition)
2. Deploy frontend
3. Remove old API endpoints after verification

---

## 📞 Next Steps

### If Choosing Option 1 (Recommended):
1. **Backup current database** 📦
2. **Create feature branch**: `feature/rename-worklocation-to-sites`
3. **Run provided implementation package** (I'll create)
4. **Test thoroughly on staging**
5. **Deploy to production**

### If Choosing Option 2:
1. **Backup current database** 📦
2. **Create feature branch**: `feature/add-sites-and-sections`
3. **Create migration sequence**
4. **Test data migration thoroughly**
5. **Deploy with careful monitoring**

---

## 📚 References

- Current work_locations: 9 sites, simple schema
- Proposed sites: 13 sites, enhanced schema
- New feature: section_departments with FK to departments
- Impact: 29 files across backend + frontend
- API breaking change: `/worklocations` → `/sites`

---

**Prepared by**: AI Assistant  
**Date**: November 20, 2025  
**Status**: ✅ Ready for Decision & Implementation

