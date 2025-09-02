<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="LeaveRequest",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="employee_id", type="integer"),
 *     @OA\Property(property="leave_type_id", type="integer"),
 *     @OA\Property(property="start_date", type="string", format="date"),
 *     @OA\Property(property="end_date", type="string", format="date"),
 *     @OA\Property(property="total_days", type="number"),
 *     @OA\Property(property="reason", type="string"),
 *     @OA\Property(property="status", type="string"),
 *     @OA\Property(property="created_by", type="string", nullable=true),
 *     @OA\Property(property="updated_by", type="string", nullable=true)
 * )
 */
class LeaveRequest extends Model
{
    //
    protected $table = 'leave_requests';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'total_days',
        'reason',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_days' => 'decimal:2',
    ];

    /**
     * Get the employee that owns the leave request.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the leave type that owns the leave request.
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * Get the approvals for the leave request.
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(LeaveRequestApproval::class);
    }

    /**
     * Get the attachments for the leave request.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(LeaveAttachment::class);
    }
}
