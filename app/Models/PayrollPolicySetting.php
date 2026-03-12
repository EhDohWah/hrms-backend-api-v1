<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PayrollPolicySetting',
    required: ['policy_key', 'policy_value', 'setting_type'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', readOnly: true),
        new OA\Property(property: 'policy_key', type: 'string', description: 'Unique identifier (e.g., thirteenth_month, salary_increase)'),
        new OA\Property(property: 'policy_value', type: 'number', format: 'float', nullable: true, description: 'Numeric value of the setting'),
        new OA\Property(property: 'setting_type', type: 'string', default: 'numeric', description: 'Type: percentage, boolean, numeric'),
        new OA\Property(property: 'category', type: 'string', nullable: true, description: 'Category: thirteenth_month, salary_increase'),
        new OA\Property(property: 'description', type: 'string', nullable: true, description: 'Human-readable description'),
        new OA\Property(property: 'effective_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'is_active', type: 'boolean', default: true),
        new OA\Property(property: 'created_by', type: 'string', nullable: true),
        new OA\Property(property: 'updated_by', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', readOnly: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', readOnly: true),
    ]
)]
class PayrollPolicySetting extends Model
{
    use HasFactory, SoftDeletes;

    // Policy keys — one row per policy
    const KEY_THIRTEENTH_MONTH = 'thirteenth_month';

    const KEY_SALARY_INCREASE = 'salary_increase';

    protected $fillable = [
        'policy_key',
        'policy_value',
        'setting_type',
        'category',
        'description',
        'effective_date',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'policy_value' => 'decimal:2',
        'effective_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    private const CACHE_KEY = 'payroll_policy';

    private const CACHE_TTL = 3600;

    /**
     * Get a policy setting by key.
     *
     * Returns false if the policy is inactive or not found.
     * Returns an array with policy data if active: ['policy_value' => ..., 'setting_type' => ..., ...]
     */
    public static function getActiveSetting(string $key): array|false
    {
        $cacheKey = self::CACHE_KEY.":{$key}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key) {
            $setting = self::query()
                ->where('policy_key', $key)
                ->first();

            if (! $setting || ! $setting->is_active) {
                return false;
            }

            return [
                'policy_value' => $setting->policy_value,
                'setting_type' => $setting->setting_type,
            ];
        });
    }

    /**
     * Clear cache for this policy.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY.":{$this->policy_key}");
    }

    /**
     * Get all available categories.
     */
    public static function getCategories(): array
    {
        return [
            self::KEY_THIRTEENTH_MONTH => '13th Month Salary',
            self::KEY_SALARY_INCREASE => 'Annual Salary Increase',
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
