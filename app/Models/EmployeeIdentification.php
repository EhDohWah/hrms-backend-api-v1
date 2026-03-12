<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'EmployeeIdentification',
    title: 'Employee Identification',
    description: 'Employee identification document record',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64'),
        new OA\Property(property: 'employee_id', type: 'integer', format: 'int64'),
        new OA\Property(property: 'identification_type', type: 'string', maxLength: 50),
        new OA\Property(property: 'identification_number', type: 'string', maxLength: 50),
        new OA\Property(property: 'identification_issue_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'identification_expiry_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'first_name_en', type: 'string', nullable: true),
        new OA\Property(property: 'last_name_en', type: 'string', nullable: true),
        new OA\Property(property: 'first_name_th', type: 'string', nullable: true),
        new OA\Property(property: 'last_name_th', type: 'string', nullable: true),
        new OA\Property(property: 'initial_en', type: 'string', nullable: true),
        new OA\Property(property: 'initial_th', type: 'string', nullable: true),
        new OA\Property(property: 'is_primary', type: 'boolean'),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'created_by', type: 'string', nullable: true),
        new OA\Property(property: 'updated_by', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class EmployeeIdentification extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'employee_id',
        'identification_type',
        'identification_number',
        'identification_issue_date',
        'identification_expiry_date',
        'first_name_en',
        'last_name_en',
        'first_name_th',
        'last_name_th',
        'initial_en',
        'initial_th',
        'is_primary',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'identification_issue_date' => 'date',
        'identification_expiry_date' => 'date',
        'is_primary' => 'boolean',
    ];

    public const NAME_FIELDS = [
        'first_name_en',
        'last_name_en',
        'first_name_th',
        'last_name_th',
        'initial_en',
        'initial_th',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }
}
