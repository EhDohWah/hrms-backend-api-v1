<?php

namespace App\Http\Requests\Employee;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('employees.update');
    }

    public function rules(): array
    {
        return [
            'new_organization' => ['required', 'string', Rule::in(['SMRU', 'BHF'])],
            'effective_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $employee = $this->route('employee');
            $currentOrg = $employee->employment?->organization;

            if ($this->new_organization === $currentOrg) {
                $validator->errors()->add(
                    'new_organization',
                    "Employee is already in {$currentOrg}."
                );
            }

            if (! $employee->employment || $employee->employment->end_date) {
                $validator->errors()->add(
                    'new_organization',
                    'Employee does not have an active employment record.'
                );
            }
        });
    }

    public function messages(): array
    {
        return [
            'new_organization.required' => 'Target organization is required.',
            'new_organization.in' => 'Organization must be SMRU or BHF.',
            'effective_date.required' => 'Effective date is required.',
        ];
    }
}
