<?php

namespace App\Http\Requests\Module;

use Illuminate\Foundation\Http\FormRequest;

class IndexModuleRequest extends FormRequest
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
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'sort_by' => ['nullable', 'string', 'in:order,name,display_name,category,created_at,updated_at'],
            'sort_order' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing([
            'page' => 1,
            'per_page' => 10,
            'sort_by' => 'order',
            'sort_order' => 'asc',
        ]);
    }
}
