# Personnel Actions API - Complete Documentation

**Form:** SMRU-SF038 Personnel Action Form  
**Status:** ✅ Production Ready  
**Implementation Date:** October 2, 2025  
**Version:** 2.0 (Foreign Key Architecture)

---

## Table of Contents
1. [Overview](#overview)
2. [Database Schema](#database-schema)
3. [SMRU-SF038 Form Mapping](#form-mapping)
4. [API Endpoints](#api-endpoints)
5. [Model & Relationships](#model--relationships)
6. [Service Layer](#service-layer)
7. [Validation Rules](#validation-rules)
8. [Approval Workflow](#approval-workflow)
9. [Code Examples](#code-examples)
10. [Related Documentation](#related-documentation)

---

## Overview

The Personnel Actions API digitizes the **SMRU-SF038 Personnel Action Form** into a structured database system. This is a **data entry and display system** designed to:

✅ Capture all paper form data electronically  
✅ Manage 4-level approval workflow  
✅ Auto-update employment records on approval  
✅ Maintain complete audit trail  
✅ Support all action types (appointments, transfers, promotions, separations, etc.)

### Key Features

- **100% Form Coverage** - All 36+ fields from paper form supported
- **Foreign Key Architecture** - Proper relationships with departments, positions, locations
- **Auto-Population** - Current employment data fills automatically
- **Smart Validation** - Position must belong to department
- **Approval Workflow** - 4-level boolean approvals with automatic implementation
- **SQL Server Compatible** - No cascade cycles
- **Employment Integration** - Updates employment records on full approval
- **Cache Management** - Automatic invalidation on changes

---

## Database Schema

### Migration: `2025_09_25_134034_create_personnel_actions_table.php`

```php
Schema::create('personnel_actions', function (Blueprint $table) {
    $table->id();
    $table->string('form_number')->default('SMRU-SF038');
    $table->string('reference_number')->unique(); // PA-YYYY-000001
    
    // Employment Reference
    $table->unsignedBigInteger('employment_id');
    
    // Section 1: Current Information (Auto-populated)
    $table->string('current_employee_no')->nullable();
    $table->unsignedBigInteger('current_department_id')->nullable();
    $table->unsignedBigInteger('current_position_id')->nullable();
    $table->decimal('current_salary', 12, 2)->nullable();
    $table->unsignedBigInteger('current_work_location_id')->nullable();
    $table->date('current_employment_date')->nullable();
    $table->date('effective_date');
    
    // Section 2: Action Type
    $table->string('action_type'); // appointment, fiscal_increment, etc.
    $table->string('action_subtype')->nullable();
    $table->boolean('is_transfer')->default(false);
    $table->string('transfer_type')->nullable();
    
    // Section 3: New Information (Foreign Keys)
    $table->unsignedBigInteger('new_department_id')->nullable();
    $table->unsignedBigInteger('new_position_id')->nullable();
    $table->unsignedBigInteger('new_work_location_id')->nullable();
    $table->decimal('new_salary', 12, 2)->nullable();
    $table->string('new_work_schedule')->nullable();
    $table->string('new_report_to')->nullable();
    $table->string('new_pay_plan')->nullable();
    $table->string('new_phone_ext')->nullable();
    $table->string('new_email')->nullable();
    
    // Section 4: Comments
    $table->text('comments')->nullable();
    $table->text('change_details')->nullable();
    
    // 4-Level Approvals
    $table->boolean('dept_head_approved')->default(false);
    $table->boolean('coo_approved')->default(false);
    $table->boolean('hr_approved')->default(false);
    $table->boolean('accountant_approved')->default(false);
    
    // Audit Fields
    $table->unsignedBigInteger('created_by');
    $table->unsignedBigInteger('updated_by')->nullable();
    $table->timestamps();
    $table->softDeletes();
    
    // Foreign Keys (NO ACTION for SQL Server compatibility)
    $table->foreign('employment_id')->references('id')->on('employments');
    $table->foreign('current_department_id')->references('id')->on('departments');
    $table->foreign('current_position_id')->references('id')->on('positions');
    $table->foreign('current_work_location_id')->references('id')->on('work_locations');
    $table->foreign('new_department_id')->references('id')->on('departments');
    $table->foreign('new_position_id')->references('id')->on('positions');
    $table->foreign('new_work_location_id')->references('id')->on('work_locations');
    $table->foreign('created_by')->references('id')->on('users');
    $table->foreign('updated_by')->references('id')->on('users');
    
    // Indexes
    $table->index(['employment_id', 'effective_date']);
    $table->index(['dept_head_approved', 'coo_approved', 'hr_approved', 'accountant_approved']);
    $table->index(['current_department_id', 'current_position_id']);
    $table->index(['new_department_id', 'new_position_id']);
});
```

---

## SMRU-SF038 Form Mapping

### Section 1: Current Information

| Paper Form Field | Database Column | Auto-Populated |
|-----------------|-----------------|----------------|
| Name / ชื่อพนักงาน | Via `employment.employee` | ✅ |
| Employee No. | `current_employee_no` | ✅ |
| Position / ตำแหน่ง | `current_position_id` (FK) | ✅ |
| Title / หัวข้อ | Via position relationship | ✅ |
| Department / แผนก | `current_department_id` (FK) | ✅ |
| Salary / เงินเดือน | `current_salary` | ✅ |
| Date of Employment | `current_employment_date` | ✅ |
| Effective date | `effective_date` | ❌ (Required input) |

### Section 2: Action Type

| Form Checkbox | API Values |
|--------------|------------|
| ☐ Appointment | `action_type: "appointment"` |
| ☐ Fiscal Increment | `action_type: "fiscal_increment"` |
| ☐ Title Change | `action_type: "title_change"` |
| ☐ Voluntary Separation | `action_type: "voluntary_separation"` |
| ☐ Re-Evaluated Pay | `action_type: "position_change"`, `action_subtype: "re_evaluated_pay_adjustment"` |
| ☐ Promotion | `action_type: "position_change"`, `action_subtype: "promotion"` |
| ☐ Demotion | `action_type: "position_change"`, `action_subtype: "demotion"` |
| ☐ End of contract | `action_subtype: "end_of_contract"` |
| ☐ Work allocation | `action_subtype: "work_allocation"` |
| ☐ Internal Department | `is_transfer: true`, `transfer_type: "internal_department"` |
| ☐ From site to site | `is_transfer: true`, `transfer_type: "site_to_site"` |

### Section 3: New Information

| Form Field | Database Column | Type |
|-----------|----------------|------|
| Position | `new_position_id` | FK → positions |
| Location | `new_work_location_id` | FK → work_locations |
| Work Schedule | `new_work_schedule` | string |
| Job title | Via position relationship | - |
| Department | `new_department_id` | FK → departments |
| Pay plan | `new_pay_plan` | string |
| Phone Ext | `new_phone_ext` | string |
| Report to | `new_report_to` | string |
| Salary | `new_salary` | decimal(12,2) |

### Section 4: Comments

| Form Field | Database Column |
|-----------|----------------|
| Comments/Details | `comments` (text) |
| - | `change_details` (text) |

### Approved By

| Signature | Database Column |
|----------|----------------|
| Dept. Head / Supervisor | `dept_head_approved` (boolean) |
| COO of SMRU | `coo_approved` (boolean) |
| Human Resources Manager | `hr_approved` (boolean) |
| Accountant Manager | `accountant_approved` (boolean) |

---

## API Endpoints

### Base URL: `/api/v1/personnel-actions`

| Method | Endpoint | Description | Permission |
|--------|----------|-------------|------------|
| GET | `/personnel-actions` | List all personnel actions | `personnel_action.read` |
| GET | `/personnel-actions/constants` | Get valid constants | `personnel_action.read` |
| GET | `/personnel-actions/{id}` | Show single action | `personnel_action.read` |
| POST | `/personnel-actions` | Create new action | `personnel_action.create` |
| PUT/PATCH | `/personnel-actions/{id}` | Update action | `personnel_action.update` |
| PATCH | `/personnel-actions/{id}/approve` | Update approval | `personnel_action.update` |

### List Personnel Actions

```http
GET /api/v1/personnel-actions?page=1&per_page=15

Query Parameters:
- dept_head_approved (boolean)
- coo_approved (boolean)
- hr_approved (boolean)
- accountant_approved (boolean)
- action_type (string)
- employment_id (integer)
```

### Create Personnel Action

```http
POST /api/v1/personnel-actions

{
  "employment_id": 15,
  "effective_date": "2025-11-01",
  "action_type": "position_change",
  "action_subtype": "promotion",
  "new_department_id": 5,
  "new_position_id": 42,
  "new_work_location_id": 3,
  "new_salary": 65000.00,
  "comments": "Annual performance promotion"
}
```

### Update Approval

```http
PATCH /api/v1/personnel-actions/1/approve

{
  "approval_type": "dept_head",
  "approved": true
}
```

**Valid approval types:** `dept_head`, `coo`, `hr`, `accountant`

### Get Constants

```http
GET /api/v1/personnel-actions/constants

Response:
{
  "success": true,
  "data": {
    "action_types": { ... },
    "action_subtypes": { ... },
    "transfer_types": { ... },
    "statuses": { ... }
  }
}
```

---

## Model & Relationships

### PersonnelAction Model

```php
class PersonnelAction extends Model
{
    use SoftDeletes;
    
    // Relationships
    public function employment(): BelongsTo
    public function employee(): HasOneThrough
    public function creator(): BelongsTo
    public function updater(): BelongsTo
    
    // Current State Relationships
    public function currentDepartment(): BelongsTo
    public function currentPosition(): BelongsTo
    public function currentWorkLocation(): BelongsTo
    
    // New State Relationships
    public function newDepartment(): BelongsTo
    public function newPosition(): BelongsTo
    public function newWorkLocation(): BelongsTo
    
    // Helper Methods
    public function generateReferenceNumber(): string
    public function isFullyApproved(): bool
    public function canBeApprovedBy(User $user): bool
    public function populateCurrentEmploymentData(): void
    public function getStatusAttribute(): string
}
```

### Constants

```php
ACTION_TYPES = [
    'appointment' => 'Appointment',
    'fiscal_increment' => 'Fiscal Increment',
    'title_change' => 'Title Change',
    'voluntary_separation' => 'Voluntary Separation',
    'position_change' => 'Position Change',
    'transfer' => 'Transfer',
];

ACTION_SUBTYPES = [
    're_evaluated_pay_adjustment' => 'Re-Evaluated Pay Adjustment',
    'promotion' => 'Promotion',
    'demotion' => 'Demotion',
    'end_of_contract' => 'End of Contract',
    'work_allocation' => 'Work Allocation',
];

TRANSFER_TYPES = [
    'internal_department' => 'Internal Department',
    'site_to_site' => 'From Site to Site',
    'attachment_position' => 'Attachment Position',
];
```

---

## Service Layer

### PersonnelActionService

**Location:** `app/Services/PersonnelActionService.php`

#### Key Methods

1. **`createPersonnelAction(array $data): PersonnelAction`**
   - Creates personnel action
   - Auto-generates reference number (PA-YYYY-000001)
   - Auto-populates current employment data if not provided
   - Creates employment history entry
   - Uses DB transactions

2. **`updateApproval(PersonnelAction $action, string $type, bool $approved): bool`**
   - Updates specific approval (dept_head, coo, hr, accountant)
   - Validates approval type
   - Auto-implements action when all 4 approvals = true
   - Uses DB transactions

3. **`implementAction(PersonnelAction $action): bool`**
   - Updates employment record based on action type
   - Clears employment caches
   - Creates employment history
   - Uses DB transactions

#### Private Implementation Methods

- `handleAppointment()` - Updates position, department, salary, location
- `handlePositionChange()` - Updates position, department, salary
- `handleTransfer()` - Updates department, location, position
- `handleSeparation()` - Sets employment end_date
- `handleTitleChange()` - Updates position only

---

## Validation Rules

### PersonnelActionRequest

**Location:** `app/Http/Requests/PersonnelActionRequest.php`

#### Core Validations

```php
'employment_id' => 'required|exists:employments,id',
'effective_date' => 'required|date|after_or_equal:today',
'action_type' => 'required|in:appointment,fiscal_increment,title_change,...',
'action_subtype' => 'nullable|in:re_evaluated_pay_adjustment,promotion,...',
'is_transfer' => 'boolean',
'transfer_type' => 'nullable|required_if:is_transfer,true',
```

#### Foreign Key Validations

```php
'new_department_id' => 'nullable|exists:departments,id',
'new_position_id' => [
    'nullable',
    'integer',
    'exists:positions,id',
    function ($attribute, $value, $fail) {
        // Validate position belongs to department
        if ($this->filled('new_department_id') && $value) {
            $position = Position::find($value);
            if ($position && $position->department_id != $this->new_department_id) {
                $fail('The selected position must belong to the selected department.');
            }
        }
    },
],
'new_work_location_id' => 'nullable|exists:work_locations,id',
```

#### Action-Type Specific Validation

```php
withValidator($validator): void
{
    $validator->after(function ($validator) {
        if ($this->action_type === 'position_change' && !$this->new_position_id) {
            $validator->errors()->add('new_position_id', 'Required for position changes.');
        }
        
        if ($this->action_type === 'transfer' && !$this->new_department_id) {
            $validator->errors()->add('new_department_id', 'Required for transfers.');
        }
        
        if ($this->action_type === 'fiscal_increment' && !$this->new_salary) {
            $validator->errors()->add('new_salary', 'Required for fiscal increments.');
        }
    });
}
```

---

## Approval Workflow

### 4-Level Approval Process

1. **Department Head** → `dept_head_approved`
2. **COO** → `coo_approved`
3. **HR Manager** → `hr_approved`
4. **Accountant** → `accountant_approved`

### Auto-Implementation

When **all 4 approvals = true**:

1. ✅ Service automatically calls `implementAction()`
2. ✅ Employment record updated with new values
3. ✅ Employment history entry created
4. ✅ Employment caches cleared
5. ✅ Status becomes "fully_approved"

### Approval Status Logic

```php
public function getStatusAttribute(): string
{
    if ($this->isFullyApproved()) {
        return 'fully_approved';
    }
    
    if ($this->dept_head_approved || $this->coo_approved || 
        $this->hr_approved || $this->accountant_approved) {
        return 'partial_approved';
    }
    
    return 'pending';
}
```

---

## Code Examples

### Example 1: Create Position Change (Promotion)

```json
POST /api/v1/personnel-actions

{
  "employment_id": 15,
  "effective_date": "2025-11-01",
  "action_type": "position_change",
  "action_subtype": "promotion",
  "new_department_id": 5,
  "new_position_id": 42,
  "new_work_location_id": 3,
  "new_salary": 65000.00,
  "new_work_schedule": "Monday-Friday 9AM-5PM",
  "new_report_to": "Jane Smith",
  "comments": "Annual performance promotion",
  "change_details": "Promoted based on excellent performance review"
}
```

### Example 2: Create Transfer

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

### Example 3: Approve by Department Head

```json
PATCH /api/v1/personnel-actions/1/approve

{
  "approval_type": "dept_head",
  "approved": true
}
```

### Example 4: List Pending Approvals

```http
GET /api/v1/personnel-actions?dept_head_approved=false&page=1
```

---

## Related Documentation

| Document | Description |
|----------|-------------|
| `PERSONNEL_ACTION_FORM_TO_API_MAPPING.md` | Complete form-to-API field mapping |
| `PERSONNEL_ACTIONS_ANALYSIS_AND_IMPROVEMENTS.md` | Analysis of Employment system alignment |
| `PERSONNEL_ACTIONS_IMPROVEMENTS_IMPLEMENTED.md` | Implementation details and benefits |
| `PERSONNEL_ACTIONS_API_UPDATED_REFERENCE.md` | Quick reference guide for frontend |

---

## Files Changed

### Database
- `database/migrations/2025_09_25_134034_create_personnel_actions_table.php`

### Models
- `app/Models/PersonnelAction.php`

### Services
- `app/Services/PersonnelActionService.php`

### Controllers
- `app/Http/Controllers/Api/PersonnelActionController.php`

### Requests
- `app/Http/Requests/PersonnelActionRequest.php`

### Routes
- `routes/api/personnel_actions.php`

### Seeders
- `database/seeders/PersonnelActionPermissionSeeder.php` (pre-existing)
- `database/seeders/PermissionRoleSeeder.php` (includes personnel_action permissions)

---

## Deployment Checklist

- [x] Migration created with foreign keys
- [x] Model with all relationships
- [x] Service layer implemented
- [x] Controller with all endpoints
- [x] Form request validation
- [x] Routes configured
- [x] Permissions seeded
- [x] Swagger documentation generated
- [x] Code formatted with Pint
- [x] No linter errors
- [x] SQL Server compatible (no cascade cycles)
- [x] Documentation complete

---

## Testing

### Manual Testing Steps

1. **Create Personnel Action**
   ```bash
   POST /api/v1/personnel-actions
   ```

2. **Verify Auto-Population**
   - Check that Section 1 fields are filled from employment

3. **Test Position-Department Validation**
   - Try creating with position from wrong department → should fail

4. **Test Approval Workflow**
   - Approve with dept_head
   - Approve with coo
   - Approve with hr
   - Approve with accountant → employment should update automatically

5. **Verify Employment Update**
   - Check that employment record has new values

6. **Check Employment History**
   - Verify history entry was created

---

**Implementation Status:** ✅ COMPLETE  
**Production Ready:** ✅ YES  
**Last Updated:** October 2, 2025  
**Version:** 2.0 (Foreign Key Architecture)

