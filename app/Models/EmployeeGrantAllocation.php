<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Annotations as OA;
use App\Models\Employee;
use App\Models\GrantItem;

/**
 * @OA\Schema(
 *     schema="EmployeeGrantAllocation",
 *     type="object",
 *     required={"employee_id", "grant_items_id", "level_of_effort", "start_date", "active"},
 *     @OA\Property(property="employee_id", type="integer", example=1),
 *     @OA\Property(property="grant_items_id", type="integer", example=1),
 *     @OA\Property(property="level_of_effort", type="number", format="float", example=0.5),
 *     @OA\Property(property="start_date", type="string", format="date", example="2024-01-01"),
 *     @OA\Property(property="end_date", type="string", format="date", example="2024-12-31"),
 *     @OA\Property(property="active", type="boolean", example=true),
 *     @OA\Property(property="created_by", type="string", example="admin"),
 *     @OA\Property(property="updated_by", type="string", example="admin")
 * )
 */

class EmployeeGrantAllocation extends Model
{
    protected $fillable = [
        'employee_id',
        'grant_items_id',
        'level_of_effort',
        'start_date',
        'end_date',
        'active',
        'created_by',
        'updated_by'
    ];

    public function employeeAllocation()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function grantItemAllocation()
    {
        return $this->belongsTo(GrantItem::class, 'grant_items_id');
    }

}
