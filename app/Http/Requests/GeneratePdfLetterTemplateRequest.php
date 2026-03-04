<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GeneratePdfLetterTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'placeholders' => 'required|array',
            'placeholders.*' => 'nullable|string',
        ];
    }

    public function messages(): array
    {
        return [
            'placeholders.required' => 'Placeholder data is required for PDF generation.',
            'placeholders.array' => 'Placeholders must be an array of key-value pairs.',
        ];
    }
}
