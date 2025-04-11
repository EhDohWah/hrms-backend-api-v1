<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Interview",
 *     type="object",
 *     required={"candidate_name", "job_position"},
 *     @OA\Property(property="id", type="integer", readOnly=true),
 *     @OA\Property(property="candidate_name", type="string", maxLength=255),
 *     @OA\Property(property="phone", type="string", maxLength=10, nullable=true),
 *     @OA\Property(property="job_position", type="string", maxLength=255),
 *     @OA\Property(property="interviewer_name", type="string", nullable=true),
 *     @OA\Property(property="interview_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="start_time", type="string", format="time", nullable=true),
 *     @OA\Property(property="end_time", type="string", format="time", nullable=true),
 *     @OA\Property(property="interview_mode", type="string", nullable=true),
 *     @OA\Property(property="interview_status", type="string", nullable=true),
 *     @OA\Property(property="hired_status", type="string", nullable=true),
 *     @OA\Property(property="score", type="number", format="decimal", nullable=true),
 *     @OA\Property(property="feedback", type="string", nullable=true),
 *     @OA\Property(property="reference_info", type="string", nullable=true),
 *     @OA\Property(property="created_by", type="string", nullable=true),
 *     @OA\Property(property="updated_by", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class Interview extends Model
{
    use HasFactory;
    protected $fillable = [
        'candidate_name',
        'phone',
        'job_position',
        'interviewer_name',
        'interview_date',
        'start_time',
        'end_time',
        'interview_mode',
        'interview_status',
        'hired_status',
        'score',
        'feedback',
        'reference_info',
        'created_by',
        'updated_by'
    ];


}
