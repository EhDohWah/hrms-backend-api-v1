<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmploymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Employment fields (matching actual database schema)
            'employment_type' => ['sometimes', 'string', Rule::in(['Full-time', 'Part-time', 'Contract', 'Temporary'])],
            'pay_method' => ['nullable', 'string', Rule::in(['Transferred to bank', 'Cash cheque'])],
            'probation_pass_date' => ['nullable', 'date', 'before:start_date'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'department_position_id' => ['sometimes', 'integer', 'exists:department_positions,id'],
            'section_department' => ['nullable', 'string', 'max:255'],
            'work_location_id' => ['sometimes', 'integer', 'exists:work_locations,id'],
            'position_salary' => ['sometimes', 'numeric', 'min:0'],
            'probation_salary' => ['nullable', 'numeric', 'min:0'],
            'fte' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'health_welfare' => ['sometimes', 'boolean'],
            'pvd' => ['sometimes', 'boolean'],
            'saving_fund' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'employment_type.in' => 'Invalid employment type. Must be Full-time, Part-time, Contract, or Temporary.',
            'pay_method.in' => 'Invalid pay method. Must be "Transferred to bank" or "Cash cheque".',
            'probation_pass_date.before' => 'Probation pass date must be before employment start date.',
            'end_date.after' => 'Employment end date must be after start date.',
            'department_position_id.exists' => 'Selected department position does not exist.',
            'work_location_id.exists' => 'Selected work location does not exist.',
            'position_salary.min' => 'Position salary must be at least 0.',
            'fte.min' => 'FTE must be at least 0.',
            'fte.max' => 'FTE cannot exceed 2.0.',
            'health_welfare.boolean' => 'Health welfare must be true or false.',
            'pvd.boolean' => 'PVD must be true or false.',
            'saving_fund.boolean' => 'Saving fund must be true or false.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate probation pass date is within reasonable timeframe
            if ($this->filled('probation_pass_date') && $this->filled('start_date')) {
                $startDate = \Carbon\Carbon::parse($this->start_date);
                $probationDate = \Carbon\Carbon::parse($this->probation_pass_date);

                // Probation period should not exceed 1 year before employment start
                if ($probationDate->lt($startDate->copy()->subYear())) {
                    $validator->errors()->add('probation_pass_date',
                        'Probation pass date cannot be more than 1 year before employment start date.'
                    );
                }
            }
        });
    }
}
