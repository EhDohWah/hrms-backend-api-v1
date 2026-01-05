<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 *     schema="SectionDepartmentResource",
 *     title="Section Department Resource",
 *     description="Section Department API response resource",
 *
 *     @OA\Property(property="id", type="integer", format="int64", description="Section Department ID"),
 *     @OA\Property(property="name", type="string", description="Section department name"),
 *     @OA\Property(property="department_id", type="integer", description="Parent department ID"),
 *     @OA\Property(property="department", type="object", description="Parent department details"),
 *     @OA\Property(property="description", type="string", nullable=true, description="Section department description"),
 *     @OA\Property(property="is_active", type="boolean", description="Whether the section department is active"),
 *     @OA\Property(property="employments_count", type="integer", description="Total number of employments in this section"),
 *     @OA\Property(property="active_employments_count", type="integer", description="Number of active employments in this section"),
 *     @OA\Property(property="created_at", type="string", format="date-time", description="Creation date"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", description="Last update date")
 * )
 */
class SectionDepartmentResource extends JsonResource
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
            'department_id' => $this->department_id,
            'department' => $this->whenLoaded('department', function () {
                return [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                ];
            }),
            'department_name' => $this->department?->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'employments_count' => $this->whenCounted('employments'),
            'active_employments_count' => $this->active_employments_count ?? null,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
