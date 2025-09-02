<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="InterSubsidiaryAdvance",
 *     title="Inter Subsidiary Advance",
 *     description="Inter Subsidiary Advance model",
 *
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="payroll_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="from_subsidiary", type="string", maxLength=5, example="SUB1"),
 *     @OA\Property(property="to_subsidiary", type="string", maxLength=5, example="SUB2"),
 *     @OA\Property(property="via_grant_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="amount", type="number", format="float", example=10000.00),
 *     @OA\Property(property="advance_date", type="string", format="date", example="2023-01-15"),
 *     @OA\Property(property="notes", type="string", maxLength=255, nullable=true, example="Advance for project expenses"),
 *     @OA\Property(property="settlement_date", type="string", format="date", nullable=true, example="2023-06-15"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="created_by", type="string", maxLength=100, nullable=true, example="admin"),
 *     @OA\Property(property="updated_by", type="string", maxLength=100, nullable=true, example="admin")
 * )
 */
class InterSubsidiaryAdvance extends Model
{
    protected $fillable = [
        'payroll_id',
        'from_subsidiary',
        'to_subsidiary',
        'via_grant_id',
        'amount',
        'advance_date',
        'notes',
        'settlement_date',
        'created_by',
        'updated_by',
    ];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }

    public function viaGrant()
    {
        return $this->belongsTo(Grant::class, 'via_grant_id');
    }

    public function fromSubsidiary()
    {
        return $this->belongsTo(Subsidiary::class, 'from_subsidiary', 'code');
    }

    public function toSubsidiary()
    {
        return $this->belongsTo(Subsidiary::class, 'to_subsidiary', 'code');
    }

    // Query scopes for better performance
    public function scopeUnsettled($query)
    {
        return $query->whereNull('settlement_date');
    }

    public function scopeSettled($query)
    {
        return $query->whereNotNull('settlement_date');
    }

    public function scopeBySubsidiary($query, $subsidiary, $direction = 'both')
    {
        return $query->where(function ($q) use ($subsidiary, $direction) {
            if ($direction === 'from' || $direction === 'both') {
                $q->orWhere('from_subsidiary', $subsidiary);
            }
            if ($direction === 'to' || $direction === 'both') {
                $q->orWhere('to_subsidiary', $subsidiary);
            }
        });
    }

    public function scopeByDateRange($query, $startDate, $endDate = null)
    {
        $query->where('advance_date', '>=', $startDate);

        if ($endDate) {
            $query->where('advance_date', '<=', $endDate);
        }

        return $query;
    }

    public function scopeByAmountRange($query, $minAmount, $maxAmount = null)
    {
        $query->where('amount', '>=', $minAmount);

        if ($maxAmount !== null) {
            $query->where('amount', '<=', $maxAmount);
        }

        return $query;
    }

    public function scopeWithFullDetails($query)
    {
        return $query->with([
            'viaGrant:id,name,code',
            'fromSubsidiary:code,name',
            'toSubsidiary:code,name',
        ]);
    }

    public function scopeForSummary($query)
    {
        return $query->select([
            'id', 'from_subsidiary', 'to_subsidiary', 'via_grant_id',
            'amount', 'advance_date', 'settlement_date',
        ])->with(['viaGrant:id,name,code']);
    }

    // Helper methods
    public function getIsSettledAttribute(): bool
    {
        return ! is_null($this->settlement_date);
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format($this->amount, 2);
    }
}
