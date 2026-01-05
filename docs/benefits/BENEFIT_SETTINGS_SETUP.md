# Benefit Settings Database Setup ✅

## Overview

This document explains the benefit settings system and the scripts created to manage global benefit percentages.

---

## Background

Previously, benefit percentages were stored directly in the `employments` table:
- `health_welfare_percentage`
- `pvd_percentage`
- `saving_fund_percentage`

This approach had limitations:
- ❌ Hard to update percentages globally
- ❌ No historical tracking of percentage changes
- ❌ Inconsistent percentages across employees
- ❌ No effective dating for future changes

**Solution**: Move benefit percentages to a centralized `benefit_settings` table.

---

## Database Schema

### `benefit_settings` Table

```sql
CREATE TABLE benefit_settings (
    id BIGINT PRIMARY KEY,
    setting_key VARCHAR(255) UNIQUE,           -- e.g., 'health_welfare_percentage'
    setting_value DECIMAL(10,2),               -- Percentage value (e.g., 5.00)
    setting_type VARCHAR(50) DEFAULT 'percentage',
    description TEXT,                          -- Human-readable description
    effective_date DATE,                       -- When this setting becomes active
    is_active BOOLEAN DEFAULT true,
    applies_to JSON,                           -- Optional conditions
    created_by VARCHAR(255),
    updated_by VARCHAR(255),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);
```

### `employments` Table (Updated)

```sql
-- Only boolean flags now
health_welfare BOOLEAN DEFAULT false
pvd BOOLEAN DEFAULT false
saving_fund BOOLEAN DEFAULT false

-- NO percentage columns!
```

---

## Scripts Created

### 1. `create_benefit_settings.php`

**Purpose**: Create/populate the three benefit percentage settings

**Usage**:
```bash
php create_benefit_settings.php
```

**What it does**:
1. Checks for existing benefit settings
2. Prompts for confirmation if records exist
3. Creates three benefit settings:
   - `health_welfare_percentage` = 5.00%
   - `pvd_percentage` = 3.00%
   - `saving_fund_percentage` = 3.00%
4. Sets effective date to start of current year
5. Marks all as active

**Output**:
```
✓ Created: health_welfare_percentage = 5.00%
✓ Created: pvd_percentage = 3.00%
✓ Created: saving_fund_percentage = 3.00%
```

---

### 2. `verify_benefit_settings.php`

**Purpose**: Verify benefit settings are properly configured

**Usage**:
```bash
php verify_benefit_settings.php
```

**What it does**:
1. Fetches all benefit settings from database
2. Displays detailed information for each setting
3. Tests the `BenefitSetting::getActiveSetting()` helper method
4. Confirms settings are working correctly

**Output**:
```
Total Settings: 3

ID: 1
Key: health_welfare_percentage
Value: 5.00%
Active: ✓ Yes

BenefitSetting::getActiveSetting('health_welfare_percentage') = 5%
✓ Verification complete!
```

---

### 3. `create_test_data.php` (Updated)

**Purpose**: Create test employments and employees

**Changes Made**:
- ✅ Removed `health_welfare_percentage` from employment creation
- ✅ Removed `pvd_percentage` from employment creation
- ✅ Removed `saving_fund_percentage` from employment creation
- ✅ Added comment explaining percentages are managed globally

**Before**:
```php
Employment::create([
    'health_welfare' => true,
    'health_welfare_percentage' => 5.00,  // ❌ Old way
    'pvd' => true,
    'pvd_percentage' => 3.00,             // ❌ Old way
]);
```

**After**:
```php
Employment::create([
    'health_welfare' => true,  // ✅ Only boolean flag
    'pvd' => true,             // ✅ Only boolean flag
    // NOTE: Benefit percentages are managed globally in benefit_settings table
]);
```

---

## How It Works

### 1. BenefitSetting Model

Location: `app/Models/BenefitSetting.php`

**Key Method**:
```php
/**
 * Get active benefit setting by key
 *
 * @param string $key Setting key (e.g., 'health_welfare_percentage')
 * @return float|null Setting value or null if not found
 */
public static function getActiveSetting(string $key): ?float
{
    return Cache::remember("benefit_setting:{$key}", 3600, function () use ($key) {
        $setting = self::query()
            ->where('setting_key', $key)
            ->where('is_active', true)
            ->whereDate('effective_date', '<=', now())
            ->orderBy('effective_date', 'desc')
            ->first();

        return $setting?->setting_value;
    });
}
```

**Features**:
- ✅ Cached for 1 hour (3600 seconds)
- ✅ Automatic cache invalidation on update
- ✅ Returns only active settings
- ✅ Filters by effective date (only returns settings that are currently active)
- ✅ Returns most recent setting if multiple exist

---

### 2. EmploymentController Integration

The controller dynamically attaches global benefit percentages to employment records:

**index() method** (app/Http/Controllers/Api/EmploymentController.php:345-358):
```php
// Fetch global benefit percentages
$globalBenefits = [
    'health_welfare_percentage' => BenefitSetting::getActiveSetting('health_welfare_percentage'),
    'pvd_percentage' => BenefitSetting::getActiveSetting('pvd_percentage'),
    'saving_fund_percentage' => BenefitSetting::getActiveSetting('saving_fund_percentage'),
];

// Attach to each employment item
foreach ($employments->items() as $item) {
    $item->health_welfare_percentage = $globalBenefits['health_welfare_percentage'];
    $item->pvd_percentage = $globalBenefits['pvd_percentage'];
    $item->saving_fund_percentage = $globalBenefits['saving_fund_percentage'];
}
```

**show() method** (app/Http/Controllers/Api/EmploymentController.php:991-994):
```php
$employment->health_welfare_percentage = BenefitSetting::getActiveSetting('health_welfare_percentage');
$employment->pvd_percentage = BenefitSetting::getActiveSetting('pvd_percentage');
$employment->saving_fund_percentage = BenefitSetting::getActiveSetting('saving_fund_percentage');
```

---

## API Response Structure

### Employment List Response
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "employee_id": 123,
      "health_welfare": true,
      "pvd": true,
      "saving_fund": false,
      "health_welfare_percentage": 5.00,  // ✅ From benefit_settings
      "pvd_percentage": 3.00,              // ✅ From benefit_settings
      "saving_fund_percentage": 3.00       // ✅ From benefit_settings
    }
  ]
}
```

---

## Setup Instructions

### After Running `php artisan migrate:fresh`

You must run the benefit settings script to populate the `benefit_settings` table:

```bash
# Step 1: Create benefit settings
php create_benefit_settings.php

# Step 2: Verify settings were created
php verify_benefit_settings.php

# Step 3: Create test data (optional)
php create_test_data.php
```

---

## Managing Benefit Percentages

### Update Existing Percentage

**Option 1: Direct Database Update**
```sql
UPDATE benefit_settings
SET setting_value = 6.00,
    updated_by = 'admin',
    updated_at = GETDATE()
WHERE setting_key = 'health_welfare_percentage'
AND is_active = true;
```

**Option 2: Via Laravel Tinker**
```php
php artisan tinker

$setting = BenefitSetting::where('setting_key', 'health_welfare_percentage')->first();
$setting->setting_value = 6.00;
$setting->updated_by = 'admin';
$setting->save();  // Automatically clears cache
```

---

### Create Future-Dated Setting

To schedule a percentage change for the future:

```php
BenefitSetting::create([
    'setting_key' => 'health_welfare_percentage',
    'setting_value' => 6.00,
    'setting_type' => 'percentage',
    'description' => 'Updated Health and Welfare percentage for 2026',
    'effective_date' => '2026-01-01',
    'is_active' => true,
    'created_by' => 'admin',
    'updated_by' => 'admin',
]);
```

When January 1, 2026 arrives, the system will automatically use the new 6% rate because `getActiveSetting()` filters by `effective_date <= now()` and orders by `effective_date DESC`.

---

## Benefits of This Approach

### ✅ Centralized Management
- All benefit percentages in one table
- Easy to update globally
- Single source of truth

### ✅ Performance
- Cached for 1 hour
- Only 3 cache lookups per request (not per employment)
- Automatic cache invalidation

### ✅ Historical Tracking
- Keep history of percentage changes
- Track effective dates
- Audit trail with created_by/updated_by

### ✅ Flexibility
- Future-dated changes
- Conditional application (via `applies_to` JSON)
- Multiple versions with effective dates

### ✅ Backward Compatibility
- Frontend still receives `*_percentage` fields
- No frontend changes required
- API response structure unchanged

---

## Testing

### Test Employment List API
```bash
curl -X GET "http://localhost:8000/api/v1/employments?page=1&per_page=10" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

**Expected Response**:
```json
{
  "success": true,
  "data": [
    {
      "health_welfare_percentage": 5.00,  // ✅ From benefit_settings
      "pvd_percentage": 3.00,
      "saving_fund_percentage": 3.00
    }
  ]
}
```

### Test Employment Creation
```bash
curl -X POST "http://localhost:8000/api/v1/employments" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "employee_id": 1,
    "health_welfare": true,
    "pvd": true,
    "saving_fund": false
  }'
```

**Note**: No percentage fields needed in request body!

---

## Current Settings

As of 2025-01-01:

| Setting Key                  | Value  | Type       | Status |
|------------------------------|--------|------------|--------|
| health_welfare_percentage    | 5.00%  | percentage | Active |
| pvd_percentage               | 3.00%  | percentage | Active |
| saving_fund_percentage       | 3.00%  | percentage | Active |

---

## Files Modified/Created

### Created:
1. ✅ `create_benefit_settings.php` - Script to create benefit settings
2. ✅ `verify_benefit_settings.php` - Script to verify benefit settings
3. ✅ `BENEFIT_SETTINGS_SETUP.md` - This documentation

### Modified:
1. ✅ `create_test_data.php` - Removed percentage fields from employment creation

### Already Existing (No Changes):
1. ✅ `database/migrations/2025_11_09_000526_create_benefit_settings_table.php`
2. ✅ `app/Models/BenefitSetting.php`
3. ✅ `app/Http/Controllers/Api/EmploymentController.php` (already fixed)

---

## Summary

✅ **Benefit settings table populated**
✅ **Three benefit percentages configured (5%, 3%, 3%)**
✅ **Test data script updated to remove old percentage fields**
✅ **Verification script confirms settings working**
✅ **API endpoints will now return global benefit percentages**
✅ **Frontend remains backward compatible**

**Status**: Complete and Ready for Use

**Date**: 2025-11-11
**Version**: 1.0
