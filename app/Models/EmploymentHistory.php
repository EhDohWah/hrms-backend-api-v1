<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'EmploymentHistory',
    required: ['employment_id', 'employee_id', 'employment_type', 'start_date', 'pass_probation_salary'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', readOnly: true),
        new OA\Property(property: 'employment_id', type: 'integer', format: 'int64'),
        new OA\Property(property: 'employee_id', type: 'integer', format: 'int64'),
        new OA\Property(property: 'employment_type', type: 'string'),
        new OA\Property(property: 'start_date', type: 'string', format: 'date'),
        new OA\Property(property: 'pass_probation_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'pay_method', type: 'string', nullable: true),
        new OA\Property(property: 'department_id', type: 'integer', format: 'int64', nullable: true),
        new OA\Property(property: 'section_department_id', type: 'integer', format: 'int64', nullable: true),
        new OA\Property(property: 'position_id', type: 'integer', format: 'int64', nullable: true),
        new OA\Property(property: 'site_id', type: 'integer', format: 'int64', nullable: true),
        new OA\Property(property: 'pass_probation_salary', type: 'number', format: 'float'),
        new OA\Property(property: 'probation_salary', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'active', type: 'boolean', default: true),
        new OA\Property(property: 'health_welfare', type: 'boolean', default: false),
        new OA\Property(property: 'pvd', type: 'boolean', default: false),
        new OA\Property(property: 'saving_fund', type: 'boolean', default: false),
        new OA\Property(property: 'change_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'change_reason', type: 'string', nullable: true),
        new OA\Property(property: 'changed_by_user', type: 'string', nullable: true),
        new OA\Property(property: 'changes_made', type: 'object', nullable: true),
        new OA\Property(property: 'previous_values', type: 'object', nullable: true),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'created_by', type: 'string', nullable: true),
        new OA\Property(property: 'updated_by', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', readOnly: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', readOnly: true),
    ]
)]
class EmploymentHistory extends Model
{
    protected $fillable = [
        'employment_id',
        'employee_id',
        'employment_type',
        'start_date',
        'pass_probation_date',
        'pay_method',
        'department_id',
        'section_department_id',
        'position_id',
        'site_id',
        'section_department', // Legacy text field - retained for migration compatibility
        'pass_probation_salary',
        'probation_salary',
        'active',
        'health_welfare',
        'pvd',
        'saving_fund',
        'change_date',
        'change_reason',
        'changed_by_user',
        'changes_made',
        'previous_values',
        'notes',
        'created_by',
        'updated_by',
    ];

    public function employment()
    {
        return $this->belongsTo(Employment::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function sectionDepartment()
    {
        return $this->belongsTo(SectionDepartment::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * Alias for site() relationship for backward compatibility
     */
    public function workLocation()
    {
        return $this->site();
    }

    /**
     * Attribute casting for type safety
     */
    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'pass_probation_date' => 'date:Y-m-d',
        'change_date' => 'date:Y-m-d',
        'pass_probation_salary' => 'decimal:2',
        'probation_salary' => 'decimal:2',
        'active' => 'boolean',
        'health_welfare' => 'boolean',
        'pvd' => 'boolean',
        'saving_fund' => 'boolean',
        'changes_made' => 'array',
        'previous_values' => 'array',
    ];
}
