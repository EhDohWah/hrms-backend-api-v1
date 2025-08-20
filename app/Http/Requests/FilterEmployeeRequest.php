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
            'staff_id' => ['nullable', 'string', 'max:10'],
            'status' => ['nullable', 'in:Expats,Local ID,Local non ID'],
            'id_type' => ['nullable', 'in:Passport,ThaiID,10YearsID,Other'],
            'subsidiary' => ['nullable', 'in:SMRU,BHF'],
            'gender' => ['nullable', 'in:Male,Female'],
            'date_of_birth' => ['nullable', 'date'],
            'sort_by' => ['nullable', 'in:subsidiary,staff_id,initials,first_name_en,last_name_en,gender,date_of_birth,age,status,id_type,id_number,social_security_number,tax_number,mobile_phone'],
            'sort_order' => ['nullable', 'in:asc,desc'],
        ];
    }
}
