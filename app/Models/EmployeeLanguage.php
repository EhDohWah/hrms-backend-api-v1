<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'EmployeeLanguage',
    title: 'Employee Language',
    description: 'Employee Language model',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'employee_id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'language', type: 'string', example: 'English'),
        new OA\Property(property: 'proficiency_level', type: 'string', example: 'Fluent'),
        new OA\Property(property: 'created_by', type: 'string', example: 'admin'),
        new OA\Property(property: 'updated_by', type: 'string', example: 'admin'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2023-01-01T00:00:00.000000Z'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2023-01-01T00:00:00.000000Z'),
    ]
)]
class EmployeeLanguage extends Model
{
    protected $fillable = [
        'employee_id',
        'language',
        'proficiency_level',
        'created_by',
        'updated_by',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }
}
