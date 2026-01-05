# Dynamic Lookup System

## Overview

The lookup system has been enhanced to be fully dynamic, eliminating the need to hardcode lookup types in the controller. The system now automatically discovers all lookup types from the database and provides them through the API.

## Key Features

### 1. **Dynamic Type Discovery**
- Lookup types are automatically discovered from the database
- No need to modify controller code when adding new lookup types
- Types are sorted alphabetically for consistent ordering

### 2. **Enhanced API Endpoints**

#### Get All Lookups
```http
GET /api/lookups
```
Returns all lookup data organized by type:
```json
{
  "bank_name": [
    {"id": 80, "type": "bank_name", "value": "Bangkok Bank", ...},
    {"id": 81, "type": "bank_name", "value": "Kasikorn Bank", ...}
  ],
  "gender": [
    {"id": 1, "type": "gender", "value": "M", ...},
    {"id": 2, "type": "gender", "value": "F", ...}
  ]
}
```

#### Get Available Types
```http
GET /api/lookups/types
```
Returns all available lookup types:
```json
{
  "success": true,
  "data": [
    "bank_name",
    "employee_education",
    "employee_initial_en",
    "gender",
    "nationality"
  ]
}
```

#### Get Lookups by Type
```http
GET /api/lookups/type/{type}
```
Returns all lookup values for a specific type with enhanced error handling:
```json
{
  "success": true,
  "data": [
    {"id": 80, "type": "bank_name", "value": "Bangkok Bank", ...}
  ]
}
```

If type doesn't exist:
```json
{
  "success": false,
  "message": "Lookup type 'invalid_type' does not exist",
  "available_types": ["bank_name", "gender", ...]
}
```

## Model Enhancements

### New Methods in `Lookup` Model

```php
// Get all distinct lookup types
Lookup::getAllTypes()

// Get all lookup data organized by type
Lookup::getAllLookups()

// Check if a lookup type exists
Lookup::typeExists('bank_name')

// Get dynamic validation rules for all types
Lookup::getValidationRules()

// Get validation rule for specific type
Lookup::getValidationRule('gender')
```

## Adding New Lookup Types

### Method 1: Direct Database Insert
```php
use App\Models\Lookup;

// Add new lookup type with values
Lookup::create(['type' => 'department', 'value' => 'Engineering']);
Lookup::create(['type' => 'department', 'value' => 'Marketing']);
Lookup::create(['type' => 'department', 'value' => 'Sales']);
```

### Method 2: Migration
```php
// In a migration file
DB::table('lookups')->insert([
    ['type' => 'department', 'value' => 'Engineering', 'created_at' => now(), 'updated_at' => now()],
    ['type' => 'department', 'value' => 'Marketing', 'created_at' => now(), 'updated_at' => now()],
    ['type' => 'department', 'value' => 'Sales', 'created_at' => now(), 'updated_at' => now()],
]);
```

### Method 3: API Endpoint
```http
POST /api/lookups
Content-Type: application/json

{
  "type": "department",
  "value": "Engineering"
}
```

## Benefits

1. **Zero Code Changes**: New lookup types work immediately without controller modifications
2. **Automatic Discovery**: Types are discovered dynamically from the database
3. **Better Error Handling**: Provides helpful error messages with available types
4. **Consistent API**: All endpoints follow the same patterns
5. **Validation Ready**: Dynamic validation rules generation
6. **Sorted Output**: Types are automatically sorted alphabetically

## Frontend Integration

### JavaScript Example
```javascript
// Get all lookup types
const typesResponse = await fetch('/api/lookups/types');
const { data: types } = await typesResponse.json();

// Get specific lookup values
const bankResponse = await fetch('/api/lookups/type/bank_name');
const { data: banks } = await bankResponse.json();

// Populate select options
banks.forEach(bank => {
  const option = document.createElement('option');
  option.value = bank.value;
  option.textContent = bank.value;
  selectElement.appendChild(option);
});
```

### Vue.js Example
```vue
<template>
  <select v-model="selectedBank">
    <option value="" disabled>Select Bank</option>
    <option v-for="bank in banks" :key="bank.id" :value="bank.value">
      {{ bank.value }}
    </option>
  </select>
</template>

<script>
export default {
  data() {
    return {
      banks: [],
      selectedBank: ''
    }
  },
  async mounted() {
    const response = await fetch('/api/lookups/type/bank_name');
    const { data } = await response.json();
    this.banks = data;
  }
}
</script>
```

## Migration Guide

### Before (Hardcoded)
```php
// Controller had hardcoded types
$lookupTypes = [
    'gender', 'subsidiary', 'employee_status',
    // ... more hardcoded types
];
```

### After (Dynamic)
```php
// Controller uses dynamic discovery
$result = Lookup::getAllLookups();
```

## Performance Considerations

- The system caches results within the same request
- Consider implementing Laravel cache for high-traffic scenarios:

```php
// Example caching implementation
public static function getAllTypes()
{
    return Cache::remember('lookup_types', 3600, function () {
        return self::distinct()->pluck('type')->sort()->values();
    });
}
```

## Conclusion

The dynamic lookup system provides a more maintainable and flexible approach to managing lookup data. It eliminates the need for code changes when adding new lookup types and provides a consistent API for frontend integration.
