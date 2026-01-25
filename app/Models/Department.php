<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OpenApi\Attributes as OA;

/**
 * Department Model
 */
#[OA\Schema(
    schema: 'Department',
    title: 'Department',
    description: 'Department model',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Human Resources'),
        new OA\Property(property: 'description', type: 'string', nullable: true, example: 'HR Department'),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
    ]
)]
class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'created_by',
        'updated_by',
    ];

    /**
     * Get all positions in this department
     */
    public function positions(): HasMany
    {
        return $this->hasMany(Position::class);
    }

    /**
     * Get active positions only
     */
    public function activePositions(): HasMany
    {
        return $this->positions()->where('is_active', true);
    }

    /**
     * Get manager positions (department heads)
     */
    public function managerPositions(): HasMany
    {
        return $this->positions()->where('is_manager', true)->where('is_active', true);
    }

    /**
     * Get the department head (top-level manager)
     */
    public function departmentHead()
    {
        return $this->positions()
            ->where('level', 1)
            ->where('is_manager', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Get positions count
     */
    public function getPositionsCountAttribute(): int
    {
        return $this->positions()->count();
    }

    /**
     * Get active positions count
     */
    public function getActivePositionsCountAttribute(): int
    {
        return $this->activePositions()->count();
    }

    /**
     * Scope for active departments
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for search
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    /**
     * Scope to include positions count
     */
    public function scopeWithPositionsCount($query)
    {
        return $query->withCount(['positions', 'activePositions']);
    }
}
