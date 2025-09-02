<?php

namespace App\Http\Requests;

use App\Models\TaxSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaxSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('tax.update');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $taxSettingId = $this->route('id');

        return [
            'setting_key' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::in(TaxSetting::getAllowedKeys()),
                Rule::unique('tax_settings')->ignore($taxSettingId)->where(function ($query) {
                    if ($this->has('effective_year')) {
                        return $query->where('effective_year', $this->effective_year);
                    }

                    return $query;
                }),
            ],
            'setting_value' => 'sometimes|required|numeric|min:0',
            'setting_type' => [
                'sometimes',
                'required',
                'string',
                Rule::in([TaxSetting::TYPE_DEDUCTION, TaxSetting::TYPE_RATE, TaxSetting::TYPE_LIMIT, TaxSetting::TYPE_ALLOWANCE]),
            ],
            'description' => 'nullable|string|max:255',
            'effective_year' => 'sometimes|required|integer|min:2000|max:2100',
            'is_selected' => 'boolean',
            'updated_by' => 'nullable|string|max:100',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'setting_key.in' => 'The selected setting key is not allowed. Use /api/v1/tax-settings/allowed-keys to see valid options.',
            'setting_key.unique' => 'This setting key already exists for the specified year.',
            'setting_type.in' => 'Setting type must be one of: DEDUCTION, RATE, LIMIT, or ALLOWANCE.',
            'effective_year.integer' => 'Effective year must be a valid year.',
            'effective_year.min' => 'Effective year must be 2000 or later.',
            'effective_year.max' => 'Effective year must be 2100 or earlier.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'setting_key' => 'tax setting key',
            'setting_value' => 'setting value',
            'setting_type' => 'setting type',
            'effective_year' => 'effective year',
            'is_selected' => 'selection status',
        ];
    }
}
