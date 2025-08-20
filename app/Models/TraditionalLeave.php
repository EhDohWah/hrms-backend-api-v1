<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="TraditionalLeave",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="description", type="string"),
 *     @OA\Property(property="date", type="string", format="date"),
 *     @OA\Property(property="created_by", type="string", nullable=true),
 *     @OA\Property(property="updated_by", type="string", nullable=true)
 * )
 */
class TraditionalLeave extends Model
{
    //
    protected $table = 'traditional_leaves';

    protected $fillable = [
        'name',
        'description',
        'date',
        'created_by',
        'updated_by',
    ];

    public $timestamps = true;
}
