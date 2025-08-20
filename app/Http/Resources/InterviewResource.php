<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterviewResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'candidate_name' => $this->candidate_name,
            'phone' => $this->phone,
            'job_position' => $this->job_position,
            'interviewer_name' => $this->interviewer_name,
            'interview_date' => $this->interview_date,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'interview_mode' => $this->interview_mode,
            'interview_status' => $this->interview_status,
            'hired_status' => $this->hired_status,
            'score' => $this->score,
            'feedback' => $this->feedback,
            'reference_info' => $this->reference_info,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
