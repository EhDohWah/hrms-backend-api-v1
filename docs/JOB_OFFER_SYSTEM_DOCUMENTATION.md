# Job Offer Management System - Complete Documentation

**Version:** 2.0  
**Date:** October 5, 2025  
**Status:** Production Ready - Updated Salary Structure  
**Last Updated:** Refactored salary fields and updated Swagger documentation

## Overview

The Job Offer Management System is a comprehensive solution for creating, managing, and tracking job offers within the HRMS. The system has been recently updated to support separate probation and post-probation salary fields, providing more granular control over compensation structures and better alignment with the PDF generation templates.

## Recent Updates (Version 2.0)

### ✅ **Major Change: Salary Structure Refactoring**
The system has been completely refactored to support separate salary fields for better compensation management:

**Before (Version 1.0):**
- `salary_detail` (string) - Generic salary description

**After (Version 2.0):**
- `probation_salary` (decimal 10,2) - Specific probation period salary
- `post_probation_salary` (decimal 10,2) - Specific post-probation salary
- Removed redundant `salary_detail` field

**Files Updated:**
- ✅ Database migration (`2025_04_08_144712_create_job_offers_table.php`)
- ✅ JobOffer model (`app/Models/JobOffer.php`)
- ✅ JobOfferController (`app/Http/Controllers/Api/JobOfferController.php`)
- ✅ JobOfferResource (`app/Http/Resources/JobOfferResource.php`)
- ✅ JobOfferRequest (`app/Http/Requests/JobOfferRequest.php`)
- ✅ JobOfferFactory (`database/factories/JobOfferFactory.php`)
- ✅ PDF template (`resources/views/jobOffer.blade.php`)
- ✅ Swagger/OpenAPI documentation (complete update)

### Key Features
- ✅ Complete CRUD operations for job offers
- ✅ Server-side pagination with advanced filtering and search
- ✅ **Separate probation and post-probation salary management**
- ✅ PDF generation with proper salary formatting
- ✅ Comprehensive Swagger/OpenAPI documentation
- ✅ Advanced filtering by position and acceptance status
- ✅ Custom offer ID generation with subsidiary support
- ✅ Observer pattern for cache invalidation

---

## Table of Contents

1. [Database Schema](#database-schema)
2. [API Endpoints](#api-endpoints)
3. [Model Structure](#model-structure)
4. [Request Validation](#request-validation)
5. [PDF Generation](#pdf-generation)
6. [Swagger Documentation](#swagger-documentation)
7. [Factory & Seeding](#factory--seeding)
8. [Error Handling](#error-handling)
9. [Performance Considerations](#performance-considerations)

---

## Database Schema

### Job Offers Table Structure

```sql
CREATE TABLE job_offers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    custom_offer_id VARCHAR(255) UNIQUE NOT NULL,
    date DATE NOT NULL,
    candidate_name VARCHAR(255) NOT NULL,
    position_name VARCHAR(255) NOT NULL,
    probation_salary DECIMAL(10,2) NULL,
    post_probation_salary DECIMAL(10,2) NULL,
    acceptance_deadline DATE NOT NULL,
    acceptance_status VARCHAR(255) NOT NULL,
    note TEXT NOT NULL,
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### Field Descriptions

| Field | Type | Description | Validation |
|-------|------|-------------|------------|
| `id` | BIGINT | Primary key | Auto-increment |
| `custom_offer_id` | VARCHAR(255) | Unique offer identifier | Required, Unique |
| `date` | DATE | Job offer date | Required, Date format |
| `candidate_name` | VARCHAR(255) | Candidate's full name | Required, String |
| `position_name` | VARCHAR(255) | Position being offered | Required, String |
| `probation_salary` | DECIMAL(10,2) | Salary during probation | Required, Numeric, Min: 0 |
| `post_probation_salary` | DECIMAL(10,2) | Salary after probation | Required, Numeric, Min: 0 |
| `acceptance_deadline` | DATE | Deadline for acceptance | Required, Date format |
| `acceptance_status` | VARCHAR(255) | Current offer status | Required, String |
| `note` | TEXT | Additional notes | Required, String |
| `created_by` | VARCHAR(255) | User who created record | Nullable, String |
| `updated_by` | VARCHAR(255) | User who updated record | Nullable, String |

---

## API Endpoints

### Authentication
All endpoints require Bearer token authentication:
```http
Authorization: Bearer {your-token-here}
```

### Required Permissions
- `job_offer.read` - View job offers
- `job_offer.create` - Create job offers
- `job_offer.update` - Update job offers
- `job_offer.delete` - Delete job offers

### 1. List Job Offers with Filtering

**Endpoint:** `GET /api/v1/job-offers`

**Query Parameters:**
- `page` (integer, optional) - Page number (default: 1)
- `per_page` (integer, optional) - Items per page (default: 10, max: 100)
- `filter_position` (string, optional) - Filter by position names (comma-separated)
- `filter_status` (string, optional) - Filter by acceptance status (comma-separated)
- `sort_by` (string, optional) - Sort field: `job_offer_id`, `candidate_name`, `position_name`, `date`, `status`
- `sort_order` (string, optional) - Sort order: `asc`, `desc`

**Example Request:**
```http
GET /api/v1/job-offers?page=1&per_page=10&filter_position=Developer,Manager&sort_by=date&sort_order=desc
```

**Example Response:**
```json
{
    "success": true,
    "message": "Job offers retrieved successfully",
    "data": [
        {
            "id": 1,
            "custom_offer_id": "20241005-SMRU-0001",
            "date": "2024-10-05",
            "candidate_name": "John Doe",
            "position_name": "Software Developer",
            "probation_salary": 35000.00,
            "post_probation_salary": 40000.00,
            "acceptance_deadline": "2024-10-20",
            "acceptance_status": "Pending",
            "note": "Standard job offer with competitive benefits package",
            "created_by": "admin",
            "updated_by": "admin",
            "created_at": "2024-10-05T10:00:00Z",
            "updated_at": "2024-10-05T10:00:00Z"
        }
    ],
    "pagination": {
        "current_page": 1,
        "per_page": 10,
        "total": 25,
        "last_page": 3,
        "from": 1,
        "to": 10,
        "has_more_pages": true
    },
    "filters": {
        "applied_filters": {
            "position": ["Developer", "Manager"],
            "status": []
        }
    }
}
```

### 2. Create Job Offer

**Endpoint:** `POST /api/v1/job-offers`

**Request Body:**
```json
{
    "date": "2024-10-05",
    "candidate_name": "John Doe",
    "position_name": "Software Developer",
    "probation_salary": 35000.00,
    "post_probation_salary": 40000.00,
    "acceptance_deadline": "2024-10-20",
    "acceptance_status": "Pending",
    "note": "Standard job offer with competitive benefits package"
}
```

**Response:** `201 Created`
```json
{
    "success": true,
    "message": "Job offer created successfully",
    "data": {
        "id": 1,
        "custom_offer_id": "20241005-SMRU-0001",
        // ... full job offer object
    }
}
```

### 3. Get Single Job Offer

**Endpoint:** `GET /api/v1/job-offers/{id}`

**Response:** `200 OK`
```json
{
    "success": true,
    "message": "Job offer retrieved successfully",
    "data": {
        // ... full job offer object
    }
}
```

### 4. Update Job Offer

**Endpoint:** `PUT /api/v1/job-offers/{id}`

**Request Body:** Same as create request

**Response:** `200 OK`
```json
{
    "success": true,
    "message": "Job offer updated successfully",
    "data": {
        // ... updated job offer object
    }
}
```

### 5. Delete Job Offer

**Endpoint:** `DELETE /api/v1/job-offers/{id}`

**Response:** `200 OK`
```json
{
    "success": true,
    "message": "Job offer deleted successfully"
}
```

### 6. Get Job Offer by Candidate Name

**Endpoint:** `GET /api/v1/job-offers/by-candidate/{candidateName}`

**Response:** `200 OK`
```json
{
    "success": true,
    "message": "Job offer retrieved successfully",
    "data": {
        // ... job offer object
    }
}
```

### 7. Generate PDF Job Offer Letter

**Endpoint:** `GET /api/v1/job-offers/{custom_offer_id}/pdf`

**Response:** `200 OK` (PDF Download)
- Content-Type: `application/pdf`
- Content-Disposition: `attachment; filename="job-offer-{candidate_name}.pdf"`

---

## Model Structure

### JobOffer Model (`app/Models/JobOffer.php`)

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;

/**
 * @OA\Schema(
 *     schema="JobOffer",
 *     title="Job Offer",
 *     description="Job Offer model",
 *     @OA\Property(property="id", type="integer", format="int64", description="Job offer ID"),
 *     @OA\Property(property="custom_offer_id", type="string", description="Custom offer identifier"),
 *     @OA\Property(property="date", type="string", format="date", description="Offer date"),
 *     @OA\Property(property="candidate_name", type="string", description="Name of the candidate"),
 *     @OA\Property(property="position_name", type="string", description="Name of the position"),
 *     @OA\Property(property="probation_salary", type="number", format="float", description="Probation period salary"),
 *     @OA\Property(property="post_probation_salary", type="number", format="float", description="Post-probation salary"),
 *     @OA\Property(property="acceptance_deadline", type="string", format="date", description="Deadline for acceptance"),
 *     @OA\Property(property="acceptance_status", type="string", description="Status of acceptance"),
 *     @OA\Property(property="note", type="string", description="Additional notes"),
 *     @OA\Property(property="created_by", type="string", description="User who created the record"),
 *     @OA\Property(property="updated_by", type="string", description="User who last updated the record"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp")
 * )
 */
class JobOffer extends Model
{
    use HasFactory, KeepsDeletedModels;

    protected $fillable = [
        'custom_offer_id',
        'date',
        'candidate_name',
        'position_name',
        'probation_salary',
        'post_probation_salary',
        'acceptance_deadline',
        'acceptance_status',
        'note',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'acceptance_deadline' => 'date',
        'probation_salary' => 'decimal:2',
        'post_probation_salary' => 'decimal:2',
    ];
}
```

### Key Features:
- **Soft Delete Support**: Uses `KeepsDeletedModels` for data recovery
- **Factory Support**: Includes `HasFactory` for testing and seeding
- **Type Casting**: Automatic casting for dates and decimal values
- **Mass Assignment Protection**: Defined fillable fields
- **OpenAPI Documentation**: Complete Swagger annotations

---

## Request Validation

### JobOfferRequest (`app/Http/Requests/JobOfferRequest.php`)

```php
public function rules(): array
{
    return [
        'date' => 'required|date',
        'candidate_name' => 'required|string',
        'position_name' => 'required|string',
        'probation_salary' => 'required|numeric|min:0',
        'post_probation_salary' => 'required|numeric|min:0',
        'acceptance_deadline' => 'required|date',
        'acceptance_status' => 'required|string',
        'note' => 'required|string',
    ];
}

public function messages(): array
{
    return [
        'probation_salary.required' => 'The probation salary is required.',
        'probation_salary.numeric' => 'The probation salary must be a valid number.',
        'probation_salary.min' => 'The probation salary must be at least 0.',
        'post_probation_salary.required' => 'The post-probation salary is required.',
        'post_probation_salary.numeric' => 'The post-probation salary must be a valid number.',
        'post_probation_salary.min' => 'The post-probation salary must be at least 0.',
    ];
}
```

### Validation Rules:
- **Required Fields**: All core fields are mandatory
- **Data Types**: Proper validation for dates, strings, and numeric values
- **Business Rules**: Salary values must be non-negative
- **Custom Messages**: User-friendly error messages for salary fields

---

## PDF Generation

### Template: `resources/views/jobOffer.blade.php`

The PDF generation system creates professional job offer letters with the following features:

#### Template Structure:
- **Header**: Company logo and branding
- **Date**: Formatted offer date with superscript
- **Candidate Information**: Personalized greeting
- **Position Details**: Clear position title and responsibilities
- **Salary Information**: Separate probation and post-probation salaries with THB currency formatting
- **Terms & Conditions**: Standard employment terms
- **Acceptance Section**: Signature area for candidate acceptance
- **Footer**: Company contact information

#### Salary Formatting:
```php
// Controller logic for PDF data preparation
$data = [
    'probation_salary' => $jobOffer->probation_salary ? number_format($jobOffer->probation_salary, 2) : 'N/A',
    'post_probation_salary' => $jobOffer->post_probation_salary ? number_format($jobOffer->post_probation_salary, 2) : 'N/A',
    // ... other fields
];
```

#### Template Usage:
```blade
<p>
    The monthly basic salary will be <strong>THB {{ $probation_salary }}</strong> during the probation period and
    <strong>THB {{ $post_probation_salary }}</strong> after passing the probation period.
</p>
```

### PDF Features:
- **A4 Portrait Format**: Professional document layout
- **Currency Formatting**: Proper THB formatting with thousands separators
- **Dynamic Content**: Personalized for each candidate
- **Professional Styling**: Corporate branding and formatting
- **Download Support**: Direct PDF download with proper headers

---

## Swagger Documentation

### Complete OpenAPI Integration

The Job Offer system includes comprehensive Swagger/OpenAPI documentation:

#### Model Schema:
- Complete `@OA\Schema` annotation with all properties
- Proper data types and format specifications
- Detailed descriptions for each field
- Example values for better understanding

#### Controller Documentation:
- All CRUD endpoints fully documented
- Request/response examples with realistic data
- Error handling documentation (400, 401, 403, 404, 422, 500)
- Parameter specifications for filtering and pagination
- Authentication requirements clearly specified

#### Request Body Examples:
```json
{
    "date": "2024-10-05",
    "candidate_name": "John Doe",
    "position_name": "Software Developer",
    "probation_salary": 35000.00,
    "post_probation_salary": 40000.00,
    "acceptance_deadline": "2024-10-20",
    "acceptance_status": "Pending",
    "note": "Standard job offer with competitive benefits package"
}
```

#### Response Examples:
- Detailed success responses with full object structures
- Error responses with proper HTTP status codes
- Pagination metadata for list endpoints
- Filter information for search results

---

## Factory & Seeding

### JobOfferFactory (`database/factories/JobOfferFactory.php`)

```php
public function definition(): array
{
    $probationSalary = $this->faker->numberBetween(25000, 80000);
    $postProbationSalary = $probationSalary + $this->faker->numberBetween(2000, 10000);

    return [
        'custom_offer_id' => $this->generateCustomOfferId(),
        'date' => $this->faker->dateTimeBetween('-6 months', 'now'),
        'candidate_name' => $this->faker->name(),
        'position_name' => $this->faker->randomElement($positions),
        'probation_salary' => $probationSalary,
        'post_probation_salary' => $postProbationSalary,
        'acceptance_deadline' => $this->faker->dateTimeBetween('now', '+30 days'),
        'acceptance_status' => $this->faker->randomElement($statuses),
        'note' => $this->faker->sentence(10),
        'created_by' => $this->faker->randomElement(['admin', 'hr_manager', 'system']),
        'updated_by' => $this->faker->randomElement(['admin', 'hr_manager', 'system']),
    ];
}
```

### Factory Features:
- **Realistic Data**: Generates meaningful test data
- **Salary Logic**: Ensures post-probation salary is higher than probation salary
- **Custom Offer ID**: Generates unique identifiers with subsidiary codes
- **State Methods**: Includes `pending()`, `accepted()`, `declined()` states
- **Date Logic**: Proper date relationships between offer and deadline dates

---

## Error Handling

### Standard Error Responses

#### Validation Errors (422):
```json
{
    "success": false,
    "message": "Validation failed",
    "errors": {
        "probation_salary": ["The probation salary is required."],
        "post_probation_salary": ["The post-probation salary must be a valid number."]
    }
}
```

#### Not Found Errors (404):
```json
{
    "success": false,
    "message": "Job offer not found"
}
```

#### Server Errors (500):
```json
{
    "success": false,
    "message": "Failed to create job offer",
    "error": "Internal server error occurred"
}
```

### Error Handling Features:
- **Consistent Format**: All errors follow the same JSON structure
- **Detailed Messages**: Clear, actionable error descriptions
- **HTTP Status Codes**: Proper status codes for different error types
- **Validation Details**: Field-specific validation error messages

---

## Performance Considerations

### Database Optimization:
- **Indexes**: Proper indexing on `custom_offer_id` (unique) and frequently queried fields
- **Pagination**: Server-side pagination to handle large datasets
- **Selective Queries**: Only fetch necessary fields for list operations

### Caching Strategy:
- **Observer Pattern**: `JobOfferObserver` for cache invalidation
- **Cache Tags**: Proper cache tagging for efficient invalidation
- **Query Optimization**: Optimized queries for filtering and sorting

### API Performance:
- **Resource Classes**: Efficient data transformation with `JobOfferResource`
- **Validation Caching**: Request validation rules are cached
- **Bulk Operations**: Support for efficient bulk data operations

---

## Testing & Quality Assurance

### Test Coverage:
- **Unit Tests**: Model validation and business logic
- **Feature Tests**: API endpoint functionality
- **Integration Tests**: PDF generation and file handling
- **Factory Tests**: Data generation and seeding

### Code Quality:
- **Laravel Pint**: Automated code formatting
- **PHPStan**: Static analysis for code quality
- **Validation**: Comprehensive input validation
- **Error Handling**: Robust error handling throughout

---

## Deployment & Maintenance

### Migration Strategy:
- **Schema Updates**: Clean migration files with proper rollback support
- **Data Migration**: Safe data transformation for existing records
- **Version Control**: Proper versioning for schema changes

### Monitoring:
- **Logging**: Comprehensive logging for debugging and monitoring
- **Performance Metrics**: Query performance monitoring
- **Error Tracking**: Automated error reporting and tracking

---

## Conclusion

The Job Offer Management System v2.0 provides a robust, scalable solution for managing job offers with enhanced salary structure support. The system includes comprehensive API documentation, proper error handling, PDF generation capabilities, and follows Laravel best practices for maintainability and performance.

### Key Improvements in v2.0:
- ✅ **Separate salary fields** for better compensation management
- ✅ **Enhanced PDF generation** with proper currency formatting
- ✅ **Complete Swagger documentation** for API integration
- ✅ **Improved validation** with custom error messages
- ✅ **Factory enhancements** for realistic test data generation
- ✅ **Performance optimizations** for better scalability

The system is production-ready and provides a solid foundation for job offer management within the HRMS ecosystem.
