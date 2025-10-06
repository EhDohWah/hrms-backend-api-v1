<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeEducationRequest extends FormRequest
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
            'employee_id' => 'sometimes|required|exists:employees,id',
            'school_name' => 'sometimes|required|string|max:100',
            'degree' => 'sometimes|required|string|max:100',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after_or_equal:start_date',
            'created_by' => 'sometimes|required|string|max:100',
            'updated_by' => 'sometimes|required|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Employee ID is required',
            'employee_id.exists' => 'Selected employee does not exist',
            'school_name.required' => 'School name is required',
            'school_name.max' => 'School name cannot exceed 100 characters',
            'degree.required' => 'Degree is required',
            'degree.max' => 'Degree cannot exceed 100 characters',
            'start_date.required' => 'Start date is required',
            'start_date.date' => 'Start date must be a valid date',
            'end_date.required' => 'End date is required',
            'end_date.date' => 'End date must be a valid date',
            'end_date.after_or_equal' => 'End date must be on or after the start date',
            'created_by.required' => 'Created by field is required',
            'created_by.max' => 'Created by field cannot exceed 100 characters',
            'updated_by.required' => 'Updated by field is required',
            'updated_by.max' => 'Updated by field cannot exceed 100 characters',
        ];
    }
}
