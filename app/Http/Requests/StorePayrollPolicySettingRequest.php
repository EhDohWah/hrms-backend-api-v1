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
            'policy_key' => ['required', 'string', 'max:100'],
            'policy_value' => ['nullable', 'numeric'],
            'setting_type' => ['required', 'string', 'in:percentage,boolean,numeric'],
            'category' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:255'],
            'effective_date' => ['required', 'date'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'policy_key.required' => 'Policy key is required.',
            'setting_type.required' => 'Setting type is required.',
            'setting_type.in' => 'Setting type must be one of: percentage, boolean, numeric.',
            'effective_date.required' => 'Effective date is required.',
        ];
    }
}
