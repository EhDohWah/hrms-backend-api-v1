<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class FilterEmployeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'staff_id' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'in:Expats (Local),Local ID Staff,Local non ID Staff'],
            'organization' => ['nullable', 'in:SMRU,BHF'],
        ];
    }
}
