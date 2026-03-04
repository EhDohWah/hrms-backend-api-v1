<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexHolidayRequest extends FormRequest
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
            'search' => 'string|nullable|max:255',
            'year' => 'integer|nullable',
            'is_active' => 'boolean|nullable',
            'from' => 'date|nullable',
            'to' => 'date|nullable',
            'sort_by' => 'string|nullable|in:date_asc,date_desc,name_asc,name_desc,recently_added',
        ];
    }
}
