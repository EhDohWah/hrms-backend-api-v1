<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for retrieving section department options (dropdown/select lists).
 */
class OptionsSectionDepartmentRequest extends FormRequest
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
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'search' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('is_active') && $this->input('is_active') !== null) {
            $this->merge([
                'is_active' => filter_var($this->input('is_active'), FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
