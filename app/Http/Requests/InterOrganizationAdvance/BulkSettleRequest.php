<?php

namespace App\Http\Requests\InterOrganizationAdvance;

use Illuminate\Foundation\Http\FormRequest;

class BulkSettleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'advance_ids' => 'required|array|min:1',
            'advance_ids.*' => 'integer|exists:inter_organization_advances,id',
            'settlement_date' => 'required|date',
            'notes' => 'nullable|string|max:500',
        ];
    }
}
