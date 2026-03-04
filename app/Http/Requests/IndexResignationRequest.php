<?php

namespace App\Http\Requests;

use App\Enums\ResignationAcknowledgementStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexResignationRequest extends FormRequest
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
            'search' => 'nullable|string|max:255',
            'acknowledgement_status' => [
                'nullable',
                'string',
                Rule::enum(ResignationAcknowledgementStatus::class),
            ],
            'department_id' => 'nullable|exists:departments,id',
            'reason' => 'nullable|string|max:50',
            'sort_by' => 'nullable|in:resignation_date,last_working_date,acknowledgement_status,created_at',
            'sort_order' => 'nullable|in:asc,desc',
        ];
    }
}
