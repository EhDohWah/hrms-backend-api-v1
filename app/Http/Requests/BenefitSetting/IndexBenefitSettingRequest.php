<?php

namespace App\Http\Requests\BenefitSetting;

use Illuminate\Foundation\Http\FormRequest;

class IndexBenefitSettingRequest extends FormRequest
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
            'filter_is_active' => ['nullable', 'boolean'],
            'filter_setting_type' => ['nullable', 'string'],
            'filter_category' => ['nullable', 'string'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('filter_is_active')) {
            $this->merge([
                'filter_is_active' => filter_var($this->filter_is_active, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
