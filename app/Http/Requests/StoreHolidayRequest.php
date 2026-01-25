<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreHolidayRequest extends FormRequest
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
            'name' => 'required|string|max:255',
            'name_th' => 'nullable|string|max:255',
            'date' => 'required|date|unique:holidays,date',
            'year' => 'nullable|integer|min:2000|max:2100',
            'description' => 'nullable|string|max:500',
            'is_active' => 'nullable|boolean',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Auto-populate year from date if not provided
        if ($this->has('date') && ! $this->has('year')) {
            $this->merge([
                'year' => date('Y', strtotime($this->date)),
            ]);
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The holiday name is required.',
            'name.max' => 'The holiday name cannot exceed 255 characters.',
            'date.required' => 'The holiday date is required.',
            'date.date' => 'The holiday date must be a valid date.',
            'date.unique' => 'A holiday already exists on this date.',
            'year.integer' => 'The year must be a valid integer.',
            'year.min' => 'The year must be at least 2000.',
            'year.max' => 'The year cannot exceed 2100.',
        ];
    }
}
