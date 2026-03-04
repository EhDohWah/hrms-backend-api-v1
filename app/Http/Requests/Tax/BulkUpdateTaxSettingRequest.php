<?php

namespace App\Http\Requests\Tax;

use App\Models\TaxSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateTaxSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'effective_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'settings' => ['required', 'array', 'min:1'],
            'settings.*.setting_key' => [
                'required',
                'string',
                'max:50',
                Rule::in(TaxSetting::getAllowedKeys()),
            ],
            'settings.*.setting_value' => ['required', 'numeric', 'min:0'],
            'settings.*.setting_type' => ['required', 'string', 'in:DEDUCTION,RATE,LIMIT'],
            'settings.*.description' => ['nullable', 'string', 'max:255'],
            'updated_by' => ['nullable', 'string', 'max:100'],
        ];
    }
}
