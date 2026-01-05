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
            'pass_probation_date' => ['nullable', 'date', 'after:start_date'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'position_id' => ['sometimes', 'integer', 'exists:positions,id'],
            'section_department' => ['nullable', 'string', 'max:255'],
            'site_id' => ['sometimes', 'integer', 'exists:sites,id'],
            'pass_probation_salary' => ['sometimes', 'numeric', 'min:0'],
            'probation_salary' => ['nullable', 'numeric', 'min:0', 'lte:pass_probation_salary'],
            'health_welfare' => ['sometimes', 'boolean'],
            'health_welfare_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pvd' => ['sometimes', 'boolean'],
            'pvd_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'saving_fund' => ['sometimes', 'boolean'],
            'saving_fund_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'status' => ['sometimes', 'boolean'],
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
            'pass_probation_date.after' => 'Pass probation date must be after employment start date.',
            'probation_salary.lte' => 'Probation salary must be less than or equal to pass probation salary.',
            'end_date.after' => 'Employment end date must be after start date.',
            'department_id.exists' => 'Selected department does not exist.',
            'position_id.exists' => 'Selected position does not exist.',
            'site_id.exists' => 'Selected site does not exist.',
            'pass_probation_salary.min' => 'Pass probation salary must be at least 0.',
            'health_welfare.boolean' => 'Health welfare must be true or false.',
            'pvd.boolean' => 'PVD must be true or false.',
            'saving_fund.boolean' => 'Saving fund must be true or false.',
            'status.boolean' => 'Status must be true (Active) or false (Inactive).',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate pass probation date relationship with start_date
            if ($this->filled('pass_probation_date') && $this->filled('start_date')) {
                $startDate = \Carbon\Carbon::parse($this->start_date);
                $probationDate = \Carbon\Carbon::parse($this->pass_probation_date);

                // Probation date must be after start date
                if ($probationDate->lte($startDate)) {
                    $validator->errors()->add('pass_probation_date',
                        'Pass probation date must be after employment start date.'
                    );
                }

                // Note: Frontend should auto-calculate 3 months from start_date
                // This validation allows flexibility for exceptional cases
            }
        });
    }
}
