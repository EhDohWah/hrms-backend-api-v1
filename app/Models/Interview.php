<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use OpenApi\Annotations as OA;
use Illuminate\Support\Carbon;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;
use Illuminate\Support\Facades\DB;

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
    use HasFactory, KeepsDeletedModels;

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



    // //Below code is for SQL Server only, to allow explicit ID insertion.
    // /**
    //  * Hook called before restoring a deleted model.
    //  * Enables IDENTITY_INSERT for SQL Server to allow explicit ID insertion.
    //  */
    // public static function beforeRestoringModel($deletedModel): void
    // {
    //     if (DB::getDriverName() === 'sqlsrv') {
    //         DB::unprepared('SET IDENTITY_INSERT interviews ON');
    //     }
    // }

    // /**
    //  * Hook called after restoring a deleted model.
    //  * Disables IDENTITY_INSERT for SQL Server to return to normal operation.
    //  */
    // public static function afterRestoringModel(Model $restoredModel, $deletedModel): void
    // {
    //     if (DB::getDriverName() === 'sqlsrv') {
    //         DB::unprepared('SET IDENTITY_INSERT interviews OFF');
    //     }
    // }

    // // Mutator for interview_date (accepts ISO, SQL, etc.)
    // public function setInterviewDateAttribute($value)
    // {
    //     if (empty($value)) {
    //         $this->attributes['interview_date'] = null;
    //     } else {
    //         $this->attributes['interview_date'] = Carbon::parse($value)->format('Y-m-d');
    //     }
    // }

    // // Mutator for start_time
    // public function setStartTimeAttribute($value)
    // {
    //     if (empty($value)) {
    //         $this->attributes['start_time'] = null;
    //     } else {
    //         $this->attributes['start_time'] = Carbon::parse($value)->format('H:i:s');
    //     }
    // }

    // // Mutator for end_time
    // public function setEndTimeAttribute($value)
    // {
    //     if (empty($value)) {
    //         $this->attributes['end_time'] = null;
    //     } else {
    //         $this->attributes['end_time'] = Carbon::parse($value)->format('H:i:s');
    //     }
    // }
}
