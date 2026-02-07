<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchSafeDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => 'required|array|min:1',
            'ids.*' => 'required|integer',
            'reason' => 'nullable|string|max:500',
        ];
    }
}
