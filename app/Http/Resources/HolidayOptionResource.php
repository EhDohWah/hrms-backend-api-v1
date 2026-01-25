<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HolidayOptionResource extends JsonResource
{
    /**
     * Transform the resource into an array for dropdown options.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'date' => $this->date?->format('Y-m-d'),
            'year' => $this->year,
        ];
    }
}
