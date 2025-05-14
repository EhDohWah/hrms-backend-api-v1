<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="PayrollGrantAllocation",
 *     title="Payroll Grant Allocation",
 *     description="Payroll Grant Allocation model",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="payroll_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="employee_grant_allocation_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="loe_snapshot", type="number", format="float", example=50.00),
 *     @OA\Property(property="amount", type="number", format="float", example=1000.00),
 *     @OA\Property(property="is_advance", type="boolean", example=false),
 *     @OA\Property(property="description", type="string", nullable=true, example="Salary allocation for research project"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="created_by", type="string", nullable=true, example="john.doe"),
 *     @OA\Property(property="updated_by", type="string", nullable=true, example="jane.smith")
 * )
 */
class PayrollGrantAllocation extends Model
{
    protected $fillable = [
        'payroll_id',
        'employee_grant_allocation_id',
        'loe_snapshot',
        'amount',
        'is_advance',
        'description',
        'created_by',
        'updated_by',
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class, 'payroll_id');
    }

    public function employeeGrantAllocation()
    {
        return $this->belongsTo(EmployeeGrantAllocation::class, 'employee_grant_allocation_id');
    }

}
