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
 *     @OA\Property(property="type", type="string", description="Type of lookup (gender, organization, etc.)"),
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

    /**
     * Get all distinct lookup types from the database
     *
     * @return \Illuminate\Support\Collection
     */
    public static function getAllTypes()
    {
        return self::distinct()->pluck('type')->sort()->values();
    }

    /**
     * Get all lookup data organized by type
     */
    public static function getAllLookups(): array
    {
        $types = self::getAllTypes();
        $result = [];

        foreach ($types as $type) {
            $result[$type] = self::getByType($type);
        }

        return $result;
    }

    /**
     * Check if a lookup type exists
     */
    public static function typeExists(string $type): bool
    {
        return self::where('type', $type)->exists();
    }

    /**
     * Get lookup values as array for validation rules
     */
    public static function getValuesForValidation(string $type): array
    {
        return self::where('type', $type)->pluck('value')->toArray();
    }

    /**
     * Get all lookup validation rules dynamically
     */
    public static function getValidationRules(): array
    {
        $types = self::getAllTypes();
        $rules = [];

        foreach ($types as $type) {
            $rules[$type] = self::getValuesForValidation($type);
        }

        return $rules;
    }

    /**
     * Get validation rule for a specific lookup type
     */
    public static function getValidationRule(string $type): array
    {
        return self::getValuesForValidation($type);
    }
}
