<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="EmployeeEducation",
 *     title="Employee Education",
 *     description="Employee Education model",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="employee_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="school_name", type="string", example="Harvard University"),
 *     @OA\Property(property="degree", type="string", example="Bachelor of Science"),
 *     @OA\Property(property="start_date", type="string", format="date", example="2018-09-01"),
 *     @OA\Property(property="end_date", type="string", format="date", example="2022-06-30"),
 *     @OA\Property(property="created_by", type="string", example="admin"),
 *     @OA\Property(property="updated_by", type="string", example="admin"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2023-01-01T00:00:00.000000Z")
 * )
 */
class EmployeeEducation extends Model
{
    protected $fillable = [
        'employee_id',
        'school_name',
        'degree',
        'start_date',
        'end_date',
        'created_by',
        'updated_by'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
