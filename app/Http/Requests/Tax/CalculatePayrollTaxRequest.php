<?php

namespace App\Http\Requests\Tax;

use Illuminate\Foundation\Http\FormRequest;

class CalculatePayrollTaxRequest extends FormRequest
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
            'additional_income' => 'nullable|array',
            'additional_income.*.type' => 'required_with:additional_income|string',
            'additional_income.*.amount' => 'required_with:additional_income|numeric|min:0',
            'additional_income.*.description' => 'nullable|string',
            'additional_deductions' => 'nullable|array',
            'additional_deductions.*.type' => 'required_with:additional_deductions|string',
            'additional_deductions.*.amount' => 'required_with:additional_deductions|numeric|min:0',
            'additional_deductions.*.description' => 'nullable|string',
        ];
    }
}
