<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexPositionRequest extends FormRequest
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
            'search' => ['sometimes', 'string', 'max:255'],
            'department_id' => ['sometimes', 'integer', 'exists:departments,id'],
            'is_active' => ['sometimes', 'boolean'],
            'is_manager' => ['sometimes', 'boolean'],
            'level' => ['sometimes', 'integer', 'min:1'],
            'sort_by' => ['sometimes', 'string', 'in:title,level,created_at,direct_reports_count'],
            'sort_direction' => ['sometimes', 'string', 'in:asc,desc'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
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
            'search.max' => 'Search term cannot exceed 255 characters.',
            'department_id.exists' => 'Selected department does not exist.',
            'level.min' => 'Level must be at least 1.',
            'sort_by.in' => 'Sort field must be one of: title, level, created_at, direct_reports_count.',
            'sort_direction.in' => 'Sort direction must be either asc or desc.',
            'per_page.min' => 'Items per page must be at least 1.',
            'per_page.max' => 'Items per page cannot exceed 100.',
        ];
    }
}
