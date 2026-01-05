# Data Entry System Development Checklist

## Core Principle
This HRMS backend is a **Data Entry and Display System** - not a workflow management system. All features should follow this principle.

## ✅ New Feature Checklist

When implementing any new feature, ensure:

### 1. Database Design
- [ ] Migration creates simple table structure
- [ ] Boolean fields for approval status (not complex workflow states)
- [ ] Audit fields: `created_by`, `updated_by`, `created_at`, `updated_at`
- [ ] Soft deletes if needed: `deleted_at`

### 2. Model Design
- [ ] Standard Eloquent model with relationships
- [ ] Simple casting for data types
- [ ] No complex state management methods
- [ ] Basic helper methods only

### 3. Controller Pattern
- [ ] `index()` - List with filtering and pagination
- [ ] `store()` - Create new record
- [ ] `show()` - Display single record
- [ ] `update()` - Edit existing record
- [ ] `destroy()` - Delete record (if needed)
- [ ] `export()` - Excel export functionality
- [ ] `import()` - Excel import functionality (if needed)

### 4. Validation
- [ ] Form Request class for validation
- [ ] Simple validation rules
- [ ] Allow all fields that users might need to edit
- [ ] Include approval boolean fields in validation

### 5. Routes
- [ ] Standard RESTful routes
- [ ] Permission-based middleware
- [ ] No special workflow endpoints (like `/approve`, `/reject`)

### 6. Service Layer (Optional)
- [ ] Keep services simple - data validation and storage only
- [ ] No complex business logic
- [ ] No automated state changes
- [ ] Cache management if needed

### 7. Permissions
- [ ] Standard CRUD permissions: `module.create`, `module.read`, `module.update`, `module.delete`
- [ ] Export/Import permissions: `module.export`, `module.import`
- [ ] No special workflow permissions

### 8. API Design
- [ ] RESTful endpoints
- [ ] Consistent JSON response format
- [ ] Pagination for list endpoints
- [ ] Filtering capabilities
- [ ] OpenAPI documentation

## ❌ What to Avoid

### Don't Implement:
- Complex approval workflows
- State machines
- Automated business logic
- Real-time notifications
- Complex validation rules that enforce business processes
- Endpoints like `/approve`, `/submit`, `/process`
- Special permissions beyond CRUD

### Don't Add:
- Workflow status fields (use simple booleans instead)
- Complex service methods that "implement" or "process" actions
- Event listeners that trigger business logic
- Background jobs for workflow processing

## 📋 Example Implementation

For reference, see the Personnel Actions implementation:
- Simple table with boolean approval fields
- Standard CRUD endpoints
- Basic validation
- No complex approval workflow
- Data entry focused

## 🎯 Remember

**The system digitizes completed paper processes - it doesn't manage the processes themselves.**

Users will:
1. Complete HR processes on paper
2. Enter the final results into the system
3. Use the system to view, search, and report on the data

The system should make this as simple and straightforward as possible.

