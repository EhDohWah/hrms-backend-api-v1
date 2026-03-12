# Payroll Policy Settings

## Design

Key-value pattern, same as `benefit_settings`. Each row is one setting with a key, value, and type.

### Schema

| Column | Type | Description |
|--------|------|-------------|
| `policy_key` | string(100) | Machine key, e.g. `thirteenth_month_divisor` |
| `policy_value` | decimal(10,2) nullable | Numeric value of the setting |
| `setting_type` | string(50) | `percentage`, `boolean`, or `numeric` |
| `category` | string(50) nullable | Grouping: `thirteenth_month`, `salary_increase` |
| `description` | string nullable | Human-readable label |
| `effective_date` | date nullable | When this setting takes effect |
| `is_active` | boolean | Enable/disable this setting |

### Seeded Settings

| Key | Value | Type | Category | Description |
|-----|-------|------|----------|-------------|
| `thirteenth_month_enabled` | 1 | boolean | thirteenth_month | Enable/disable 13th month salary |
| `thirteenth_month_divisor` | 12 | numeric | thirteenth_month | YTD gross / divisor = 13th month amount |
| `salary_increase_enabled` | 1 | boolean | salary_increase | Enable/disable annual salary increase |
| `salary_increase_rate` | 1.00 | percentage | salary_increase | Increase rate (1.00 = 1%) |
| `salary_increase_min_days` | 365 | numeric | salary_increase | Min calendar days of employment |
| `salary_increase_apply_month` | null | numeric | salary_increase | Month to apply (null = every eligible month) |

## How PayrollService Reads Settings

```php
// Same pattern as BenefitSetting::getActiveSetting()
$enabled = PayrollPolicySetting::getActiveSetting(PayrollPolicySetting::KEY_13TH_MONTH_ENABLED) ?? 1;
$divisor = PayrollPolicySetting::getActiveSetting(PayrollPolicySetting::KEY_13TH_MONTH_DIVISOR) ?? 12;
$rate    = PayrollPolicySetting::getActiveSetting(PayrollPolicySetting::KEY_SALARY_INCREASE_RATE) ?? 1.00;
```

Each call is cached per-key for 1 hour. Cache clears on save/delete.

## API Response

```
GET /api/v1/payroll-policy-settings
```

```json
{
  "success": true,
  "data": [...all setting rows...],
  "active_settings": {
    "thirteenth_month_enabled": "1.00",
    "thirteenth_month_divisor": "12.00",
    "salary_increase_enabled": "1.00",
    "salary_increase_rate": "1.00",
    "salary_increase_min_days": "365.00",
    "salary_increase_apply_month": null
  },
  "categories": {
    "thirteenth_month": "13th Month Salary",
    "salary_increase": "Annual Salary Increase"
  }
}
```

## Adding New Settings

1. Insert a new row via the API or seeder — no migration needed
2. Add a `KEY_*` constant on `PayrollPolicySetting` model
3. Read it in `PayrollService` with `getActiveSetting(KEY)`
