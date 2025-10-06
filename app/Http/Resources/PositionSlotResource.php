<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionSlotResource extends JsonResource
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
            'grant_item_id' => $this->grant_item_id,
            'slot_number' => $this->slot_number,
            'budgetline_code' => $this->grantItem?->budgetline_code, // Access through grant item relationship
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'grant_item' => $this->whenLoaded('grantItem'),
        ];
    }
}
