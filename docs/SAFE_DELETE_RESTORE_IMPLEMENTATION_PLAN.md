# Safe Delete & Restore Implementation Plan

## HRMS Recycle Bin System for Complex Relationships

---

## Executive Summary

This document outlines the implementation strategy for a safe delete/restore mechanism that handles tables with complex foreign key relationships. The goal is to enable users to delete records (e.g., employees) while preserving the ability to fully restore them with all related data intact.

---

## Current State Analysis

### Tables Already Using KeepsDeletedModels (Working)
| Table | Has FK Dependencies? | Status |
|-------|---------------------|--------|
| `interviews` | No | ✅ Working |
| `job_offers` | No | ✅ Working |

### Tables Using SoftDeletes Only (No Recycle Bin)
| Table | Notes |
|-------|-------|
| `benefit_settings` | System config, rarely deleted |
| `modules` | System config |
| `personnel_actions` | Historical records |
| `sites` | Referenced by employments |
| `section_departments` | Referenced by employments |
| `resignations` | Has employee FK |

### Critical Tables Needing Safe Delete (Not Yet Implemented)
| Table | Child Dependencies | Complexity |
|-------|-------------------|------------|
| `employees` | 11+ child tables | **CRITICAL** |
| `employments` | 5+ child tables | **HIGH** |
| `grants` | grant_items → allocations → payrolls | **HIGH** |
| `departments` | positions, section_departments | **MEDIUM** |
| `leave_requests` | leave_request_items | **LOW** |

---

## Recommended Strategy: "Cascading Soft Delete with Snapshot"

### Why Not Use Database CASCADE DELETE?
- **Data Loss**: CASCADE DELETE permanently removes related records
- **No Recovery**: Cannot restore deleted child records
- **Audit Trail Lost**: No history of what was deleted

### Why Not Use Laravel SoftDeletes Alone?
- **Orphan Problem**: Parent deleted, children still exist
- **Constraint Violations**: Can't delete parent if FK constraints exist
- **Incomplete Restore**: Restoring parent doesn't restore children

### Recommended Approach: Cascading Snapshot Delete

```
DELETE employee (id: 100)
    ├── Snapshot employee record → deleted_models (key: "employee_100")
    ├── Snapshot ALL related records with parent reference
    │   ├── employments (employee_id: 100) → deleted_models (key: "emp_100_employment_1")
    │   ├── leave_requests (employee_id: 100) → deleted_models
    │   ├── leave_balances (employee_id: 100) → deleted_models
    │   └── ... all other dependencies
    ├── Store deletion manifest (JSON) linking all snapshots
    └── Hard delete all records in correct FK order (children first)

RESTORE employee (key: "employee_100")
    ├── Read deletion manifest
    ├── Restore parent first (with IDENTITY_INSERT for MSSQL)
    ├── Restore children in correct FK order
    └── Delete all related snapshots from deleted_models
```

---

## Database Relationship Map

### Tier 1: Root Tables (No Parent FK)
```
users, departments, sites, grants, leave_types, trainings,
lookups, organizations, modules, holidays, tax_settings,
tax_brackets, benefit_settings
```

### Tier 2: Primary Entities (Have Parents, Many Children)
```
employees ──────────────┐
    └── user_id → users │ (SET NULL on delete)
                        │
positions ──────────────┤
    └── department_id → departments (CASCADE)
                        │
grant_items ────────────┤
    └── grant_id → grants (CASCADE)
```

### Tier 3: Core Records (Middle of Chain)
```
employments
    ├── employee_id → employees (CASCADE)
    ├── department_id → departments (NO ACTION)
    ├── position_id → positions (NO ACTION)
    ├── section_department_id → section_departments (SET NULL)
    └── site_id → sites (SET NULL)

employee_funding_allocations
    ├── employee_id → employees
    ├── employment_id → employments
    └── grant_item_id → grant_items (NO ACTION)
```

### Tier 4: Leaf Tables (End of Chain)
```
employee_beneficiaries, employee_children, employee_education,
employee_languages, employee_trainings, leave_request_items,
employment_histories, payrolls, payroll_grant_allocations,
probation_records, tax_calculation_logs, holiday_compensation_records
```

---

## Implementation Plan

### Phase 1: Infrastructure (Foundation)

#### Task 1.1: Create Deletion Manifest Table
Store metadata about cascading deletions for grouped restore.

```php
// Migration: create_deletion_manifests_table.php
Schema::create('deletion_manifests', function (Blueprint $table) {
    $table->id();
    $table->string('root_model');           // e.g., "App\Models\Employee"
    $table->unsignedBigInteger('root_id');  // Original ID of deleted root record
    $table->string('deletion_key')->unique(); // UUID for this deletion group
    $table->json('snapshot_keys');          // Array of deleted_models keys
    $table->json('deletion_order');         // Order records were deleted (for restore)
    $table->string('deleted_by')->nullable(); // User who deleted
    $table->text('reason')->nullable();     // Deletion reason
    $table->timestamps();

    $table->index(['root_model', 'root_id']);
});
```

#### Task 1.2: Create CascadingDelete Trait
Base trait for models that need cascading safe delete.

```php
// app/Traits/CascadingDeletesWithSnapshot.php
trait CascadingDeletesWithSnapshot
{
    use KeepsDeletedModels;

    // Define in each model
    abstract protected function getCascadeRelations(): array;

    public function safeDelete(?string $reason = null): DeletionManifest
    {
        // 1. Collect all related records
        // 2. Create snapshots in deleted_models
        // 3. Create manifest linking them
        // 4. Delete in FK-safe order (children first)
        // 5. Return manifest for potential restore
    }

    public static function safeRestore(string $deletionKey): static
    {
        // 1. Load manifest
        // 2. Restore parent first
        // 3. Restore children in order
        // 4. Clean up manifest and snapshots
    }
}
```

#### Task 1.3: Create Deletion Service
Centralized service for handling complex deletions.

```php
// app/Services/SafeDeleteService.php
class SafeDeleteService
{
    public function delete(Model $model, ?string $reason = null): DeletionManifest;
    public function restore(string $deletionKey): Model;
    public function getDeletedRecords(string $modelClass): Collection;
    public function permanentlyDelete(string $deletionKey): bool;
    public function getManifest(string $deletionKey): ?DeletionManifest;
}
```

---

### Phase 2: Employee Deletion (Most Complex)

#### Task 2.1: Map Employee Dependencies
```php
// Employee cascade relations (in order of deletion - children first)
protected function getCascadeRelations(): array
{
    return [
        // Tier 4: Leaf records (delete first)
        'payrolls.payrollGrantAllocations',     // Payroll allocations
        'payrolls',                              // Payrolls
        'employments.employmentHistories',       // Employment history
        'employments.probationRecords',          // Probation records
        'employments.employeeFundingAllocations.history', // Allocation history
        'employments.employeeFundingAllocations', // Funding allocations
        'employments',                           // Employments

        // Tier 3: Direct children
        'leaveRequests.items',                   // Leave request items
        'leaveRequests',                         // Leave requests
        'leaveBalances',                         // Leave balances
        'travelRequests',                        // Travel requests
        'resignations',                          // Resignations
        'beneficiaries',                         // Beneficiaries
        'children',                              // Children
        'education',                             // Education
        'languages',                             // Languages
        'trainings',                             // Trainings (pivot)
        'taxCalculationLogs',                    // Tax logs
        'holidayCompensationRecords',            // Holiday compensation
    ];
}
```

#### Task 2.2: Add Trait to Employee Model
```php
class Employee extends Model
{
    use HasFactory, CascadingDeletesWithSnapshot;

    protected function getCascadeRelations(): array { /* ... */ }
}
```

#### Task 2.3: Create Employee Delete Controller Method
```php
// DELETE /api/v1/employees/{id}
public function destroy(Employee $employee, Request $request)
{
    $reason = $request->input('reason');

    $manifest = $employee->safeDelete($reason);

    return response()->json([
        'success' => true,
        'message' => 'Employee moved to recycle bin',
        'deletion_key' => $manifest->deletion_key,
        'deleted_records_count' => count($manifest->snapshot_keys),
    ]);
}
```

#### Task 2.4: Create Employee Restore Controller Method
```php
// POST /api/v1/employees/restore/{deletionKey}
public function restore(string $deletionKey)
{
    $employee = Employee::safeRestore($deletionKey);

    return response()->json([
        'success' => true,
        'message' => 'Employee and all related records restored',
        'data' => new EmployeeResource($employee),
    ]);
}
```

---

### Phase 3: Grant Deletion

#### Task 3.1: Map Grant Dependencies
```php
// Grant cascade relations
protected function getCascadeRelations(): array
{
    return [
        'grantItems.employeeFundingAllocations.payrollAllocations',
        'grantItems.employeeFundingAllocations.history',
        'grantItems.employeeFundingAllocations',
        'grantItems',
    ];
}
```

#### Task 3.2: Handle Payroll Constraint
**Challenge**: Payrolls reference employments with `NO ACTION` - cannot delete grant if payrolls exist.

**Options**:
1. **Block Delete**: Prevent grant deletion if any payroll references it
2. **Orphan Payrolls**: Set allocation_id to NULL on payrolls (requires schema change)
3. **Include in Snapshot**: Snapshot the payrolls too (dangerous - affects employee data)

**Recommendation**: Option 1 - Block Delete with validation
```php
public function canSafeDelete(): array
{
    $blockers = [];

    // Check if any payrolls reference this grant's allocations
    $payrollCount = Payroll::whereHas('employeeFundingAllocation.grantItem', function ($q) {
        $q->where('grant_id', $this->id);
    })->count();

    if ($payrollCount > 0) {
        $blockers[] = "Cannot delete: {$payrollCount} payroll records reference this grant";
    }

    return $blockers;
}
```

---

### Phase 4: Department & Position Deletion

#### Task 4.1: Map Department Dependencies
```php
protected function getCascadeRelations(): array
{
    return [
        'positions',           // Positions in this department
        'sectionDepartments',  // Sub-departments
    ];
}
```

#### Task 4.2: Handle Employment Constraint
**Challenge**: Employments reference departments with `NO ACTION`.

**Options**:
1. **Block Delete**: Cannot delete department with active employments
2. **Reassign**: Move employees to a "Deleted Department" placeholder
3. **Set NULL**: Change schema to allow null department_id

**Recommendation**: Option 1 - Block Delete
```php
public function canSafeDelete(): array
{
    $blockers = [];

    $employmentCount = Employment::where('department_id', $this->id)->count();
    if ($employmentCount > 0) {
        $blockers[] = "Cannot delete: {$employmentCount} employments in this department";
    }

    return $blockers;
}
```

---

### Phase 5: Leave Request Deletion

#### Task 5.1: Map Leave Request Dependencies
```php
protected function getCascadeRelations(): array
{
    return [
        'items',  // Leave request items (already CASCADE)
    ];
}
```

This is simpler since `leave_request_items` already cascades.

---

### Phase 6: Recycle Bin UI Integration

#### Task 6.1: Create Recycle Bin API Endpoints
```php
// routes/api/recycle-bin.php
Route::prefix('recycle-bin')->group(function () {
    Route::get('/', [RecycleBinController::class, 'index']);           // List all deleted
    Route::get('/{model}', [RecycleBinController::class, 'byModel']);  // List by model type
    Route::post('/restore/{key}', [RecycleBinController::class, 'restore']);
    Route::delete('/permanent/{key}', [RecycleBinController::class, 'permanentDelete']);
    Route::delete('/empty', [RecycleBinController::class, 'emptyTrash']); // Clear all
});
```

#### Task 6.2: Create Recycle Bin Controller
```php
class RecycleBinController extends Controller
{
    public function __construct(
        private SafeDeleteService $deleteService
    ) {}

    public function index(Request $request)
    {
        $manifests = DeletionManifest::with('deletedBy')
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return RecycleBinResource::collection($manifests);
    }

    public function restore(string $deletionKey)
    {
        $model = $this->deleteService->restore($deletionKey);

        return response()->json([
            'success' => true,
            'message' => 'Record restored successfully',
            'model_type' => get_class($model),
            'model_id' => $model->id,
        ]);
    }
}
```

---

## SQL Server Considerations

### IDENTITY_INSERT for Restore
When restoring records on SQL Server, we must preserve original IDs:

```php
trait RestoresWithIdentity
{
    protected static function restoreWithOriginalId(array $data, string $table): Model
    {
        $originalId = $data['id'] ?? null;

        if (!$originalId || DB::getDriverName() !== 'sqlsrv') {
            return static::create($data);
        }

        DB::statement("SET IDENTITY_INSERT {$table} ON");

        try {
            $model = static::create($data);
        } finally {
            DB::statement("SET IDENTITY_INSERT {$table} OFF");
        }

        return $model;
    }
}
```

### Transaction Handling
All cascading operations must be wrapped in transactions:

```php
public function safeDelete(?string $reason = null): DeletionManifest
{
    return DB::transaction(function () use ($reason) {
        // Snapshot all records
        // Create manifest
        // Delete in order
        // Return manifest
    });
}
```

---

## Implementation Order (Task List)

### Week 1: Foundation
- [ ] 1.1 Create `deletion_manifests` migration
- [ ] 1.2 Create `DeletionManifest` model
- [ ] 1.3 Create `CascadingDeletesWithSnapshot` trait
- [ ] 1.4 Create `SafeDeleteService`
- [ ] 1.5 Create `RestoresWithIdentity` trait (SQL Server)
- [ ] 1.6 Write unit tests for foundation

### Week 2: Employee Implementation
- [ ] 2.1 Add trait to `Employee` model
- [ ] 2.2 Define cascade relations for Employee
- [ ] 2.3 Implement `canSafeDelete()` validation
- [ ] 2.4 Create employee delete API endpoint
- [ ] 2.5 Create employee restore API endpoint
- [ ] 2.6 Write integration tests for employee delete/restore

### Week 3: Grant & Department Implementation
- [ ] 3.1 Add trait to `Grant` model
- [ ] 3.2 Add trait to `Department` model
- [ ] 3.3 Implement blocking validation for constraints
- [ ] 3.4 Create grant delete/restore endpoints
- [ ] 3.5 Create department delete/restore endpoints
- [ ] 3.6 Write integration tests

### Week 4: UI & Polish
- [ ] 4.1 Create Recycle Bin API endpoints
- [ ] 4.2 Create `RecycleBinController`
- [ ] 4.3 Create `RecycleBinResource`
- [ ] 4.4 Add recycle bin permissions to RBAC
- [ ] 4.5 Document API endpoints in Swagger
- [ ] 4.6 Frontend integration (separate task)

---

## API Endpoints Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| DELETE | `/api/v1/employees/{id}` | Safe delete employee |
| POST | `/api/v1/employees/restore/{key}` | Restore employee |
| DELETE | `/api/v1/grants/{id}` | Safe delete grant |
| POST | `/api/v1/grants/restore/{key}` | Restore grant |
| GET | `/api/v1/recycle-bin` | List all deleted records |
| GET | `/api/v1/recycle-bin/{model}` | List by model type |
| POST | `/api/v1/recycle-bin/restore/{key}` | Restore any record |
| DELETE | `/api/v1/recycle-bin/permanent/{key}` | Permanently delete |
| DELETE | `/api/v1/recycle-bin/empty` | Empty entire recycle bin |

---

## Risk Mitigation

### Data Integrity
- Always use transactions
- Validate FK constraints before delete
- Test restore on copy of production data

### Performance
- Batch snapshot creation for large dependency trees
- Index `deletion_manifests` on `root_model`, `root_id`
- Consider async queue for large deletions

### Storage
- Set retention policy for recycle bin (e.g., 30 days)
- Create scheduled job to purge old manifests
- Monitor `deleted_models` table size

---

## Questions for Clarification

Before implementation, please confirm:

1. **Retention Period**: How long should deleted records stay in recycle bin? (30 days? 90 days? Forever?)

2. **Payroll Handling**: When deleting an employee with existing payrolls:
   - Block deletion entirely?
   - Include payrolls in snapshot (allows full restore)?
   - Orphan payrolls (set employee references to NULL)?

3. **Audit Requirements**: Should we log who deleted/restored and when in `activity_logs`?

4. **Permissions**: Should only certain roles be able to:
   - Delete employees?
   - Restore from recycle bin?
   - Permanently delete from recycle bin?

5. **Bulk Operations**: Do you need bulk delete/restore capabilities?

---

## Next Steps

1. Review and approve this plan
2. Answer clarification questions above
3. Start with Phase 1 (Foundation) implementation
4. Proceed through phases in order with testing at each stage
