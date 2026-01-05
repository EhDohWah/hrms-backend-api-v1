# Grant Import - Quick Reference Card

## 🎯 Two Grant Types

| Type | Budget Line Code | Duplicate Positions | Example |
|------|------------------|---------------------|---------|
| **General Fund** | ❌ None (NULL) | ✅ Allowed | S0031, S22001 |
| **Project Grant** | ✅ Required | ❌ Not allowed | GR-2024-001 |

---

## 📋 Excel Structure (Each Sheet = One Grant)

```
Row 1: Grant name - [Name]
Row 2: Grant code - [Code]
Row 3: Subsidiary - [Org]
Row 4: End date - [Date or empty]
Row 5: Description - [Text or empty]
Row 6: (EMPTY - Required spacer)
Row 7: [Column Headers] Budget Line Code | Position | Salary | Benefit | LOE% | Manpower
Row 8: [Validation Rules] - For reference only, not imported
Row 9+: ... actual data items
```

---

## 📝 Column A: Budget Line Code Rules

### Accepted Values:
- ✅ `1.2.2.1` (hierarchical)
- ✅ `BL-001` (alphanumeric)
- ✅ `A.B.C` (letters with dots)
- ✅ `CODE_123` (with underscore)
- ✅ (empty) - becomes NULL for General Fund
- ✅ **Any string** - no format restrictions!

### NOT Accepted:
- ❌ Over 255 characters

---

## ✅ Valid Examples

### General Fund (BHF):
```
Row 1: Grant name - General Fund
Row 2: Grant code - S22001
Row 3: Subsidiary - BHF
Row 4: End date -
Row 5: Description - BHF's hub grant
Row 6: (empty)
Row 7:        | Manager       | 75000 | 15000 | 100 | 2
Row 8:        | Field Officer | 45000 | 9000  | 100 | 3
Row 9:        | Manager       | 60000 | 12000 | 75  | 1  ← Same position OK!
```
**Column A is empty** ✅

### Project Grant:
```
Row 1: Grant name - Health Initiative
Row 2: Grant code - GR-2024-001
Row 3: Subsidiary - SMRU
Row 4: End date - 2024-12-31
Row 5: Description - Health programs
Row 6: (empty)
Row 7: 1.2.2.1 | Capacity Building Officer | 61800 | 10841 | 100 | 2
Row 8: 1.2.1.2 | CE Field Coordinator       | 42436 | 7774  | 100 | 1
Row 9: 1.1.2.8 | Cleaner                    | 31827 | 6096  | 100 | 1
```
**Column A has budget codes** ✅

---

## ⚠️ Common Errors

### Error 1: Duplicate in Project Grant
```
Row 7: 1.2.2.1 | Manager | 75000 | ...
Row 8: 1.2.2.1 | Manager | 60000 | ...  ❌ Duplicate!
```
**Fix**: Change budget code or position name

### Error 2: Missing Position
```
Row 7: 1.2.2.1 | (empty) | 75000 | ...  ❌ No position!
```
**Fix**: Add position name in Column B

---

## 🚀 Import API

### Endpoint
```
POST /api/grants/upload
Authorization: Bearer {token}
Content-Type: multipart/form-data

file: grants.xlsx
```

### Success Response (200 OK)
```json
{
    "success": true,
    "message": "Grant data import completed",
    "data": {
        "processed_grants": 3,
        "processed_items": 25,
        "warnings": [],
        "skipped_grants": []
    }
}
```

### Notification Sent
```
Grant import finished! Processed: 3 grants, 25 grant items
```

---

## 🗄️ Database Schema

### grant_items Table
```sql
id                    BIGINT
grant_id              BIGINT (FK)
grant_position        VARCHAR(255) NULLABLE
grant_salary          DECIMAL(15,2) NULLABLE
grant_benefit         DECIMAL(15,2) NULLABLE
grant_level_of_effort DECIMAL(5,2) NULLABLE
grant_position_number INT NULLABLE
budgetline_code       VARCHAR(255) NULLABLE  ← Can be NULL!
created_by            VARCHAR(255) NULLABLE
updated_by            VARCHAR(255) NULLABLE
timestamps
```

**No unique constraint** - allows NULL duplicates for General Fund

---

## 🔍 Query Examples

### Get General Fund Items
```sql
SELECT * FROM grant_items 
WHERE budgetline_code IS NULL;
```

### Get Project Grant Items
```sql
SELECT * FROM grant_items 
WHERE budgetline_code IS NOT NULL;
```

### Get Specific Budget Line
```sql
SELECT * FROM grant_items 
WHERE budgetline_code = '1.2.2.1';
```

---

## 📊 Real Data Example (From Your Excel)

### Sheet: "EF L'Initiative for CE"
```
Code: EF L'Initiative for CE
Items:
  1.2.2.1 | Capacity Building Officer
  1.2.1.2 | CE Field Coordinator
  1.1.2.8 | Cleaner
  1.1.2.7 | HR assistant
  1.2.2.3 | Logistic assistant
  1.1.1.3 | Sr. Project Coordinator
```

**All budget codes in hierarchical format** ✅

---

## 💾 Migration Commands

### Fresh Install
```bash
php artisan migrate:fresh
# No default grants created
# Import General Fund from Excel
# Import project grants from Excel
```

### Existing Database
```bash
# Option 1: Keep existing hub grants
# Just import grant items

# Option 2: Clean slate
php artisan migrate:rollback --step=50
php artisan migrate
# Import all grants from Excel
```

---

## ✨ Summary

| Feature | Status |
|---------|--------|
| Budget Line Optional | ✅ Yes |
| Any Format Accepted | ✅ Yes |
| General Fund Support | ✅ Yes |
| Duplicate Check | ✅ Smart (only with codes) |
| Notification | ✅ Yes |
| Documentation | ✅ Complete |
| Testing | ✅ Ready |

---

## 📚 Related Documentation

- `GRANT_BUDGET_LINE_CODE_IMPLEMENTATION.md` - Complete technical details
- `GRANT_IMPORT_NOTIFICATION_IMPLEMENTATION.md` - Notification system
- `GRANT_IMPORT_ROW_STRUCTURE_UPDATE.md` - Row structure changes
- `GRANT_IMPORT_EXCEL_FORMAT.md` - Excel format guide

---

## 🎉 Ready to Use!

Your grant import system now supports:
- General Fund without budget codes
- Project grants with any budget code format
- Smart duplicate detection
- Complete notification system

Import your `grants.xlsx` file and you're all set! 🚀
