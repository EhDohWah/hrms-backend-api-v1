# User Management System Documentation

Welcome to the complete documentation for the HRMS User Management System. This documentation covers both the Laravel 11 backend API and Vue 3 frontend application.

## 📚 Documentation Index

### 1. [Overview](./USER_MANAGEMENT_OVERVIEW.md)
**Start here** for a comprehensive overview of the entire system.

**Contents**:
- System architecture diagram
- Technology stack (backend + frontend)
- Key features and capabilities
- Authentication and authorization flow
- Data flow diagrams
- File structure
- Known issues and gaps

**Best for**: Understanding the big picture, onboarding new developers

---

### 2. [Backend API Reference](./BACKEND_API_REFERENCE.md)
Complete reference for all backend API endpoints and implementations.

**Contents**:
- Authentication endpoints (login, logout, refresh)
- User profile endpoints (get user, update profile, password)
- Admin user management endpoints (CRUD operations)
- Activity log endpoints
- Models and database schema
- Middleware and authorization
- Seeders and factories
- Validation rules
- Postman collection examples

**Best for**: Backend developers, API consumers, testing

---

### 3. [Frontend Components Guide](./FRONTEND_COMPONENTS_GUIDE.md)
Detailed guide to all Vue 3 components and pages.

**Contents**:
- Authentication pages (login, forgot password, reset password)
- User management pages (user list, roles, permissions)
- Modal components (user CRUD, role CRUD)
- State management (Pinia stores)
- Form validation (Vuelidate)
- Event bus and utilities
- Component lifecycle and methods

**Best for**: Frontend developers, UI/UX implementation

---

### 4. [API Integration Guide](./API_INTEGRATION_GUIDE.md)
How frontend and backend integrate together.

**Contents**:
- API service architecture
- Complete authentication flow
- Service layer details (apiService, authService, adminService)
- State management integration
- Error handling patterns
- Real-time integration (Laravel Echo)
- Best practices

**Best for**: Full-stack developers, understanding data flow

---

### 5. [Quick Reference](./QUICK_REFERENCE.md)
**Quick lookup** for common tasks and code snippets.

**Contents**:
- API endpoints cheat sheet
- Default users and credentials
- Roles and permissions reference
- File paths (frontend + backend)
- Common code snippets
- Validation rules
- Database schema quick reference
- Environment variables
- Common issues and solutions
- Testing commands
- Performance tips
- Security checklist

**Best for**: Day-to-day development, quick answers

---

## 🚀 Getting Started

### For New Developers

1. **Read**: [USER_MANAGEMENT_OVERVIEW.md](./USER_MANAGEMENT_OVERVIEW.md) - Get the big picture
2. **Setup**: Follow the setup instructions below
3. **Explore**: Use [QUICK_REFERENCE.md](./QUICK_REFERENCE.md) for common tasks
4. **Deep Dive**: Refer to specific guides as needed

### For Backend Developers

1. Start with [BACKEND_API_REFERENCE.md](./BACKEND_API_REFERENCE.md)
2. Review authentication flow in [USER_MANAGEMENT_OVERVIEW.md](./USER_MANAGEMENT_OVERVIEW.md)
3. Use [QUICK_REFERENCE.md](./QUICK_REFERENCE.md) for code snippets

### For Frontend Developers

1. Start with [FRONTEND_COMPONENTS_GUIDE.md](./FRONTEND_COMPONENTS_GUIDE.md)
2. Review [API_INTEGRATION_GUIDE.md](./API_INTEGRATION_GUIDE.md) for service layer
3. Use [QUICK_REFERENCE.md](./QUICK_REFERENCE.md) for common patterns

---

## 🔧 Setup Instructions

### Backend Setup

```bash
# Navigate to backend directory
cd hrms-backend-api-v1

# Install dependencies
composer install

# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate

# Configure database in .env
DB_DATABASE=hrms
DB_USERNAME=root
DB_PASSWORD=

# Run migrations
php artisan migrate

# Seed database (creates default users and roles)
php artisan db:seed

# Start development server
php artisan serve
# Server running at http://localhost:8000
```

### Frontend Setup

```bash
# Navigate to frontend directory
cd hrms-frontend-dev

# Install dependencies
npm install

# Copy environment file
cp .env.example .env

# Configure API URL in .env
VUE_APP_API_BASE_URL=http://localhost:8000/api/v1

# Start development server
npm run dev
# Server running at http://localhost:8080
```

### Testing the Setup

1. **Backend**: Visit http://localhost:8000/api/v1/health (if health check endpoint exists)
2. **Frontend**: Open http://localhost:8080
3. **Login**: Use default credentials from [QUICK_REFERENCE.md](./QUICK_REFERENCE.md#default-users)

---

## 🎯 Common Tasks

### How to...

| Task | Documentation |
|------|---------------|
| Add a new API endpoint | [BACKEND_API_REFERENCE.md](./BACKEND_API_REFERENCE.md) - See controller examples |
| Create a new user role | [QUICK_REFERENCE.md](./QUICK_REFERENCE.md#roles--permissions) |
| Implement form validation | [FRONTEND_COMPONENTS_GUIDE.md](./FRONTEND_COMPONENTS_GUIDE.md#form-validation) |
| Handle file uploads | [API_INTEGRATION_GUIDE.md](./API_INTEGRATION_GUIDE.md#admin-service) - See createUser method |
| Add permission checks | [QUICK_REFERENCE.md](./QUICK_REFERENCE.md#common-code-snippets) - See permission examples |
| Debug authentication issues | [API_INTEGRATION_GUIDE.md](./API_INTEGRATION_GUIDE.md#authentication-flow) |
| Customize activity logging | [BACKEND_API_REFERENCE.md](./BACKEND_API_REFERENCE.md#activity-log-model--schema) |
| Add route guards | [API_INTEGRATION_GUIDE.md](./API_INTEGRATION_GUIDE.md#frontend---route-guard) |

---

## 📊 System Overview

### Tech Stack

**Backend**:
- Laravel 11
- PHP 8.2.29
- Laravel Sanctum (authentication)
- Spatie Permission (authorization)
- MySQL database

**Frontend**:
- Vue 3
- Pinia (state management)
- Vue Router 4
- Bootstrap 5 + Ant Design Vue 4
- Vuelidate 2.0 (validation)
- Laravel Echo (real-time)

### Key Features

✅ Email/password authentication
✅ Token-based API auth (Sanctum)
✅ Role-based access control (5 roles)
✅ Permission-based access control (147 permissions)
✅ User CRUD operations
✅ Profile management (picture, email, password)
✅ Activity logging
✅ Real-time notifications
✅ Rate limiting
✅ Password strength validation

---

## 🔐 Default Credentials

Use these credentials to log in for the first time:

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@hrms.com | password |
| HR Manager | hrmanager@hrms.com | password |
| HR Assistant Senior | hrassistant.senior@hrms.com | password |
| HR Assistant Junior | hrassistant.junior@hrms.com | password |
| Site Admin | siteadmin@hrms.com | password |

⚠️ **Change these passwords in production!**

---

## 🐛 Known Issues

### Backend

1. ❌ `AdminController@show($id)` - Method not implemented
2. ❌ `AdminController@getRoles()` - Method not implemented
3. ❌ `AdminController@getPermissions()` - Method not implemented
4. ❌ No Form Request classes (validation is inline)
5. ❌ No API Resource classes (returning raw models)
6. ❌ No pagination on user list endpoint

### Frontend

1. ❌ No pagination on user table (loads all users)
2. ❌ Basic HTML5 validation only on modals
3. ❌ No comprehensive error handling service

See [USER_MANAGEMENT_OVERVIEW.md](./USER_MANAGEMENT_OVERVIEW.md#known-issues--gaps) for complete list.

---

## 📝 API Endpoint Summary

### Public Endpoints
```
POST /api/v1/login
```

### Authenticated Endpoints
```
POST   /api/v1/logout
POST   /api/v1/refresh-token
GET    /api/v1/user/user
POST   /api/v1/user/profile-picture
POST   /api/v1/user/username
POST   /api/v1/user/email
POST   /api/v1/user/password
```

### Admin Endpoints (Permission Required)
```
GET    /api/v1/admin/users
POST   /api/v1/admin/users
PUT    /api/v1/admin/users/{id}
DELETE /api/v1/admin/users/{id}
```

See [QUICK_REFERENCE.md](./QUICK_REFERENCE.md#api-endpoints-cheat-sheet) for complete list.

---

## 🧪 Testing

### Backend (Pest/PHPUnit)

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/UserManagementTest.php

# Run with filter
php artisan test --filter=test_user_can_login
```

### Frontend (Jest/Vitest)

```bash
# Run all tests
npm run test

# Run with coverage
npm run test:coverage

# Watch mode
npm run test:watch
```

---

## 📞 Support & Contributing

### Getting Help

1. **Check Documentation**: Search these docs first
2. **Common Issues**: See [QUICK_REFERENCE.md](./QUICK_REFERENCE.md#common-issues--solutions)
3. **API Reference**: Check [BACKEND_API_REFERENCE.md](./BACKEND_API_REFERENCE.md) for endpoint details

### Contributing

When adding new features:

1. **Update Documentation**: Keep docs in sync with code
2. **Follow Patterns**: Use existing code as reference
3. **Test**: Write tests for new functionality
4. **Security**: Follow the security checklist in [QUICK_REFERENCE.md](./QUICK_REFERENCE.md#security-checklist)

---

## 📁 Documentation Files

All documentation is in Markdown format:

```
docs/user-management/
├── README.md                           # This file (documentation index)
├── USER_MANAGEMENT_OVERVIEW.md         # System overview and architecture
├── BACKEND_API_REFERENCE.md            # Complete backend API documentation
├── FRONTEND_COMPONENTS_GUIDE.md        # Vue components and pages guide
├── API_INTEGRATION_GUIDE.md            # Frontend-backend integration
└── QUICK_REFERENCE.md                  # Quick lookup and code snippets
```

---

## 🔄 Changelog

### Version 1.0 (2025-12-17)
- Initial documentation release
- Complete backend API documentation
- Complete frontend components documentation
- Integration guide
- Quick reference guide

---

## 📚 External Resources

### Laravel
- [Laravel 11 Documentation](https://laravel.com/docs/11.x)
- [Laravel Sanctum](https://laravel.com/docs/11.x/sanctum)
- [Spatie Permission](https://spatie.be/docs/laravel-permission/v6/introduction)

### Vue
- [Vue 3 Guide](https://vuejs.org/guide/introduction.html)
- [Pinia Documentation](https://pinia.vuejs.org/)
- [Vue Router 4](https://router.vuejs.org/)
- [Vuelidate 2](https://vuelidate-next.netlify.app/)

### Testing
- [Pest PHP](https://pestphp.com/)
- [Vue Test Utils](https://test-utils.vuejs.org/)

---

## ⚖️ License

This documentation is part of the HRMS project.

---

**Last Updated**: 2025-12-17
**Version**: 1.0
**Maintained by**: HRMS Development Team

---

## 🎓 Learning Path

### Beginner
1. Read [USER_MANAGEMENT_OVERVIEW.md](./USER_MANAGEMENT_OVERVIEW.md)
2. Set up development environment (see above)
3. Test login with default credentials
4. Explore the user list page

### Intermediate
1. Study [API_INTEGRATION_GUIDE.md](./API_INTEGRATION_GUIDE.md)
2. Understand authentication flow
3. Review Pinia store patterns
4. Practice creating a new user

### Advanced
1. Deep dive into [BACKEND_API_REFERENCE.md](./BACKEND_API_REFERENCE.md)
2. Study permission system implementation
3. Implement missing features (see Known Issues)
4. Optimize performance (see Performance Tips in Quick Reference)

---

**Happy Coding! 🚀**
