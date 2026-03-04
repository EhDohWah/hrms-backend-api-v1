<?php

namespace App\Http\Requests\RecycleBin;

use Illuminate\Foundation\Http\FormRequest;

class BulkRestoreLegacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'restore_requests' => 'required|array',
            'restore_requests.*.model_class' => 'required_without:restore_requests.*.deleted_record_id|string',
            'restore_requests.*.original_id' => 'required_without:restore_requests.*.deleted_record_id|integer',
            'restore_requests.*.deleted_record_id' => 'required_without_all:restore_requests.*.model_class,restore_requests.*.original_id|integer|exists:deleted_models,id',
        ];
    }
}
