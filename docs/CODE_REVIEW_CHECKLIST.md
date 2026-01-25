# Code Review Checklist - Controllers

**Version:** 1.0
**Last Updated:** 2026-01-24

Use this checklist when reviewing PRs that add or modify controllers.

---

## 1. Naming Conventions

### Controller Name
- [ ] Controller name is singular + "Controller" (e.g., `EmployeeController`, not `EmployeesController`)
- [ ] Controller name matches the resource it manages
- [ ] Located in `app/Http/Controllers/Api/` for API controllers

### Method Names
- [ ] CRUD methods use RESTful names: `index`, `show`, `store`, `update`, `destroy`
- [ ] No redundant entity names in methods (e.g., `store()` not `storeEmployee()`)
- [ ] No redundant "get" prefix (e.g., `options()` not `getOptions()`)
- [ ] Custom methods use verb-first pattern (e.g., `calculatePayroll()`, `approveRequest()`)
- [ ] Partial update methods follow `update{Section}Info()` pattern

---

## 2. RESTful Compliance

- [ ] `index()` returns paginated list with filtering support
- [ ] `show($id)` returns single resource by ID
- [ ] `store()` creates new resource and returns 201 status
- [ ] `update($id)` updates resource and returns 200 status
- [ ] `destroy($id)` deletes resource and returns 200/204 status
- [ ] No duplicate lookup methods (use `index()` with query params instead of `getByCode()`)

---

## 3. Controller Size & Responsibility

- [ ] Controller has fewer than 12 public methods
- [ ] Controller is fewer than 600 lines
- [ ] Controller handles only one resource type
- [ ] Sub-resources with 3+ methods are in separate controllers
- [ ] Business logic is in Service classes, not controller methods

---

## 4. Request/Response Standards

### Requests
- [ ] Form Request classes used for validation (not inline validation)
- [ ] Request class name follows pattern: `Store{Model}Request`, `Update{Model}Request`
- [ ] Validation rules are comprehensive

### Responses
- [ ] All responses use consistent JSON structure:
  ```json
  {
    "success": true,
    "message": "Operation completed",
    "data": {}
  }
  ```
- [ ] Error responses include `success: false`
- [ ] Appropriate HTTP status codes used
- [ ] Pagination includes standard metadata

---

## 5. Route Definitions

- [ ] Routes use `apiResource()` where applicable
- [ ] Custom routes have meaningful names
- [ ] Route names follow `{resource}.{action}` pattern
- [ ] Appropriate middleware applied (auth, permissions)
- [ ] No duplicate routes for same functionality

---

## 6. Documentation

- [ ] Controller has class-level docblock explaining purpose
- [ ] Complex methods have docblocks with:
  - Description
  - @param annotations
  - @return annotation
  - Example request/response
- [ ] OpenAPI annotations present for public endpoints

---

## 7. Security

- [ ] Authentication middleware applied (`auth:sanctum`)
- [ ] Authorization checks present (permissions, policies)
- [ ] No mass assignment vulnerabilities (use Form Requests)
- [ ] Sensitive data not exposed in responses
- [ ] Input validated before use

---

## 8. Error Handling

- [ ] Try-catch blocks for database operations
- [ ] Meaningful error messages returned
- [ ] Exceptions logged appropriately
- [ ] 500 errors don't expose internal details in production

---

## 9. Performance

- [ ] Eager loading used for relationships (`with()`)
- [ ] Select only needed columns (`select()`)
- [ ] Pagination used for list endpoints
- [ ] No N+1 query problems
- [ ] Cache used where appropriate

---

## 10. Testing

- [ ] Feature tests cover all public methods
- [ ] Tests verify correct HTTP status codes
- [ ] Tests verify response structure
- [ ] Edge cases tested (not found, unauthorized, invalid input)

---

## Quick Decision Tree

```
Is it a CRUD operation?
├── Yes → Use RESTful method name (index, show, store, update, destroy)
└── No → Is it returning dropdown/options data?
    ├── Yes → Use options()
    └── No → Is it a state change?
        ├── Yes → Use verb + noun (approveRequest, toggleStatus)
        └── No → Is it a bulk operation?
            ├── Yes → Use bulk prefix (bulkDelete, deleteSelected)
            └── No → Is it import/export?
                ├── Yes → Use import() or export()
                └── No → Use verb + object pattern (calculatePayroll)
```

---

## Red Flags to Watch For

| Flag | Example | Action |
|------|---------|--------|
| Entity name in method | `storeEmployee()` | Rename to `store()` |
| "get" prefix | `getUsers()` | Rename to `index()` |
| Duplicate lookup | `getById()` AND `show()` | Remove duplicate |
| God controller | 20+ methods | Split into multiple |
| Business logic | 50+ lines of calculations | Extract to Service |
| No Form Request | Inline `$request->validate()` | Create Form Request |

---

*Print this checklist and use during code reviews.*
