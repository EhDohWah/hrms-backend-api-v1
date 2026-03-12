<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FullUpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $employeeId = $this->route('employee')->id;

        return [
            'staff_id' => [
                'required', 'string', 'min:3', 'max:50', 'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('employees', 'staff_id')->ignore($employeeId)->whereNull('deleted_at'),
            ],
            'initial_en' => ['nullable', 'string', 'max:10'],
            'initial_th' => ['nullable', 'string', 'max:10'],
            'first_name_en' => ['required', 'string', 'min:2', 'max:255'],
            'last_name_en' => ['nullable', 'string', 'max:255'],
            'first_name_th' => ['nullable', 'string', 'max:255'],
            'last_name_th' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', 'string', 'in:M,F'],
            'date_of_birth' => ['required', 'date', 'before:-18 years', 'after:1940-01-01'],
            'status' => ['required', 'string', 'in:Expats (Local),Local ID Staff,Local non ID Staff'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'social_security_number' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_branch' => ['nullable', 'string', 'max:100'],
            'bank_account_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'mobile_phone' => ['nullable', 'string', 'max:20'],
            'permanent_address' => ['nullable', 'string'],
            'current_address' => ['nullable', 'string'],
            'military_status' => ['nullable', 'boolean'],
            'marital_status' => ['nullable', 'string', 'in:Single,Married,Divorced,Widowed'],
            'spouse_name' => ['nullable', 'string', 'max:100'],
            'spouse_phone_number' => ['nullable', 'string', 'max:20'],
            'emergency_contact_person_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_person_relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact_person_phone' => ['nullable', 'string', 'max:20'],
            'father_name' => ['nullable', 'string', 'max:200'],
            'father_occupation' => ['nullable', 'string', 'max:200'],
            'father_phone_number' => ['nullable', 'string', 'max:20'],
            'mother_name' => ['nullable', 'string', 'max:200'],
            'mother_occupation' => ['nullable', 'string', 'max:200'],
            'mother_phone_number' => ['nullable', 'string', 'max:20'],
            'eligible_parents_count' => ['nullable', 'integer', 'min:0', 'max:4'],
            'driver_license_number' => ['nullable', 'string', 'max:100'],
            'remark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
