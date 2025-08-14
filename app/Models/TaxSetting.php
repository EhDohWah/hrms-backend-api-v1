<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Cache;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="TaxSetting",
 *     type="object",
 *     title="Tax Setting",
 *     description="Configurable tax settings for deductions, rates, and limits",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="setting_key", type="string", example="PERSONAL_ALLOWANCE", description="Unique setting identifier"),
 *     @OA\Property(property="setting_value", type="number", format="float", example=60000, description="Setting value"),
 *     @OA\Property(property="setting_type", type="string", enum={"DEDUCTION", "RATE", "LIMIT"}, example="DEDUCTION", description="Type of setting"),
 *     @OA\Property(property="description", type="string", example="Personal allowance for individual taxpayers"),
 *     @OA\Property(property="effective_year", type="integer", example=2025, description="Year this setting is effective"),
 *     @OA\Property(property="is_active", type="boolean", example=true, description="Whether setting is currently active"),
 *     @OA\Property(property="created_by", type="string", example="admin@example.com"),
 *     @OA\Property(property="updated_by", type="string", example="admin@example.com"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class TaxSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'setting_key',
        'setting_value',
        'setting_type',
        'description',
        'effective_year',
        'is_active',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'setting_value' => 'decimal:2',
        'is_active' => 'boolean',
        'effective_year' => 'integer'
    ];

    // Setting types
    const TYPE_DEDUCTION = 'DEDUCTION';
    const TYPE_RATE = 'RATE';
    const TYPE_LIMIT = 'LIMIT';

    // Common setting keys
    const KEY_PERSONAL_ALLOWANCE = 'PERSONAL_ALLOWANCE';
    const KEY_SPOUSE_ALLOWANCE = 'SPOUSE_ALLOWANCE';
    const KEY_CHILD_ALLOWANCE = 'CHILD_ALLOWANCE';
    const KEY_PERSONAL_EXPENSE_RATE = 'PERSONAL_EXPENSE_RATE';
    const KEY_PERSONAL_EXPENSE_MAX = 'PERSONAL_EXPENSE_MAX';
    const KEY_SSF_RATE = 'SSF_RATE';
    const KEY_SSF_MAX_MONTHLY = 'SSF_MAX_MONTHLY';
    const KEY_SSF_MAX_YEARLY = 'SSF_MAX_YEARLY';
    const KEY_PF_MIN_RATE = 'PF_MIN_RATE';
    const KEY_PF_MAX_RATE = 'PF_MAX_RATE';

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForYear(Builder $query, int $year): Builder
    {
        return $query->where('effective_year', $year);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('setting_type', $type);
    }

    // Get a specific setting value
    public static function getValue(string $key, int $year = null): ?float
    {
        $year = $year ?: date('Y');
        
        $cacheKey = "tax_setting_{$key}_{$year}";
        
        return Cache::remember($cacheKey, 3600, function () use ($key, $year) {
            $setting = static::active()
                ->forYear($year)
                ->where('setting_key', $key)
                ->first();
            
            return $setting ? $setting->setting_value : null;
        });
    }

    // Get all settings for a year grouped by type
    public static function getSettingsForYear(int $year = null): array
    {
        $year = $year ?: date('Y');
        
        $cacheKey = "tax_settings_all_{$year}";
        
        return Cache::remember($cacheKey, 3600, function () use ($year) {
            return static::active()
                ->forYear($year)
                ->get()
                ->groupBy('setting_type')
                ->map(function ($group) {
                    return $group->pluck('setting_value', 'setting_key');
                })
                ->toArray();
        });
    }

    // Clear cache when settings are updated
    protected static function boot()
    {
        parent::boot();
        
        static::saved(function ($model) {
            Cache::forget("tax_setting_{$model->setting_key}_{$model->effective_year}");
            Cache::forget("tax_settings_all_{$model->effective_year}");
        });
        
        static::deleted(function ($model) {
            Cache::forget("tax_setting_{$model->setting_key}_{$model->effective_year}");
            Cache::forget("tax_settings_all_{$model->effective_year}");
        });
    }
}
