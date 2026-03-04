<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePayrollGrantAllocationRequest extends FormRequest
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
            'payroll_id' => 'sometimes|exists:payrolls,id',
            'employee_funding_allocation_id' => 'sometimes|exists:employee_funding_allocations,id',
            'grant_item_id' => 'sometimes|exists:grant_items,id',
            'grant_code' => 'nullable|string|max:255',
            'grant_name' => 'nullable|string|max:255',
            'budget_line_code' => 'nullable|string|max:255',
            'grant_position' => 'nullable|string|max:255',
            'fte' => 'sometimes|numeric|min:0|max:1',
            'allocated_amount' => 'sometimes|numeric|min:0',
            'salary_type' => 'nullable|string|max:255',
        ];
    }
}
