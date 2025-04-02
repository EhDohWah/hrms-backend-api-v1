<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\LeaveRequestApproval;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="LeaveRequest",
 *     type="object",
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

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType()
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approvals()
    {
        return $this->hasMany(LeaveRequestApproval::class);
    }

}
