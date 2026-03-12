# Transfer & Action Change — Implementation Plan

## Overview

Two independent systems:

1. **Transfer** — Changes `employee.organization` (SMRU ↔ BHF). A simple, direct operation. No approval workflow. HR does it, it's done.
2. **Action Change** — The existing Personnel Action system (SMRU-SF038). Changes employment details: position, department, site, salary. Has a 4-level approval workflow. Already implemented on backend, needs bug fixes.

Sometimes they happen together: HR does a transfer first (change org), then creates an action change (change position/dept/salary). But they are **two independent operations** — transfer does not live inside the personnel action system.

---

## Part A: Transfer (New)

### What It Is

A dedicated endpoint to change an employee's organization. That's it.

```
BEFORE: Employee #101 — organization: SMRU
AFTER:  Employee #101 — organization: BHF
```

No approval workflow. No new table. Just a dedicated endpoint with proper validation, audit trail, and cache invalidation.

### Why Not Just Use the Existing Employee Update?

The existing `PUT /employees/{id}` allows changing organization as part of a full employee update. But:
- It mixes a significant organizational change with mundane field edits
- No specific validation for the transfer (staff_id uniqueness in target org)
- No specific logging that makes it easy to find "all transfers" in the audit trail
- The frontend sends ALL employee fields on update — the org change gets lost in the noise

A dedicated `POST /employees/{id}/transfer` endpoint makes the operation explicit, auditable, and independently callable.

### API Design

```
POST /api/v1/employees/{employee}/transfer

Body:
{
    "new_organization": "BHF",       // required: "SMRU" or "BHF"
    "effective_date": "2026-03-10",  // required: when the transfer takes effect
    "reason": "Budget reallocation"  // optional: transfer reason for audit trail
}

Response:
{
    "success": true,
    "message": "Employee transferred from SMRU to BHF",
    "data": { /* updated employee */ }
}
```

### Backend Implementation

#### 1. Form Request

**New file:** `app/Http/Requests/Employee/TransferEmployeeRequest.php`

```php
<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('employees.edit');
    }

    public function rules(): array
    {
        return [
            'new_organization' => ['required', 'string', Rule::in(['SMRU', 'BHF'])],
            'effective_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $employee = $this->route('employee');

            // Must be different from current organization
            if ($this->new_organization === $employee->organization) {
                $validator->errors()->add(
                    'new_organization',
                    "Employee is already in {$employee->organization}."
                );
            }

            // Check staff_id uniqueness in target organization
            $exists = \App\Models\Employee::where('staff_id', $employee->staff_id)
                ->where('organization', $this->new_organization)
                ->where('id', '!=', $employee->id)
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                $validator->errors()->add(
                    'new_organization',
                    "Staff ID {$employee->staff_id} already exists in {$this->new_organization}."
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'new_organization.required' => 'Target organization is required.',
            'new_organization.in' => 'Organization must be SMRU or BHF.',
            'effective_date.required' => 'Effective date is required.',
        ];
    }
}
```

#### 2. Service Method

**Add to:** `app/Services/EmployeeDataService.php`

```php
public function transfer(Employee $employee, array $validated): Employee
{
    $oldOrganization = $employee->organization;
    $newOrganization = $validated['new_organization'];

    $employee->update([
        'organization' => $newOrganization,
        'updated_by' => auth()->user()->name ?? 'system',
    ]);

    // LogsActivity trait automatically captures the old → new diff in activity_logs.
    // Add an explicit log entry with transfer-specific context for easy filtering.
    $employee->logActivity('transferred', [
        'from_organization' => $oldOrganization,
        'to_organization' => $newOrganization,
        'effective_date' => $validated['effective_date'],
        'reason' => $validated['reason'] ?? null,
    ], "Organization transfer: {$oldOrganization} → {$newOrganization}");

    // Invalidate caches
    $this->invalidateCache();

    // Notify
    $this->notifyAction('transferred', $employee);

    $employee->refresh();

    return $employee;
}
```

That's it. The `LogsActivity` trait on the Employee model already writes to `activity_logs` on every update with before/after values. The explicit `logActivity('transferred', ...)` call adds a second entry with `action = 'transferred'` so you can query all transfers specifically:

```sql
SELECT * FROM activity_logs
WHERE subject_type = 'App\Models\Employee'
AND action = 'transferred'
ORDER BY created_at DESC;
```

#### 3. Controller Method

**Add to:** `app/Http/Controllers/Api/V1/EmployeeController.php`

```php
use App\Http\Requests\Employee\TransferEmployeeRequest;

public function transfer(TransferEmployeeRequest $request, Employee $employee): JsonResponse
{
    $oldOrg = $employee->organization;
    $employee = $this->employeeService->transfer($employee, $request->validated());

    return response()->json([
        'success' => true,
        'message' => "Employee transferred from {$oldOrg} to {$employee->organization}",
        'data' => $employee,
    ]);
}
```

#### 4. Route

**Add to:** `routes/api/employees.php` inside the `permission:employees.edit` group:

```php
Route::post('/employees/{employee}/transfer', [EmployeeController::class, 'transfer']);
```

### What Happens Automatically After Transfer

These systems all read `employee.organization` at runtime. No code changes needed:

| System | Effect |
|---|---|
| **Payslip template** | Next payslip uses the new org's template (SMRU vs BHF layout) |
| **Health welfare** | Re-evaluated against new org's eligibility rules next payroll |
| **Inter-org advances** | Recalculated next payroll (may flip from same-org to cross-org or vice versa) |
| **Bulk payroll filter** | Employee appears under new org in next bulk payroll run |
| **Dashboard stats** | Cache invalidated by `invalidateCache()`, refreshes on next load |
| **Employee list** | Appears under new org filter immediately |

### What HR May Need to Do Manually After Transfer

| Item | Why |
|---|---|
| **Funding allocations** | Employee may need to be reassigned to grants in the new org. Existing allocations stay linked to their current grants — which may now be cross-org |
| **Action Change** | If position/department/site also changes, HR creates a separate Personnel Action |

---

## Part B: Action Change — Bug Fixes (Existing)

The Personnel Action system is already implemented on the backend. These are the bugs to fix before building the frontend.

### Bug 1: Column Name Mismatch (site_id vs work_location_id)

The migration uses `current_site_id` / `new_site_id`, but the model uses `current_work_location_id` / `new_work_location_id`.

**Fix:** Align everything to `site_id` (matching the migration and Employment model convention).

**Files to change:**

**`app/Models/PersonnelAction.php` — $fillable:**
```php
// REPLACE:
'current_work_location_id',
'new_work_location_id',

// WITH:
'current_site_id',
'new_site_id',
```

**`app/Models/PersonnelAction.php` — Relationships:**

Remove `currentWorkLocation()`, `newWorkLocation()`, and the alias methods `currentSite()`, `newSite()`. Replace with:

```php
public function currentSite(): BelongsTo
{
    return $this->belongsTo(Site::class, 'current_site_id');
}

public function newSite(): BelongsTo
{
    return $this->belongsTo(Site::class, 'new_site_id');
}
```

**`app/Models/PersonnelAction.php` — populateCurrentEmploymentData():**
```php
// REPLACE:
$this->current_work_location_id = $employment->site_id;

// WITH:
$this->current_site_id = $employment->site_id;
```

**`app/Services/PersonnelActionService.php` — EAGER_LOAD:**
```php
// REPLACE:
'currentWorkLocation',
'newWorkLocation',

// WITH:
'currentSite',
'newSite',
```

**`app/Services/PersonnelActionService.php` — handleAppointment():**
```php
// REPLACE:
'work_location_id' => $action->new_work_location_id,

// WITH:
'site_id' => $action->new_site_id,
```

**`app/Services/PersonnelActionService.php` — handleTransfer():**
```php
// REPLACE:
'work_location_id' => $action->new_work_location_id,

// WITH:
'site_id' => $action->new_site_id,
```

**`app/Http/Resources/PersonnelActionResource.php`:**
```php
// REPLACE all work_location references:
'current_site_id' => $this->current_site_id,
'new_site_id' => $this->new_site_id,

'current_site' => $this->whenLoaded('currentSite', function () {
    return [
        'id' => $this->currentSite->id,
        'name' => $this->currentSite->name,
    ];
}),
'new_site' => $this->whenLoaded('newSite', function () {
    return [
        'id' => $this->newSite->id,
        'name' => $this->newSite->name,
    ];
}),
```

**`app/Http/Requests/StorePersonnelActionRequest.php`:**
```php
// REPLACE:
'current_work_location_id' => ['nullable', 'exists:sites,id'],
'new_work_location_id' => ['nullable', 'exists:sites,id'],

// WITH:
'current_site_id' => ['nullable', 'exists:sites,id'],
'new_site_id' => ['nullable', 'exists:sites,id'],
```

### Bug 2: "Implemented" Status Never Set

**Fix:** Add `implemented_at` column.

**Migration** — add to `2025_09_25_134034_create_personnel_actions_table.php`:
```php
// After accountant_approved:
$table->timestamp('implemented_at')->nullable();
```

**Model — add to `$fillable`:**
```php
'implemented_at',
```

**Model — add to `casts()`:**
```php
'implemented_at' => 'datetime',
```

**Model — update `getStatusAttribute()`:**
```php
public function getStatusAttribute(): string
{
    if ($this->implemented_at) {
        return 'implemented';
    }

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

**Service — set `implemented_at` in `implementAction()`:**
```php
// At the end of implementAction(), after the switch block:
$personnelAction->update(['implemented_at' => now()]);
```

**Service — guard against re-implementation in `updateApproval()`:**
```php
$fresh = $personnelAction->fresh();
if ($fresh->isFullyApproved() && ! $fresh->implemented_at) {
    $this->implementAction($fresh);
}
```

**Resource — add to response:**
```php
'implemented_at' => $this->implemented_at?->toIso8601String(),
```

### Bug 3: No UpdatePersonnelActionRequest

**New file:** `app/Http/Requests/PersonnelAction/UpdatePersonnelActionRequest.php`

```php
<?php

namespace App\Http\Requests\PersonnelAction;

use App\Models\PersonnelAction;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePersonnelActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('personnel_action.update');
    }

    public function rules(): array
    {
        return [
            'employment_id' => ['sometimes', 'exists:employments,id'],
            'effective_date' => ['sometimes', 'date'],
            'action_type' => ['sometimes', 'in:'.implode(',', array_keys(PersonnelAction::ACTION_TYPES))],
            'action_subtype' => ['nullable', 'in:'.implode(',', array_keys(PersonnelAction::ACTION_SUBTYPES))],
            'is_transfer' => ['sometimes', 'boolean'],
            'transfer_type' => ['nullable', 'in:'.implode(',', array_keys(PersonnelAction::TRANSFER_TYPES))],
            'new_department_id' => ['nullable', 'exists:departments,id'],
            'new_position_id' => [
                'nullable',
                'integer',
                'exists:positions,id',
                function ($attribute, $value, $fail) {
                    if ($this->filled('new_department_id') && $value) {
                        $position = \App\Models\Position::find($value);
                        if ($position && $position->department_id != $this->new_department_id) {
                            $fail('The selected position must belong to the selected department.');
                        }
                    }
                },
            ],
            'new_site_id' => ['nullable', 'exists:sites,id'],
            'new_salary' => ['nullable', 'numeric', 'min:0'],
            'new_work_schedule' => ['nullable', 'string', 'max:255'],
            'new_report_to' => ['nullable', 'string', 'max:255'],
            'new_pay_plan' => ['nullable', 'string', 'max:255'],
            'new_phone_ext' => ['nullable', 'string', 'max:20'],
            'new_email' => ['nullable', 'email', 'max:255'],
            'comments' => ['nullable', 'string'],
            'change_details' => ['nullable', 'string'],
        ];
    }
}
```

**Update controller to use it:**
```php
use App\Http\Requests\PersonnelAction\UpdatePersonnelActionRequest;

public function update(UpdatePersonnelActionRequest $request, PersonnelAction $personnelAction): JsonResponse
{
    if ($personnelAction->implemented_at) {
        return response()->json([
            'success' => false,
            'message' => 'Cannot update an implemented personnel action.',
        ], 422);
    }

    $personnelAction = $this->personnelActionService->update(
        $personnelAction,
        array_merge($request->validated(), ['updated_by' => auth()->id()])
    );

    return response()->json([
        'success' => true,
        'message' => 'Personnel action updated successfully',
        'data' => $personnelAction,
    ]);
}
```

### Bug 4: No Delete Endpoint

**Route — add to `routes/api/personnel_actions.php`:**
```php
Route::delete('/personnel-actions/{personnelAction}', [PersonnelActionController::class, 'destroy']);
```

**Controller method:**
```php
public function destroy(PersonnelAction $personnelAction): JsonResponse
{
    if ($personnelAction->implemented_at) {
        return response()->json([
            'success' => false,
            'message' => 'Cannot delete an implemented personnel action.',
        ], 422);
    }

    $personnelAction->delete();

    return response()->json([
        'success' => true,
        'message' => 'Personnel action deleted successfully',
    ]);
}
```

### Bug 5: handleTransfer writes to wrong Employment column

Already covered in Bug 1 fix — `work_location_id` → `site_id`.

Also add `pass_probation_salary` support to `handleTransfer()` (currently missing — a transfer with salary change would lose the salary):

```php
private function handleTransfer(Employment $employment, PersonnelAction $action): void
{
    $updateData = array_filter([
        'department_id' => $action->new_department_id,
        'site_id' => $action->new_site_id,
        'position_id' => $action->new_position_id,
        'pass_probation_salary' => $action->new_salary,
        'updated_by' => Auth::user()?->name ?? 'Personnel Action',
    ], fn ($value) => $value !== null);

    if (! empty($updateData)) {
        $employment->update($updateData);
    }
}
```

---

## Detailed Implementation Todo List

---

### Phase 1: Transfer — Form Request & Validation

- [x] **1.1** Create `app/Http/Requests/Employee/TransferEmployeeRequest.php`
  - `new_organization`: required, in `SMRU,BHF`
  - `effective_date`: required, date
  - `reason`: nullable, string, max 500
  - `withValidator()`: check org differs from current, check staff_id uniqueness in target org
  - `authorize()`: check `employees.edit` permission

### Phase 2: Transfer — Service Layer

- [x] **2.1** Add `transfer(Employee $employee, array $validated): Employee` to `app/Services/EmployeeDataService.php`
  - Save `$oldOrganization` before update
  - Call `$employee->update(['organization' => $newOrganization, 'updated_by' => ...])`
  - Call `$employee->logActivity('transferred', [...], "Organization transfer: ...")` with from/to/effective_date/reason
  - Call `$this->invalidateCache()` to clear `employee_statistics` cache
  - Call `$this->notifyAction('transferred', $employee)` for real-time notification
  - Refresh and return employee

### Phase 3: Transfer — Controller & Route

- [x] **3.1** Add `transfer(TransferEmployeeRequest $request, Employee $employee): JsonResponse` to `app/Http/Controllers/Api/V1/EmployeeController.php`
  - Import `TransferEmployeeRequest`
  - Capture `$oldOrg` before calling service
  - Return success response with "Employee transferred from X to Y" message
- [x] **3.2** Add route to `routes/api/employees.php`
  - `Route::post('/employees/{employee}/transfer', [EmployeeController::class, 'transfer'])` inside the `permission:employees.edit` group
  - Must be placed BEFORE the `{employee}` wildcard routes to avoid route conflicts

### Phase 4: Action Change — Fix Column Name Mismatch (Bug 1)

- [x] **4.1** Update `app/Models/PersonnelAction.php` — `$fillable`
  - Replace `'current_work_location_id'` with `'current_site_id'`
  - Replace `'new_work_location_id'` with `'new_site_id'`
- [x] **4.2** Update `app/Models/PersonnelAction.php` — relationships
  - Remove `currentWorkLocation()` method
  - Remove `newWorkLocation()` method
  - Remove old `currentSite()` alias that calls `currentWorkLocation()`
  - Remove old `newSite()` alias that calls `newWorkLocation()`
  - Add new `currentSite()`: `belongsTo(Site::class, 'current_site_id')`
  - Add new `newSite()`: `belongsTo(Site::class, 'new_site_id')`
- [x] **4.3** Update `app/Models/PersonnelAction.php` — `populateCurrentEmploymentData()`
  - Replace `$this->current_work_location_id = $employment->site_id` with `$this->current_site_id = $employment->site_id`
  - Change eager load from `'workLocation'` to `'site'`
- [x] **4.4** Update `app/Services/PersonnelActionService.php` — `EAGER_LOAD` constant
  - Replace `'currentWorkLocation'` with `'currentSite'`
  - Replace `'newWorkLocation'` with `'newSite'`
- [x] **4.5** Update `app/Services/PersonnelActionService.php` — `handleAppointment()`
  - Replace `'work_location_id' => $action->new_work_location_id` with `'site_id' => $action->new_site_id`
- [x] **4.6** Update `app/Services/PersonnelActionService.php` — `handleTransfer()`
  - Replace `'work_location_id' => $action->new_work_location_id` with `'site_id' => $action->new_site_id`
  - Add `'pass_probation_salary' => $action->new_salary` (currently missing — salary changes during internal transfer are silently dropped)
- [x] **4.7** Update `app/Http/Resources/PersonnelActionResource.php`
  - Replace `'current_work_location_id' => $this->current_work_location_id` with `'current_site_id' => $this->current_site_id`
  - Replace `'new_work_location_id' => $this->new_work_location_id` with `'new_site_id' => $this->new_site_id`
  - Replace `'current_work_location' => $this->whenLoaded('currentWorkLocation', ...)` with `'current_site' => $this->whenLoaded('currentSite', ...)`
  - Replace `'new_work_location' => $this->whenLoaded('newWorkLocation', ...)` with `'new_site' => $this->whenLoaded('newSite', ...)`
- [x] **4.8** Update `app/Http/Requests/StorePersonnelActionRequest.php`
  - Replace `'current_work_location_id'` rule key with `'current_site_id'`
  - Replace `'new_work_location_id'` rule key with `'new_site_id'`

### Phase 5: Action Change — Add `implemented_at` (Bug 2)

- [x] **5.1** Update migration `database/migrations/2025_09_25_134034_create_personnel_actions_table.php`
  - Add `$table->timestamp('implemented_at')->nullable()` after the approval boolean columns
- [x] **5.2** Update `app/Models/PersonnelAction.php` — `$fillable`
  - Add `'implemented_at'` to the `$fillable` array
- [x] **5.3** Update `app/Models/PersonnelAction.php` — `casts()`
  - Add `'implemented_at' => 'datetime'`
- [x] **5.4** Update `app/Models/PersonnelAction.php` — `getStatusAttribute()`
  - Add `if ($this->implemented_at) { return 'implemented'; }` as the FIRST check, before `isFullyApproved()`
- [x] **5.5** Update `app/Services/PersonnelActionService.php` — `implementAction()`
  - Add `$personnelAction->update(['implemented_at' => now()])` at the end, after the switch block and before cache clearing
- [x] **5.6** Update `app/Services/PersonnelActionService.php` — `updateApproval()`
  - Change the fully-approved check to: `if ($fresh->isFullyApproved() && ! $fresh->implemented_at)`
  - This prevents re-implementation if an approval is toggled off and back on
- [x] **5.7** Update `app/Http/Resources/PersonnelActionResource.php`
  - Add `'implemented_at' => $this->implemented_at?->toIso8601String()`

### Phase 6: Action Change — Create UpdatePersonnelActionRequest (Bug 3)

- [x] **6.1** Create `app/Http/Requests/PersonnelAction/UpdatePersonnelActionRequest.php`
  - All fields use `'sometimes'` instead of `'required'`
  - No `'after_or_equal:today'` on `effective_date` (allow correcting historical records)
  - Same position-department cross-validation as Store request
  - `authorize()` checks `personnel_action.update` permission
- [x] **6.2** Update `app/Http/Controllers/Api/V1/PersonnelActionController.php` — `update()` method
  - Import and use `UpdatePersonnelActionRequest` instead of `StorePersonnelActionRequest`
  - Add guard: return 422 if `$personnelAction->implemented_at` is set ("Cannot update an implemented personnel action")

### Phase 7: Action Change — Add Delete Endpoint (Bug 4)

- [x] **7.1** Add `destroy()` method to `app/Http/Controllers/Api/V1/PersonnelActionController.php`
  - Guard: return 422 if `$personnelAction->implemented_at` is set ("Cannot delete an implemented personnel action")
  - Call `$personnelAction->delete()` (soft delete via `SoftDeletes` trait)
  - Return success response
- [x] **7.2** Add DELETE route to `routes/api/personnel_actions.php`
  - `Route::delete('/personnel-actions/{personnelAction}', [PersonnelActionController::class, 'destroy'])`
  - Inside the `permission:employees.edit` group

### Phase 8: Migration & Formatting

- [x] **8.1** Run `php artisan migrate:fresh --seed`
  - Verify `personnel_actions` table has `implemented_at`, `current_site_id`, `new_site_id` columns
  - Verify no `current_work_location_id` or `new_work_location_id` columns exist
- [x] **8.2** Run `vendor/bin/pint --dirty` to format all changed files

### Phase 9: Verification

#### Transfer verification
- [x] **9.1** Test happy path: `POST /api/v1/employees/{id}/transfer` with `{"new_organization": "BHF", "effective_date": "2026-03-10"}`
  - Verify `employee.organization` changed to `BHF`
  - Verify response message includes from/to orgs
- [x] **9.2** Test same-org rejection: transfer SMRU employee to SMRU
  - Expect 422 with "Employee is already in SMRU"
- [x] **9.3** Test staff_id collision: transfer employee whose staff_id already exists in target org
  - Expect 422 with "Staff ID X already exists in BHF"
- [x] **9.4** Test audit trail: query `activity_logs` for `action = 'transferred'`
  - Verify entry exists with `from_organization`, `to_organization`, `effective_date`, `reason` in properties
  - Verify a second entry exists (from `LogsActivity` trait) with `action = 'updated'` showing old/new organization diff
- [x] **9.5** Test cache invalidation: verify `employee_statistics` cache is cleared after transfer

#### Action Change verification
- [x] **9.6** Test personnel action creation: `POST /api/v1/personnel-actions` with `new_site_id` field
  - Verify `new_site_id` is stored correctly (was silently failing before with `new_work_location_id`)
- [x] **9.7** Test full approval flow: approve all 4 approvals
  - Verify `implemented_at` timestamp is set
  - Verify `status` returns `'implemented'`
  - Verify employment record is updated with the new values (position, dept, site, salary)
- [x] **9.8** Test re-approval guard: toggle an approval off then back on for an already-implemented action
  - Verify `implementAction()` does NOT run again
- [x] **9.9** Test update block: `PUT /api/v1/personnel-actions/{id}` on an implemented action
  - Expect 422 "Cannot update an implemented personnel action"
- [x] **9.10** Test delete block: `DELETE /api/v1/personnel-actions/{id}` on an implemented action
  - Expect 422 "Cannot delete an implemented personnel action"
- [x] **9.11** Test delete success: `DELETE /api/v1/personnel-actions/{id}` on a pending action
  - Verify soft-deleted (still in DB with `deleted_at` set)
- [x] **9.12** Test handleTransfer salary: create a transfer-type action with `new_salary` set
  - Approve all 4 → verify `employment.pass_probation_salary` is updated (was silently dropped before)
