# Controller Refactoring Guide

> **Purpose**: Step-by-step prompt for refactoring any fat HRMS controller into clean architecture.
> **Pattern**: Thin Controller + Service + API Resources + Custom Exceptions + Route Model Binding
> **First applied to**: `EmploymentController` (1,060 → 538 lines)

---

## When to Refactor

A controller is a candidate when it has:

- **>300 lines** of code
- Inline business logic (date calculations, DB queries, cross-model validation)
- Duplicated query building across methods
- Direct model creation inside `DB::beginTransaction()` blocks
- Inline `$request->validate([...])` in index/list methods

---

## Architecture Overview

```
Request → Route (model binding) → Controller (thin) → Service (logic) → Model
                                       ↓
                                  API Resource (formatting)
                                       ↓
                                  JSON Response
```

| Layer | Responsibility | Location |
|-------|---------------|----------|
| **Route** | URL → Controller + implicit model binding | `routes/api/*.php` |
| **Form Request** | Input validation + authorization | `app/Http/Requests/` |
| **Controller** | HTTP concerns only: auth, caching, response formatting | `app/Http/Controllers/Api/` |
| **Service** | All business logic, DB transactions, cross-model validation | `app/Services/` |
| **API Resource** | Shape the JSON output | `app/Http/Resources/` |
| **Custom Exception** | Self-rendering domain errors | `app/Exceptions/{Module}/` |

---

## Step-by-Step Refactoring Process

### Step 1: Audit the Controller

Read the entire controller. For each method, classify every block of code:

| Code Block | Moves To |
|-----------|----------|
| `$request->validate([...])` in index | `IndexXxxRequest` form request |
| Query building, filtering, sorting | `Service::list()` |
| Business rule checks (e.g. "already exists") | Service method → throws custom exception |
| `DB::beginTransaction()` + model create/update | Service method |
| `->with([relations])->find($id)` for response | Service method |
| `response()->json([...])` | Stays in controller |
| `$this->cacheAndPaginate(...)` | Stays in controller |
| `$this->invalidateCacheAfterWrite(...)` | Stays in controller |

### Step 2: Create Custom Exceptions

Create `app/Exceptions/{Module}/` directory. Each exception:

```php
<?php

namespace App\Exceptions\{Module};

use Exception;
use Illuminate\Http\JsonResponse;

class SomeDomainException extends Exception
{
    protected $message = 'Human-readable error message.';

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], 422); // or 400
    }
}
```

**Key rules:**
- Self-rendering via `render()` — no registration in `bootstrap/app.php`
- Use 422 for validation-type errors, 400 for invalid state transitions
- If the original controller returned `errors` array, include it in `render()`
- Exception message should match the original error message exactly (frontend depends on it)

### Step 3: Create Index Form Request (if applicable)

Only if `index()` has inline `$request->validate([...])`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Index{Model}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true; // middleware handles auth
    }

    public function rules(): array
    {
        return [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            // ... copy from controller's inline validation
        ];
    }
}
```

### Step 4: Create API Resource + Collection

**Resource** — map all fields explicitly:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class {Model}Resource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // ... all model fields with proper date formatting
            'some_date' => $this->some_date?->format('Y-m-d'),

            // Dynamically appended attributes (set by service before returning)
            'computed_field' => $this->resource->computed_field ?? null,

            // Conditional relationships
            'relation_name' => $this->whenLoaded('relationName'),
        ];
    }
}
```

**Collection** — minimal pass-through:

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class {Model}Collection extends ResourceCollection
{
    public $collects = {Model}Resource::class;

    public function toArray($request): array
    {
        return parent::toArray($request);
    }
}
```

**CRITICAL**: For `index()`, do NOT use `return (new Collection($items))->response()`. Instead, embed the resource inside the manual `response()->json([...])` to preserve the `{ success, message, data, pagination, filters }` format.

### Step 5: Create the Service

```php
<?php

namespace App\Services;

class {Model}Service
{
    public function __construct(
        // Inject any existing services this controller already uses
    ) {}
}
```

**Method mapping from controller:**

| Controller Method | Service Method | Returns |
|------------------|---------------|---------|
| `index()` query logic | `list(array $validated): array` | `['query' => Builder, 'filters' => [...], 'applied_filters' => [...], 'per_page' => int]` |
| `store()` business logic | `create(array $validated): Model` | The created model with relations loaded |
| `show()` loading logic | `show(Model $model): Model` | Model with relations + computed fields |
| `update()` business logic | `update(Model $model, array $validated): array` | `['model' => ..., 'extra_data' => ...]` |
| `destroy()` | `delete(Model $model): void` | void |

**Service rules:**
- DB transactions (`DB::beginTransaction/commit/rollBack`) live in the service
- Throw custom exceptions for business rule violations
- Return models/arrays, never `JsonResponse`
- Use `Auth::user()->name ?? 'system'` for audit fields
- Load relationships before returning for response use

### Step 6: Update Routes (Route Model Binding)

Change `{id}` → `{modelName}` for all routes that accept a model ID:

```php
// Before
Route::get('/{id}', [Controller::class, 'show']);
Route::put('/{id}', [Controller::class, 'update']);

// After
Route::get('/{model}', [Controller::class, 'show']);
Route::put('/{model}', [Controller::class, 'update']);
```

**Do NOT change:**
- Routes with no model param (index, store)
- Routes with non-model params (`{staffId}`, `{year}`)

### Step 7: Rewrite the Controller

Each method follows this thin pattern:

```php
public function store(StoreRequest $request): JsonResponse
{
    try {
        $model = $this->modelService->create($request->validated());
        $this->invalidateCacheAfterWrite($model);

        return response()->json([
            'success' => true,
            'message' => '{Model} created successfully',
            'data' => ['{model}' => new ModelResource($model)],
        ], 201);
    } catch (CustomDomainException $e) {
        throw $e; // Let self-rendering exception handle it
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Failed to create {model}',
            'error' => $e->getMessage(),
        ], 500);
    }
}
```

**Key patterns:**
- Custom exceptions: `catch (CustomException $e) { throw $e; }` — re-throw so `render()` handles it
- Generic exceptions: return `{ success: false, message, error }` with 500
- `ModelNotFoundException`: Laravel auto-returns 404 with route model binding
- Keep `HasCacheManagement` on controller — caching is a transport concern
- Keep OpenAPI attributes on every method
- Method signatures use type-hinted model params: `show(Model $model)`

### Step 8: Write Unit Tests

Test the **service**, not the controller. Use Mockery for injected dependencies:

```php
class {Model}ServiceTest extends TestCase
{
    use RefreshDatabase;

    protected {Model}Service $service;
    protected $mockDependency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockDependency = Mockery::mock(DependencyService::class);
        $this->service = new {Model}Service($this->mockDependency);
    }

    /** @test */
    public function create_throws_exception_when_business_rule_violated()
    {
        // Arrange: create conflicting data
        // Act & Assert
        $this->expectException(CustomException::class);
        $this->service->create([...]);
    }
}
```

**Test categories:**
- Happy path for each CRUD method
- Each custom exception thrown under correct conditions
- Auto-calculated fields (dates, derived values)
- Edge cases (mismatched relations, date constraints)

### Step 9: Verify

```bash
# All routes resolve
php artisan route:clear && php artisan route:list --path={prefix}

# No syntax errors
php -l app/Services/{Model}Service.php
php -l app/Http/Controllers/Api/{Model}Controller.php

# Tests pass
php artisan test tests/Unit/Services/{Model}ServiceTest.php
```

---

## What NOT to Change

| Concern | Why It Stays |
|---------|-------------|
| `HasCacheManagement` on controller | Caching is HTTP/transport, not business logic |
| `response()->json([...])` format | Frontend depends on `{ success, message, data }` envelope |
| Permission middleware on routes | Project uses route middleware, not Laravel Policies |
| OpenAPI `#[OA\...]` attributes | They belong on the HTTP layer (controller) |
| Try/catch per method | Preserves `{ success: false, message, error }` for 500s |
| `created_by` / `updated_by` audit fields | Part of the model contract, service sets them |

---

## Response Format Contract

All endpoints MUST return these shapes. The frontend depends on them:

**Success (list):**
```json
{
    "success": true,
    "message": "Items retrieved successfully",
    "data": [...],
    "pagination": {
        "current_page": 1,
        "per_page": 10,
        "total": 50,
        "last_page": 5,
        "from": 1,
        "to": 10,
        "has_more_pages": true
    },
    "filters": { "applied_filters": {} }
}
```

**Success (single):**
```json
{
    "success": true,
    "message": "Item retrieved successfully",
    "data": { "item": {...} }
}
```

**Error (validation / business rule):**
```json
{
    "success": false,
    "message": "Human-readable error",
    "errors": { "field": ["Error detail"] }
}
```

**Error (server):**
```json
{
    "success": false,
    "message": "Failed to do something",
    "error": "Exception message"
}
```

---

## Refactoring Candidates

Controllers that would benefit from this pattern (sorted by line count):

| Controller | Est. Lines | Priority |
|-----------|-----------|----------|
| `EmploymentController` | ~~1,060~~ **Done** |
| `EmployeeController` | Check | High |
| `GrantController` | Check | High |
| `PayrollController` | Check | Medium |
| `LeaveRequestController` | Check | Medium |
| `InterviewController` | Check | Low |
| `JobOfferController` | Check | Low |

Run this to find fat controllers:
```bash
find app/Http/Controllers/Api -name "*.php" -exec wc -l {} + | sort -rn | head -20
```
