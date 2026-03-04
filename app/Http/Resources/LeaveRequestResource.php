<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveRequestResource extends JsonResource
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
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'total_days' => $this->total_days,
            'reason' => $this->reason,
            'status' => $this->status,

            // Approval structure
            'supervisor_approved' => $this->supervisor_approved,
            'supervisor_approved_date' => $this->supervisor_approved_date,
            'hr_site_admin_approved' => $this->hr_site_admin_approved,
            'hr_site_admin_approved_date' => $this->hr_site_admin_approved_date,

            'attachment_notes' => $this->attachment_notes,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,

            // Relationships
            'employee' => $this->whenLoaded('employee', function () {
                $data = [
                    'id' => $this->employee->id,
                    'staff_id' => $this->employee->staff_id,
                    'first_name_en' => $this->employee->first_name_en,
                    'last_name_en' => $this->employee->last_name_en,
                    'organization' => $this->employee->organization,
                ];

                // Include employment details when loaded (show view)
                if ($this->employee->relationLoaded('employment') && $this->employee->employment) {
                    $employment = $this->employee->employment;
                    $data['employment'] = [
                        'id' => $employment->id,
                        'department' => $employment->relationLoaded('department') && $employment->department
                            ? ['id' => $employment->department->id, 'name' => $employment->department->name]
                            : null,
                        'position' => $employment->relationLoaded('position') && $employment->position
                            ? ['id' => $employment->position->id, 'title' => $employment->position->title]
                            : null,
                    ];
                }

                return $data;
            }),

            // Multi-type items (primary relationship)
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'leave_type_id' => $item->leave_type_id,
                        'days' => $item->days,
                        'leave_type' => $item->relationLoaded('leaveType') && $item->leaveType ? [
                            'id' => $item->leaveType->id,
                            'name' => $item->leaveType->name,
                            'requires_attachment' => $item->leaveType->requires_attachment ?? null,
                            'default_duration' => $item->leaveType->default_duration ?? null,
                            'description' => $item->leaveType->description ?? null,
                        ] : null,
                    ];
                });
            }),

            // Deprecated: single leave type (backward compatibility)
            'leave_type' => $this->whenLoaded('leaveType', function () {
                return [
                    'id' => $this->leaveType->id,
                    'name' => $this->leaveType->name,
                    'requires_attachment' => $this->leaveType->requires_attachment,
                ];
            }),
        ];
    }
}
