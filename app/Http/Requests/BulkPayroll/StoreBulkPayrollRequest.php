<?php

namespace App\Http\Requests\BulkPayroll;

use Illuminate\Foundation\Http\FormRequest;

class StoreBulkPayrollRequest extends FormRequest
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
        ];
    }
}
