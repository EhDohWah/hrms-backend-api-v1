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
 *     required={"employee_id", "grant_item_id", "employment_id", "bg_line", "level_of_effort", "start_date"},
 *     @OA\Property(property="employee_id", type="integer", example=1),
 *     @OA\Property(property="grant_item_id", type="integer", example=1),
 *     @OA\Property(property="employment_id", type="integer", example=1),
 *     @OA\Property(property="bg_line", type="string", example="1"),
 *     @OA\Property(property="level_of_effort", type="number", format="float", example=0.5),
 *     @OA\Property(property="start_date", type="string", format="date", example="2024-01-01"),
 *     @OA\Property(property="end_date", type="string", format="date", example="2024-12-31"),
 *     @OA\Property(property="created_by", type="string", example="admin"),
 *     @OA\Property(property="updated_by", type="string", example="admin")
 * )
 */

class EmployeeGrantAllocation extends Model
{
    protected $fillable = [
        'employee_id',
        'grant_item_id',
        'employment_id',
        'bg_line',
        'level_of_effort',
        'active',
        'start_date',
        'end_date',
        'created_by',
        'updated_by',
    ];

    // protected $casts = [
    //     'level_of_effort' => 'decimal:2',
    //     'start_date' => 'date',
    //     'end_date' => 'date',
    // ];

    public function employeeAllocation()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function grantItemAllocation()
    {
        return $this->belongsTo(GrantItem::class, 'grant_item_id');
    }

}
