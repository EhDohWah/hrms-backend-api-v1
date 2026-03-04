<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportBulkPayslipsPdfRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // permission:employee_salary.read enforced on the route
    }

    public function rules(): array
    {
        return [
            'organization'    => ['required', 'string', Rule::in(['SMRU', 'BHF'])],
            'pay_period_date' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'organization.in'       => 'Organisation must be SMRU or BHF.',
            'pay_period_date.regex' => 'Pay period date must be in YYYY-MM format (e.g. 2025-02).',
        ];
    }
}
