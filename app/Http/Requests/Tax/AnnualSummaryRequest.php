<?php

namespace App\Http\Requests\Tax;

use Illuminate\Foundation\Http\FormRequest;

class AnnualSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'tax_year' => 'nullable|integer|min:2000|max:2100',
            'monthly_payrolls' => 'required|array|min:1',
            'monthly_payrolls.*.month' => 'required|integer|between:1,12',
            'monthly_payrolls.*.total_income' => 'required|numeric|min:0',
            'monthly_payrolls.*.total_deductions' => 'required|numeric|min:0',
            'monthly_payrolls.*.income_tax' => 'required|numeric|min:0',
        ];
    }
}
