<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'subsidiary'  => ['required','string', Rule::in(['SMRU','BHF'])],
            'staff_id'    => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees')      // table name
                    ->where(fn($query) =>     // add a WHERE subsidiary = input('subsidiary')
                        $query->where('subsidiary', $this->input('subsidiary'))
                    ),
            ],
            'initial_en' => 'nullable|string|max:10',
            'initial_th' => 'nullable|string|max:10',
            'first_name_en' => 'required|string|max:255',
            'last_name_en' => 'nullable|string|max:255',
            'first_name_th' => 'nullable|string|max:255',
            'last_name_th' => 'nullable|string|max:255',
            'gender' => 'required|string|max:10',
            'date_of_birth' => 'required|date',
            'status' => 'required|string|in:Expats (Local),Local ID Staff,Local non ID Staff',
        ];
    }
}
