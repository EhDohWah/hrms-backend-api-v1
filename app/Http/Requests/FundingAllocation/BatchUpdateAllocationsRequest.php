<?php

namespace App\Http\Requests\FundingAllocation;

use Illuminate\Foundation\Http\FormRequest;

class BatchUpdateAllocationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'required|integer|exists:employees,id',
            'employment_id' => 'required|integer|exists:employments,id',
            'updates' => 'nullable|array',
            'updates.*.id' => 'required|integer|exists:employee_funding_allocations,id',
            'updates.*.grant_item_id' => 'nullable|integer|exists:grant_items,id',
            'updates.*.fte' => 'nullable|numeric|min:1|max:100',
            'updates.*.status' => 'nullable|string|in:active,inactive',
            'creates' => 'nullable|array',
            'creates.*.grant_item_id' => 'required|integer|exists:grant_items,id',
            'creates.*.fte' => 'required|numeric|min:1|max:100',
            'deletes' => 'nullable|array',
            'deletes.*' => 'integer|exists:employee_funding_allocations,id',
        ];
    }
}
