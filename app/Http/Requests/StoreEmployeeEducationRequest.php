<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeEducationRequest extends FormRequest
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
            'employee_id' => 'required|exists:employees,id',
            'school_name' => 'required|string|max:100',
            'degree' => 'required|string|max:100',
            'start_date' => 'required|date',
            'end_date' => 'required|date',
            'created_by' => 'required|string|max:100',
            'updated_by' => 'required|string|max:100',
        ];
    }
}
