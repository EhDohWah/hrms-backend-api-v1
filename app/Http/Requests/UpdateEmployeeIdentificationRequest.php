<?php

namespace App\Http\Requests;

use App\Enums\IdentificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeIdentificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'identification_type' => ['sometimes', 'string', Rule::in(IdentificationType::values())],
            'identification_number' => ['sometimes', 'string', 'max:50'],
            'identification_issue_date' => ['nullable', 'date', 'before_or_equal:today'],
            'identification_expiry_date' => ['nullable', 'date', 'after:identification_issue_date'],
            'first_name_en' => ['nullable', 'string', 'max:255'],
            'last_name_en' => ['nullable', 'string', 'max:255'],
            'first_name_th' => ['nullable', 'string', 'max:255'],
            'last_name_th' => ['nullable', 'string', 'max:255'],
            'initial_en' => ['nullable', 'string', 'max:10'],
            'initial_th' => ['nullable', 'string', 'max:10'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'identification_type.in' => 'Invalid identification type. Allowed: '.implode(', ', IdentificationType::values()),
            'identification_expiry_date.after' => 'Expiry date must be after the issue date.',
        ];
    }
}
