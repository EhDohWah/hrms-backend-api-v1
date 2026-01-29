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
            // Organization - required, SMRU or BHF only
            'organization' => ['required', 'string', Rule::in(['SMRU', 'BHF'])],

            // Staff ID - unique per organization (ignore current employee)
            'staff_id' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'regex:/^[A-Za-z0-9-]+$/',
                Rule::unique('employees')
                    ->where(fn ($query) => $query->where('organization', $this->input('organization')))
                    ->ignore($this->route('employee')->id),
            ],

            // Basic information
            'initial_en' => 'nullable|string|max:10',
            'initial_th' => 'nullable|string|max:10',
            'first_name_en' => 'required|string|min:2|max:255',
            'last_name_en' => 'nullable|string|max:255',
            'first_name_th' => 'nullable|string|max:255',
            'last_name_th' => 'nullable|string|max:255',
            'gender' => 'required|string|in:M,F',
            'date_of_birth' => 'required|date|before:-18 years|after:1940-01-01',
            'status' => 'required|string|in:Expats (Local),Local ID Staff,Local non ID Staff',

            // Identification - direct columns (not separate table)
            'identification_type' => 'nullable|string|in:10YearsID,BurmeseID,CI,Borderpass,ThaiID,Passport,Other',
            'identification_number' => 'nullable|string|max:50|required_with:identification_type',
            'identification_issue_date' => 'nullable|date|before_or_equal:today',
            'identification_expiry_date' => 'nullable|date|after:identification_issue_date',

            // Military status - stored as boolean
            'military_status' => 'nullable|boolean',
        ];
    }
}
