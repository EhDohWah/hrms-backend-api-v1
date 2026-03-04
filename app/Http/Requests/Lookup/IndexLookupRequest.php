<?php

namespace App\Http\Requests\Lookup;

use Illuminate\Foundation\Http\FormRequest;

class IndexLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'filter_type' => ['nullable', 'string'],
            'search' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'string', 'in:type,value,created_at,updated_at'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
            'grouped' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing([
            'page' => 1,
            'per_page' => 10,
            'sort_by' => 'type',
            'sort_order' => 'asc',
        ]);
    }
}
