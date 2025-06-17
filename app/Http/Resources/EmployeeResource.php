<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\Employee;
use App\Models\EmployeeIdentification;

class EmployeeResource extends JsonResource
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
            'subsidiary' => $this->subsidiary,
            'staff_id' => $this->staff_id,
            'employee_identification' => $this->employeeIdentification,
            'employment' => $this->employment,
            'initial_en' => $this->initial_en,
            'first_name_en' => $this->first_name_en,
            'last_name_en' => $this->last_name_en,
            'gender' => $this->gender,
            'date_of_birth' => $this->date_of_birth,
            'status' => $this->status,
            'id_type' => $this->employeeIdentification ? $this->employeeIdentification->id_type : null,
            'id_number' => $this->employeeIdentification ? $this->employeeIdentification->id_number : null,
            'social_security_number' => $this->social_security_number,
            'tax_number' => $this->tax_number,
            'mobile_phone' => $this->mobile_phone,
        ];
    }
}
