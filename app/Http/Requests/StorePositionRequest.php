<?php

namespace App\Http\Requests;

use App\Models\Position;
use Illuminate\Foundation\Http\FormRequest;

class StorePositionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'reports_to_position_id' => [
                'nullable',
                'integer',
                'exists:positions,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $supervisor = Position::find($value);
                        if ($supervisor && ! $supervisor->is_active) {
                            $fail('Cannot report to an inactive position.');
                        }

                        // Check if supervisor is in the same department
                        if ($supervisor && $supervisor->department_id != $this->input('department_id')) {
                            $fail('Position cannot report to someone from a different department.');
                        }
                    }
                },
            ],
            'level' => ['sometimes', 'integer', 'min:1', 'max:10'],
            'is_manager' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Position title is required.',
            'title.max' => 'Position title cannot exceed 255 characters.',
            'department_id.required' => 'Department is required.',
            'department_id.exists' => 'Selected department does not exist.',
            'reports_to_position_id.exists' => 'Selected supervisor position does not exist.',
            'level.min' => 'Level must be at least 1.',
            'level.max' => 'Level cannot exceed 10.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'level' => $this->input('level', 1), // Default to level 1
            'is_manager' => $this->input('is_manager', false), // Default to false
            'is_active' => $this->input('is_active', true), // Default to true
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Additional validation: if reports_to_position_id is set, auto-calculate level
            if ($this->input('reports_to_position_id')) {
                $supervisor = Position::find($this->input('reports_to_position_id'));
                if ($supervisor) {
                    // Set level to supervisor's level + 1
                    $this->merge(['level' => $supervisor->level + 1]);
                }
            }

            // Validate hierarchy constraints
            if ($this->input('level') == 1 && $this->input('reports_to_position_id')) {
                $validator->errors()->add('level', 'Level 1 positions cannot report to another position.');
            }

            // Validate manager constraint for level 1
            if ($this->input('level') == 1 && ! $this->input('is_manager')) {
                $validator->errors()->add('is_manager', 'Level 1 positions must be managers.');
            }
        });
    }
}
