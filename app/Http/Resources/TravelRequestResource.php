<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TravelRequestResource extends JsonResource
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
            'destination' => $this->destination,
            'start_date' => $this->start_date,
            'to_date' => $this->to_date,
            'purpose' => $this->purpose,
            'grant' => $this->grant,
            'transportation' => $this->transportation,
            'transportation_other_text' => $this->transportation_other_text,
            'accommodation' => $this->accommodation,
            'accommodation_other_text' => $this->accommodation_other_text,

            // Request date
            'request_by_date' => $this->request_by_date,

            // Approval structure
            'supervisor_approved' => $this->supervisor_approved,
            'supervisor_approved_date' => $this->supervisor_approved_date,
            'hr_acknowledged' => $this->hr_acknowledged,
            'hr_acknowledgement_date' => $this->hr_acknowledgement_date,

            'remarks' => $this->remarks,
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
                ];
            }),
            'department' => $this->whenLoaded('department', function () {
                return [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                ];
            }),
            'position' => $this->whenLoaded('position', function () {
                return [
                    'id' => $this->position->id,
                    'title' => $this->position->title,
                    'department_id' => $this->position->department_id,
                ];
            }),
        ];
    }
}
