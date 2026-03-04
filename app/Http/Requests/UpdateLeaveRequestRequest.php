<?php

namespace App\Http\Requests;

use App\Enums\LeaveRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeaveRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:1000',
            'status' => [
                'nullable',
                Rule::enum(LeaveRequestStatus::class),
            ],

            // Items array for updating leave types
            'items' => 'nullable|array|min:1',
            'items.*.leave_type_id' => 'required_with:items|exists:leave_types,id',
            'items.*.days' => 'required_with:items|numeric|min:0.5',

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
            'end_date.after_or_equal' => 'End date must be on or after the start date.',
            'items.*.leave_type_id.exists' => 'Selected leave type does not exist.',
            'items.*.days.min' => 'Each item must have at least 0.5 days.',
        ];
    }
}
