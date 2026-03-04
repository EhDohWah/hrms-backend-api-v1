<?php

namespace App\Http\Requests\Tax;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaxBracketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'min_income' => 'required|numeric|min:0',
            'max_income' => 'nullable|numeric|gt:min_income',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'bracket_order' => 'required|integer|min:1',
            'effective_year' => 'required|integer|min:2000|max:2100',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'created_by' => 'nullable|string|max:100',
        ];
    }
}
