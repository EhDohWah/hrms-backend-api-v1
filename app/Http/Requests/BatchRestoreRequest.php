<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchRestoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'deletion_keys' => 'required|array|min:1',
            'deletion_keys.*' => 'required|string|max:40',
        ];
    }
}
