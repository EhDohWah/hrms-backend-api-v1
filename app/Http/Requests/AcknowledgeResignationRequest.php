<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcknowledgeResignationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'action' => 'required|in:acknowledge,reject',
        ];
    }

    public function messages(): array
    {
        return [
            'action.required' => 'An action (acknowledge or reject) is required.',
            'action.in' => 'The action must be either "acknowledge" or "reject".',
        ];
    }
}
