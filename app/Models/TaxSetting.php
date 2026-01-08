<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'TaxSetting',
    title: 'Tax Setting',
    description: 'Configurable tax settings for deductions, rates, and limits',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'setting_key', type: 'string', example: 'PERSONAL_ALLOWANCE', description: 'Unique setting identifier'),
        new OA\Property(property: 'setting_value', type: 'number', format: 'float', example: 60000, description: 'Setting value'),
        new OA\Property(property: 'setting_type', type: 'string', example: 'DEDUCTION', enum: ['DEDUCTION', 'RATE', 'LIMIT'], description: 'Type of setting'),
        new OA\Property(property: 'description', type: 'string', example: 'Personal allowance for individual taxpayers'),
        new OA\Property(property: 'effective_year', type: 'integer', example: 2025, description: 'Year this setting is effective'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true, description: 'Whether setting is currently active'),
        new OA\Property(property: 'created_by', type: 'string', example: 'admin@example.com'),
        new OA\Property(property: 'updated_by', type: 'string', example: 'admin@example.com'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class TaxSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'setting_key',
        'setting_value',
        'setting_type',
        'description',
        'effective_year',
        'is_selected',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'setting_value' => 'decimal:2',
        'is_selected' => 'boolean',
        'effective_year' => 'integer',
    ];

    // Setting types
    const TYPE_DEDUCTION = 'DEDUCTION';

    const TYPE_RATE = 'RATE';

    const TYPE_LIMIT = 'LIMIT';

    const TYPE_ALLOWANCE = 'ALLOWANCE';

    // Setting categories for Thai compliance
    const CATEGORY_EMPLOYMENT = 'EMPLOYMENT';

    const CATEGORY_ALLOWANCE = 'ALLOWANCE';

    const CATEGORY_SOCIAL_SECURITY = 'SOCIAL_SECURITY';

    const CATEGORY_PROVIDENT_FUND = 'PROVIDENT_FUND';

    const CATEGORY_DEDUCTION = 'DEDUCTION';

    const CATEGORY_TEMPORARY = 'TEMPORARY';

    // Thai Revenue Department Setting Keys - Employment Deductions (Applied FIRST)
    const KEY_EMPLOYMENT_DEDUCTION_RATE = 'EMPLOYMENT_DEDUCTION_RATE'; // 50% of income

    const KEY_EMPLOYMENT_DEDUCTION_MAX = 'EMPLOYMENT_DEDUCTION_MAX';   // ฿100,000 maximum

    // Thai Revenue Department Setting Keys - Personal Allowances (Applied AFTER employment deductions)
    const KEY_PERSONAL_ALLOWANCE = 'PERSONAL_ALLOWANCE';               // ฿60,000

    const KEY_SPOUSE_ALLOWANCE = 'SPOUSE_ALLOWANCE';                   // ฿60,000

    const KEY_CHILD_ALLOWANCE = 'CHILD_ALLOWANCE';                     // ฿30,000 first child

    const KEY_CHILD_ALLOWANCE_SUBSEQUENT = 'CHILD_ALLOWANCE_SUBSEQUENT'; // ฿60,000 for children born 2018+

    const KEY_PARENT_ALLOWANCE = 'PARENT_ALLOWANCE';                   // ฿30,000 per eligible parent

    // Social Security Fund (SSF) - Fixed rates per Thai law
    const KEY_SSF_RATE = 'SSF_RATE';                                   // 5% (mandatory)

    const KEY_SSF_MIN_SALARY = 'SSF_MIN_SALARY';                       // ฿1,650 monthly

    const KEY_SSF_MAX_SALARY = 'SSF_MAX_SALARY';                       // ฿15,000 monthly

    const KEY_SSF_MAX_MONTHLY = 'SSF_MAX_MONTHLY';                     // ฿750 monthly

    const KEY_SSF_MAX_YEARLY = 'SSF_MAX_YEARLY';                       // ฿9,000 annually

    // Provident Fund (PF) - Negotiable rates
    const KEY_PF_MIN_RATE = 'PF_MIN_RATE';                            // 2% minimum

    const KEY_PF_MAX_RATE = 'PF_MAX_RATE';                            // 15% maximum

    const KEY_PF_MAX_ANNUAL = 'PF_MAX_ANNUAL';                        // ฿500,000 maximum annual

    // Additional Deduction Categories
    const KEY_HEALTH_INSURANCE_MAX = 'HEALTH_INSURANCE_MAX';           // ฿25,000

    const KEY_LIFE_INSURANCE_MAX = 'LIFE_INSURANCE_MAX';               // ฿100,000

    const KEY_MORTGAGE_INTEREST_MAX = 'MORTGAGE_INTEREST_MAX';         // ฿100,000

    const KEY_POLITICAL_DONATION_MAX = 'POLITICAL_DONATION_MAX';       // ฿10,000

    const KEY_THAI_ESG_FUND_MAX = 'THAI_ESG_FUND_MAX';               // ฿300,000 (2024-2026)

    // Temporary Deductions (2024-2025)
    const KEY_SHOPPING_ALLOWANCE = 'SHOPPING_ALLOWANCE';               // ฿50,000 for 2024

    const KEY_CONSTRUCTION_EXPENSE_MAX = 'CONSTRUCTION_EXPENSE_MAX';    // ฿100,000 (Apr 2024 - Dec 2025)

    // Legacy keys (for backwards compatibility)
    const KEY_PERSONAL_EXPENSE_RATE = 'PERSONAL_EXPENSE_RATE';         // Deprecated: Use EMPLOYMENT_DEDUCTION_RATE

    const KEY_PERSONAL_EXPENSE_MAX = 'PERSONAL_EXPENSE_MAX';           // Deprecated: Use EMPLOYMENT_DEDUCTION_MAX

    // Thai 2025 Official Values
    const THAI_2025_EMPLOYMENT_DEDUCTION_RATE = 50.0;                 // 50% of income

    const THAI_2025_EMPLOYMENT_DEDUCTION_MAX = 100000;                // ฿100,000 maximum

    const THAI_2025_PERSONAL_ALLOWANCE = 60000;                       // ฿60,000

    const THAI_2025_SPOUSE_ALLOWANCE = 60000;                         // ฿60,000

    const THAI_2025_CHILD_ALLOWANCE = 30000;                          // ฿30,000 first child

    const THAI_2025_CHILD_ALLOWANCE_SUBSEQUENT = 60000;               // ฿60,000 subsequent children (born 2018+)

    const THAI_2025_PARENT_ALLOWANCE = 30000;                         // ฿30,000 per eligible parent

    const THAI_2025_SSF_RATE = 5.0;                                   // 5% mandatory rate

    const THAI_2025_SSF_MIN_SALARY = 1650;                            // ฿1,650 monthly minimum

    const THAI_2025_SSF_MAX_SALARY = 15000;                           // ฿15,000 monthly maximum

    const THAI_2025_SSF_MAX_MONTHLY = 750;                            // ฿750 monthly maximum contribution

    const THAI_2025_SSF_MAX_YEARLY = 9000;                            // ฿9,000 annual maximum contribution

    // Boot method for cache clearing
    protected static function booted(): void
    {
        static::updated(function ($taxSetting) {
            // Clear specific caches
            $year = $taxSetting->effective_year;
            Cache::forget("tax_setting_{$taxSetting->setting_key}_{$year}");
            Cache::forget("tax_settings_all_{$year}");
            Cache::forget("tax_config_{$year}_settings");
            Cache::forget("tax_config_{$year}_brackets");

            // Clear pattern-based caches if possible
            try {
                Cache::tags(['tax_calculations'])->flush();
            } catch (\BadMethodCallException $e) {
                // Fallback for cache drivers that don't support tagging
                Cache::flush();
            }

            Log::info('Tax setting cache cleared', [
                'setting_id' => $taxSetting->id,
                'setting_key' => $taxSetting->setting_key,
                'effective_year' => $year,
                'is_selected' => $taxSetting->is_selected,
            ]);
        });

        static::created(function ($taxSetting) {
            try {
                Cache::tags(['tax_calculations'])->flush();
            } catch (\BadMethodCallException $e) {
                Cache::flush();
            }
        });

        static::deleted(function ($taxSetting) {
            try {
                Cache::tags(['tax_calculations'])->flush();
            } catch (\BadMethodCallException $e) {
                Cache::flush();
            }
        });
    }

    // Scopes
    public function scopeSelected(Builder $query): Builder
    {
        return $query->where('is_selected', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_selected', true);
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('effective_year', $year);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('setting_type', $type);
    }

    // Helper: Get selected allowances for current year
    public static function getSelectedAllowances(?int $year = null): \Illuminate\Database\Eloquent\Collection
    {
        $year = $year ?? date('Y');

        return static::selected()
            ->byType('ALLOWANCE')
            ->forYear($year)
            ->get();
    }

    // Helper: Get selected deductions for current year
    public static function getSelectedDeductions(?int $year = null): \Illuminate\Database\Eloquent\Collection
    {
        $year = $year ?? date('Y');

        return static::selected()
            ->byType('DEDUCTION')
            ->forYear($year)
            ->get();
    }

    // Helper: Get selected rates for current year
    public static function getSelectedRates(?int $year = null): \Illuminate\Database\Eloquent\Collection
    {
        $year = $year ?? date('Y');

        return static::selected()
            ->byType('RATE')
            ->forYear($year)
            ->get();
    }

    // Get a specific setting value
    public static function getValue(string $key, ?int $year = null): ?float
    {
        $year = $year ?: date('Y');

        $cacheKey = "tax_setting_{$key}_{$year}";

        return Cache::remember($cacheKey, 3600, function () use ($key, $year) {
            $setting = static::selected()
                ->forYear($year)
                ->where('setting_key', $key)
                ->first();

            return $setting ? $setting->setting_value : null;
        });
    }

    // Get all settings for a year grouped by type
    public static function getSettingsForYear(?int $year = null): array
    {
        $year = $year ?: date('Y');

        $cacheKey = "tax_settings_all_{$year}";

        return Cache::remember($cacheKey, 3600, function () use ($year) {
            return static::selected()
                ->forYear($year)
                ->get()
                ->groupBy('setting_type')
                ->map(function ($group) {
                    return $group->pluck('setting_value', 'setting_key');
                })
                ->toArray();
        });
    }

    // Get settings by category for Thai compliance
    public static function getSettingsByCategory(string $category, ?int $year = null): array
    {
        $year = $year ?: date('Y');

        $cacheKey = "tax_settings_category_{$category}_{$year}";

        return Cache::remember($cacheKey, 3600, function () use ($category, $year) {
            return static::selected()
                ->forYear($year)
                ->where('setting_category', $category)
                ->pluck('setting_value', 'setting_key')
                ->toArray();
        });
    }

    // Thai compliance validation methods
    public static function validateThaiCompliance(?int $year = null): array
    {
        $year = $year ?: date('Y');
        $errors = [];
        $warnings = [];

        // Check required employment deduction settings
        if (! static::getValue(self::KEY_EMPLOYMENT_DEDUCTION_RATE, $year)) {
            $errors[] = 'Employment deduction rate is required for Thai compliance';
        }

        if (! static::getValue(self::KEY_EMPLOYMENT_DEDUCTION_MAX, $year)) {
            $errors[] = 'Employment deduction maximum is required for Thai compliance';
        }

        // Check personal allowance settings
        if (! static::getValue(self::KEY_PERSONAL_ALLOWANCE, $year)) {
            $errors[] = 'Personal allowance is required for Thai compliance';
        }

        // Check Social Security Fund settings
        $ssfRate = static::getValue(self::KEY_SSF_RATE, $year);
        if (! $ssfRate || $ssfRate != 5.0) {
            $errors[] = 'SSF rate must be exactly 5% for Thai compliance';
        }

        // Check if using deprecated keys
        if (static::getValue(self::KEY_PERSONAL_EXPENSE_RATE, $year)) {
            $warnings[] = 'Using deprecated PERSONAL_EXPENSE_RATE. Use EMPLOYMENT_DEDUCTION_RATE instead.';
        }

        return [
            'is_compliant' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    // Get Thai 2025 default settings
    public static function getThaiDefaults2025(): array
    {
        return [
            // Employment deductions (applied FIRST)
            self::KEY_EMPLOYMENT_DEDUCTION_RATE => [
                'value' => self::THAI_2025_EMPLOYMENT_DEDUCTION_RATE,
                'type' => self::TYPE_RATE,
                'category' => self::CATEGORY_EMPLOYMENT,
                'description' => 'Employment income deduction rate - 50% of gross income (applied first)',
                'thai_law_reference' => 'Revenue Code Section 42(1)',
            ],
            self::KEY_EMPLOYMENT_DEDUCTION_MAX => [
                'value' => self::THAI_2025_EMPLOYMENT_DEDUCTION_MAX,
                'type' => self::TYPE_LIMIT,
                'category' => self::CATEGORY_EMPLOYMENT,
                'description' => 'Maximum employment income deduction - ฿100,000 annually',
                'thai_law_reference' => 'Revenue Code Section 42(1)',
            ],

            // Personal allowances (applied AFTER employment deductions)
            self::KEY_PERSONAL_ALLOWANCE => [
                'value' => self::THAI_2025_PERSONAL_ALLOWANCE,
                'type' => self::TYPE_ALLOWANCE,
                'category' => self::CATEGORY_ALLOWANCE,
                'description' => 'Personal allowance for individual taxpayer - ฿60,000',
                'thai_law_reference' => 'Revenue Code Section 42(2)',
            ],
            self::KEY_SPOUSE_ALLOWANCE => [
                'value' => self::THAI_2025_SPOUSE_ALLOWANCE,
                'type' => self::TYPE_ALLOWANCE,
                'category' => self::CATEGORY_ALLOWANCE,
                'description' => 'Spouse allowance (only if spouse has no income) - ฿60,000',
                'thai_law_reference' => 'Revenue Code Section 42(3)',
            ],
            self::KEY_CHILD_ALLOWANCE => [
                'value' => self::THAI_2025_CHILD_ALLOWANCE,
                'type' => self::TYPE_ALLOWANCE,
                'category' => self::CATEGORY_ALLOWANCE,
                'description' => 'Child allowance for first child - ฿30,000',
                'thai_law_reference' => 'Revenue Code Section 42(4)',
            ],
            self::KEY_CHILD_ALLOWANCE_SUBSEQUENT => [
                'value' => self::THAI_2025_CHILD_ALLOWANCE_SUBSEQUENT,
                'type' => self::TYPE_ALLOWANCE,
                'category' => self::CATEGORY_ALLOWANCE,
                'description' => 'Child allowance for subsequent children born 2018 onwards - ฿60,000',
                'thai_law_reference' => 'Revenue Code Section 42(4)',
            ],
            self::KEY_PARENT_ALLOWANCE => [
                'value' => self::THAI_2025_PARENT_ALLOWANCE,
                'type' => self::TYPE_ALLOWANCE,
                'category' => self::CATEGORY_ALLOWANCE,
                'description' => 'Parent allowance (age 60+, income < ฿30,000/year) - ฿30,000 per parent',
                'thai_law_reference' => 'Revenue Code Section 42(5)',
            ],

            // Social Security Fund
            self::KEY_SSF_RATE => [
                'value' => self::THAI_2025_SSF_RATE,
                'type' => self::TYPE_RATE,
                'category' => self::CATEGORY_SOCIAL_SECURITY,
                'description' => 'Social Security Fund contribution rate - 5% (mandatory)',
                'thai_law_reference' => 'Social Security Act',
            ],
            self::KEY_SSF_MIN_SALARY => [
                'value' => self::THAI_2025_SSF_MIN_SALARY,
                'type' => self::TYPE_LIMIT,
                'category' => self::CATEGORY_SOCIAL_SECURITY,
                'description' => 'Minimum monthly salary for SSF calculation - ฿1,650',
                'thai_law_reference' => 'Social Security Act',
            ],
            self::KEY_SSF_MAX_SALARY => [
                'value' => self::THAI_2025_SSF_MAX_SALARY,
                'type' => self::TYPE_LIMIT,
                'category' => self::CATEGORY_SOCIAL_SECURITY,
                'description' => 'Maximum monthly salary for SSF calculation - ฿15,000',
                'thai_law_reference' => 'Social Security Act',
            ],
            self::KEY_SSF_MAX_MONTHLY => [
                'value' => self::THAI_2025_SSF_MAX_MONTHLY,
                'type' => self::TYPE_LIMIT,
                'category' => self::CATEGORY_SOCIAL_SECURITY,
                'description' => 'Maximum monthly SSF contribution - ฿750',
                'thai_law_reference' => 'Social Security Act',
            ],
            self::KEY_SSF_MAX_YEARLY => [
                'value' => self::THAI_2025_SSF_MAX_YEARLY,
                'type' => self::TYPE_LIMIT,
                'category' => self::CATEGORY_SOCIAL_SECURITY,
                'description' => 'Maximum annual SSF contribution - ฿9,000',
                'thai_law_reference' => 'Social Security Act',
            ],
        ];
    }

    // Get all allowed setting keys
    public static function getAllowedKeys(): array
    {
        return [
            // Employment deductions
            self::KEY_EMPLOYMENT_DEDUCTION_RATE,
            self::KEY_EMPLOYMENT_DEDUCTION_MAX,

            // Personal allowances
            self::KEY_PERSONAL_ALLOWANCE,
            self::KEY_SPOUSE_ALLOWANCE,
            self::KEY_CHILD_ALLOWANCE,
            self::KEY_CHILD_ALLOWANCE_SUBSEQUENT,
            self::KEY_PARENT_ALLOWANCE,

            // Social Security Fund
            self::KEY_SSF_RATE,
            self::KEY_SSF_MIN_SALARY,
            self::KEY_SSF_MAX_SALARY,
            self::KEY_SSF_MAX_MONTHLY,
            self::KEY_SSF_MAX_YEARLY,

            // Provident Fund
            self::KEY_PF_MIN_RATE,
            self::KEY_PF_MAX_RATE,
            self::KEY_PF_MAX_ANNUAL,

            // Additional deductions
            self::KEY_HEALTH_INSURANCE_MAX,
            self::KEY_LIFE_INSURANCE_MAX,
            self::KEY_MORTGAGE_INTEREST_MAX,
            self::KEY_POLITICAL_DONATION_MAX,
            self::KEY_THAI_ESG_FUND_MAX,

            // Temporary deductions
            self::KEY_SHOPPING_ALLOWANCE,
            self::KEY_CONSTRUCTION_EXPENSE_MAX,

            // Legacy keys (deprecated but still allowed)
            self::KEY_PERSONAL_EXPENSE_RATE,
            self::KEY_PERSONAL_EXPENSE_MAX,

            // Test keys (for development/testing)
            'CHILD_ALLOWANCE_TEST',
        ];
    }

    // Get setting keys organized by category
    public static function getKeysByCategory(): array
    {
        return [
            'employment_deductions' => [
                self::KEY_EMPLOYMENT_DEDUCTION_RATE,
                self::KEY_EMPLOYMENT_DEDUCTION_MAX,
            ],
            'personal_allowances' => [
                self::KEY_PERSONAL_ALLOWANCE,
                self::KEY_SPOUSE_ALLOWANCE,
                self::KEY_CHILD_ALLOWANCE,
                self::KEY_CHILD_ALLOWANCE_SUBSEQUENT,
                self::KEY_PARENT_ALLOWANCE,
            ],
            'social_security' => [
                self::KEY_SSF_RATE,
                self::KEY_SSF_MIN_SALARY,
                self::KEY_SSF_MAX_SALARY,
                self::KEY_SSF_MAX_MONTHLY,
                self::KEY_SSF_MAX_YEARLY,
            ],
            'provident_fund' => [
                self::KEY_PF_MIN_RATE,
                self::KEY_PF_MAX_RATE,
                self::KEY_PF_MAX_ANNUAL,
            ],
            'additional_deductions' => [
                self::KEY_HEALTH_INSURANCE_MAX,
                self::KEY_LIFE_INSURANCE_MAX,
                self::KEY_MORTGAGE_INTEREST_MAX,
                self::KEY_POLITICAL_DONATION_MAX,
                self::KEY_THAI_ESG_FUND_MAX,
            ],
            'temporary_deductions' => [
                self::KEY_SHOPPING_ALLOWANCE,
                self::KEY_CONSTRUCTION_EXPENSE_MAX,
            ],
            'legacy_deprecated' => [
                self::KEY_PERSONAL_EXPENSE_RATE,
                self::KEY_PERSONAL_EXPENSE_MAX,
            ],
        ];
    }

    // Clear cache when settings are updated
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($model) {
            Cache::forget("tax_setting_{$model->setting_key}_{$model->effective_year}");
            Cache::forget("tax_settings_all_{$model->effective_year}");
            Cache::forget("tax_settings_category_{$model->setting_category}_{$model->effective_year}");
        });

        static::deleted(function ($model) {
            Cache::forget("tax_setting_{$model->setting_key}_{$model->effective_year}");
            Cache::forget("tax_settings_all_{$model->effective_year}");
            Cache::forget("tax_settings_category_{$model->setting_category}_{$model->effective_year}");
        });
    }
}
