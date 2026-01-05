<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only authenticated users can update roles
        // Additional permission checks are handled by middleware
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('roles', 'name')->ignore($this->route('id')),
                'regex:/^[a-z0-9-]+$/',
                'not_in:admin,hr-manager', // Prevent renaming to protected names
            ],
        ];
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Role name is required',
            'name.unique' => 'A role with this name already exists',
            'name.regex' => 'Role name must contain only lowercase letters, numbers, and hyphens (e.g., hr-coordinator, payroll-specialist)',
            'name.not_in' => 'Cannot rename role to a protected system role name',
        ];
    }
}
