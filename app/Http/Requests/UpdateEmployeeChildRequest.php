<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeChildRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'sometimes|required|integer|exists:employees,id',
            'name' => 'sometimes|required|string|max:100',
            'date_of_birth' => 'sometimes|required|date',
        ];
    }
}
