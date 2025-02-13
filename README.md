    # HR Management System Laravel/API

A Laravel-based REST API for managing HR data including employees, grants, users, interviews, employment history, children, questionnaires, languages, references, education, payroll, attendance, training, reports, travel requests and leave requests.

## Features

- User authentication with Laravel Sanctum
- Role-based access control with permissions
- Employee management
- Grant management with Excel import functionality
- User management

## Requirements

- PHP 8.0+
- Composer
- MySQL 5.7+
- Laravel Framework 11.31+

## Packages

- Laravel Sanctum 4.0+
- Laravel Swagger (darkaonline/l5-swagger) 8.6+
- Laravel Tinker 2.9+
- PhpSpreadsheet 3.9+
- Laravel Spatie Permission 6.13+


## Installation

1. Clone the repository:

bash
git clone https://github.com/EhDohWah/hrms-backend-api-v1.git
cd hrms-backend-api-v1

2. Install dependencies:

composer install

3. Configure environment:

Copy .env.example to .env and configure your database settings:

bash
cp .env.example .env

# MySQL
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hrms_db
DB_USERNAME=root
DB_PASSWORD=

# SQL Server
DB_CONNECTION=sqlsrv
DB_HOST=127.0.0.1
DB_PORT=1433
DB_DATABASE=hrms_db
DB_USERNAME=sa
DB_PASSWORD=your_password

Choose either MySQL or SQL Server configuration and update the values according to your database setup.

Generate application key:

bash
php artisan key:generate

4. Run migrations:

bash
php artisan migrate

5. Seed the database:

bash
php artisan db:seed

6. Start the server:

bash
php artisan serve

## API Documentation

Access the API documentation at:

http://localhost:8000/api/documentation

## API Endpoints

### Authentication

POST /login

### Employees

GET /employees
GET /employees/{id}
POST /employees
PUT /employees/{id}
DELETE /employees/{id}

### Grants

POST /grants/upload

### Users

GET /users
GET /users/{id}
POST /users
PUT /users/{id}
DELETE /users/{id}
    


