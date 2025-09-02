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

    // Query scopes for better performance
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('end_date')
                ->orWhere('end_date', '>', now());
        });
    }

    public function scopeByAllocationType($query, $type)
    {
        return $query->where('allocation_type', $type);
    }

    public function scopeGrant($query)
    {
        return $query->where('allocation_type', 'grant');
    }

    public function scopeOrgFunded($query)
    {
        return $query->where('allocation_type', 'org_funded');
    }

    public function scopeByEffortRange($query, $minEffort, $maxEffort = null)
    {
        $query->where('level_of_effort', '>=', $minEffort);

        if ($maxEffort !== null) {
            $query->where('level_of_effort', '<=', $maxEffort);
        }

        return $query;
    }

    public function scopeWithFullDetails($query)
    {
        return $query->with([
            'employee:id,staff_id,first_name_en,last_name_en,subsidiary',
            'employment:id,employee_id,start_date,end_date,position_salary',
            'positionSlot:id,grant_item_id,slot_number,budget_line_id',
            'positionSlot.grantItem:id,grant_id,grant_position,grant_salary',
            'positionSlot.grantItem.grant:id,name,code',
            'positionSlot.budgetLine:id,budget_line_code,description',
            'orgFunded.grant:id,name,code',
            'orgFunded.departmentPosition:id,department,position',
        ]);
    }

    public function scopeForPayrollCalculation($query)
    {
        return $query->active()
            ->select([
                'id', 'employee_id', 'employment_id', 'allocation_type',
                'level_of_effort', 'allocated_amount', 'position_slot_id', 'org_funded_id',
            ])
            ->with([
                'positionSlot:id,grant_item_id,slot_number',
                'positionSlot.grantItem.grant:id,name,code',
                'orgFunded.grant:id,name,code',
            ]);
    }
}
