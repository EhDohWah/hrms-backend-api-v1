<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DepartmentDetailResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'positions_count' => $this->whenCounted('positions'),
            'active_positions_count' => $this->whenCounted('activePositions'),
            'positions' => PositionResource::collection($this->whenLoaded('positions')),
            'department_head' => $this->when(
                $this->relationLoaded('positions'),
                function () {
                    $head = $this->departmentHead();

                    return $head ? new PositionResource($head) : null;
                }
            ),
            'managers' => $this->when(
                $this->relationLoaded('positions'),
                function () {
                    return PositionResource::collection(
                        $this->positions->where('is_manager', true)->where('is_active', true)
                    );
                }
            ),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
