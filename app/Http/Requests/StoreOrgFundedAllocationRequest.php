<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrgFundedAllocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'grant_id' => ['required', 'integer', 'exists:grants,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'position_id' => [
                'required',
                'integer',
                'exists:positions,id',
                Rule::exists('positions', 'id')->where(function ($query) {
                    return $query->where('department_id', $this->department_id);
                }),
            ],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'grant_id.required' => 'Grant is required.',
            'grant_id.exists' => 'The selected grant does not exist.',
            'department_id.required' => 'Department is required.',
            'department_id.exists' => 'The selected department does not exist.',
            'position_id.required' => 'Position is required.',
            'position_id.exists' => 'The selected position does not exist or does not belong to the selected department.',
            'description.max' => 'Description cannot exceed 255 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'grant_id' => 'grant',
            'department_id' => 'department',
            'position_id' => 'position',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Additional validation: Check if position belongs to department
            if ($this->filled(['department_id', 'position_id'])) {
                $position = \App\Models\Position::find($this->position_id);
                if ($position && $position->department_id != $this->department_id) {
                    $validator->errors()->add('position_id', 'The selected position must belong to the selected department.');
                }
            }
        });
    }
}
