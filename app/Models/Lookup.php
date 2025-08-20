<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="Lookup",
 *     title="Lookup",
 *     description="Lookup model for various system reference data",
 *
 *     @OA\Property(property="id", type="integer", format="int64", description="Lookup ID"),
 *     @OA\Property(property="type", type="string", description="Type of lookup (gender, subsidiary, etc.)"),
 *     @OA\Property(property="value", type="string", description="Display value"),
 *     @OA\Property(property="created_by", type="string", nullable=true, description="User who created the record"),
 *     @OA\Property(property="updated_by", type="string", nullable=true, description="User who last updated the record"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp")
 * )
 */
class Lookup extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'type',
        'value',
        'created_by',
        'updated_by',
    ];

    /**
     * Get lookup values by type
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function getByType(string $type)
    {
        return self::where('type', $type)->get();
    }
}
