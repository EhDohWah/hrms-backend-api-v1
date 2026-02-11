<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Resignation',
    type: 'object',
    title: 'Resignation',
    description: 'Employee resignation model',
    required: ['employee_id', 'resignation_date', 'last_working_date', 'reason'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', description: 'Unique identifier for the resignation', example: 1),
        new OA\Property(property: 'employee_id', type: 'integer', format: 'int64', description: 'ID of the employee submitting resignation', example: 1),
        new OA\Property(property: 'department_id', type: 'integer', format: 'int64', description: 'ID of the employee\'s department', example: 5, nullable: true),
        new OA\Property(property: 'position_id', type: 'integer', format: 'int64', description: 'ID of the employee\'s position', example: 12, nullable: true),
        new OA\Property(property: 'resignation_date', type: 'string', format: 'date', description: 'Date when resignation was submitted', example: '2024-02-01'),
        new OA\Property(property: 'last_working_date', type: 'string', format: 'date', description: 'Employee\'s last day of work', example: '2024-02-29'),
        new OA\Property(property: 'reason', type: 'string', maxLength: 50, description: 'Primary reason for resignation', example: 'Career Advancement'),
        new OA\Property(property: 'reason_details', type: 'string', description: 'Detailed explanation of resignation reason', example: 'Accepted a position with better growth opportunities and higher compensation', nullable: true),
        new OA\Property(property: 'acknowledgement_status', type: 'string', description: 'Current status of the resignation', example: 'Pending', enum: ['Pending', 'Acknowledged', 'Rejected']),
        new OA\Property(property: 'acknowledged_by', type: 'integer', format: 'int64', description: 'ID of the user who acknowledged the resignation', example: 3, nullable: true),
        new OA\Property(property: 'acknowledged_at', type: 'string', format: 'date-time', description: 'Timestamp when resignation was acknowledged', example: '2024-02-02T10:30:00Z', nullable: true),
        new OA\Property(property: 'created_by', type: 'string', description: 'Name of user who created the record', example: 'John Doe', nullable: true),
        new OA\Property(property: 'updated_by', type: 'string', description: 'Name of user who last updated the record', example: 'Jane Smith', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', description: 'Record creation timestamp', example: '2024-02-01T09:00:00Z'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', description: 'Record last update timestamp', example: '2024-02-02T10:30:00Z'),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', description: 'Record deletion timestamp (soft delete)', example: '2024-03-01T14:20:00Z', nullable: true),
        new OA\Property(property: 'employee', ref: '#/components/schemas/Employee', description: 'Employee who submitted the resignation'),
        new OA\Property(property: 'department', ref: '#/components/schemas/Department', description: 'Employee\'s department information'),
        new OA\Property(property: 'position', ref: '#/components/schemas/Position', description: 'Employee\'s position information'),
        new OA\Property(property: 'acknowledged_by_user', ref: '#/components/schemas/User', description: 'User who acknowledged the resignation'),
        new OA\Property(property: 'notice_period_days', type: 'integer', description: 'Calculated notice period in days', example: 28),
        new OA\Property(property: 'days_until_last_working', type: 'integer', description: 'Days remaining until last working date', example: 15),
        new OA\Property(property: 'is_overdue', type: 'boolean', description: 'Whether the resignation is overdue for processing', example: false),
    ]
)]
class Resignation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'department_id',
        'position_id',
        'resignation_date',
        'last_working_date',
        'reason',
        'reason_details',
        'acknowledgement_status',
        'acknowledged_by',
        'acknowledged_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'resignation_date' => 'date',
        'last_working_date' => 'date',
        'acknowledged_at' => 'datetime',
    ];

    protected $dates = [
        'deleted_at',
        'resignation_date',
        'last_working_date',
        'acknowledged_at',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($resignation) {
            // Auto-populate department and position from employee if not provided
            if ($resignation->employee_id && (! $resignation->department_id || ! $resignation->position_id)) {
                $employee = Employee::with(['employment.department', 'employment.position'])->find($resignation->employee_id);
                if ($employee && $employee->employment) {
                    $resignation->department_id = $resignation->department_id ?? $employee->employment->department_id;
                    $resignation->position_id = $resignation->position_id ?? $employee->employment->position_id;
                }
            }
        });
    }

    /**
     * Relationships
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id')->withTrashed();
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * Scopes
     */
    public function scopePending($query)
    {
        return $query->where('acknowledgement_status', 'Pending');
    }

    public function scopeAcknowledged($query)
    {
        return $query->where('acknowledgement_status', 'Acknowledged');
    }

    public function scopeRejected($query)
    {
        return $query->where('acknowledgement_status', 'Rejected');
    }

    public function scopeCurrentMonth($query)
    {
        return $query->whereMonth('resignation_date', now()->month)
            ->whereYear('resignation_date', now()->year);
    }

    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeByReason($query, $reason)
    {
        return $query->where('reason', $reason);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->whereHas('employee', function ($employeeQuery) use ($search) {
                $employeeQuery->where('staff_id', 'like', "%{$search}%")
                    ->orWhereRaw("CONCAT(first_name_en, ' ', last_name_en) LIKE ?", ["%{$search}%"]);
            })
                ->orWhere('reason', 'like', "%{$search}%")
                ->orWhere('reason_details', 'like', "%{$search}%");
        });
    }

    /**
     * Accessors
     */
    public function getDaysUntilLastWorkingAttribute(): int
    {
        if (! $this->last_working_date) {
            return 0;
        }

        $days = now()->diffInDays(Carbon::parse($this->last_working_date), false);

        return max(0, $days);
    }

    public function getIsOverdueAttribute(): bool
    {
        return $this->last_working_date &&
               Carbon::parse($this->last_working_date)->isPast() &&
               $this->acknowledgement_status === 'Pending';
    }

    public function getNoticePeriodDaysAttribute(): int
    {
        if (! $this->resignation_date || ! $this->last_working_date) {
            return 0;
        }

        return Carbon::parse($this->resignation_date)
            ->diffInDays(Carbon::parse($this->last_working_date));
    }

    /**
     * Custom Methods
     */
    public function acknowledge(User $user): bool
    {
        $this->update([
            'acknowledgement_status' => 'Acknowledged',
            'acknowledged_by' => $user->id,
            'acknowledged_at' => now(),
        ]);

        return true;
    }

    public function reject(User $user): bool
    {
        $this->update([
            'acknowledgement_status' => 'Rejected',
            'acknowledged_by' => $user->id,
            'acknowledged_at' => now(),
        ]);

        return true;
    }
}
