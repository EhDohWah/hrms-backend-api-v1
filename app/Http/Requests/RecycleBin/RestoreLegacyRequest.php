<?php

namespace App\Http\Requests\RecycleBin;

use Illuminate\Foundation\Http\FormRequest;

class RestoreLegacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'model_class' => 'required_without:deleted_record_id|string',
            'original_id' => 'required_without:deleted_record_id|integer',
            'deleted_record_id' => 'required_without_all:model_class,original_id|integer|exists:deleted_models,id',
        ];
    }
}
