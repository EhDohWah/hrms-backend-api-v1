## HRMS Backend Architecture

### Overview
The HRMS backend is a Laravel 11 API designed as a **Data Entry and Display System** with **Report Export** capabilities. It digitizes paper-based HR processes into a database for storage, viewing, searching, and reporting. The system uses modular route files, service classes, and strong authentication/authorization to manage employee data, employment records, grants, payroll information, and personnel actions.

### System Purpose & Approach
This HRMS backend serves as a **digital repository** for HR data that originates from paper forms and manual processes:

- **Data Entry Focus**: Convert completed paper forms into structured database records
- **Display & Search**: Provide web interfaces to view, filter, and search HR data
- **Report Export**: Generate Excel/PDF reports from stored data
- **No Workflow Automation**: Business logic and approvals happen offline; system stores final states
- **Simple CRUD Operations**: Create, Read, Update, Delete operations without complex state management

### Tech Stack & Key Packages
- PHP 8.2.29, Laravel 11
- Authentication: Laravel Sanctum 4.x
- Authorization: Spatie Permission
- Import/Export: Maatwebsite Excel
- API Docs: L5 Swagger (OpenAPI)

### Laravel 11 App Bootstrap
- `bootstrap/app.php` configures routing, middleware aliases, and exception rendering:
  - API prefix: `api/v1`
  - Middleware aliases: `role`, `permission`, `role_or_permission`, `cors`
  - Exception JSON response for API requests with a standardized envelope
- `bootstrap/providers.php` registers core providers:
  - `AppServiceProvider`, `CacheServiceProvider`, `EventServiceProvider`, `RateLimitServiceProvider`
  - `Laravel\Sanctum\SanctumServiceProvider`, `Spatie\Permission\PermissionServiceProvider`

### Routing & Versioning
- API prefix: all routes are served under `/api/v1`.
- Modular route files included from `routes/api.php`:
  - `api/admin.php`
  - `api/employees.php`
  - `api/grants.php`
  - `api/payroll.php`
  - `api/employment.php`
- Authentication & RBAC:
  - Most groups are wrapped in `Route::middleware('auth:sanctum')`
  - Endpoints use `permission:*` middleware to enforce granular access (Spatie roles/permissions)

### Domain Modules (Data Entry & Display)
- **Employees** (`routes/api/employees.php`): Employee personal data, children, beneficiaries, education, language skills, training records, tree search with employment integration
- **Employment** (`routes/api/employment.php`): Employment contracts, departments, positions, work locations, interview records, job offers, travel requests
- **Leave Management** (`routes/api/employment.php`): Leave requests, leave balances, leave types, approval records (includes balance calculations for data integrity)
- **Grants & Funding** (`routes/api/grants.php`): Grant information, position slot allocations, organizational funding data
- **Payroll & Tax** (`routes/api/payroll.php`): Payroll records, inter‑subsidiary advances, tax bracket data, tax calculations
- **Personnel Actions** (`routes/api/personnel_actions.php`): Personnel action forms, promotions, transfers, salary changes

Each module provides:
- **CRUD APIs** for data entry and updates
- **List/Search APIs** with filtering and pagination
- **Export APIs** for report generation
- **Import APIs** for bulk data entry (Excel)

### Authentication & Authorization
- Sanctum bearer tokens are created on login in `app/Http/Controllers/Api/AuthController.php` and returned to the client. Routes are protected with `auth:sanctum`.
- Role/permission checks via Spatie Permission:
  - Middleware aliases registered in `bootstrap/app.php`
  - Route middleware like `permission:employee.read`, `permission:payroll.create` guard access per endpoint

### Error Handling
- `bootstrap/app.php` provides a JSON fallback for exceptions during API requests:
  - Envelope: `{ success: false, message: 'Something went wrong', error: <message> }`

### Services (Core Business Logic)
- `app/Services/PayrollService.php`
  - End‑to‑end payroll processing for an employee across funding allocations
  - Creates `Payroll` records, and auto‑creates `InterSubsidiaryAdvance` when fund subsidiary ≠ employee subsidiary
  - Encapsulates 13 required payroll items (gross salary, FTE salary, compensation/refund, 13th month, PVD/Saving, SSF, health welfare, income tax, net salary, total salary, total PVD/Saving)
  - Handles probation mid‑month pro‑rating and annual increase logic
- `app/Services/TaxCalculationService.php`
  - Caches tax settings/brackets and computes compliant Thai taxes
  - Exposes helpers to clear tax cache when settings change
- `app/Services/FundingAllocationService.php`
  - Funding allocation utilities (grant/org funded calculations and helpers)
- `app/Services/CacheManagerService.php`
  - Centralized cache utilities with tag support, TTL constants, warming, and selective clearing by pattern
- `app/Services/PaginationMetricsService.php`
  - Tracks/reads cached pagination and usage metrics
- `app/Services/UniversalRestoreService.php`
  - Cross‑module restore helpers

### Payroll Calculation Highlights
1) Pro‑rated salary for probation transitions
   - If probation ends mid‑month, split the month into probation days vs position days and pay each portion at its respective daily rate
2) Annual 1% increase (after 365 working days)
3) FTE/LOE application per allocation
4) 13th month accrual when eligible (≥ 6 months)
5) Deductions: PVD/Saving (7.5% employee), Social Security (5% capped 750), Health & Welfare (tiered), Thai tax via `TaxCalculationService`
6) Inter‑subsidiary advances auto‑creation using hub grants when funding subsidiary ≠ employee subsidiary

### Caching Strategy
- Central utilities in `CacheManagerService`:
  - TTL presets: 15m, 60m (default), 24h
  - Tag‑based remember/flush, warm cache, clear by pattern (optimized for Redis)
  - Related cache invalidation to prevent stale reads across models
- Usage examples in codebase:
  - Tax configuration caches in `TaxSetting` model and `TaxCalculationService`
  - Employee and leave statistics caches in models/controllers
  - Import process state (errors, counts, snapshots) cached during Excel imports
  - Pagination metrics via middleware/service

### Events, Observers, Notifications
- Events: `app/Events/*` (e.g., `EmployeeImportCompleted`)
- Observers: `app/Observers/*` (`EmployeeObserver`, `EmploymentObserver`, `JobOfferObserver`, `CacheInvalidationObserver`)
- Notifications: queued notifications for import lifecycle (`ImportedCompletedNotification`, `ImportFailedNotification`)

### Queues & Background Jobs
- Default queue connection: `database` (`config/queue.php`)
- Dedicated `import` queue for long‑running imports
- Maatwebsite Excel imports queued via `Excel::queueImport(...)->onQueue('import')`
- Notifications implement `ShouldQueue`

### API Documentation & Security Schemes
- L5 Swagger is configured; security scheme includes `sanctum` for bearer tokens
- Many controllers include OpenAPI annotations for request/response models and authorization

### Request Lifecycle (API)
1) Client sends bearer token (Sanctum)
2) `auth:sanctum` authenticates the user
3) `permission:*` middleware verifies RBAC per endpoint
4) Controller delegates to service classes (e.g., payroll/tax)
5) Responses return structured JSON payloads; errors return standardized JSON from global exception handler

### Data Access & Relationships
- Preference for Eloquent models with explicit relationship loading and eager loading patterns to prevent N+1 queries
- Query building favors model queries over raw DB calls; transactions used around payroll creation

### Testing & Quality
- Feature tests under `tests/Feature` (e.g., `TaxApiTest`) use Sanctum for authenticated requests
- Code style: Laravel Pint is recommended; run `vendor/bin/pint --dirty` before finalizing changes

### Deployment & Operations
- Configure `.env` for `QUEUE_CONNECTION`, DB credentials, cache driver (Redis recommended for tag support), Sanctum config
- Start a queue worker for `database` driver; ensure `import` queue is processed for large Excel imports
- CORS is configured via `config/cors.php`

### Extensibility Guidelines for Data Entry System
- **New Data Entities**: Create migration, model, controller, form request, and routes following existing patterns
- **CRUD Operations**: Use standard REST patterns (GET, POST, PUT/PATCH, DELETE) for all data management
- **Export Features**: Add export endpoints using Maatwebsite Excel for report generation
- **Import Features**: Add import endpoints for bulk data entry from Excel files
- **Simple Services**: Keep business logic minimal - focus on data validation and storage
- **Permissions**: Use standard CRUD permissions (create, read, update, delete, import, export)
- **No Complex Workflows**: Avoid state machines, approval workflows, or automated business processes

### Notable Files
- Routing: `routes/api.php`, `routes/api/*.php`
- Bootstrap: `bootstrap/app.php`, `bootstrap/providers.php`
- Auth: `app/Http/Controllers/Api/AuthController.php`, `config/sanctum.php`, `config/auth.php`
- Permissions: Spatie middleware aliases in `bootstrap/app.php`, use on routes
- Services: `app/Services/*`
- Observers: `app/Observers/*`
- Queue: `config/queue.php`
- Docs: existing domain docs under `docs/*` (payroll, grants, employment, travel, leave management, personnel actions, etc.)

---

### Key Architectural Principle

**This HRMS backend is fundamentally a data digitization system**, not a workflow management system. It assumes:

1. **Paper-based processes** are the source of truth for HR decisions
2. **Manual approvals** happen offline before data entry
3. **Database storage** preserves the final state of HR actions
4. **Reporting capabilities** extract data for analysis and compliance
5. **Simple CRUD operations** without complex business logic automation

This architectural decision keeps the system simple, reliable, and focused on its core purpose: **digitizing and managing HR data efficiently**.

---
This document reflects the current codebase (Laravel 11) and is intended to guide contributors and integrators on how the backend is structured, secured, and extended.


