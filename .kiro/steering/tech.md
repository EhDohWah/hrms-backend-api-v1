# Technology Stack & Build System

## Core Framework & Language

- **Framework**: Laravel 11.x
- **PHP Version**: 8.2+
- **Database**: MySQL 5.7+ / SQL Server support
- **Authentication**: Laravel Sanctum (token-based API authentication)

## Key Dependencies

### Backend Packages
- **spatie/laravel-permission**: Role-based access control and permissions
- **spatie/laravel-query-builder**: Advanced API query filtering and sorting
- **darkaonline/l5-swagger**: API documentation with Swagger/OpenAPI
- **maatwebsite/excel**: Excel import/export functionality
- **barryvdh/laravel-dompdf**: PDF generation for reports
- **league/flysystem-aws-s3-v3**: AWS S3 file storage integration
- **pusher/pusher-php-server**: Real-time notifications

### Frontend Build Tools
- **Vite**: Modern build tool and dev server
- **Tailwind CSS**: Utility-first CSS framework
- **Laravel Vite Plugin**: Laravel integration for Vite
- **PostCSS**: CSS processing with Autoprefixer

## Development Tools

- **Laravel Pint**: Code style fixer (PSR-12 compliance)
- **PHPUnit**: Testing framework
- **Laravel Sail**: Docker development environment
- **Laravel Pail**: Log viewer
- **Faker**: Test data generation

## API Configuration

- **API Prefix**: `/api/v1`
- **Authentication**: Sanctum token-based
- **Documentation**: Available at `/api/documentation`
- **CORS**: Configured for cross-origin requests

## Common Commands

### Development
```bash
# Start development environment
composer run dev

# Start individual services
php artisan serve          # Laravel server
php artisan queue:listen    # Queue worker
php artisan pail           # Log viewer
npm run dev                # Vite dev server

# Database operations
php artisan migrate        # Run migrations
php artisan db:seed        # Seed database
php artisan migrate:fresh --seed  # Fresh migration with seeding
```

### Testing
```bash
# Run all tests
php artisan test

# Run specific test suites
php artisan test tests/Unit
php artisan test tests/Feature

# Run tests with coverage
php artisan test --coverage
```

### Code Quality
```bash
# Fix code style
./vendor/bin/pint

# Generate API documentation
php artisan l5-swagger:generate
```

### Production
```bash
# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize

# Build frontend assets
npm run build
```

## Database Configuration

- **SQLite**: Used for testing (database.sqlite)
- **MySQL/SQL Server**: Production databases
- **Migrations**: Located in `database/migrations`
- **Seeders**: Located in `database/seeders`

## File Storage

- **Local**: Default for development
- **S3**: Configured for production file storage
- **Public**: Assets served from `public/storage`

## Queue System

- **Default**: Sync (development)
- **Production**: Database/Redis queues recommended
- **Jobs**: Background processing for heavy operations

## Security Features

- **Encryption**: All sensitive payroll data encrypted at rest
- **CSRF Protection**: Built-in Laravel CSRF protection
- **Rate Limiting**: API rate limiting configured
- **Input Validation**: Comprehensive request validation
- **Permission System**: Granular role-based permissions