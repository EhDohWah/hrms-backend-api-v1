<?php

namespace App\Http\Requests;

use App\Enums\IdentificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeIdentificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'identification_type' => ['required', 'string', Rule::in(IdentificationType::values())],
            'identification_number' => ['required', 'string', 'max:50'],
            'identification_issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'identification_expiry_date' => ['nullable', 'date', 'after:identification_issue_date'],
            'first_name_en' => ['nullable', 'string', 'max:255'],
            'last_name_en' => ['nullable', 'string', 'max:255'],
            'first_name_th' => ['nullable', 'string', 'max:255'],
            'last_name_th' => ['nullable', 'string', 'max:255'],
            'initial_en' => ['nullable', 'string', 'max:10'],
            'initial_th' => ['nullable', 'string', 'max:10'],
            'is_primary' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Please select an employee.',
            'employee_id.exists' => 'The selected employee does not exist.',
            'identification_type.required' => 'Identification type is required.',
            'identification_type.in' => 'Invalid identification type. Allowed: '.implode(', ', IdentificationType::values()),
            'identification_number.required' => 'Identification number is required.',
            'identification_expiry_date.after' => 'Expiry date must be after the issue date.',
        ];
    }
}
