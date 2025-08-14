# Grant API Usage Examples

This document provides examples of how to use the updated Grant API endpoint following the RandomUser API pattern.

## Base Endpoint
```
GET /api/grants
```

## URL Structure
The API follows the same pattern as [RandomUser API](https://randomuser.me/api), supporting standard pagination and filtering parameters.

## Query Parameters

### Pagination Parameters

| Parameter | Type | Description | Default | Min | Max |
|-----------|------|-------------|---------|-----|-----|
| `page` | integer | Page number | 1 | 1 | - |
| `per_page` | integer | Number of items per page | 10 | 1 | 100 |

### Filter Parameters

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `filter_subsidiary` | string | Filter by subsidiary (comma-separated) | `SMRU,BHF` |

## API Examples

### Basic Usage

#### Get all grants (default pagination)
```
GET /api/grants
```

#### Get first page with 10 results
```
GET /api/grants?page=1&per_page=10
```

#### Get second page with 15 results per page
```
GET /api/grants?page=2&per_page=15
```

### Filtering Examples

#### Filter by single subsidiary
```
GET /api/grants?filter_subsidiary=SMRU
```

#### Filter by multiple subsidiaries
```
GET /api/grants?filter_subsidiary=SMRU,BHF
```

#### Combined pagination and filtering
```
GET /api/grants?page=1&per_page=20&filter_subsidiary=SMRU,BHF
```

## Response Format

The API returns a standardized JSON response:

```json
{
  "success": true,
  "message": "Grants retrieved successfully",
  "data": [
    {
      "id": 1,
      "code": "S2023-NIH-1234",
      "name": "Malaria Research Initiative - Mae Sot",
      "subsidiary": "SMRU",
      "description": "Comprehensive research program focused on tropical disease prevention...",
      "end_date": "2025-12-31",
      "status": "Active",
      "created_at": "2024-06-25T15:38:59.000000Z",
      "updated_at": "2024-06-25T15:38:59.000000Z",
      "created_by": "grant_manager",
      "updated_by": "admin",
      "grant_items_count": 3,
      "grant_items": [
        {
          "id": 1,
          "grant_position": "Senior Researcher",
          "grant_salary": 75000,
          "grant_benefit": 15000,
          "grant_level_of_effort": 0.75,
          "grant_position_number": 2,
          "grant_id": 1
        }
      ]
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
      "subsidiary": ["SMRU"]
    }
  }
}
```

## Frontend Integration

### JavaScript/TypeScript Example

```javascript
// Using the frontend service method
async function fetchGrants(page = 1, resultsPerPage = 10, subsidiaries = []) {
  const params = {
    page: page,
    per_page: resultsPerPage,
  };
  
  // Add subsidiary filter if provided
  if (subsidiaries.length > 0) {
    params.filter_subsidiary = subsidiaries.join(',');
  }
  
  try {
    const response = await getAllGrants(params);
    return response;
  } catch (error) {
    console.error('Error fetching grants:', error);
    throw error;
  }
}

// Usage examples
fetchGrants(1, 20);                           // First page, 20 items
fetchGrants(2, 10, ['SMRU', 'BHF']);         // Page 2, filter by subsidiaries
```

### React Hook Example

```jsx
import { useState, useEffect } from 'react';

function useGrants(page = 1, perPage = 10, subsidiaries = []) {
  const [grants, setGrants] = useState([]);
  const [pagination, setPagination] = useState({});
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    async function loadGrants() {
      setLoading(true);
      try {
        const params = { page, per_page: perPage };
        if (subsidiaries.length > 0) {
          params.filter_subsidiary = subsidiaries.join(',');
        }
        
        const response = await getAllGrants(params);
        setGrants(response.data);
        setPagination(response.pagination);
        setError(null);
      } catch (err) {
        setError(err);
      } finally {
        setLoading(false);
      }
    }
    
    loadGrants();
  }, [page, perPage, subsidiaries]);

  return { grants, pagination, loading, error };
}
```

## Error Handling

### Validation Errors (422)
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "per_page": ["The per page must be between 1 and 100."]
  }
}
```

### Server Errors (500)
```json
{
  "success": false,
  "message": "Failed to retrieve grants",
  "error": "Database connection error"
}
```

## Comparison with RandomUser API

| Feature | RandomUser API | Grant API |
|---------|---------------|-----------|
| Base URL | `https://randomuser.me/api` | `/api/grants` |
| Results per page | `?results=10` | `?per_page=10` |
| Page number | `?page=2` | `?page=2` |
| Filtering | `?gender[]=female` | `?filter_subsidiary=SMRU,BHF` |
| Response format | Direct array | Wrapped with success/pagination |

## Performance Considerations

- **Optimized Queries**: Uses model scopes for efficient database queries
- **Pagination**: Limits results to prevent memory issues
- **Field Selection**: Only loads necessary fields for list view
- **Eager Loading**: Includes related data efficiently
- **Caching**: Can be cached at the frontend level

## Available Subsidiaries

Based on the system configuration:
- `SMRU` - Shoklo Malaria Research Unit
- `BHF` - British Heart Foundation  
- `MORU` - Mahidol Oxford Tropical Medicine Research Unit
- `OUCRU` - Oxford University Clinical Research Unit

## Rate Limiting

The API endpoint includes rate limiting middleware to prevent abuse:
- Throttle: `grants` (configurable in middleware)
- Monitoring: Pagination performance monitoring enabled