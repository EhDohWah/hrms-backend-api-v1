<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollPolicySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'thirteenth_month_enabled' => 'sometimes|boolean',
            'thirteenth_month_divisor' => 'sometimes|integer|min:1|max:24',
            'thirteenth_month_min_months' => 'sometimes|integer|min:0|max:24',
            'thirteenth_month_accrual_method' => 'sometimes|string|in:monthly,quarterly,annual',
            'salary_increase_enabled' => 'sometimes|boolean',
            'salary_increase_rate' => 'sometimes|numeric|min:0|max:100',
            'salary_increase_min_working_days' => 'sometimes|integer|min:0|max:730',
            'salary_increase_effective_month' => 'nullable|integer|min:1|max:12',
            'effective_date' => 'sometimes|date',
            'is_active' => 'sometimes|boolean',
            'description' => 'nullable|string|max:255',
        ];
    }
}
