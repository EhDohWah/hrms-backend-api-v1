# Department & Position Separation: Frontend Integration Guide

## Overview

The backend has been updated to separate departments and positions into distinct entities with proper relational structure. This replaces the old `department_positions` table implementation with a more robust, hierarchical system.

## Key Changes Summary

### 🔄 **Migration from Old to New System**

| **Old Implementation** | **New Implementation** |
|------------------------|------------------------|
| Single `department_positions` table | Separate `departments` and `positions` tables |
| String-based department/position names | ID-based relationships with foreign keys |
| No hierarchy support | Multi-level hierarchy with `reports_to_position_id` |
| No organizational structure | Full organizational chart support |

---

## 📊 **Database Structure**

### Departments Table
```sql
CREATE TABLE departments (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) UNIQUE NOT NULL,
    description VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL
);
```

### Positions Table
```sql
CREATE TABLE positions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    department_id BIGINT NOT NULL,
    reports_to_position_id BIGINT NULL,
    level INTEGER DEFAULT 1,
    is_manager BOOLEAN DEFAULT false,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    created_by VARCHAR(255) NULL,
    updated_by VARCHAR(255) NULL,
    
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
    FOREIGN KEY (reports_to_position_id) REFERENCES positions(id) ON DELETE NO ACTION
);
```

---

## 🏗 **Model Relationships**

### Department Model
```php
// Relationships
public function positions(): HasMany
public function activePositions(): HasMany
public function managerPositions(): HasMany

// Computed Properties
public function getPositionsCountAttribute(): int
public function getActivePositionsCountAttribute(): int

// Methods
public function departmentHead()
```

### Position Model
```php
// Relationships
public function department(): BelongsTo
public function reportsTo(): BelongsTo
public function manager(): BelongsTo
public function directReports(): HasMany
public function subordinates(): HasMany
public function activeSubordinates(): HasMany

// Computed Properties
public function getDirectReportsCountAttribute()
public function getManagerNameAttribute()

// Methods
public function isDepartmentHead(): bool
public function getDepartmentManager()
public function peers()
```

---

## 🌐 **API Endpoints**

### Departments API

#### GET `/api/departments`
**Description:** Get all departments with optional filtering
```json
{
  "success": true,
  "message": "Departments retrieved successfully",
  "data": [
    {
      "id": 1,
      "name": "Administration",
      "description": "Administrative operations and support services",
      "is_active": true,
      "positions_count": 8,
      "active_positions_count": 7,
      "created_at": "2024-02-12T00:00:00.000Z",
      "updated_at": "2024-02-12T00:00:00.000Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "total": 19,
    "per_page": 15
  }
}
```

**Query Parameters:**
- `search`: Search department name or description
- `is_active`: Filter by active status (boolean)
- `sort_by`: Field to sort by (`name`, `created_at`, `positions_count`)
- `sort_direction`: Sort direction (`asc`, `desc`)
- `per_page`: Items per page (default: 15)

#### GET `/api/departments/{id}`
**Description:** Get department details with positions
```json
{
  "success": true,
  "message": "Department retrieved successfully",
  "data": {
    "id": 1,
    "name": "Administration",
    "description": "Administrative operations and support services",
    "is_active": true,
    "positions_count": 8,
    "active_positions_count": 7,
    "created_at": "2024-02-12T00:00:00.000Z",
    "updated_at": "2024-02-12T00:00:00.000Z",
    "positions": [
      {
        "id": 1,
        "title": "Administrator",
        "department_id": 1,
        "manager_name": null,
        "level": 1,
        "is_manager": true,
        "is_active": true,
        "direct_reports_count": 7,
        "is_department_head": true
      }
    ]
  }
}
```

#### GET `/api/departments/{id}/positions`
**Description:** Get all positions in a department
```json
{
  "success": true,
  "message": "Department positions retrieved successfully",
  "data": [
    {
      "id": 1,
      "title": "Administrator",
      "department_id": 1,
      "manager_name": null,
      "level": 1,
      "is_manager": true,
      "is_active": true,
      "direct_reports_count": 7,
      "is_department_head": true,
      "created_at": "2024-02-12T00:00:00.000Z",
      "updated_at": "2024-02-12T00:00:00.000Z"
    }
  ]
}
```

**Query Parameters:**
- `is_active`: Filter by active status
- `is_manager`: Filter by manager status

#### GET `/api/departments/{id}/managers`
**Description:** Get manager positions in a department
```json
{
  "success": true,
  "message": "Department managers retrieved successfully",
  "data": [
    {
      "id": 1,
      "title": "Administrator",
      "department_id": 1,
      "manager_name": null,
      "level": 1,
      "is_manager": true,
      "is_active": true,
      "direct_reports_count": 7,
      "is_department_head": true
    }
  ]
}
```

#### POST `/api/departments`
**Description:** Create a new department
```json
{
  "name": "New Department",
  "description": "Department description",
  "is_active": true
}
```

#### PUT `/api/departments/{id}`
**Description:** Update department
```json
{
  "name": "Updated Department Name",
  "description": "Updated description",
  "is_active": false
}
```

#### DELETE `/api/departments/{id}`
**Description:** Delete department (will cascade delete positions)

---

### Positions API

#### GET `/api/positions`
**Description:** Get all positions with optional filtering
```json
{
  "success": true,
  "message": "Positions retrieved successfully",
  "data": [
    {
      "id": 1,
      "title": "Administrator",
      "department_id": 1,
      "department": {
        "id": 1,
        "name": "Administration",
        "description": "Administrative operations and support services",
        "is_active": true
      },
      "manager_name": null,
      "level": 1,
      "is_manager": true,
      "is_active": true,
      "direct_reports_count": 7,
      "is_department_head": true,
      "created_at": "2024-02-12T00:00:00.000Z",
      "updated_at": "2024-02-12T00:00:00.000Z"
    }
  ],
  "pagination": {
    "current_page": 1,
    "total": 150,
    "per_page": 15
  }
}
```

**Query Parameters:**
- `search`: Search position title or department name
- `department_id`: Filter by department ID
- `is_active`: Filter by active status
- `is_manager`: Filter by manager status
- `level`: Filter by hierarchy level
- `sort_by`: Field to sort by
- `sort_direction`: Sort direction

#### GET `/api/positions/{id}`
**Description:** Get position details
```json
{
  "success": true,
  "message": "Position retrieved successfully",
  "data": {
    "id": 1,
    "title": "Administrator",
    "department_id": 1,
    "department": {
      "id": 1,
      "name": "Administration",
      "description": "Administrative operations and support services",
      "is_active": true
    },
    "manager_name": null,
    "level": 1,
    "is_manager": true,
    "is_active": true,
    "direct_reports_count": 7,
    "is_department_head": true,
    "created_at": "2024-02-12T00:00:00.000Z",
    "updated_at": "2024-02-12T00:00:00.000Z",
    "direct_reports": [
      {
        "id": 2,
        "title": "Administrative Officer",
        "level": 2,
        "is_manager": false
      }
    ]
  }
}
```

#### GET `/api/positions/{id}/direct-reports`
**Description:** Get positions that report to this position

#### POST `/api/positions`
**Description:** Create a new position
```json
{
  "title": "New Position Title",
  "department_id": 1,
  "reports_to_position_id": 1,  // optional
  "level": 2,                   // auto-calculated if reports_to is provided
  "is_manager": false,
  "is_active": true
}
```

**Validation Rules:**
- Position cannot report to someone from different department
- Level 1 positions must be managers
- Level 1 positions cannot report to another position

#### PUT `/api/positions/{id}`
**Description:** Update position
```json
{
  "title": "Updated Position Title",
  "department_id": 1,
  "reports_to_position_id": 2,
  "is_manager": true,
  "is_active": false
}
```

#### DELETE `/api/positions/{id}`
**Description:** Delete position

---

## 🔧 **Frontend Integration Points**

### 1. Department Management

#### Department List Component
```typescript
interface Department {
  id: number;
  name: string;
  description?: string;
  is_active: boolean;
  positions_count: number;
  active_positions_count: number;
  created_at: string;
  updated_at: string;
}

// API Call
const fetchDepartments = async (params?: {
  search?: string;
  is_active?: boolean;
  sort_by?: 'name' | 'created_at' | 'positions_count';
  sort_direction?: 'asc' | 'desc';
  per_page?: number;
}) => {
  const response = await api.get('/departments', { params });
  return response.data;
};
```

#### Department Form Component
```typescript
interface DepartmentForm {
  name: string;
  description?: string;
  is_active: boolean;
}

const createDepartment = async (data: DepartmentForm) => {
  const response = await api.post('/departments', data);
  return response.data;
};

const updateDepartment = async (id: number, data: DepartmentForm) => {
  const response = await api.put(`/departments/${id}`, data);
  return response.data;
};
```

### 2. Position Management

#### Position List Component
```typescript
interface Position {
  id: number;
  title: string;
  department_id: number;
  department?: Department;
  manager_name?: string;
  level: number;
  is_manager: boolean;
  is_active: boolean;
  direct_reports_count: number;
  is_department_head: boolean;
  created_at: string;
  updated_at: string;
}

// API Call
const fetchPositions = async (params?: {
  search?: string;
  department_id?: number;
  is_active?: boolean;
  is_manager?: boolean;
  level?: number;
}) => {
  const response = await api.get('/positions', { params });
  return response.data;
};
```

#### Position Form Component
```typescript
interface PositionForm {
  title: string;
  department_id: number;
  reports_to_position_id?: number;
  level?: number;
  is_manager: boolean;
  is_active: boolean;
}

const createPosition = async (data: PositionForm) => {
  const response = await api.post('/positions', data);
  return response.data;
};
```

### 3. Organizational Chart Component

#### Hierarchical Position Display
```typescript
interface OrgChartNode {
  position: Position;
  children: OrgChartNode[];
}

const fetchDepartmentHierarchy = async (departmentId: number) => {
  const response = await api.get(`/departments/${departmentId}/positions`);
  return buildHierarchy(response.data.data);
};

const buildHierarchy = (positions: Position[]): OrgChartNode[] => {
  // Build tree structure based on reports_to_position_id
  const positionMap = new Map<number, OrgChartNode>();
  const roots: OrgChartNode[] = [];
  
  // Create nodes
  positions.forEach(position => {
    positionMap.set(position.id, { position, children: [] });
  });
  
  // Build hierarchy
  positions.forEach(position => {
    const node = positionMap.get(position.id)!;
    if (position.reports_to_position_id) {
      const parent = positionMap.get(position.reports_to_position_id);
      if (parent) {
        parent.children.push(node);
      }
    } else {
      roots.push(node);
    }
  });
  
  return roots;
};
```

### 4. Department-Position Selector Components

#### Cascading Selectors
```typescript
const DepartmentPositionSelector = () => {
  const [selectedDepartment, setSelectedDepartment] = useState<number | null>(null);
  const [availablePositions, setAvailablePositions] = useState<Position[]>([]);
  
  useEffect(() => {
    if (selectedDepartment) {
      fetchDepartmentPositions(selectedDepartment)
        .then(response => setAvailablePositions(response.data));
    }
  }, [selectedDepartment]);
  
  const fetchDepartmentPositions = async (departmentId: number) => {
    return api.get(`/departments/${departmentId}/positions`, {
      params: { is_active: true }
    });
  };
  
  return (
    <div>
      <DepartmentSelect 
        value={selectedDepartment}
        onChange={setSelectedDepartment}
      />
      <PositionSelect 
        positions={availablePositions}
        disabled={!selectedDepartment}
      />
    </div>
  );
};
```

### 5. Manager Selector Component

```typescript
const ManagerSelector = ({ departmentId }: { departmentId: number }) => {
  const [managers, setManagers] = useState<Position[]>([]);
  
  useEffect(() => {
    if (departmentId) {
      fetchDepartmentManagers(departmentId)
        .then(response => setManagers(response.data));
    }
  }, [departmentId]);
  
  const fetchDepartmentManagers = async (departmentId: number) => {
    return api.get(`/departments/${departmentId}/managers`);
  };
  
  return (
    <select>
      <option value="">Select Manager</option>
      {managers.map(manager => (
        <option key={manager.id} value={manager.id}>
          {manager.title} (Level {manager.level})
        </option>
      ))}
    </select>
  );
};
```

---

## 🚨 **Migration Considerations**

### Data Migration Status
- ✅ Old `department_positions` table data has been migrated
- ✅ All related tables updated with new foreign keys
- ✅ Migration commands available for data transformation

### Frontend Updates Required

1. **Remove References to Old Structure:**
   - Replace any hardcoded department/position string combinations
   - Update forms that used department_position_id
   - Remove old department-position lookup tables

2. **Update API Calls:**
   - Change from string-based to ID-based parameters
   - Update response parsing for new structure
   - Add support for hierarchical data

3. **Add New Features:**
   - Implement organizational chart visualization
   - Add hierarchy-aware position selection
   - Support manager/subordinate relationships

4. **Validation Updates:**
   - Add department-position relationship validation
   - Implement hierarchy level constraints
   - Add manager position requirements

---

## 📝 **Common Use Cases**

### 1. Employee Assignment
```typescript
// When assigning employee to position
const assignEmployee = async (employeeId: number, positionId: number) => {
  // Position automatically includes department relationship
  const response = await api.put(`/employees/${employeeId}/employment`, {
    position_id: positionId
    // department_id is derived from position
  });
};
```

### 2. Reporting Structure
```typescript
// Get employee's reporting chain
const getReportingChain = async (positionId: number) => {
  const position = await api.get(`/positions/${positionId}`);
  const chain = [];
  let current = position.data.data;
  
  while (current.reports_to_position_id) {
    const manager = await api.get(`/positions/${current.reports_to_position_id}`);
    chain.push(manager.data.data);
    current = manager.data.data;
  }
  
  return chain;
};
```

### 3. Department Statistics
```typescript
// Get department overview with position counts
const getDepartmentOverview = async () => {
  const departments = await api.get('/departments', {
    params: { 
      is_active: true,
      sort_by: 'positions_count',
      sort_direction: 'desc'
    }
  });
  
  return departments.data.data.map(dept => ({
    ...dept,
    utilization: dept.active_positions_count / dept.positions_count
  }));
};
```

---

## ⚠️ **Important Notes**

1. **Cascade Deletes:** Deleting a department will delete all its positions
2. **Hierarchy Validation:** Positions can only report to positions in the same department
3. **Manager Requirements:** Level 1 positions must be managers
4. **Performance:** Use pagination for large datasets and include only necessary relationships
5. **Caching:** Position hierarchies are cached for performance - refresh when structure changes

---

## 🔗 **Related Documentation**

- [Employment System Integration](./EMPLOYMENT_CONTROLLER_CACHING_UPDATE.md)
- [Grant Allocation Updates](./GRANT_ITEM_BUDGET_LINE_CODE_FIX.md)
- [API Documentation](../storage/api-docs/api-docs.json)

---

**Last Updated:** September 15, 2025  
**Backend Version:** v1.0  
**Migration Status:** Complete
