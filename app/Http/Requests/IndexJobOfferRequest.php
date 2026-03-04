<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexJobOfferRequest extends FormRequest
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
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'search' => 'nullable|string|max:255',
            'filter_position' => 'nullable|string',
            'filter_status' => 'nullable|string',
            'sort_by' => 'nullable|in:job_offer_id,candidate_name,position_name,date,status,created_at',
            'sort_order' => 'nullable|in:asc,desc',
        ];
    }
}
