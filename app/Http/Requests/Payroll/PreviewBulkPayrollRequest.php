<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class PreviewBulkPayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pay_period_date' => ['required', 'date_format:Y-m-d'],
            'filters' => ['nullable', 'array'],
            'filters.subsidiaries' => ['nullable', 'array'],
            'detailed' => ['nullable', 'boolean'],
        ];
    }
}
