# Controller Naming Standards

**Version:** 2.0
**Last Updated:** 2026-01-24
**Applies To:** HRMS Backend API
**Refactoring Status:** ✅ Complete (Phases 1-4)

---

## Purpose

This document defines the naming conventions for Laravel controllers and their methods in the HRMS Backend API. Following these standards ensures code consistency and makes the codebase maintainable for junior developers.

## Refactoring Summary

The following controllers were refactored to follow these standards:

| Phase | Controller | Changes |
|-------|------------|---------|
| 1 | GrantController | `storeGrant`→`store`, `updateGrant`→`update`, `deleteGrant`→`destroy` |
| 2 | GrantItemController | New controller extracted from GrantController (5 methods) |
| 3 | LeaveTypeController | New controller extracted from LeaveManagementController |
| 3 | LeaveBalanceController | New controller extracted from LeaveManagementController |
| 3 | LeaveRequestController | New controller extracted from LeaveManagementController |
| 4 | EmployeeController | `updateEmployeeBasicInformation`→`updateBasicInfo`, etc. |
| 4 | DashboardController | `getAllWidgets`→`index`, `getUserDashboard`→`show`, etc. |
| 4 | LookupController | `getLookupLists`→`lists`, `getTypes`→`types`, etc. |

---

## 1. RESTful Resource Methods

Use these exact method names for standard CRUD operations:

| Method | HTTP Verb | Route | Purpose | Example |
|--------|-----------|-------|---------|---------|
| `index()` | GET | `/resources` | List all resources | `GET /employees` |
| `show($id)` | GET | `/resources/{id}` | Show single resource | `GET /employees/1` |
| `store()` | POST | `/resources` | Create new resource | `POST /employees` |
| `update($id)` | PUT/PATCH | `/resources/{id}` | Update resource | `PUT /employees/1` |
| `destroy($id)` | DELETE | `/resources/{id}` | Delete resource | `DELETE /employees/1` |

### Rules

```php
// CORRECT
class EmployeeController extends Controller
{
    public function index() {}    // List employees
    public function show($id) {}  // Show employee
    public function store() {}    // Create employee
    public function update($id) {} // Update employee
    public function destroy($id) {} // Delete employee
}

// INCORRECT - Don't add entity names
class EmployeeController extends Controller
{
    public function getEmployees() {}     // BAD: Use index()
    public function getById($id) {}       // BAD: Use show()
    public function storeEmployee() {}    // BAD: Use store()
    public function updateEmployee($id) {} // BAD: Use update()
    public function deleteEmployee($id) {} // BAD: Use destroy()
}
```

---

## 2. Custom Method Naming Pattern

**Format:** `{verb}{Object}{Context?}()`

### Approved Verbs by Category

#### Data Retrieval
| Verb | Usage | Example |
|------|-------|---------|
| `search` | Find resources by criteria | `searchByStaffId()` |
| `filter` | Apply filters to list | `filterByDepartment()` |
| `find` | Locate specific resource | `findByCode()` |

#### Data Manipulation
| Verb | Usage | Example |
|------|-------|---------|
| `calculate` | Perform calculations | `calculateMonthlyPayroll()` |
| `generate` | Create output/reports | `generateReport()` |
| `process` | Execute workflow | `processPayrollBatch()` |
| `transform` | Convert data format | `transformToExcel()` |

#### State Changes
| Verb | Usage | Example |
|------|-------|---------|
| `approve` | Approve request | `approveLeaveRequest()` |
| `reject` | Reject request | `rejectLeaveRequest()` |
| `activate` | Enable resource | `activateUser()` |
| `deactivate` | Disable resource | `deactivateUser()` |
| `toggle` | Switch state | `toggleStatus()` |
| `acknowledge` | Confirm receipt | `acknowledgeResignation()` |

#### I/O Operations
| Verb | Usage | Example |
|------|-------|---------|
| `export` | Output to file | `exportToExcel()` |
| `import` | Input from file | `importFromExcel()` |
| `upload` | Receive file | `uploadProfilePicture()` |
| `download` | Send file | `downloadTemplate()` |

#### Bulk Operations
| Verb | Usage | Example |
|------|-------|---------|
| `deleteSelected` | Delete multiple | `deleteSelected()` |
| `bulkUpdate` | Update multiple | `bulkUpdate()` |
| `bulkApprove` | Approve multiple | `bulkApprove()` |

---

## 3. Special Method Patterns

### Dropdown/Options Data
```php
// Use options() for dropdown data
public function options()
{
    return response()->json([
        'data' => Model::select('id', 'name')->get()
    ]);
}
```

### Partial Updates
```php
// Pattern: update{Section}Info()
public function updateBasicInfo($id) {}
public function updateContactInfo($id) {}
public function updatePersonalInfo($id) {}
public function updateBankInfo($id) {}
```

### Statistics/Reports
```php
// Be specific about what statistics
public function calculateDepartmentStats() {}  // GOOD
public function generateMonthlyReport() {}     // GOOD
public function getStatistics() {}              // BAD - too vague
```

---

## 4. Anti-Patterns to Avoid

### Redundant Entity Names
```php
// In EmployeeController
public function storeEmployee() {}  // BAD
public function store() {}          // GOOD

// In GrantController
public function deleteGrant() {}    // BAD
public function destroy() {}        // GOOD
```

### Redundant "get" Prefix
```php
public function getUser() {}        // BAD
public function user() {}           // GOOD (or show())

public function getAllWidgets() {}  // BAD
public function index() {}          // GOOD
```

### Redundant "do/perform" Prefix
```php
public function doImport() {}       // BAD
public function import() {}         // GOOD

public function performCalculation() {} // BAD
public function calculate() {}          // GOOD
```

### Duplicate Methods
```php
// If you have show($id), don't add:
public function getById($id) {}     // BAD - duplicate
public function getByCode($code) {} // BAD - use index with filter

// Instead, use query parameters on index():
// GET /resources?code=ABC123
```

---

## 5. Route Naming Conventions

### Resource Routes
```php
// Use apiResource for standard CRUD
Route::apiResource('employees', EmployeeController::class);
// Creates: index, store, show, update, destroy

// Add custom routes explicitly
Route::delete('employees/delete-selected', [EmployeeController::class, 'deleteSelected']);
Route::get('employees/search', [EmployeeController::class, 'search']);
```

### Sub-Resource Routes
```php
// Sub-resources should have their own controller
// BAD: In GrantController
Route::get('grants/items', [GrantController::class, 'getGrantItems']);

// GOOD: Separate controller
Route::apiResource('grant-items', GrantItemController::class);
```

### Route Names
```php
// Use dot notation
Route::get('/employees', [...])
    ->name('employees.index');

Route::get('/employees/{id}', [...])
    ->name('employees.show');

Route::delete('/employees/delete-selected', [...])
    ->name('employees.deleteSelected');
```

---

## 6. Controller Organization

### When to Create a New Controller

Create a new controller when:
1. Managing a distinct database entity
2. Sub-resource has 3+ methods
3. Controller exceeds 500 lines
4. Functionality is reusable across modules

### Controller Size Guidelines

| Metric | Guideline |
|--------|-----------|
| Methods | Max 10-12 per controller |
| Lines | Max 500-600 lines |
| Responsibilities | Single entity/concern |

---

## 7. Quick Reference Card

```
+------------------+-------------------+----------------------+
| Action           | Method Name       | HTTP                 |
+------------------+-------------------+----------------------+
| List all         | index()           | GET /resources       |
| Show one         | show($id)         | GET /resources/{id}  |
| Create           | store()           | POST /resources      |
| Update           | update($id)       | PUT /resources/{id}  |
| Delete           | destroy($id)      | DELETE /resources/{id}|
+------------------+-------------------+----------------------+
| Dropdown data    | options()         | GET /resources/options|
| Bulk delete      | deleteSelected()  | DELETE /resources/... |
| Export           | export()          | GET /resources/export |
| Import           | import()          | POST /resources/import|
| Partial update   | updateXxxInfo()   | PATCH /resources/{id}/xxx |
| Status change    | approveXxx()      | POST /resources/{id}/approve |
+------------------+-------------------+----------------------+
```

---

## 8. Migration Checklist

When refactoring existing methods:

- [ ] Update method name in controller
- [ ] Update route definition
- [ ] Update any internal calls to the method
- [ ] Update frontend API calls
- [ ] Add backward compatibility route if URL changed
- [ ] Update API documentation
- [ ] Update tests
- [ ] Add deprecation headers to old routes

---

*This document should be reviewed during code review for all new controllers.*
