# New Upload Menu Request Template

> **Instructions:** Fill out this template completely before requesting a new upload menu implementation. This ensures all necessary information is provided upfront, preventing errors and back-and-forth.

---

## 📝 Request Information

**Requested By:** [Your Name]  
**Date:** [YYYY-MM-DD]  
**Priority:** [ ] High [ ] Medium [ ] Low  
**Target Completion:** [YYYY-MM-DD]

---

## 1️⃣ Module Information

### Basic Details
```yaml
Module Name: _________________
  # Example: "employee_salaries", "leave_applications", "training_records"
  # Use snake_case, plural form

Display Name: _________________
  # Example: "Employee Salaries", "Leave Applications", "Training Records"
  # User-friendly name shown in UI

Description: _________________
  # Example: "Manage employee salary records and adjustments"
  # Brief description of the module's purpose

Category: _________________
  # Choose from: Employee, Payroll, Leave, Attendance, Training, Travel, 
  # Grants, Recruitment, Organization, Administration, Reports

Icon: _________________
  # Tabler icon name without 'ti-' prefix
  # Examples: wallet, calendar, award, users, briefcase, chart-pie
  # Browse: https://tabler-icons.io/

Route: _________________
  # Example: "/employee/salaries", "/leave/applications"
  # Path where the list page will be (if exists)

Order: _________________
  # Number indicating position in category (1-100)
  # Check existing modules in same category for reference
```

---

## 2️⃣ Database Information

### Table Details
```yaml
Table Name: _________________
  # Example: "employee_salaries", "leave_applications"
  # Use snake_case, plural form

Model Name: _________________
  # Example: "EmployeeSalary", "LeaveApplication"
  # Use PascalCase, singular form

Migration File: _________________
  # Example: "2025_01_08_create_employee_salaries_table.php"
  # Should already exist

Migration Status: [ ] Exists [ ] Needs to be created
```

### Relationships
```yaml
Foreign Keys:
  - field: _________________
    references: _________________ (table.column)
    description: _________________
  
  - field: _________________
    references: _________________ (table.column)
    description: _________________
  
  # Add more as needed
```

---

## 3️⃣ Template Column Definitions

**Instructions:** List ALL columns that should appear in the Excel template. For each column, provide complete information.

### Column 1
```yaml
Column Name: _________________
  # Example: "staff_id", "effective_date", "base_salary"

Display Order: _________________
  # Position in template (1, 2, 3, ...)

Data Type: _________________
  # Choose: string, integer, decimal, date, boolean, enum

Required: [ ] Yes [ ] No

Auto-Calculate: [ ] Yes [ ] No
Auto-Calculate Logic: _________________
  # If yes, explain calculation. Example: "total = base + allowances"

Auto-Detect: [ ] Yes [ ] No
Auto-Detect Logic: _________________
  # If yes, explain logic. Example: "lookup employee_id from staff_id"

Default Value: _________________
  # If applicable. Example: "active", "0", empty

Validation Rules: _________________
  # Example: "required|string|max:50", "nullable|numeric|min:0"
  # Laravel validation rules

Validation Description: _________________
  # User-friendly description for template
  # Example: "String - NOT NULL - Employee staff ID (must exist in system)"

Enum Values (if applicable): _________________
  # If type is enum, list all possible values
  # Example: "pending, approved, rejected"

Sample Values: _________________
  # 2-3 example values for template sample data
  # Example: "EMP001, EMP002, EMP003"

Database Column: _________________
  # Actual column name in database (if different from template column)

Special Notes: _________________
  # Any special handling, formatting, or considerations
```

### Column 2
```yaml
Column Name: _________________
Display Order: _________________
Data Type: _________________
Required: [ ] Yes [ ] No
Auto-Calculate: [ ] Yes [ ] No
Auto-Calculate Logic: _________________
Auto-Detect: [ ] Yes [ ] No
Auto-Detect Logic: _________________
Default Value: _________________
Validation Rules: _________________
Validation Description: _________________
Enum Values: _________________
Sample Values: _________________
Database Column: _________________
Special Notes: _________________
```

### Column 3
```yaml
Column Name: _________________
Display Order: _________________
Data Type: _________________
Required: [ ] Yes [ ] No
Auto-Calculate: [ ] Yes [ ] No
Auto-Calculate Logic: _________________
Auto-Detect: [ ] Yes [ ] No
Auto-Detect Logic: _________________
Default Value: _________________
Validation Rules: _________________
Validation Description: _________________
Enum Values: _________________
Sample Values: _________________
Database Column: _________________
Special Notes: _________________
```

**[COPY ADDITIONAL COLUMNS AS NEEDED]**

---

## 4️⃣ Import Behavior

### Duplicate Detection Strategy
```yaml
Duplicate Detection: [ ] Yes [ ] No

Match Fields: _________________
  # Comma-separated list of fields used to identify duplicates
  # Example: "employee_id, effective_date"

On Duplicate: _________________
  # Choose: update, skip, error
  # update = Update existing record
  # skip = Skip and continue with next record
  # error = Stop import and show error

Duplicate Logic Explanation: _________________
  # Explain when a record is considered a duplicate
  # Example: "Same employee_id and effective_date in same month"
```

### Data Transformations
```yaml
Date Format Handling: [ ] Yes [ ] No
  # Does import need to handle multiple date formats?

Numeric Format Handling: [ ] Yes [ ] No
  # Does import need to handle commas in numbers? (e.g., "1,000.50")

Text Cleaning: [ ] Yes [ ] No
  # Does import need to trim/clean text fields?

Special Transformations: _________________
  # Describe any special data transformations needed
  # Example: "Convert percentages from 0-100 to 0.00-1.00"
```

### Performance Considerations
```yaml
Expected Record Volume: _________________
  # Example: "100-500 per upload", "1000+ per upload"

Chunk Size: _________________
  # Default is 50. Increase for simple data, decrease for complex
  # Example: 50, 100, 200

Queue Processing: [ ] Yes [ ] No (Default: Yes)
  # Should import run in background queue?
```

---

## 5️⃣ File Naming Conventions

### Backend Files
```yaml
Controller Name: _________________
  # Example: "EmployeeSalaryController"
  # PascalCase, singular or plural depending on resource

Import Class Name: _________________
  # Example: "EmployeeSalariesImport"
  # PascalCase, usually plural

Controller File Path: _________________
  # Example: "app/Http/Controllers/Api/EmployeeSalaryController.php"

Import File Path: _________________
  # Example: "app/Imports/EmployeeSalariesImport.php"
```

### Frontend Files
```yaml
Service Name: _________________
  # Example: "upload-salary.service.js"
  # kebab-case

Component Name: _________________
  # Example: "salary-upload.vue"
  # kebab-case

Service File Path: _________________
  # Example: "src/services/upload-salary.service.js"

Component File Path: _________________
  # Example: "src/components/uploads/salary-upload.vue"
```

### Route Names
```yaml
Upload Route Name: _________________
  # Example: "uploads.employee-salary"
  # Format: uploads.{module-name}

Template Route Name: _________________
  # Example: "downloads.employee-salary-template"
  # Format: downloads.{module-name}-template

Upload Endpoint: _________________
  # Example: "/uploads/employee-salary"
  # Format: /uploads/{module-name}

Template Endpoint: _________________
  # Example: "/downloads/employee-salary-template"
  # Format: /downloads/{module-name}-template
```

---

## 6️⃣ UI Integration

### Upload List Page Integration
```yaml
Section Name: _________________
  # Example: "Employee Salary Uploads", "Leave Application Uploads"

Section Icon: _________________
  # Same as module icon
  # Tabler icon name without 'ti-' prefix

Section Description: _________________
  # Brief description shown in upload row
  # Example: "Upload Excel file with employee salary records (bulk import)"

Position in List: _________________
  # Where should this section appear?
  # Example: "Below Employment, Above Payroll"
  # Or: "After Grant, Before Employee"

Color Theme: _________________
  # Hex color code for section header
  # Examples:
  # - #2196F3 (Blue) - Employee-related
  # - #4CAF50 (Green) - Grant-related
  # - #FF9800 (Orange) - Payroll-related
  # - #9C27B0 (Purple) - Leave-related
  # - #F44336 (Red) - Termination/Resignation
  # - #00BCD4 (Cyan) - Allocation-related

nth-child Value: _________________
  # CSS nth-child selector number for styling
  # Count from top: 1, 2, 3, 4...
  # Will be calculated based on position
```

---

## 7️⃣ Permission Configuration

### Permission Details
```yaml
Permission Prefix: _________________
  # Same as module name
  # Example: "employee_salaries", "leave_applications"

Read Permission: _________________
  # Format: {prefix}.read
  # Example: "employee_salaries.read"

Edit Permission: _________________
  # Format: {prefix}.edit
  # Example: "employee_salaries.edit"

Additional Permissions (if any): _________________
  # List any other permissions needed
  # Example: "employee_salaries.approve", "employee_salaries.delete"
```

### Default Role Assignment
```yaml
Roles with Read Access: _________________
  # Example: "Admin, HR Manager, Department Manager"

Roles with Edit Access: _________________
  # Example: "Admin, HR Manager"
```

---

## 8️⃣ Validation & Error Handling

### Validation Requirements
```yaml
Client-Side Validation Needed: [ ] Yes [ ] No

Client-Side Checks: _________________
  # Example: "File type (xlsx, xls)", "File size (max 10MB)"

Server-Side Validation: _________________
  # List critical validations
  # Example:
  # - Employee must exist in system
  # - Effective date cannot be in future
  # - Base salary must be positive

Custom Error Messages: _________________
  # Any specific error messages that should be shown
  # Example: "Effective date cannot be earlier than employee hire date"
```

### Error Handling Strategy
```yaml
On Validation Error: _________________
  # Choose: Skip row and continue, Stop entire import

Error Notification: [ ] Email [ ] In-App [ ] Both

Error Report Format: _________________
  # Example: "List all errors with row numbers and values"
```

---

## 9️⃣ Business Logic & Rules

### Import Rules
```yaml
Business Rule 1: _________________
  # Example: "Cannot import salary less than minimum wage"

Business Rule 2: _________________
  # Example: "Cannot have overlapping salary periods for same employee"

Business Rule 3: _________________
  # Add more as needed

Pre-Import Checks: _________________
  # Any validations before import starts
  # Example: "Check if payroll period is not yet closed"

Post-Import Actions: _________________
  # Any actions after successful import
  # Example: "Recalculate payroll totals", "Send notification to Finance"
```

---

## 🔟 Sample Data

### Sample Row 1
```
Provide a complete sample row with actual values:
Column1: value1 | Column2: value2 | Column3: value3 | ...
```

### Sample Row 2
```
Provide a complete sample row with actual values:
Column1: value1 | Column2: value2 | Column3: value3 | ...
```

### Sample Row 3
```
Provide a complete sample row with actual values:
Column1: value1 | Column2: value2 | Column3: value3 | ...
```

---

## 1️⃣1️⃣ Additional Requirements

### Special Features
```yaml
Feature 1: _________________
  # Any special requirements not covered above
  # Example: "Support bulk delete via upload"

Feature 2: _________________
  
Feature 3: _________________
```

### Dependencies
```yaml
Dependent Modules: _________________
  # Other modules that must exist first
  # Example: "Requires Employee module, Employment module"

External Systems: _________________
  # Any external system integrations
  # Example: "May sync with external payroll system"
```

### Documentation Needs
```yaml
User Guide Needed: [ ] Yes [ ] No

API Documentation Needed: [ ] Yes [ ] No

Training Required: [ ] Yes [ ] No

Additional Documentation: _________________
```

---

## 1️⃣2️⃣ Testing Requirements

### Test Scenarios
```yaml
Test Case 1: _________________
  # Example: "Upload file with all valid data"

Test Case 2: _________________
  # Example: "Upload file with some invalid rows"

Test Case 3: _________________
  # Example: "Upload file with duplicate records"

Test Case 4: _________________
  # Example: "Upload very large file (1000+ rows)"

Additional Test Cases: _________________
```

### Acceptance Criteria
```yaml
Success Criteria 1: _________________
  # Example: "Valid records are imported successfully"

Success Criteria 2: _________________
  # Example: "Invalid records are skipped with clear error messages"

Success Criteria 3: _________________
  # Example: "User receives notification upon completion"
```

---

## 1️⃣3️⃣ Timeline & Priority

### Timeline
```yaml
Implementation Start: _________________
  # Date: YYYY-MM-DD

Expected Completion: _________________
  # Date: YYYY-MM-DD

Testing Period: _________________
  # Duration: X days

Deployment Date: _________________
  # Date: YYYY-MM-DD
```

### Dependencies & Blockers
```yaml
Dependent on: _________________
  # Other tasks that must be completed first

Potential Blockers: _________________
  # Any known issues or risks
```

---

## ✅ Checklist Before Submission

**Information Completeness:**
- [ ] All module information provided
- [ ] All database information provided
- [ ] ALL template columns documented (with complete details)
- [ ] Import behavior clearly defined
- [ ] File naming conventions specified
- [ ] UI integration details provided
- [ ] Permission configuration specified
- [ ] Sample data provided (at least 3 rows)
- [ ] Testing requirements listed

**Verification:**
- [ ] Database table already exists (or migration ready)
- [ ] Model has correct fillable fields
- [ ] Relationships are correct in model
- [ ] No conflicts with existing modules
- [ ] Icon exists in Tabler Icons
- [ ] All enum values are defined
- [ ] Validation rules are realistic

**Approval:**
- [ ] Reviewed by: _________________
- [ ] Approved by: _________________
- [ ] Date: _________________

---

## 📌 Notes

Add any additional notes, considerations, or special instructions here:

```
[Your notes here]
```

---

## 📎 Attachments

List any supporting documents:

- [ ] Sample Excel file
- [ ] Database schema diagram
- [ ] Mockups/screenshots
- [ ] Business requirements document
- [ ] Other: _________________

---

**Template Version:** 1.0  
**Last Updated:** January 8, 2026  
**Instructions:** Save this filled template as `NEW_UPLOAD_[MODULE_NAME]_REQUEST.md`

