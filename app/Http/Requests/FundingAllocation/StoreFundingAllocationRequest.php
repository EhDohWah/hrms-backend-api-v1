<?php

namespace App\Http\Requests\FundingAllocation;

use Illuminate\Foundation\Http\FormRequest;

class StoreFundingAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'employment_id' => 'required|exists:employments,id',
            'allocations' => 'required|array|min:1',
            'allocations.*.grant_item_id' => 'required|exists:grant_items,id',
            'allocations.*.fte' => 'required|numeric|min:0|max:100',
            'allocations.*.allocated_amount' => 'nullable|numeric|min:0',
            'replace_allocation_ids' => 'nullable|array',
            'replace_allocation_ids.*' => 'exists:employee_funding_allocations,id',
        ];
    }
}
