<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'AllocationChangeLog',
    title: 'Allocation Change Log',
    description: 'Allocation change log model for tracking funding allocation changes',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'employee_id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'employment_id', type: 'integer', format: 'int64', nullable: true, example: 1),
        new OA\Property(property: 'employee_funding_allocation_id', type: 'integer', format: 'int64', nullable: true, example: 1),
        new OA\Property(property: 'change_type', type: 'string', example: 'updated', enum: ['created', 'updated', 'deleted', 'transferred']),
        new OA\Property(property: 'action_description', type: 'string', example: 'Level of effort changed from 50% to 60%'),
        new OA\Property(property: 'old_values', type: 'object', nullable: true),
        new OA\Property(property: 'new_values', type: 'object', nullable: true),
        new OA\Property(property: 'allocation_summary', type: 'object', nullable: true),
        new OA\Property(property: 'financial_impact', type: 'number', format: 'float', nullable: true, example: 5000.00),
        new OA\Property(property: 'impact_type', type: 'string', nullable: true, example: 'increase', enum: ['increase', 'decrease', 'neutral']),
        new OA\Property(property: 'approval_status', type: 'string', example: 'approved', enum: ['pending', 'approved', 'rejected']),
        new OA\Property(property: 'reason_category', type: 'string', nullable: true, example: 'promotion'),
        new OA\Property(property: 'business_justification', type: 'string', nullable: true, example: 'Employee promoted to senior role'),
        new OA\Property(property: 'changed_by', type: 'string', example: 'john.doe'),
        new OA\Property(property: 'change_source', type: 'string', example: 'manual', enum: ['manual', 'system', 'import', 'api']),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
class AllocationChangeLog extends Model
{
    protected $fillable = [
        'employee_id',
        'employment_id',
        'employee_funding_allocation_id',
        'change_type',
        'action_description',
        'old_values',
        'new_values',
        'allocation_summary',
        'financial_impact',
        'impact_type',
        'approval_status',
        'approved_by',
        'approved_at',
        'approval_notes',
        'reason_category',
        'business_justification',
        'effective_date',
        'changed_by',
        'change_source',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'allocation_summary' => 'array',
        'metadata' => 'array',
        'financial_impact' => 'decimal:2',
        'approved_at' => 'datetime',
        'effective_date' => 'date',
    ];

    // Constants for change types
    public const CHANGE_TYPE_CREATED = 'created';

    public const CHANGE_TYPE_UPDATED = 'updated';

    public const CHANGE_TYPE_DELETED = 'deleted';

    public const CHANGE_TYPE_TRANSFERRED = 'transferred';

    // Constants for impact types
    public const IMPACT_TYPE_INCREASE = 'increase';

    public const IMPACT_TYPE_DECREASE = 'decrease';

    public const IMPACT_TYPE_NEUTRAL = 'neutral';

    // Constants for approval status
    public const APPROVAL_STATUS_PENDING = 'pending';

    public const APPROVAL_STATUS_APPROVED = 'approved';

    public const APPROVAL_STATUS_REJECTED = 'rejected';

    // Constants for change sources
    public const CHANGE_SOURCE_MANUAL = 'manual';

    public const CHANGE_SOURCE_SYSTEM = 'system';

    public const CHANGE_SOURCE_IMPORT = 'import';

    public const CHANGE_SOURCE_API = 'api';

    // Relationships
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function employment(): BelongsTo
    {
        return $this->belongsTo(Employment::class);
    }

    public function employeeFundingAllocation(): BelongsTo
    {
        return $this->belongsTo(EmployeeFundingAllocation::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeByEmployee($query, int $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeByEmployment($query, int $employmentId)
    {
        return $query->where('employment_id', $employmentId);
    }

    public function scopeByChangeType($query, string $changeType)
    {
        return $query->where('change_type', $changeType);
    }

    public function scopeByApprovalStatus($query, string $status)
    {
        return $query->where('approval_status', $status);
    }

    public function scopeByDateRange($query, string $startDate, string $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('approval_status', self::APPROVAL_STATUS_PENDING);
    }

    public function scopeWithFinancialImpact($query)
    {
        return $query->whereNotNull('financial_impact')->where('financial_impact', '!=', 0);
    }

    // Static factory methods for creating logs
    public static function logAllocationCreated(
        EmployeeFundingAllocation $allocation,
        ?string $reason = null,
        array $metadata = []
    ): self {
        return self::create([
            'employee_id' => $allocation->employee_id,
            'employment_id' => $allocation->employment_id,
            'employee_funding_allocation_id' => $allocation->id,
            'change_type' => self::CHANGE_TYPE_CREATED,
            'action_description' => self::generateCreatedDescription($allocation),
            'new_values' => $allocation->toArray(),
            'allocation_summary' => self::getAllocationSummary($allocation->employee_id),
            'financial_impact' => $allocation->allocated_amount,
            'impact_type' => $allocation->allocated_amount ? self::IMPACT_TYPE_INCREASE : self::IMPACT_TYPE_NEUTRAL,
            'approval_status' => self::APPROVAL_STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'business_justification' => $reason,
            'changed_by' => Auth::user()->name ?? 'system',
            'change_source' => self::CHANGE_SOURCE_MANUAL,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
        ]);
    }

    public static function logAllocationUpdated(
        EmployeeFundingAllocation $allocation,
        array $oldValues,
        ?string $reason = null,
        array $metadata = []
    ): self {
        $changes = array_diff_assoc($allocation->toArray(), $oldValues);
        $financialImpact = self::calculateFinancialImpact($oldValues, $allocation->toArray());

        return self::create([
            'employee_id' => $allocation->employee_id,
            'employment_id' => $allocation->employment_id,
            'employee_funding_allocation_id' => $allocation->id,
            'change_type' => self::CHANGE_TYPE_UPDATED,
            'action_description' => self::generateUpdatedDescription($changes),
            'old_values' => $oldValues,
            'new_values' => $allocation->toArray(),
            'allocation_summary' => self::getAllocationSummary($allocation->employee_id),
            'financial_impact' => $financialImpact['amount'],
            'impact_type' => $financialImpact['type'],
            'approval_status' => self::APPROVAL_STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'business_justification' => $reason,
            'changed_by' => Auth::user()->name ?? 'system',
            'change_source' => self::CHANGE_SOURCE_MANUAL,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
        ]);
    }

    public static function logAllocationDeleted(
        EmployeeFundingAllocation $allocation,
        ?string $reason = null,
        array $metadata = []
    ): self {
        return self::create([
            'employee_id' => $allocation->employee_id,
            'employment_id' => $allocation->employment_id,
            'employee_funding_allocation_id' => null, // Allocation will be deleted
            'change_type' => self::CHANGE_TYPE_DELETED,
            'action_description' => self::generateDeletedDescription($allocation),
            'old_values' => $allocation->toArray(),
            'allocation_summary' => self::getAllocationSummary($allocation->employee_id),
            'financial_impact' => $allocation->allocated_amount ? -$allocation->allocated_amount : null,
            'impact_type' => $allocation->allocated_amount ? self::IMPACT_TYPE_DECREASE : self::IMPACT_TYPE_NEUTRAL,
            'approval_status' => self::APPROVAL_STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'business_justification' => $reason,
            'changed_by' => Auth::user()->name ?? 'system',
            'change_source' => self::CHANGE_SOURCE_MANUAL,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
        ]);
    }

    // Helper methods
    private static function generateCreatedDescription(EmployeeFundingAllocation $allocation): string
    {
        $effort = round($allocation->fte * 100, 1);

        return "New grant allocation created with {$effort}% level of effort";
    }

    private static function generateUpdatedDescription(array $changes): string
    {
        $descriptions = [];

        if (isset($changes['fte'])) {
            $oldEffort = round(($changes['fte'] ?? 0) * 100, 1);
            $newEffort = round($changes['fte'] * 100, 1);
            $descriptions[] = "Level of effort changed from {$oldEffort}% to {$newEffort}%";
        }

        if (isset($changes['allocated_amount'])) {
            $oldAmount = number_format($changes['allocated_amount'] ?? 0, 2);
            $newAmount = number_format($changes['allocated_amount'], 2);
            $descriptions[] = "Allocated amount changed from ฿{$oldAmount} to ฿{$newAmount}";
        }

        return implode(', ', $descriptions) ?: 'Allocation updated';
    }

    private static function generateDeletedDescription(EmployeeFundingAllocation $allocation): string
    {
        $effort = round($allocation->fte * 100, 1);

        return "Grant allocation with {$effort}% level of effort removed";
    }

    private static function calculateFinancialImpact(array $oldValues, array $newValues): array
    {
        $oldAmount = $oldValues['allocated_amount'] ?? 0;
        $newAmount = $newValues['allocated_amount'] ?? 0;
        $difference = $newAmount - $oldAmount;

        if ($difference > 0) {
            return ['amount' => $difference, 'type' => self::IMPACT_TYPE_INCREASE];
        } elseif ($difference < 0) {
            return ['amount' => abs($difference), 'type' => self::IMPACT_TYPE_DECREASE];
        } else {
            return ['amount' => 0, 'type' => self::IMPACT_TYPE_NEUTRAL];
        }
    }

    private static function getAllocationSummary(int $employeeId): array
    {
        $allocations = EmployeeFundingAllocation::where('employee_id', $employeeId)
            ->where(function ($query) {
                $query->whereNull('end_date')
                    ->orWhere('end_date', '>', now());
            })
            ->get();

        return [
            'total_allocations' => $allocations->count(),
            'total_effort' => $allocations->sum('fte'),
            'total_amount' => $allocations->sum('allocated_amount'),
            'snapshot_date' => now()->toISOString(),
        ];
    }

    // Accessor for formatted financial impact
    public function getFormattedFinancialImpactAttribute(): ?string
    {
        if (! $this->financial_impact) {
            return null;
        }

        $symbol = $this->impact_type === self::IMPACT_TYPE_INCREASE ? '+' :
                 ($this->impact_type === self::IMPACT_TYPE_DECREASE ? '-' : '');

        return $symbol.'฿'.number_format(abs($this->financial_impact), 2);
    }

    // Accessor for human-readable change summary
    public function getChangeSummaryAttribute(): string
    {
        $summary = $this->action_description;

        if ($this->financial_impact) {
            $summary .= ' ('.$this->formatted_financial_impact.')';
        }

        return $summary;
    }

    // Check if change requires approval
    public function requiresApproval(): bool
    {
        // Define business rules for when changes require approval
        $highImpactThreshold = 50000; // ฿50,000

        return $this->financial_impact &&
               abs($this->financial_impact) > $highImpactThreshold;
    }

    // Approve the change
    public function approve(?string $notes = null): bool
    {
        return $this->update([
            'approval_status' => self::APPROVAL_STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);
    }

    // Reject the change
    public function reject(?string $notes = null): bool
    {
        return $this->update([
            'approval_status' => self::APPROVAL_STATUS_REJECTED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);
    }
}
