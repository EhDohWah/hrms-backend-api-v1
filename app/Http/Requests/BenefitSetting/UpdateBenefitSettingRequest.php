<?php

namespace App\Http\Requests\BenefitSetting;

use App\Enums\BenefitCategory;
use App\Enums\BenefitSettingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBenefitSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'setting_key' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('benefit_settings', 'setting_key')->ignore($this->route('benefitSetting')),
            ],
            'setting_value' => ['sometimes', 'numeric'],
            'setting_type' => ['sometimes', 'string', Rule::in(BenefitSettingType::values())],
            'category' => ['nullable', 'string', Rule::in(BenefitCategory::values())],
            'description' => ['nullable', 'string'],
            'effective_date' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'applies_to' => ['nullable', 'array'],
        ];
    }
}
