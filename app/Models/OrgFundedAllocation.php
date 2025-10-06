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
 *     @OA\Property(property="department_id", type="integer", description="Department ID"),
 *     @OA\Property(property="position_id", type="integer", description="Position ID"),
 *     @OA\Property(property="description", type="string", description="Allocation description"),
 *     @OA\Property(property="created_by", type="string", description="User who created the record"),
 *     @OA\Property(property="updated_by", type="string", description="User who last updated the record"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp"),
 *     @OA\Property(property="grant", ref="#/components/schemas/Grant", description="Associated grant"),
 *     @OA\Property(property="department", ref="#/components/schemas/Department", description="Associated department"),
 *     @OA\Property(property="position", ref="#/components/schemas/Position", description="Associated position")
 * )
 */
class OrgFundedAllocation extends Model
{
    protected $fillable = [
        'grant_id',
        'department_id',
        'position_id',
        'description',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'grant_id' => 'integer',
            'department_id' => 'integer',
            'position_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    // Relationships
    public function grant()
    {
        return $this->belongsTo(Grant::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    // Validation: Ensure position belongs to the specified department
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($allocation) {
            $position = Position::find($allocation->position_id);
            if ($position && $position->department_id !== $allocation->department_id) {
                throw new \InvalidArgumentException('Position must belong to the specified department');
            }
        });

        static::updating(function ($allocation) {
            if ($allocation->isDirty(['department_id', 'position_id'])) {
                $position = Position::find($allocation->position_id);
                if ($position && $position->department_id !== $allocation->department_id) {
                    throw new \InvalidArgumentException('Position must belong to the specified department');
                }
            }
        });
    }

    // Scopes
    public function scopeForDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeForPosition($query, $positionId)
    {
        return $query->where('position_id', $positionId);
    }

    public function scopeForGrant($query, $grantId)
    {
        return $query->where('grant_id', $grantId);
    }
}
