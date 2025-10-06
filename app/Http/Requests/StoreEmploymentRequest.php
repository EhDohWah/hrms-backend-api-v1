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
            'probation_pass_date' => ['nullable', 'date', 'after:start_date'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'position_id' => ['required', 'integer', 'exists:positions,id', Rule::exists('positions', 'id')->where(fn ($q) => $q->where('department_id', $this->department_id))],
            'section_department' => ['nullable', 'string', 'max:255'],
            'work_location_id' => ['nullable', 'integer', 'exists:work_locations,id'],
            'position_salary' => ['required', 'numeric', 'min:0'],
            'probation_salary' => ['nullable', 'numeric', 'min:0'],
            'health_welfare' => ['boolean'],
            'health_welfare_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'pvd' => ['boolean'],
            'pvd_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'saving_fund' => ['boolean'],
            'saving_fund_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Allocation fields (matching actual database schema)
            'allocations' => ['required', 'array', 'min:1'],
            'allocations.*.allocation_type' => ['required', 'string', Rule::in(['grant', 'org_funded'])],
            'allocations.*.fte' => ['required', 'numeric', 'min:0.01', 'max:100'],
            'allocations.*.position_slot_id' => ['required_if:allocations.*.allocation_type,grant', 'nullable', 'integer', 'exists:position_slots,id'],
            'allocations.*.org_funded_id' => ['nullable', 'integer'],
            'allocations.*.grant_id' => ['required_if:allocations.*.allocation_type,org_funded', 'nullable', 'integer', 'exists:grants,id'],
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
            'probation_pass_date.after' => 'Probation pass date must be after employment start date.',
            'start_date.required' => 'Employment start date is required.',
            'end_date.after' => 'Employment end date must be after start date.',
            'department_id.required' => 'Department is required.',
            'department_id.exists' => 'Selected department does not exist.',
            'position_id.required' => 'Position is required.',
            'position_id.exists' => 'Selected position does not belong to the selected department or does not exist.',
            'work_location_id.exists' => 'Selected work location does not exist.',
            'position_salary.required' => 'Position salary is required.',
            'position_salary.min' => 'Position salary must be at least 0.',
            'health_welfare.boolean' => 'Health welfare must be true or false.',
            'pvd.boolean' => 'PVD must be true or false.',
            'saving_fund.boolean' => 'Saving fund must be true or false.',
            'allocations.required' => 'At least one funding allocation is required.',
            'allocations.min' => 'At least one funding allocation must be specified.',
            'allocations.*.allocation_type.required' => 'Funding allocation type is required.',
            'allocations.*.allocation_type.in' => 'Invalid allocation type. Must be grant or org_funded.',
            'allocations.*.fte.required' => 'FTE (Full-Time Equivalent) is required for each allocation.',
            'allocations.*.fte.min' => 'FTE must be at least 0.01%.',
            'allocations.*.fte.max' => 'FTE cannot exceed 100%.',
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
            // Validate total FTE equals 100%
            if ($this->has('allocations')) {
                $totalFte = array_sum(array_column($this->allocations, 'fte'));
                if (abs($totalFte - 100) > 0.01) { // Allow small floating point differences
                    $validator->errors()->add('allocations',
                        'Total FTE must equal 100%. Current total: '.number_format($totalFte, 2).'%'
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
