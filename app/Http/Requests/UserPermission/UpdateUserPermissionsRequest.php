<?php

namespace App\Http\Requests\UserPermission;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modules' => ['required', 'array'],
            'modules.*.read' => ['required', 'boolean'],
            'modules.*.edit' => ['required', 'boolean'],
        ];
    }
}
