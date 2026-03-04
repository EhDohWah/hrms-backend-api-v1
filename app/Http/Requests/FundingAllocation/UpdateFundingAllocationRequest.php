<?php

namespace App\Http\Requests\FundingAllocation;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFundingAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employee_id' => 'sometimes|exists:employees,id',
            'employment_id' => 'sometimes|exists:employments,id',
            'grant_item_id' => 'required|exists:grant_items,id',
            'grant_id' => 'nullable',
            'fte' => 'sometimes|numeric|min:0|max:100',
            'allocated_amount' => 'nullable|numeric|min:0',
        ];
    }
}
