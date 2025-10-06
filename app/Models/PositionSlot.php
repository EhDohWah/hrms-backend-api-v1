<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="PositionSlot",
 *     type="object",
 *     title="Position Slot",
 *     required={"grant_item_id", "slot_number"},
 *
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="grant_item_id", type="integer", example=1, description="Foreign key to grant items"),
 *     @OA\Property(property="slot_number", type="integer", example=1, description="Slot number, e.g., 1, 2, 3..."),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="created_by", type="string", example="admin", nullable=true),
 *     @OA\Property(property="updated_by", type="string", example="admin", nullable=true)
 * )
 */
class PositionSlot extends Model
{
    protected $fillable = [
        'grant_item_id',
        'slot_number',
        'created_by',
        'updated_by',
    ];

    public function grantItem()
    {
        return $this->belongsTo(GrantItem::class);
    }

    // Budget line code moved to GrantItem model for better data normalization

    public function employeeGrantAllocations()
    {
        return $this->hasMany(EmployeeGrantAllocation::class);
    }
}
