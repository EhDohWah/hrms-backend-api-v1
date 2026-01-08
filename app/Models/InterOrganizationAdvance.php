<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'InterOrganizationAdvance',
    title: 'Inter Organization Advance',
    description: 'Inter Organization Advance model',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'payroll_id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'from_organization', type: 'string', maxLength: 5, example: 'ORG1'),
        new OA\Property(property: 'to_organization', type: 'string', maxLength: 5, example: 'ORG2'),
        new OA\Property(property: 'via_grant_id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'amount', type: 'number', format: 'float', example: 10000.00),
        new OA\Property(property: 'advance_date', type: 'string', format: 'date', example: '2023-01-15'),
        new OA\Property(property: 'notes', type: 'string', maxLength: 255, nullable: true, example: 'Advance for project expenses'),
        new OA\Property(property: 'settlement_date', type: 'string', format: 'date', nullable: true, example: '2023-06-15'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'created_by', type: 'string', maxLength: 100, nullable: true, example: 'admin'),
        new OA\Property(property: 'updated_by', type: 'string', maxLength: 100, nullable: true, example: 'admin'),
    ]
)]
class InterOrganizationAdvance extends Model
{
    protected $fillable = [
        'payroll_id',
        'from_organization',
        'to_organization',
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

    public function fromOrganization()
    {
        return $this->belongsTo(Organization::class, 'from_organization', 'code');
    }

    public function toOrganization()
    {
        return $this->belongsTo(Organization::class, 'to_organization', 'code');
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

    public function scopeByOrganization($query, $organization, $direction = 'both')
    {
        return $query->where(function ($q) use ($organization, $direction) {
            if ($direction === 'from' || $direction === 'both') {
                $q->orWhere('from_organization', $organization);
            }
            if ($direction === 'to' || $direction === 'both') {
                $q->orWhere('to_organization', $organization);
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
            'fromOrganization:code,name',
            'toOrganization:code,name',
        ]);
    }

    public function scopeForSummary($query)
    {
        return $query->select([
            'id', 'from_organization', 'to_organization', 'via_grant_id',
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
