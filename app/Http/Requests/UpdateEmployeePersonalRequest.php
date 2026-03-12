<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeePersonalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'exists:employees,id'],
            'staff_id' => ['nullable', 'string', 'max:50'],
            'mobile_phone' => ['required', 'string', 'max:20'],
            'nationality' => ['required', 'string', 'max:50'],
            'social_security_number' => ['nullable', 'string', 'max:50'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'religion' => ['required', 'string', 'max:100'],
            'marital_status' => ['required', 'string', 'in:Single,Married,Divorced,Widowed'],
            'spouse_name' => ['nullable', 'string', 'max:255'],
            'spouse_phone_number' => ['nullable', 'string', 'max:20'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', 'max:30'],
            'current_address' => ['required', 'string'],
            'permanent_address' => ['required', 'string'],
        ];
    }
}
