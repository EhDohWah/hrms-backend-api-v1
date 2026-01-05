# Quick Reference: Real-time Allocation Calculation API

## 🚀 Quick Start

### New API Endpoint
```
POST /api/employments/calculate-allocation
```

### Request
```json
{
  "employment_id": 123,
  "fte": 60
}
```

### Response
```json
{
  "success": true,
  "data": {
    "allocated_amount": 30000,
    "formatted_amount": "฿30,000.00",
    "calculation_formula": "(50000 × 60) / 100 = 30000"
  }
}
```

---

## 📝 Frontend Changes Needed

### 1. Service (employment.service.js)
```javascript
async calculateAllocationAmount(data) {
  return this.post('/employments/calculate-allocation', data);
}
```

### 2. Component (employment-modal.vue)
```javascript
// REMOVE this
getCalculatedSalary(fte) {
  return (this.formData.position_salary * fte) / 100;
}

// ADD this
async onFteChange() {
  const result = await employmentService.calculateAllocationAmount({
    employment_id: this.employmentId,
    fte: this.currentAllocation.fte
  });
  this.displayAmount = result.data.formatted_amount;
}
```

### 3. Payload (Don't send allocated_amount)
```javascript
// ❌ OLD
allocations: [{
  fte: 60,
  allocated_amount: 30000  // Don't send this
}]

// ✅ NEW
allocations: [{
  fte: 60  // Backend calculates
}]
```

---

## 🔑 Key Points

1. **Backend calculates** - Don't calculate on frontend
2. **Use real-time API** - Call endpoint when FTE changes
3. **Debounce calls** - Wait 500ms before calling API
4. **Show loading state** - Better UX
5. **Handle errors** - Graceful fallback

---

## 📚 Full Documentation

- **Migration Guide:** `docs/FRONTEND_EMPLOYMENT_MIGRATION_GUIDE.md`
- **API Changes:** `docs/EMPLOYMENT_API_CHANGES_V2.md`
- **Implementation Summary:** `docs/IMPLEMENTATION_SUMMARY_REAL_TIME_CALCULATION.md`

---

## 💡 AI Prompt

Copy this to your AI assistant:

```
Update the Vue 3 Employment Management frontend to use the new real-time 
calculation API (POST /api/employments/calculate-allocation). Remove local 
calculation logic and call the backend API when FTE changes. See full 
details in docs/FRONTEND_EMPLOYMENT_MIGRATION_GUIDE.md
```

---

## ✅ Testing

```bash
# Test the endpoint
curl -X POST http://localhost:8000/api/employments/calculate-allocation \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"employment_id": 1, "fte": 60}'
```

---

## 🆘 Support

Questions? Check:
1. `docs/FRONTEND_EMPLOYMENT_MIGRATION_GUIDE.md` - Complete guide
2. `/api/documentation` - Swagger UI
3. Development team - For help

