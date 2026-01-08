<?php

namespace App\Models;

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

    /** Mass-assignable attributes */
    protected $fillable = [
        'setting_key',
        'setting_value',
        'setting_type',
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
    ];

    /**
     * Cache key prefix for benefit settings
     */
    private const CACHE_KEY = 'benefit_setting';

    /**
     * Cache TTL in seconds (1 hour)
     */
    private const CACHE_TTL = 3600;

    /**
     * Get active benefit setting by key
     *
     * @param  string  $key  Setting key (e.g., 'health_welfare_percentage')
     * @return float|null Setting value or null if not found
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
     * Clear cache for this setting
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY.":{$this->setting_key}");
    }

    /**
     * Boot method to clear cache on model events
     */
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
