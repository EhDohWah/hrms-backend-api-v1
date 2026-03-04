<?php

namespace App\Http\Requests\Training;

use Illuminate\Foundation\Http\FormRequest;

class IndexTrainingRequest extends FormRequest
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
            'filter_organizer' => 'string|nullable',
            'filter_title' => 'string|nullable',
            'sort_by' => 'string|nullable|in:title,organizer,start_date,end_date,created_at',
            'sort_order' => 'string|nullable|in:asc,desc',
        ];
    }
}
