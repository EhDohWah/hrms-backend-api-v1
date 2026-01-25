<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FilterEmployeeRequest extends FormRequest
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
            'per_page' => 'required|integer|min:1',
            'staff_id' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:Expats (Local),Local ID Staff,Local non ID Staff'],
            'identification_type' => ['nullable', 'in:10YearsID,BurmeseID,CI,Borderpass,ThaiID,Passport,Other'],
            'organization' => ['nullable', 'in:SMRU,BHF'],
            'gender' => ['nullable', 'in:M,F'],
            'date_of_birth' => ['nullable', 'date'],
            'sort_by' => ['nullable', 'in:organization,staff_id,initials,first_name_en,last_name_en,gender,date_of_birth,age,status,identification_type,identification_number,social_security_number,tax_number,mobile_phone'],
            'sort_order' => ['nullable', 'in:asc,desc'],
        ];
    }
}
