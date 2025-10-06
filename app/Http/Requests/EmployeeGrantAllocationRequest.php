<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class EmployeeGrantAllocationRequest extends FormRequest
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
            'employee_id' => 'required|exists:employees,id',
            'employment_id' => 'nullable|exists:employments,id',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'active' => 'boolean',
            'allocations' => 'required|array|min:1',
            'allocations.*.position_slot_id' => 'required|exists:position_slots,id',
            'allocations.*.fte' => 'required|numeric|min:0|max:100',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Employee is required.',
            'employee_id.exists' => 'Selected employee does not exist.',
            'employment_id.exists' => 'Selected employment does not exist.',
            'start_date.required' => 'Start date is required.',
            'start_date.date' => 'Start date must be a valid date.',
            'end_date.date' => 'End date must be a valid date.',
            'end_date.after_or_equal' => 'End date must be after or equal to start date.',
            'allocations.required' => 'At least one allocation is required.',
            'allocations.array' => 'Allocations must be an array.',
            'allocations.min' => 'At least one allocation is required.',
            'allocations.*.position_slot_id.required' => 'Position slot is required for each allocation.',
            'allocations.*.position_slot_id.exists' => 'Selected position slot does not exist.',
            'allocations.*.fte.required' => 'Level of effort is required for each allocation.',
            'allocations.*.fte.numeric' => 'Level of effort must be a number.',
            'allocations.*.fte.min' => 'Level of effort must be at least 0.',
            'allocations.*.fte.max' => 'Level of effort cannot exceed 100.',
        ];
    }
}
