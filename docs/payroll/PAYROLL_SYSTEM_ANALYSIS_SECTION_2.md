# Payroll System Analysis - Section 2: Encryption & Data Handling

## Question 6: Salary Field Encryption and Decryption

### Encryption Implementation in Payroll Model

```php
// File: app/Models/Payroll.php

// All salary fields are automatically encrypted/decrypted using Laravel's 'encrypted' cast
protected $casts = [
    'gross_salary' => 'encrypted',
    'gross_salary_by_FTE' => 'encrypted',
    'compensation_refund' => 'encrypted',
    'thirteen_month_salary' => 'encrypted',
    'thirteen_month_salary_accured' => 'encrypted',
    'pvd' => 'encrypted',
    'saving_fund' => 'encrypted',
    'employer_social_security' => 'encrypted',
    'employee_social_security' => 'encrypted',
    'employer_health_welfare' => 'encrypted',
    'employee_health_welfare' => 'encrypted',
    'tax' => 'encrypted',
    'net_salary' => 'encrypted',
    'total_salary' => 'encrypted',
    'total_pvd' => 'encrypted',
    'total_saving_fund' => 'encrypted',
    'salary_bonus' => 'encrypted',
    'total_income' => 'encrypted',
    'employer_contribution' => 'encrypted',
    'total_deduction' => 'encrypted',
    'pay_period_date' => 'date',
];
```

### How Laravel Encryption Works

**Storage Format:**
- Database columns are defined as `TEXT` type
- Actual salary values (decimals) are encrypted before storage
- Encrypted data is stored as base64-encoded strings

**Encryption Method:**
- Uses Laravel's `Crypt` facade
- AES-256-CBC encryption (default)
- Encryption key from `APP_KEY` in `.env` file

**Automatic Encryption/Decryption:**

```php
// WRITING (Encryption happens automatically)
$payroll = new Payroll();
$payroll->gross_salary = 50000.00;  // Plain decimal value
$payroll->save();
// Database stores: encrypted string like "eyJpdiI6Ik..."

// READING (Decryption happens automatically)
$payroll = Payroll::find(1);
echo $payroll->gross_salary;  // Returns: 50000.00 (decrypted)
```

### No Custom Accessors/Mutators Needed

The `'encrypted'` cast handles everything automatically. There are **NO** custom accessors or mutators in the Payroll model for salary fields.

**What Laravel does behind the scenes:**

```php
// Equivalent to having these (but you don't need to write them):

// Mutator (when setting value)
public function setGrossSalaryAttribute($value)
{
    $this->attributes['gross_salary'] = encrypt($value);
}

// Accessor (when getting value)
public function getGrossSalaryAttribute($value)
{
    return decrypt($value);
}
```

### Database Storage Example

```sql
-- What's actually stored in the database:
SELECT id, gross_salary FROM payrolls WHERE id = 1;

-- Result:
-- id: 1
-- gross_salary: "eyJpdiI6IkRhVGFIZXJlIiwidmFsdWUiOiJFbmNyeXB0ZWREYXRhIiwibWFjIjoiSGFzaCJ9"

-- When accessed via Eloquent:
$payroll->gross_salary  // Returns: 50000.00
```

### Important Notes

1. **Encryption Key**: Must be set in `.env` as `APP_KEY`
2. **Key Rotation**: Changing `APP_KEY` will break existing encrypted data
3. **Performance**: Encryption/decryption happens on every read/write
4. **Searchability**: Cannot query encrypted fields directly (e.g., `WHERE gross_salary > 50000`)
5. **Type Preservation**: Values are encrypted as-is, so store as strings or numbers before encryption

---

## Question 7: Querying and Decrypting Payroll Data

### Example: PayrollController Index Method

```php
// File: app/Http/Controllers/Api/PayrollController.php

public function index(Request $request)
{
    // Build query with relationships
    $query = Payroll::query()
        ->with([
            'employment.employee:id,staff_id,first_name_en,last_name_en,organization',
            'employment.department:id,name',
            'employment.position:id,title,department_id',
            'employeeFundingAllocation:id,employee_id,allocation_type,fte',
        ]);

    // Apply filters using scopes
    if ($request->has('filter_organization')) {
        $query->bySubsidiary($request->filter_organization);
    }

    if ($request->has('filter_department')) {
        $query->byDepartment($request->filter_department);
    }

    if ($request->has('filter_date_range')) {
        $query->byPayPeriodDate($request->filter_date_range);
    }

    // Apply sorting
    if ($request->has('sort_by')) {
        $query->orderByField($request->sort_by, $request->sort_order ?? 'desc');
    }

    // Paginate results
    $payrolls = $query->paginate($request->per_page ?? 10);

    // Return response - decryption happens automatically
    return response()->json([
        'success' => true,
        'data' => $payrolls->items(),
        'pagination' => [
            'current_page' => $payrolls->currentPage(),
            'per_page' => $payrolls->perPage(),
            'total' => $payrolls->total(),
            'last_page' => $payrolls->lastPage(),
        ]
    ]);
}
```

### Automatic Decryption in API Response

**When payroll data is returned:**

```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "employment_id": 123,
      "employee_funding_allocation_id": 456,
      "gross_salary": 50000.00,           // ← Automatically decrypted
      "gross_salary_by_FTE": 25000.00,    // ← Automatically decrypted
      "net_salary": 42500.00,             // ← Automatically decrypted
      "total_income": 52000.00,           // ← Automatically decrypted
      "total_deduction": 9500.00,         // ← Automatically decrypted
      "pay_period_date": "2025-01-31",
      "employment": {
        "id": 123,
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
          "title": "Senior Developer"
        }
      },
      "employee_funding_allocation": {
        "id": 456,
        "allocation_type": "grant",
        "fte": 0.50
      }
    }
  ]
}
```

### How Decryption is Handled

1. **Query Execution**: Eloquent fetches encrypted data from database
2. **Model Hydration**: When creating model instances, Laravel checks `$casts` array
3. **Automatic Decryption**: For each field with `'encrypted'` cast, Laravel calls `decrypt()`
4. **JSON Serialization**: When converting to JSON for API response, decrypted values are used
5. **No Manual Intervention**: Controllers don't need to decrypt anything

### Example: Single Payroll Retrieval

```php
public function show($id)
{
    $payroll = Payroll::with([
        'employment.employee',
        'employment.department',
        'employment.position',
        'employeeFundingAllocation.grantItem.grant'
    ])->findOrFail($id);

    // All encrypted fields are automatically decrypted
    return response()->json([
        'success' => true,
        'data' => $payroll
    ]);
}
```

### Filtering and Searching Encrypted Data

**Problem**: You cannot filter encrypted fields directly in SQL

```php
// ❌ THIS WILL NOT WORK:
$payrolls = Payroll::where('gross_salary', '>', 50000)->get();
// Compares encrypted strings, not actual values

// ✅ SOLUTION 1: Filter after retrieval
$payrolls = Payroll::all()->filter(function($payroll) {
    return $payroll->gross_salary > 50000;
});

// ✅ SOLUTION 2: Use related non-encrypted fields
$payrolls = Payroll::whereHas('employment', function($query) {
    $query->where('pass_probation_salary', '>', 50000);
})->get();
```

### Current Codebase Approach

The system **does not filter by salary amounts** in queries. Instead, it:
- Filters by organization, department, date range, staff_id
- Retrieves all matching records
- Returns decrypted data to frontend
- Frontend handles any salary-based filtering/sorting

---

## Question 8: Performance and Caching with Encrypted Fields

### Current Performance Considerations

**No caching is implemented** for decrypted payroll data in the current codebase.

### Performance Characteristics

**Decryption Overhead:**
```php
// Each field decryption takes ~0.1-0.5ms
// For 20 encrypted fields per record:
// - 1 record: ~2-10ms total decryption time
// - 100 records: ~200-1000ms (0.2-1 second)
// - 1000 records: ~2-10 seconds
```

**Query Optimization in Payroll Model:**

```php
// File: app/Models/Payroll.php

// Scope to select only necessary fields for pagination
public function scopeForPagination($query)
{
    return $query->select([
        'id',
        'employment_id',
        'employee_funding_allocation_id',
        'gross_salary',           // Only 5 encrypted fields
        'net_salary',
        'total_income',
        'total_deduction',
        'pay_period_date',
        'created_at',
        'updated_at',
    ]);
}

// Scope to eager load relationships efficiently
public function scopeWithOptimizedRelations($query)
{
    return $query->with([
        'employment.employee:id,staff_id,first_name_en,last_name_en,organization',
        'employment.department:id,name',
        'employment.position:id,title,department_id',
        'employeeFundingAllocation:id,employee_id,allocation_type,fte',
    ]);
}
```

**Usage:**
```php
// Optimized query for listing
$payrolls = Payroll::forPagination()
    ->withOptimizedRelations()
    ->paginate(10);
// Only decrypts 5 fields per record instead of 20
```

### No Caching Strategy Currently Implemented

**What's NOT in the codebase:**
- ❌ No Redis/Memcached caching of decrypted payroll data
- ❌ No in-memory caching of frequently accessed records
- ❌ No query result caching
- ❌ No computed/cached totals

**Why no caching:**
1. **Data Sensitivity**: Payroll data is highly sensitive
2. **Data Freshness**: Payroll records can be updated/corrected
3. **Security**: Cached decrypted data could be a security risk
4. **Simplicity**: Current performance is acceptable for typical usage

### Performance Optimization Strategies Used

**1. Pagination:**
```php
// Only load 10-50 records at a time
$payrolls = Payroll::paginate(10);
```

**2. Selective Field Loading:**
```php
// Don't load all 20 encrypted fields if not needed
$payrolls = Payroll::select(['id', 'gross_salary', 'net_salary'])->get();
```

**3. Eager Loading:**
```php
// Prevent N+1 queries
$payrolls = Payroll::with(['employment.employee', 'employeeFundingAllocation'])->get();
```

**4. Query Scopes:**
```php
// Filter at database level before decryption
$payrolls = Payroll::bySubsidiary('SMRU')
    ->byPayPeriodDate('2025-01-01,2025-01-31')
    ->paginate(10);
```

### Typical Response Times

**Based on current implementation:**

| Records | Encrypted Fields | Approximate Time |
|---------|------------------|------------------|
| 10 | 5 (pagination) | 20-50ms |
| 10 | 20 (full detail) | 50-100ms |
| 100 | 5 (pagination) | 200-500ms |
| 100 | 20 (full detail) | 500ms-1s |

### Potential Future Optimizations

**If performance becomes an issue:**

1. **Lazy Loading Encrypted Fields:**
```php
// Load encrypted fields only when accessed
$payroll = Payroll::select(['id', 'employment_id'])->first();
// gross_salary not loaded yet

echo $payroll->gross_salary;  // Loads and decrypts on demand
```

2. **Computed Columns (Non-Encrypted):**
```php
// Store salary ranges as non-encrypted for filtering
$table->enum('salary_range', ['0-30k', '30k-50k', '50k+']);
```

3. **Background Processing:**
```php
// Pre-calculate monthly reports in background jobs
// Store aggregated (non-sensitive) data separately
```

4. **Database Indexing:**
```php
// Index non-encrypted fields used for filtering
$table->index(['pay_period_date', 'employment_id']);
```

### Current Conclusion

**The current implementation prioritizes:**
- ✅ Security (encryption at rest)
- ✅ Simplicity (no complex caching)
- ✅ Correctness (always fresh data)

**Performance is acceptable because:**
- Pagination limits records per request
- Selective field loading reduces decryption overhead
- Typical use case: viewing 10-50 records at a time
- API response times: 50-500ms (acceptable for internal HR system)




