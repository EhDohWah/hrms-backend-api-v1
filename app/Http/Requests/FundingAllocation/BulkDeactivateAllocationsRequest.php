<?php

namespace App\Http\Requests\FundingAllocation;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeactivateAllocationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'allocation_ids' => 'required|array|min:1',
            'allocation_ids.*' => 'integer|exists:employee_funding_allocations,id',
        ];
    }
}
