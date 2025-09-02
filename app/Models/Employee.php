<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Schema(
 *     schema="Employee",
 *     required={"staff_id", "first_name_en", "last_name_en", "gender", "date_of_birth", "status"},
 *
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
        'updated_by',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'date_of_birth' => 'date',
        'military_status' => 'boolean',
    ];

    /**
     * Get the user associated with the employee
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the employment record associated with the employee
     */
    public function employment()
    {
        return $this->hasOne(Employment::class);
    }

    /**
     * Get all employment records associated with the employee
     */
    public function employments()
    {
        return $this->hasMany(Employment::class);
    }

    /**
     * Check if employee has a user account
     *
     * @return bool
     */
    public function hasUserAccount()
    {
        return ! is_null($this->user_id);
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

    public function employeeFundingAllocations()
    {
        return $this->hasMany(EmployeeFundingAllocation::class, 'employee_id');
    }

    public function employeeLanguages()
    {
        return $this->hasMany(EmployeeLanguage::class);
    }

    public function employeeChildren()
    {
        return $this->hasMany(EmployeeChild::class);
    }

    // Parent information is stored directly in employees table
    // Helper methods for tax calculation

    /**
     * Get count of eligible parents for tax allowance
     * In Thai tax law, parents are eligible if they are over 60 and have income < 30,000 per year
     */
    public function getEligibleParentsCountAttribute(): int
    {
        $count = 0;

        // For now, assume parents are eligible if their names are provided
        // In a real system, you'd want separate parent records with age and income
        if (! empty($this->father_name)) {
            $count++;
        }

        if (! empty($this->mother_name)) {
            $count++;
        }

        return $count;
    }

    /**
     * Check if employee has spouse based on marital status and spouse name
     */
    public function getHasSpouseAttribute(): bool
    {
        return strtolower($this->marital_status) === 'married' || ! empty($this->spouse_name);
    }

    public function taxCalculationLogs()
    {
        return $this->hasMany(TaxCalculationLog::class);
    }

    public function employeeEducation()
    {
        return $this->hasMany(EmployeeEducation::class);
    }

    public function employeeTrainings()
    {
        return $this->hasMany(EmployeeTraining::class);
    }

    /**
     * Get the leave requests for the employee.
     */
    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Get the leave balances for the employee.
     */
    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    // Query optimization scopes
    public function scopeForPagination($query)
    {
        return $query->select([
            'employees.id',
            'employees.subsidiary',
            'employees.staff_id',
            'employees.initial_en',
            'employees.first_name_en',
            'employees.last_name_en',
            'employees.gender',
            'employees.date_of_birth',
            'employees.status',
            'employees.social_security_number',
            'employees.tax_number',
            'employees.mobile_phone',
            'employees.created_at',
            'employees.updated_at',
        ]);
    }

    public function scopeWithOptimizedRelations($query)
    {
        return $query->with([
            'employeeIdentification:id,employee_id,id_type,document_number,issue_date,expiry_date',
            'employment:id,employee_id,start_date,end_date',
        ]);
    }

    public function scopeBySubsidiary($query, $subsidiaries)
    {
        if (is_string($subsidiaries)) {
            $subsidiaries = explode(',', $subsidiaries);
        }

        return $query->whereIn('subsidiary', array_filter($subsidiaries));
    }

    public function scopeByStatus($query, $statuses)
    {
        if (is_string($statuses)) {
            $statuses = explode(',', $statuses);
        }

        return $query->whereIn('status', array_filter($statuses));
    }

    public function scopeByGender($query, $genders)
    {
        if (is_string($genders)) {
            $genders = explode(',', $genders);
        }

        return $query->whereIn('gender', array_filter($genders));
    }

    public function scopeByAge($query, $age)
    {
        if (is_numeric($age)) {
            $birthYear = now()->year - $age;

            return $query->whereYear('date_of_birth', $birthYear);
        }

        return $query;
    }

    public function scopeByIdType($query, $idTypes)
    {
        if (is_string($idTypes)) {
            $idTypes = explode(',', $idTypes);
        }

        return $query->whereHas('employeeIdentification', function ($q) use ($idTypes) {
            $q->whereIn('id_type', array_filter($idTypes));
        });
    }

    // Computed attributes
    public function getAgeAttribute()
    {
        if (! $this->date_of_birth) {
            return null;
        }

        return now()->diffInYears($this->date_of_birth);
    }

    public function getIdTypeAttribute()
    {
        return $this->employeeIdentification?->id_type;
    }

    // Helper methods for statistics
    public static function getStatistics(): array
    {
        return Cache::remember('employee_statistics', 300, function () {
            $now = now();
            $threeMonthsAgo = $now->copy()->subMonths(3);

            // Use SQL aggregation instead of loading ALL employees into memory
            return [
                'totalEmployees' => Employee::count(),
                'activeCount' => DB::table('employments')
                    ->join('employees', 'employees.id', '=', 'employments.employee_id')
                    ->where(function ($q) use ($now) {
                        $q->whereNull('employments.end_date')
                            ->orWhere('employments.end_date', '>', $now);
                    })
                    ->count(),
                'inactiveCount' => DB::table('employments')
                    ->join('employees', 'employees.id', '=', 'employments.employee_id')
                    ->whereNotNull('employments.end_date')
                    ->where('employments.end_date', '<=', $now)
                    ->count(),
                'newJoinerCount' => DB::table('employments')
                    ->join('employees', 'employees.id', '=', 'employments.employee_id')
                    ->whereBetween('employments.start_date', [$threeMonthsAgo, $now])
                    ->count(),
                'subsidiaryCount' => [
                    'SMRU_count' => Employee::where('subsidiary', 'SMRU')->count(),
                    'BHF_count' => Employee::where('subsidiary', 'BHF')->count(),
                ],
            ];
        });
    }
}
