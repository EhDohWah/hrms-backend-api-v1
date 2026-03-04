<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexEmploymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'filter_organization' => 'string|nullable',
            'filter_site' => 'string|nullable',
            'filter_department' => 'string|nullable',
            'sort_by' => 'string|nullable|in:staff_id,employee_name,site,start_date',
            'sort_order' => 'string|nullable|in:asc,desc',
            'include_allocations' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'page.integer' => 'Page must be a valid integer.',
            'page.min' => 'Page must be at least 1.',
            'per_page.integer' => 'Per page must be a valid integer.',
            'per_page.min' => 'Per page must be at least 1.',
            'per_page.max' => 'Per page must not exceed 100.',
            'sort_by.in' => 'Sort by must be one of: staff_id, employee_name, site, start_date.',
            'sort_order.in' => 'Sort order must be either asc or desc.',
        ];
    }
}
