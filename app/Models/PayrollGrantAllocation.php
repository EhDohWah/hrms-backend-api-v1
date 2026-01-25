<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PayrollGrantAllocation Model
 *
 * Represents a snapshot of a funding allocation at the time a payroll was created.
 * Each payroll can have multiple grant allocations (e.g., 60% Grant A + 40% Grant B).
 *
 * Used for Budget History reporting to show which grants paid for each month's salary.
 */
class PayrollGrantAllocation extends Model
{
    protected $table = 'payroll_grant_allocations';

    protected $fillable = [
        'payroll_id',
        'employee_funding_allocation_id',
        'grant_item_id',
        'grant_code',
        'grant_name',
        'budget_line_code',
        'grant_position',
        'fte',
        'allocated_amount',
        'salary_type',
    ];

    protected $casts = [
        'fte' => 'decimal:4',
        'allocated_amount' => 'decimal:2',
    ];

    // ============================================
    // RELATIONSHIPS
    // ============================================

    /**
     * Get the payroll this allocation belongs to
     */
    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    /**
     * Get the original funding allocation (may be null if deleted)
     */
    public function fundingAllocation(): BelongsTo
    {
        return $this->belongsTo(EmployeeFundingAllocation::class, 'employee_funding_allocation_id');
    }

    /**
     * Get the grant item (may be null if deleted)
     */
    public function grantItem(): BelongsTo
    {
        return $this->belongsTo(GrantItem::class);
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
     * Create allocation snapshot from an active EmployeeFundingAllocation
     *
     * @param  Payroll  $payroll  The payroll being created
     * @param  EmployeeFundingAllocation  $allocation  The active allocation to snapshot
     */
    public static function createFromAllocation(Payroll $payroll, EmployeeFundingAllocation $allocation): self
    {
        // Get grant details for snapshot
        $grantItem = $allocation->grantItem;
        $grant = $grantItem?->grant;

        return self::create([
            'payroll_id' => $payroll->id,
            'employee_funding_allocation_id' => $allocation->id,
            'grant_item_id' => $allocation->grant_item_id,
            'grant_code' => $grant?->code,
            'grant_name' => $grant?->name,
            'budget_line_code' => $grantItem?->budgetline_code,
            'grant_position' => $grantItem?->grant_position,
            'fte' => $allocation->fte,
            'allocated_amount' => $allocation->allocated_amount,
            'salary_type' => $allocation->salary_type,
        ]);
    }
}
