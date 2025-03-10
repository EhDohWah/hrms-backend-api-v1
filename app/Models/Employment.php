<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Models\Department;
use App\Models\WorkLocation;

/**
 * @OA\Schema(
 *     schema="Employment",
 *     required={"employee_id", "employment_type_id", "start_date", "position_id", "department_id", "work_location_id", "position_salary", "probation_salary"},
 *     @OA\Property(property="id", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="employee_id", type="integer", format="int64"),
 *     @OA\Property(property="employment_type_id", type="integer", format="int64"),
 *     @OA\Property(property="start_date", type="string", format="date"),
 *     @OA\Property(property="probation_end_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="end_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="position_id", type="integer", format="int64"),
 *     @OA\Property(property="department_id", type="integer", format="int64"),
 *     @OA\Property(property="work_location_id", type="integer", format="int64"),
 *     @OA\Property(property="position_salary", type="number", format="float"),
 *     @OA\Property(property="probation_salary", type="number", format="float"),
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
        'employment_type_id',
        'start_date',
        'probation_end_date',
        'end_date',
        'position_id',
        'department_id',
        'work_location_id',
        'position_salary',
        'probation_salary',
        'supervisor_id',
        'employee_tax',
        'fte',
        'active',
        'health_welfare',
        'pvd',
        'saving_fund',
        'social_security_id',
        'created_by',
        'updated_by'
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function employmentType()
    {
        return $this->belongsTo(EmploymentType::class, 'employment_type_id');
    }

    public function position()
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function workLocation()
    {
        return $this->belongsTo(WorkLocation::class, 'work_location_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }
}
