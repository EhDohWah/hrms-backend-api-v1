<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="GrantPosition",
 *     type="object",
 *     title="Grant Position",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="grant_id", type="integer", example=1),
 *     @OA\Property(property="budget_line", type="string", example="BL-123"),
 *     @OA\Property(property="grant_salary", type="number", format="float", example=75000),
 *     @OA\Property(property="grant_benefit", type="number", format="float", example=15000),
 *     @OA\Property(property="grant_level_of_effort", type="number", format="float", example=0.75),
 *     @OA\Property(property="grant_position_number", type="string", example="POS-001"),
 *     @OA\Property(property="grant_monthly_cost", type="number", format="float", example=7500),
 *     @OA\Property(property="grant_total_person_cost", type="number", format="float", example=90000),
 *     @OA\Property(property="grant_total_amount", type="number", format="float", example=90000),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="created_by", type="string", nullable=true),
 *     @OA\Property(property="updated_by", type="string", nullable=true)
 * )
 */
class GrantPosition extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'grant_id',
        'budget_line',
        'grant_salary',
        'grant_benefit',
        'grant_level_of_effort',
        'grant_position_number',
        'grant_monthly_cost',
        'grant_total_person_cost',
        'grant_total_amount',
        'created_by',
        'updated_by'
    ];

    /**
     * Get the grant that owns the position.
     */
    public function grant()
    {
        return $this->belongsTo(Grant::class);
    }
}
