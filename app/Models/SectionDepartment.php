<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'SectionDepartment',
    title: 'Section Department',
    description: 'Sub-department within a department',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'Training'),
        new OA\Property(property: 'department_id', type: 'integer', example: 5),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'created_by', type: 'string', nullable: true),
        new OA\Property(property: 'updated_by', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
class SectionDepartment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'department_id',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'department_id' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function employments()
    {
        return $this->hasMany(Employment::class);
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    public function scopeWithCounts($query)
    {
        return $query->withCount([
            'employments',
            'employments as active_employments_count' => function ($q) {
                $q->where('is_active', true);
            },
        ]);
    }

    /**
     * Accessors
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->department->name} - {$this->name}";
    }
}
