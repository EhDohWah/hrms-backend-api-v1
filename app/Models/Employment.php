<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\DepartmentPosition;
use App\Models\WorkLocation;
use App\Models\EmploymentHistory;
use App\Models\EmploymentGrantAllocation;

/**
 * @OA\Schema(
 *     schema="Employment",
 *     required={"employee_id", "employment_type", "start_date", "department_position_id", "work_location_id", "position_salary"},
 *     @OA\Property(property="id", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="employee_id", type="integer", format="int64"),
 *     @OA\Property(property="employment_type", type="string"),
 *     @OA\Property(property="start_date", type="string", format="date"),
 *     @OA\Property(property="probation_end_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="end_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="department_position_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="work_location_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="position_salary", type="number", format="float"),
 *     @OA\Property(property="probation_salary", type="number", format="float", nullable=true),
 *     @OA\Property(property="supervisor_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="employee_tax", type="number", format="float", nullable=true),
 *     @OA\Property(property="fte", type="number", format="float", nullable=true),
 *     @OA\Property(property="active", type="boolean", default=true),
 *     @OA\Property(property="health_welfare", type="boolean", default=false),
 *     @OA\Property(property="pvd", type="boolean", default=false),
 *     @OA\Property(property="saving_fund", type="boolean", default=false),
 *     @OA\Property(property="social_security_id", type="string", nullable=true),
 *     @OA\Property(property="created_by", type="string", nullable=true),
 *     @OA\Property(property="updated_by", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", readOnly=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", readOnly=true)
 * )
 */
class Employment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'employment_type',
        'start_date',
        'probation_end_date',
        'end_date',
        'department_position_id',
        'work_location_id',
        'position_salary',
        'probation_salary',
        'employee_tax',
        'fte',
        'active',
        'health_welfare',
        'pvd',
        'saving_fund',
        'created_by',
        'updated_by'
    ];

    protected static function boot()
    {
        parent::boot();
        // When a new Employment record is created:
        static::created(function ($employment) {
            // Create a history record with all the employment attributes
            EmploymentHistory::create([
                'employment_id' => $employment->id,
                'employee_id' => $employment->employee_id,
                'employment_type' => $employment->employment_type,
                'start_date' => $employment->start_date,
                'probation_end_date' => $employment->probation_end_date,
                'end_date' => $employment->end_date,
                'department_position_id' => $employment->department_position_id,
                'work_location_id' => $employment->work_location_id,
                'position_salary' => $employment->position_salary,
                'probation_salary' => $employment->probation_salary,
                'employee_tax' => $employment->employee_tax,
                'fte' => $employment->fte,
                'active' => $employment->active,
                'health_welfare' => $employment->health_welfare,
                'pvd' => $employment->pvd,
                'saving_fund' => $employment->saving_fund,
                'created_by' => $employment->created_by,
                'updated_by' => $employment->updated_by,
                'change_type' => 'created',
                'change_date' => now(),
            ]);
        });

        // When an existing Employment record is updated:
        static::updated(function ($employment) {
            EmploymentHistory::create([
                'employment_id' => $employment->id,
                'employee_id' => $employment->employee_id,
                'employment_type' => $employment->employment_type,
                'start_date' => $employment->start_date,
                'probation_end_date' => $employment->probation_end_date,
                'end_date' => $employment->end_date,
                'department_position_id' => $employment->department_position_id,
                'work_location_id' => $employment->work_location_id,
                'position_salary' => $employment->position_salary,
                'probation_salary' => $employment->probation_salary,
                'employee_tax' => $employment->employee_tax,
                'fte' => $employment->fte,
                'active' => $employment->active,
                'health_welfare' => $employment->health_welfare,
                'pvd' => $employment->pvd,
                'saving_fund' => $employment->saving_fund,
                'created_by' => $employment->created_by,
                'updated_by' => $employment->updated_by,
                'change_type' => 'updated',
                'change_date' => now(),
            ]);
        });
    }


    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }



    public function departmentPosition()
    {
        return $this->belongsTo(DepartmentPosition::class, 'department_position_id');
    }


    public function workLocation()
    {
        return $this->belongsTo(WorkLocation::class, 'work_location_id');
    }


    public function grantAllocations()
    {
        return $this->hasMany(EmploymentGrantAllocation::class, 'employment_id');
    }
}
