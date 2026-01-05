<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only authenticated users can create roles
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
                'unique:roles,name',
                'regex:/^[a-z0-9-]+$/', // Only lowercase letters, numbers, and hyphens
                'not_in:admin,hr-manager', // Prevent creating roles with protected names
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
            'name.not_in' => 'Cannot create role with a protected system role name',
        ];
    }
}
