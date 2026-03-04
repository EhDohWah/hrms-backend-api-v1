<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobOfferResource extends JsonResource
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
            'custom_offer_id' => $this->custom_offer_id,
            'date' => $this->date?->format('Y-m-d'),
            'candidate_name' => $this->candidate_name,
            'position_name' => $this->position_name,
            'probation_salary' => $this->probation_salary,
            'pass_probation_salary' => $this->pass_probation_salary,
            'acceptance_deadline' => $this->acceptance_deadline?->format('Y-m-d'),
            'acceptance_status' => $this->acceptance_status,
            'note' => $this->note,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
