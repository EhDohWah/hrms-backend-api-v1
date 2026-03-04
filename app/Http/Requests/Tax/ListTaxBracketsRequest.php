<?php

namespace App\Http\Requests\Tax;

use Illuminate\Foundation\Http\FormRequest;

class ListTaxBracketsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'filter_effective_year' => 'string|nullable',
            'filter_is_active' => 'nullable|in:true,false,1,0',
            'sort_by' => 'string|nullable|in:effective_year,bracket_order,min_income,max_income,tax_rate',
            'sort_order' => 'string|nullable|in:asc,desc',
            'search' => 'string|nullable',
        ];
    }
}
