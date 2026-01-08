# Quick Start: Creating New Upload Menu

> **Quick Reference:** Use this for rapid implementation after filling out the request template

---

## 🚀 Quick Steps

### 1. Fill Request Template (5-10 mins)
- Copy `NEW_UPLOAD_REQUEST_TEMPLATE.md`
- Fill out ALL sections completely
- Provide to developer

### 2. Developer Implementation (30-60 mins)

Following the `UPLOAD_MENU_CREATION_GUIDE.md`, developer will:

**Backend (20-30 mins):**
1. Create Import class
2. Add Controller methods (upload + downloadTemplate)
3. Register routes
4. Add module to ModuleSeeder

**Frontend (15-20 mins):**
5. Create upload service
6. Create upload component
7. Update API config
8. Integrate into upload list page

**Permissions (5-10 mins):**
9. Run seeders
10. Clear cache
11. Verify permissions

### 3. Testing (15-30 mins)
- Download template
- Upload test data
- Verify import results
- Check notifications

---

## 📋 Information Checklist (Must Provide)

Before requesting implementation, YOU must provide:

### ✅ Module Basics
- [ ] Module name (snake_case, plural)
- [ ] Display name (User-friendly)
- [ ] Category (Employee, Payroll, etc.)
- [ ] Icon name (Tabler icon)
- [ ] Color theme (hex code)

### ✅ Database Info
- [ ] Table name
- [ ] Model name
- [ ] Migration file path
- [ ] All foreign key relationships

### ✅ Template Columns (CRITICAL!)
For EACH column, provide:
- [ ] Column name
- [ ] Data type (string, integer, decimal, date, boolean, enum)
- [ ] Required: Yes/No
- [ ] Validation rules (Laravel format)
- [ ] Validation description (user-friendly)
- [ ] Sample values (2-3 examples)
- [ ] Enum values (if applicable)
- [ ] Auto-calculate logic (if applicable)
- [ ] Auto-detect logic (if applicable)

### ✅ Import Behavior
- [ ] Duplicate detection strategy (which fields match?)
- [ ] On duplicate: update, skip, or error?
- [ ] Any special transformations needed?

### ✅ UI Details
- [ ] Section name
- [ ] Section description
- [ ] Position in list (after which section?)
- [ ] Color theme

### ✅ Sample Data
- [ ] At least 3 complete sample rows

---

## ⚡ Copy-Paste Template

```yaml
# QUICK UPLOAD REQUEST

Module: employee_salaries
Display: Employee Salaries
Category: Payroll
Icon: wallet
Color: #FF9800

Table: employee_salaries
Model: EmployeeSalary
Migration: 2025_01_08_create_employee_salaries_table.php

Columns:
  1. staff_id | string | required | "Employee staff ID (must exist)" | EMP001
  2. effective_date | date | required | "Date (YYYY-MM-DD)" | 2025-01-01
  3. base_salary | decimal | required | "Decimal(10,2) - Base salary" | 50000.00
  4. allowances | decimal | nullable | "Decimal(10,2) - Allowances" | 5000.00
  5. status | enum(active,inactive) | default:active | "Status" | active

Duplicate Detection: employee_id + effective_date → update
Auto-Calculate: total_salary = base_salary + allowances
Position: After Employee, Before Payroll

Samples:
  EMP001 | 2025-01-01 | 50000.00 | 5000.00 | active
  EMP002 | 2025-01-01 | 60000.00 | 7000.00 | active
  EMP003 | 2025-01-15 | 45000.00 | 4500.00 | active
```

---

## 🎯 Expected Deliverables

After implementation, you will receive:

### Backend Files
- ✅ Import class (`app/Imports/YourModuleImport.php`)
- ✅ Controller methods (upload + downloadTemplate)
- ✅ Routes registered (`routes/api/uploads.php`)
- ✅ Module seeded (`database/seeders/ModuleSeeder.php`)
- ✅ Permissions created and assigned

### Frontend Files
- ✅ Upload service (`src/services/upload-your-module.service.js`)
- ✅ Upload component (`src/components/uploads/your-module-upload.vue`)
- ✅ API config updated (`src/config/api.config.js`)
- ✅ Upload list integration (`src/views/pages/administration/file-uploads/file-uploads-list.vue`)

### Documentation
- ✅ Field documentation (optional)
- ✅ Implementation notes

### Testing Results
- ✅ Template download working
- ✅ Upload working (tested with sample data)
- ✅ Permissions verified
- ✅ Import notifications working

---

## 🔍 Quality Checklist

Before marking complete, verify:

**Backend:**
- [ ] Template downloads successfully
- [ ] Template has all specified columns
- [ ] Template has validation rules row
- [ ] Template has sample data rows
- [ ] Upload accepts file
- [ ] Import processes in background
- [ ] Duplicate detection works correctly
- [ ] Notifications are sent

**Frontend:**
- [ ] Section appears in correct position
- [ ] Section has correct color theme
- [ ] Download button works
- [ ] Upload button works
- [ ] Progress bar shows
- [ ] Success message displays
- [ ] Error messages display
- [ ] File clears after upload

**Permissions:**
- [ ] Module in ModuleSeeder
- [ ] Permissions created (read + edit)
- [ ] Admin has permissions
- [ ] HR Manager has permissions
- [ ] Permission cache cleared

**Data Integrity:**
- [ ] Valid data imports correctly
- [ ] Invalid data shows errors
- [ ] Duplicates handled per strategy
- [ ] Foreign keys validated
- [ ] Auto-calculations work
- [ ] Enum values validated

---

## ⚠️ Common Mistakes to Avoid

### ❌ DON'T:
- Forget to provide sample data
- Skip validation descriptions
- Miss enum values
- Forget duplicate detection strategy
- Skip testing with real data
- Forget to clear cache after seeding

### ✅ DO:
- Fill out EVERY field in template
- Provide realistic sample data
- Test with edge cases
- Verify permissions work
- Check logs for errors
- Document special business rules

---

## 📞 Support

If you encounter issues:

1. **Check logs:** `storage/logs/laravel.log`
2. **Check queue:** `php artisan queue:work` (must be running)
3. **Check permissions:** Run `php verify_permissions.php`
4. **Check routes:** `php artisan route:list --path=your-module`
5. **Check cache:** Clear with `php artisan cache:clear`

---

## 📚 Related Documents

- **Detailed Guide:** `UPLOAD_MENU_CREATION_GUIDE.md`
- **Request Template:** `NEW_UPLOAD_REQUEST_TEMPLATE.md`
- **Example:** `EMPLOYEE_FUNDING_ALLOCATION_TEMPLATE_FIELDS.md`
- **Permissions:** `PERMISSIONS_SETUP.md`

---

## 🎓 Examples to Reference

Study these existing uploads for patterns:

1. **Simple Upload:** Grant Upload
   - Single table
   - No complex relationships
   - Basic validation

2. **Medium Complexity:** Employee Upload
   - Multiple fields
   - Some auto-detection
   - Foreign key lookups

3. **Complex Upload:** Employee Funding Allocation Upload
   - Many columns
   - Multiple auto-calculations
   - Complex duplicate detection
   - Multiple relationships

**File Locations:**
- Controllers: `app/Http/Controllers/Api/`
- Imports: `app/Imports/`
- Services: `src/services/`
- Components: `src/components/uploads/`

---

**Version:** 1.0  
**Last Updated:** January 8, 2026  
**Purpose:** Quick reference for creating new upload menus

