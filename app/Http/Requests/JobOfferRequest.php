<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class JobOfferRequest extends FormRequest
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
            'date' => 'required|date',
            'candidate_name' => 'required|string',
            'position_name' => 'required|string',
            'probation_salary' => 'required|numeric|min:0',
            'post_probation_salary' => 'required|numeric|min:0',
            'acceptance_deadline' => 'required|date',
            'acceptance_status' => 'required|string',
            'note' => 'required|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'probation_salary.required' => 'The probation salary is required.',
            'probation_salary.numeric' => 'The probation salary must be a valid number.',
            'probation_salary.min' => 'The probation salary must be at least 0.',
            'post_probation_salary.required' => 'The post-probation salary is required.',
            'post_probation_salary.numeric' => 'The post-probation salary must be a valid number.',
            'post_probation_salary.min' => 'The post-probation salary must be at least 0.',
        ];
    }
}
