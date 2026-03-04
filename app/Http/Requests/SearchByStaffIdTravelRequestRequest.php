<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchByStaffIdTravelRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:50',
        ];
    }

    /**
     * Validate the staff ID route parameter.
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $staffId = $this->route('staffId');

                if (empty($staffId) || ! is_string($staffId)) {
                    $validator->errors()->add('staffId', 'Staff ID is required and must be a valid string.');
                }
            },
        ];
    }
}
