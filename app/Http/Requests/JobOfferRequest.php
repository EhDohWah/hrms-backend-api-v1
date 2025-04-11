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
            'salary_detail' => 'required|string',
            'acceptance_deadline' => 'required|date',
            'acceptance_status' => 'required|string',
            'note' => 'required|string',
        ];
    }
}
