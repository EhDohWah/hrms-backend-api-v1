<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PersonnelActionResource extends JsonResource
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
            'form_number' => $this->form_number,
            'reference_number' => $this->reference_number,
            'employment_id' => $this->employment_id,
            'current_employee_no' => $this->current_employee_no,
            'current_department_id' => $this->current_department_id,
            'current_position_id' => $this->current_position_id,
            'current_work_location_id' => $this->current_work_location_id,
            'current_salary' => $this->current_salary,
            'current_employment_date' => $this->current_employment_date?->format('Y-m-d'),
            'effective_date' => $this->effective_date?->format('Y-m-d'),
            'action_type' => $this->action_type,
            'action_subtype' => $this->action_subtype,
            'is_transfer' => $this->is_transfer,
            'transfer_type' => $this->transfer_type,
            'new_department_id' => $this->new_department_id,
            'new_position_id' => $this->new_position_id,
            'new_work_location_id' => $this->new_work_location_id,
            'new_work_schedule' => $this->new_work_schedule,
            'new_report_to' => $this->new_report_to,
            'new_pay_plan' => $this->new_pay_plan,
            'new_salary' => $this->new_salary,
            'new_phone_ext' => $this->new_phone_ext,
            'new_email' => $this->new_email,
            'comments' => $this->comments,
            'change_details' => $this->change_details,
            'dept_head_approved' => $this->dept_head_approved,
            'coo_approved' => $this->coo_approved,
            'hr_approved' => $this->hr_approved,
            'accountant_approved' => $this->accountant_approved,
            'status' => $this->status,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),

            // Relationships
            'employment' => $this->whenLoaded('employment'),
            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'id' => $this->employee->id,
                    'staff_id' => $this->employee->staff_id,
                    'first_name_en' => $this->employee->first_name_en,
                    'last_name_en' => $this->employee->last_name_en,
                ];
            }),
            'current_department' => $this->whenLoaded('currentDepartment', function () {
                return [
                    'id' => $this->currentDepartment->id,
                    'name' => $this->currentDepartment->name,
                ];
            }),
            'current_position' => $this->whenLoaded('currentPosition', function () {
                return [
                    'id' => $this->currentPosition->id,
                    'title' => $this->currentPosition->title,
                ];
            }),
            'current_work_location' => $this->whenLoaded('currentWorkLocation', function () {
                return [
                    'id' => $this->currentWorkLocation->id,
                    'name' => $this->currentWorkLocation->name,
                ];
            }),
            'new_department' => $this->whenLoaded('newDepartment', function () {
                return [
                    'id' => $this->newDepartment->id,
                    'name' => $this->newDepartment->name,
                ];
            }),
            'new_position' => $this->whenLoaded('newPosition', function () {
                return [
                    'id' => $this->newPosition->id,
                    'title' => $this->newPosition->title,
                ];
            }),
            'new_work_location' => $this->whenLoaded('newWorkLocation', function () {
                return [
                    'id' => $this->newWorkLocation->id,
                    'name' => $this->newWorkLocation->name,
                ];
            }),
        ];
    }
}
