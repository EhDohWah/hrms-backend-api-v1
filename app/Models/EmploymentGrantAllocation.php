<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Employment;
use App\Models\GrantItem;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="EmploymentGrantAllocation",
 *     type="object",
 *     title="Employment Grant Allocation",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="employment_id", type="integer", example=1),
 *     @OA\Property(property="grant_items_id", type="integer", example=1),
 *     @OA\Property(property="level_of_effort", type="number", format="float", example=0.75),
 *     @OA\Property(property="start_date", type="string", format="date", example="2023-01-01"),
 *     @OA\Property(property="end_date", type="string", format="date", example="2023-12-31", nullable=true),
 *     @OA\Property(property="active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class EmploymentGrantAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'employment_id',
        'grant_items_id',
        'level_of_effort',
        'start_date',
        'end_date',
        'active'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'active' => 'boolean',
        'level_of_effort' => 'decimal:2'
    ];

    public function employmentAllocation()
    {
        return $this->belongsTo(Employment::class, 'employment_id');
    }

    public function grantItemAllocation()
    {
        return $this->belongsTo(GrantItem::class, 'grant_items_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true)
                    ->where(function ($query) {
                        $query->whereNull('end_date')
                            ->orWhere('end_date', '>', now());
                    });
    }
}
