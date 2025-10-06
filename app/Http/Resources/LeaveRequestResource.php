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
            'leave_type_id' => $this->leave_type_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'total_days' => $this->total_days,
            'reason' => $this->reason,
            'status' => $this->status,

            // New approval structure
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
                return [
                    'id' => $this->employee->id,
                    'staff_id' => $this->employee->staff_id,
                    'first_name_en' => $this->employee->first_name_en,
                    'last_name_en' => $this->employee->last_name_en,
                    'subsidiary' => $this->employee->subsidiary,
                ];
            }),
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
