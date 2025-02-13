<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Employee;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Models\Department;
use App\Models\WorkLocation;


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
        'social_security_id',
        'employee_social_security',
        'employer_social_security',
        'employee_saving_fund',
        'employer_saving_fund',
        'employee_health_insurance',
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
