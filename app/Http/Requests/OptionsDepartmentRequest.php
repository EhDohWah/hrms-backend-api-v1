<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request for retrieving department options (dropdown/select lists).
 */
class OptionsDepartmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'order_by' => ['sometimes', 'string', 'in:name,created_at'],
            'order_direction' => ['sometimes', 'string', 'in:asc,desc'],
        ];
    }
}
