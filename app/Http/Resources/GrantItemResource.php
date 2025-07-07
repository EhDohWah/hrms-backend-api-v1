<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GrantItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                     => $this->id,
            'grant_id'               => $this->grant_id,
            'grant_code'             => $this->grant?->code,
            'grant_name'             => $this->grant?->name,
            'grant_position'         => $this->grant_position,
            'grant_salary'           => $this->grant_salary,
            'grant_benefit'          => $this->grant_benefit,
            'grant_level_of_effort'  => $this->grant_level_of_effort,
            'grant_position_number'  => $this->grant_position_number,
            'position_slots'         => PositionSlotResource::collection($this->whenLoaded('positionSlots')),
            'created_at'             => $this->created_at,
            'updated_at'             => $this->updated_at,
        ];
    }
}
