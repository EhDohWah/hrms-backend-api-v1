# Personnel Actions Analysis & Improvement Plan

## Executive Summary

After analyzing the Employment system implementation, I've identified critical inconsistencies in the current Personnel Actions implementation. Employment records use **foreign key relationships** (department_id, position_id), while Personnel Actions currently use **text fields**. This analysis proposes a complete restructuring to align with Employment patterns.

---

## Key Findings from Employment System Analysis

### 1. **Employment Uses Foreign Key IDs, Not Text Fields**

```php
// Employment Model Fields
'department_id'      => FK to departments table
'position_id'        => FK to positions table  
'work_location_id'   => FK to work_locations table
'pass_probation_salary'    => decimal(12,2)
```

### 2. **Strict Position-Department Relationship Validation**

**In StoreEmploymentRequest:**
```php
'position_id' => [
    'required', 
    'integer', 
    'exists:positions,id', 
    Rule::exists('positions', 'id')
        ->where(fn($q) => $q->where('department_id', $this->department_id))
],
```

**In Position Model boot():**
```php
static::creating(function ($position) {
    if ($position->reports_to_position_id) {
        $supervisor = Position::find($position->reports_to_position_id);
        if ($supervisor && $supervisor->department_id !== $position->department_id) {
            throw new \InvalidArgumentException('Position cannot report to someone from a different department');
        }
    }
});
```

### 3. **Employment Creation Pattern**

```php
// 1. Validate employee_id, department_id, position_id exist
// 2. Validate position belongs to department
// 3. Validate total allocations = 100%
// 4. Check no active employment exists
// 5. Create employment with all foreign keys
// 6. Create funding allocations
// 7. Load relationships and return
```

---

## Current Personnel Actions Problems

### ❌ Problem 1: Text Fields Instead of Foreign Keys

```php
// Current (WRONG)
'current_position'  => string (text)
'current_department' => string (text)
'new_position'      => string (text)
'new_department'    => string (text)
'new_location'      => string (text)
```

**Issues:**
- No referential integrity
- Can't use relationships
- Manual string matching required
- Data inconsistency risk
- No cascade updates

### ❌ Problem 2: No Department-Position Validation

Current implementation doesn't validate that new_position belongs to new_department, but Employment system strictly enforces this.

### ❌ Problem 3: Manual Field Resolution in Service

```php
// Current approach (inefficient)
private function resolvePositionId(?string $positionName): ?int
{
    if (!$positionName) return null;
    if (is_numeric($positionName)) return (int) $positionName;
    
    // Try to find position by title
    $position = DB::table('positions')->where('title', $positionName)->first();
    return $position?->id;
}
```

This is error-prone and doesn't enforce department relationship.

### ❌ Problem 4: No Auto-Population of Current Employment Data

When creating a personnel action, user manually enters current position, department, salary instead of auto-populating from employment record.

---

## Proposed Solution: Align with Employment Patterns

### ✅ Solution 1: Update Migration to Use Foreign Keys

**New Migration Structure:**
```php
Schema::create('personnel_actions', function (Blueprint $table) {
    $table->id();
    $table->string('form_number')->default('SMRU-SF038');
    $table->string('reference_number')->unique();
    
    // Employment Reference
    $table->foreignId('employment_id')->constrained('employments');
    
    // CURRENT EMPLOYMENT DATA (captured at creation for audit trail)
    $table->string('current_employee_no')->nullable();
    $table->foreignId('current_department_id')->nullable()->constrained('departments');
    $table->foreignId('current_position_id')->nullable()->constrained('positions');
    $table->decimal('current_salary', 12, 2)->nullable();
    $table->foreignId('current_work_location_id')->nullable()->constrained('work_locations');
    $table->date('current_employment_date')->nullable();
    
    // ACTION DETAILS
    $table->date('effective_date');
    $table->string('action_type');
    $table->string('action_subtype')->nullable();
    $table->boolean('is_transfer')->default(false);
    $table->string('transfer_type')->nullable();
    
    // NEW EMPLOYMENT DATA (proposed changes)
    $table->foreignId('new_department_id')->nullable()->constrained('departments');
    $table->foreignId('new_position_id')->nullable()->constrained('positions');
    $table->foreignId('new_work_location_id')->nullable()->constrained('work_locations');
    $table->decimal('new_salary', 12, 2)->nullable();
    
    // Additional text fields for supplementary information
    $table->string('new_work_schedule')->nullable();
    $table->string('new_report_to')->nullable();
    $table->string('new_pay_plan')->nullable();
    $table->string('new_phone_ext')->nullable();
    $table->string('new_email')->nullable();
    
    // Comments & Approvals
    $table->text('comments')->nullable();
    $table->text('change_details')->nullable();
    $table->boolean('dept_head_approved')->default(false);
    $table->boolean('coo_approved')->default(false);
    $table->boolean('hr_approved')->default(false);
    $table->boolean('accountant_approved')->default(false);
    
    // Audit fields
    $table->foreignId('created_by')->constrained('users');
    $table->foreignId('updated_by')->nullable()->constrained('users');
    $table->timestamps();
    $table->softDeletes();
    
    // Indexes
    $table->index(['employment_id', 'effective_date']);
    $table->index(['dept_head_approved', 'coo_approved', 'hr_approved', 'accountant_approved']);
});
```

### ✅ Solution 2: Enhanced Model with Relationships

```php
class PersonnelAction extends Model
{
    use SoftDeletes;
    
    protected $fillable = [
        'form_number', 'reference_number', 'employment_id',
        'current_employee_no', 
        'current_department_id', 'current_position_id', 
        'current_salary', 'current_work_location_id', 'current_employment_date',
        'effective_date', 'action_type', 'action_subtype', 
        'is_transfer', 'transfer_type',
        'new_department_id', 'new_position_id', 'new_work_location_id',
        'new_salary', 'new_work_schedule', 'new_report_to',
        'new_pay_plan', 'new_phone_ext', 'new_email',
        'comments', 'change_details',
        'dept_head_approved', 'coo_approved', 'hr_approved', 'accountant_approved',
        'created_by', 'updated_by',
    ];
    
    // Current State Relationships
    public function currentDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'current_department_id');
    }
    
    public function currentPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'current_position_id');
    }
    
    public function currentWorkLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class, 'current_work_location_id');
    }
    
    // New State Relationships
    public function newDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'new_department_id');
    }
    
    public function newPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'new_position_id');
    }
    
    public function newWorkLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class, 'new_work_location_id');
    }
    
    // Auto-populate current employment data
    public function populateCurrentEmploymentData(): void
    {
        $employment = $this->employment()->with(['department', 'position', 'workLocation', 'employee'])->first();
        
        if ($employment) {
            $this->current_employee_no = $employment->employee->staff_id;
            $this->current_department_id = $employment->department_id;
            $this->current_position_id = $employment->position_id;
            $this->current_salary = $employment->pass_probation_salary;
            $this->current_work_location_id = $employment->work_location_id;
            $this->current_employment_date = $employment->start_date;
        }
    }
}
```

### ✅ Solution 3: Validation Like Employment

```php
class PersonnelActionRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'employment_id' => 'required|exists:employments,id',
            'effective_date' => 'required|date|after_or_equal:today',
            'action_type' => 'required|in:'.implode(',', array_keys(PersonnelAction::ACTION_TYPES)),
            'action_subtype' => 'nullable|in:'.implode(',', array_keys(PersonnelAction::ACTION_SUBTYPES)),
            'is_transfer' => 'boolean',
            'transfer_type' => 'nullable|required_if:is_transfer,true',
            
            // NEW: Foreign key validation
            'new_department_id' => 'nullable|exists:departments,id',
            'new_position_id' => [
                'nullable',
                'integer',
                'exists:positions,id',
                // Validate position belongs to department if both provided
                function ($attribute, $value, $fail) {
                    if ($this->filled('new_department_id') && $value) {
                        $position = \App\Models\Position::find($value);
                        if ($position && $position->department_id != $this->new_department_id) {
                            $fail('The selected position must belong to the selected department.');
                        }
                    }
                },
            ],
            'new_work_location_id' => 'nullable|exists:work_locations,id',
            'new_salary' => 'nullable|numeric|min:0',
            
            // Additional fields remain as strings
            'new_work_schedule' => 'nullable|string|max:255',
            'new_report_to' => 'nullable|string|max:255',
            'new_pay_plan' => 'nullable|string|max:255',
            'new_phone_ext' => 'nullable|string|max:20',
            'new_email' => 'nullable|email|max:255',
            
            'comments' => 'nullable|string',
            'change_details' => 'nullable|string',
            
            // Approval fields
            'dept_head_approved' => 'boolean',
            'coo_approved' => 'boolean',
            'hr_approved' => 'boolean',
            'accountant_approved' => 'boolean',
        ];
    }
    
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Auto-validate based on action type
            if ($this->action_type === 'position_change' && !$this->new_position_id) {
                $validator->errors()->add('new_position_id', 'New position is required for position changes.');
            }
            
            if ($this->action_type === 'transfer' && !$this->new_department_id) {
                $validator->errors()->add('new_department_id', 'New department is required for transfers.');
            }
            
            if ($this->action_type === 'fiscal_increment' && !$this->new_salary) {
                $validator->errors()->add('new_salary', 'New salary is required for fiscal increments.');
            }
        });
    }
}
```

### ✅ Solution 4: Simplified Service (No Resolution Needed)

```php
class PersonnelActionService
{
    public function createPersonnelAction(array $data): PersonnelAction
    {
        return DB::transaction(function () use ($data) {
            $personnelAction = PersonnelAction::create($data);
            
            // Auto-populate current employment data if not provided
            if (!$personnelAction->current_department_id) {
                $personnelAction->populateCurrentEmploymentData();
                $personnelAction->save();
            }
            
            // Generate reference number
            $personnelAction->update([
                'reference_number' => $personnelAction->generateReferenceNumber(),
            ]);
            
            // Create employment history
            $employment = $personnelAction->employment;
            $employment->addHistoryEntry(
                "Personnel Action {$personnelAction->reference_number} created: {$personnelAction->action_type}",
                $personnelAction->comments
            );
            
            return $personnelAction->fresh();
        });
    }
    
    private function handlePositionChange(Employment $employment, PersonnelAction $action): void
    {
        // Much simpler - direct ID usage
        $updateData = array_filter([
            'position_id' => $action->new_position_id,
            'department_id' => $action->new_department_id,
            'pass_probation_salary' => $action->new_salary,
            'updated_by' => Auth::user()?->name ?? 'Personnel Action',
        ], fn($value) => $value !== null);
        
        if (!empty($updateData)) {
            $employment->update($updateData);
        }
    }
    
    // No need for resolve methods!
}
```

---

## Migration Strategy

### Option A: Create New Migration (Recommended)

```php
// 2025_10_02_create_new_personnel_actions_structure.php
public function up(): void
{
    // 1. Rename old table
    Schema::rename('personnel_actions', 'personnel_actions_old');
    
    // 2. Create new table with correct structure
    Schema::create('personnel_actions', function (Blueprint $table) {
        // ... new structure
    });
    
    // 3. Migrate existing data if any
    $oldRecords = DB::table('personnel_actions_old')->get();
    foreach ($oldRecords as $old) {
        // Convert text fields to IDs where possible
        DB::table('personnel_actions')->insert([
            // ... migration logic
        ]);
    }
}
```

### Option B: Modify Existing Migration (If No Production Data)

Simply update the existing migration file `2025_09_25_134034_create_personnel_actions_table.php` with the new structure.

---

## Benefits of This Approach

### 1. **Data Integrity**
- Foreign key constraints prevent invalid references
- Cascading deletes/updates handled automatically
- Referential integrity enforced at database level

### 2. **Consistency with Employment**
- Same field types and structure
- Same validation patterns
- Same relationship patterns

### 3. **Performance**
- Direct joins instead of string matching
- Indexed foreign keys
- No runtime resolution overhead

### 4. **Maintainability**
- Clear relationships visible in code
- IDE autocomplete support
- Easier to understand and modify

### 5. **Audit Trail**
- Current state captured as IDs with relationships
- Can always load historical department/position names via relationships
- Protects against master data changes

---

## Implementation Checklist

- [ ] Create new migration or modify existing
- [ ] Update PersonnelAction model with relationships
- [ ] Add populateCurrentEmploymentData() method
- [ ] Update PersonnelActionRequest validation
- [ ] Simplify PersonnelActionService (remove resolve methods)
- [ ] Update Controller to eager load relationships
- [ ] Update API responses to include relationship data
- [ ] Update Swagger documentation
- [ ] Test position-department validation
- [ ] Test auto-population of current data
- [ ] Run Pint for code formatting

---

## API Usage Examples

### Before (Text-based):
```json
POST /api/v1/personnel-actions
{
    "employment_id": 15,
    "action_type": "position_change",
    "new_position": "Senior Developer",  // ❌ Text
    "new_department": "Engineering",      // ❌ Text
    "new_salary": 65000
}
```

### After (ID-based):
```json
POST /api/v1/personnel-actions
{
    "employment_id": 15,
    "action_type": "position_change",
    "new_position_id": 42,         // ✅ Foreign Key
    "new_department_id": 5,        // ✅ Foreign Key
    "new_salary": 65000
}

// Response includes relationships:
{
    "success": true,
    "data": {
        "id": 1,
        "employment_id": 15,
        "new_position_id": 42,
        "new_department_id": 5,
        "new_salary": 65000,
        "new_position": {
            "id": 42,
            "title": "Senior Developer",
            "department_id": 5
        },
        "new_department": {
            "id": 5,
            "name": "Engineering"
        }
    }
}
```

---

## Conclusion

The current Personnel Actions implementation diverges significantly from Employment patterns by using text fields instead of foreign key relationships. This creates data integrity issues, validation gaps, and maintenance overhead.

**Recommendation:** Implement the proposed changes to align Personnel Actions with Employment structure, ensuring consistency, reliability, and maintainability across the HRMS system.

---

**Analysis Date:** October 2, 2025  
**Status:** Awaiting Implementation Decision

