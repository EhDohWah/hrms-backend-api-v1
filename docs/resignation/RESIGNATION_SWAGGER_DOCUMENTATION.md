# Resignation System - Complete Swagger/OpenAPI Documentation

## 🎯 **Overview**

The Resignation Management System now includes comprehensive Swagger/OpenAPI annotations for both the model and controller, providing interactive API documentation with detailed request/response specifications, validation rules, and examples.

## 📋 **Documentation Features Implemented**

### ✅ **Model Documentation**
- **Complete Schema Definition**: Full `@OA\Schema` annotation for the Resignation model
- **Property Specifications**: All 20+ properties with types, descriptions, and examples
- **Relationship References**: Links to Employee, DepartmentPosition, and User schemas
- **Validation Constraints**: Required fields, string lengths, enums, and nullable properties
- **Computed Properties**: Notice period, days until last working, overdue status

### ✅ **Controller Documentation**
- **Complete CRUD Operations**: All 6 API endpoints fully documented
- **Request/Response Examples**: Realistic JSON examples for all operations
- **Error Handling**: Comprehensive error response documentation (400, 401, 403, 404, 422, 500)
- **Parameter Specifications**: Query parameters, path parameters, and request bodies
- **Authentication**: Bearer token security requirements

## 🏗️ **Model Schema Documentation**

### **@OA\Schema - Resignation Model**

```php
@OA\Schema(
    schema="Resignation",
    type="object",
    title="Resignation",
    description="Employee resignation model",
    required={"employee_id", "resignation_date", "last_working_date", "reason"}
)
```

### **Key Properties Documented**

1. **Core Fields**:
   - `id` (integer) - Primary key
   - `employee_id` (integer) - Employee reference
   - `department_id` (integer, nullable) - Department reference
   - `position_id` (integer, nullable) - Position reference

2. **Resignation Data**:
   - `resignation_date` (date) - Submission date
   - `last_working_date` (date) - Final work day
   - `reason` (string, max 50) - Primary reason
   - `reason_details` (text, nullable) - Detailed explanation

3. **Status Management**:
   - `acknowledgement_status` (enum: Pending, Acknowledged, Rejected)
   - `acknowledged_by` (integer, nullable) - Acknowledger user ID
   - `acknowledged_at` (datetime, nullable) - Acknowledgement timestamp

4. **Relationships**:
   - `employee` - Employee details
   - `department` - Department information
   - `position` - Position details
   - `acknowledged_by_user` - Acknowledger information

5. **Computed Properties**:
   - `notice_period_days` - Calculated notice period
   - `days_until_last_working` - Days remaining
   - `is_overdue` - Processing status indicator

## 🎛️ **Controller Documentation**

### **1. GET /api/v1/resignations - List Resignations**

```php
@OA\Get(
    path="/api/v1/resignations",
    summary="Get list of resignations",
    description="Returns paginated list with advanced filtering",
    operationId="getResignations",
    tags={"Resignations"},
    security={{"bearerAuth":{}}}
)
```

**Query Parameters**:
- `page` (integer) - Page number (min: 1)
- `per_page` (integer) - Items per page (1-100)
- `search` (string) - Search employee name/staff ID/reason
- `acknowledgement_status` (enum) - Pending, Acknowledged, Rejected
- `department_id` (integer) - Filter by department
- `reason` (string) - Filter by reason (partial match)
- `sort_by` (enum) - resignation_date, last_working_date, acknowledgement_status, created_at
- `sort_order` (enum) - asc, desc

**Response Example**:
```json
{
  "success": true,
  "message": "Resignations retrieved successfully",
  "data": [...],
  "meta": {
    "pagination": {
      "current_page": 1,
      "last_page": 5,
      "per_page": 15,
      "total": 72
    },
    "cached": true,
    "timestamp": "2024-02-01T10:30:00Z"
  }
}
```

### **2. POST /api/v1/resignations - Create Resignation**

```php
@OA\Post(
    path="/api/v1/resignations",
    summary="Create a new resignation",
    description="Creates resignation with automatic employee data population",
    operationId="storeResignation"
)
```

**Request Body**:
```json
{
  "employee_id": 1,
  "department_id": 5,
  "position_id": 12,
  "resignation_date": "2024-02-01",
  "last_working_date": "2024-02-29",
  "reason": "Career Advancement",
  "reason_details": "Accepted a position with better growth opportunities",
  "acknowledgement_status": "Pending"
}
```

**Validation Rules**:
- `employee_id`: Required, must exist in employees table
- `resignation_date`: Required, cannot be future date
- `last_working_date`: Required, must be on/after resignation date
- `reason`: Required, max 50 characters
- `department_id/position_id`: Auto-populated if not provided

### **3. GET /api/v1/resignations/{id} - Get Single Resignation**

```php
@OA\Get(
    path="/api/v1/resignations/{id}",
    summary="Get resignation by ID",
    description="Returns detailed resignation with relationships",
    operationId="getResignation"
)
```

**Path Parameter**:
- `id` (integer, required) - Resignation ID

**Response**: Full resignation object with related employee, department, position, and acknowledger data.

### **4. PUT /api/v1/resignations/{id} - Update Resignation**

```php
@OA\Put(
    path="/api/v1/resignations/{id}",
    summary="Update resignation",
    description="Updates existing resignation with validation",
    operationId="updateResignation"
)
```

**Request Body**: Same as create, but all fields optional (partial update support).

### **5. DELETE /api/v1/resignations/{id} - Delete Resignation**

```php
@OA\Delete(
    path="/api/v1/resignations/{id}",
    summary="Delete resignation",
    description="Soft deletes resignation with cache invalidation",
    operationId="deleteResignation"
)
```

**Response**: Success confirmation message.

### **6. PUT /api/v1/resignations/{id}/acknowledge - Acknowledge/Reject**

```php
@OA\Put(
    path="/api/v1/resignations/{id}/acknowledge",
    summary="Acknowledge or reject resignation",
    description="Updates status with user tracking",
    operationId="acknowledgeResignation"
)
```

**Request Body**:
```json
{
  "action": "acknowledge"  // or "reject"
}
```

**Business Rules**:
- Only pending resignations can be acknowledged/rejected
- Automatically sets `acknowledged_by` and `acknowledged_at`
- Updates `acknowledgement_status` to "Acknowledged" or "Rejected"

## 📊 **Error Response Documentation**

### **Standard Error Responses**

1. **401 - Unauthenticated**:
   ```json
   {
     "message": "Unauthenticated."
   }
   ```

2. **403 - Forbidden**:
   ```json
   {
     "message": "Access denied."
   }
   ```

3. **404 - Not Found**:
   ```json
   {
     "success": false,
     "message": "Resignation not found"
   }
   ```

4. **422 - Validation Error**:
   ```json
   {
     "message": "The given data was invalid.",
     "errors": {
       "employee_id": ["Please select an employee."],
       "resignation_date": ["Resignation date cannot be in the future."]
     }
   }
   ```

5. **500 - Server Error**:
   ```json
   {
     "success": false,
     "message": "Failed to retrieve resignations",
     "error": "Database connection error"
   }
   ```

### **Business Logic Errors**

1. **400 - Invalid Operation**:
   ```json
   {
     "success": false,
     "message": "Only pending resignations can be acknowledged or rejected"
   }
   ```

## 🔧 **Security Documentation**

### **Authentication**
All endpoints require Bearer token authentication:

```php
security={{"bearerAuth":{}}}
```

### **Authorization**
- Read operations: Any authenticated user
- Write operations: HR roles and above
- Acknowledge operations: Manager roles and above

## 🚀 **Usage Examples**

### **1. Create a Resignation**
```bash
curl -X POST "/api/v1/resignations" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 1,
    "resignation_date": "2024-02-01",
    "last_working_date": "2024-02-29",
    "reason": "Career Advancement",
    "reason_details": "Better opportunity elsewhere"
  }'
```

### **2. List with Filters**
```bash
curl -X GET "/api/v1/resignations?acknowledgement_status=Pending&department_id=5&per_page=20" \
  -H "Authorization: Bearer {token}"
```

### **3. Acknowledge Resignation**
```bash
curl -X PUT "/api/v1/resignations/1/acknowledge" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"action": "acknowledge"}'
```

## 📚 **Documentation Access**

### **Swagger UI**
- **URL**: `/api/documentation`
- **Interactive testing**: Full request/response testing
- **Schema exploration**: Browse all models and relationships
- **Authentication**: Built-in token authentication support

### **OpenAPI JSON**
- **URL**: `/docs/api-docs.json`
- **Format**: OpenAPI 3.0 specification
- **Usage**: Import into Postman, Insomnia, or other API tools

## 🎯 **Key Benefits**

1. **Developer Experience**:
   - ✅ Interactive API exploration
   - ✅ Built-in request validation
   - ✅ Automatic example generation
   - ✅ Type safety documentation

2. **API Testing**:
   - ✅ Direct browser testing
   - ✅ Authentication support
   - ✅ Real-time validation feedback
   - ✅ Response format verification

3. **Integration Support**:
   - ✅ Postman/Insomnia import
   - ✅ Code generation support
   - ✅ Client SDK generation
   - ✅ API contract validation

4. **Maintenance**:
   - ✅ Self-updating documentation
   - ✅ Version-controlled specs
   - ✅ Automatic validation sync
   - ✅ Error handling documentation

## ✅ **Documentation Completeness**

- **✅ Model Schema**: Complete with all properties, relationships, and constraints
- **✅ CRUD Operations**: All 5 standard operations fully documented
- **✅ Custom Endpoints**: Acknowledge/reject workflow documented
- **✅ Query Parameters**: All filtering and pagination options
- **✅ Request Bodies**: Complete validation rules and examples
- **✅ Response Formats**: Success and error responses
- **✅ Authentication**: Security requirements and examples
- **✅ Error Handling**: All HTTP status codes and business logic errors

---

**The Resignation System now has production-ready Swagger documentation with complete API specifications, interactive testing capabilities, and comprehensive examples for all operations!** 🎉📚

### **Access Your Documentation**
Visit `/api/documentation` in your browser to explore the interactive Swagger UI for the Resignation Management System.
