<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'HolidayCompensationRecord',
    description: 'Record of employee working on a holiday and earning compensation days',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'employee_id', type: 'integer', example: 123),
        new OA\Property(property: 'holiday_id', type: 'integer', example: 5),
        new OA\Property(property: 'worked_date', type: 'string', format: 'date', example: '2025-01-01'),
        new OA\Property(property: 'compensation_days', type: 'number', format: 'float', example: 1.0),
        new OA\Property(property: 'used_days', type: 'number', format: 'float', example: 0.0),
        new OA\Property(property: 'remaining_days', type: 'number', format: 'float', example: 1.0),
        new OA\Property(property: 'expiry_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['available', 'partially_used', 'exhausted', 'expired'], example: 'available'),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'created_by', type: 'string', nullable: true),
        new OA\Property(property: 'updated_by', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
class HolidayCompensationRecord extends Model
{
    use HasFactory;

    protected $table = 'holiday_compensation_records';

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_PARTIALLY_USED = 'partially_used';

    public const STATUS_EXHAUSTED = 'exhausted';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'employee_id',
        'holiday_id',
        'worked_date',
        'compensation_days',
        'used_days',
        'remaining_days',
        'expiry_date',
        'status',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'worked_date' => 'date',
        'expiry_date' => 'date',
        'compensation_days' => 'decimal:1',
        'used_days' => 'decimal:1',
        'remaining_days' => 'decimal:1',
    ];

    /**
     * Get the employee that owns this compensation record.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the holiday that was worked.
     */
    public function holiday(): BelongsTo
    {
        return $this->belongsTo(Holiday::class);
    }

    /**
     * Scope to filter by employee.
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter available compensation days.
     */
    public function scopeAvailable($query)
    {
        return $query->whereIn('status', [self::STATUS_AVAILABLE, self::STATUS_PARTIALLY_USED])
            ->where('remaining_days', '>', 0);
    }

    /**
     * Scope to filter non-expired records.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expiry_date')
                ->orWhere('expiry_date', '>=', now()->toDateString());
        });
    }

    /**
     * Get total available compensation days for an employee.
     */
    public static function getAvailableDaysForEmployee(int $employeeId): float
    {
        return static::forEmployee($employeeId)
            ->available()
            ->notExpired()
            ->sum('remaining_days');
    }

    /**
     * Use compensation days and update status.
     *
     * @param  float  $days  Days to use
     * @return bool Whether the operation succeeded
     */
    public function useDays(float $days): bool
    {
        if ($days > $this->remaining_days) {
            return false;
        }

        $this->used_days += $days;
        $this->remaining_days -= $days;

        if ($this->remaining_days <= 0) {
            $this->status = self::STATUS_EXHAUSTED;
        } else {
            $this->status = self::STATUS_PARTIALLY_USED;
        }

        return $this->save();
    }

    /**
     * Restore used compensation days.
     *
     * @param  float  $days  Days to restore
     */
    public function restoreDays(float $days): bool
    {
        $this->used_days = max(0, $this->used_days - $days);
        $this->remaining_days = $this->compensation_days - $this->used_days;

        if ($this->remaining_days >= $this->compensation_days) {
            $this->status = self::STATUS_AVAILABLE;
        } elseif ($this->remaining_days > 0) {
            $this->status = self::STATUS_PARTIALLY_USED;
        }

        return $this->save();
    }
}
