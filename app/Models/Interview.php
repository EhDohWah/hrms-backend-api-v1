<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * @OA\Schema(
 *     schema="Interview",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="job_position", type="string"),
 *     @OA\Property(property="interview_date", type="string", format="date"),
 *     @OA\Property(property="start_time", type="string", format="time"),
 *     @OA\Property(property="end_time", type="string", format="time"),
 *     @OA\Property(property="interview_mode", type="string", enum={"in-person", "virtual"}),
 *     @OA\Property(property="interview_status", type="string", enum={"scheduled", "completed", "cancelled"}),
 *     @OA\Property(property="score", type="number", nullable=true),
 *     @OA\Property(property="feedback", type="string", nullable=true),
 *     @OA\Property(property="resume", type="string", nullable=true),
 *     @OA\Property(property="created_by", type="string", nullable=true),
 *     @OA\Property(property="updated_by", type="string", nullable=true)
 * )
 */
class Interview extends Model
{
    use HasFactory;
    protected $fillable = [
        'job_position',
        'interview_date',
        'start_time',
        'end_time',
        'interview_mode',
        'interview_status',
        'score',
        'feedback',
        'resume',
        'created_by',
        'updated_by'
    ];
}
