<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\GrantItem;

/**
 * @OA\Schema(
 *     schema="Grant",
 *     title="Grant",
 *     description="Grant model",
 *     @OA\Property(
 *         property="id",
 *         type="integer",
 *         format="int64",
 *         description="Grant ID"
 *     ),
 *     @OA\Property(
 *         property="name",
 *         type="string",
 *         description="Name of the grant"
 *     ),
 *     @OA\Property(
 *         property="code",
 *         type="string",
 *         description="Unique code for the grant"
 *     ),
 *     @OA\Property(
 *         property="created_by",
 *         type="string",
 *         description="User who created the grant"
 *     ),
 *     @OA\Property(
 *         property="updated_by",
 *         type="string",
 *         description="User who last updated the grant"
 *     ),
 *     @OA\Property(
 *         property="created_at",
 *         type="string",
 *         format="date-time",
 *         description="Timestamp of creation"
 *     ),
 *     @OA\Property(
 *         property="updated_at",
 *         type="string",
 *         format="date-time",
 *         description="Timestamp of last update"
 *     )
 * )
 */
class Grant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'created_by',
        'updated_by'
    ];

    public function grantItems()
    {
        return $this->hasMany(GrantItem::class);
    }
}
