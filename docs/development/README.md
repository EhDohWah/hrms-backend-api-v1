# Development Guidelines & Standards

**Location:** `/docs/development/`  
**Purpose:** Development guidelines, coding standards, and best practices for HRMS Backend

---

## 📄 Files in This Folder

### Coding Guidelines
- **[CLAUDE_GUIDELINES.md](./CLAUDE_GUIDELINES.md)**
  - Laravel Boost guidelines for this application
  - Package versions and dependencies
  - Coding conventions and standards
  - Laravel 11-specific patterns
  - Testing guidelines (Pest)
  - Code formatting (Pint)

---

## 🎯 Quick Reference

### Technology Stack
- **PHP:** 8.2.29
- **Laravel:** 11
- **Database:** MySQL/MariaDB
- **Authentication:** Laravel Sanctum v4
- **Broadcasting:** Laravel Reverb v1
- **Testing:** Pest v3
- **Code Style:** Laravel Pint v1

### Key Conventions
- Use explicit return type declarations
- Follow Laravel 11 streamlined structure
- Use Pest for all tests
- Run Pint before commits
- Follow repository pattern
- Use Service classes for business logic

---

## 📚 Guidelines Summary

### Laravel 11 Structure
- No `app/Console/Kernel.php`
- No `app/Http/Kernel.php`
- Middleware in `bootstrap/app.php`
- Commands auto-register from `app/Console/Commands/`

### Database Conventions
- Use Eloquent relationships
- Avoid raw queries
- Prevent N+1 with eager loading
- Use query builder for complex operations
- Migrations for all schema changes

### API Development
- Use API Resources for responses
- Version APIs properly
- Use Form Requests for validation
- Include custom error messages
- Follow REST conventions

### Testing
- Write Pest tests (not PHPUnit)
- Test happy paths, failure paths, edge cases
- Use factories for test data
- Run tests before commits
- Aim for high code coverage

---

## 🔧 Development Workflow

### Before Starting
1. Review Laravel Boost Guidelines
2. Check existing patterns in codebase
3. Plan database schema changes
4. Write tests first (TDD recommended)

### During Development
1. Follow naming conventions
2. Use type hints everywhere
3. Write descriptive commit messages
4. Keep methods small and focused
5. Document complex logic

### Before Committing
1. Run Pint: `vendor/bin/pint`
2. Run tests: `php artisan test`
3. Check for N+1 queries
4. Review your own code
5. Update documentation if needed

---

## 📖 Related Documentation

- [Architecture](../architecture/) - System architecture
- [Database](../database/) - Database schema
- [Testing](../testing/) - Testing guidelines
- [General](../general/) - General documentation

---

**Last Updated:** January 8, 2026  
**Maintained By:** Development Team  


