<?php

namespace App\Http\Requests\PersonnelAction;

use Illuminate\Foundation\Http\FormRequest;

class ApprovePersonnelActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approval_type' => ['required', 'string', 'in:dept_head,coo,hr,accountant'],
            'approved' => ['required', 'boolean'],
        ];
    }
}
