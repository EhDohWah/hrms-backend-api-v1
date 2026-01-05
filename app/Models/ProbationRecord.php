<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="ProbationRecord",
 *     required={"employment_id", "employee_id", "event_type", "event_date", "probation_start_date", "probation_end_date"},
 *
 *     @OA\Property(property="id", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="employment_id", type="integer", format="int64"),
 *     @OA\Property(property="employee_id", type="integer", format="int64"),
 *     @OA\Property(property="event_type", type="string", enum={"initial", "extension", "passed", "failed"}),
 *     @OA\Property(property="event_date", type="string", format="date"),
 *     @OA\Property(property="decision_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="probation_start_date", type="string", format="date"),
 *     @OA\Property(property="probation_end_date", type="string", format="date"),
 *     @OA\Property(property="previous_end_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="extension_number", type="integer", default=0),
 *     @OA\Property(property="decision_reason", type="string", nullable=true),
 *     @OA\Property(property="evaluation_notes", type="string", nullable=true),
 *     @OA\Property(property="approved_by", type="string", nullable=true),
 *     @OA\Property(property="is_active", type="boolean", default=true),
 *     @OA\Property(property="created_by", type="string", nullable=true),
 *     @OA\Property(property="updated_by", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", readOnly=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", readOnly=true)
 * )
 */
class ProbationRecord extends Model
{
    use HasFactory;

    /** Event type constants */
    public const EVENT_INITIAL = 'initial';

    public const EVENT_EXTENSION = 'extension';

    public const EVENT_PASSED = 'passed';

    public const EVENT_FAILED = 'failed';

    /** Mass-assignable attributes */
    protected $fillable = [
        'employment_id',
        'employee_id',
        'event_type',
        'event_date',
        'decision_date',
        'probation_start_date',
        'probation_end_date',
        'previous_end_date',
        'extension_number',
        'decision_reason',
        'evaluation_notes',
        'approved_by',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /** Attribute casting for type safety */
    protected $casts = [
        'event_date' => 'date:Y-m-d',
        'decision_date' => 'date:Y-m-d',
        'probation_start_date' => 'date:Y-m-d',
        'probation_end_date' => 'date:Y-m-d',
        'previous_end_date' => 'date:Y-m-d',
        'extension_number' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the employment record that owns this probation record
     */
    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    /**
     * Get the employee that owns this probation record
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Scope to get only active probation records
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to filter by event type
     */
    public function scopeByEventType($query, string $eventType)
    {
        return $query->where('event_type', $eventType);
    }

    /**
     * Scope to get only extensions
     */
    public function scopeExtensions($query)
    {
        return $query->where('event_type', self::EVENT_EXTENSION);
    }

    /**
     * Scope to get initial probation records
     */
    public function scopeInitial($query)
    {
        return $query->where('event_type', self::EVENT_INITIAL);
    }

    /**
     * Scope to get passed probation records
     */
    public function scopePassed($query)
    {
        return $query->where('event_type', self::EVENT_PASSED);
    }

    /**
     * Scope to get failed probation records
     */
    public function scopeFailed($query)
    {
        return $query->where('event_type', self::EVENT_FAILED);
    }

    /**
     * Scope to get probation records for a specific employment
     */
    public function scopeForEmployment($query, int $employmentId)
    {
        return $query->where('employment_id', $employmentId);
    }

    /**
     * Scope to get probation records for a specific employee
     */
    public function scopeForEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Check if this is an extension record
     */
    public function isExtension(): bool
    {
        return $this->event_type === self::EVENT_EXTENSION;
    }

    /**
     * Check if this is the initial probation record
     */
    public function isInitial(): bool
    {
        return $this->event_type === self::EVENT_INITIAL;
    }

    /**
     * Check if probation was passed
     */
    public function isPassed(): bool
    {
        return $this->event_type === self::EVENT_PASSED;
    }

    /**
     * Check if probation was failed
     */
    public function isFailed(): bool
    {
        return $this->event_type === self::EVENT_FAILED;
    }

    /**
     * Get the event type label
     */
    public function getEventTypeLabelAttribute(): string
    {
        return match ($this->event_type) {
            self::EVENT_INITIAL => 'Probation Started',
            self::EVENT_EXTENSION => 'Probation Extended',
            self::EVENT_PASSED => 'Probation Passed',
            self::EVENT_FAILED => 'Probation Failed',
            default => $this->event_type,
        };
    }

    /**
     * Get the probation duration in days
     */
    public function getDurationInDaysAttribute(): int
    {
        return $this->probation_start_date->diffInDays($this->probation_end_date);
    }

    /**
     * Check if this record is currently active
     */
    public function isCurrentlyActive(): bool
    {
        return $this->is_active === true;
    }
}
