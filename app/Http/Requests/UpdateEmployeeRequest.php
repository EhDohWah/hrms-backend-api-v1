<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // You can add policies here
    }

    public function rules(): array
    {
        return [
            'subsidiary'  => ['required', 'string', Rule::in(['SMRU', 'BHF'])],
            'staff_id'    => [
                'required',
                'string',
                'max:50',
                Rule::unique('employees')
                    ->where(fn($query) =>
                        $query->where('subsidiary', $this->input('subsidiary'))
                    )
                    ->ignore($this->route('employee')->id), // Cleanest way!
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
