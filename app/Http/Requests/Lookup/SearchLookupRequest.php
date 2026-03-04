<?php

namespace App\Http\Requests\Lookup;

use Illuminate\Foundation\Http\FormRequest;

class SearchLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'types' => ['nullable', 'string', 'max:500'],
            'value' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'sort_by' => ['nullable', 'string', 'in:type,value,created_at,updated_at'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
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

    /**
     * Add custom validation: at least one search parameter is required.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (empty($this->search) && empty($this->types) && empty($this->value)) {
                $validator->errors()->add('search', 'At least one search parameter (search, types, or value) is required.');
            }
        });
    }
}
