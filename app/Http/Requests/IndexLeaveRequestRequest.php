<?php

namespace App\Http\Requests;

use App\Enums\LeaveRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexLeaveRequestRequest extends FormRequest
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
            'from' => 'date|nullable',
            'to' => 'date|nullable',
            'leave_types' => 'string|nullable',
            'status' => [
                'string',
                'nullable',
                Rule::enum(LeaveRequestStatus::class),
            ],
            'supervisor_approved' => 'boolean|nullable',
            'hr_site_admin_approved' => 'boolean|nullable',
            'sort_by' => 'string|nullable|in:recently_added,ascending,descending,last_month,last_7_days',
        ];
    }
}
