<?php

namespace App\Http\Requests\Tax;

use Illuminate\Foundation\Http\FormRequest;

class ListTaxSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:100'],
            'filter_setting_type' => ['string', 'nullable'],
            'filter_effective_year' => ['string', 'nullable'],
            'filter_is_selected' => ['nullable', 'in:true,false,1,0'],
            'sort_by' => ['string', 'nullable', 'in:setting_key,setting_value,setting_type,effective_year'],
            'sort_order' => ['string', 'nullable', 'in:asc,desc'],
            'search' => ['string', 'nullable'],
        ];
    }
}
