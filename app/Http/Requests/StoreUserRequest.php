<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only authenticated users can create users
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

        // Backward compatibility: If "permissions" is provided as a comma-separated string, convert it to an array
        if ($this->has('permissions') && ! is_array($this->input('permissions'))) {
            $permissionsString = $this->input('permissions');
            // Explode the string by comma and trim each permission
            $permissionsArray = array_map('trim', explode(',', $permissionsString));
            $this->merge(['permissions' => $permissionsArray]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255|unique:users,name',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]+$/',
            'password_confirmation' => 'required|string',
            'role' => 'required|string|exists:roles,name',

            // New permission system: modules with Read/Edit flags
            'modules' => 'nullable|array',
            'modules.*.read' => 'boolean',
            'modules.*.edit' => 'boolean',

            // Backward compatibility: old permission system
            'permissions' => 'nullable|array',
            'permissions.*' => 'string',

            'profile_picture' => 'nullable|image|max:2048', // 2MB max file size
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
            'password.regex' => 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.',
            'name.unique' => 'A user with this name already exists.',
            'email.unique' => 'A user with this email already exists.',
            'role.exists' => 'Invalid role selected. Please choose a valid role.',
            'profile_picture.image' => 'Profile picture must be an image file.',
            'profile_picture.max' => 'Profile picture must not exceed 2MB.',
            'modules.*.read' => 'Read permission must be a boolean value.',
            'modules.*.edit' => 'Edit permission must be a boolean value.',
        ];
    }
}
