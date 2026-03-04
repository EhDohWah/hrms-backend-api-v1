<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexAttendanceRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            'filter_employee_id' => ['nullable', 'integer'],
            'filter_status' => ['nullable', 'string'],
            'filter_date_from' => ['nullable', 'date'],
            'filter_date_to' => ['nullable', 'date'],
            'sort_by' => ['nullable', 'string', 'in:date,employee_name,clock_in,clock_out,status,total_hours,created_at'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing([
            'sort_by' => 'date',
            'sort_order' => 'desc',
            'per_page' => 10,
        ]);
    }
}
