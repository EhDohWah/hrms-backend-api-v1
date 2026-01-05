<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProbationStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('action') && is_string($this->action)) {
            $this->merge([
                'action' => strtolower($this->action),
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:passed,failed'],
            'decision_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:500', 'required_if:action,failed'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'action.required' => 'Please select a probation decision.',
            'action.in' => 'Probation decision must be either passed or failed.',
            'decision_date.date' => 'Decision date must be a valid date.',
            'reason.required_if' => 'A reason is required when marking probation as failed.',
            'reason.max' => 'Reason may not exceed 500 characters.',
            'notes.max' => 'Notes may not exceed 2000 characters.',
        ];
    }
}
