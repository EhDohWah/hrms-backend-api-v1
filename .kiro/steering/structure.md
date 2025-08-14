# Project Structure & Organization

## Laravel Application Structure

### Core Application (`app/`)
- **Events/**: Application events for system notifications
- **Exports/**: Excel export classes using Maatwebsite Excel
- **Http/**: Controllers, middleware, requests, and resources
- **Imports/**: Excel import classes for bulk data operations
- **Listeners/**: Event listeners for handling application events
- **Models/**: Eloquent models representing database entities
- **Notifications/**: Email and system notifications
- **Observers/**: Model observers for automatic actions
- **Providers/**: Service providers for dependency injection
- **Services/**: Business logic services (e.g., TaxCalculationService)

### Configuration (`config/`)
Key configuration files:
- `app.php`: Application settings
- `auth.php`: Authentication configuration
- `database.php`: Database connections
- `permission.php`: Spatie permission settings
- `l5-swagger.php`: API documentation settings
- `excel.php`: Excel import/export configuration

### Database (`database/`)
- **migrations/**: Database schema migrations
- **seeders/**: Database seeders for initial data
- **factories/**: Model factories for testing
- `database.sqlite`: SQLite database for testing

### Resources (`resources/`)
- **css/**: Tailwind CSS styles
- **js/**: JavaScript assets and components
- **views/**: Blade templates (minimal for API-focused app)

### Routes (`routes/`)
- `api.php`: API routes with `/api/v1` prefix
- `web.php`: Web routes (minimal)
- `console.php`: Artisan commands
- `channels.php`: Broadcasting channels

### Testing (`tests/`)
- **Feature/**: Integration tests for API endpoints
- **Unit/**: Unit tests for services and models
- `TestCase.php`: Base test case with common setup

## Key Architectural Patterns

### API Resource Pattern
- Controllers return API resources for consistent JSON responses
- Resources handle data transformation and formatting
- Pagination and filtering handled consistently

### Service Layer Pattern
- Business logic extracted to service classes
- Services handle complex calculations (e.g., tax calculations)
- Controllers remain thin, delegating to services

### Repository Pattern (Implicit)
- Eloquent models act as repositories
- Query builders used for complex queries
- Spatie Query Builder for API filtering

### Request Validation Pattern
- Form request classes for input validation
- Validation rules centralized and reusable
- Custom validation messages

## Naming Conventions

### Models
- Singular, PascalCase: `Employee`, `Payroll`, `TaxBracket`
- Relationships follow Laravel conventions
- Use traits for common functionality

### Controllers
- PascalCase with `Controller` suffix: `EmployeeController`
- RESTful method names: `index`, `show`, `store`, `update`, `destroy`
- API controllers in `Http/Controllers/Api` namespace

### Database Tables
- Plural, snake_case: `employees`, `payrolls`, `tax_brackets`
- Foreign keys: `employee_id`, `employment_id`
- Timestamps: `created_at`, `updated_at`
- Audit fields: `created_by`, `updated_by`

### API Routes
- Plural resource names: `/employees`, `/payrolls`
- Kebab-case for multi-word resources: `/tax-brackets`
- Nested resources where appropriate: `/employees/{id}/payrolls`

## File Organization Best Practices

### Controllers
- Group related functionality
- Keep methods focused and single-purpose
- Use dependency injection for services

### Models
- Define relationships clearly
- Use accessors/mutators for data transformation
- Implement model observers for automatic actions

### Migrations
- Descriptive names with timestamps
- Use foreign key constraints
- Include indexes for performance

### Seeders
- Separate seeders for different data types
- Use model factories where possible
- Include realistic test data

## Security Patterns

### Authentication
- Sanctum tokens for API authentication
- Middleware for route protection
- Token scoping for different access levels

### Authorization
- Spatie Permission for role-based access
- Policy classes for complex authorization logic
- Middleware aliases for common permissions

### Data Protection
- Encrypted attributes for sensitive data (payroll)
- Input validation on all endpoints
- CORS configuration for API access

## Documentation Standards

### API Documentation
- Swagger/OpenAPI annotations in controllers
- Comprehensive endpoint documentation
- Example requests and responses

### Code Documentation
- PHPDoc blocks for classes and methods
- Inline comments for complex logic
- README files for major features

## Development Workflow

### Feature Development
1. Create migration for database changes
2. Create/update models with relationships
3. Create form request classes for validation
4. Implement controller methods
5. Create API resource classes
6. Add routes with appropriate middleware
7. Write tests (unit and feature)
8. Update API documentation

### Testing Strategy
- Unit tests for services and complex logic
- Feature tests for API endpoints
- Database transactions for test isolation
- Factory-based test data generation

This structure supports maintainable, scalable development while following Laravel best practices and conventions.