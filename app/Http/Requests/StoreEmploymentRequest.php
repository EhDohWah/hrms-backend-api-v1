<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmploymentRequest extends FormRequest
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
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'organization' => ['required', 'string', Rule::in(['SMRU', 'BHF'])],
            'position_id' => ['required', 'integer', 'exists:positions,id', Rule::exists('positions', 'id')->where(fn ($q) => $q->where('department_id', $this->department_id))],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'section_department_id' => ['nullable', 'integer', 'exists:section_departments,id'],
            'site_id' => ['nullable', 'integer', 'exists:sites,id'],
            'pay_method' => ['nullable', 'string', Rule::in(['Transferred to bank', 'Cash cheque'])],
            'start_date' => ['required', 'date'],
            'pass_probation_date' => ['nullable', 'date', 'after:start_date'],
            'end_probation_date' => ['nullable', 'date', 'after:start_date'],
            'probation_salary' => ['nullable', 'numeric', 'min:0', 'lte:pass_probation_salary'],
            'pass_probation_salary' => ['required', 'numeric', 'min:0'],
            'health_welfare' => ['boolean'],
            'pvd' => ['boolean'],
            'saving_fund' => ['boolean'],
            'study_loan' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'retroactive_salary' => ['nullable', 'numeric', 'min:-999999.99', 'max:999999.99'],
            'probation_required' => ['nullable', 'boolean'],
            // NOTE: Benefit percentages are managed globally in benefit_settings table
            'end_date' => ['nullable', 'date'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Employee selection is required.',
            'employee_id.exists' => 'Selected employee does not exist.',
            'position_id.required' => 'Position is required.',
            'position_id.exists' => 'Selected position does not belong to the selected department or does not exist.',
            'department_id.required' => 'Department is required.',
            'department_id.exists' => 'Selected department does not exist.',
            'section_department_id.exists' => 'Selected section department does not exist.',
            'site_id.exists' => 'Selected site does not exist.',
            'pay_method.in' => 'Invalid pay method. Must be "Transferred to bank" or "Cash cheque".',
            'start_date.required' => 'Employment start date is required. Used to auto-calculate probation period (3 months).',
            'pass_probation_date.after' => 'Pass probation date must be after employment start date.',
            'end_probation_date.after' => 'End probation date must be after start date.',
            'probation_salary.lte' => 'Probation salary must be less than or equal to pass probation salary.',
            'pass_probation_salary.required' => 'Pass probation salary is required.',
            'pass_probation_salary.min' => 'Pass probation salary must be at least 0.',
            'health_welfare.boolean' => 'Health welfare must be true or false.',
            'pvd.boolean' => 'PVD must be true or false.',
            'saving_fund.boolean' => 'Saving fund must be true or false.',
            'end_date.date' => 'End date must be a valid date.',
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
