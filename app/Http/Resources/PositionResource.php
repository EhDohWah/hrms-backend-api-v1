<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="PositionResource",
 *     title="Position Resource",
 *     description="Position API response resource for organizational hierarchy",
 *
 *     @OA\Property(property="id", type="integer", format="int64", description="Position ID"),
 *     @OA\Property(property="title", type="string", description="Position title"),
 *     @OA\Property(property="department_id", type="integer", description="Department ID"),
 *     @OA\Property(property="department", ref="#/components/schemas/DepartmentResource", description="Department information"),
 *     @OA\Property(property="manager_name", type="string", nullable=true, description="Name of the manager this position reports to"),
 *     @OA\Property(property="level", type="integer", description="Hierarchy level (1=top, 2=reports to level 1, etc.)"),
 *     @OA\Property(property="is_manager", type="boolean", description="Whether this is a manager position"),
 *     @OA\Property(property="is_active", type="boolean", description="Whether the position is active"),
 *     @OA\Property(property="direct_reports_count", type="integer", description="Number of positions reporting to this manager"),
 *     @OA\Property(property="is_department_head", type="boolean", description="Whether this is the department head position"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation date"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update date")
 * )
 */
class PositionResource extends JsonResource
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
            'title' => $this->title,
            'department_id' => $this->department_id,
            'department' => new DepartmentResource($this->whenLoaded('department')),
            'manager_name' => $this->manager_name,
            'level' => $this->level,
            'is_manager' => $this->is_manager,
            'is_active' => $this->is_active,
            'direct_reports_count' => $this->whenCounted('directReports'),
            'is_department_head' => $this->isDepartmentHead(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
