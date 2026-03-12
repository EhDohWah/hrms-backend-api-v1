<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonnelAction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'form_number', 'reference_number', 'employment_id',
        'current_employee_no',
        'current_department_id', 'current_position_id', 'current_site_id',
        'current_salary', 'current_employment_date',
        'effective_date', 'action_type', 'action_subtype',
        'new_department_id', 'new_position_id', 'new_site_id',
        'new_work_schedule', 'new_report_to',
        'new_pay_plan', 'new_salary', 'new_phone_ext', 'new_email',
        'comments', 'change_details', 'acknowledged_by',
        'dept_head_approved', 'dept_head_approved_date',
        'coo_approved', 'coo_approved_date',
        'hr_approved', 'hr_approved_date',
        'accountant_approved', 'accountant_approved_date',
        'implemented_at',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'current_salary' => 'decimal:2',
            'new_salary' => 'decimal:2',
            'effective_date' => 'date',
            'current_employment_date' => 'date',
            'dept_head_approved' => 'boolean',
            'dept_head_approved_date' => 'date',
            'coo_approved' => 'boolean',
            'coo_approved_date' => 'date',
            'hr_approved' => 'boolean',
            'hr_approved_date' => 'date',
            'accountant_approved' => 'boolean',
            'accountant_approved_date' => 'date',
            'implemented_at' => 'datetime',
        ];
    }

    public const ACTION_TYPES = [
        'appointment' => 'Appointment',
        'fiscal_increment' => 'Fiscal Increment',
        'title_change' => 'Title Change',
        'voluntary_separation' => 'Voluntary Separation',
        're_evaluated_pay' => 'Re-Evaluated Pay Adjustment',
        'promotion' => 'Promotion',
        'demotion' => 'Demotion',
        'end_of_contract' => 'End of Contract',
        'work_allocation' => 'Work Allocation',
        'transfer' => 'Transfer',
        'position_change' => 'Position Change',
    ];

    public const ACTION_SUBTYPES = [
        'internal_department' => 'Internal Department',
        'site_to_site' => 'From Site to Site',
    ];

    public const STATUSES = [
        'pending' => 'Pending Approval',
        'partial_approved' => 'Partially Approved',
        'fully_approved' => 'Fully Approved',
        'implemented' => 'Implemented',
    ];

    // Relationships
    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function employee(): HasOneThrough
    {
        return $this->hasOneThrough(
            Employee::class,
            Employment::class,
            'id',        // Foreign key on employments table
            'id',        // Foreign key on employees table
            'employment_id', // Local key on personnel_actions table
            'employee_id'    // Local key on employments table
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Current State Relationships
    public function currentDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'current_department_id')->withTrashed();
    }

    public function currentPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'current_position_id');
    }

    public function currentSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'current_site_id');
    }

    // New State Relationships
    public function newDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'new_department_id')->withTrashed();
    }

    public function newPosition(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'new_position_id');
    }

    public function newSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'new_site_id');
    }

    // Helper methods
    public function generateReferenceNumber(): string
    {
        return 'PA-'.date('Y').'-'.str_pad($this->id, 6, '0', STR_PAD_LEFT);
    }

    public function isFullyApproved(): bool
    {
        return $this->dept_head_approved &&
               $this->coo_approved &&
               $this->hr_approved &&
               $this->accountant_approved;
    }

    public function canBeApprovedBy(User $user): bool
    {
        return $user->can('personnel_action.update') || $user->can('personnel_action.approve');
    }

    /**
     * Auto-populate current employment data from employment record
     */
    public function populateCurrentEmploymentData(): void
    {
        $employment = $this->employment()->with(['department', 'position', 'site', 'employee'])->first();

        if ($employment) {
            $this->current_employee_no = $employment->employee->staff_id ?? null;
            $this->current_department_id = $employment->department_id;
            $this->current_position_id = $employment->position_id;
            $this->current_salary = $employment->pass_probation_salary;
            $this->current_site_id = $employment->site_id;
            $this->current_employment_date = $employment->start_date;
        }
    }

    public function getStatusAttribute(): string
    {
        if ($this->implemented_at) {
            return 'implemented';
        }

        if ($this->isFullyApproved()) {
            return 'fully_approved';
        }

        if ($this->dept_head_approved || $this->coo_approved ||
            $this->hr_approved || $this->accountant_approved) {
            return 'partial_approved';
        }

        return 'pending';
    }
}
