<?php

namespace App\Http\Requests\Lookup;

use Illuminate\Foundation\Http\FormRequest;

class StoreLookupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'max:255'],
            'value' => ['required', 'string', 'max:255'],
        ];
    }
}
