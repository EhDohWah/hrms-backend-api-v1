<?php

namespace App\Http\Requests\InterOrganizationAdvance;

use Illuminate\Foundation\Http\FormRequest;

class ListInterOrgAdvancesRequest extends FormRequest
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
            'from_organization' => 'string|nullable',
            'to_organization' => 'string|nullable',
            'status' => 'string|nullable|in:pending,settled',
            'date_range' => 'string|nullable',
            'sort_by' => 'string|nullable|in:advance_date,amount,from_organization,to_organization',
            'sort_order' => 'string|nullable|in:asc,desc',
        ];
    }
}
