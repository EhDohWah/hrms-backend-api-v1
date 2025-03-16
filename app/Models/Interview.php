<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="Interview",
 *     type="object",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="candidate_id", type="integer", nullable=true),
 *     @OA\Property(property="grant_position_id", type="integer", nullable=true),
 *     @OA\Property(property="interviewer_name", type="string", nullable=true),
 *     @OA\Property(property="interview_date", type="string", format="date", nullable=true),
 *     @OA\Property(property="start_time", type="string", format="time", nullable=true),
 *     @OA\Property(property="end_time", type="string", format="time", nullable=true),
 *     @OA\Property(property="interview_mode", type="string", enum={"in-person", "virtual"}, nullable=true),
 *     @OA\Property(property="interview_status", type="string", enum={"scheduled", "completed", "cancelled"}),
 *     @OA\Property(property="score", type="number", nullable=true),
 *     @OA\Property(property="feedback", type="string", nullable=true),
 *     @OA\Property(property="created_by", type="string", nullable=true),
 *     @OA\Property(property="updated_by", type="string", nullable=true)
 * )
 */
class Interview extends Model
{
    use HasFactory;
    protected $fillable = [
        'candidate_id',
        'interviewer_name',
        'interview_date',
        'start_time',
        'end_time',
        'interview_mode',
        'interview_status',
        'score',
        'feedback',
        'created_by',
        'updated_by'
    ];

    public function candidate()
    {
        return $this->belongsTo(Candidate::class);
    }

    public function grantPosition()
    {
        return $this->belongsTo(GrantPosition::class);
    }
}
