<?php

namespace App\Http\Requests\Tax;

use Illuminate\Foundation\Http\FormRequest;

class ComplianceCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'gross_salary' => 'required|numeric|min:0',
            'tax_year' => 'nullable|integer|min:2000|max:2100',
        ];
    }
}
