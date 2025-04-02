<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="EmployeeChild",
 *     title="Employee Child",
 *     description="Employee Child model",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="employee_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="name", type="string", example="John Doe"),
 *     @OA\Property(property="date_of_birth", type="string", format="date", example="2020-01-01"),
 *     @OA\Property(property="created_by", type="string", example="admin"),
 *     @OA\Property(property="updated_by", type="string", example="admin"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z")
 * )
 */
class EmployeeChild extends Model
{
    protected $table = 'employee_children';

    protected $fillable = [
        'employee_id',
        'name',
        'date_of_birth',
        'created_by',
        'updated_by'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
