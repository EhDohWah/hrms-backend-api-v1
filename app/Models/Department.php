<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Employment;

/**
 * @OA\Schema(
 *     schema="Department",
 *     title="Department",
 *     description="Department model",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="name", type="string", example="Human Resources"),
 *     @OA\Property(property="description", type="string", example="Department responsible for HR activities"),
 *     @OA\Property(property="created_by", type="string", example="admin"),
 *     @OA\Property(property="updated_by", type="string", example="admin"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'created_by',
        'updated_by'
    ];

    public function employments()
    {
        return $this->hasMany(Employment::class);
    }
}
