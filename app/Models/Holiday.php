<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Holiday',
    description: 'Organization public holiday / traditional day-off',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'New Year\'s Day'),
        new OA\Property(property: 'name_th', type: 'string', nullable: true, example: 'วันขึ้นปีใหม่'),
        new OA\Property(property: 'date', type: 'string', format: 'date', example: '2025-01-01'),
        new OA\Property(property: 'year', type: 'integer', example: 2025),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'First day of the new year'),
        new OA\Property(property: 'is_active', type: 'boolean', default: true, example: true),
        new OA\Property(property: 'created_by', type: 'string', nullable: true),
        new OA\Property(property: 'updated_by', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
class Holiday extends Model
{
    use HasFactory;

    protected $table = 'holidays';

    protected $fillable = [
        'name',
        'name_th',
        'date',
        'year',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'date' => 'date',
        'year' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get compensation records for this holiday.
     */
    public function compensationRecords(): HasMany
    {
        return $this->hasMany(HolidayCompensationRecord::class);
    }

    /**
     * Scope to filter only active holidays.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by year.
     */
    public function scopeForYear($query, int $year)
    {
        return $query->where('year', $year);
    }

    /**
     * Scope to filter holidays within a date range.
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    /**
     * Check if a given date is a holiday.
     */
    public static function isHoliday($date): bool
    {
        return static::active()
            ->whereDate('date', $date)
            ->exists();
    }

    /**
     * Get all holiday dates for a given year as an array.
     */
    public static function getDatesForYear(int $year): array
    {
        return static::active()
            ->forYear($year)
            ->pluck('date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->toArray();
    }

    /**
     * Get holiday dates within a date range.
     */
    public static function getDatesInRange($startDate, $endDate): array
    {
        return static::active()
            ->betweenDates($startDate, $endDate)
            ->pluck('date')
            ->map(fn ($d) => $d->format('Y-m-d'))
            ->toArray();
    }
}
