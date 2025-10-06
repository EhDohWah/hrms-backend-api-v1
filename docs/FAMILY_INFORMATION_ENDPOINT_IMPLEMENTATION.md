# Family Information Endpoint Implementation

## Overview
This document outlines the implementation of the family information update endpoint for the Employee module, which allows updating parent and emergency contact information through the API.

## Implementation Details

### 1. Form Request Class
**File:** `app/Http/Requests/UpdateEmployeeFamilyRequest.php`

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeFamilyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Father
            'father_name' => 'nullable|string|max:100',
            'father_occupation' => 'nullable|string|max:100',
            'father_phone' => 'nullable|string|max:20',

            // Mother
            'mother_name' => 'nullable|string|max:100',
            'mother_occupation' => 'nullable|string|max:100',
            'mother_phone' => 'nullable|string|max:20',

            // Spouse
            'spouse_name' => 'nullable|string|max:100',
            'spouse_phone_number' => 'nullable|string|max:20',

            // Emergency contact
            'emergency_contact_name' => 'nullable|string|max:100',
            'emergency_contact_relationship' => 'nullable|string|max:50',
            'emergency_contact_phone' => 'nullable|string|max:20',
        ];
    }
}
```

### 2. Controller Method
**File:** `app/Http/Controllers/Api/EmployeeController.php`

Added the `updateEmployeeFamilyInformation` method that:
- Uses route model binding for employee parameter
- Maps frontend form fields to database columns
- Includes comprehensive Swagger documentation
- Follows existing controller patterns for error handling and response formatting

### 3. Route Registration
**File:** `routes/api/employees.php`

```php
Route::put('/{employee}/family-information', [EmployeeController::class, 'updateEmployeeFamilyInformation'])
    ->where('employee', '[0-9]+')
    ->middleware('permission:employee.update');
```

## Field Mapping

The endpoint maps frontend form fields to existing database columns:

| Frontend Field | Database Column |
|----------------|-----------------|
| `father_name` | `father_name` |
| `father_occupation` | `father_occupation` |
| `father_phone` | `father_phone_number` |
| `mother_name` | `mother_name` |
| `mother_occupation` | `mother_occupation` |
| `mother_phone` | `mother_phone_number` |
| `spouse_name` | `spouse_name` |
| `spouse_phone_number` | `spouse_phone_number` |
| `emergency_contact_name` | `emergency_contact_person_name` |
| `emergency_contact_relationship` | `emergency_contact_person_relationship` |
| `emergency_contact_phone` | `emergency_contact_person_phone` |

## API Endpoint

### Request
- **Method:** `PUT`
- **URL:** `/v1/employees/{employee}/family-information`
- **Authentication:** Required (Bearer token)
- **Permission:** `employee.update`

### Request Body
```json
{
    "father_name": "James Doe",
    "father_occupation": "Engineer",
    "father_phone": "0812345670",
    "mother_name": "Mary Doe",
    "mother_occupation": "Teacher",
    "mother_phone": "0812345671",
    "spouse_name": "Jane Doe",
    "spouse_phone_number": "0812345673",
    "emergency_contact_name": "John Smith",
    "emergency_contact_relationship": "Brother",
    "emergency_contact_phone": "0812345672"
}
```

### Response (Success)
```json
{
    "success": true,
    "message": "Employee family information updated successfully",
    "data": {
        "father_name": "James Doe",
        "father_occupation": "Engineer",
        "father_phone_number": "0812345670",
        "mother_name": "Mary Doe",
        "mother_occupation": "Teacher",
        "mother_phone_number": "0812345671",
        "spouse_name": "Jane Doe",
        "spouse_phone_number": "0812345673",
        "emergency_contact_person_name": "John Smith",
        "emergency_contact_person_relationship": "Brother",
        "emergency_contact_person_phone": "0812345672"
    }
}
```

### Response (Error)
```json
{
    "success": false,
    "message": "Employee not found"
}
```

## Features

### 1. Validation
- All fields are optional (nullable)
- String length limits enforced
- Phone number format validation

### 2. Security
- Route model binding prevents unauthorized access
- Permission middleware (`employee.update`) required
- Audit trail with `updated_by` field

### 3. Performance
- Cache clearing after updates
- Selective field updates (only provided fields)
- Optimized database queries

### 4. Error Handling
- Comprehensive exception handling
- Structured error responses
- Detailed logging for debugging

## Frontend Integration

The endpoint is designed to work with the provided frontend modal form:

```html
<!-- Add/Edit Family Information Modal -->
<div class="modal fade" id="edit_familyinformation">
  <!-- Modal content with form fields -->
  <form @submit.prevent="submitFamilyForm">
    <!-- Father, Mother, and Emergency Contact sections -->
  </form>
</div>
```

## Usage Examples

### Update Father Information Only
```javascript
PUT /v1/employees/123/family-information
{
    "father_name": "Updated Father Name",
    "father_occupation": "Updated Occupation"
}
```

### Update Emergency Contact Only
```javascript
PUT /v1/employees/123/family-information
{
    "emergency_contact_name": "New Contact",
    "emergency_contact_relationship": "Friend",
    "emergency_contact_phone": "0987654321"
}
```

### Update All Information
```javascript
PUT /v1/employees/123/family-information
{
    "father_name": "James Doe",
    "father_occupation": "Engineer",
    "father_phone": "0812345670",
    "mother_name": "Mary Doe",
    "mother_occupation": "Teacher",
    "mother_phone": "0812345671",
    "spouse_name": "Jane Doe",
    "spouse_phone_number": "0812345673",
    "emergency_contact_name": "John Smith",
    "emergency_contact_relationship": "Brother",
    "emergency_contact_phone": "0812345672"
}
```

## Notes

1. **Partial Updates:** The endpoint supports partial updates - you can send only the fields you want to change.

2. **Field Alignment:** All fields in the endpoint correspond exactly to the frontend modal form fields and existing database columns.

3. **Consistency:** The implementation follows the existing patterns used in other employee update endpoints (`updateEmployeeBasicInformation`, `updateEmployeePersonalInformation`, `updateBankInformation`).

4. **Swagger Documentation:** Complete OpenAPI 3.0 documentation is included in the controller method for API documentation generation.

5. **Route Model Binding:** Uses Laravel's route model binding for automatic employee retrieval and 404 handling.

## Testing

The endpoint can be tested using:
- Postman/Insomnia with the provided request examples
- Laravel's built-in testing framework
- Frontend form integration

## Migration Compatibility

This implementation works with the existing database schema and doesn't require any migration changes. All referenced columns already exist in the `employees` table.
