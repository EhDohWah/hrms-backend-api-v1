<?php

namespace App\Http\Requests;

use App\Enums\LeaveRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:1000',
            'status' => [
                'nullable',
                'string',
                Rule::enum(LeaveRequestStatus::class),
            ],

            // Items array for multiple leave types
            'items' => 'required|array|min:1',
            'items.*.leave_type_id' => 'required|exists:leave_types,id',
            'items.*.days' => 'required|numeric|min:0.5',

            // Approval fields from paper forms
            'supervisor_approved' => 'nullable|boolean',
            'supervisor_approved_date' => 'nullable|date',
            'hr_site_admin_approved' => 'nullable|boolean',
            'hr_site_admin_approved_date' => 'nullable|date',

            // Attachment notes
            'attachment_notes' => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Employee is required.',
            'employee_id.exists' => 'Selected employee does not exist.',
            'start_date.required' => 'Start date is required.',
            'start_date.after_or_equal' => 'Start date cannot be in the past.',
            'end_date.required' => 'End date is required.',
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
            'items.required' => 'At least one leave type item is required.',
            'items.min' => 'At least one leave type item is required.',
            'items.*.leave_type_id.required' => 'Each item must have a leave type.',
            'items.*.leave_type_id.exists' => 'Selected leave type does not exist.',
            'items.*.days.required' => 'Each item must specify the number of days.',
            'items.*.days.min' => 'Each item must have at least 0.5 days.',
        ];
    }
}
