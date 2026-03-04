<?php

namespace App\Http\Requests\ActivityLog;

use Illuminate\Foundation\Http\FormRequest;

class ListActivityLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'subject_type' => 'string|nullable',
            'subject_id' => 'integer|nullable',
            'user_id' => 'integer|nullable',
            'action' => 'string|nullable',
            'date_from' => 'date|nullable',
            'date_to' => 'date|nullable',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing([
            'per_page' => 20,
        ]);
    }
}
