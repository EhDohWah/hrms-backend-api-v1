<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="EmploymentHistory",
 *     required={"employment_id", "employee_id", "employment_type_id", "start_date", "position_id", "department_id", "work_location_id", "position_salary"},
 *
 *     @OA\Property(property="id", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="employment_id", type="integer", format="int64"),
 *     @OA\Property(property="employee_id", type="integer", format="int64"),
 *     @OA\Property(property="employment_type_id", type="integer", format="int64"),
 *     @OA\Property(property="start_date", type="string", format="date"),
 *     @OA\Property(property="probation_pass_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="end_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="position_id", type="integer", format="int64"),
 *     @OA\Property(property="department_id", type="integer", format="int64"),
 *     @OA\Property(property="work_location_id", type="integer", format="int64"),
 *     @OA\Property(property="position_salary", type="number", format="float"),
 *     @OA\Property(property="probation_salary", type="number", format="float", nullable=true),
 *     @OA\Property(property="supervisor_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="active", type="boolean", default=true),
 *     @OA\Property(property="health_welfare", type="boolean", default=false),
 *     @OA\Property(property="pvd", type="boolean", default=false),
 *     @OA\Property(property="saving_fund", type="boolean", default=false),
 *     @OA\Property(property="social_security_id", type="string", nullable=true),
 *     @OA\Property(property="grant_item_id", type="integer", format="int64", nullable=true),
 *     @OA\Property(property="created_by", type="string", nullable=true),
 *     @OA\Property(property="updated_by", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", readOnly=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", readOnly=true)
 * )
 */
class EmploymentHistory extends Model
{
    protected $fillable = [
        'employment_id',
        'employee_id',
        'employment_type',
        'start_date',
        'end_date',
        'probation_pass_date',
        'pay_method',
        'work_location_id',
        'position_salary',
        'probation_salary',
        'active',
        'health_welfare',
        'pvd',
        'saving_fund',
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

    public function employmentType()
    {
        return $this->belongsTo(EmploymentType::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function workLocation()
    {
        return $this->belongsTo(WorkLocation::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'supervisor_id');
    }

    public function grantItem()
    {
        return $this->belongsTo(GrantItem::class);
    }
}
