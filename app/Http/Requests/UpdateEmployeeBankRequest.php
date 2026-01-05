<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEmployeeBankRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_branch' => ['nullable', 'string', 'max:100'],
            'bank_account_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:100', 'regex:/^[0-9\-\s]*$/'],
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'bank_name.string' => 'Bank name must be a valid string.',
            'bank_name.max' => 'Bank name cannot exceed 100 characters.',
            'bank_branch.string' => 'Bank branch must be a valid string.',
            'bank_branch.max' => 'Bank branch cannot exceed 100 characters.',
            'bank_account_name.string' => 'Bank account name must be a valid string.',
            'bank_account_name.max' => 'Bank account name cannot exceed 100 characters.',
            'bank_account_number.string' => 'Bank account number must be a valid string.',
            'bank_account_number.max' => 'Bank account number cannot exceed 100 characters.',
            'bank_account_number.regex' => 'Bank account number can only contain numbers, dashes, and spaces.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // If any bank field is provided, ensure account name and number are also provided
            $bankFields = ['bank_name', 'bank_branch', 'bank_account_name', 'bank_account_number'];
            $providedFields = array_filter($bankFields, fn ($field) => $this->filled($field));

            // If at least one bank field is provided, require account name and number
            if (! empty($providedFields)) {
                if (! $this->filled('bank_account_name')) {
                    $validator->errors()->add('bank_account_name',
                        'Bank account name is required when providing bank information.'
                    );
                }

                if (! $this->filled('bank_account_number')) {
                    $validator->errors()->add('bank_account_number',
                        'Bank account number is required when providing bank information.'
                    );
                }
            }
        });
    }
}
