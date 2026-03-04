<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'BenefitSetting',
    required: ['setting_key', 'setting_value', 'setting_type'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', readOnly: true),
        new OA\Property(property: 'setting_key', type: 'string', description: 'Unique identifier (e.g., health_welfare_percentage)'),
        new OA\Property(property: 'setting_value', type: 'number', format: 'float', description: 'Numeric value of the setting'),
        new OA\Property(property: 'setting_type', type: 'string', default: 'percentage', description: 'Type: percentage, boolean, numeric'),
        new OA\Property(property: 'category', type: 'string', nullable: true, description: 'Category: health_welfare, social_security, provident_fund, saving_fund'),
        new OA\Property(property: 'description', type: 'string', nullable: true, description: 'Human-readable description'),
        new OA\Property(property: 'effective_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'is_active', type: 'boolean', default: true),
        new OA\Property(property: 'applies_to', type: 'object', nullable: true, description: 'JSON conditions for applicability'),
        new OA\Property(property: 'created_by', type: 'string', nullable: true),
        new OA\Property(property: 'updated_by', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', readOnly: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', readOnly: true),
    ]
)]
class BenefitSetting extends Model
{
    use HasFactory, SoftDeletes;

    // Category constants
    const CATEGORY_HEALTH_WELFARE = 'health_welfare';

    const CATEGORY_SOCIAL_SECURITY = 'social_security';

    const CATEGORY_PROVIDENT_FUND = 'provident_fund';

    const CATEGORY_SAVING_FUND = 'saving_fund';

    // Social Security keys
    const KEY_SSF_EMPLOYEE_RATE = 'ssf_employee_rate';

    const KEY_SSF_EMPLOYER_RATE = 'ssf_employer_rate';

    const KEY_SSF_MIN_SALARY = 'ssf_min_salary';

    const KEY_SSF_MAX_SALARY = 'ssf_max_salary';

    const KEY_SSF_MAX_MONTHLY = 'ssf_max_monthly';

    const KEY_SSF_MAX_YEARLY = 'ssf_max_yearly';

    // Provident Fund keys
    const KEY_PVD_EMPLOYEE_RATE = 'pvd_employee_rate';

    const KEY_PVD_EMPLOYER_RATE = 'pvd_employer_rate';

    const KEY_PVD_MAX_ANNUAL = 'pvd_max_annual';

    // Saving Fund keys
    const KEY_SAVING_FUND_EMPLOYEE_RATE = 'saving_fund_employee_rate';

    const KEY_SAVING_FUND_EMPLOYER_RATE = 'saving_fund_employer_rate';

    const KEY_SAVING_FUND_MAX_ANNUAL = 'saving_fund_max_annual';

    // Health Welfare keys
    const KEY_HEALTH_WELFARE_EMPLOYER_ENABLED = 'health_welfare_employer_enabled';

    // Health Welfare tier keys — Non-Thai employee
    const KEY_HW_NONTHAI_EMPLOYEE_LOW = 'hw_nonthai_employee_low';

    const KEY_HW_NONTHAI_EMPLOYEE_MEDIUM = 'hw_nonthai_employee_medium';

    const KEY_HW_NONTHAI_EMPLOYEE_HIGH = 'hw_nonthai_employee_high';

    // Health Welfare tier keys — Non-Thai employer
    const KEY_HW_NONTHAI_EMPLOYER_LOW = 'hw_nonthai_employer_low';

    const KEY_HW_NONTHAI_EMPLOYER_MEDIUM = 'hw_nonthai_employer_medium';

    const KEY_HW_NONTHAI_EMPLOYER_HIGH = 'hw_nonthai_employer_high';

    // Health Welfare tier keys — Thai employee (no employer contribution)
    const KEY_HW_THAI_EMPLOYEE_LOW = 'hw_thai_employee_low';

    const KEY_HW_THAI_EMPLOYEE_MEDIUM = 'hw_thai_employee_medium';

    const KEY_HW_THAI_EMPLOYEE_HIGH = 'hw_thai_employee_high';

    /** Mass-assignable attributes */
    protected $fillable = [
        'setting_key',
        'setting_value',
        'setting_type',
        'category',
        'description',
        'effective_date',
        'is_active',
        'applies_to',
        'created_by',
        'updated_by',
    ];

    /** Attribute casting for type safety */
    protected $casts = [
        'setting_value' => 'decimal:2',
        'effective_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
        'applies_to' => 'array',
        'category' => \App\Enums\BenefitCategory::class,
        'setting_type' => \App\Enums\BenefitSettingType::class,
    ];

    private const CACHE_KEY = 'benefit_setting';

    private const CACHE_TTL = 3600;

    /**
     * Get active benefit setting by key
     */
    public static function getActiveSetting(string $key): ?float
    {
        $cacheKey = self::CACHE_KEY.":{$key}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key) {
            $setting = self::query()
                ->where('setting_key', $key)
                ->where('is_active', true)
                ->whereDate('effective_date', '<=', now())
                ->orderBy('effective_date', 'desc')
                ->first();

            return $setting?->setting_value;
        });
    }

    /**
     * Get all active settings for a category as key-value collection
     */
    public static function getActiveSettingsByCategory(string $category): array
    {
        $cacheKey = self::CACHE_KEY."_category:{$category}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($category) {
            return self::query()
                ->where('category', $category)
                ->where('is_active', true)
                ->whereDate('effective_date', '<=', now())
                ->orderBy('effective_date', 'desc')
                ->get()
                ->unique('setting_key')
                ->pluck('setting_value', 'setting_key')
                ->toArray();
        });
    }

    /**
     * Get employer health welfare eligibility config from the applies_to JSON
     * Returns ['eligible_statuses' => [...], 'eligible_organizations' => [...]]
     */
    public static function getEmployerHWEligibility(): ?array
    {
        $cacheKey = self::CACHE_KEY.':hw_employer_eligibility';

        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $setting = self::query()
                ->where('setting_key', self::KEY_HEALTH_WELFARE_EMPLOYER_ENABLED)
                ->where('is_active', true)
                ->first();

            if (! $setting || ! $setting->setting_value) {
                return null;
            }

            return $setting->applies_to;
        });
    }

    /**
     * Scope: filter by category
     */
    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    /**
     * Clear cache for this setting
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY.":{$this->setting_key}");

        if ($this->category) {
            Cache::forget(self::CACHE_KEY."_category:{$this->category->value}");
        }

        Cache::forget(self::CACHE_KEY.':hw_employer_eligibility');
    }

    /**
     * Get all available categories
     */
    public static function getCategories(): array
    {
        return [
            self::CATEGORY_HEALTH_WELFARE => 'Health Welfare',
            self::CATEGORY_SOCIAL_SECURITY => 'Social Security',
            self::CATEGORY_PROVIDENT_FUND => 'Provident Fund',
            self::CATEGORY_SAVING_FUND => 'Saving Fund',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (self $setting): void {
            $setting->clearCache();
        });

        static::deleted(function (self $setting): void {
            $setting->clearCache();
        });
    }
}
