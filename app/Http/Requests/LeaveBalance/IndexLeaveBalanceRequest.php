<?php

namespace App\Http\Requests\LeaveBalance;

use Illuminate\Foundation\Http\FormRequest;

class IndexLeaveBalanceRequest extends FormRequest
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
            'employee_id' => 'integer|exists:employees,id',
            'leave_type_id' => 'integer|exists:leave_types,id',
            'year' => 'integer|min:2020|max:2030',
            'search' => 'string|nullable|max:255',
            'sort_by' => 'string|nullable|in:employee_name,staff_id,leave_type,total_days,used_days,remaining_days,year,created_at',
            'sort_order' => 'string|nullable|in:asc,desc',
        ];
    }
}
