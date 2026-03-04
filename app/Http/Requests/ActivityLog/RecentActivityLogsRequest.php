<?php

namespace App\Http\Requests\ActivityLog;

use Illuminate\Foundation\Http\FormRequest;

class RecentActivityLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'limit' => 'integer|min:1|max:100',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->mergeIfMissing([
            'limit' => 50,
        ]);
    }
}
