<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeIdentificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'identification_type' => $this->identification_type,
            'identification_number' => $this->identification_number,
            'identification_issue_date' => $this->identification_issue_date,
            'identification_expiry_date' => $this->identification_expiry_date,
            'first_name_en' => $this->first_name_en,
            'last_name_en' => $this->last_name_en,
            'first_name_th' => $this->first_name_th,
            'last_name_th' => $this->last_name_th,
            'initial_en' => $this->initial_en,
            'initial_th' => $this->initial_th,
            'is_primary' => $this->is_primary,
            'notes' => $this->notes,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            'employee' => $this->whenLoaded('employee', function () {
                return [
                    'id' => $this->employee->id,
                    'staff_id' => $this->employee->staff_id,
                    'first_name_en' => $this->employee->first_name_en,
                    'last_name_en' => $this->employee->last_name_en,
                ];
            }),
        ];
    }
}
