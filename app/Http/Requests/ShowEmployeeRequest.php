<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ShowEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Include the route {id} in the data to be validated.
     */
    public function validationData(): array
    {
        return array_merge(
            $this->all(),                  // existing input
            ['staff_id' => $this->route('staff_id')]   // add route parameter
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'staff_id' => 'required|digits:4|exists:employees,staff_id',
        ];
    }
}
