<?php

namespace App\Http\Requests;

use App\Enums\ResignationAcknowledgementStatus;
use App\Models\Employment;
use App\Models\Resignation;
use Illuminate\Foundation\Http\FormRequest;

class StoreResignationRequest extends FormRequest
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
     * Note: acknowledgement_status is not accepted — new resignations always start as 'Pending'.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'department_id' => 'nullable|exists:departments,id',
            'position_id' => 'nullable|exists:positions,id',
            'resignation_date' => 'required|date|before_or_equal:today',
            'last_working_date' => 'required|date|after_or_equal:resignation_date',
            'reason' => 'required|string|max:50',
            'reason_details' => 'nullable|string',
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Please select an employee.',
            'employee_id.exists' => 'The selected employee does not exist.',
            'department_id.exists' => 'The selected department does not exist.',
            'position_id.exists' => 'The selected position does not exist.',
            'resignation_date.required' => 'Resignation date is required.',
            'resignation_date.before_or_equal' => 'Resignation date cannot be in the future.',
            'last_working_date.required' => 'Last working date is required.',
            'last_working_date.after_or_equal' => 'Last working date must be on or after resignation date.',
            'reason.required' => 'Please provide a reason for resignation.',
            'reason.max' => 'Reason cannot exceed 50 characters.',
        ];
    }

    /**
     * Get custom attribute names for validation errors.
     */
    public function attributes(): array
    {
        return [
            'employee_id' => 'employee',
            'department_id' => 'department',
            'position_id' => 'position',
            'resignation_date' => 'resignation date',
            'last_working_date' => 'last working date',
            'reason_details' => 'reason details',
        ];
    }

    /**
     * Configure the validator instance.
     *
     * Prevents duplicate resignations and validates that the employee
     * has an active employment record before allowing resignation creation.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! $this->employee_id) {
                return; // Let required validation handle this
            }

            // Prevent duplicate resignations (only one pending/acknowledged allowed per employee)
            $existing = Resignation::where('employee_id', $this->employee_id)
                ->whereIn('acknowledgement_status', [
                    ResignationAcknowledgementStatus::Pending->value,
                    ResignationAcknowledgementStatus::Acknowledged->value,
                ])
                ->first();

            if ($existing) {
                $status = strtolower($existing->acknowledgement_status->value);
                $validator->errors()->add('employee_id',
                    "This employee already has a {$status} resignation record."
                );

                return;
            }

            // Require active employment (end_date must be null)
            $employment = Employment::where('employee_id', $this->employee_id)
                ->whereNull('end_date')
                ->first();

            if (! $employment) {
                $validator->errors()->add('employee_id',
                    'This employee does not have an active employment record.'
                );
            }
        });
    }
}
