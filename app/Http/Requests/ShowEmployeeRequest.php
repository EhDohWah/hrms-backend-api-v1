<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Include the route {staff_id} in the data to be validated.
     */
    public function validationData(): array
    {
        return array_merge(
            $this->all(),
            ['staff_id' => $this->route('staff_id')]
        );
    }

    public function rules(): array
    {
        return [
            'staff_id' => ['required', 'digits:4', 'exists:employees,staff_id'],
        ];
    }
}
