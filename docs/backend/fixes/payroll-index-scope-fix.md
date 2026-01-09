# Payroll Index Method - Missing Scope Fix

## Issue Description

The `PayrollController::index()` method was throwing a 500 Internal Server Error when attempting to filter payrolls by organization. The error occurred because the controller was calling a non-existent `byOrganization()` scope on the Payroll model.

### Error Details

- **HTTP Status**: 500 Internal Server Error
- **Endpoint**: `GET /api/v1/payrolls`
- **Query Parameters**: `page=1&per_page=10&sort_by=created_at&sort_order=desc`
- **Root Cause**: Missing `scopeByOrganization()` method in the Payroll model

### Controller Code (Line 798)

```php
// Apply organization filter if provided
if (! empty($validated['filter_organization'])) {
    $query->byOrganization($validated['filter_organization']);
}
```

The controller was calling `byOrganization()` but the Payroll model only had `scopeBySubsidiary()`.

## Solution

Added the missing `scopeByOrganization()` method to the Payroll model. This scope filters payroll records by organization(s).

### Implementation

**File**: `app/Models/Payroll.php`

**Added Method**:

```php
public function scopeByOrganization($query, $organizations)
{
    if (is_string($organizations)) {
        $organizations = explode(',', $organizations);
    }
    $organizations = array_map('trim', array_filter($organizations));

    return $query->whereHas('employment.employee', function ($q) use ($organizations) {
        $q->whereIn('organization', $organizations);
    });
}
```

### Method Features

1. **Flexible Input**: Accepts both string (comma-separated) and array input
2. **Multiple Organizations**: Supports filtering by multiple organizations at once
3. **Relationship Filtering**: Uses `whereHas` to filter through the employment and employee relationships
4. **Trim & Filter**: Removes whitespace and empty values from the input

### Usage Examples

```php
// Single organization
Payroll::byOrganization('ACME Corp')->get();

// Multiple organizations (comma-separated string)
Payroll::byOrganization('ACME Corp,Tech Inc')->get();

// Multiple organizations (array)
Payroll::byOrganization(['ACME Corp', 'Tech Inc'])->get();

// Combined with other scopes
Payroll::forPagination()
    ->withOptimizedRelations()
    ->byOrganization('ACME Corp')
    ->byDepartment('Engineering')
    ->paginate(10);
```

## Testing

### Verification Script

Created `php_test_codes/test_payroll_index_fix.php` to verify the scope exists and is properly configured.

**Test Results**:
```
✓ scopeByOrganization method EXISTS
✓ Method is public: YES
✓ Number of parameters: 2 (query, organizations)
```

### Related Scopes

The Payroll model now has the following filtering scopes:

1. `scopeByOrganization()` - Filter by organization(s) ✓ **NEW**
2. `scopeBySubsidiary()` - Filter by subsidiary/subsidiaries
3. `scopeByDepartment()` - Filter by department(s)
4. `scopeByPayPeriodDate()` - Filter by pay period date or date range
5. `scopeForPagination()` - Select optimized fields for pagination
6. `scopeWithOptimizedRelations()` - Eager load relationships efficiently
7. `scopeOrderByField()` - Custom sorting by various fields
8. `scopeByStaffId()` - Filter by employee staff ID
9. `scopeWithEmployeeInfo()` - Include employee information

## Impact

- **Fixed**: 500 Internal Server Error on payroll index endpoint
- **Improved**: Consistency between controller and model
- **Enhanced**: Filtering capabilities for payroll records

## Files Modified

1. `app/Models/Payroll.php` - Added `scopeByOrganization()` method
2. `php_test_codes/test_payroll_index_fix.php` - Created verification script (can be deleted after testing)

## Related Files

- `app/Http/Controllers/Api/PayrollController.php` - Uses the scope in index() method (line 798)
- `routes/api/payrolls.php` - Defines the payroll routes

## Notes

- The `scopeBySubsidiary()` method has similar functionality but is named differently
- Both methods can coexist for backward compatibility
- The controller uses `byOrganization()` which is more semantically clear
- All code has been formatted with Laravel Pint

## Date

Fixed: January 9, 2026
