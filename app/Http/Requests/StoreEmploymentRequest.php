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
            'employment_type' => ['required', 'string', Rule::in(['Full-time', 'Part-time', 'Contract', 'Temporary'])],
            'pay_method' => ['nullable', 'string', Rule::in(['Transferred to bank', 'Cash cheque'])],
            'probation_pass_date' => ['nullable', 'date', 'before:start_date'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'department_position_id' => ['nullable', 'integer', 'exists:department_positions,id'],
            'section_department' => ['nullable', 'string', 'max:255'],
            'work_location_id' => ['nullable', 'integer', 'exists:work_locations,id'],
            'position_salary' => ['required', 'numeric', 'min:0'],
            'probation_salary' => ['nullable', 'numeric', 'min:0'],
            'fte' => ['nullable', 'numeric', 'min:0', 'max:2'],
            'health_welfare' => ['boolean'],
            'pvd' => ['boolean'],
            'saving_fund' => ['boolean'],

            // Allocation fields (matching actual database schema)
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.allocation_type' => ['required', 'string', Rule::in(['grant', 'org_funded'])],
            'allocations.*.level_of_effort' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'allocations.*.position_slot_id' => ['required_if:allocations.*.allocation_type,grant', 'nullable', 'integer', 'exists:position_slots,id'],
            'allocations.*.org_funded_id' => ['nullable', 'integer'],
            'allocations.*.grant_id' => ['nullable', 'integer', 'exists:grants,id'],
            'allocations.*.allocated_amount' => ['nullable', 'numeric', 'min:0'],
            'allocations.*.start_date' => ['nullable', 'date'],
            'allocations.*.end_date' => ['nullable', 'date', 'after:allocations.*.start_date'],
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
            'employment_type.required' => 'Employment type is required.',
            'employment_type.in' => 'Invalid employment type. Must be Full-time, Part-time, Contract, or Temporary.',
            'pay_method.in' => 'Invalid pay method. Must be "Transferred to bank" or "Cash cheque".',
            'probation_pass_date.before' => 'Probation pass date must be before employment start date.',
            'start_date.required' => 'Employment start date is required.',
            'end_date.after' => 'Employment end date must be after start date.',
            'department_position_id.exists' => 'Selected department position does not exist.',
            'work_location_id.exists' => 'Selected work location does not exist.',
            'position_salary.required' => 'Position salary is required.',
            'position_salary.min' => 'Position salary must be at least 0.',
            'fte.min' => 'FTE must be at least 0.',
            'fte.max' => 'FTE cannot exceed 2.0.',
            'health_welfare.boolean' => 'Health welfare must be true or false.',
            'pvd.boolean' => 'PVD must be true or false.',
            'saving_fund.boolean' => 'Saving fund must be true or false.',
            'allocations.required' => 'At least one funding allocation is required.',
            'allocations.min' => 'At least one funding allocation must be specified.',
            'allocations.*.allocation_type.required' => 'Funding allocation type is required.',
            'allocations.*.allocation_type.in' => 'Invalid allocation type. Must be grant or org_funded.',
            'allocations.*.level_of_effort.required' => 'Level of effort is required for each allocation.',
            'allocations.*.level_of_effort.min' => 'Level of effort must be at least 0.01%.',
            'allocations.*.level_of_effort.max' => 'Level of effort cannot exceed 100%.',
            'allocations.*.position_slot_id.exists' => 'Selected position slot does not exist.',
            'allocations.*.position_slot_id.required_if' => 'Position slot is required for grant allocations.',
            'allocations.*.grant_id.exists' => 'Selected grant does not exist.',
            'allocations.*.allocated_amount.min' => 'Allocated amount cannot be negative.',
            'allocations.*.end_date.after' => 'Allocation end date must be after start date.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate total level of effort equals 100%
            if ($this->has('allocations')) {
                $totalEffort = array_sum(array_column($this->allocations, 'level_of_effort'));
                if (abs($totalEffort - 100) > 0.01) { // Allow small floating point differences
                    $validator->errors()->add('allocations',
                        'Total level of effort must equal 100%. Current total: '.number_format($totalEffort, 2).'%'
                    );
                }
            }

            // Validate grant allocations have position_slot_id
            if ($this->has('allocations')) {
                foreach ($this->allocations as $index => $allocation) {
                    if ($allocation['allocation_type'] === 'grant' && empty($allocation['position_slot_id'])) {
                        $validator->errors()->add("allocations.{$index}.position_slot_id",
                            'Position slot is required for grant allocations.'
                        );
                    }
                }
            }

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
