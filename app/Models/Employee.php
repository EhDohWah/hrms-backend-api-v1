<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Employee',
    required: ['staff_id', 'first_name_en', 'last_name_en', 'gender', 'date_of_birth', 'status'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', readOnly: true),
        new OA\Property(property: 'staff_id', type: 'string', maxLength: 50),
        new OA\Property(property: 'organization', type: 'string', default: 'SMRU', enum: ['SMRU', 'BHF']),
        new OA\Property(property: 'initial_en', type: 'string', maxLength: 10, nullable: true),
        new OA\Property(property: 'initial_th', type: 'string', maxLength: 10, nullable: true),
        new OA\Property(property: 'first_name_en', type: 'string', maxLength: 255),
        new OA\Property(property: 'last_name_en', type: 'string', maxLength: 255),
        new OA\Property(property: 'first_name_th', type: 'string', maxLength: 255, nullable: true),
        new OA\Property(property: 'last_name_th', type: 'string', maxLength: 255, nullable: true),
        new OA\Property(property: 'gender', type: 'string', maxLength: 10),
        new OA\Property(property: 'date_of_birth', type: 'string', format: 'date'),
        new OA\Property(property: 'status', type: 'string', default: 'Local ID Staff', enum: ['Expats (Local)', 'Local ID Staff', 'Local non ID Staff']),
        new OA\Property(property: 'nationality', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(property: 'religion', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(property: 'identification_type', type: 'string', maxLength: 50, nullable: true),
        new OA\Property(property: 'identification_number', type: 'string', maxLength: 50, nullable: true),
        new OA\Property(property: 'identification_issue_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'identification_expiry_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'social_security_number', type: 'string', maxLength: 50, nullable: true),
        new OA\Property(property: 'tax_number', type: 'string', maxLength: 50, nullable: true),
        new OA\Property(property: 'bank_name', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(property: 'bank_branch', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(property: 'bank_account_name', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(property: 'bank_account_number', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(property: 'mobile_phone', type: 'string', maxLength: 20, nullable: true),
        new OA\Property(property: 'permanent_address', type: 'string', nullable: true),
        new OA\Property(property: 'current_address', type: 'string', nullable: true),
        new OA\Property(property: 'military_status', type: 'boolean', default: false),
        new OA\Property(property: 'marital_status', type: 'string', maxLength: 20, nullable: true),
        new OA\Property(property: 'spouse_name', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(property: 'spouse_phone_number', type: 'string', maxLength: 20, nullable: true),
        new OA\Property(property: 'emergency_contact_person_name', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(property: 'emergency_contact_person_relationship', type: 'string', maxLength: 50, nullable: true),
        new OA\Property(property: 'emergency_contact_person_phone', type: 'string', maxLength: 20, nullable: true),
        new OA\Property(property: 'father_name', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(property: 'father_occupation', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(property: 'father_phone_number', type: 'string', maxLength: 20, nullable: true),
        new OA\Property(property: 'mother_name', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(property: 'mother_occupation', type: 'string', maxLength: 100, nullable: true),
        new OA\Property(property: 'mother_phone_number', type: 'string', maxLength: 20, nullable: true),
        new OA\Property(property: 'driver_license_number', type: 'string', maxLength: 50, nullable: true),
        new OA\Property(property: 'remark', type: 'string', nullable: true),
        new OA\Property(property: 'created_by', type: 'string', nullable: true),
        new OA\Property(property: 'updated_by', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', readOnly: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', readOnly: true),
    ]
)]
class Employee extends Model
{
    use HasFactory, LogsActivity, Prunable, SoftDeletes;

    protected $fillable = [
        'organization',
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
        'identification_type',
        'identification_number',
        'identification_issue_date',
        'identification_expiry_date',
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
        'identification_issue_date' => 'date',
        'identification_expiry_date' => 'date',
        'military_status' => 'boolean',
    ];

    /**
     * Prunable query: permanently delete soft-deleted records after 90 days.
     */
    public function prunable()
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(90));
    }

    /**
     * Pre-deletion cleanup for multi-path FK children.
     *
     * SQL Server disallows CASCADE on tables with dual FK paths (e.g. employment_histories
     * has both employee_id → employees AND employment_id → employments). These children
     * must be deleted manually before forceDelete() runs.
     *
     * Leaf children (beneficiaries, children, education, etc.) are handled by DB-level CASCADE.
     */
    protected function pruning(): void
    {
        $employmentIds = DB::table('employments')->where('employee_id', $this->id)->pluck('id');

        if ($employmentIds->isNotEmpty()) {
            // Deepest children first to avoid FK violations
            DB::table('allocation_change_logs')->whereIn('employment_id', $employmentIds)->delete();
            DB::table('employee_funding_allocation_history')->whereIn('employment_id', $employmentIds)->delete();
            DB::table('employee_funding_allocations')->whereIn('employment_id', $employmentIds)->delete();
            DB::table('employment_histories')->whereIn('employment_id', $employmentIds)->delete();
            DB::table('probation_records')->whereIn('employment_id', $employmentIds)->delete();
            DB::table('personnel_actions')->whereIn('employment_id', $employmentIds)->delete();

            // Payroll children (blocker should have prevented these, but handle gracefully)
            $payrollIds = DB::table('payrolls')->whereIn('employment_id', $employmentIds)->pluck('id');
            if ($payrollIds->isNotEmpty()) {
                DB::table('payroll_grant_allocations')->whereIn('payroll_id', $payrollIds)->delete();
                DB::table('inter_organization_advances')->whereIn('payroll_id', $payrollIds)->delete();
                DB::table('payrolls')->whereIn('id', $payrollIds)->delete();
            }

            DB::table('employments')->whereIn('id', $employmentIds)->delete();
        }

        // Direct children with no FK constraint (would create orphans)
        DB::table('tax_calculation_logs')->where('employee_id', $this->id)->delete();

        Log::info("Pruning employee #{$this->id} ({$this->staff_id}) and all related records");
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
     * Get the beneficiaries for the employee
     */
    public function employeeBeneficiaries()
    {
        return $this->hasMany(EmployeeBeneficiary::class);
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

    public function employeeEducation(): \Illuminate\Database\Eloquent\Relations\HasMany
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

    /**
     * Get the holiday compensation records for the employee.
     */
    public function holidayCompensationRecords()
    {
        return $this->hasMany(HolidayCompensationRecord::class);
    }

    /**
     * Get the resignations for the employee.
     */
    public function resignations()
    {
        return $this->hasMany(Resignation::class);
    }

    // Query optimization scopes
    public function scopeForPagination($query)
    {
        return $query->select([
            'employees.id',
            'employees.organization',
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
            'employment:id,employee_id,start_date,end_probation_date',
        ]);
    }

    public function scopeByOrganization($query, $organizations)
    {
        if (is_string($organizations)) {
            $organizations = explode(',', $organizations);
        }

        return $query->whereIn('organization', array_filter($organizations));
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

        return $query->whereIn('identification_type', array_filter($idTypes));
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
        return $this->identification_type;
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
                    ->whereNull('employees.deleted_at')
                    ->where(function ($q) use ($now) {
                        $q->whereNull('employments.end_probation_date')
                            ->orWhere('employments.end_probation_date', '>', $now);
                    })
                    ->count(),
                'inactiveCount' => DB::table('employments')
                    ->join('employees', 'employees.id', '=', 'employments.employee_id')
                    ->whereNull('employees.deleted_at')
                    ->whereNotNull('employments.end_probation_date')
                    ->where('employments.end_probation_date', '<=', $now)
                    ->count(),
                'newJoinerCount' => DB::table('employments')
                    ->join('employees', 'employees.id', '=', 'employments.employee_id')
                    ->whereNull('employees.deleted_at')
                    ->whereBetween('employments.start_date', [$threeMonthsAgo, $now])
                    ->count(),
                'organizationCount' => [
                    'SMRU_count' => Employee::where('organization', 'SMRU')->count(),
                    'BHF_count' => Employee::where('organization', 'BHF')->count(),
                ],
            ];
        });
    }

    /**
     * Check for conditions that prevent deletion.
     * Returns array of blocker messages. Empty = safe to delete.
     */
    public function getDeletionBlockers(): array
    {
        $blockers = [];
        $employmentIds = $this->employments()->pluck('id');

        if ($employmentIds->isNotEmpty()) {
            $payrollCount = DB::table('payrolls')->whereIn('employment_id', $employmentIds)->whereNull('deleted_at')->count();
            if ($payrollCount > 0) {
                $blockers[] = "Cannot delete: {$payrollCount} payroll record(s) exist for this employee. Please delete or archive payrolls first.";
            }
        }

        return $blockers;
    }

    /**
     * Get the display name for activity logs
     */
    public function getActivityLogName(): string
    {
        $fullName = trim(($this->first_name_en ?? '').' '.($this->last_name_en ?? ''));

        return $fullName ?: ($this->staff_id ?? "Employee #{$this->id}");
    }
}
