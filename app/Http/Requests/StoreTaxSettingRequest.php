<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\TaxSetting;

class StoreTaxSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('tax.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'setting_key' => 'required|string|max:50|unique:tax_settings,setting_key',
            'setting_value' => 'required|numeric|min:0',
            'setting_type' => 'required|string|in:' . implode(',', $this->getValidSettingTypes()),
            'description' => 'nullable|string|max:255',
            'effective_year' => 'required|integer|min:2000|max:2100',
            'is_active' => 'boolean',
            'created_by' => 'nullable|string|max:100'
        ];
    }

    /**
     * Get valid setting types.
     */
    private function getValidSettingTypes(): array
    {
        return [
            TaxSetting::TYPE_DEDUCTION,
            TaxSetting::TYPE_RATE,
            TaxSetting::TYPE_LIMIT
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'setting_key.required' => 'Setting key is required.',
            'setting_key.string' => 'Setting key must be a string.',
            'setting_key.max' => 'Setting key cannot exceed 50 characters.',
            'setting_key.unique' => 'A tax setting with this key already exists.',
            'setting_value.required' => 'Setting value is required.',
            'setting_value.numeric' => 'Setting value must be a valid number.',
            'setting_value.min' => 'Setting value cannot be negative.',
            'setting_type.required' => 'Setting type is required.',
            'setting_type.in' => 'Setting type must be one of: ' . implode(', ', $this->getValidSettingTypes()),
            'effective_year.required' => 'Effective year is required.',
            'effective_year.integer' => 'Effective year must be a valid year.',
            'effective_year.min' => 'Effective year must be 2000 or later.',
            'effective_year.max' => 'Effective year must be 2100 or earlier.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate rate values are percentages (0-100)
            if ($this->setting_type === TaxSetting::TYPE_RATE && $this->setting_value > 100) {
                $validator->errors()->add(
                    'setting_value',
                    'Rate values cannot exceed 100%.'
                );
            }

            // Validate setting key format
            if ($this->setting_key && !preg_match('/^[A-Z_]+$/', $this->setting_key)) {
                $validator->errors()->add(
                    'setting_key',
                    'Setting key must contain only uppercase letters and underscores.'
                );
            }

            // Check for duplicate key-year combination
            $existingSetting = TaxSetting::where('setting_key', $this->setting_key)
                ->where('effective_year', $this->effective_year)
                ->first();

            if ($existingSetting) {
                $validator->errors()->add(
                    'setting_key',
                    'A tax setting with this key already exists for the specified year.'
                );
            }
        });
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'setting_key' => 'setting key',
            'setting_value' => 'setting value',
            'setting_type' => 'setting type',
            'effective_year' => 'effective year',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert setting key to uppercase
        if ($this->has('setting_key')) {
            $this->merge([
                'setting_key' => strtoupper($this->setting_key)
            ]);
        }
    }
}