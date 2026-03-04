<?php

namespace App\Http\Requests\RecycleBin;

use Illuminate\Foundation\Http\FormRequest;

class BulkRestoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.model_type' => 'required|string',
            'items.*.id' => 'required|integer',
        ];
    }
}
