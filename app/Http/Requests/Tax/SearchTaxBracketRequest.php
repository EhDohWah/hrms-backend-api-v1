<?php

namespace App\Http\Requests\Tax;

use Illuminate\Foundation\Http\FormRequest;

class SearchTaxBracketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'order_id' => 'required|integer|min:1',
            'effective_year' => 'nullable|integer|min:2000|max:2100',
            'is_active' => 'nullable|boolean',
        ];
    }
}
