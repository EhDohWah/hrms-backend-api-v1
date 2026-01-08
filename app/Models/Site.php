<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Site',
    title: 'Site',
    description: 'Organizational site/unit model',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'MRM'),
        new OA\Property(property: 'code', type: 'string', example: 'MRM'),
        new OA\Property(property: 'description', type: 'string', nullable: true),
        new OA\Property(property: 'address', type: 'string', nullable: true),
        new OA\Property(property: 'contact_person', type: 'string', nullable: true),
        new OA\Property(property: 'contact_phone', type: 'string', nullable: true),
        new OA\Property(property: 'contact_email', type: 'string', nullable: true),
        new OA\Property(property: 'is_active', type: 'boolean', example: true),
        new OA\Property(property: 'created_by', type: 'string', nullable: true),
        new OA\Property(property: 'updated_by', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time', nullable: true),
    ]
)]
class Site extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'description',
        'address',
        'contact_person',
        'contact_phone',
        'contact_email',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relationships
     */
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
    public function getFullLocationAttribute(): string
    {
        $parts = array_filter([
            $this->name,
            $this->address,
        ]);

        return implode(', ', $parts);
    }
}
