<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'PayrollPolicySetting',
    required: ['effective_date'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', readOnly: true),
        new OA\Property(property: 'thirteenth_month_enabled', type: 'boolean', default: true),
        new OA\Property(property: 'thirteenth_month_divisor', type: 'integer', default: 12),
        new OA\Property(property: 'thirteenth_month_min_months', type: 'integer', default: 6),
        new OA\Property(property: 'thirteenth_month_accrual_method', type: 'string', default: 'monthly'),
        new OA\Property(property: 'salary_increase_enabled', type: 'boolean', default: true),
        new OA\Property(property: 'salary_increase_rate', type: 'number', format: 'float', default: 1.00),
        new OA\Property(property: 'salary_increase_min_working_days', type: 'integer', default: 365),
        new OA\Property(property: 'salary_increase_effective_month', type: 'integer', nullable: true),
        new OA\Property(property: 'effective_date', type: 'string', format: 'date'),
        new OA\Property(property: 'is_active', type: 'boolean', default: true),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'created_by', type: 'string', nullable: true),
        new OA\Property(property: 'updated_by', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', readOnly: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', readOnly: true),
    ]
)]
class PayrollPolicySetting extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'thirteenth_month_enabled',
        'thirteenth_month_divisor',
        'thirteenth_month_min_months',
        'thirteenth_month_accrual_method',
        'salary_increase_enabled',
        'salary_increase_rate',
        'salary_increase_min_working_days',
        'salary_increase_effective_month',
        'effective_date',
        'is_active',
        'description',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'thirteenth_month_enabled' => 'boolean',
        'thirteenth_month_divisor' => 'integer',
        'thirteenth_month_min_months' => 'integer',
        'salary_increase_enabled' => 'boolean',
        'salary_increase_rate' => 'decimal:2',
        'salary_increase_min_working_days' => 'integer',
        'salary_increase_effective_month' => 'integer',
        'effective_date' => 'date:Y-m-d',
        'is_active' => 'boolean',
    ];

    private const CACHE_KEY = 'payroll_policy_active';

    private const CACHE_TTL = 3600;

    /**
     * Get the currently active policy (effective_date <= today, most recent first)
     */
    public static function getActivePolicy(): ?self
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return self::query()
                ->where('is_active', true)
                ->whereDate('effective_date', '<=', now())
                ->orderBy('effective_date', 'desc')
                ->first();
        });
    }

    /**
     * Clear cached active policy
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            self::clearCache();
        });

        static::deleted(function (): void {
            self::clearCache();
        });
    }
}
