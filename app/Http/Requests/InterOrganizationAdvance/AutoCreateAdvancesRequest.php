<?php

namespace App\Http\Requests\InterOrganizationAdvance;

use Illuminate\Foundation\Http\FormRequest;

class AutoCreateAdvancesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payroll_period_date' => 'required|date',
            'dry_run' => 'boolean',
        ];
    }
}
