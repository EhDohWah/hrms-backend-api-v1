<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="EmployeeTraining",
 *     title="Employee Training",
 *     description="Employee Training model",
 *
 *     @OA\Property(property="id", type="integer", format="int64", description="Employee Training ID"),
 *     @OA\Property(property="employee_id", type="integer", format="int64", description="Employee ID"),
 *     @OA\Property(property="training_id", type="integer", format="int64", description="Training ID"),
 *     @OA\Property(property="status", type="string", description="Training status"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation date"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update date"),
 *     @OA\Property(property="created_by", type="string", description="User who created the record"),
 *     @OA\Property(property="updated_by", type="string", description="User who last updated the record")
 * )
 */
class EmployeeTraining extends Model
{
    protected $table = 'employee_trainings';

    protected $fillable = [
        'employee_id',
        'training_id',
        'status',
        'created_by',
        'updated_by',
    ];

    // Relationship: Each employee training belongs to a training
    public function training()
    {
        return $this->belongsTo(Training::class);
    }

    // Relationship: Each employee training belongs to an employee
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
