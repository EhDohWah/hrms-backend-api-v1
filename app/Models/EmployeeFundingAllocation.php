<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="EmployeeFundingAllocation",
 *     title="Employee Funding Allocation",
 *     description="Employee Funding Allocation model",
 *
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="employee_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="employment_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="org_funded_id", type="integer", format="int64", nullable=true, example=1),
 *     @OA\Property(property="position_slot_id", type="integer", format="int64", nullable=true, example=1),
 *     @OA\Property(property="level_of_effort", type="number", format="float", example=0.5),
 *     @OA\Property(property="allocation_type", type="string", enum={"grant", "org_funded"}, example="grant"),
 *     @OA\Property(property="allocated_amount", type="number", format="float", nullable=true, example=10000),
 *     @OA\Property(property="start_date", type="string", format="date", nullable=true, example="2023-01-01"),
 *     @OA\Property(property="end_date", type="string", format="date", nullable=true, example="2023-12-31"),
 *     @OA\Property(property="created_by", type="string", nullable=true, example="admin"),
 *     @OA\Property(property="updated_by", type="string", nullable=true, example="admin"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z")
 * )
 */
class EmployeeFundingAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'employment_id',
        'org_funded_id',
        'position_slot_id',
        'level_of_effort',
        'allocation_type',
        'allocated_amount',
        'start_date',
        'end_date',
        'created_by',
        'updated_by',
    ];

    // Relationships
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function employment()
    {
        return $this->belongsTo(Employment::class);
    }

    public function orgFunded()
    {
        return $this->belongsTo(OrgFundedAllocation::class, 'org_funded_id');
    }

    public function positionSlot()
    {
        return $this->belongsTo(PositionSlot::class);
    }
}
