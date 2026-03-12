<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'staff_id' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('employees')
                    ->ignore($this->route('employee')->id)
                    ->whereNull('deleted_at'),
            ],

            // Basic information
            'initial_en' => ['nullable', 'string', 'max:10'],
            'initial_th' => ['nullable', 'string', 'max:10'],
            'first_name_en' => ['required', 'string', 'min:2', 'max:255'],
            'last_name_en' => ['nullable', 'string', 'max:255'],
            'first_name_th' => ['nullable', 'string', 'max:255'],
            'last_name_th' => ['nullable', 'string', 'max:255'],
            'gender' => ['required', 'string', 'in:M,F'],
            'date_of_birth' => ['required', 'date', 'before:-18 years', 'after:1940-01-01'],
            'status' => ['required', 'string', 'in:Expats (Local),Local ID Staff,Local non ID Staff'],

            // Military status - stored as boolean
            'military_status' => ['nullable', 'boolean'],
        ];
    }
}
