<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmploymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'position_id' => $this->position_id,
            'department_id' => $this->department_id,
            'section_department_id' => $this->section_department_id,
            'site_id' => $this->site_id,
            'pay_method' => $this->pay_method,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'pass_probation_date' => $this->pass_probation_date?->format('Y-m-d'),
            'end_probation_date' => $this->end_probation_date?->format('Y-m-d'),
            'probation_salary' => $this->probation_salary,
            'pass_probation_salary' => $this->pass_probation_salary,
            'probation_required' => $this->probation_required,
            'health_welfare' => $this->health_welfare,
            'pvd' => $this->pvd,
            'saving_fund' => $this->saving_fund,
            'is_active' => $this->end_date === null,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Dynamically appended benefit percentages (set by service)
            'health_welfare_percentage' => $this->resource->health_welfare_percentage ?? null,
            'pvd_percentage' => $this->resource->pvd_percentage ?? null,
            'saving_fund_percentage' => $this->resource->saving_fund_percentage ?? null,

            // Conditional relationships
            'employee' => $this->whenLoaded('employee'),
            'department' => $this->whenLoaded('department'),
            'position' => $this->whenLoaded('position'),
            'site' => $this->whenLoaded('site'),
            'employee_funding_allocations' => $this->whenLoaded('employeeFundingAllocations'),
        ];
    }
}
