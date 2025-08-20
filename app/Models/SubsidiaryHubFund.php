<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="SubsidiaryHubFund",
 *     title="Subsidiary Hub Fund",
 *     description="Subsidiary Hub Fund model",
 *
 *     @OA\Property(property="id", type="integer", format="int64", description="ID of the subsidiary hub fund"),
 *     @OA\Property(property="subsidiary", type="string", maxLength=5, description="Subsidiary code"),
 *     @OA\Property(property="hub_grant_id", type="integer", format="int64", description="ID of the hub grant"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp"),
 *     @OA\Property(property="created_by", type="string", maxLength=100, nullable=true, description="User who created the record"),
 *     @OA\Property(property="updated_by", type="string", maxLength=100, nullable=true, description="User who last updated the record")
 * )
 */
class SubsidiaryHubFund extends Model
{
    /**
     * @var array
     *
     * @OA\Property(
     *     property="fillable",
     *     type="array",
     *
     *     @OA\Items(type="string"),
     *     description="Mass assignable attributes"
     * )
     */
    protected $fillable = [
        'subsidiary',
        'hub_grant_id',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the hub grant that this fund belongs to
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function hubGrant()
    {
        return $this->belongsTo(Grant::class, 'hub_grant_id');
    }
}
