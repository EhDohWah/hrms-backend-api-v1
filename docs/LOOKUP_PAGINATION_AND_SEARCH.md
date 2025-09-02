# Lookup Controller - Pagination and Search Implementation

## Overview

The LookupController has been enhanced with comprehensive pagination, filtering, and search capabilities following the established patterns from EmployeeController and GrantController. This implementation provides a powerful and flexible API for managing lookup data.

## Enhanced Features

### 🔍 **1. Paginated Index Method**

**Endpoint:** `GET /api/v1/lookups`

#### Parameters
- `page` (integer, optional): Page number (default: 1)
- `per_page` (integer, optional): Items per page (1-100, default: 10)
- `filter_type` (string, optional): Filter by lookup type(s) - comma-separated
- `search` (string, optional): Search in type and value fields
- `sort_by` (string, optional): Sort field (`type`, `value`, `created_at`, `updated_at`)
- `sort_order` (string, optional): Sort order (`asc`, `desc`)
- `grouped` (boolean, optional): Return legacy grouped format

#### Response Format
```json
{
  "success": true,
  "message": "Lookups retrieved successfully",
  "data": [
    {
      "id": 80,
      "type": "bank_name",
      "value": "Bangkok Bank",
      "created_by": null,
      "updated_by": null,
      "created_at": "2025-09-02T07:23:13.043000Z",
      "updated_at": "2025-09-02T07:23:13.043000Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 5,
    "total": 86,
    "last_page": 18,
    "from": 1,
    "to": 5,
    "has_more_pages": true
  },
  "filters": {
    "applied_filters": {
      "type": ["bank_name"]
    },
    "available_types": ["bank_name", "gender", "nationality", ...]
  }
}
```

### 🔎 **2. Advanced Search Method**

**Endpoint:** `GET /api/v1/lookups/search`

#### Parameters
- `search` (string, optional): General search term for type or value
- `types` (string, optional): Comma-separated list of types to search in
- `value` (string, optional): Search specifically in values
- `page` (integer, optional): Page number (default: 1)
- `per_page` (integer, optional): Items per page (1-50, default: 10)
- `sort_by` (string, optional): Sort field
- `sort_order` (string, optional): Sort order

#### Response Format
```json
{
  "success": true,
  "message": "Search completed successfully",
  "data": [...],
  "pagination": {...},
  "search_info": {
    "search_term": "Bank",
    "searched_types": ["bank_name"],
    "total_found": 8
  }
}
```

### 📋 **3. Backward Compatibility**

The enhanced controller maintains full backward compatibility:

- **Legacy Mode:** Use `?grouped=true` to get the old grouped format
- **Existing Endpoints:** All existing endpoints continue to work unchanged
- **Default Behavior:** Without pagination parameters, returns paginated results

## API Endpoints Summary

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/v1/lookups` | Paginated lookup list with filtering |
| GET | `/api/v1/lookups/search` | Advanced search with flexible criteria |
| GET | `/api/v1/lookups/types` | Get all available lookup types |
| GET | `/api/v1/lookups/type/{type}` | Get lookups by specific type |
| POST | `/api/v1/lookups` | Create new lookup |
| GET | `/api/v1/lookups/{id}` | Get specific lookup |
| PUT | `/api/v1/lookups/{id}` | Update lookup |
| DELETE | `/api/v1/lookups/{id}` | Delete lookup |

## Usage Examples

### Frontend Integration Examples

#### 1. Basic Pagination
```javascript
// Fetch first page with 10 items
const response = await fetch('/api/v1/lookups?page=1&per_page=10');
const data = await response.json();

console.log('Total items:', data.pagination.total);
console.log('Items:', data.data);
```

#### 2. Type Filtering
```javascript
// Get only bank names and gender types
const response = await fetch('/api/v1/lookups?filter_type=bank_name,gender&per_page=20');
const data = await response.json();

console.log('Applied filters:', data.filters.applied_filters);
```

#### 3. Search Functionality
```javascript
// Search for 'Bank' in all fields
const searchResponse = await fetch('/api/v1/lookups/search?search=Bank&per_page=5');
const searchData = await searchResponse.json();

console.log('Search results:', searchData.data);
console.log('Total found:', searchData.search_info.total_found);
```

#### 4. Advanced Search with Type Filtering
```javascript
// Search for 'Commercial' only in bank_name and employment_type
const advancedSearch = await fetch('/api/v1/lookups/search?value=Commercial&types=bank_name,employment_type');
const results = await advancedSearch.json();
```

#### 5. Legacy Grouped Format
```javascript
// Get data in the old grouped format for backward compatibility
const legacyResponse = await fetch('/api/v1/lookups?grouped=true');
const legacyData = await legacyResponse.json();

// legacyData is now organized as: { bank_name: [...], gender: [...], ... }
```

### Vue.js Component Example

```vue
<template>
  <div>
    <!-- Search and Filter Controls -->
    <div class="filters">
      <input v-model="searchTerm" @input="search" placeholder="Search lookups..." />
      
      <select v-model="selectedType" @change="filterByType">
        <option value="">All Types</option>
        <option v-for="type in availableTypes" :key="type" :value="type">
          {{ type.replace('_', ' ').toUpperCase() }}
        </option>
      </select>
      
      <select v-model="perPage" @change="loadLookups">
        <option value="10">10 per page</option>
        <option value="25">25 per page</option>
        <option value="50">50 per page</option>
      </select>
    </div>

    <!-- Results Table -->
    <table>
      <thead>
        <tr>
          <th @click="sort('type')">Type</th>
          <th @click="sort('value')">Value</th>
          <th @click="sort('created_at')">Created</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="lookup in lookups" :key="lookup.id">
          <td>{{ lookup.type }}</td>
          <td>{{ lookup.value }}</td>
          <td>{{ formatDate(lookup.created_at) }}</td>
        </tr>
      </tbody>
    </table>

    <!-- Pagination -->
    <div class="pagination">
      <button @click="previousPage" :disabled="!pagination.from">Previous</button>
      <span>Page {{ pagination.current_page }} of {{ pagination.last_page }}</span>
      <button @click="nextPage" :disabled="!pagination.has_more_pages">Next</button>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      lookups: [],
      pagination: {},
      availableTypes: [],
      searchTerm: '',
      selectedType: '',
      perPage: 10,
      sortBy: 'type',
      sortOrder: 'asc'
    }
  },
  
  async mounted() {
    await this.loadAvailableTypes();
    await this.loadLookups();
  },
  
  methods: {
    async loadLookups() {
      const params = new URLSearchParams({
        per_page: this.perPage,
        sort_by: this.sortBy,
        sort_order: this.sortOrder
      });
      
      if (this.selectedType) params.append('filter_type', this.selectedType);
      if (this.searchTerm) params.append('search', this.searchTerm);
      
      const response = await fetch(`/api/v1/lookups?${params}`);
      const data = await response.json();
      
      this.lookups = data.data;
      this.pagination = data.pagination;
    },
    
    async loadAvailableTypes() {
      const response = await fetch('/api/v1/lookups/types');
      const data = await response.json();
      this.availableTypes = data.data;
    },
    
    async search() {
      if (this.searchTerm.length >= 2 || this.searchTerm.length === 0) {
        await this.loadLookups();
      }
    },
    
    async filterByType() {
      await this.loadLookups();
    },
    
    async sort(field) {
      if (this.sortBy === field) {
        this.sortOrder = this.sortOrder === 'asc' ? 'desc' : 'asc';
      } else {
        this.sortBy = field;
        this.sortOrder = 'asc';
      }
      await this.loadLookups();
    },
    
    async nextPage() {
      if (this.pagination.has_more_pages) {
        this.pagination.current_page++;
        await this.loadLookups();
      }
    },
    
    async previousPage() {
      if (this.pagination.current_page > 1) {
        this.pagination.current_page--;
        await this.loadLookups();
      }
    },
    
    formatDate(dateString) {
      return new Date(dateString).toLocaleDateString();
    }
  }
}
</script>
```

## Performance Considerations

### 1. **Database Optimization**
- Uses Laravel's efficient pagination
- Indexes recommended on `type` and `value` columns
- Query optimization for large datasets

### 2. **Caching Recommendations**
```php
// Add caching for frequently accessed data
public function getAllTypes()
{
    return Cache::remember('lookup_types', 3600, function () {
        return self::distinct()->pluck('type')->sort()->values();
    });
}
```

### 3. **Frontend Optimization**
- Implement debouncing for search inputs
- Use virtual scrolling for large result sets
- Cache type lists in frontend state management

## Error Handling

### Validation Errors (422)
```json
{
  "success": false,
  "message": "At least one search parameter is required.",
  "errors": {
    "search": ["At least one search parameter must be provided."]
  }
}
```

### No Results Found (404)
```json
{
  "success": false,
  "message": "No lookup records found for search: Bank",
  "data": [],
  "pagination": {
    "current_page": 1,
    "per_page": 10,
    "total": 0,
    "last_page": 1,
    "from": null,
    "to": null,
    "has_more_pages": false
  }
}
```

## Migration from Old API

### Before (Non-paginated)
```javascript
// Old approach - all data at once
const response = await fetch('/api/v1/lookups');
const allLookups = await response.json();
// Result: { bank_name: [...], gender: [...], ... }
```

### After (Paginated with Compatibility)
```javascript
// New approach - paginated with better performance
const response = await fetch('/api/v1/lookups?per_page=50');
const paginatedLookups = await response.json();
// Result: { success: true, data: [...], pagination: {...} }

// Or keep old format for compatibility
const legacyResponse = await fetch('/api/v1/lookups?grouped=true');
const groupedLookups = await legacyResponse.json();
// Result: { bank_name: [...], gender: [...], ... }
```

## Testing the Implementation

### Manual Testing Commands

```bash
# Test basic pagination
curl "http://localhost:8000/api/v1/lookups?per_page=5&page=1"

# Test type filtering
curl "http://localhost:8000/api/v1/lookups?filter_type=bank_name&per_page=10"

# Test search
curl "http://localhost:8000/api/v1/lookups/search?search=Bank&per_page=5"

# Test legacy grouped mode
curl "http://localhost:8000/api/v1/lookups?grouped=true"

# Test sorting
curl "http://localhost:8000/api/v1/lookups?sort_by=value&sort_order=desc"
```

## Benefits

1. **📊 Better Performance**: Pagination reduces memory usage and improves response times
2. **🔍 Enhanced Search**: Flexible search capabilities across multiple fields
3. **📱 Mobile Friendly**: Smaller payloads ideal for mobile applications
4. **🔄 Backward Compatible**: Existing integrations continue to work
5. **🎯 Flexible Filtering**: Filter by multiple types and search terms
6. **📈 Scalable**: Handles large datasets efficiently
7. **🛠️ Developer Friendly**: Clear API responses with helpful metadata

This implementation follows Laravel best practices and maintains consistency with other controllers in the system while providing powerful new capabilities for managing lookup data.
