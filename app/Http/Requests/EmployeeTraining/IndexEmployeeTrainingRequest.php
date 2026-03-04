<?php

namespace App\Http\Requests\EmployeeTraining;

use Illuminate\Foundation\Http\FormRequest;

class IndexEmployeeTrainingRequest extends FormRequest
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
            'filter_training_id' => ['integer', 'nullable'],
            'filter_employee_id' => ['integer', 'nullable'],
            'filter_status' => ['string', 'nullable'],
            'filter_training_title' => ['string', 'nullable'],
            'filter_organizer' => ['string', 'nullable'],
            'sort_by' => ['string', 'nullable', 'in:created_at,training_title,status,employee_name,start_date,end_date'],
            'sort_order' => ['string', 'nullable', 'in:asc,desc'],
        ];
    }
}
