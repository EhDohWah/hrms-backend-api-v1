<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexTravelRequestRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'search' => 'string|nullable|max:255',
            'filter_department' => 'string|nullable',
            'filter_destination' => 'string|nullable',
            'filter_transportation' => 'string|nullable|in:smru_vehicle,public_transportation,air,other',
            'sort_by' => 'string|nullable|in:start_date,destination,employee_name,department,created_at',
            'sort_order' => 'string|nullable|in:asc,desc',
        ];
    }
}
