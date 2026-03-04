<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;

class ExportEmployeesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization' => ['nullable', 'string', 'in:SMRU,BHF'],
            'status' => ['nullable', 'string', 'in:Expats (Local),Local ID Staff,Local non ID Staff'],
        ];
    }
}
