<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        new OA\Property(property: 'retroactive_salary', type: 'number', format: 'float', description: 'Retroactive adjustment: +ve=under-paid, -ve=over-paid'),
        new OA\Property(property: 'thirteen_month_salary', type: 'number', format: 'float'),
        new OA\Property(property: 'thirteen_month_salary_accured', type: 'number', format: 'float'),
        new OA\Property(property: 'pvd', type: 'number', format: 'float'),
        new OA\Property(property: 'saving_fund', type: 'number', format: 'float'),
        new OA\Property(property: 'study_loan', type: 'number', format: 'float', nullable: true, description: 'Monthly study loan deduction'),
        new OA\Property(property: 'employer_social_security', type: 'number', format: 'float'),
        new OA\Property(property: 'employee_social_security', type: 'number', format: 'float'),
        new OA\Property(property: 'employer_health_welfare', type: 'number', format: 'float'),
        new OA\Property(property: 'employee_health_welfare', type: 'number', format: 'float'),
        new OA\Property(property: 'tax', type: 'number', format: 'float'),
        new OA\Property(property: 'net_salary', type: 'number', format: 'float'),
        new OA\Property(property: 'total_salary', type: 'number', format: 'float'),
        new OA\Property(property: 'total_pvd', type: 'number', format: 'float'),
        new OA\Property(property: 'total_saving_fund', type: 'number', format: 'float'),
        new OA\Property(property: 'salary_increase', type: 'number', format: 'float'),
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
    use HasFactory, LogsActivity, Prunable, SoftDeletes;

    protected $table = 'payrolls';

    protected $fillable = [
        'employment_id',
        'employee_funding_allocation_id',
        'organization',

        // Snapshot fields (immutable after creation — point-in-time data)
        'snapshot_staff_id',
        'snapshot_employee_name',
        'snapshot_department',
        'snapshot_position',
        'snapshot_site',
        'snapshot_grant_code',
        'snapshot_grant_name',
        'snapshot_budget_line_code',
        'snapshot_fte',

        'gross_salary',
        'gross_salary_by_FTE',
        'retroactive_salary',
        'thirteen_month_salary',
        'thirteen_month_salary_accured',
        'pvd',
        'saving_fund',
        'study_loan',
        'employer_social_security',
        'employee_social_security',
        'employer_health_welfare',
        'employee_health_welfare',
        'tax',
        'net_salary',
        'total_salary',
        'total_pvd',
        'total_saving_fund',
        'salary_increase',
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
        'retroactive_salary' => 'encrypted',
        'thirteen_month_salary' => 'encrypted',
        'thirteen_month_salary_accured' => 'encrypted',
        'pvd' => 'encrypted',
        'saving_fund' => 'encrypted',
        'study_loan' => 'encrypted',
        'employer_social_security' => 'encrypted',
        'employee_social_security' => 'encrypted',
        'employer_health_welfare' => 'encrypted',
        'employee_health_welfare' => 'encrypted',
        'tax' => 'encrypted',
        'net_salary' => 'encrypted',
        'total_salary' => 'encrypted',
        'total_pvd' => 'encrypted',
        'total_saving_fund' => 'encrypted',
        'salary_increase' => 'encrypted',
        'total_income' => 'encrypted',
        'employer_contribution' => 'encrypted',
        'total_deduction' => 'encrypted',
        'pay_period_date' => 'date',
        'snapshot_fte' => 'decimal:4',
    ];

    /**
     * Prunable: auto-delete soft-deleted payrolls after 90 days.
     * Runs via `php artisan model:prune` (scheduled daily at 02:00).
     */
    public function prunable()
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(90));
    }

    /**
     * Clean up child records before permanent deletion (forceDelete).
     *
     * inter_organization_advances: NO ACTION FK — must delete manually.
     */
    protected function pruning(): void
    {
        // inter_organization_advances has NO ACTION FK on payroll_id
        DB::table('inter_organization_advances')
            ->where('payroll_id', $this->id)
            ->delete();

        Log::info("Pruning payroll #{$this->id} (pay period: {$this->pay_period_date})");
    }

    /**
     * Build the 9 snapshot fields from an employment + allocation pair.
     * Used by ProcessBulkPayroll and PayrollsImport to stamp point-in-time data.
     */
    public static function buildSnapshotFields(Employment $employment, EmployeeFundingAllocation $allocation): array
    {
        return [
            'snapshot_staff_id' => $employment->employee?->staff_id,
            'snapshot_employee_name' => $employment->employee?->full_name_en,
            'snapshot_department' => $employment->department?->name,
            'snapshot_position' => $employment->position?->title,
            'snapshot_site' => $employment->site?->name,
            'snapshot_grant_code' => $allocation->grantItem?->grant?->code,
            'snapshot_grant_name' => $allocation->grantItem?->grant?->name,
            'snapshot_budget_line_code' => $allocation->grantItem?->budgetline_code,
            'snapshot_fte' => $allocation->fte,
        ];
    }

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

    // Query optimization scopes
    public function scopeForPagination($query)
    {
        return $query->select([
            'id',
            'employment_id',
            'employee_funding_allocation_id',
            'organization',
            'snapshot_staff_id',
            'snapshot_employee_name',
            'snapshot_department',
            'snapshot_position',
            'snapshot_site',
            'snapshot_grant_code',
            'snapshot_grant_name',
            'snapshot_budget_line_code',
            'snapshot_fte',
            'gross_salary',
            'gross_salary_by_FTE',
            'retroactive_salary',
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
            'salary_increase',
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
            'employment.employee:id,staff_id,initial_en,first_name_en,last_name_en,status',
            'employment:id,employee_id,department_id,position_id,pay_method,pvd,saving_fund,start_date,end_probation_date',
            'employment.department:id,name',
            'employment.position:id,title,department_id',
            'employeeFundingAllocation:id,employee_id,employment_id,grant_item_id,fte,allocated_amount,status',
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

        return $query->whereIn('payrolls.organization', $subsidiaries);
    }

    public function scopeByOrganization($query, $organizations)
    {
        if (is_string($organizations)) {
            $organizations = explode(',', $organizations);
        }
        $organizations = array_map('trim', array_filter($organizations));

        return $query->whereIn('payrolls.organization', $organizations);
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
                return $query->orderBy('payrolls.organization', $sortOrder);

            case 'department':
                return $query->join('employments', 'payrolls.employment_id', '=', 'employments.id')
                    ->join('departments', 'employments.department_id', '=', 'departments.id')
                    ->whereNull('departments.deleted_at')
                    ->orderBy('departments.name', $sortOrder)
                    ->select('payrolls.*');

            case 'staff_id':
                return $query->join('employments', 'payrolls.employment_id', '=', 'employments.id')
                    ->join('employees', 'employments.employee_id', '=', 'employees.id')
                    ->whereNull('employees.deleted_at')
                    ->orderBy('employees.staff_id', $sortOrder)
                    ->select('payrolls.*');

            case 'payslip_date':
                return $query->orderBy('pay_period_date', $sortOrder);

            case 'employee_name':
                return $query->join('employments', 'payrolls.employment_id', '=', 'employments.id')
                    ->join('employees', 'employments.employee_id', '=', 'employees.id')
                    ->whereNull('employees.deleted_at')
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
        return Employment::select('organization')
            ->distinct()
            ->whereNotNull('organization')
            ->where('organization', '!=', '')
            ->whereNull('end_date')
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
            'employment.employee:id,staff_id,initial_en,first_name_en,last_name_en,status',
            'employment:id,employee_id,department_id,position_id,pay_method,pvd,saving_fund,start_date,end_probation_date',
            'employment.department:id,name',
            'employment.position:id,title,department_id',
            'employeeFundingAllocation:id,employee_id,employment_id,grant_item_id,fte,allocated_amount,status',
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
