<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Grant;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="GrantItem",
 *     type="object",
 *     title="Grant Item",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="grant_id", type="integer", example=1),
 *     @OA\Property(property="bg_line", type="string", example="BL-123", nullable=true),
 *     @OA\Property(property="grant_position", type="string", example="Project Manager", nullable=true),
 *     @OA\Property(property="grant_salary", type="number", format="float", example=75000, nullable=true),
 *     @OA\Property(property="grant_benefit", type="number", format="float", example=15000, nullable=true),
 *     @OA\Property(property="grant_level_of_effort", type="string", example="0.75", nullable=true),
 *     @OA\Property(property="grant_position_number", type="string", example="POS-001", nullable=true),
 *     @OA\Property(property="grant_cost_by_monthly", type="string", example="7500", nullable=true),
 *     @OA\Property(property="grant_total_cost_by_person", type="string", example="90000", nullable=true),
 *     @OA\Property(property="grant_benefit_fte", type="number", format="float", example=0.75, nullable=true),
 *     @OA\Property(property="position_id", type="string", example="P123", nullable=true),
 *     @OA\Property(property="grant_total_amount", type="number", format="float", example=90000, nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="created_by", type="string", nullable=true),
 *     @OA\Property(property="updated_by", type="string", nullable=true)
 * )
 */
class GrantItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'grant_id', 'bg_line', 'grant_position', 'grant_salary', 'grant_benefit',
        'grant_level_of_effort', 'grant_position_number', 'grant_cost_by_monthly',
        'grant_total_cost_by_person', 'grant_benefit_fte', 'position_id', 'grant_total_amount',
        'created_by', 'updated_by'
    ];

    public function grant()
    {
        return $this->belongsTo(Grant::class);
    }
}
