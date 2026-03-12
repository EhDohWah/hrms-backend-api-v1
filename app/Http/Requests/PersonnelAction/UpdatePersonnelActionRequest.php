<?php

namespace App\Http\Requests\PersonnelAction;

use App\Models\PersonnelAction;
use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePersonnelActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('personnel_action.update');
    }

    public function rules(): array
    {
        return [
            'employment_id' => ['sometimes', 'exists:employments,id'],
            'effective_date' => ['sometimes', 'date'],
            'action_type' => ['sometimes', 'in:'.implode(',', array_keys(PersonnelAction::ACTION_TYPES))],
            'action_subtype' => ['nullable', 'in:'.implode(',', array_keys(PersonnelAction::ACTION_SUBTYPES))],
            'new_department_id' => ['nullable', 'exists:departments,id'],
            'new_position_id' => [
                'nullable',
                'integer',
                'exists:positions,id',
                function ($attribute, $value, $fail) {
                    if ($this->filled('new_department_id') && $value) {
                        $position = Position::find($value);
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
            'dept_head_approved_date' => ['nullable', 'date'],
            'coo_approved_date' => ['nullable', 'date'],
            'hr_approved_date' => ['nullable', 'date'],
            'accountant_approved_date' => ['nullable', 'date'],
        ];
    }
}
