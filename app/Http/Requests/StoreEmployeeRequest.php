<?php

namespace App\Http\Requests;

use App\Enums\IdentificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'staff_id' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('employees')
                    ->whereNull('deleted_at'),
            ],

            // Basic information
            'initial_en' => ['nullable', 'string', 'max:10'],
            'initial_th' => ['nullable', 'string', 'max:10'],
            'first_name_en' => ['required', 'string', 'min:2', 'max:255'],
            'last_name_en' => ['nullable', 'string', 'max:255'],
            'first_name_th' => ['nullable', 'string', 'max:255'],
            'last_name_th' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', 'string', 'in:M,F'],
            'date_of_birth' => ['required', 'date', 'before:-18 years', 'after:1940-01-01'],
            'status' => ['required', 'string', 'in:Expats (Local),Local ID Staff,Local non ID Staff'],

            // Identification (stored in employee_identifications table via service)
            'identification_type' => ['nullable', 'string', Rule::in(IdentificationType::values())],
            'identification_number' => ['nullable', 'string', 'max:50', 'required_with:identification_type'],
            'identification_issue_date' => ['nullable', 'date'],
            'identification_expiry_date' => ['nullable', 'date', 'after_or_equal:identification_issue_date'],

            // Additional info
            'nationality' => ['nullable', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'social_security_number' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'driver_license_number' => ['nullable', 'string', 'max:100'],
            'mobile_phone' => ['nullable', 'string', 'max:20'],
            'current_address' => ['nullable', 'string'],
            'permanent_address' => ['nullable', 'string'],

            // Military status - stored as boolean
            'military_status' => ['nullable', 'boolean'],

            // Marital status
            'marital_status' => ['nullable', 'string', 'in:Single,Married,Divorced,Widowed'],
            'spouse_name' => ['nullable', 'string', 'max:100'],
            'spouse_phone_number' => ['nullable', 'string', 'max:20'],

            // Emergency contact
            'emergency_contact_person_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_person_relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact_person_phone' => ['nullable', 'string', 'max:20'],

            // Bank information
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_branch' => ['nullable', 'string', 'max:100'],
            'bank_account_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],

            // Parent information
            'father_name' => ['nullable', 'string', 'max:200'],
            'father_occupation' => ['nullable', 'string', 'max:200'],
            'father_phone_number' => ['nullable', 'string', 'max:20'],
            'mother_name' => ['nullable', 'string', 'max:200'],
            'mother_occupation' => ['nullable', 'string', 'max:200'],
            'mother_phone_number' => ['nullable', 'string', 'max:20'],

            // Tax: number of parents who meet Thai RD eligibility (age 60+, income < ฿30,000/yr)
            'eligible_parents_count' => ['nullable', 'integer', 'min:0', 'max:4'],

            // Remark
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
