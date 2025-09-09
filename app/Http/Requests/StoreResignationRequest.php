<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreResignationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Core fields as per schema
            'employee_id' => 'required|exists:employees,id',
            'department_id' => 'nullable|exists:department_positions,id',
            'position_id' => 'nullable|exists:department_positions,id',
            'resignation_date' => 'required|date|before_or_equal:today',
            'last_working_date' => 'required|date|after_or_equal:resignation_date',
            'reason' => 'required|string|max:50',
            'reason_details' => 'nullable|string',
            'acknowledgement_status' => 'sometimes|string|max:50|in:Pending,Acknowledged,Rejected',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Please select an employee.',
            'employee_id.exists' => 'The selected employee does not exist.',
            'department_id.exists' => 'The selected department does not exist.',
            'position_id.exists' => 'The selected position does not exist.',
            'resignation_date.required' => 'Resignation date is required.',
            'resignation_date.before_or_equal' => 'Resignation date cannot be in the future.',
            'last_working_date.required' => 'Last working date is required.',
            'last_working_date.after_or_equal' => 'Last working date must be on or after resignation date.',
            'reason.required' => 'Please provide a reason for resignation.',
            'reason.max' => 'Reason cannot exceed 50 characters.',
            'acknowledgement_status.in' => 'Invalid acknowledgement status.',
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     */
    public function attributes(): array
    {
        return [
            'employee_id' => 'employee',
            'department_id' => 'department',
            'position_id' => 'position',
            'resignation_date' => 'resignation date',
            'last_working_date' => 'last working date',
            'reason_details' => 'reason details',
            'acknowledgement_status' => 'acknowledgement status',
        ];
    }
}
