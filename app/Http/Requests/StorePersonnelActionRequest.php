<?php

namespace App\Http\Requests;

use App\Models\PersonnelAction;
use Illuminate\Foundation\Http\FormRequest;

class StorePersonnelActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('personnel_action.create');
    }

    public function rules(): array
    {
        return [
            'employment_id' => ['required', 'exists:employments,id'],
            'effective_date' => ['required', 'date', 'after_or_equal:today'],
            'action_type' => ['required', 'in:'.implode(',', array_keys(PersonnelAction::ACTION_TYPES))],
            'action_subtype' => ['nullable', 'in:'.implode(',', array_keys(PersonnelAction::ACTION_SUBTYPES))],

            'current_employee_no' => ['nullable', 'string', 'max:255'],
            'current_department_id' => ['nullable', 'exists:departments,id'],
            'current_position_id' => ['nullable', 'exists:positions,id'],
            'current_site_id' => ['nullable', 'exists:sites,id'],
            'current_salary' => ['nullable', 'numeric', 'min:0'],
            'current_employment_date' => ['nullable', 'date'],

            'new_department_id' => ['nullable', 'exists:departments,id'],
            'new_position_id' => [
                'nullable',
                'integer',
                'exists:positions,id',
                function ($attribute, $value, $fail) {
                    if ($this->filled('new_department_id') && $value) {
                        $position = \App\Models\Position::find($value);
                        if ($position && $position->department_id != $this->new_department_id) {
                            $fail('The selected position must belong to the selected department.');
                        }
                    }
                },
            ],
            'new_site_id' => ['nullable', 'exists:sites,id'],
            'new_salary' => ['nullable', 'numeric', 'min:0'],
            'new_work_schedule' => ['nullable', 'string', 'max:255'],
            'new_report_to' => ['nullable', 'string', 'max:255'],
            'new_pay_plan' => ['nullable', 'string', 'max:255'],
            'new_phone_ext' => ['nullable', 'string', 'max:20'],
            'new_email' => ['nullable', 'email', 'max:255'],

            'comments' => ['nullable', 'string'],
            'change_details' => ['nullable', 'string'],
            'acknowledged_by' => ['nullable', 'string', 'max:255'],

            'dept_head_approved' => ['boolean'],
            'coo_approved' => ['boolean'],
            'hr_approved' => ['boolean'],
            'accountant_approved' => ['boolean'],
            'dept_head_approved_date' => ['nullable', 'date'],
            'coo_approved_date' => ['nullable', 'date'],
            'hr_approved_date' => ['nullable', 'date'],
            'accountant_approved_date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'employment_id.required' => 'Employment is required.',
            'employment_id.exists' => 'Selected employment does not exist.',
            'effective_date.required' => 'Effective date is required.',
            'effective_date.after_or_equal' => 'Effective date must be today or in the future.',
            'action_type.required' => 'Action type is required.',
            'action_type.in' => 'Selected action type is invalid.',
            'action_subtype.in' => 'Selected action subtype is invalid.',
            'new_department_id.exists' => 'Selected department does not exist.',
            'new_position_id.exists' => 'Selected position does not exist or does not belong to the selected department.',
            'new_site_id.exists' => 'Selected site does not exist.',
            'new_salary.numeric' => 'New salary must be a valid number.',
            'new_salary.min' => 'New salary must be greater than or equal to 0.',
            'current_salary.numeric' => 'Current salary must be a valid number.',
            'current_salary.min' => 'Current salary must be greater than or equal to 0.',
            'new_email.email' => 'New email must be a valid email address.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->action_type === 'position_change' && ! $this->new_position_id) {
                $validator->errors()->add('new_position_id', 'New position is required for position changes.');
            }

            if ($this->action_type === 'transfer' && ! $this->new_department_id && ! $this->new_site_id) {
                $validator->errors()->add('new_department_id', 'New department or site is required for transfers.');
            }

            if (in_array($this->action_type, ['fiscal_increment', 're_evaluated_pay']) && ! $this->new_salary) {
                $validator->errors()->add('new_salary', 'New salary is required for salary adjustments.');
            }

            if (in_array($this->action_type, ['promotion', 'demotion']) && ! $this->new_position_id) {
                $validator->errors()->add('new_position_id', 'New position is required for promotions/demotions.');
            }

            if ($this->action_type === 'transfer' && $this->action_subtype === null) {
                $validator->errors()->add('action_subtype', 'Action subtype is required for transfer actions.');
            }
        });
    }
}
