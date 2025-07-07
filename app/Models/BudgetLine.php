<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="BudgetLine",
 *     type="object",
 *     required={"budget_line_code"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="budget_line_code", type="string", example="BL001"),
 *     @OA\Property(property="description", type="string", example="Description"),
 *     @OA\Property(property="created_by", type="string", example="admin"),
 *     @OA\Property(property="updated_by", type="string", example="admin"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-06-25T15:38:59Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-06-25T15:38:59Z")
 * )
 */
class BudgetLine extends Model
{
    protected $fillable = [
        'budget_line_code',
        'description',
        'created_by',
        'updated_by',
    ];

    public function positionSlots()
    {
        return $this->hasMany(PositionSlot::class);
    }
}
