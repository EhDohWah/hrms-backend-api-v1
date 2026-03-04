<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class ListEmployeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['integer', 'min:1'],
            'per_page' => ['integer', 'min:1', 'max:100'],
            'search' => ['string', 'nullable', 'max:255'],
            'filter_organization' => ['string', 'nullable'],
            'filter_status' => ['string', 'nullable'],
            'filter_gender' => ['string', 'nullable'],
            'filter_age' => ['integer', 'nullable'],
            'filter_identification_type' => ['string', 'nullable'],
            'filter_staff_id' => ['string', 'nullable'],
            'sort_by' => ['string', 'nullable', 'in:organization,staff_id,first_name_en,last_name_en,gender,date_of_birth,status,age,identification_type'],
            'sort_order' => ['string', 'nullable', 'in:asc,desc'],
        ];
    }
}
