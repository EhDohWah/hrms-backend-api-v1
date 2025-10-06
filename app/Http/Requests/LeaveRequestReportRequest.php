<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LeaveRequestReportRequest extends FormRequest
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
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'work_location' => 'required|string|max:255|exists:work_locations,name',
            'department' => 'required|string|max:255|exists:departments,name',
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'start_date.required' => 'Start date is required.',
            'start_date.date_format' => 'Start date must be in YYYY-MM-DD format.',
            'end_date.required' => 'End date is required.',
            'end_date.date_format' => 'End date must be in YYYY-MM-DD format.',
            'end_date.after_or_equal' => 'End date must be after or equal to start date.',
            'work_location.required' => 'Work location is required.',
            'work_location.max' => 'Work location name must not exceed 255 characters.',
            'work_location.exists' => 'The selected work location does not exist.',
            'department.required' => 'Department is required.',
            'department.max' => 'Department name must not exceed 255 characters.',
            'department.exists' => 'The selected department does not exist.',
        ];
    }
}
