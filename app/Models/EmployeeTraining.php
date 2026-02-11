<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'EmployeeTraining',
    title: 'Employee Training',
    description: 'Employee Training model',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', description: 'Employee Training ID'),
        new OA\Property(property: 'employee_id', type: 'integer', format: 'int64', description: 'Employee ID'),
        new OA\Property(property: 'training_id', type: 'integer', format: 'int64', description: 'Training ID'),
        new OA\Property(property: 'status', type: 'string', description: 'Training status'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', description: 'Creation date'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', description: 'Last update date'),
        new OA\Property(property: 'created_by', type: 'string', description: 'User who created the record'),
        new OA\Property(property: 'updated_by', type: 'string', description: 'User who last updated the record'),
    ]
)]
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
        return $this->belongsTo(Employee::class)->withTrashed();
    }
}
