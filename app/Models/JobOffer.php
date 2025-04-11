<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @OA\Schema(
 *     schema="JobOffer",
 *     title="Job Offer",
 *     description="Job Offer model",
 *     @OA\Property(property="id", type="integer", format="int64", description="Job offer ID"),
 *     @OA\Property(property="date", type="string", format="date", description="Offer date"),
 *     @OA\Property(property="candidate_name", type="string", description="Name of the candidate"),
 *     @OA\Property(property="position_name", type="string", description="Name of the position"),
 *     @OA\Property(property="salary_detail", type="string", description="Salary details"),
 *     @OA\Property(property="acceptance_deadline", type="string", format="date", description="Deadline for acceptance"),
 *     @OA\Property(property="acceptance_status", type="string", description="Status of acceptance"),
 *     @OA\Property(property="note", type="string", description="Additional notes"),
 *     @OA\Property(property="created_by", type="string", description="User who created the record"),
 *     @OA\Property(property="updated_by", type="string", description="User who last updated the record"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation timestamp"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update timestamp")
 * )
 */
class JobOffer extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date',
        'candidate_name',
        'position_name',
        'salary_detail',
        'acceptance_deadline',
        'acceptance_status',
        'note',
        'created_by',
        'updated_by'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'date' => 'date',
        'acceptance_deadline' => 'date',
    ];
}
