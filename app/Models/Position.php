<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Position Model
 *
 * Note: OpenAPI schema is defined in PositionResource to avoid duplication
 * and to ensure the schema matches the actual API response structure.
 */
class Position extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'department_id',
        'reports_to_position_id',
        'level',
        'is_manager',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_manager' => 'boolean',
            'is_active' => 'boolean',
            'level' => 'integer',
            'department_id' => 'integer',
            'reports_to_position_id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected $hidden = [
        'created_by',
        'updated_by',
    ];

    // Removed automatic eager loading to prevent memory issues
    // protected $with = ['department'];

    /**
     * Get the department this position belongs to
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the position this position reports to
     */
    public function reportsTo(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'reports_to_position_id');
    }

    /**
     * Get the manager this position reports to
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'reports_to_position_id')
            ->where('is_manager', true);
    }

    /**
     * Get positions that report to this position (only if this is a manager)
     */
    public function directReports(): HasMany
    {
        return $this->hasMany(Position::class, 'reports_to_position_id')
            ->where('is_active', true);
    }

    /**
     * Get all subordinates (active and inactive)
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(Position::class, 'reports_to_position_id');
    }

    /**
     * Get active subordinates only
     */
    public function activeSubordinates(): HasMany
    {
        return $this->subordinates()->where('is_active', true);
    }

    /**
     * Get the department manager for this position
     */
    public function getDepartmentManager()
    {
        return Position::where('department_id', $this->department_id)
            ->where('is_manager', true)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Check if this position is a department head
     */
    public function isDepartmentHead(): bool
    {
        return $this->level === 1 && $this->is_manager;
    }

    /**
     * Get positions at the same level in the same department
     */
    public function peers()
    {
        return $this->where('department_id', $this->department_id)
            ->where('level', $this->level)
            ->where('id', '!=', $this->id)
            ->where('is_active', true);
    }

    /**
     * Get direct reports count (only if this is a manager)
     */
    public function getDirectReportsCountAttribute()
    {
        return $this->directReports()->count();
    }

    /**
     * Get the manager's name for this position
     */
    public function getManagerNameAttribute()
    {
        // First try to get the direct manager
        if ($this->manager) {
            return $this->manager->title;
        }

        // If no direct manager, get the department manager
        $departmentManager = $this->getDepartmentManager();

        return $departmentManager?->title ?? 'No Manager Assigned';
    }

    /**
     * Scope for active positions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for manager positions
     */
    public function scopeManagers($query)
    {
        return $query->where('is_manager', true);
    }

    /**
     * Scope for positions by department
     */
    public function scopeInDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Scope for positions by level
     */
    public function scopeAtLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * Scope for search
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
                ->orWhereHas('department', function ($dq) use ($search) {
                    $dq->where('name', 'like', "%{$search}%");
                });
        });
    }

    /**
     * Scope to include direct reports count for managers
     */
    public function scopeWithDirectReportsCount($query)
    {
        return $query->withCount('directReports');
    }

    /**
     * Scope to include subordinates count
     */
    public function scopeWithSubordinatesCount($query)
    {
        return $query->withCount('subordinates');
    }

    /**
     * Scope for department heads
     */
    public function scopeDepartmentHeads($query)
    {
        return $query->where('level', 1)->where('is_manager', true);
    }

    /**
     * Boot method for model events
     */
    protected static function boot()
    {
        parent::boot();

        // When creating a position, validate hierarchy
        static::creating(function ($position) {
            if ($position->reports_to_position_id) {
                $supervisor = Position::find($position->reports_to_position_id);
                if ($supervisor && $supervisor->department_id !== $position->department_id) {
                    throw new \InvalidArgumentException('Position cannot report to someone from a different department');
                }
            }
        });

        // When updating, validate hierarchy
        static::updating(function ($position) {
            if ($position->reports_to_position_id && $position->isDirty('reports_to_position_id')) {
                $supervisor = Position::find($position->reports_to_position_id);
                if ($supervisor && $supervisor->department_id !== $position->department_id) {
                    throw new \InvalidArgumentException('Position cannot report to someone from a different department');
                }
            }
        });
    }
}
