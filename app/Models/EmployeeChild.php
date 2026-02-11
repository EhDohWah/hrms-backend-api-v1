<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'EmployeeChild',
    title: 'Employee Child',
    description: 'Employee Child model',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'employee_id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
        new OA\Property(property: 'date_of_birth', type: 'string', format: 'date', example: '2020-01-01'),
        new OA\Property(property: 'created_by', type: 'string', example: 'admin'),
        new OA\Property(property: 'updated_by', type: 'string', example: 'admin'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2023-01-01T00:00:00.000000Z'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2023-01-01T00:00:00.000000Z'),
    ]
)]
class EmployeeChild extends Model
{
    protected $table = 'employee_children';

    protected $fillable = [
        'employee_id',
        'name',
        'date_of_birth',
        'created_by',
        'updated_by',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }
}
