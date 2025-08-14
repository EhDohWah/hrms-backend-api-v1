<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="DepartmentPosition",
 *     title="Department Position",
 *     description="Department Position model",
 *     @OA\Property(property="id", type="integer", format="int64", description="Department Position ID"),
 *     @OA\Property(property="department", type="string", description="Department name"),
 *     @OA\Property(property="position", type="string", description="Position title"),
 *     @OA\Property(property="report_to", type="string", nullable=true, description="Name or identifier of the manager position"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation date"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update date"),
 *     @OA\Property(property="created_by", type="string", nullable=true, description="User who created the record"),
 *     @OA\Property(property="updated_by", type="string", nullable=true, description="User who last updated the record")
 * )
 */
class DepartmentPosition extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'department',
        'position',
        'report_to',
        'created_by',
        'updated_by'
    ];

    // — Accessors —
    public function getFullTitleAttribute(): string
    {
        return trim($this->department . ' - ' . $this->position);
    }
}
