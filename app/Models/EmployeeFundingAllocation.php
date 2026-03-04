<?php

namespace App\Models;

use App\Enums\FundingAllocationStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'EmployeeFundingAllocation',
    title: 'Employee Funding Allocation',
    description: 'Employee Funding Allocation model',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'employee_id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'employment_id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'grant_item_id', type: 'integer', format: 'int64', nullable: true, example: 1, description: 'Direct link to grant_items for grant allocations'),
        new OA\Property(property: 'fte', type: 'number', format: 'float', example: 0.5, description: 'Full-Time Equivalent - actual funding allocation percentage'),
        new OA\Property(property: 'allocated_amount', type: 'number', format: 'float', nullable: true, example: 10000),
        new OA\Property(property: 'status', type: 'string', example: 'active', description: 'Allocation status: active, inactive, closed'),
        new OA\Property(property: 'created_by', type: 'string', nullable: true, example: 'admin'),
        new OA\Property(property: 'updated_by', type: 'string', nullable: true, example: 'admin'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2023-01-01T00:00:00.000000Z'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2023-01-01T00:00:00.000000Z'),
    ]
)]
class EmployeeFundingAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'employment_id',
        'grant_item_id',
        'fte',
        'allocated_amount',
        'salary_type',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'fte' => 'decimal:4',
        'allocated_amount' => 'decimal:2',
        'status' => \App\Enums\FundingAllocationStatus::class,
    ];

    // Relationships
    public function employee()
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function employment()
    {
        return $this->belongsTo(Employment::class);
    }

    public function grantItem()
    {
        return $this->belongsTo(GrantItem::class);
    }

    /**
     * Get the history records for this allocation
     */
    public function history()
    {
        return $this->hasMany(EmployeeFundingAllocationHistory::class);
    }

    /**
     * Get payroll snapshots that include this allocation
     */
    public function payrollAllocations()
    {
        return $this->hasMany(PayrollGrantAllocation::class);
    }

    /**
     * Get payrolls associated with this allocation
     */
    public function payrolls()
    {
        return $this->hasManyThrough(
            Payroll::class,
            PayrollGrantAllocation::class,
            'employee_funding_allocation_id', // FK on payroll_grant_allocations
            'id', // FK on payrolls
            'id', // Local key on employee_funding_allocations
            'payroll_id' // Local key on payroll_grant_allocations
        );
    }

    // Query scopes for better performance
    public function scopeActive($query)
    {
        return $query->where('status', FundingAllocationStatus::Active);
    }

    public function scopeInactive($query)
    {
        return $query->where('status', FundingAllocationStatus::Inactive);
    }

    public function scopeClosed($query)
    {
        return $query->where('status', FundingAllocationStatus::Closed);
    }

    public function scopeByEffortRange($query, $minEffort, $maxEffort = null)
    {
        $query->where('fte', '>=', $minEffort);

        if ($maxEffort !== null) {
            $query->where('fte', '<=', $maxEffort);
        }

        return $query;
    }

    public function scopeWithFullDetails($query)
    {
        return $query->with([
            'employee:id,staff_id,first_name_en,last_name_en,organization',
            'employment:id,employee_id,start_date,end_probation_date,pass_probation_salary,department_id,position_id',
            'grantItem:id,grant_id,grant_position,grant_salary,budgetline_code,grant_position_number',
            'grantItem.grant:id,name,code',
            'employment.department:id,name',
            'employment.position:id,title',
        ]);
    }

    public function scopeForPayrollCalculation($query)
    {
        return $query->active()
            ->select([
                'id', 'employee_id', 'employment_id',
                'fte', 'allocated_amount', 'grant_item_id',
            ])
            ->with([
                'grantItem:id,grant_id,grant_position,budgetline_code',
                'grantItem.grant:id,name,code',
            ]);
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === FundingAllocationStatus::Active;
    }

    public function isInactive(): bool
    {
        return $this->status === FundingAllocationStatus::Inactive;
    }

    public function isClosed(): bool
    {
        return $this->status === FundingAllocationStatus::Closed;
    }

    // Accessors
    public function getStatusLabelAttribute(): string
    {
        return $this->status?->label() ?? 'Unknown';
    }

    public function getSalaryTypeLabelAttribute(): string
    {
        return match ($this->salary_type) {
            'probation_salary' => 'Probation Salary',
            'pass_probation_salary' => 'Pass Probation Salary',
            default => 'Unknown'
        };
    }

    /**
     * Convenience accessors to reach the grant through grant_item.
     */
    public function getGrantAttribute(): ?Grant
    {
        return $this->grantItem?->grant;
    }

    public function getGrantCodeAttribute(): ?string
    {
        return $this->grantItem?->grant?->code;
    }

    public function getGrantNameAttribute(): ?string
    {
        return $this->grantItem?->grant?->name;
    }
}
