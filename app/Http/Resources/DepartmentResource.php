<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="DepartmentResource",
 *     title="Department Resource",
 *     description="Department API response resource",
 *
 *     @OA\Property(property="id", type="integer", format="int64", description="Department ID"),
 *     @OA\Property(property="name", type="string", description="Department name"),
 *     @OA\Property(property="description", type="string", nullable=true, description="Department description"),
 *     @OA\Property(property="is_active", type="boolean", description="Whether the department is active"),
 *     @OA\Property(property="positions_count", type="integer", description="Number of positions in department"),
 *     @OA\Property(property="active_positions_count", type="integer", description="Number of active positions in department"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation date"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update date")
 * )
 */
class DepartmentResource extends JsonResource
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
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
