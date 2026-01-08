# Payroll System Analysis - Sections 3 & 4: API Endpoints & Controllers

## SECTION 3: Existing API Endpoints

### Question 9: Current Payroll API Endpoints

**File**: `routes/api/payroll.php`

```php
Route::middleware(['auth:sanctum'])->group(function () {
    // Payroll routes - Uses employee_salary permission (main payroll submenu)
    Route::prefix('payrolls')->group(function () {
        // READ Operations (employee_salary.read permission)
        Route::get('/', [PayrollController::class, 'index'])
            ->middleware('permission:employee_salary.read');
        
        Route::get('/search', [PayrollController::class, 'search'])
            ->middleware('permission:employee_salary.read');
        
        Route::get('/employee-employment', [PayrollController::class, 'getEmployeeEmploymentDetail'])
            ->middleware('permission:employee_salary.read');
        
        Route::get('/employee-employment-calculated', [PayrollController::class, 'getEmployeeEmploymentDetailWithCalculations'])
            ->middleware('permission:employee_salary.read');
        
        Route::get('/preview-advances', [PayrollController::class, 'previewAdvances'])
            ->middleware('permission:employee_salary.read');
        
        Route::get('/tax-summary/{id}', [PayrollController::class, 'getTaxSummary'])
            ->middleware('permission:employee_salary.read');
        
        Route::get('/{id}', [PayrollController::class, 'show'])
            ->middleware('permission:employee_salary.read');
        
        Route::post('/calculate', [PayrollController::class, 'calculatePayroll'])
            ->middleware('permission:employee_salary.read');

        // WRITE Operations (employee_salary.edit permission)
        Route::post('/', [PayrollController::class, 'store'])
            ->middleware('permission:employee_salary.edit');
        
        Route::put('/{id}', [PayrollController::class, 'update'])
            ->middleware('permission:employee_salary.edit');
        
        Route::delete('/{id}', [PayrollController::class, 'destroy'])
            ->middleware('permission:employee_salary.edit');
        
        Route::post('/bulk-calculate', [PayrollController::class, 'bulkCalculatePayroll'])
            ->middleware('permission:employee_salary.edit');

        // Bulk Payroll Creation routes with real-time progress tracking
        Route::prefix('bulk')->middleware('permission:employee_salary.edit')->group(function () {
            Route::post('/preview', [BulkPayrollController::class, 'preview']);
            Route::post('/create', [BulkPayrollController::class, 'create']);
            Route::get('/status/{batchId}', [BulkPayrollController::class, 'status']);
            Route::get('/errors/{batchId}', [BulkPayrollController::class, 'downloadErrors']);
        });
    });

    // Inter-organization advance routes - Uses payroll_items permission
    Route::prefix('inter-organization-advances')->middleware('permission:payroll_items.read')->group(function () {
        Route::get('/', [InterOrganizationAdvanceController::class, 'index']);
        Route::get('/{id}', [InterOrganizationAdvanceController::class, 'show']);
        Route::post('/', [InterOrganizationAdvanceController::class, 'store'])
            ->middleware('permission:payroll_items.edit');
        Route::put('/{id}', [InterOrganizationAdvanceController::class, 'update'])
            ->middleware('permission:payroll_items.edit');
        Route::delete('/{id}', [InterOrganizationAdvanceController::class, 'destroy'])
            ->middleware('permission:payroll_items.edit');
    });

    // Payroll grant allocation routes - Uses payroll_items permission
    Route::prefix('payroll-grant-allocations')->middleware('permission:payroll_items.read')->group(function () {
        Route::get('/', [PayrollGrantAllocationController::class, 'index']);
        Route::get('/{id}', [PayrollGrantAllocationController::class, 'show']);
        Route::post('/', [PayrollGrantAllocationController::class, 'store'])
            ->middleware('permission:payroll_items.edit');
        Route::put('/{id}', [PayrollGrantAllocationController::class, 'update'])
            ->middleware('permission:payroll_items.edit');
        Route::delete('/{id}', [PayrollGrantAllocationController::class, 'destroy'])
            ->middleware('permission:payroll_items.edit');
    });
});
```

### Endpoint Summary Table

| Method | Endpoint | Permission | Description |
|--------|----------|------------|-------------|
| GET | `/api/v1/payrolls` | employee_salary.read | List all payrolls with pagination/filters |
| GET | `/api/v1/payrolls/search` | employee_salary.read | Search payrolls by staff ID |
| GET | `/api/v1/payrolls/{id}` | employee_salary.read | Get single payroll details |
| GET | `/api/v1/payrolls/employee-employment` | employee_salary.read | Get employee employment details |
| GET | `/api/v1/payrolls/employee-employment-calculated` | employee_salary.read | Get employee with payroll calculations |
| GET | `/api/v1/payrolls/preview-advances` | employee_salary.read | Preview inter-org advances |
| GET | `/api/v1/payrolls/tax-summary/{id}` | employee_salary.read | Get tax summary for payroll |
| POST | `/api/v1/payrolls/calculate` | employee_salary.read | Calculate payroll (preview) |
| POST | `/api/v1/payrolls` | employee_salary.edit | Create new payroll |
| PUT | `/api/v1/payrolls/{id}` | employee_salary.edit | Update existing payroll |
| DELETE | `/api/v1/payrolls/{id}` | employee_salary.edit | Delete payroll |
| POST | `/api/v1/payrolls/bulk-calculate` | employee_salary.edit | Bulk calculate payrolls |
| POST | `/api/v1/payrolls/bulk/preview` | employee_salary.edit | Preview bulk payroll creation |
| POST | `/api/v1/payrolls/bulk/create` | employee_salary.edit | Create bulk payrolls |
| GET | `/api/v1/payrolls/bulk/status/{batchId}` | employee_salary.edit | Get bulk creation status |
| GET | `/api/v1/payrolls/bulk/errors/{batchId}` | employee_salary.edit | Download bulk creation errors |

---

### Question 10: PayrollController Methods

**File**: `app/Http/Controllers/Api/PayrollController.php`

#### Main Controller Methods:

```php
class PayrollController extends Controller
{
    // 1. LIST PAYROLLS - Main endpoint for fetching payroll data
    public function index(Request $request)
    {
        // Validates parameters
        // Builds query with scopes: forPagination(), withOptimizedRelations()
        // Applies filters: search, organization, department, position, date_range
        // Applies sorting: organization, department, staff_id, employee_name, etc.
        // Returns paginated results with metadata
    }

    // 2. SEARCH PAYROLLS - Search by staff ID
    public function search(Request $request)
    {
        // Similar to index but focused on staff_id search
        // Returns matching payroll records
    }

    // 3. SHOW SINGLE PAYROLL
    public function show($id)
    {
        // Fetches single payroll with all relationships
        // Returns complete payroll details
    }

    // 4. GET EMPLOYEE EMPLOYMENT DETAILS
    public function getEmployeeEmploymentDetail(Request $request)
    {
        // Fetches employee with employment and funding allocations
        // No calculations, just data
    }

    // 5. GET EMPLOYEE EMPLOYMENT WITH CALCULATIONS
    public function getEmployeeEmploymentDetailWithCalculations(Request $request)
    {
        // Fetches employee data
        // If pay_period_date provided, calculates payroll for all allocations
        // Returns allocation_calculations and summary_totals
    }

    // 6. PREVIEW ADVANCES
    public function previewAdvances(Request $request)
    {
        // Previews inter-organization advances needed for employee
        // Shows which grants require advances from other organizations
    }

    // 7. GET TAX SUMMARY
    public function getTaxSummary($id)
    {
        // Returns tax calculation details for a payroll
        // Shows tax brackets, deductions, etc.
    }

    // 8. CALCULATE PAYROLL (Preview)
    public function calculatePayroll(Request $request)
    {
        // Calculates payroll without saving
        // Returns preview of all salary components
    }

    // 9. CREATE PAYROLL
    public function store(Request $request)
    {
        // Validates input
        // Creates new payroll record
        // Handles inter-org advances if needed
    }

    // 10. UPDATE PAYROLL
    public function update(Request $request, $id)
    {
        // Validates input
        // Updates existing payroll
        // Recalculates if needed
    }

    // 11. DELETE PAYROLL
    public function destroy($id)
    {
        // Soft deletes or hard deletes payroll
        // Handles related records
    }

    // 12. BULK CALCULATE
    public function bulkCalculatePayroll(Request $request)
    {
        // Calculates payroll for multiple employees
        // Returns array of calculations
    }
}
```

#### Key Implementation Details:

**1. Index Method - Filtering & Pagination:**

```php
public function index(Request $request)
{
    // Validation
    $validated = $request->validate([
        'page' => 'integer|min:1',
        'per_page' => 'integer|min:1|max:100',
        'search' => 'string|nullable|max:255',
        'filter_organization' => 'string|nullable',
        'filter_department' => 'string|nullable',
        'filter_position' => 'string|nullable',
        'filter_date_range' => 'string|nullable',
        'sort_by' => 'string|nullable',
        'sort_order' => 'string|nullable|in:asc,desc',
    ]);

    // Build query
    $query = Payroll::forPagination()
        ->withOptimizedRelations();

    // Apply search
    if (!empty($validated['search'])) {
        $query->whereHas('employment.employee', function ($q) use ($searchTerm) {
            $q->where('staff_id', 'LIKE', "%{$searchTerm}%")
              ->orWhere('first_name_en', 'LIKE', "%{$searchTerm}%")
              ->orWhere('last_name_en', 'LIKE', "%{$searchTerm}%");
        });
    }

    // Apply filters
    if (!empty($validated['filter_organization'])) {
        $query->byOrganization($validated['filter_organization']);
    }

    if (!empty($validated['filter_department'])) {
        $query->byDepartment($validated['filter_department']);
    }

    if (!empty($validated['filter_date_range'])) {
        $query->byPayPeriodDate($validated['filter_date_range']);
    }

    // Apply sorting
    $query->orderByField($sortBy, $sortOrder);

    // Paginate
    $payrolls = $query->paginate($perPage);

    // Return response
    return response()->json([
        'success' => true,
        'data' => $payrolls->items(),
        'pagination' => [
            'current_page' => $payrolls->currentPage(),
            'per_page' => $payrolls->perPage(),
            'total' => $payrolls->total(),
            'last_page' => $payrolls->lastPage(),
            'from' => $payrolls->firstItem(),
            'to' => $payrolls->lastItem(),
            'has_more_pages' => $payrolls->hasMorePages()
        ]
    ]);
}
```

---

### Question 11: Example API Response from GET /api/payrolls

```json
{
  "success": true,
  "message": "Payrolls retrieved successfully",
  "data": [
    {
      "id": 1,
      "employment_id": 123,
      "employee_funding_allocation_id": 456,
      "gross_salary": 50000.00,
      "gross_salary_by_FTE": 25000.00,
      "compensation_refund": 0.00,
      "thirteen_month_salary": 2083.33,
      "thirteen_month_salary_accured": 25000.00,
      "pvd": 1875.00,
      "saving_fund": 0.00,
      "employer_social_security": 450.00,
      "employee_social_security": 450.00,
      "employer_health_welfare": 0.00,
      "employee_health_welfare": 150.00,
      "tax": 1200.00,
      "net_salary": 24283.33,
      "total_salary": 27083.33,
      "total_pvd": 3750.00,
      "total_saving_fund": 0.00,
      "salary_bonus": 0.00,
      "total_income": 27083.33,
      "employer_contribution": 2325.00,
      "total_deduction": 2800.00,
      "notes": null,
      "pay_period_date": "2025-01-31",
      "created_at": "2025-01-15T10:30:00.000000Z",
      "updated_at": "2025-01-15T10:30:00.000000Z",
      "employment": {
        "id": 123,
        "employee_id": 789,
        "employment_type": "Full-time",
        "pay_method": "Monthly",
        "start_date": "2024-01-01",
        "pass_probation_salary": 50000.00,
        "status": true,
        "employee": {
          "id": 789,
          "staff_id": "0001",
          "first_name_en": "John",
          "last_name_en": "Doe",
          "organization": "SMRU"
        },
        "department": {
          "id": 5,
          "name": "IT"
        },
        "position": {
          "id": 12,
          "title": "Senior Developer",
          "department_id": 5
        }
      },
      "employee_funding_allocation": {
        "id": 456,
        "employee_id": 789,
        "employment_id": 123,
        "grant_item_id": 234,
        "fte": 0.50,
        "allocation_type": "grant",
        "allocated_amount": 25000.00,
        "salary_type": "pass_probation_salary",
        "status": "active",
        "start_date": "2024-01-01",
        "end_date": null
      }
    }
  ],
  "pagination": {
    "current_page": 1,
    "per_page": 10,
    "total": 125,
    "last_page": 13,
    "from": 1,
    "to": 10,
    "has_more_pages": true
  },
  "filters": {
    "applied_filters": {
      "organization": ["SMRU"],
      "department": ["IT"],
      "payslip_date": "2025-01-01,2025-01-31"
    },
    "available_options": {
      "subsidiaries": ["SMRU", "BHF"],
      "departments": ["IT", "HR", "Finance", "Administration"]
    }
  }
}
```

---

### Question 12: Pagination, Filtering, and Query Parameters

#### Supported Query Parameters:

| Parameter | Type | Description | Example |
|-----------|------|-------------|---------|
| `page` | integer | Page number (1-based) | `page=1` |
| `per_page` | integer | Items per page (1-100) | `per_page=20` |
| `search` | string | Search by staff_id, name | `search=John` |
| `filter_organization` | string | Filter by org (comma-separated) | `filter_organization=SMRU,BHF` |
| `filter_department` | string | Filter by dept (comma-separated) | `filter_department=IT,HR` |
| `filter_position` | string | Filter by position | `filter_position=Developer` |
| `filter_date_range` | string | Date range (YYYY-MM-DD,YYYY-MM-DD) | `filter_date_range=2025-01-01,2025-01-31` |
| `filter_payslip_date` | string | Single date (legacy) | `filter_payslip_date=2025-01-31` |
| `sort_by` | string | Sort field | `sort_by=employee_name` |
| `sort_order` | string | Sort direction (asc/desc) | `sort_order=asc` |

#### Sort Options:

- `organization` - Sort by organization name
- `department` - Sort by department name
- `staff_id` - Sort by employee staff ID
- `employee_name` - Sort by employee name
- `basic_salary` - Sort by salary (fallback to created_at due to encryption)
- `payslip_date` - Sort by pay_period_date
- `created_at` - Sort by creation date (default)
- `last_7_days` - Filter to last 7 days
- `last_month` - Filter to last month
- `recently_added` - Sort by most recent

#### Example API Calls:

**1. Get first page with 20 items:**
```
GET /api/v1/payrolls?page=1&per_page=20
```

**2. Filter by organization:**
```
GET /api/v1/payrolls?filter_organization=SMRU
```

**3. Filter by multiple departments:**
```
GET /api/v1/payrolls?filter_department=IT,HR,Finance
```

**4. Filter by date range:**
```
GET /api/v1/payrolls?filter_date_range=2025-01-01,2025-01-31
```

**5. Search by employee name:**
```
GET /api/v1/payrolls?search=John
```

**6. Combined filters with sorting:**
```
GET /api/v1/payrolls?filter_organization=SMRU&filter_department=IT&filter_date_range=2025-01-01,2025-01-31&sort_by=employee_name&sort_order=asc&page=1&per_page=20
```

#### Implementation in Model Scopes:

```php
// File: app/Models/Payroll.php

public function scopeBySubsidiary($query, $subsidiaries)
{
    if (is_string($subsidiaries)) {
        $subsidiaries = explode(',', $subsidiaries);
    }
    $subsidiaries = array_map('trim', array_filter($subsidiaries));

    return $query->whereHas('employment.employee', function ($q) use ($subsidiaries) {
        $q->whereIn('organization', $subsidiaries);
    });
}

public function scopeByDepartment($query, $departments)
{
    if (is_string($departments)) {
        $departments = explode(',', $departments);
    }
    $departments = array_map('trim', array_filter($departments));

    return $query->whereHas('employment.department', function ($q) use ($departments) {
        $q->whereIn('name', $departments);
    });
}

public function scopeByPayPeriodDate($query, $dateFilter)
{
    if (strpos($dateFilter, ',') !== false) {
        // Date range filter
        $dates = explode(',', $dateFilter);
        if (count($dates) === 2) {
            $startDate = trim($dates[0]);
            $endDate = trim($dates[1]);
            if ($startDate && $endDate) {
                return $query->whereBetween('pay_period_date', [$startDate, $endDate]);
            }
        }
    } else {
        // Single date filter
        return $query->whereDate('pay_period_date', $dateFilter);
    }
    return $query;
}
```

---

## SECTION 4: Frontend Data Fetching

### Question 13: payrollService.js File

**File**: `src/services/payroll.service.js`

```javascript
import { apiService } from '@/services/api.service';
import { API_ENDPOINTS } from '@/config/api.config';

class PayrollService {
  // Get employee employment details
  async getEmployeeEmploymentDetails(employeeId) {
    const base = API_ENDPOINTS.PAYROLL.EMPLOYEE_EMPLOYMENT;
    const url = `${base}?employee_id=${encodeURIComponent(employeeId)}`;
    return apiService.get(url);
  }

  // Get employee employment details with calculations
  async getEmployeeEmploymentDetailsWithCalculations(employeeId, payPeriodDate = null) {
    const base = API_ENDPOINTS.PAYROLL.EMPLOYEE_EMPLOYMENT_CALCULATED;
    let url = `${base}?employee_id=${encodeURIComponent(employeeId)}`;
    if (payPeriodDate) {
      url += `&pay_period_date=${encodeURIComponent(payPeriodDate)}`;
    }
    return apiService.get(url);
  }

  // Preview advances for employee payroll
  async previewAdvances(employeeId, payPeriodDate) {
    const base = API_ENDPOINTS.PAYROLL.PREVIEW_ADVANCES;
    const url = `${base}?employee_id=${encodeURIComponent(employeeId)}&pay_period_date=${encodeURIComponent(payPeriodDate)}`;
    return apiService.get(url);
  }

  // Get payrolls with pagination, filtering, and search
  async getPayrolls(params = {}) {
    const queryParams = new URLSearchParams();

    // Normalize pagination to be 1-based and valid
    if (params && Object.prototype.hasOwnProperty.call(params, 'page')) {
      const pageNum = Number(params.page);
      params.page = Number.isFinite(pageNum) && pageNum >= 1 ? pageNum : 1;
    }
    if (params && Object.prototype.hasOwnProperty.call(params, 'per_page')) {
      const perNum = Number(params.per_page);
      params.per_page = Number.isFinite(perNum) && perNum > 0 ? perNum : 10;
    }

    // Add all parameters to query string
    Object.keys(params).forEach(key => {
      if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
        queryParams.append(key, params[key]);
      }
    });

    const queryString = queryParams.toString();
    const url = queryString ? `${API_ENDPOINTS.PAYROLL.LIST}?${queryString}` : API_ENDPOINTS.PAYROLL.LIST;

    return apiService.get(url);
  }

  // Search payrolls by staff ID or employee details
  async searchPayrolls(params = {}) {
    const queryParams = new URLSearchParams();

    // Normalize pagination
    if (params && Object.prototype.hasOwnProperty.call(params, 'page')) {
      const pageNum = Number(params.page);
      params.page = Number.isFinite(pageNum) && pageNum >= 1 ? pageNum : 1;
    }
    if (params && Object.prototype.hasOwnProperty.call(params, 'per_page')) {
      const perNum = Number(params.per_page);
      params.per_page = Number.isFinite(perNum) && perNum > 0 ? perNum : 10;
    }

    Object.keys(params).forEach(key => {
      if (params[key] !== null && params[key] !== undefined && params[key] !== '') {
        queryParams.append(key, params[key]);
      }
    });

    const queryString = queryParams.toString();
    const url = queryString ? `${API_ENDPOINTS.PAYROLL.SEARCH}?${queryString}` : API_ENDPOINTS.PAYROLL.SEARCH;

    return apiService.get(url);
  }

  // Get payroll by ID
  async getPayrollById(id) {
    const endpoint = API_ENDPOINTS.PAYROLL.DETAILS.replace(':id', id);
    return await apiService.get(endpoint);
  }

  // Get tax summary for a payroll
  async getTaxSummary(id) {
    const endpoint = API_ENDPOINTS.PAYROLL.TAX_SUMMARY.replace(':id', id);
    return await apiService.get(endpoint);
  }

  // Create a new payroll
  async createPayroll(payrollData) {
    return await apiService.post(API_ENDPOINTS.PAYROLL.CREATE, payrollData);
  }

  // Update an existing payroll
  async updatePayroll(id, payrollData) {
    const endpoint = API_ENDPOINTS.PAYROLL.UPDATE.replace(':id', id);
    return await apiService.put(endpoint, payrollData);
  }

  // Delete a payroll
  async deletePayroll(id) {
    const endpoint = API_ENDPOINTS.PAYROLL.DELETE.replace(':id', id);
    return await apiService.delete(endpoint);
  }

  // Calculate payroll with automated tax calculations
  async calculatePayroll(calculationData) {
    return await apiService.post(API_ENDPOINTS.PAYROLL.CALCULATE, calculationData);
  }

  // Calculate payroll for multiple employees
  async bulkCalculatePayroll(bulkData) {
    return await apiService.post(API_ENDPOINTS.PAYROLL.BULK_CALCULATE, bulkData);
  }

  // Bulk Payroll Creation with Real-Time Progress Tracking
  async bulkPreview(data) {
    return await apiService.post(API_ENDPOINTS.PAYROLL.BULK_PREVIEW || '/payrolls/bulk/preview', data);
  }

  async bulkCreate(data) {
    return await apiService.post(API_ENDPOINTS.PAYROLL.BULK_CREATE_NEW || '/payrolls/bulk/create', data);
  }

  async getBatchStatus(batchId) {
    const endpoint = (API_ENDPOINTS.PAYROLL.BULK_STATUS || '/payrolls/bulk/status/:batchId').replace(':batchId', batchId);
    return await apiService.get(endpoint);
  }

  async downloadBatchErrors(batchId) {
    const endpoint = (API_ENDPOINTS.PAYROLL.BULK_ERRORS || '/payrolls/bulk/errors/:batchId').replace(':batchId', batchId);
    return await apiService.get(endpoint, { responseType: 'blob' });
  }
}

export const payrollService = new PayrollService();
```

### API Endpoints Configuration

**File**: `src/config/api.config.js`

```javascript
export const API_ENDPOINTS = {
  PAYROLL: {
    LIST: '/payrolls',
    SEARCH: '/payrolls/search',
    DETAILS: '/payrolls/:id',
    CREATE: '/payrolls',
    UPDATE: '/payrolls/:id',
    DELETE: '/payrolls/:id',
    CALCULATE: '/payrolls/calculate',
    BULK_CALCULATE: '/payrolls/bulk-calculate',
    EMPLOYEE_EMPLOYMENT: '/payrolls/employee-employment',
    EMPLOYEE_EMPLOYMENT_CALCULATED: '/payrolls/employee-employment-calculated',
    PREVIEW_ADVANCES: '/payrolls/preview-advances',
    TAX_SUMMARY: '/payrolls/tax-summary/:id',
    BULK_PREVIEW: '/payrolls/bulk/preview',
    BULK_CREATE_NEW: '/payrolls/bulk/create',
    BULK_STATUS: '/payrolls/bulk/status/:batchId',
    BULK_ERRORS: '/payrolls/bulk/errors/:batchId'
  }
};
```




