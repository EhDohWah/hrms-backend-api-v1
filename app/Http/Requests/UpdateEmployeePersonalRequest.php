<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeePersonalRequest extends FormRequest
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
            'id' => 'required|exists:employees,id',
            'staff_id' => 'nullable|string|max:50',
            'mobile_phone' => 'required|string|max:20',
            'nationality' => 'required|string|max:50',
            'social_security_number' => 'nullable|string|max:50',
            'tax_number' => 'nullable|string|max:50',
            'religion' => 'required|string|max:100',
            'marital_status' => 'required|string|in:Single,Married,Divorced,Widowed',
            'spouse_name' => 'nullable|string|max:255',
            'spouse_phone_number' => 'nullable|string|max:20',
            'languages' => 'nullable|array',
            'languages.*' => 'string|max:30',
            'current_address' => 'required|string',
            'permanent_address' => 'required|string',
            'identification_type' => 'nullable|string|in:10YearsID,BurmeseID,CI,Borderpass,ThaiID,Passport,Other',
            'identification_number' => 'nullable|string|max:50|required_with:identification_type',
            // Support legacy nested format for backward compatibility
            'employee_identification' => 'nullable|array',
            'employee_identification.id_type' => 'nullable|string|max:30',
            'employee_identification.document_number' => 'nullable|string|max:50',
        ];
    }
}
