<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Employee;
use App\Models\DepartmentPosition;
use App\Models\TravelRequestApproval;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="TravelRequest",
 *     title="Travel Request",
 *     description="Travel Request model",
 *     @OA\Property(property="id", type="integer", format="int32", example=1),
 *     @OA\Property(property="employee_id", type="integer", example=1),
 *     @OA\Property(property="department_position_id", type="integer", example=1, nullable=true),
 *     @OA\Property(property="destination", type="string", example="New York", nullable=true),
 *     @OA\Property(property="start_date", type="string", format="date", example="2025-04-01", nullable=true),
 *     @OA\Property(property="end_date", type="string", format="date", example="2025-04-10", nullable=true),
 *     @OA\Property(property="purpose", type="string", example="Business meeting", nullable=true),
 *     @OA\Property(property="grant", type="string", example="Company funded", nullable=true),
 *     @OA\Property(property="transportation", type="string", example="Flight", nullable=true),
 *     @OA\Property(property="accommodation", type="string", example="Hotel", nullable=true),
 *     @OA\Property(property="request_by_signature", type="string", nullable=true),
 *     @OA\Property(property="request_by_fullname", type="string", example="John Doe", nullable=true),
 *     @OA\Property(property="request_by_date", type="string", format="date", example="2025-03-15", nullable=true),
 *     @OA\Property(property="remarks", type="string", example="Approved with conditions", nullable=true),
 *     @OA\Property(property="status", type="string", example="pending", default="pending"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-15T12:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-03-16T12:00:00Z"),
 *     @OA\Property(property="created_by", type="string", example="admin", nullable=true),
 *     @OA\Property(property="updated_by", type="string", example="admin", nullable=true)
 * )
 */
class TravelRequest extends Model
{
    protected $table = 'travel_requests';

    protected $fillable = [
        'employee_id',
        'department_position_id',
        'destination',
        'start_date',
        'end_date',
        'purpose',
        'grant',
        'transportation',
        'accommodation',
        'request_by_signature',
        'request_by_fullname',
        'request_by_date',
        'remarks',
        'status',
        'created_by',
        'updated_by'
    ];

    // Relationships:
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function departmentPosition()
    {
        return $this->belongsTo(DepartmentPosition::class);
    }

    public function approvals()
    {
        return $this->hasMany(TravelRequestApproval::class);
    }
}
