# Employment Template - Quick Reference

## 📥 Download Template

**Endpoint:** `GET /api/v1/downloads/employment-template`  
**Permission:** `employment_records.read`  
**Response:** Excel file (.xlsx)

---

## 📤 Upload Template

**Endpoint:** `POST /api/v1/uploads/employment`  
**Permission:** `employment_records.edit`  
**Processing:** Async (queued)  
**Response:** 202 Accepted (notification sent when complete)

---

## 📋 Required Columns (4)

| Column | Format | Example |
|--------|--------|---------|
| `staff_id` | String | EMP001 |
| `employment_type` | Full-time, Part-time, Contract, Temporary | Full-time |
| `start_date` | YYYY-MM-DD | 2025-01-15 |
| `pass_probation_salary` | Decimal | 50000.00 |

---

## 📋 Optional Columns (15)

| Column | Format | Example | Notes |
|--------|--------|---------|-------|
| `pass_probation_date` | YYYY-MM-DD | 2025-04-15 | Default: 3 months after start |
| `probation_salary` | Decimal | 45000.00 | Salary during probation |
| `end_date` | YYYY-MM-DD | 2025-12-31 | For contracts |
| `pay_method` | String | Monthly | Dropdown available |
| `site` | String | Headquarters | Must exist in sites table |
| `department_id` | Integer | 1 | Must exist |
| `section_department_id` | Integer | 3 | Must exist |
| `position_id` | Integer | 5 | Must exist |
| `health_welfare` | 1/0 | 1 | Boolean |
| `health_welfare_percentage` | Decimal | 5.00 | 0-100 |
| `pvd` | 1/0 | 1 | Boolean |
| `pvd_percentage` | Decimal | 7.50 | Typically 7.5 |
| `saving_fund` | 1/0 | 0 | Boolean |
| `saving_fund_percentage` | Decimal | 7.50 | Typically 7.5 |
| `status` | 1/0 | 1 | 1=Active, 0=Inactive |

---

## 🎯 Dropdown Values

### Employment Type
- Full-time
- Part-time
- Contract
- Temporary

### Pay Method
- Monthly
- Weekly
- Daily
- Hourly
- Bank Transfer
- Cash
- Cheque

### Boolean Fields (1/0)
- 1 = Yes/True/Enabled
- 0 = No/False/Disabled

---

## ⚠️ Important Rules

1. **staff_id must exist** in employees table
2. **Dates must be YYYY-MM-DD** format
3. **Site name must match exactly** (case-sensitive)
4. **Foreign key IDs must exist** in respective tables
5. **Duplicate staff_ids** in same file will fail
6. **Existing employments** (matched by staff_id) will be **UPDATED**
7. **New staff_ids** will **CREATE** new employment records

---

## 🚫 NOT Included

- **Funding Allocations** - Must be added separately via UI
- **Probation Records** - Managed separately
- **Employment History** - Auto-tracked on changes

---

## 📊 Sample Data

```
staff_id | employment_type | start_date | pass_probation_salary | pass_probation_date | probation_salary | site | status
---------|-----------------|------------|----------------------|---------------------|------------------|------|-------
EMP001   | Full-time       | 2025-01-15 | 50000.00             | 2025-04-15          | 45000.00         | HQ   | 1
EMP002   | Part-time       | 2025-02-01 | 30000.00             | 2025-05-01          |                  | Branch | 1
EMP003   | Contract        | 2025-03-01 | 60000.00             |                     |                  | Remote | 1
```

---

## 🔄 Processing Flow

1. **Upload file** → Queued for processing
2. **Background job** → Processes in chunks
3. **Validation** → Checks staff_id, dates, foreign keys
4. **Create/Update** → Based on staff_id match
5. **Notification** → Sent when complete

---

## ✅ Success Response

```json
{
  "success": true,
  "message": "Employment import started successfully. You will receive a notification when the import is complete.",
  "data": {
    "import_id": "employment_import_abc123",
    "status": "processing"
  }
}
```

---

## ❌ Common Errors

| Error | Cause | Solution |
|-------|-------|----------|
| "Employee with staff_id 'XXX' not found" | staff_id doesn't exist | Use existing employee staff_id |
| "Duplicate staff_id in import file" | Same staff_id appears twice | Remove duplicate rows |
| "Missing start date" | start_date column empty | Provide valid date |
| "Invalid employment type" | Wrong value | Use dropdown values |
| "Site not found" | Site name doesn't match | Check site name spelling |

---

## 🎯 Quick Tips

1. **Download template first** - Contains validation rules and examples
2. **Check Instructions sheet** - Detailed guidelines included
3. **Use dropdowns** - Prevents typos in employment_type and pay_method
4. **Test with small file** - Upload 1-2 rows first
5. **Wait for notification** - Import is async, don't re-upload
6. **Check existing data** - Verify staff_ids, sites, departments exist

---

**Last Updated:** December 31, 2025  
**Version:** 1.0.0

