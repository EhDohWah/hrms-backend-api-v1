<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Employment;
use App\Models\GrantPosition;
use App\Models\EmployeeBeneficiary;
use App\Models\EmployeeIdentification;
use App\Models\EmployeeGrantAllocation;

/**
 * @OA\Schema(
 *     schema="Employee",
 *     required={"staff_id", "first_name_en", "last_name_en", "gender", "date_of_birth", "status"},
 *     @OA\Property(property="id", type="integer", format="int64", readOnly=true),
 *     @OA\Property(property="staff_id", type="string", maxLength=50),
 *     @OA\Property(property="subsidiary", type="string", enum={"SMRU", "BHF"}, default="SMRU"),
 *     @OA\Property(property="user_id", type="integer", nullable=true),
 *     @OA\Property(property="department_position_id", type="integer", nullable=true),
 *     @OA\Property(property="initial_en", type="string", maxLength=10, nullable=true),
 *     @OA\Property(property="initial_th", type="string", maxLength=10, nullable=true),
 *     @OA\Property(property="first_name_en", type="string", maxLength=255),
 *     @OA\Property(property="last_name_en", type="string", maxLength=255),
 *     @OA\Property(property="first_name_th", type="string", maxLength=255, nullable=true),
 *     @OA\Property(property="last_name_th", type="string", maxLength=255, nullable=true),
 *     @OA\Property(property="gender", type="string", maxLength=10),
 *     @OA\Property(property="date_of_birth", type="string", format="date"),
 *     @OA\Property(property="status", type="string", enum={"Expats", "Local ID", "Local non ID"}, default="Expats"),
 *     @OA\Property(property="nationality", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="religion", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="social_security_number", type="string", maxLength=50, nullable=true),
 *     @OA\Property(property="tax_number", type="string", maxLength=50, nullable=true),
 *     @OA\Property(property="bank_name", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="bank_branch", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="bank_account_name", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="bank_account_number", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="mobile_phone", type="string", maxLength=20, nullable=true),
 *     @OA\Property(property="permanent_address", type="string", nullable=true),
 *     @OA\Property(property="current_address", type="string", nullable=true),
 *     @OA\Property(property="military_status", type="boolean", default=false),
 *     @OA\Property(property="marital_status", type="string", maxLength=20, nullable=true),
 *     @OA\Property(property="spouse_name", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="spouse_phone_number", type="string", maxLength=20, nullable=true),
 *     @OA\Property(property="emergency_contact_person_name", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="emergency_contact_person_relationship", type="string", maxLength=50, nullable=true),
 *     @OA\Property(property="emergency_contact_person_phone", type="string", maxLength=20, nullable=true),
 *     @OA\Property(property="father_name", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="father_occupation", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="father_phone_number", type="string", maxLength=20, nullable=true),
 *     @OA\Property(property="mother_name", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="mother_occupation", type="string", maxLength=100, nullable=true),
 *     @OA\Property(property="mother_phone_number", type="string", maxLength=20, nullable=true),
 *     @OA\Property(property="driver_license_number", type="string", maxLength=50, nullable=true),
 *     @OA\Property(property="remark", type="string", nullable=true),
 *     @OA\Property(property="created_by", type="string", nullable=true),
 *     @OA\Property(property="updated_by", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", readOnly=true),
 *     @OA\Property(property="updated_at", type="string", format="date-time", readOnly=true)
 * )
 */
class Employee extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'department_position_id',
        'subsidiary',
        'staff_id',
        'initial_en',
        'initial_th',
        'first_name_en',
        'last_name_en',
        'first_name_th',
        'last_name_th',
        'gender',
        'date_of_birth',
        'status',
        'nationality',
        'religion',
        'social_security_number',
        'tax_number',
        'bank_name',
        'bank_branch',
        'bank_account_name',
        'bank_account_number',
        'mobile_phone',
        'permanent_address',
        'current_address',
        'military_status',
        'marital_status',
        'spouse_name',
        'spouse_phone_number',
        'emergency_contact_person_name',
        'emergency_contact_person_relationship',
        'emergency_contact_person_phone',
        'father_name',
        'father_occupation',
        'father_phone_number',
        'mother_name',
        'mother_occupation',
        'mother_phone_number',
        'driver_license_number',
        'remark',
        'created_by',
        'updated_by'
    ];

    /**
     * Get the user associated with the employee
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the department position associated with the employee
     */
    public function departmentPosition()
    {
        return $this->belongsTo(DepartmentPosition::class);
    }

    /**
     * Get the employment record associated with the employee
     */
    public function employment()
    {
        return $this->hasOne(Employment::class);
    }

    /**
     * Check if employee has a user account
     *
     * @return bool
     */
    public function hasUserAccount()
    {
        return !is_null($this->user_id);
    }

    /**
     * Get the beneficiaries for the employee
     */
    public function employeeBeneficiaries()
    {
        return $this->hasMany(EmployeeBeneficiary::class);
    }

    /**
     * Get the identification record for the employee
     */
    public function employeeIdentification()
    {
        return $this->hasOne(EmployeeIdentification::class);
    }

    public function employeeGrantAllocations()
    {
        return $this->hasMany(EmployeeGrantAllocation::class, 'employee_id');
    }


}
