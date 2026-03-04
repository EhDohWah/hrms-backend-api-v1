<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;

#[OA\Schema(
    schema: 'Interview',
    required: ['candidate_name', 'job_position'],
    properties: [
        new OA\Property(property: 'id', type: 'integer', readOnly: true),
        new OA\Property(property: 'candidate_name', type: 'string', maxLength: 255),
        new OA\Property(property: 'phone', type: 'string', maxLength: 10, nullable: true),
        new OA\Property(property: 'job_position', type: 'string', maxLength: 255),
        new OA\Property(property: 'interviewer_name', type: 'string', nullable: true),
        new OA\Property(property: 'interview_date', type: 'string', format: 'date', nullable: true),
        new OA\Property(property: 'start_time', type: 'string', format: 'time', nullable: true),
        new OA\Property(property: 'end_time', type: 'string', format: 'time', nullable: true),
        new OA\Property(property: 'interview_mode', type: 'string', nullable: true),
        new OA\Property(property: 'interview_status', type: 'string', nullable: true),
        new OA\Property(property: 'hired_status', type: 'string', nullable: true),
        new OA\Property(property: 'score', type: 'number', format: 'decimal', nullable: true),
        new OA\Property(property: 'feedback', type: 'string', nullable: true),
        new OA\Property(property: 'reference_info', type: 'string', nullable: true),
        new OA\Property(property: 'created_by', type: 'string', nullable: true),
        new OA\Property(property: 'updated_by', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ]
)]
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
        'updated_by',
    ];

    protected $casts = [
        'interview_date' => 'date',
        'score' => 'decimal:2',
        'interview_status' => \App\Enums\InterviewStatus::class,
        'hired_status' => \App\Enums\HiredStatus::class,
    ];

    /**
     * Scope: search by candidate name, interviewer name, or job position.
     */
    public function scopeSearch(Builder $query, string $search): Builder
    {
        $term = trim($search);

        return $query->where(function ($q) use ($term) {
            $q->where('candidate_name', 'LIKE', "%{$term}%")
                ->orWhere('interviewer_name', 'LIKE', "%{$term}%")
                ->orWhere('job_position', 'LIKE', "%{$term}%");
        });
    }

    /**
     * Scope: filter by job positions (comma-separated string or array).
     */
    public function scopeFilterByJobPosition(Builder $query, string|array $jobPositions): Builder
    {
        $positions = is_array($jobPositions) ? $jobPositions : explode(',', $jobPositions);

        return $query->whereIn('job_position', $positions);
    }

    /**
     * Scope: filter by hired statuses (comma-separated string or array).
     */
    public function scopeFilterByHiredStatus(Builder $query, string|array $hiredStatuses): Builder
    {
        $statuses = is_array($hiredStatuses) ? $hiredStatuses : explode(',', $hiredStatuses);

        return $query->whereIn('hired_status', $statuses);
    }
}
