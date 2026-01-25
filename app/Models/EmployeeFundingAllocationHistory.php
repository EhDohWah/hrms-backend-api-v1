<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * EmployeeFundingAllocationHistory Model
 *
 * Tracks ALL changes to funding allocations for audit trail and reporting.
 * Creates a historical record whenever an allocation is created, updated, or ended.
 *
 * Use cases:
 * - Audit trail: "Who changed this allocation and when?"
 * - Budget reporting: "What was the funding structure in Q1 2025?"
 * - Compliance: "Show me all allocation changes for employee X"
 */
class EmployeeFundingAllocationHistory extends Model
{
    protected $table = 'employee_funding_allocation_history';

    protected $fillable = [
        'employee_funding_allocation_id',
        'employee_id',
        'employment_id',
        'grant_item_id',
        'grant_code',
        'grant_name',
        'budget_line_code',
        'grant_position',
        'fte',
        'allocated_amount',
        'salary_type',
        'allocation_status',
        'effective_date',
        'end_date',
        'change_type',
        'change_reason',
        'change_details',
        'changed_by',
        'changed_by_name',
    ];

    protected $casts = [
        'fte' => 'decimal:4',
        'allocated_amount' => 'decimal:2',
        'effective_date' => 'date',
        'end_date' => 'date',
        'change_details' => 'array',
    ];

    // Change type constants for consistency
    public const CHANGE_CREATED = 'created';

    public const CHANGE_UPDATED = 'updated';

    public const CHANGE_ENDED = 'ended';

    public const CHANGE_PROBATION_COMPLETED = 'probation_completed';

    public const CHANGE_TERMINATED = 'terminated';

    // ============================================
    // RELATIONSHIPS
    // ============================================

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(EmployeeFundingAllocation::class, 'employee_funding_allocation_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function grantItem(): BelongsTo
    {
        return $this->belongsTo(GrantItem::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    // ============================================
    // SCOPES
    // ============================================

    /**
     * Get history for a specific date range
     */
    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            // Records where effective_date is within range
            $q->whereBetween('effective_date', [$startDate, $endDate])
                // OR records that were active during the range
                ->orWhere(function ($q2) use ($startDate, $endDate) {
                    $q2->where('effective_date', '<=', $endDate)
                        ->where(function ($q3) use ($startDate) {
                            $q3->whereNull('end_date')
                                ->orWhere('end_date', '>=', $startDate);
                        });
                });
        });
    }

    /**
     * Get history for a specific employee
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Get history for a specific grant
     */
    public function scopeForGrant($query, $grantItemId)
    {
        return $query->where('grant_item_id', $grantItemId);
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    /**
     * Get FTE as percentage (0-100)
     */
    public function getFtePercentageAttribute(): float
    {
        return $this->fte * 100;
    }

    /**
     * Create a history record from an allocation
     *
     * @param  EmployeeFundingAllocation  $allocation  The allocation
     * @param  string  $changeType  One of the CHANGE_* constants
     * @param  string|null  $reason  Human-readable reason for the change
     * @param  array|null  $details  Optional details about what changed
     */
    public static function recordChange(
        EmployeeFundingAllocation $allocation,
        string $changeType,
        ?string $reason = null,
        ?array $details = null
    ): self {
        $grantItem = $allocation->grantItem;
        $grant = $grantItem?->grant;
        $user = Auth::user();

        return self::create([
            'employee_funding_allocation_id' => $allocation->id,
            'employee_id' => $allocation->employee_id,
            'employment_id' => $allocation->employment_id,
            'grant_item_id' => $allocation->grant_item_id,
            'grant_code' => $grant?->code,
            'grant_name' => $grant?->name,
            'budget_line_code' => $grantItem?->budgetline_code,
            'grant_position' => $grantItem?->grant_position,
            'fte' => $allocation->fte,
            'allocated_amount' => $allocation->allocated_amount,
            'salary_type' => $allocation->salary_type,
            'allocation_status' => $allocation->status,
            'effective_date' => $allocation->start_date,
            'end_date' => $allocation->end_date,
            'change_type' => $changeType,
            'change_reason' => $reason,
            'change_details' => $details,
            'changed_by' => $user?->id,
            'changed_by_name' => $user?->name ?? 'system',
        ]);
    }

    /**
     * Record when an allocation is created
     */
    public static function recordCreation(EmployeeFundingAllocation $allocation, ?string $reason = null): self
    {
        return self::recordChange($allocation, self::CHANGE_CREATED, $reason ?? 'Allocation created');
    }

    /**
     * Record when an allocation is updated
     */
    public static function recordUpdate(
        EmployeeFundingAllocation $allocation,
        array $oldValues,
        ?string $reason = null
    ): self {
        $details = [
            'old_values' => $oldValues,
            'new_values' => $allocation->only(['fte', 'allocated_amount', 'grant_item_id', 'salary_type']),
        ];

        return self::recordChange($allocation, self::CHANGE_UPDATED, $reason ?? 'Allocation updated', $details);
    }

    /**
     * Record when an allocation is ended (replaced)
     */
    public static function recordEnded(EmployeeFundingAllocation $allocation, ?string $reason = null): self
    {
        return self::recordChange($allocation, self::CHANGE_ENDED, $reason ?? 'Allocation ended/replaced');
    }

    /**
     * Record probation completion transition
     */
    public static function recordProbationCompleted(EmployeeFundingAllocation $allocation): self
    {
        return self::recordChange(
            $allocation,
            self::CHANGE_PROBATION_COMPLETED,
            'Probation completed - transitioned to post-probation salary'
        );
    }

    /**
     * Record allocation termination
     */
    public static function recordTermination(EmployeeFundingAllocation $allocation, ?string $reason = null): self
    {
        return self::recordChange($allocation, self::CHANGE_TERMINATED, $reason ?? 'Allocation terminated');
    }
}
