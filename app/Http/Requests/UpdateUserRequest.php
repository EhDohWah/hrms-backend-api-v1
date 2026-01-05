<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only authenticated users can update users
        // Additional permission checks are handled by middleware
        return auth()->check();
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // If "modules" is provided as a JSON string, decode it
        if ($this->has('modules') && is_string($this->input('modules'))) {
            $modulesString = $this->input('modules');
            $modulesArray = json_decode($modulesString, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($modulesArray)) {
                $this->merge(['modules' => $modulesArray]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'role' => 'nullable|string|exists:roles,name',

            // New permission system: modules with Read/Edit flags
            'modules' => 'nullable|array',
            'modules.*.read' => 'boolean',
            'modules.*.edit' => 'boolean',

            // Backward compatibility: old permission system
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',
        ];

        // Add password validation rules if password is provided
        if ($this->filled('password')) {
            $rules['password'] = 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/';
            $rules['password_confirmation'] = 'required|string';
        }

        return $rules;
    }

    /**
     * Get the custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            'role.exists' => 'Invalid role selected. Please choose a valid role.',
            'permissions.*.exists' => 'One or more permissions are invalid.',
            'modules.*.read' => 'Read permission must be a boolean value.',
            'modules.*.edit' => 'Edit permission must be a boolean value.',
        ];
    }
}
