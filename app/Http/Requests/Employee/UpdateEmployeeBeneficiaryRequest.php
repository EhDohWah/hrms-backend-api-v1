<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeBeneficiaryRequest extends FormRequest
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
            'employee_id' => ['sometimes', 'required', 'integer', 'exists:employees,id'],
            'beneficiary_name' => ['sometimes', 'required', 'string', 'max:255'],
            'beneficiary_relationship' => ['sometimes', 'required', 'string', 'max:100'],
            'phone_number' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }
}
