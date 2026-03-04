<?php

namespace App\Http\Requests\EmployeeTraining;

use Illuminate\Foundation\Http\FormRequest;

class StoreEmployeeTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'training_id' => ['required', 'integer', 'exists:trainings,id'],
            'status' => ['required', 'string', 'max:50'],
        ];
    }
}
