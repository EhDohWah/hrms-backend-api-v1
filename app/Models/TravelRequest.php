<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'TravelRequest',
    title: 'Travel Request',
    description: 'Travel Request model',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int32', example: 1),
        new OA\Property(property: 'employee_id', type: 'integer', example: 1),
        new OA\Property(property: 'department_id', type: 'integer', example: 1, nullable: true),
        new OA\Property(property: 'position_id', type: 'integer', example: 1, nullable: true),
        new OA\Property(property: 'destination', type: 'string', example: 'New York', nullable: true),
        new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2025-04-01', nullable: true),
        new OA\Property(property: 'to_date', type: 'string', format: 'date', example: '2025-04-10', nullable: true),
        new OA\Property(property: 'purpose', type: 'string', example: 'Business meeting', nullable: true),
        new OA\Property(property: 'grant', type: 'string', example: 'Company funded', nullable: true),
        new OA\Property(property: 'transportation', type: 'string', example: 'air', description: 'Valid options: smru_vehicle, public_transportation, air, other', nullable: true),
        new OA\Property(property: 'transportation_other_text', type: 'string', example: 'Private car rental with driver', description: 'Required when transportation is \'other\'. Max 200 characters.', nullable: true),
        new OA\Property(property: 'accommodation', type: 'string', example: 'smru_arrangement', description: 'Valid options: smru_arrangement, self_arrangement, other', nullable: true),
        new OA\Property(property: 'accommodation_other_text', type: 'string', example: 'Family guest house near conference center', description: 'Required when accommodation is \'other\'. Max 200 characters.', nullable: true),
        new OA\Property(property: 'request_by_date', type: 'string', format: 'date', example: '2025-03-15', nullable: true),
        new OA\Property(property: 'supervisor_approved', type: 'boolean', example: false, default: false),
        new OA\Property(property: 'supervisor_approved_date', type: 'string', format: 'date', example: '2025-03-16', nullable: true),
        new OA\Property(property: 'hr_acknowledged', type: 'boolean', example: false, default: false),
        new OA\Property(property: 'hr_acknowledgement_date', type: 'string', format: 'date', example: '2025-03-17', nullable: true),
        new OA\Property(property: 'remarks', type: 'string', example: 'Approved with conditions', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2025-03-15T12:00:00Z'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2025-03-16T12:00:00Z'),
        new OA\Property(property: 'created_by', type: 'string', example: 'admin', nullable: true),
        new OA\Property(property: 'updated_by', type: 'string', example: 'admin', nullable: true),
        new OA\Property(
            property: 'employee',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'staff_id', type: 'string', example: 'EMP001'),
                new OA\Property(property: 'first_name_en', type: 'string', example: 'John'),
                new OA\Property(property: 'last_name_en', type: 'string', example: 'Doe'),
            ],
            type: 'object',
            nullable: true
        ),
        new OA\Property(
            property: 'department',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'name', type: 'string', example: 'Information Technology'),
            ],
            type: 'object',
            nullable: true
        ),
        new OA\Property(
            property: 'position',
            properties: [
                new OA\Property(property: 'id', type: 'integer', example: 1),
                new OA\Property(property: 'title', type: 'string', example: 'Software Developer'),
                new OA\Property(property: 'department_id', type: 'integer', example: 1),
            ],
            type: 'object',
            nullable: true
        ),
    ]
)]
class TravelRequest extends Model
{
    use HasFactory;

    protected $table = 'travel_requests';

    protected $fillable = [
        'employee_id',
        'department_id',
        'position_id',
        'destination',
        'start_date',
        'to_date',
        'purpose',
        'grant',
        'transportation',
        'transportation_other_text',
        'accommodation',
        'accommodation_other_text',
        'request_by_date',
        'supervisor_approved',
        'supervisor_approved_date',
        'hr_acknowledged',
        'hr_acknowledgement_date',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'supervisor_approved' => 'boolean',
        'hr_acknowledged' => 'boolean',
        'start_date' => 'date',
        'to_date' => 'date',
        'request_by_date' => 'date',
        'supervisor_approved_date' => 'date',
        'hr_acknowledgement_date' => 'date',
    ];

    // Relationships:
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    // Scopes for efficient querying
    public function scopeWithRelations($query)
    {
        return $query->with([
            'employee:id,staff_id,first_name_en,last_name_en',
            'department:id,name',
            'position:id,title,department_id',
        ]);
    }

    // Helper methods to get valid options
    public static function getTransportationOptions()
    {
        return [
            'smru_vehicle',
            'public_transportation',
            'air',
            'other',
        ];
    }

    public static function getAccommodationOptions()
    {
        return [
            'smru_arrangement',
            'self_arrangement',
            'other',
        ];
    }
}
