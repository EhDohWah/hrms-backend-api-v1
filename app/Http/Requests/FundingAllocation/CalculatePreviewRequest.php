<?php

namespace App\Http\Requests\FundingAllocation;

use Illuminate\Foundation\Http\FormRequest;

class CalculatePreviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'employment_id' => 'required|exists:employments,id',
            'fte' => 'required|numeric|min:0|max:100',
            'effective_date' => 'nullable|date',
        ];
    }
}
