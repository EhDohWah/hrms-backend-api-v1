<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Payroll',
    type: 'object',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64'),
        new OA\Property(property: 'employment_id', type: 'integer', format: 'int64'),
        new OA\Property(property: 'employee_funding_allocation_id', type: 'integer', format: 'int64'),
        new OA\Property(property: 'pay_period_date', type: 'string', format: 'date'),
        new OA\Property(property: 'gross_salary', type: 'number', format: 'float'),
        new OA\Property(property: 'gross_salary_by_FTE', type: 'number', format: 'float'),
        new OA\Property(property: 'compensation_refund', type: 'number', format: 'float'),
        new OA\Property(property: 'thirteen_month_salary', type: 'number', format: 'float'),
        new OA\Property(property: 'thirteen_month_salary_accured', type: 'number', format: 'float'),
        new OA\Property(property: 'pvd', type: 'number', format: 'float'),
        new OA\Property(property: 'saving_fund', type: 'number', format: 'float'),
        new OA\Property(property: 'employer_social_security', type: 'number', format: 'float'),
        new OA\Property(property: 'employee_social_security', type: 'number', format: 'float'),
        new OA\Property(property: 'employer_health_welfare', type: 'number', format: 'float'),
        new OA\Property(property: 'employee_health_welfare', type: 'number', format: 'float'),
        new OA\Property(property: 'tax', type: 'number', format: 'float'),
        new OA\Property(property: 'net_salary', type: 'number', format: 'float'),
        new OA\Property(property: 'total_salary', type: 'number', format: 'float'),
        new OA\Property(property: 'total_pvd', type: 'number', format: 'float'),
        new OA\Property(property: 'total_saving_fund', type: 'number', format: 'float'),
        new OA\Property(property: 'salary_bonus', type: 'number', format: 'float'),
        new OA\Property(property: 'total_income', type: 'number', format: 'float'),
        new OA\Property(property: 'employer_contribution', type: 'number', format: 'float'),
        new OA\Property(property: 'total_deduction', type: 'number', format: 'float'),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
class Payroll extends Model
{
    use LogsActivity;

    protected $table = 'payrolls';

    protected $fillable = [
        'employment_id',
        'employee_funding_allocation_id',
        'gross_salary',
        'gross_salary_by_FTE',
        'compensation_refund',
        'thirteen_month_salary',
        'thirteen_month_salary_accured',
        'pvd',
        'saving_fund',
        'employer_social_security',
        'employee_social_security',
        'employer_health_welfare',
        'employee_health_welfare',
        'tax',
        'net_salary',
        'total_salary',
        'total_pvd',
        'total_saving_fund',
        'salary_bonus',
        'total_income',
        'employer_contribution',
        'total_deduction',
        'notes',
        'pay_period_date',
    ];

    public $timestamps = true;

    // Encrypted attributes - Laravel will automatically encrypt/decrypt these
    protected $casts = [
        'gross_salary' => 'encrypted',
        'gross_salary_by_FTE' => 'encrypted',
        'compensation_refund' => 'encrypted',
        'thirteen_month_salary' => 'encrypted',
        'thirteen_month_salary_accured' => 'encrypted',
        'pvd' => 'encrypted',
        'saving_fund' => 'encrypted',
        'employer_social_security' => 'encrypted',
        'employee_social_security' => 'encrypted',
        'employer_health_welfare' => 'encrypted',
        'employee_health_welfare' => 'encrypted',
        'tax' => 'encrypted',
        'net_salary' => 'encrypted',
        'total_salary' => 'encrypted',
        'total_pvd' => 'encrypted',
        'total_saving_fund' => 'encrypted',
        'salary_bonus' => 'encrypted',
        'total_income' => 'encrypted',
        'employer_contribution' => 'encrypted',
        'total_deduction' => 'encrypted',
        'pay_period_date' => 'date',
    ];

    // Define relationships
    public function employment()
    {
        return $this->belongsTo(Employment::class, 'employment_id');
    }

    public function employeeFundingAllocation()
    {
        return $this->belongsTo(EmployeeFundingAllocation::class, 'employee_funding_allocation_id');
    }

    // Access employee through employment relationship
    public function employee()
    {
        return $this->hasOneThrough(Employee::class, Employment::class, 'id', 'id', 'employment_id', 'employee_id');
    }

    public function grantAllocations()
    {
        return $this->hasMany(PayrollGrantAllocation::class, 'payroll_id');
    }

    // Query optimization scopes
    public function scopeForPagination($query)
    {
        return $query->select([
            'id',
            'employment_id',
            'employee_funding_allocation_id',
            'gross_salary',
            'gross_salary_by_FTE',
            'compensation_refund',
            'thirteen_month_salary',
            'thirteen_month_salary_accured',
            'pvd',
            'saving_fund',
            'employer_social_security',
            'employee_social_security',
            'employer_health_welfare',
            'employee_health_welfare',
            'tax',
            'net_salary',
            'total_salary',
            'total_pvd',
            'total_saving_fund',
            'salary_bonus',
            'total_income',
            'employer_contribution',
            'total_deduction',
            'notes',
            'pay_period_date',
            'created_at',
            'updated_at',
        ]);
    }

    public function scopeWithOptimizedRelations($query)
    {
        return $query->with([
            'employment.employee:id,staff_id,initial_en,first_name_en,last_name_en,organization,status',
            'employment:id,employee_id,department_id,position_id,pay_method,pvd,saving_fund,start_date,end_date',
            'employment.department:id,name',
            'employment.position:id,title,department_id',
            'employeeFundingAllocation:id,employee_id,employment_id,grant_item_id,allocation_type,fte,allocated_amount,status',
            'employeeFundingAllocation.grantItem:id,grant_id,grant_position,budgetline_code',
            'employeeFundingAllocation.grantItem.grant:id,name,code',
        ]);
    }

    public function scopeBySubsidiary($query, $subsidiaries)
    {
        if (is_string($subsidiaries)) {
            $subsidiaries = explode(',', $subsidiaries);
        }
        $subsidiaries = array_map('trim', array_filter($subsidiaries));

        return $query->whereHas('employment.employee', function ($q) use ($subsidiaries) {
            $q->whereIn('organization', $subsidiaries);
        });
    }

    public function scopeByDepartment($query, $departments)
    {
        if (is_string($departments)) {
            $departments = explode(',', $departments);
        }
        $departments = array_map('trim', array_filter($departments));

        return $query->whereHas('employment.department', function ($q) use ($departments) {
            $q->whereIn('name', $departments);
        });
    }

    public function scopeByPayPeriodDate($query, $dateFilter)
    {
        if (strpos($dateFilter, ',') !== false) {
            // Date range filter
            $dates = explode(',', $dateFilter);
            if (count($dates) === 2) {
                $startDate = trim($dates[0]);
                $endDate = trim($dates[1]);

                if ($startDate && $endDate) {
                    return $query->whereBetween('pay_period_date', [$startDate, $endDate]);
                }
            }
        } else {
            // Single date filter
            return $query->whereDate('pay_period_date', $dateFilter);
        }

        return $query;
    }

    public function scopeOrderByField($query, $sortBy, $sortOrder = 'desc')
    {
        switch ($sortBy) {
            case 'organization':
                return $query->join('employments', 'payrolls.employment_id', '=', 'employments.id')
                    ->join('employees', 'employments.employee_id', '=', 'employees.id')
                    ->orderBy('employees.organization', $sortOrder)
                    ->select('payrolls.*');

            case 'department':
                return $query->join('employments', 'payrolls.employment_id', '=', 'employments.id')
                    ->join('departments', 'employments.department_id', '=', 'departments.id')
                    ->orderBy('departments.name', $sortOrder)
                    ->select('payrolls.*');

            case 'staff_id':
                return $query->join('employments', 'payrolls.employment_id', '=', 'employments.id')
                    ->join('employees', 'employments.employee_id', '=', 'employees.id')
                    ->orderBy('employees.staff_id', $sortOrder)
                    ->select('payrolls.*');

            case 'payslip_date':
                return $query->orderBy('pay_period_date', $sortOrder);

            case 'employee_name':
                return $query->join('employments', 'payrolls.employment_id', '=', 'employments.id')
                    ->join('employees', 'employments.employee_id', '=', 'employees.id')
                    ->orderBy('employees.first_name_en', $sortOrder)
                    ->orderBy('employees.last_name_en', $sortOrder)
                    ->select('payrolls.*');

            case 'basic_salary':
                // Note: gross_salary is encrypted, so we fallback to created_at
                return $query->orderBy('created_at', $sortOrder);

            default:
                return $query->orderBy('created_at', $sortOrder);
        }
    }

    // Helper methods for filter options
    public static function getUniqueSubsidiaries()
    {
        return \App\Models\Employee::select('organization')
            ->distinct()
            ->whereNotNull('organization')
            ->where('organization', '!=', '')
            ->whereHas('employment.payrolls')
            ->orderBy('organization')
            ->pluck('organization');
    }

    public static function getUniqueDepartments()
    {
        return \App\Models\Department::select('name as department')
            ->where('is_active', true)
            ->whereNotNull('name')
            ->where('department', '!=', '')
            ->whereHas('employments.payrolls')
            ->orderBy('department')
            ->pluck('department');
    }

    public function scopeByStaffId($query, $staffId)
    {
        return $query->whereHas('employment.employee', function ($q) use ($staffId) {
            $q->where('staff_id', 'LIKE', "%{$staffId}%");
        });
    }

    public function scopeWithEmployeeInfo($query)
    {
        return $query->with([
            'employment.employee:id,staff_id,initial_en,first_name_en,last_name_en,organization,status',
            'employment:id,employee_id,department_id,position_id,pay_method,pvd,saving_fund,start_date,end_date',
            'employment.department:id,name',
            'employment.position:id,title,department_id',
            'employeeFundingAllocation:id,employee_id,employment_id,grant_item_id,allocation_type,fte,allocated_amount,status',
            'employeeFundingAllocation.grantItem:id,grant_id,grant_position,budgetline_code',
            'employeeFundingAllocation.grantItem.grant:id,name,code',
        ]);
    }

    /**
     * Get the display name for activity logs
     */
    public function getActivityLogName(): string
    {
        $period = $this->pay_period_date
            ? $this->pay_period_date->format('M Y')
            : '';
        $employeeName = '';

        if ($this->employment && $this->employment->employee) {
            $employeeName = trim(
                ($this->employment->employee->first_name_en ?? '').' '.
                ($this->employment->employee->last_name_en ?? '')
            );
        }

        if ($period && $employeeName) {
            return "{$period} - {$employeeName}";
        }

        return $period ?: $employeeName ?: "Payroll #{$this->id}";
    }
}
