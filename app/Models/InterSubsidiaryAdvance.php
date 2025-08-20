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
 *     @OA\Property(property="payroll_grant_allocation_id", type="integer", format="int64", example=1),
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
        'payroll_grant_allocation_id',
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
}
