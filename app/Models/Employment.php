<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Schema(
 *   schema="Employment",
 *   required={"employee_id","employment_type","start_date","site_id","pass_probation_salary"},
 *
 *   @OA\Property(property="id", type="integer", format="int64", readOnly=true),
 *   @OA\Property(property="employee_id", type="integer", format="int64"),
 *   @OA\Property(property="employment_type", type="string"),
 *   @OA\Property(property="start_date", type="string", format="date"),
 *   @OA\Property(property="end_date", type="string", format="date", nullable=true),
 *   @OA\Property(property="pass_probation_date", type="string", format="date", nullable=true, description="First day employee receives pass_probation_salary - typically 3 months after start_date"),
 *   @OA\Property(property="pay_method", type="string", nullable=true),
 *   @OA\Property(property="site_id", type="integer", format="int64", nullable=true, description="Site/organizational location"),
 *   @OA\Property(property="section_department_id", type="integer", format="int64", nullable=true, description="Section department within main department"),
 *   @OA\Property(property="section_department", type="string", nullable=true, description="Legacy text field - being migrated to section_department_id"),
 *   @OA\Property(property="pass_probation_salary", type="number", format="float"),
 *   @OA\Property(property="probation_salary", type="number", format="float", nullable=true),

 *   @OA\Property(property="health_welfare", type="boolean", default=false),
 *   @OA\Property(property="health_welfare_percentage", type="number", format="float", nullable=true, description="Health & Welfare percentage (0-100)"),
 *   @OA\Property(property="pvd", type="boolean", default=false),
 *   @OA\Property(property="pvd_percentage", type="number", format="float", nullable=true, description="PVD percentage (0-100)"),
 *   @OA\Property(property="saving_fund", type="boolean", default=false),
 *   @OA\Property(property="saving_fund_percentage", type="number", format="float", nullable=true, description="Saving Fund percentage (0-100)"),
 *   @OA\Property(property="status", type="boolean", default=true, description="Employment status: true=Active, false=Inactive"),
 *   @OA\Property(property="created_by", type="string", nullable=true),
 *   @OA\Property(property="updated_by", type="string", nullable=true),
 *   @OA\Property(property="created_at", type="string", format="date-time", readOnly=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", readOnly=true)
 * )
 */
class Employment extends Model
{
    use HasFactory, LogsActivity;

    /** Employment status constants */
    public const STATUS_INACTIVE = false;

    public const STATUS_ACTIVE = true;

    /** Mass-assignable attributes */
    protected $fillable = [
        'employee_id',
        'employment_type',
        'start_date',
        'end_date',
        'pass_probation_date',
        'pay_method',
        'department_id',
        'position_id',
        'site_id',
        'section_department_id',
        'section_department', // Legacy - being phased out
        'pass_probation_salary',
        'probation_salary',
        'health_welfare',
        'health_welfare_percentage',
        'pvd',
        'pvd_percentage',
        'saving_fund',
        'saving_fund_percentage',
        'status',
        // NOTE: probation_status removed - use probation_records table instead
        'created_by',
        'updated_by',
    ];

    /** Attribute casting for type safety */
    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'pass_probation_date' => 'date:Y-m-d',
        'pass_probation_salary' => 'decimal:2',
        'probation_salary' => 'decimal:2',
        'health_welfare' => 'boolean',
        'health_welfare_percentage' => 'decimal:2',
        'pvd' => 'boolean',
        'pvd_percentage' => 'decimal:2',
        'saving_fund' => 'boolean',
        'saving_fund_percentage' => 'decimal:2',
        'status' => 'boolean',
    ];

    /**
     * Wire up audit-trail events using Laravel's `booted()` hook
     */
    protected static function booted(): void
    {
        // Initial creation
        static::created(function (self $employment): void {
            $employment->createHistoryRecord(
                type: 'created',
                reason: 'Initial employment record'
            );
        });

        // Subsequent updates
        static::updated(function (self $employment): void {
            $changes = collect($employment->getChanges())->except('updated_at')->all();
            $original = collect($employment->getOriginal())
                ->only(array_keys($changes))
                ->all();

            if (count($changes) > 0) {
                $employment->createHistoryRecord(
                    type: 'updated',
                    reason: $employment->generateChangeReason($changes),
                    changesMade: $changes,
                    previousValues: $original
                );
            }
        });
    }

    /**
     * Centralized history-record creation
     */
    protected function createHistoryRecord(
        string $type,
        string $reason,
        array $changesMade = [],
        array $previousValues = []
    ): void {
        EmploymentHistory::create([
            'employment_id' => $this->id,
            'employee_id' => $this->employee_id,
            'employment_type' => $this->employment_type,
            'start_date' => $this->start_date,
            'pass_probation_date' => $this->pass_probation_date,
            'pay_method' => $this->pay_method,
            'department_id' => $this->department_id,
            'position_id' => $this->position_id,
            'site_id' => $this->site_id,
            'section_department_id' => $this->section_department_id,
            'pass_probation_salary' => $this->pass_probation_salary,
            'probation_salary' => $this->probation_salary,
            'health_welfare' => $this->health_welfare,
            'pvd' => $this->pvd,
            'saving_fund' => $this->saving_fund,
            'change_date' => now(),
            'change_reason' => $reason,
            'changed_by_user' => Auth::user()?->name ?? $this->updated_by ?? 'system',
            'changes_made' => $changesMade ?: null,
            'previous_values' => $previousValues ?: null,
            'notes' => null,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
        ]);
    }

    /**
     * Human-readable summary of what changed
     */
    private function generateChangeReason(array $changes): string
    {
        $fieldMap = [
            'pass_probation_salary' => 'Salary adjustment',
            'department_id' => 'Department change',
            'position_id' => 'Position change',
            'site_id' => 'Site/location change',
            'section_department_id' => 'Section department change',
            'employment_type' => 'Employment type change',
            'pass_probation_date' => 'Probation period update',
            'pay_method' => 'Payment method change',
            'health_welfare' => 'Health welfare benefit change',
            'pvd' => 'PVD benefit change',
            'saving_fund' => 'Saving fund benefit change',
            'status' => 'Employment status change',
        ];

        $descriptions = collect($changes)
            ->keys()
            ->map(function ($field) use ($fieldMap, $changes) {
                $mapper = $fieldMap[$field] ?? null;
                if (is_callable($mapper)) {
                    return $mapper($changes[$field]);
                }

                return $mapper;
            })
            ->filter()
            ->take(3) // Limit to first 3 changes for readability
            ->implode(', ');

        return $descriptions ?: 'Employment details updated';
    }

    /**
     * Create a manual history entry with custom reason and notes
     */
    public function addHistoryEntry(string $reason, ?string $notes = null, ?string $changedBy = null): EmploymentHistory
    {
        return EmploymentHistory::create([
            'employment_id' => $this->id,
            'employee_id' => $this->employee_id,
            'employment_type' => $this->employment_type,
            'start_date' => $this->start_date,
            'pass_probation_date' => $this->pass_probation_date,
            'pay_method' => $this->pay_method,
            'department_id' => $this->department_id,
            'position_id' => $this->position_id,
            'site_id' => $this->site_id,
            'section_department_id' => $this->section_department_id,
            'pass_probation_salary' => $this->pass_probation_salary,
            'probation_salary' => $this->probation_salary,
            'health_welfare' => $this->health_welfare,
            'pvd' => $this->pvd,
            'saving_fund' => $this->saving_fund,
            'change_date' => now(),
            'change_reason' => $reason,
            'changed_by_user' => $changedBy ?? Auth::user()?->name ?? 'Manual Entry',
            'notes' => $notes,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
        ]);
    }

    /**
     * Update funding allocations after probation period ends
     * Recalculates allocated_amount using pass_probation_salary instead of probation_salary
     */
    public function updateFundingAllocationsAfterProbation(): bool
    {
        if (! $this->pass_probation_salary || ! $this->probation_salary) {
            return false;
        }

        $updated = 0;
        foreach ($this->employeeFundingAllocations as $allocation) {
            // Calculate new allocated amount based on pass_probation_salary
            $newAllocatedAmount = ($this->pass_probation_salary * $allocation->fte) / 100;

            $allocation->update([
                'allocated_amount' => $newAllocatedAmount,
                'updated_by' => Auth::user()?->name ?? 'system',
            ]);

            $updated++;
        }

        return $updated > 0;
    }

    /**
     * Check if employee is currently on probation
     */
    public function isOnProbation(): bool
    {
        if (! $this->pass_probation_date) {
            return false;
        }

        return now()->lt($this->pass_probation_date);
    }

    /**
     * Get the current applicable salary based on probation status
     */
    public function getCurrentSalary(): float
    {
        if ($this->isOnProbation() && $this->probation_salary) {
            return (float) $this->probation_salary;
        }

        return (float) $this->pass_probation_salary;
    }

    /**
     * Check if employment is active
     */
    public function isActive(): bool
    {
        return $this->status === true;
    }

    /**
     * Check if employment is inactive
     */
    public function isInactive(): bool
    {
        return $this->status === false;
    }

    /**
     * Activate employment
     */
    public function activate(): bool
    {
        return $this->update(['status' => true]);
    }

    /**
     * Deactivate employment
     */
    public function deactivate(): bool
    {
        return $this->update(['status' => false]);
    }

    /**
     * Get status label
     */
    public function getStatusLabelAttribute(): string
    {
        return $this->status ? 'Active' : 'Inactive';
    }

    /**
     * Check if probation period has ended
     */
    public function hasProbationEnded(): bool
    {
        if (! $this->pass_probation_date) {
            return false;
        }

        return now()->gte($this->pass_probation_date);
    }

    /**
     * Check if employment is currently active (within start and end dates)
     */
    public function isCurrentlyActive(): bool
    {
        $today = now()->startOfDay();

        return $this->start_date <= $today &&
               ($this->end_date === null || $this->end_date >= $today);
    }

    /**
     * Check if employment was terminated before probation completion
     */
    public function wasTerminatedEarly(): bool
    {
        if (! $this->end_date || ! $this->pass_probation_date) {
            return false;
        }

        return $this->end_date < $this->pass_probation_date;
    }

    /**
     * Get the salary type that should be used for a specific date
     */
    public function getSalaryTypeForDate($date): string
    {
        $date = $date instanceof \Carbon\Carbon ? $date : \Carbon\Carbon::parse($date);

        if (! $this->pass_probation_date) {
            return 'pass_probation_salary';
        }

        if ($date->lt($this->pass_probation_date)) {
            return $this->probation_salary ? 'probation_salary' : 'pass_probation_salary';
        }

        return 'pass_probation_salary';
    }

    /**
     * Resolve the monetary salary that applies to a given date.
     */
    public function getSalaryAmountForDate($date): float
    {
        $salaryType = $this->getSalaryTypeForDate($date);

        if ($salaryType === 'probation_salary' && $this->probation_salary) {
            return (float) $this->probation_salary;
        }

        return (float) $this->pass_probation_salary;
    }

    /**
     * Calculate allocated amount for given FTE and date
     */
    public function calculateAllocatedAmount(float $fte, $date = null): float
    {
        $date = $date ?? now();
        $salaryType = $this->getSalaryTypeForDate($date);

        $baseSalary = $this->getSalaryAmountForDate($date);

        return round($baseSalary * $fte, 2);
    }

    /**
     * Check if employment is ready for probation transition
     */
    public function isReadyForTransition(): bool
    {
        if (! $this->pass_probation_date) {
            return false;
        }

        // Check if probation already passed by checking active probation record
        $activeProbation = $this->activeProbationRecord;
        $alreadyPassed = $activeProbation && $activeProbation->event_type === \App\Models\ProbationRecord::EVENT_PASSED;

        return $this->pass_probation_date->isToday() &&
               ! $this->end_date &&
               ! $alreadyPassed;
    }

    // — Relationships —
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function employeeFundingAllocations()
    {
        return $this->hasMany(EmployeeFundingAllocation::class, 'employment_id');
    }

    public function activeAllocations()
    {
        return $this->hasMany(EmployeeFundingAllocation::class, 'employment_id')
            ->where('status', 'active');
    }

    public function historicalAllocations()
    {
        return $this->hasMany(EmployeeFundingAllocation::class, 'employment_id')
            ->where('status', 'historical');
    }

    public function terminatedAllocations()
    {
        return $this->hasMany(EmployeeFundingAllocation::class, 'employment_id')
            ->where('status', 'terminated');
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class, 'employment_id');
    }

    public function employmentHistories()
    {
        return $this->hasMany(EmploymentHistory::class, 'employment_id');
    }

    public function probationRecords()
    {
        return $this->hasMany(ProbationRecord::class);
    }

    public function activeProbationRecord()
    {
        return $this->hasOne(ProbationRecord::class)->where('is_active', true);
    }

    public function probationHistory()
    {
        return $this->hasMany(ProbationRecord::class)->orderBy('event_date');
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

    // — Query Scopes —

    public function scopeByEmploymentType($query, string $type)
    {
        return $query->where('employment_type', $type);
    }

    public function scopeByDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeByStatus($query, bool $status)
    {
        return $query->where('status', $status);
    }

    public function scopeActiveStatus($query)
    {
        return $query->where('status', true);
    }

    public function scopeInactiveStatus($query)
    {
        return $query->where('status', false);
    }

    // — Accessors —
    public function getFullEmploymentTypeAttribute(): string
    {
        $types = [
            'Full-time' => 'Full-time Employee',
            'Part-time' => 'Part-time Employee',
            'Contract' => 'Contract Employee',
            'Temporary' => 'Temporary Employee',
        ];

        return $types[$this->employment_type] ?? $this->employment_type;
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->start_date &&
               (! $this->end_date || $this->end_date > now());
    }

    public function getFormattedSalaryAttribute(): string
    {
        return number_format($this->pass_probation_salary, 2);
    }

    // Query scopes for better performance
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('end_date')
                ->orWhere('end_date', '>', now());
        });
    }

    public function scopeInactive($query)
    {
        return $query->whereNotNull('end_date')
            ->where('end_date', '<=', now());
    }

    public function scopeByDateRange($query, $startDate, $endDate = null)
    {
        return $query->where('start_date', '>=', $startDate)
            ->when($endDate, function ($q) use ($endDate) {
                $q->where(function ($subQuery) use ($endDate) {
                    $subQuery->whereNull('end_date')
                        ->orWhere('end_date', '<=', $endDate);
                });
            });
    }

    public function scopeWithFundingAllocations($query)
    {
        return $query->with([
            'employeeFundingAllocations' => function ($q) {
                $q->orderBy('allocation_type')
                    ->orderBy('fte', 'desc');
            },
            'employeeFundingAllocations.grantItem.grant',
        ]);
    }

    public function scopeForPayroll($query)
    {
        return $query->with([
            'employee:id,staff_id,first_name_en,last_name_en,organization,status',
            'department:id,name',
            'position:id,title,department_id',
            'employeeFundingAllocations' => function ($q) {
                $q->active()
                    ->select(['id', 'employment_id', 'allocation_type', 'fte', 'allocated_amount']);
            },
            'employeeFundingAllocations.grantItem.grant:id,name,code',
        ]);
    }

    /**
     * Get the display name for activity logs
     */
    public function getActivityLogName(): string
    {
        $employeeName = $this->employee
            ? trim(($this->employee->first_name_en ?? '').' '.($this->employee->last_name_en ?? ''))
            : '';
        $positionTitle = $this->position->title ?? '';

        if ($employeeName && $positionTitle) {
            return "{$employeeName} - {$positionTitle}";
        }

        return $employeeName ?: $positionTitle ?: "Employment #{$this->id}";
    }
}
