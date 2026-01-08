<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'EmployeeEducation',
    title: 'Employee Education',
    description: 'Employee Education model',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 1, description: 'Unique identifier'),
        new OA\Property(property: 'employee_id', type: 'integer', format: 'int64', example: 1, description: 'ID of the employee'),
        new OA\Property(property: 'school_name', type: 'string', maxLength: 100, example: 'Harvard University', description: 'Name of the educational institution'),
        new OA\Property(property: 'degree', type: 'string', maxLength: 100, example: 'Bachelor of Science in Computer Science', description: 'Degree or qualification obtained'),
        new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2018-09-01', description: 'Start date of education'),
        new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2022-06-30', description: 'End date of education'),
        new OA\Property(property: 'created_by', type: 'string', maxLength: 100, example: 'admin', description: 'User who created the record'),
        new OA\Property(property: 'updated_by', type: 'string', maxLength: 100, example: 'admin', description: 'User who last updated the record'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2023-01-01T00:00:00.000000Z', description: 'Record creation timestamp'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2023-01-01T00:00:00.000000Z', description: 'Record last update timestamp'),
        new OA\Property(property: 'employee', description: 'Associated employee information', ref: '#/components/schemas/Employee'),
    ]
)]
class EmployeeEducation extends Model
{
    protected $fillable = [
        'employee_id',
        'school_name',
        'degree',
        'start_date',
        'end_date',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
