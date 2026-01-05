# Swagger Documentation Updates for Budget Line Code Migration

## 📄 Overview
Updated all Swagger/OpenAPI documentation to reflect the migration of `budgetline_code` from `position_slots` to `grant_items`.

---

## ✅ **Swagger Changes Made**

### **1. GrantItem Model Schema** ✅ **UPDATED**
**File**: `app/Models/GrantItem.php`

**Added Property**:
```php
@OA\Property(property="budgetline_code", type="string", example="BL001", description="Budget line code for grant funding")
```

**Impact**: 
- Budget line code now appears in all GrantItem API responses
- Properly documented as part of grant funding structure

### **2. PositionSlot Model Schema** ✅ **UPDATED**  
**File**: `app/Models/PositionSlot.php`

**Removed from Required Fields**:
```php
// OLD: required={"grant_item_id", "slot_number", "budgetline_code"}
// NEW: required={"grant_item_id", "slot_number"}
```

**Removed Property**:
```php
// REMOVED: @OA\Property(property="budgetline_code", ...)
```

**Impact**:
- Position slots no longer directly contain budget line codes in API responses
- Budget line codes accessed through `grantItem.budgetline_code` relationship

### **3. PositionSlot Controller APIs** ✅ **UPDATED**
**File**: `app/Http/Controllers/Api/PositionSlotController.php`

#### **POST /api/position-slots (Create)**
**Request Body Updated**:
```php
// OLD RequestBody
@OA\JsonContent(
    required={"grant_item_id", "slot_number", "budgetline_code"},
    @OA\Property(property="budgetline_code", type="string", example="BL001")
)

// NEW RequestBody  
@OA\JsonContent(
    required={"grant_item_id", "slot_number"},
    // budgetline_code removed
)
```

#### **PUT /api/position-slots/{id} (Update)**
**Request Body Updated**:
```php
// OLD RequestBody
@OA\JsonContent(
    @OA\Property(property="budgetline_code", type="string", example="BL002")
)

// NEW RequestBody
@OA\JsonContent(
    // budgetline_code removed - only slot_number can be updated
)
```

#### **Error Response Updated**:
```php
// OLD: "Budget line is required"
// NEW: "Validation error"
```

### **4. Resource Response Structure** ✅ **MAINTAINED**
**Files**: 
- `app/Http/Resources/PositionSlotResource.php`
- `app/Http/Resources/EmployeeGrantAllocationResource.php`

**Response Structure**:
```json
{
  "id": 1,
  "grant_item_id": 1,
  "slot_number": 1,
  "budgetline_code": "BL001",  // Still present via relationship
  "grant_item": {
    "id": 1,
    "budgetline_code": "BL001"  // Source of budget line code
  }
}
```

**Impact**: 
- ✅ **Backward compatibility maintained** - API responses still include budget line codes
- ✅ **Data access improved** - Budget line code now comes from proper source (grant item)

---

## 🔄 **API Behavior Changes**

### **Before Migration**
```bash
# Creating position slot required budget line code
POST /api/position-slots
{
  "grant_item_id": 1,
  "slot_number": 1,
  "budgetline_code": "BL001"  # REQUIRED
}

# Updating position slot could change budget line code
PUT /api/position-slots/1
{
  "budgetline_code": "BL002"  # Could update budget line
}
```

### **After Migration**
```bash
# Creating position slot no longer needs budget line code
POST /api/position-slots
{
  "grant_item_id": 1,
  "slot_number": 1
  # budgetline_code automatically inherited from grant item
}

# Budget line code managed at grant item level
# Position slots inherit budget line code from their grant item
```

---

## 📋 **Updated API Endpoints**

### **Position Slots**
| Method | Endpoint | Budget Line Code Handling |
|--------|----------|---------------------------|
| `GET` | `/api/position-slots` | ✅ Returned via `grantItem.budgetline_code` |
| `POST` | `/api/position-slots` | ❌ No longer required in request |
| `PUT` | `/api/position-slots/{id}` | ❌ Cannot update budget line code |
| `GET` | `/api/position-slots/{id}` | ✅ Returned via `grantItem.budgetline_code` |

### **Grant Items**
| Method | Endpoint | Budget Line Code Handling |
|--------|----------|---------------------------|
| `GET` | `/api/grants/{id}/items` | ✅ Returned in `budgetline_code` field |
| Excel Upload | `/api/grants/upload` | ✅ Budget line code assigned to grant items |

### **Employee Allocations**
| Method | Endpoint | Budget Line Code Handling |
|--------|----------|---------------------------|
| `GET` | `/api/employee-grant-allocations` | ✅ Returned via `positionSlot.grantItem.budgetline_code` |
| `GET` | `/api/employments/{id}/funding-details` | ✅ Returned via relationship chain |

---

## 🧪 **Testing the Updated APIs**

### **1. Test Position Slot Creation**
```bash
# Should work without budget line code
curl -X POST /api/position-slots \
  -H "Content-Type: application/json" \
  -d '{
    "grant_item_id": 1,
    "slot_number": 1
  }'

# Response should include budget line code from grant item
{
  "success": true,
  "data": {
    "id": 1,
    "grant_item_id": 1,
    "slot_number": 1,
    "budgetline_code": "BL001"  // From grant item
  }
}
```

### **2. Test Grant Item Responses**
```bash
# Grant items should now include budget line codes
curl -X GET /api/grants/1/items

# Response should include budget line code
{
  "data": [
    {
      "id": 1,
      "grant_id": 1,
      "grant_position": "Project Manager",
      "budgetline_code": "BL001"  // Now included
    }
  ]
}
```

---

## 🎯 **Benefits of Updated Documentation**

### **1. Clarity & Accuracy**
- ✅ API documentation matches actual implementation
- ✅ Clear understanding of where budget line codes are managed
- ✅ Proper data flow documentation

### **2. Developer Experience**
- ✅ Simpler position slot creation (fewer required fields)
- ✅ Clear separation of concerns in API design
- ✅ Better understanding of data relationships

### **3. System Integration**
- ✅ Frontend developers understand new data structure
- ✅ API consumers know where to find budget line codes
- ✅ Clear migration path for existing integrations

---

## 📝 **Implementation Notes**

### **Regenerated Documentation**
```bash
php artisan l5-swagger:generate
```
✅ **Swagger documentation has been regenerated** to reflect all changes.

### **Validation Updates**
- ✅ Position slot validation rules updated
- ✅ Error messages updated to reflect new requirements
- ✅ Required field lists updated in Swagger annotations

### **Backward Compatibility**
- ✅ **API responses still include budget line codes** (via relationships)
- ✅ **Existing frontend code should continue working** (budget line codes still present in responses)
- ✅ **Only request structures changed** (budget line code no longer required for position slot creation)

This documentation update ensures that all API consumers have accurate, up-to-date information about the budget line code migration and can integrate with the improved system architecture.
