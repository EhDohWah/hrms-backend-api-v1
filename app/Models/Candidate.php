<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @OA\Schema(
 *     schema="Candidate",
 *     title="Candidate",
 *     description="Candidate model",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="candidate_name", type="string", example="John Doe"),
 *     @OA\Property(property="phone", type="string", example="123-456-7890"),
 *     @OA\Property(property="resume", type="string", example="resume_file.pdf"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Candidate extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'candidate_name',
        'phone',
        'resume',
    ];

    public function interviews()
    {
        return $this->hasMany(Interview::class);
    }

}
