<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="LeaveBalance",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="employee_id", type="integer"),
 *     @OA\Property(property="leave_type_id", type="integer"),
 *     @OA\Property(property="total_days", type="number", format="float", default=0),
 *     @OA\Property(property="used_days", type="number", format="float", default=0),
 *     @OA\Property(property="remaining_days", type="number", format="float", default=0),
 *     @OA\Property(property="year", type="integer"),
 *     @OA\Property(property="created_by", type="string", nullable=true),
 *     @OA\Property(property="updated_by", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", nullable=true)
 * )
 */
class LeaveBalance extends Model
{
    //
    protected $table = 'leave_balances';

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'total_days',
        'used_days',
        'remaining_days',
        'year',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'total_days' => 'decimal:2',
        'used_days' => 'decimal:2',
        'remaining_days' => 'decimal:2',
        'year' => 'integer',
    ];

    public $timestamps = true;

    /**
     * Get the employee that owns the leave balance.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the leave type that owns the leave balance.
     */
    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
