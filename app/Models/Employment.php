<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Schema(
 *   schema="Employment",
 *   required={"employee_id","employment_type","start_date","work_location_id","position_salary"},
 *
 *   @OA\Property(property="id", type="integer", format="int64", readOnly=true),
 *   @OA\Property(property="employee_id", type="integer", format="int64"),
 *   @OA\Property(property="employment_type", type="string"),
 *   @OA\Property(property="start_date", type="string", format="date"),
 *   @OA\Property(property="end_date", type="string", format="date", nullable=true),
 *   @OA\Property(property="probation_pass_date", type="string", format="date", nullable=true, description="Typically 3 months after start_date - marks the end of probation period"),
 *   @OA\Property(property="pay_method", type="string", nullable=true),
 *   @OA\Property(property="section_department", type="string", nullable=true),
 *   @OA\Property(property="work_location_id", type="integer", format="int64", nullable=true),
 *   @OA\Property(property="position_salary", type="number", format="float"),
 *   @OA\Property(property="probation_salary", type="number", format="float", nullable=true),

 *   @OA\Property(property="health_welfare", type="boolean", default=false),
 *   @OA\Property(property="health_welfare_percentage", type="number", format="float", nullable=true, description="Health & Welfare percentage (0-100)"),
 *   @OA\Property(property="pvd", type="boolean", default=false),
 *   @OA\Property(property="pvd_percentage", type="number", format="float", nullable=true, description="PVD percentage (0-100)"),
 *   @OA\Property(property="saving_fund", type="boolean", default=false),
 *   @OA\Property(property="saving_fund_percentage", type="number", format="float", nullable=true, description="Saving Fund percentage (0-100)"),
 *   @OA\Property(property="created_by", type="string", nullable=true),
 *   @OA\Property(property="updated_by", type="string", nullable=true),
 *   @OA\Property(property="created_at", type="string", format="date-time", readOnly=true),
 *   @OA\Property(property="updated_at", type="string", format="date-time", readOnly=true)
 * )
 */
class Employment extends Model
{
    use HasFactory;

    /** Mass-assignable attributes */
    protected $fillable = [
        'employee_id',
        'employment_type',
        'start_date',
        'end_date',
        'probation_pass_date',
        'pay_method',
        'department_id',
        'position_id',
        'section_department',
        'work_location_id',
        'position_salary',
        'probation_salary',
        'health_welfare',
        'health_welfare_percentage',
        'pvd',
        'pvd_percentage',
        'saving_fund',
        'saving_fund_percentage',
        'created_by',
        'updated_by',
    ];

    /** Attribute casting for type safety */
    protected $casts = [
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'probation_pass_date' => 'date:Y-m-d',
        'position_salary' => 'decimal:2',
        'probation_salary' => 'decimal:2',
        'health_welfare' => 'boolean',
        'health_welfare_percentage' => 'decimal:2',
        'pvd' => 'boolean',
        'pvd_percentage' => 'decimal:2',
        'saving_fund' => 'boolean',
        'saving_fund_percentage' => 'decimal:2',
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
            'probation_end_date' => $this->probation_pass_date,
            'pay_method' => $this->pay_method,
            'department_id' => $this->department_id,
            'position_id' => $this->position_id,
            'work_location_id' => $this->work_location_id,
            'position_salary' => $this->position_salary,
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
            'position_salary' => 'Salary adjustment',
            'department_id' => 'Department change',
            'position_id' => 'Position change',
            'work_location_id' => 'Location change',
            'employment_type' => 'Employment type change',
            'probation_pass_date' => 'Probation period update',
            'pay_method' => 'Payment method change',
            'health_welfare' => 'Health welfare benefit change',
            'pvd' => 'PVD benefit change',
            'saving_fund' => 'Saving fund benefit change',
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
            'probation_end_date' => $this->probation_pass_date,
            'pay_method' => $this->pay_method,
            'department_id' => $this->department_id,
            'position_id' => $this->position_id,
            'work_location_id' => $this->work_location_id,
            'position_salary' => $this->position_salary,
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

    // — Relationships —
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function employeeFundingAllocations()
    {
        return $this->hasMany(EmployeeFundingAllocation::class, 'employment_id');
    }

    public function payrolls()
    {
        return $this->hasMany(Payroll::class, 'employment_id');
    }

    public function employmentHistories()
    {
        return $this->hasMany(EmploymentHistory::class, 'employment_id');
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

    // — Query Scopes —

    public function scopeByEmploymentType($query, string $type)
    {
        return $query->where('employment_type', $type);
    }

    public function scopeByDepartment($query, int $departmentId)
    {
        return $query->where('department_id', $departmentId);
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
        return number_format($this->position_salary, 2);
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
                $q->with(['positionSlot.grantItem.grant', 'orgFunded.grant'])
                    ->orderBy('allocation_type')
                    ->orderBy('fte', 'desc');
            },
        ]);
    }

    public function scopeForPayroll($query)
    {
        return $query->with([
            'employee:id,staff_id,first_name_en,last_name_en,subsidiary,status',
            'department:id,name',
            'position:id,title,department_id',
            'employeeFundingAllocations' => function ($q) {
                $q->active()
                    ->with(['positionSlot.grantItem.grant:id,name,code', 'orgFunded.grant:id,name,code'])
                    ->select(['id', 'employment_id', 'allocation_type', 'fte', 'allocated_amount']);
            },
        ]);
    }
}
