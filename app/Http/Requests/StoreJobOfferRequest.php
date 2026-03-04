<?php

namespace App\Http\Requests;

use App\Enums\JobOfferAcceptanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobOfferRequest extends FormRequest
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
            'pass_probation_salary' => 'required|numeric|min:0',
            'acceptance_deadline' => 'required|date',
            'acceptance_status' => [
                'required',
                'string',
                Rule::enum(JobOfferAcceptanceStatus::class),
            ],
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
            'pass_probation_salary.required' => 'The pass-probation salary is required.',
            'pass_probation_salary.numeric' => 'The pass-probation salary must be a valid number.',
            'pass_probation_salary.min' => 'The pass-probation salary must be at least 0.',
            'acceptance_status.Illuminate\Validation\Rules\Enum' => 'The selected acceptance status is invalid.',
        ];
    }
}
