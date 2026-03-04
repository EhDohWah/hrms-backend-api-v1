<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollPolicySettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // 13th Month Salary
            'thirteenth_month_enabled' => $this->thirteenth_month_enabled,
            'thirteenth_month_divisor' => $this->thirteenth_month_divisor,
            'thirteenth_month_min_months' => $this->thirteenth_month_min_months,
            'thirteenth_month_accrual_method' => $this->thirteenth_month_accrual_method,

            // Salary Increase
            'salary_increase_enabled' => $this->salary_increase_enabled,
            'salary_increase_rate' => $this->salary_increase_rate,
            'salary_increase_min_working_days' => $this->salary_increase_min_working_days,
            'salary_increase_effective_month' => $this->salary_increase_effective_month,

            // Metadata
            'effective_date' => $this->effective_date?->format('Y-m-d'),
            'is_active' => $this->is_active,
            'description' => $this->description,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Computed
            'status' => $this->is_active ? 'active' : 'inactive',
            'salary_increase_rate_formatted' => $this->salary_increase_rate.'%',
        ];
    }
}
