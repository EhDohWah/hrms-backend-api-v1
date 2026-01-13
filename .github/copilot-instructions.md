# GitHub Copilot Rules for VSCode

## Universal Standards

- Write clean, maintainable code
- Follow SOLID principles
- Use meaningful names
- Add error handling
- Never commit commented code or console.logs
- Write JSDoc/PHPDoc for complex logic
- Use early returns to reduce nesting

---

## File Organization Rules

### PHP Test Scripts
When you create PHP scripts, **do not create the `.php` file inside the project root directory**. 

**Required location:** `/php_test_code/`

### Documentation Files
Do not create markdown (`.md`) files inside the project root directory.

**Required location:** `/docs/` directory for both backend and frontend

**Organization:** Inside the `/docs` directory, organize markdown files with proper subdirectories for better structure.

---

## Backend (Laravel) Rules

### Inherits
`../cursorrules` (workspace rules)

### Laravel Specific Standards
- Use Service classes for business logic
- Implement Form Requests for validation
- Use Eloquent eager loading
- Follow PSR-12 standards
- Type hints on all methods
- Use database transactions for payroll operations
- Implement proper API resources

---

## Frontend (Vue.js) Rules

### Inherits
`../cursorrules` (workspace rules)

### Vue.js Specific Standards
- Use Composition API with `<script setup>`
- Clean up listeners in `onUnmounted` (fix memory leaks!)
- Use Pinia for state management
- Implement proper error boundaries
- Lazy load routes
- Bootstrap 5 + Ant Design Vue 4 compatibility