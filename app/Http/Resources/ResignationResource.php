<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResignationResource extends JsonResource
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
            'employee_id' => $this->employee_id,
            'department_id' => $this->department_id,
            'position_id' => $this->position_id,
            'resignation_date' => $this->resignation_date?->format('Y-m-d'),
            'last_working_date' => $this->last_working_date?->format('Y-m-d'),
            'reason' => $this->reason,
            'reason_details' => $this->reason_details,
            'acknowledgement_status' => $this->acknowledgement_status,
            'acknowledged_by' => $this->acknowledged_by,
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),

            // Computed attributes
            'notice_period_days' => $this->notice_period_days,
            'days_until_last_working' => $this->days_until_last_working,
            'is_overdue' => $this->is_overdue,

            // Relationships
            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'id' => $this->employee->id,
                    'staff_id' => $this->employee->staff_id,
                    'first_name_en' => $this->employee->first_name_en,
                    'last_name_en' => $this->employee->last_name_en,
                    'organization' => $this->employee->organization,
                ];
            }),
            'department' => $this->whenLoaded('department', function () {
                if (! $this->department) {
                    return null;
                }

                return [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                ];
            }),
            'position' => $this->whenLoaded('position', function () {
                if (! $this->position) {
                    return null;
                }

                return [
                    'id' => $this->position->id,
                    'title' => $this->position->title,
                ];
            }),
            'acknowledged_by_user' => $this->whenLoaded('acknowledgedBy', function () {
                if (! $this->acknowledgedBy) {
                    return null;
                }

                return [
                    'id' => $this->acknowledgedBy->id,
                    'name' => $this->acknowledgedBy->name,
                ];
            }),
        ];
    }
}
