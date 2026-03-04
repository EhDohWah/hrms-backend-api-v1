<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SearchPayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'staff_id' => 'nullable|string|max:50',
            'search' => 'nullable|string|max:100',
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:50',
        ];
    }

    /**
     * Ensure at least one search parameter is provided.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (empty($this->input('staff_id')) && empty($this->input('search'))) {
                $validator->errors()->add('search', 'Either staff_id or search parameter must be provided.');
            }
        });
    }
}
