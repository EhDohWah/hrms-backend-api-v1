<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePayrollGrantAllocationRequest extends FormRequest
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
            'payroll_id' => 'required|exists:payrolls,id',
            'employee_funding_allocation_id' => 'required|exists:employee_funding_allocations,id',
            'grant_item_id' => 'required|exists:grant_items,id',
            'grant_code' => 'nullable|string|max:255',
            'grant_name' => 'nullable|string|max:255',
            'budget_line_code' => 'nullable|string|max:255',
            'grant_position' => 'nullable|string|max:255',
            'fte' => 'required|numeric|min:0|max:1',
            'allocated_amount' => 'required|numeric|min:0',
            'salary_type' => 'nullable|string|max:255',
        ];
    }
}
