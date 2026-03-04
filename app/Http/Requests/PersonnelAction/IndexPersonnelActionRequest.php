<?php

namespace App\Http\Requests\PersonnelAction;

use App\Models\PersonnelAction;
use Illuminate\Foundation\Http\FormRequest;

class IndexPersonnelActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'dept_head_approved' => ['nullable', 'boolean'],
            'coo_approved' => ['nullable', 'boolean'],
            'hr_approved' => ['nullable', 'boolean'],
            'accountant_approved' => ['nullable', 'boolean'],
            'action_type' => ['nullable', 'string', 'in:'.implode(',', array_keys(PersonnelAction::ACTION_TYPES))],
            'employment_id' => ['nullable', 'integer', 'exists:employments,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
