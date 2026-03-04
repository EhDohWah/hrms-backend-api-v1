<?php

namespace App\Http\Requests\Tax;

use Illuminate\Foundation\Http\FormRequest;

class CalculateIncomeTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Simple mode: just provide the already-computed taxable income
            'taxable_income' => 'required_without:annual_gross_income|nullable|numeric|min:0',

            // Full mode: provide gross income + personal data, and we compute taxable income
            'annual_gross_income' => 'required_without:taxable_income|nullable|numeric|min:0',

            'tax_year' => 'nullable|integer|min:2000|max:2100',

            // Personal allowance variables (used only with annual_gross_income)
            'is_married' => 'nullable|boolean',
            'num_children' => 'nullable|integer|min:0|max:20',
            'num_children_born_2018_plus' => 'nullable|integer|min:0|max:20',
            'num_eligible_parents' => 'nullable|integer|min:0|max:4',

            // Employee type determines SSF and PVD/Saving Fund eligibility
            // local_id = Thai citizen (SSF + PVD)
            // local_non_id = Non-Thai with work permit (SSF + Saving Fund)
            // expat = Expatriate (no SSF, no PVD/SF)
            'employee_type' => 'nullable|string|in:local_id,local_non_id,expat',
        ];
    }

    public function messages(): array
    {
        return [
            'taxable_income.required_without' => 'Provide either taxable_income (simple mode) or annual_gross_income (full mode).',
            'annual_gross_income.required_without' => 'Provide either annual_gross_income (full mode) or taxable_income (simple mode).',
            'employee_type.in' => 'Employee type must be local_id, local_non_id, or expat.',
        ];
    }
}
