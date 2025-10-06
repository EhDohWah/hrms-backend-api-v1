# Department & Position API Quick Reference

## 🚀 **Quick Start Guide**

This document provides a concise API reference for frontend developers working with the new separated department and position system.

---

## 📋 **Base URLs**
- **Departments:** `/api/departments`
- **Positions:** `/api/positions`

---

## 🏢 **Department Endpoints**

### GET `/api/departments`
**Purpose:** List all departments with optional filtering

**Parameters:**
```typescript
{
  search?: string;           // Search name/description
  is_active?: boolean;       // Filter by status
  sort_by?: string;          // 'name' | 'created_at' | 'positions_count'
  sort_direction?: string;   // 'asc' | 'desc'
  per_page?: number;         // Pagination size
  page?: number;             // Page number
}
```

**Response:**
```typescript
{
  success: boolean;
  message: string;
  data: Department[];
  pagination: {
    current_page: number;
    total: number;
    per_page: number;
    last_page: number;
  };
}

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
```

### GET `/api/departments/{id}`
**Purpose:** Get department details with positions

**Response:**
```typescript
{
  success: boolean;
  message: string;
  data: {
    ...Department;
    positions: Position[];
  };
}
```

### GET `/api/departments/{id}/positions`
**Purpose:** Get all positions in a department

**Parameters:**
```typescript
{
  is_active?: boolean;    // Filter by status
  is_manager?: boolean;   // Filter managers only
}
```

### GET `/api/departments/{id}/managers`
**Purpose:** Get manager positions in a department

### POST `/api/departments`
**Purpose:** Create new department

**Body:**
```typescript
{
  name: string;              // Required, unique
  description?: string;      // Optional
  is_active?: boolean;       // Default: true
}
```

### PUT `/api/departments/{id}`
**Purpose:** Update department

### DELETE `/api/departments/{id}`
**Purpose:** Delete department (cascades to positions)

---

## 👥 **Position Endpoints**

### GET `/api/positions`
**Purpose:** List all positions with optional filtering

**Parameters:**
```typescript
{
  search?: string;           // Search title/department
  department_id?: number;    // Filter by department
  is_active?: boolean;       // Filter by status
  is_manager?: boolean;      // Filter managers
  level?: number;            // Filter by hierarchy level
  sort_by?: string;          // Field to sort by
  sort_direction?: string;   // 'asc' | 'desc'
  per_page?: number;         // Pagination size
  page?: number;             // Page number
}
```

**Response:**
```typescript
{
  success: boolean;
  message: string;
  data: Position[];
  pagination: PaginationMeta;
}

interface Position {
  id: number;
  title: string;
  department_id: number;
  department?: Department;         // When loaded
  manager_name?: string;          // Computed
  level: number;                  // Hierarchy level
  is_manager: boolean;
  is_active: boolean;
  direct_reports_count: number;   // When counted
  is_department_head: boolean;    // Computed
  created_at: string;
  updated_at: string;
}
```

### GET `/api/positions/{id}`
**Purpose:** Get position details with relationships

**Response:**
```typescript
{
  success: boolean;
  message: string;
  data: {
    ...Position;
    department: Department;
    direct_reports?: Position[];  // When loaded
  };
}
```

### GET `/api/positions/{id}/direct-reports`
**Purpose:** Get positions reporting to this position

### POST `/api/positions`
**Purpose:** Create new position

**Body:**
```typescript
{
  title: string;                    // Required
  department_id: number;            // Required
  reports_to_position_id?: number;  // Optional
  level?: number;                   // Auto-calculated if reports_to provided
  is_manager?: boolean;             // Default: false
  is_active?: boolean;              // Default: true
}
```

**Validation:**
- Level 1 positions must be managers
- Level 1 positions cannot report to others
- Cannot report to position in different department

### PUT `/api/positions/{id}`
**Purpose:** Update position

### DELETE `/api/positions/{id}`
**Purpose:** Delete position

---

## 💡 **Common Usage Patterns**

### 1. Department-Position Cascading Selector
```typescript
// 1. Load departments
const departments = await api.get('/departments', {
  params: { is_active: true }
});

// 2. When department selected, load positions
const positions = await api.get(`/departments/${departmentId}/positions`, {
  params: { is_active: true }
});
```

### 2. Manager Selection
```typescript
// Get managers for a department
const managers = await api.get(`/departments/${departmentId}/managers`);

// Or filter positions
const positions = await api.get('/positions', {
  params: { 
    department_id: departmentId, 
    is_manager: true,
    is_active: true 
  }
});
```

### 3. Organizational Hierarchy
```typescript
// Get department with all positions
const response = await api.get(`/departments/${departmentId}`);
const positions = response.data.data.positions;

// Build hierarchy tree
const buildOrgChart = (positions: Position[]) => {
  const positionMap = new Map();
  const roots = [];
  
  // Create nodes
  positions.forEach(pos => {
    positionMap.set(pos.id, { ...pos, children: [] });
  });
  
  // Build tree
  positions.forEach(pos => {
    const node = positionMap.get(pos.id);
    if (pos.reports_to_position_id) {
      const parent = positionMap.get(pos.reports_to_position_id);
      if (parent) parent.children.push(node);
    } else {
      roots.push(node);
    }
  });
  
  return roots;
};
```

### 4. Search with Filters
```typescript
// Advanced position search
const searchPositions = async (filters: {
  search?: string;
  departmentId?: number;
  level?: number;
  isManager?: boolean;
}) => {
  return api.get('/positions', {
    params: {
      search: filters.search,
      department_id: filters.departmentId,
      level: filters.level,
      is_manager: filters.isManager,
      is_active: true,
      per_page: 20
    }
  });
};
```

### 5. Employee Assignment
```typescript
// When assigning employee to position
const assignEmployee = async (employeeId: number, positionId: number) => {
  // Get position details first (includes department)
  const position = await api.get(`/positions/${positionId}`);
  
  // Update employment
  return api.put(`/employees/${employeeId}/employment`, {
    position_id: positionId,
    department_id: position.data.data.department_id
  });
};
```

---

## 🔄 **React Hook Examples**

### Department Hook
```typescript
const useDepartments = (params?: DepartmentParams) => {
  const [departments, setDepartments] = useState<Department[]>([]);
  const [loading, setLoading] = useState(true);
  
  useEffect(() => {
    const fetchDepartments = async () => {
      try {
        setLoading(true);
        const response = await api.get('/departments', { params });
        setDepartments(response.data.data);
      } catch (error) {
        console.error('Failed to fetch departments:', error);
      } finally {
        setLoading(false);
      }
    };
    
    fetchDepartments();
  }, [params]);
  
  return { departments, loading };
};
```

### Position Hook
```typescript
const usePositions = (departmentId?: number) => {
  const [positions, setPositions] = useState<Position[]>([]);
  const [loading, setLoading] = useState(true);
  
  useEffect(() => {
    if (!departmentId) return;
    
    const fetchPositions = async () => {
      try {
        setLoading(true);
        const response = await api.get(`/departments/${departmentId}/positions`);
        setPositions(response.data.data);
      } catch (error) {
        console.error('Failed to fetch positions:', error);
      } finally {
        setLoading(false);
      }
    };
    
    fetchPositions();
  }, [departmentId]);
  
  return { positions, loading };
};
```

### Org Chart Hook
```typescript
const useOrgChart = (departmentId: number) => {
  const [orgChart, setOrgChart] = useState<OrgNode[]>([]);
  const [loading, setLoading] = useState(true);
  
  useEffect(() => {
    const buildChart = async () => {
      try {
        setLoading(true);
        const response = await api.get(`/departments/${departmentId}/positions`);
        const hierarchy = buildHierarchy(response.data.data);
        setOrgChart(hierarchy);
      } catch (error) {
        console.error('Failed to build org chart:', error);
      } finally {
        setLoading(false);
      }
    };
    
    buildChart();
  }, [departmentId]);
  
  return { orgChart, loading };
};
```

---

## 🎨 **Component Examples**

### Department Selector
```typescript
interface DepartmentSelectorProps {
  value?: number;
  onChange: (departmentId: number) => void;
  includeInactive?: boolean;
}

const DepartmentSelector: React.FC<DepartmentSelectorProps> = ({
  value,
  onChange,
  includeInactive = false
}) => {
  const { departments, loading } = useDepartments({ 
    is_active: !includeInactive 
  });
  
  return (
    <select 
      value={value || ''} 
      onChange={(e) => onChange(Number(e.target.value))}
      disabled={loading}
    >
      <option value="">Select Department</option>
      {departments.map(dept => (
        <option key={dept.id} value={dept.id}>
          {dept.name} ({dept.active_positions_count} positions)
        </option>
      ))}
    </select>
  );
};
```

### Position Selector
```typescript
interface PositionSelectorProps {
  departmentId?: number;
  value?: number;
  onChange: (positionId: number) => void;
  managersOnly?: boolean;
}

const PositionSelector: React.FC<PositionSelectorProps> = ({
  departmentId,
  value,
  onChange,
  managersOnly = false
}) => {
  const { positions, loading } = usePositions(departmentId);
  
  const filteredPositions = managersOnly 
    ? positions.filter(p => p.is_manager)
    : positions;
  
  return (
    <select 
      value={value || ''} 
      onChange={(e) => onChange(Number(e.target.value))}
      disabled={loading || !departmentId}
    >
      <option value="">Select Position</option>
      {filteredPositions.map(pos => (
        <option key={pos.id} value={pos.id}>
          {pos.title} {pos.is_manager && '(Manager)'} - Level {pos.level}
        </option>
      ))}
    </select>
  );
};
```

---

## ⚡ **Performance Tips**

1. **Pagination:** Use pagination for large datasets
2. **Caching:** Cache department lists, refresh on changes
3. **Lazy Loading:** Load positions only when department selected
4. **Debouncing:** Debounce search inputs
5. **Batch Requests:** Group related API calls

---

## 🚨 **Error Handling**

### Common Error Codes
- `404`: Department/Position not found
- `422`: Validation errors (hierarchy constraints)
- `409`: Conflict (circular reporting, etc.)

### Example Error Response
```typescript
{
  success: false,
  message: "Validation failed",
  errors: {
    reports_to_position_id: [
      "Position cannot report to someone from a different department"
    ]
  }
}
```

---

## 🔍 **Debugging Tips**

1. **Check Department-Position Relationships:** Ensure position belongs to department
2. **Validate Hierarchy:** Check reporting chains don't create circles
3. **Monitor Performance:** Watch for N+1 queries in dev tools
4. **Test Edge Cases:** Empty departments, single-person departments

---

**Quick Reference Version:** 1.0  
**Last Updated:** September 15, 2025  
**Backend Compatibility:** v1.0+
