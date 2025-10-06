<?php

namespace App\Http\Requests;

use App\Models\TravelRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTravelRequestRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'department_id' => 'nullable|exists:departments,id',
            'position_id' => [
                'nullable',
                'exists:positions,id',
                // If position is provided and department is provided, ensure position belongs to department
                Rule::when($this->filled(['department_id', 'position_id']), function () {
                    return Rule::exists('positions', 'id')->where(function ($query) {
                        $query->where('department_id', $this->department_id);
                    });
                }),
            ],
            'destination' => 'nullable|string|max:200',
            'start_date' => 'nullable|date|after_or_equal:'.now()->format('Y-m-d'),
            'to_date' => [
                'nullable',
                'date',
                // If both dates are provided, ensure to_date is after start_date
                Rule::when($this->filled(['start_date', 'to_date']), 'after:start_date'),
            ],
            'purpose' => 'nullable|string',
            'grant' => 'nullable|string|max:50',
            'transportation' => [
                'nullable',
                'string',
                Rule::in(TravelRequest::getTransportationOptions()),
            ],
            'transportation_other_text' => [
                'nullable',
                'string',
                'max:200',
                // Required when transportation is 'other'
                Rule::when($this->input('transportation') === 'other', 'required'),
            ],
            'accommodation' => [
                'nullable',
                'string',
                Rule::in(TravelRequest::getAccommodationOptions()),
            ],
            'accommodation_other_text' => [
                'nullable',
                'string',
                'max:200',
                // Required when accommodation is 'other'
                Rule::when($this->input('accommodation') === 'other', 'required'),
            ],
            'request_by_date' => 'nullable|date',
            'supervisor_approved' => 'nullable|boolean',
            'supervisor_approved_date' => 'nullable|date',
            'hr_acknowledged' => 'nullable|boolean',
            'hr_acknowledgement_date' => 'nullable|date',
            'remarks' => 'nullable|string',
            'created_by' => 'nullable|string|max:100',
            'updated_by' => 'nullable|string|max:100',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Employee is required.',
            'employee_id.exists' => 'Selected employee does not exist.',
            'department_id.exists' => 'Selected department does not exist.',
            'position_id.exists' => 'Selected position does not exist or does not belong to the selected department.',
            'start_date.after_or_equal' => 'Start date cannot be in the past.',
            'to_date.after' => 'End date must be after the start date.',
            'transportation.in' => 'Invalid transportation option. Please select from: SMRU vehicle, Public transportation, Air, or Other.',
            'transportation_other_text.required' => 'Please specify the transportation method when selecting "Other".',
            'transportation_other_text.max' => 'Transportation specification cannot exceed 200 characters.',
            'accommodation.in' => 'Invalid accommodation option. Please select from: SMRU arrangement, Self arrangement, or Other.',
            'accommodation_other_text.required' => 'Please specify the accommodation type when selecting "Other".',
            'accommodation_other_text.max' => 'Accommodation specification cannot exceed 200 characters.',
        ];
    }
}
