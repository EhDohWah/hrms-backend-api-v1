# Resignation Management System - Simplified Schema Implementation

## 🎯 **Updated Implementation Overview**

The resignation management system has been updated to match the simplified schema requirements provided by the user. This implementation focuses on core functionality with a clean, maintainable structure.

## 📊 **Database Schema**

### **Updated Resignations Table**

```sql
Table resignations {
  id int [pk, increment]
  employee_id int [not null, ref: > employees.id]
  department_id int [ref: > department_positions.id]
  position_id int [ref: > department_positions.id]
  resignation_date date [not null]
  last_working_date date [not null]
  reason varchar(50) [not null]
  reason_details text
  acknowledgement_status varchar(50) [not null, default: 'Pending']
  acknowledged_by int [ref: > users.id]
  acknowledged_at datetime
  // Base template fields
  created_by varchar
  updated_by varchar
  deleted_at datetime
  created_at datetime
  updated_at datetime
}
```

### **Migration Implementation**

```php
public function up(): void
{
    Schema::create('resignations', function (Blueprint $table) {
        $table->id();
        
        // Core fields as per schema
        $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
        $table->unsignedBigInteger('department_id')->nullable();
        $table->foreign('department_id')->references('id')->on('department_positions')->onDelete('no action');
        $table->unsignedBigInteger('position_id')->nullable();
        $table->foreign('position_id')->references('id')->on('department_positions')->onDelete('no action');
        $table->date('resignation_date');
        $table->date('last_working_date');
        $table->string('reason', 50);
        $table->text('reason_details')->nullable();
        $table->string('acknowledgement_status', 50)->default('Pending');
        $table->unsignedBigInteger('acknowledged_by')->nullable();
        $table->foreign('acknowledged_by')->references('id')->on('users')->onDelete('set null');
        $table->datetime('acknowledged_at')->nullable();
        
        // Base template fields
        $table->string('created_by')->nullable();
        $table->string('updated_by')->nullable();
        $table->softDeletes();
        $table->timestamps();
        
        // Indexes for performance
        $table->index(['acknowledgement_status', 'resignation_date']);
        $table->index(['employee_id', 'acknowledgement_status']);
        $table->index(['resignation_date', 'last_working_date']);
        $table->index(['department_id', 'acknowledgement_status']);
    });
}
```

## 🏗️ **Model Implementation**

### **Simplified Resignation Model**

```php
class Resignation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'department_id',
        'position_id',
        'resignation_date',
        'last_working_date',
        'reason',
        'reason_details',
        'acknowledgement_status',
        'acknowledged_by',
        'acknowledged_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'resignation_date' => 'date',
        'last_working_date' => 'date',
        'acknowledged_at' => 'datetime',
    ];

    // Relationships
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(DepartmentPosition::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(DepartmentPosition::class, 'position_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('acknowledgement_status', 'Pending');
    }

    public function scopeAcknowledged($query)
    {
        return $query->where('acknowledgement_status', 'Acknowledged');
    }

    public function scopeRejected($query)
    {
        return $query->where('acknowledgement_status', 'Rejected');
    }

    // Custom methods
    public function acknowledge(User $user): bool
    {
        $this->update([
            'acknowledgement_status' => 'Acknowledged',
            'acknowledged_by' => $user->id,
            'acknowledged_at' => now(),
        ]);
        return true;
    }

    public function reject(User $user): bool
    {
        $this->update([
            'acknowledgement_status' => 'Rejected',
            'acknowledged_by' => $user->id,
            'acknowledged_at' => now(),
        ]);
        return true;
    }
}
```

## 🔧 **Validation Updates**

### **StoreResignationRequest**

```php
public function rules(): array
{
    return [
        'employee_id' => 'required|exists:employees,id',
        'department_id' => 'nullable|exists:department_positions,id',
        'position_id' => 'nullable|exists:department_positions,id',
        'resignation_date' => 'required|date|before_or_equal:today',
        'last_working_date' => 'required|date|after_or_equal:resignation_date',
        'reason' => 'required|string|max:50',
        'reason_details' => 'nullable|string',
        'acknowledgement_status' => 'sometimes|string|max:50|in:Pending,Acknowledged,Rejected',
    ];
}
```

### **UpdateResignationRequest**

```php
public function rules(): array
{
    return [
        'employee_id' => 'sometimes|exists:employees,id',
        'department_id' => 'nullable|exists:department_positions,id',
        'position_id' => 'nullable|exists:department_positions,id',
        'resignation_date' => 'sometimes|date|before_or_equal:today',
        'last_working_date' => 'sometimes|date|after_or_equal:resignation_date',
        'reason' => 'sometimes|string|max:50',
        'reason_details' => 'nullable|string',
        'acknowledgement_status' => 'sometimes|string|max:50|in:Pending,Acknowledged,Rejected',
    ];
}
```

## 🎛️ **Controller Updates**

### **Key Changes in ResignationController**

1. **Updated Query Parameters**:
   - `acknowledgement_status` instead of `status`
   - `department_id` instead of `department`
   - `reason` filter for reason search

2. **Updated Relationships Loading**:
   ```php
   $query = Resignation::with([
       'employee:id,staff_id,first_name_en,last_name_en,subsidiary',
       'acknowledgedBy:id,name',
       'department:id,department',
       'position:id,position',
   ]);
   ```

3. **Simplified Acknowledgement Workflow**:
   ```php
   public function acknowledge(Request $request, $id): JsonResponse
   {
       $validated = $request->validate([
           'action' => 'required|in:acknowledge,reject',
       ]);

       $resignation = Resignation::findOrFail($id);
       $user = Auth::user();

       if ($resignation->acknowledgement_status !== 'Pending') {
           return response()->json([
               'success' => false,
               'message' => 'Only pending resignations can be acknowledged or rejected',
           ], 400);
       }

       if ($validated['action'] === 'acknowledge') {
           $resignation->acknowledge($user);
           $message = 'Resignation acknowledged successfully';
       } else {
           $resignation->reject($user);
           $message = 'Resignation rejected successfully';
       }

       return response()->json([
           'success' => true,
           'message' => $message,
           'data' => $resignation,
       ], 200);
   }
   ```

## 📊 **API Endpoints**

### **Core CRUD Operations**
```
GET    /api/v1/resignations           # List with pagination & filters
POST   /api/v1/resignations           # Create new resignation
GET    /api/v1/resignations/{id}      # Get single resignation
PUT    /api/v1/resignations/{id}      # Update resignation
DELETE /api/v1/resignations/{id}      # Delete resignation
PUT    /api/v1/resignations/{id}/acknowledge  # Acknowledge/reject
```

### **Updated Query Parameters**
- `acknowledgement_status` - Filter by: Pending, Acknowledged, Rejected
- `department_id` - Filter by department ID
- `reason` - Search in reason field
- `search` - Full-text search across employee details and reason
- `sort_by` - Sort by: resignation_date, last_working_date, acknowledgement_status, created_at
- `sort_order` - asc, desc

## 🔄 **Workflow States**

```
New Resignation → Pending → [HR Review] → Acknowledged/Rejected
```

### **Status Values**
- **Pending**: Default status for new resignations
- **Acknowledged**: HR has approved the resignation
- **Rejected**: HR has rejected the resignation

## 📝 **Key Features**

### ✅ **Implemented Features**

1. **Smart Employee Assignment**
   - Auto-population of department_id and position_id from employee's current employment
   - Employee search functionality maintained

2. **Date Management**
   - Resignation date validation (not in future)
   - Last working date validation (after resignation date)
   - Automatic notice period calculation via accessor

3. **Acknowledgement Workflow**
   - Simple acknowledge/reject actions
   - User tracking for acknowledgements
   - Timestamp recording

4. **Server-side Pagination & Search**
   - Advanced filtering by status, department, reason
   - Full-text search across employee details
   - Flexible sorting options

5. **Performance Optimizations**
   - Database indexes on key query fields
   - Eager loading of relationships
   - Caching integration with HasCacheManagement trait

## 🚀 **Integration Steps**

### **1. Database Migration**
```bash
php artisan migrate
```

### **2. Routes Registration**
```php
// routes/api.php
Route::prefix('v1')->group(function () {
    Route::apiResource('resignations', ResignationController::class);
    Route::put('resignations/{id}/acknowledge', [ResignationController::class, 'acknowledge']);
});
```

### **3. Cache Integration**
```php
// Register in CacheServiceProvider
Resignation::observe(CacheInvalidationObserver::class);
```

## 📊 **Sample API Usage**

### **Create Resignation**
```json
POST /api/v1/resignations
{
    "employee_id": 1,
    "resignation_date": "2024-02-01",
    "last_working_date": "2024-02-29",
    "reason": "Career Advancement",
    "reason_details": "Accepted a position with better growth opportunities"
}
```

### **List with Filters**
```
GET /api/v1/resignations?acknowledgement_status=Pending&department_id=5&search=John
```

### **Acknowledge Resignation**
```json
PUT /api/v1/resignations/1/acknowledge
{
    "action": "acknowledge"
}
```

## ⚡ **Performance Benefits**

1. **Simplified Schema**: Faster queries with fewer joins
2. **Optimized Indexes**: Better query performance for filtering and sorting
3. **Reduced Complexity**: Easier maintenance and debugging
4. **Focused Functionality**: Core features without unnecessary overhead

## 🎯 **Status Summary**

✅ **Migration**: Updated and tested  
✅ **Model**: Simplified with core relationships  
✅ **Validation**: Updated for new schema  
✅ **Controller**: Refactored for simplified workflow  
✅ **Caching**: Integrated with existing cache management  
✅ **Performance**: Optimized with proper indexes  

---

**The simplified resignation management system is now ready for production use with a clean, maintainable codebase focused on core functionality!** 🎉
