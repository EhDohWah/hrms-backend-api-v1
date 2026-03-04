<?php

namespace App\Http\Requests\EmployeeTraining;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeTrainingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['sometimes', 'required', 'integer', 'exists:employees,id'],
            'training_id' => ['sometimes', 'required', 'integer', 'exists:trainings,id'],
            'status' => ['sometimes', 'required', 'string', 'max:50'],
        ];
    }
}
