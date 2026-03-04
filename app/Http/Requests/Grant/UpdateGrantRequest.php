<?php

namespace App\Http\Requests\Grant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateGrantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('grants', 'code')
                    ->whereNull('deleted_at')
                    ->ignore($this->route('grant')),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'organization' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'end_date' => ['nullable', 'date'],
        ];
    }
}
