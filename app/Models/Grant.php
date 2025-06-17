<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\GrantItem;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Grant",
 *     title="Grant",
 *     description="Grant model",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="name", type="string", example="Research Grant 2023"),
 *     @OA\Property(property="code", type="string", example="RG-2023-001"),
 *     @OA\Property(property="description", type="string", nullable=true, example="Funding for research activities"),
 *     @OA\Property(property="end_date", type="string", format="date", nullable=true, example="2023-12-31"),
 *     @OA\Property(property="created_by", type="string", nullable=true, example="admin"),
 *     @OA\Property(property="updated_by", type="string", nullable=true, example="admin"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="grant_items",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/GrantItem")
 *     )
 * )
 */
class Grant extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'subsidiary',
        'description',
        'end_date',
        'created_by',
        'updated_by'
    ];

    public function grantItems()
    {
        return $this->hasMany(GrantItem::class, 'grant_id');
    }

    public function subsidiaryHubFunds()
    {
        return $this->hasMany(SubsidiaryHubFund::class, 'hub_grant_id');
    }

    public function interSubsidiaryAdvances()
    {
        return $this->hasMany(InterSubsidiaryAdvance::class, 'via_grant_id');
    }

}
