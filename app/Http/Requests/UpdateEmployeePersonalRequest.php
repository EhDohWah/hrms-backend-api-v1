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
            'social_security_number' => 'nullable|string|max:30',
            'tax_number' => 'nullable|string|max:30',
            'religion' => 'required|string|max:50',
            'marital_status' => 'required|string|max:20',
            'spouse_name' => 'nullable|string|max:255',
            'spouse_phone_number' => 'nullable|string|max:20',
            'languages' => 'nullable|array',
            'languages.*' => 'string|max:30',
            'current_address' => 'required|string',
            'permanent_address' => 'required|string',
            'employee_identification' => 'required|array',
            'employee_identification.id_type' => 'required|string|max:30',
            'employee_identification.document_number' => 'required|string|max:50',
        ];
    }
}
