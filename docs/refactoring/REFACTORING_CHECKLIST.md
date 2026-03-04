# Controller Refactoring Checklist

> Copy this checklist when refactoring a new controller. Check off each item as you go.

## Controller: `___Controller`
**Date**: ___
**Before line count**: ___
**After line count**: ___

---

### Phase 1: Create New Files (non-breaking)

- [ ] **Custom Exceptions** — `app/Exceptions/{Module}/`
  - [ ] Identify all inline `return response()->json([... 'success' => false ...], 4xx)` patterns
  - [ ] Create one exception class per business rule violation
  - [ ] Each has `render(): JsonResponse` method (self-rendering)
  - [ ] Message matches the original error message exactly
  - [ ] HTTP status code matches the original (422, 400, etc.)
  - [ ] `php -l` passes on each file

- [ ] **Index Form Request** — `app/Http/Requests/Index{Model}Request.php`
  - [ ] Copy rules from inline `$request->validate([...])` in `index()`
  - [ ] `authorize()` returns `true`
  - [ ] Add `messages()` with user-friendly validation messages
  - [ ] `php -l` passes

- [ ] **API Resource** — `app/Http/Resources/{Model}Resource.php`
  - [ ] Map all model fields explicitly
  - [ ] Date fields use `?->format('Y-m-d')`
  - [ ] Relations use `$this->whenLoaded('relationName')`
  - [ ] Dynamically appended fields use `$this->resource->field ?? null`
  - [ ] `php -l` passes

- [ ] **API Collection** — `app/Http/Resources/{Model}Collection.php`
  - [ ] Set `$collects = {Model}Resource::class`
  - [ ] `php -l` passes

- [ ] **Service** — `app/Services/{Model}Service.php`
  - [ ] Constructor injects existing service dependencies
  - [ ] `list()` returns `['query' => Builder, 'filters' => [], 'applied_filters' => [], 'per_page' => int]`
  - [ ] `create()` handles active record checks, auto-calculations, DB transaction
  - [ ] `show()` loads relations and appends computed fields
  - [ ] `update()` handles cross-field validation, DB transaction, side effects
  - [ ] `delete()` is simple delegation
  - [ ] All business rule violations throw custom exceptions
  - [ ] Returns models/arrays, never JsonResponse
  - [ ] `php -l` passes

### Phase 2: Modify Existing Files

- [ ] **Routes** — `routes/api/{module}.php`
  - [ ] Change `{id}` → `{model}` for all model-specific routes
  - [ ] Do NOT change routes without model params (index, store)
  - [ ] Do NOT change routes with non-model params ({staffId}, {year})
  - [ ] `php artisan route:clear`

- [ ] **Controller** — `app/Http/Controllers/Api/{Model}Controller.php`
  - [ ] Constructor injects only the Service
  - [ ] `HasCacheManagement` trait stays
  - [ ] `getModelName()` stays
  - [ ] Each method: try/catch + delegate + respond
  - [ ] Custom exceptions: `catch (Custom $e) { throw $e; }`
  - [ ] Generic exceptions: return 500 JSON
  - [ ] Method signatures use type-hinted model: `show(Model $model)`
  - [ ] OpenAPI attributes preserved on every method
  - [ ] `php -l` passes

### Phase 3: Tests

- [ ] **Unit Test** — `tests/Unit/Services/{Model}ServiceTest.php`
  - [ ] Mock injected service dependencies
  - [ ] Test each custom exception is thrown correctly
  - [ ] Test auto-calculated fields
  - [ ] Test happy path for CRUD operations
  - [ ] Test edge cases (date constraints, relation mismatches)
  - [ ] `php -l` passes

### Phase 4: Verify

- [ ] `php artisan route:clear && php artisan route:list --path={prefix}` — all routes resolve
- [ ] `php -l` passes on ALL created/modified files
- [ ] `php artisan test tests/Unit/Services/{Model}ServiceTest.php` — tests pass
- [ ] Response format unchanged (compare with frontend API calls)
- [ ] No unused imports in controller
- [ ] No leftover business logic in controller (only HTTP concerns)

---

### Post-Refactoring Verification Questions

1. Does `index()` return the exact same JSON shape? (`data`, `pagination`, `filters`)
2. Does `store()` return 201 with `{ success, message, data: { model: {...} } }`?
3. Does `update()` return 200 with same shape?
4. Does `destroy()` return 200 with `{ success, message }`?
5. Do validation errors return 422 with `{ success: false, message, errors }`?
6. Do business rule errors return the same HTTP status as before?
7. Do 500 errors return `{ success: false, message, error }`?
8. Are all relationship field names in the response unchanged?
