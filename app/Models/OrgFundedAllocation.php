<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="OrgFundedAllocation",
 *     type="object",
 *     title="Organization Funded Allocation",
 *     description="Organization funded allocation model",
 *
 *     @OA\Property(property="id", type="integer", description="Unique identifier"),
 *     @OA\Property(property="grant_id", type="integer", description="Grant ID"),
 *     @OA\Property(property="department_position_id", type="integer", description="Department position ID"),
 *     @OA\Property(property="description", type="string", description="Allocation description"),
 *     @OA\Property(property="created_by", type="string", description="User who created the record"),
 *     @OA\Property(property="updated_by", type="string", description="User who last updated the record"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp"),
 *     @OA\Property(property="grant", ref="#/components/schemas/Grant", description="Associated grant"),
 *     @OA\Property(property="department_position", ref="#/components/schemas/DepartmentPosition", description="Associated department position")
 * )
 */
class OrgFundedAllocation extends Model
{
    protected $fillable = [
        'grant_id',
        'department_position_id',
        'description',
        'created_by',
        'updated_by',
    ];

    // Relationships
    public function grant()
    {
        return $this->belongsTo(Grant::class);
    }

    public function departmentPosition()
    {
        return $this->belongsTo(DepartmentPosition::class);
    }

    // Optionally, add global scopes for active status, auditing, etc.
}
