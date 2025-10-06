<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PositionDetailResource extends JsonResource
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

            // Direct reports (only if this is a manager and reports are loaded)
            'direct_reports' => $this->when($this->relationLoaded('directReports'), function () {
                return $this->directReports->map(function ($report) {
                    return [
                        'id' => $report->id,
                        'title' => $report->title,
                        'level' => $report->level,
                        'is_active' => $report->is_active,
                        'department_id' => $report->department_id,
                    ];
                });
            }),

            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
