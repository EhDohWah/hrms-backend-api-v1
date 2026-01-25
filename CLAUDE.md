# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

HR Management System backend API built with Laravel 12, using SQL Server database and Sanctum authentication. This is a REST API serving employee management, payroll, grants, leave management, and related HR functions.

## Development Philosophy

**Write for maintainability, not complexity.** You are a senior Laravel developer, but you're writing code for future junior developers to maintain and debug. Follow these principles:

- **Simplicity over cleverness** - Prefer straightforward solutions that junior developers can understand at a glance
- **Explicit over implicit** - Make your intentions clear through descriptive variable names, method names, and comments when necessary
- **Consistency over innovation** - Follow established patterns in the codebase rather than introducing new approaches
- **Readable over compact** - Choose clarity over brevity; a few extra lines that improve understanding are worth it
- **Future-proof architecture** - Structure code so bugs can be isolated and fixed without cascading changes

When adding features or fixing bugs:
1. Keep the existing architecture and patterns consistent
2. Add inline comments for complex business logic (especially payroll/tax calculations)
3. Write self-documenting code with clear method and variable names
4. Avoid over-abstraction - don't create layers that hide what the code actually does
5. Think: "Can a junior developer debug this at 2 AM?"

## Common Commands

```bash
# Development server (runs server, queue, logs, vite concurrently)
composer run dev

# Run specific services individually
php artisan serve                    # API server
php artisan queue:listen --tries=1   # Queue worker
php artisan reverb:start             # WebSocket server (Reverb)

# Testing with Pest
php artisan test                              # Run all tests
php artisan test tests/Feature/ExampleTest.php  # Run specific file
php artisan test --filter=testName            # Filter by test name

# Code formatting (required before committing)
vendor/bin/pint --dirty               # Format only changed files
vendor/bin/pint                       # Format all files

# Database
php artisan migrate
php artisan db:seed

# Generate API documentation
php artisan l5-swagger:generate       # OpenAPI docs at /api/documentation
```

## Architecture

### API Versioning
Routes are versioned under `/api/v1/` prefix. Route files are split by domain in `routes/api/`:
- `admin.php` - User/role management
- `employees.php` - Employee CRUD and related resources
- `grants.php` - Grant management and imports
- `payroll.php` - Payroll calculations, tax settings
- `employment.php` - Employment records, probation
- `personnel_actions.php` - Personnel action tracking
- `administration.php` - Sites, departments, positions
- `benefit-settings.php` - Benefits configuration
- `uploads.php` - File upload endpoints

### Service Layer
Business logic lives in `app/Services/`:
- `PayrollService` - Payroll calculations with tax computations
- `TaxCalculationService` - Tax bracket calculations
- `ProbationTransitionService` - Automated probation status changes (scheduled daily)
- `PersonnelActionService` - Personnel action tracking
- `FundingAllocationService` / `EmployeeFundingAllocationService` - Grant allocation to employees

### Key Patterns
- **Form Requests** for validation (`app/Http/Requests/`)
- **API Resources** for response transformation (`app/Http/Resources/`)
- **Excel Imports** via Maatwebsite/Excel (`app/Imports/`) for bulk data uploads
- **Spatie Permission** for role-based access control with `DynamicModulePermission` middleware
- **Soft deletes** with Spatie's `laravel-deleted-models` for recycle bin functionality

### Queue System
Background job processing for Excel imports and bulk operations:
- **Development**: `composer run dev` includes queue worker automatically
- **Production**: Use Supervisor to manage queue workers (see `docs/queue-setup.md`)
- All imports use `$import->queue($file)` pattern with the default queue
- Queue driver configured via `QUEUE_CONNECTION` env variable (database recommended)

### Real-time Features
Uses Laravel Reverb for WebSocket broadcasting:
- `EmployeeActionEvent` - Employee changes
- `PayrollBulkProgress` - Bulk payroll processing progress
- `UserPermissionsUpdated` - Permission changes

### Database
- Primary: SQL Server (`sqlsrv` driver)
- 40+ tables covering employees, employment, payroll, grants, leave, travel, interviews, etc.
- Key relationships: Employee has many Employments, each Employment tracks grant allocations via `EmployeeFundingAllocation`

## Testing

All tests use Pest and are in `tests/Feature/` and `tests/Unit/`. Use factories when creating test data. Run relevant tests after changes:

```bash
php artisan test --filter=EmployeeApi    # Run tests matching pattern
```

## Code Style

- PSR-12 with Laravel Pint
- PHP 8.2+ constructor property promotion
- Type hints on all method parameters and returns
- Form Requests for all controller validation
- API Resources for all JSON responses
- Use `Model::query()` instead of `DB::`

## REST API Naming Standards

This project follows strict RESTful naming conventions for controllers, form requests, and API resources. See `docs/CONTROLLER_NAMING_STANDARDS.md` for full details.

### Controller Methods

#### Standard CRUD Methods

| Action | Method Name | HTTP Method | Route Example |
|--------|-------------|-------------|---------------|
| List all | `index()` | GET | `/employees` |
| Show one | `show($id)` | GET | `/employees/{id}` |
| Create | `store()` | POST | `/employees` |
| Update | `update($id)` | PUT/PATCH | `/employees/{id}` |
| Delete | `destroy($id)` | DELETE | `/employees/{id}` |

#### Custom Action Methods (Verb-First Pattern)

| Action | Method Name | HTTP Method | Route Example |
|--------|-------------|-------------|---------------|
| Dropdown data | `options()` | GET | `/employees/options` |
| Bulk delete | `destroyBatch($ids)` | DELETE | `/employees/batch/{ids}` |
| Export PDF | `exportPdf()` | POST | `/employees/export-pdf` |
| Export Excel | `exportExcel()` | GET | `/employees/export-excel` |
| Attach related | `attachGrantItem()` | POST | `/employees/{id}/grant-items` |
| Detach related | `detachGrantItem()` | DELETE | `/employees/{id}/grant-items/{itemId}` |
| Partial update | `updateBasicInfo()` | PUT | `/employees/{id}/basic-info` |
| Alternative lookup | `showByStaffId()` | GET | `/employees/staff-id/{staff_id}` |

### Form Request Naming

Form Requests follow the pattern: `{Action}{Entity}Request`

| Action Type | Naming Pattern | Example |
|-------------|----------------|---------|
| Create/Store | `Store{Entity}Request` | `StoreEmployeeRequest` |
| Update | `Update{Entity}Request` | `UpdateEmployeeRequest` |
| Dropdown options | `Options{Entity}Request` | `OptionsDepartmentRequest` |
| Export reports | `Export{Entity}Request` | `ExportLeaveReportRequest` |
| Import data | `Import{Entity}Request` | `ImportGrantRequest` |
| Bulk operations | `Batch{Action}{Entity}Request` | `BatchDestroyEmployeeRequest` |

### API Resource Naming

API Resources follow the pattern: `{Model}Resource` or `{Model}{Variant}Resource`

| Type | Naming Pattern | Example |
|------|----------------|---------|
| Standard resource | `{Model}Resource` | `EmployeeResource` |
| Detail view | `{Model}DetailResource` | `EmployeeDetailResource` |
| List/Collection | `{Model}ListResource` | `EmployeeListResource` |
| Dropdown options | `{Model}OptionResource` | `DepartmentOptionResource` |

### Anti-Patterns to Avoid

```php
// WRONG - Don't add entity names to standard CRUD methods
public function storeEmployee() {}        // ✓ Use store()
public function deleteGrant() {}          // ✓ Use destroy()
public function createPayroll() {}        // ✓ Use store()

// WRONG - Don't use "get" prefix (reads like a getter, not an action)
public function getUsers() {}             // ✓ Use index()
public function getAllWidgets() {}        // ✓ Use index()
public function getById($id) {}           // ✓ Use show()
public function getUserRoles() {}         // ✓ Use roles() or index() in RoleController

// WRONG - Don't use "list" prefix (redundant with index)
public function listEmployees() {}        // ✓ Use index()
public function listDepartmentOptions(){} // ✓ Use options()

// WRONG - Don't use "delete" for method names (use "destroy")
public function deleteSelected() {}       // ✓ Use destroyBatch()
public function deleteEmployee() {}       // ✓ Use destroy()

// WRONG - Don't use inconsistent Form Request naming
class PersonnelActionRequest {}           // ✓ Use StorePersonnelActionRequest
class ListDepartmentOptionsRequest {}     // ✓ Use OptionsDepartmentRequest
class InterviewReportRequest {}           // ✓ Use ExportInterviewReportRequest
```

### Controller Size Guidelines

- Max 10-12 public methods per controller
- Max 500-600 lines per controller
- Extract sub-resources to separate controllers when they have 3+ methods
