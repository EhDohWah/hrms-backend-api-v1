<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollGrantAllocationResource extends JsonResource
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
            'payroll_id' => $this->payroll_id,
            'employee_funding_allocation_id' => $this->employee_funding_allocation_id,
            'grant_item_id' => $this->grant_item_id,
            'grant_code' => $this->grant_code,
            'grant_name' => $this->grant_name,
            'budget_line_code' => $this->budget_line_code,
            'grant_position' => $this->grant_position,
            'fte' => $this->fte,
            'fte_percentage' => $this->fte_percentage,
            'allocated_amount' => $this->allocated_amount,
            'salary_type' => $this->salary_type,
            'payroll' => $this->whenLoaded('payroll', fn () => [
                'id' => $this->payroll->id,
                'pay_period_date' => $this->payroll->pay_period_date,
                'employment_id' => $this->payroll->employment_id,
            ]),
            'funding_allocation' => $this->whenLoaded('fundingAllocation', fn () => [
                'id' => $this->fundingAllocation->id,
                'status' => $this->fundingAllocation->status,
            ]),
            'grant_item' => $this->whenLoaded('grantItem', fn () => [
                'id' => $this->grantItem->id,
                'grant_position' => $this->grantItem->grant_position,
                'budgetline_code' => $this->grantItem->budgetline_code,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
