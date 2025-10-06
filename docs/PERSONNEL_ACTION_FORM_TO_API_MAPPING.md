# Personnel Action Form (SMRU-SF038) → API Field Mapping

## Overview
This document maps the paper form **SMRU-SF038 Personnel Action Form** to the API endpoints for data entry into the database.

---

## Form Structure → Database Fields

### 📋 **Section 1: Current Information**

| Form Field | API Field | Type | Required | Notes |
|------------|-----------|------|----------|-------|
| Name / ชื่อพนักงาน | *(Auto-populated from employment)* | - | - | Retrieved via `employment.employee` relationship |
| Employee No. / แอชพีพนักงาน | `current_employee_no` | string | No | Auto-populated if not provided |
| Date / วันที่ | *(Timestamp)* | - | - | Automatically set on creation |
| Position / ตำแหน่ง | `current_position_id` | integer (FK) | No | Auto-populated from employment |
| Date of Employment / วันจ้างงาน | `current_employment_date` | date | No | Auto-populated from employment |
| Title / หัวข้อ | *(Merged with position)* | - | - | Position relationship includes title |
| Department / แผนก | `current_department_id` | integer (FK) | No | Auto-populated from employment |
| Salary / เงินเดือน THB/เดือน | `current_salary` | decimal(12,2) | No | Auto-populated from employment |
| Effective date / วันที่มีผลผลเริ่มวันที่ | `effective_date` | date | **Yes** | When the action takes effect |

---

### 📝 **Section 2: Action Type / การปฎิบัติการต่างๆ**

#### **Position Change** (please specify below):

| Form Field | API Field | Type | Required | Example Values |
|------------|-----------|------|----------|----------------|
| ☐ Appointment | `action_type` = "appointment" | string | **Yes** | "appointment" |
| ☐ Fiscal Increment | `action_type` = "fiscal_increment" | string | **Yes** | "fiscal_increment" |
| ☐ Title Change | `action_type` = "title_change" | string | **Yes** | "title_change" |
| ☐ Voluntary Separation | `action_type` = "voluntary_separation" | string | **Yes** | "voluntary_separation" |
| ☐ Re-Evaluated Pay Adjustment | `action_type` = "position_change"<br>`action_subtype` = "re_evaluated_pay_adjustment" | string | **Yes** | "position_change" + subtype |
| ☐ Promotion | `action_type` = "position_change"<br>`action_subtype` = "promotion" | string | **Yes** | "position_change" + subtype |
| ☐ Demotion | `action_type` = "position_change"<br>`action_subtype` = "demotion" | string | **Yes** | "position_change" + subtype |
| ☐ End of contract | `action_subtype` = "end_of_contract" | string | No | "end_of_contract" |
| ☐ Work allocation | `action_subtype` = "work_allocation" | string | No | "work_allocation" |

#### **Transfer** (see attach position):

| Form Field | API Field | Type | Required | Example Values |
|------------|-----------|------|----------|----------------|
| Transfer checkbox | `is_transfer` | boolean | No | true/false |
| ☐ Internal Department | `transfer_type` = "internal_department" | string | If transfer | "internal_department" |
| ☐ From site to site | `transfer_type` = "site_to_site" | string | If transfer | "site_to_site" |
| ☐ Attachment Position | `transfer_type` = "attachment_position" | string | If transfer | "attachment_position" |

---

### ✨ **Section 3: New Information**

| Form Field | API Field | Type | Required | Notes |
|------------|-----------|------|----------|-------|
| Position: ... | `new_position_id` | integer (FK) | Conditional* | Foreign key to `positions` table |
| Location: ... | `new_work_location_id` | integer (FK) | No | ✅ **Foreign key to `work_locations` table** |
| Work Schedule: ... | `new_work_schedule` | string | No | Free text (e.g., "Monday-Friday 9AM-5PM") |
| Job title: ... | *(Included in position)* | - | - | Position relationship includes title |
| Department: ... | `new_department_id` | integer (FK) | Conditional* | Foreign key to `departments` table |
| Pay plan: ... | `new_pay_plan` | string | No | Free text |
| Phone Ext: ... | `new_phone_ext` | string | No | Extension number |
| Report to: ... | `new_report_to` | string | No | Name of supervisor |
| Salary: ... | `new_salary` | decimal(12,2) | Conditional* | New salary amount |
| *(Email - not on form but in system)* | `new_email` | string (email) | No | New employee email |

**\*Conditional Requirements:**
- `new_position_id` - Required if `action_type` = "position_change"
- `new_department_id` - Required if `action_type` = "transfer"
- `new_salary` - Required if `action_type` = "fiscal_increment"

---

### 💬 **Section 4: Comments / Details of Change**

| Form Field | API Field | Type | Required | Notes |
|------------|-----------|------|----------|-------|
| Comments / วัตถุประสงค์ | `comments` | text | No | General comments about the action |
| *(Additional details)* | `change_details` | text | No | Detailed description of changes |

---

### ✅ **Approved By: (Signatures)**

| Form Field | API Field | Type | Default | Notes |
|------------|-----------|------|---------|-------|
| Dept. Head / Supervisor<br>หัวหน้าฝ่ายกลาง | `dept_head_approved` | boolean | false | Department head signature approval |
| COO of SMRU<br>ผู้อำนวยการฝ่ายอำนวนนการเทื่อนไทร | `coo_approved` | boolean | false | COO signature approval |
| Human Resources Manager<br>ผู้จัดการฝ่ายบุคคล | `hr_approved` | boolean | false | HR manager signature approval |
| Accountant Manager | `accountant_approved` | boolean | false | Accountant signature approval |

---

## 📤 Complete API Request Example

### Creating a Position Change (Promotion)

```json
POST /api/v1/personnel-actions

{
  "employment_id": 15,
  "effective_date": "2025-11-01",
  
  "action_type": "position_change",
  "action_subtype": "promotion",
  "is_transfer": false,
  
  "new_department_id": 5,
  "new_position_id": 42,
  "new_work_location_id": 3,
  "new_salary": 65000.00,
  "new_work_schedule": "Monday-Friday 9AM-5PM",
  "new_report_to": "John Doe",
  "new_pay_plan": "Plan A",
  "new_phone_ext": "1234",
  "new_email": "employee@smru.ac.th",
  
  "comments": "Annual performance promotion",
  "change_details": "Promoted based on excellent performance review",
  
  "dept_head_approved": true,
  "coo_approved": false,
  "hr_approved": false,
  "accountant_approved": false
}
```

### Creating a Transfer

```json
POST /api/v1/personnel-actions

{
  "employment_id": 20,
  "effective_date": "2025-12-01",
  
  "action_type": "transfer",
  "is_transfer": true,
  "transfer_type": "internal_department",
  
  "new_department_id": 7,
  "new_work_location_id": 2,
  "new_position_id": 28,
  
  "comments": "Transfer to Finance department as requested"
}
```

### Creating a Fiscal Increment

```json
POST /api/v1/personnel-actions

{
  "employment_id": 18,
  "effective_date": "2025-10-01",
  
  "action_type": "fiscal_increment",
  "action_subtype": "re_evaluated_pay_adjustment",
  
  "new_salary": 58000.00,
  
  "comments": "Annual salary adjustment"
}
```

---

## 📥 API Response Example

```json
{
  "success": true,
  "message": "Personnel action created successfully",
  "data": {
    "id": 1,
    "form_number": "SMRU-SF038",
    "reference_number": "PA-2025-000001",
    "employment_id": 15,
    
    "current_employee_no": "EMP-001",
    "current_department_id": 4,
    "current_position_id": 38,
    "current_work_location_id": 1,
    "current_salary": "50000.00",
    "current_employment_date": "2024-01-15",
    
    "effective_date": "2025-11-01",
    "action_type": "position_change",
    "action_subtype": "promotion",
    "is_transfer": false,
    "transfer_type": null,
    
    "new_department_id": 5,
    "new_position_id": 42,
    "new_work_location_id": 3,
    "new_salary": "65000.00",
    "new_work_schedule": "Monday-Friday 9AM-5PM",
    "new_report_to": "John Doe",
    "new_pay_plan": "Plan A",
    "new_phone_ext": "1234",
    "new_email": "employee@smru.ac.th",
    
    "comments": "Annual performance promotion",
    "change_details": "Promoted based on excellent performance",
    
    "dept_head_approved": true,
    "coo_approved": false,
    "hr_approved": false,
    "accountant_approved": false,
    
    "created_at": "2025-10-02T12:00:00.000000Z",
    "updated_at": "2025-10-02T12:00:00.000000Z",
    
    "current_department": {
      "id": 4,
      "name": "IT Department"
    },
    "current_position": {
      "id": 38,
      "title": "Developer"
    },
    "current_work_location": {
      "id": 1,
      "name": "Head Office"
    },
    "new_department": {
      "id": 5,
      "name": "Engineering"
    },
    "new_position": {
      "id": 42,
      "title": "Senior Developer"
    },
    "new_work_location": {
      "id": 3,
      "name": "Branch Office"
    },
    "employment": {
      "id": 15,
      "employee": {
        "id": 10,
        "staff_id": "EMP-001",
        "first_name_en": "John",
        "last_name_en": "Doe"
      }
    }
  }
}
```

---

## 🔗 Getting Reference Data for Dropdowns

### Get Available Departments
```
GET /api/v1/departments
```

### Get Available Positions (filtered by department)
```
GET /api/v1/positions?department_id=5
```

### Get Available Work Locations
```
GET /api/v1/work-locations
```

### Get Valid Constants
```
GET /api/v1/personnel-actions/constants
```

Returns:
```json
{
  "success": true,
  "data": {
    "action_types": {
      "appointment": "Appointment",
      "fiscal_increment": "Fiscal Increment",
      "title_change": "Title Change",
      "voluntary_separation": "Voluntary Separation",
      "position_change": "Position Change",
      "transfer": "Transfer"
    },
    "action_subtypes": {
      "re_evaluated_pay_adjustment": "Re-Evaluated Pay Adjustment",
      "promotion": "Promotion",
      "demotion": "Demotion",
      "end_of_contract": "End of Contract",
      "work_allocation": "Work Allocation"
    },
    "transfer_types": {
      "internal_department": "Internal Department",
      "site_to_site": "From Site to Site",
      "attachment_position": "Attachment Position"
    }
  }
}
```

---

## ✅ Data Entry Workflow

1. **Get Employee Employment Record**
   - Look up employment_id for the employee

2. **Get Reference Data**
   - Load departments, positions, work locations for dropdowns

3. **Create Personnel Action**
   - Fill in Section 2 (Action Type)
   - Fill in Section 3 (New Information)
   - Add Comments (Section 4)
   - Set approval statuses if entering from signed paper form

4. **Auto-Population**
   - Section 1 (Current Information) is automatically filled from employment record

5. **Approval Workflow**
   - Use the `/approve` endpoint to update individual approvals
   - When all 4 approvals = true, employment record is automatically updated

---

## 🔒 Important Validation Rules

1. **Position must belong to Department**
   - If providing both `new_position_id` and `new_department_id`
   - The position MUST belong to that department
   - Otherwise: validation error

2. **Action-Type Specific**
   - Position Change → requires `new_position_id`
   - Transfer → requires `new_department_id`
   - Fiscal Increment → requires `new_salary`

3. **Transfer Flag**
   - If `is_transfer` = true → `transfer_type` is required

4. **Effective Date**
   - Must be today or future date

---

**Form Reference:** SMRU-SF038 Personnel Action Form  
**API Version:** 2.0 (Foreign Key Based)  
**Last Updated:** October 2, 2025

