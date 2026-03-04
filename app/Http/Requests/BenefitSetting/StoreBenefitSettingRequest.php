<?php

namespace App\Http\Requests\BenefitSetting;

use App\Enums\BenefitCategory;
use App\Enums\BenefitSettingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBenefitSettingRequest extends FormRequest
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
            'setting_key' => ['required', 'string', 'unique:benefit_settings,setting_key', 'max:255'],
            'setting_value' => ['required', 'numeric'],
            'setting_type' => ['required', 'string', Rule::in(BenefitSettingType::values())],
            'category' => ['nullable', 'string', Rule::in(BenefitCategory::values())],
            'description' => ['nullable', 'string'],
            'effective_date' => ['nullable', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'applies_to' => ['nullable', 'array'],
        ];
    }
}
