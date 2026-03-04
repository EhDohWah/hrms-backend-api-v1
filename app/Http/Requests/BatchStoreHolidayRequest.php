<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchStoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'holidays' => 'required|array|min:1',
            'holidays.*.name' => 'required|string|max:255',
            'holidays.*.name_th' => 'nullable|string|max:255',
            'holidays.*.date' => 'required|date|distinct',
            'holidays.*.description' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'holidays.required' => 'At least one holiday is required.',
            'holidays.*.name.required' => 'Each holiday must have a name.',
            'holidays.*.date.required' => 'Each holiday must have a date.',
            'holidays.*.date.distinct' => 'Duplicate dates are not allowed within the same batch.',
        ];
    }
}
