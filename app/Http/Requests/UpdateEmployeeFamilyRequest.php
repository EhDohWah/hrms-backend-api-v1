<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeFamilyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Father
            'father_name' => ['nullable', 'string', 'max:100'],
            'father_occupation' => ['nullable', 'string', 'max:100'],
            'father_phone' => ['nullable', 'string', 'max:20'],

            // Mother
            'mother_name' => ['nullable', 'string', 'max:100'],
            'mother_occupation' => ['nullable', 'string', 'max:100'],
            'mother_phone' => ['nullable', 'string', 'max:20'],

            // Tax: number of parents who meet Thai RD eligibility (age 60+, income < ฿30,000/yr)
            // Must be set explicitly by admin — max 4 (2 own + 2 spouse's parents under Thai law)
            'eligible_parents_count' => ['nullable', 'integer', 'min:0', 'max:4'],

            // Emergency contact
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:50'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
        ];
    }
}
