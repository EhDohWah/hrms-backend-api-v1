<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Training',
    title: 'Training',
    description: 'Training model',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', description: 'Training ID'),
        new OA\Property(property: 'title', type: 'string', description: 'Training title'),
        new OA\Property(property: 'organizer', type: 'string', description: 'Training organizer'),
        new OA\Property(property: 'start_date', type: 'string', format: 'date', description: 'Training start date'),
        new OA\Property(property: 'end_date', type: 'string', format: 'date', description: 'Training end date'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', description: 'Creation date'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', description: 'Last update date'),
        new OA\Property(property: 'created_by', type: 'string', description: 'User who created the record'),
        new OA\Property(property: 'updated_by', type: 'string', description: 'User who last updated the record'),
    ]
)]
class Training extends Model
{
    //
    protected $table = 'trainings';

    protected $fillable = [
        'title',
        'organizer',
        'start_date',
        'end_date',
        'created_by',
        'updated_by',
    ];

    // Relationship: A training may have many employee trainings
    public function employeeTrainings()
    {
        return $this->hasMany(EmployeeTraining::class);
    }
}
