# Employee Template Download - Implementation Plan

## 📋 Current Status Analysis

### ✅ What Already Exists

**Backend:**
- ✅ `EmployeesImport.php` - Handles Excel import with queue processing
- ✅ `EmployeeController::uploadEmployeeData()` - Upload endpoint
- ✅ Validation rules defined in import class
- ✅ Async processing with notifications

**Frontend:**
- ✅ `upload-employee.service.js` - Has `downloadTemplate()` method
- ✅ `employee-upload.vue` component
- ✅ API endpoint configured: `/uploads/employee/template`
- ✅ File upload UI with progress tracking

### ❌ What's Missing

**Backend:**
- ❌ Template download endpoint in EmployeeController
- ❌ Route for `/uploads/employee/template`

---

## 📊 Employee Template Structure

Based on `EmployeesImport.php` analysis, the template should include:

### Required Fields
1. **staff_id** - String (50) - NOT NULL - Unique
2. **first_name** - String (255) - NOT NULL
3. **gender** - String - NOT NULL - Values: M, F
4. **date_of_birth** - Date - NOT NULL - Format: YYYY-MM-DD

### Optional Fields

**Basic Information:**
- org - String (10) - Organization
- initial - String (10) - Initial (EN)
- last_name - String (255) - Last name (EN)
- initial_th - String (20) - Initial (TH)
- first_name_th - String (255) - First name (TH)
- last_name_th - String (255) - Last name (TH)
- status - String (20) - Employee status
- nationality - String (100)
- religion - String (100)

**Identification:**
- id_type - String (100) - Values: 10YearsID, BurmeseID, CI, Borderpass, ThaiID, Passport, Other
- id_no - String - ID number
- social_security_no - String (50)
- tax_no - String (50)
- driver_license - String (100)

**Banking:**
- bank_name - String (100)
- bank_branch - String (100)
- bank_acc_name - String (100)
- bank_acc_no - String (50)

**Contact:**
- mobile_no - String (50)
- current_address - Text
- permanent_address - Text

**Family:**
- marital_status - String (50)
- spouse_name - String (200)
- spouse_mobile_no - String (50)
- father_name - String (200)
- father_occupation - String (200)
- father_mobile_no - String (50)
- mother_name - String (200)
- mother_occupation - String (200)
- mother_mobile_no - String (50)

**Emergency Contact:**
- emergency_name - String (100)
- relationship - String (100)
- emergency_mobile_no - String (50)

**Beneficiaries:**
- kin1_name - String (255)
- kin1_relationship - String (255)
- kin1_mobile - String (50)
- kin2_name - String (255)
- kin2_relationship - String (255)
- kin2_mobile - String (50)

**Other:**
- military_status - String (50)
- remark - String (255)

---

## 🛠️ Implementation Tasks

### Task 1: Create Backend Template Download Endpoint

**File:** `app/Http/Controllers/Api/EmployeeController.php`

**Method:** `downloadTemplate()`

**Features:**
- Generate Excel with all employee fields
- Include validation rules in Row 2
- Add dropdown data validation for:
  - gender (M, F)
  - id_type (10YearsID, BurmeseID, CI, Borderpass, ThaiID, Passport, Other)
  - marital_status (Single, Married, Divorced, Widowed)
- Date format validation for date_of_birth
- Include 2-3 sample rows with realistic data
- Instructions sheet with field descriptions

### Task 2: Add Route

**File:** `routes/api/employees.php`

**Route:** `GET /uploads/employee/template`

**Permission:** `employees.read`

### Task 3: Update Frontend Service (Already Done)

**File:** `src/services/upload-employee.service.js`

**Status:** ✅ Already has `downloadTemplate()` method

**Fix needed:** Update blob handling (same as grant fix)

### Task 4: Update employee-upload.vue

**File:** `src/components/uploads/employee-upload.vue`

**Update:** Implement `downloadTemplate()` method to call service

---

## 📝 Template Layout

### Sheet 1: Employee Data

```
Row 1: Column Headers
Row 2: Validation Rules (for reference)
Row 3: Sample Employee 1
Row 4: Sample Employee 2
Row 5+: User data entry
```

### Sheet 2: Instructions

- File structure explanation
- Field descriptions
- Required vs optional fields
- Data format examples
- Common errors and solutions
- Import process steps

### Sheet 3: Reference Data

- Valid values for dropdowns
- ID type options
- Marital status options
- Gender options

---

## 🎨 Excel Features

1. **Data Validation:**
   - Gender dropdown (M, F)
   - ID Type dropdown (7 options)
   - Marital Status dropdown (4 options)
   - Date picker for date_of_birth

2. **Conditional Formatting:**
   - Required fields highlighted (light yellow)
   - Invalid data highlighted (light red)

3. **Column Widths:**
   - Optimized for readability
   - Text wrapping for long fields

4. **Protection:**
   - Lock validation rules row
   - Allow data entry from Row 3 onwards

---

## ⚠️ Important Considerations

1. **Async Processing:**
   - Employee import is queued (unlike grant which is synchronous)
   - Template should mention this
   - Users get notification when complete

2. **Duplicate Handling:**
   - staff_id must be unique
   - Duplicates in file are rejected
   - Duplicates in DB are rejected

3. **Related Tables:**
   - employee_identifications (auto-created from id_type/id_no)
   - employee_beneficiaries (auto-created from kin1/kin2)

4. **Sample Data:**
   - Use realistic Thai names
   - Use valid Thai phone numbers
   - Use realistic dates

---

## 🧪 Testing Checklist

- [ ] Template downloads successfully
- [ ] All columns present
- [ ] Validation rules visible
- [ ] Dropdowns work
- [ ] Date picker works
- [ ] Sample data is valid
- [ ] Instructions clear
- [ ] Upload works with template
- [ ] Validation errors shown
- [ ] Success notification received

---

## 📚 Reference Documents

- `EmployeesImport.php` - Import logic and validation
- `GRANT_TEMPLATE_DOWNLOAD_IMPLEMENTATION.md` - Similar implementation
- `EMPLOYEE_API_DOCUMENTATION.md` - API documentation

---

## 🚀 Implementation Order

1. ✅ Analysis complete
2. ⏳ Create backend endpoint
3. ⏳ Add route
4. ⏳ Fix frontend service blob handling
5. ⏳ Update employee-upload.vue
6. ⏳ Test end-to-end
7. ⏳ Create documentation

---

**Status:** Ready to implement  
**Estimated Time:** 2-3 hours  
**Complexity:** Medium (following grant pattern)

