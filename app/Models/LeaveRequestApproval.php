<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="LeaveRequestApproval",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="leave_request_id", type="integer"),
 *     @OA\Property(property="approver_role", type="string", nullable=true),
 *     @OA\Property(property="approver_name", type="string", nullable=true),
 *     @OA\Property(property="approver_signature", type="string", nullable=true),
 *     @OA\Property(property="approval_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="status", type="string", nullable=true),
 *     @OA\Property(property="created_by", type="string", nullable=true),
 *     @OA\Property(property="updated_by", type="string", nullable=true)
 * )
 */
class LeaveRequestApproval extends Model
{
    //
    protected $table = 'leave_request_approvals';

    protected $fillable = [
        'leave_request_id',
        'approver_role',
        'approver_name',
        'approver_signature',
        'approval_date',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'approval_date' => 'date',
    ];

    public $timestamps = true;

    /**
     * Get the leave request that owns the approval.
     */
    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }
}
