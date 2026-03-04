<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class IndexPayrollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'search' => 'string|nullable|max:255',
            'filter_organization' => 'string|nullable',
            'filter_department' => 'string|nullable',
            'filter_position' => 'string|nullable',
            'filter_date_range' => 'string|nullable',
            'filter_payslip_date' => 'string|nullable',
            'sort_by' => 'string|nullable|in:organization,department,staff_id,employee_name,basic_salary,payslip_date,created_at,last_7_days,last_month,recently_added',
            'sort_order' => 'string|nullable|in:asc,desc',
        ];
    }
}
