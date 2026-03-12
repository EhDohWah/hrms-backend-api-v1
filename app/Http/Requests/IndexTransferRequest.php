<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('transfer.read');
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'employee_id' => ['nullable', 'integer', 'exists:employees,id'],
            'from_organization' => ['nullable', 'string', 'in:SMRU,BHF'],
            'to_organization' => ['nullable', 'string', 'in:SMRU,BHF'],
        ];
    }
}
