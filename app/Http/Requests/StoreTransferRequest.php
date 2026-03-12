<?php

namespace App\Http\Requests;

use App\Models\Employment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('transfer.create');
    }

    public function rules(): array
    {
        return [
            'employee_id' => ['required', 'exists:employees,id'],
            'to_organization' => ['required', 'string', Rule::in(['SMRU', 'BHF'])],
            'to_start_date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->employee_id) {
                return;
            }

            $employment = Employment::where('employee_id', $this->employee_id)
                ->whereNull('end_date')
                ->first();

            if (! $employment) {
                $validator->errors()->add('employee_id', 'This employee does not have an active employment record.');

                return;
            }

            if ($employment->organization === $this->to_organization) {
                $validator->errors()->add('to_organization', "Employee is already in {$this->to_organization}.");
            }
        });
    }

    public function messages(): array
    {
        return [
            'employee_id.required' => 'Employee is required.',
            'employee_id.exists' => 'Selected employee does not exist.',
            'to_organization.required' => 'Target organization is required.',
            'to_organization.in' => 'Organization must be SMRU or BHF.',
            'to_start_date.required' => 'Transfer date is required.',
        ];
    }
}
