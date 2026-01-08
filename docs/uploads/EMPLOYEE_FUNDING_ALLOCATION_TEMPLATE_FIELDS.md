# Employee Funding Allocation Template - Field Documentation

> **Last Updated:** January 8, 2026  
> **Author:** System Documentation  
> **Related Files:**
> - `app/Http/Controllers/Api/EmployeeFundingAllocationController.php` - Template generation
> - `app/Imports/EmployeeFundingAllocationsImport.php` - Import logic
> - Database: `employee_funding_allocations` table

---

## Overview

The Employee Funding Allocation template allows bulk import of employee funding allocations from Excel files. This document describes all available columns, their purposes, validation rules, and how they map to the database.

---

## Template Columns

### 1. `staff_id` ⭐ REQUIRED
- **Type:** String
- **Database Column:** `employee_id` (via lookup)
- **Validation:** Must exist in the `employees` table
- **Description:** The unique staff ID of the employee (e.g., "EMP001", "STF-12345")
- **Example:** `EMP001`

### 2. `employment_id` (Optional)
- **Type:** Integer
- **Database Column:** `employment_id`
- **Validation:** 
  - If provided, must exist in `employments` table
  - Must belong to the employee specified by `staff_id`
- **Auto-Detection:** If empty, the system will automatically link to the employee's active employment
- **Description:** The specific employment record ID to link this allocation to
- **Example:** `15` (empty = auto-detect)

### 3. `grant_item_id` ⭐ REQUIRED
- **Type:** Integer
- **Database Column:** `grant_item_id`
- **Validation:** Must exist in the `grant_items` table
- **Description:** The grant item ID that funds this allocation
- **Example:** `1`, `2`, `3`

### 4. `fte` ⭐ REQUIRED
- **Type:** Decimal (0-100)
- **Database Column:** `fte` (stored as decimal 0.00-1.00)
- **Validation:** Must be between 0 and 100
- **Conversion:** The import automatically converts percentages to decimals (e.g., 50% → 0.50)
- **Description:** Full-Time Equivalent percentage representing the funding allocation
- **Examples:**
  - `100` = 100% FTE (1.00 in database)
  - `50` = 50% FTE (0.50 in database)
  - `25.5` = 25.5% FTE (0.255 in database)

### 5. `allocation_type` (Optional)
- **Type:** String (Enum)
- **Database Column:** `allocation_type`
- **Validation:** Must be one of: `grant`, `org_funded`
- **Default:** `grant`
- **Description:** The type of funding source
- **Options:**
  - `grant` = Funded by a grant/project
  - `org_funded` = Funded by the organization

### 6. `allocated_amount` (Optional)
- **Type:** Decimal(15,2)
- **Database Column:** `allocated_amount`
- **Validation:** Must be >= 0
- **Auto-Calculation:** If empty, automatically calculated as: `(Employee Salary × FTE)`
- **Description:** The actual monetary amount allocated to this employee for this grant item
- **Example:** `30000.00`, `50000.50` (empty = auto-calculate)

### 7. `salary_type` (Optional)
- **Type:** String (Enum)
- **Database Column:** `salary_type`
- **Validation:** Must be one of: `probation_salary`, `pass_probation_salary`
- **Auto-Detection:** If empty, system detects based on employment probation status
- **Description:** Which salary was used for allocation calculation
- **Options:**
  - `probation_salary` = Used probationary salary for calculation
  - `pass_probation_salary` = Used post-probation salary for calculation

### 8. `status` (Optional)
- **Type:** String (Enum)
- **Database Column:** `status`
- **Validation:** Must be one of: `active`, `historical`, `terminated`
- **Default:** `active`
- **Description:** The lifecycle status of this allocation
- **Options:**
  - `active` = Currently active allocation
  - `historical` = Past allocation (archived)
  - `terminated` = Terminated allocation

### 9. `start_date` ⭐ REQUIRED
- **Type:** Date (YYYY-MM-DD)
- **Database Column:** `start_date`
- **Validation:** Must be a valid date
- **Description:** The date when this funding allocation begins
- **Example:** `2025-01-01`, `2025-06-15`

### 10. `end_date` (Optional)
- **Type:** Date (YYYY-MM-DD)
- **Database Column:** `end_date`
- **Validation:** Must be a valid date (if provided)
- **Description:** The date when this funding allocation ends (leave empty for ongoing allocations)
- **Example:** `2025-12-31` (empty = no end date)

---

## Sample Data Examples

### Example 1: Minimal Required Fields Only
```
staff_id | employment_id | grant_item_id | fte | allocation_type | allocated_amount | salary_type | status | start_date | end_date
EMP001   |               | 1             | 100 |                 |                  |             |        | 2025-01-01 |
```
**Result:** System auto-detects active employment, defaults to `grant` type, auto-calculates amount, detects salary type, sets status to `active`.

### Example 2: Full Fields Specified
```
staff_id | employment_id | grant_item_id | fte | allocation_type | allocated_amount | salary_type          | status | start_date | end_date
EMP002   | 15            | 2             | 60  | grant           | 30000.00         | probation_salary     | active | 2025-01-15 | 2025-12-31
```
**Result:** Uses employment ID 15, 60% FTE on grant #2, explicit amount of 30,000, probation salary, active, with end date.

### Example 3: Multiple Allocations for Same Employee
```
staff_id | employment_id | grant_item_id | fte | allocation_type | allocated_amount | salary_type              | status | start_date | end_date
EMP002   | 15            | 2             | 60  | grant           | 30000.00         | probation_salary         | active | 2025-01-15 | 2025-12-31
EMP002   | 15            | 3             | 40  | org_funded      | 20000.00         | pass_probation_salary    | active | 2025-01-15 | 2025-12-31
```
**Result:** Employee EMP002 has two funding sources totaling 100% FTE (60% from grant #2, 40% from org-funded grant #3).

---

## Import Behavior

### Duplicate Detection
The import checks for existing allocations matching:
- `employee_id`
- `employment_id`
- `grant_item_id`
- `allocation_type`

**If found:** Updates the existing record  
**If not found:** Creates a new record

### Auto-Calculations

1. **Employment Linking:** If `employment_id` is empty, the system finds the employee's active employment
2. **Allocated Amount:** If `allocated_amount` is empty, calculated as:
   ```
   allocated_amount = (employment salary) × (fte as decimal)
   ```
3. **Salary Type:** If `salary_type` is empty, determined by checking if employee is on probation
4. **Defaults:**
   - `allocation_type` → `grant`
   - `status` → `active`

### Error Handling

The import will skip rows and log errors for:
- Non-existent `staff_id`
- Non-existent or mismatched `employment_id`
- Invalid `grant_item_id`
- Invalid FTE (outside 0-100 range)
- Invalid enum values for `allocation_type`, `salary_type`, or `status`
- Missing or invalid `start_date`

---

## Database Schema Reference

```sql
CREATE TABLE employee_funding_allocations (
    id BIGINT PRIMARY KEY,
    employee_id BIGINT FOREIGN KEY -> employees(id),
    employment_id BIGINT NULLABLE FOREIGN KEY -> employments(id),
    grant_item_id BIGINT NULLABLE FOREIGN KEY -> grant_items(id),
    fte DECIMAL(4,2) COMMENT 'FTE as decimal (0.00-1.00)',
    allocation_type VARCHAR(20) COMMENT 'grant, org_funded',
    allocated_amount DECIMAL(15,2) NULLABLE,
    salary_type VARCHAR(50) NULLABLE COMMENT 'probation_salary, pass_probation_salary',
    status VARCHAR(20) DEFAULT 'active' COMMENT 'active, historical, terminated',
    start_date DATE NULLABLE,
    end_date DATE NULLABLE,
    created_by VARCHAR(100) NULLABLE,
    updated_by VARCHAR(100) NULLABLE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

---

## Usage Instructions

### Step 1: Download Template
1. Navigate to **Administration → File Uploads**
2. Find **Employee Funding Allocations** section
3. Click **Download Template**

### Step 2: Fill Template
1. Open the downloaded Excel file
2. Read the validation rules in Row 2
3. Fill in your data starting from Row 3
4. Ensure `staff_id`, `grant_item_id`, `fte`, and `start_date` are always provided
5. Optional fields can be left empty for auto-detection/defaults

### Step 3: Upload
1. Save your Excel file
2. Click **Choose File** in the Employee Funding Allocations section
3. Select your filled template
4. Click **Upload**
5. Wait for the notification confirming completion

### Step 4: Review Results
The system will notify you with:
- Number of records created
- Number of records updated
- Number of errors (if any)

---

## Common Scenarios

### Scenario 1: New Employee Starting on a Grant
```
staff_id: EMP123
employment_id: (empty - auto-detect)
grant_item_id: 5
fte: 100
allocation_type: grant
allocated_amount: (empty - auto-calculate)
salary_type: (empty - auto-detect)
status: active
start_date: 2025-02-01
end_date: (empty - ongoing)
```

### Scenario 2: Employee Split Between Two Grants
**Row 1:**
```
staff_id: EMP456
grant_item_id: 10
fte: 70
allocation_type: grant
start_date: 2025-03-01
```

**Row 2:**
```
staff_id: EMP456
grant_item_id: 11
fte: 30
allocation_type: grant
start_date: 2025-03-01
```

### Scenario 3: Updating Existing Allocation
If an allocation already exists for EMP001 on grant_item_id 1, uploading:
```
staff_id: EMP001
grant_item_id: 1
fte: 80
start_date: 2025-04-01
```
Will **update** the existing allocation instead of creating a duplicate.

---

## Troubleshooting

### Error: "Employee with staff_id 'XXX' not found"
**Solution:** Ensure the employee exists in the system before uploading allocations.

### Error: "No active employment found for staff_id 'XXX'"
**Solution:** Either create an employment record first, or provide a valid `employment_id` in the template.

### Error: "Invalid grant_item_id 'XXX'"
**Solution:** Verify the grant item exists in the system. Check the Grant Items list.

### Error: "Invalid FTE value (must be between 0-100)"
**Solution:** Ensure FTE is a number between 0 and 100 (e.g., 50 for 50%, not 0.5).

---

## Related Documentation

- [Employee Funding Allocation Upload Implementation](./EMPLOYEE_FUNDING_ALLOCATION_UPLOAD_IMPLEMENTATION.md)
- [Permissions Setup](./PERMISSIONS_SETUP.md)
- Database Migration: `2025_04_07_090015_create_employee_funding_allocations_table.php`
- Model: `app/Models/EmployeeFundingAllocation.php`

---

## API Endpoints

- **Download Template:** `GET /api/v1/downloads/employee-funding-allocation-template`
- **Upload Data:** `POST /api/v1/uploads/employee-funding-allocation`

**Permission Required:** `employee_funding_allocations.read` (download), `employee_funding_allocations.edit` (upload)

