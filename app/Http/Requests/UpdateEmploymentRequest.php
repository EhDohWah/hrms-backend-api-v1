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
            'probation_pass_date' => ['nullable', 'date', 'after:start_date'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'position_id' => ['sometimes', 'integer', 'exists:positions,id'],
            'section_department' => ['nullable', 'string', 'max:255'],
            'work_location_id' => ['sometimes', 'integer', 'exists:work_locations,id'],
            'position_salary' => ['sometimes', 'numeric', 'min:0'],
            'probation_salary' => ['nullable', 'numeric', 'min:0'],
            'health_welfare' => ['sometimes', 'boolean'],
            'health_welfare_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pvd' => ['sometimes', 'boolean'],
            'pvd_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'saving_fund' => ['sometimes', 'boolean'],
            'saving_fund_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
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
            'probation_pass_date.after' => 'Probation pass date must be after employment start date.',
            'end_date.after' => 'Employment end date must be after start date.',
            'department_id.exists' => 'Selected department does not exist.',
            'position_id.exists' => 'Selected position does not exist.',
            'work_location_id.exists' => 'Selected work location does not exist.',
            'position_salary.min' => 'Position salary must be at least 0.',
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
            // Validate probation pass date relationship with start_date
            if ($this->filled('probation_pass_date') && $this->filled('start_date')) {
                $startDate = \Carbon\Carbon::parse($this->start_date);
                $probationDate = \Carbon\Carbon::parse($this->probation_pass_date);
                $expectedProbationDate = $startDate->copy()->addMonths(3);

                // Probation date must be after start date
                if ($probationDate->lte($startDate)) {
                    $validator->errors()->add('probation_pass_date',
                        'Probation pass date must be after employment start date.'
                    );
                }

                // Note: Frontend should auto-calculate 3 months from start_date
                // This validation allows flexibility for exceptional cases
            }
        });
    }
}
