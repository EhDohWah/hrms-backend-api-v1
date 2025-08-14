<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeBeneficiaryResource extends JsonResource
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
            'beneficiary_name' => $this->beneficiary_name,
            'beneficiary_relationship' => $this->beneficiary_relationship,
            'phone_number' => $this->phone_number,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'employee' => [
                'id' => $this->whenLoaded('employee', fn() => $this->employee->id),
                'staff_id' => $this->whenLoaded('employee', fn() => $this->employee->staff_id),
                'first_name_en' => $this->whenLoaded('employee', fn() => $this->employee->first_name_en),
                'last_name_en' => $this->whenLoaded('employee', fn() => $this->employee->last_name_en),
            ]
        ];
    }
} 