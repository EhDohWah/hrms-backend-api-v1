<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollPolicySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'thirteenth_month_enabled' => 'boolean',
            'thirteenth_month_divisor' => 'integer|min:1|max:24',
            'thirteenth_month_min_months' => 'integer|min:0|max:24',
            'thirteenth_month_accrual_method' => 'string|in:monthly,quarterly,annual',
            'salary_increase_enabled' => 'boolean',
            'salary_increase_rate' => 'numeric|min:0|max:100',
            'salary_increase_min_working_days' => 'integer|min:0|max:730',
            'salary_increase_effective_month' => 'nullable|integer|min:1|max:12',
            'effective_date' => 'required|date',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'effective_date.required' => 'Effective date is required.',
            'thirteenth_month_divisor.min' => 'Divisor must be at least 1.',
            'salary_increase_rate.max' => 'Salary increase rate cannot exceed 100%.',
        ];
    }
}
