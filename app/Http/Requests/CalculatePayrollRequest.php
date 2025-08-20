<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CalculatePayrollRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('payroll.read') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'employee_id' => 'required|exists:employees,id',
            'gross_salary' => 'required|numeric|min:0|max:999999999.99',
            'pay_period_date' => 'required|date|before_or_equal:today',
            'tax_year' => 'nullable|integer|min:2000|max:2100',

            // Additional income validation
            'additional_income' => 'nullable|array|max:20',
            'additional_income.*.type' => 'required_with:additional_income|string|max:50',
            'additional_income.*.amount' => 'required_with:additional_income|numeric|min:0|max:999999.99',
            'additional_income.*.description' => 'nullable|string|max:255',

            // Additional deductions validation
            'additional_deductions' => 'nullable|array|max:20',
            'additional_deductions.*.type' => 'required_with:additional_deductions|string|max:50',
            'additional_deductions.*.amount' => 'required_with:additional_deductions|numeric|min:0|max:999999.99',
            'additional_deductions.*.description' => 'nullable|string|max:255',

            'save_payroll' => 'boolean',
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'employee_id.required' => 'Employee selection is required.',
            'employee_id.exists' => 'Selected employee does not exist.',
            'gross_salary.required' => 'Gross salary is required.',
            'gross_salary.numeric' => 'Gross salary must be a valid number.',
            'gross_salary.min' => 'Gross salary cannot be negative.',
            'gross_salary.max' => 'Gross salary amount is too large.',
            'pay_period_date.required' => 'Pay period date is required.',
            'pay_period_date.date' => 'Pay period date must be a valid date.',
            'pay_period_date.before_or_equal' => 'Pay period date cannot be in the future.',
            'tax_year.integer' => 'Tax year must be a valid year.',
            'tax_year.min' => 'Tax year must be 2000 or later.',
            'tax_year.max' => 'Tax year must be 2100 or earlier.',

            // Additional income messages
            'additional_income.array' => 'Additional income must be an array.',
            'additional_income.max' => 'Cannot have more than 20 additional income items.',
            'additional_income.*.type.required_with' => 'Income type is required when additional income is provided.',
            'additional_income.*.type.string' => 'Income type must be a string.',
            'additional_income.*.type.max' => 'Income type cannot exceed 50 characters.',
            'additional_income.*.amount.required_with' => 'Income amount is required when additional income is provided.',
            'additional_income.*.amount.numeric' => 'Income amount must be a valid number.',
            'additional_income.*.amount.min' => 'Income amount cannot be negative.',
            'additional_income.*.amount.max' => 'Income amount is too large.',
            'additional_income.*.description.string' => 'Income description must be a string.',
            'additional_income.*.description.max' => 'Income description cannot exceed 255 characters.',

            // Additional deductions messages
            'additional_deductions.array' => 'Additional deductions must be an array.',
            'additional_deductions.max' => 'Cannot have more than 20 additional deduction items.',
            'additional_deductions.*.type.required_with' => 'Deduction type is required when additional deductions are provided.',
            'additional_deductions.*.type.string' => 'Deduction type must be a string.',
            'additional_deductions.*.type.max' => 'Deduction type cannot exceed 50 characters.',
            'additional_deductions.*.amount.required_with' => 'Deduction amount is required when additional deductions are provided.',
            'additional_deductions.*.amount.numeric' => 'Deduction amount must be a valid number.',
            'additional_deductions.*.amount.min' => 'Deduction amount cannot be negative.',
            'additional_deductions.*.amount.max' => 'Deduction amount is too large.',
            'additional_deductions.*.description.string' => 'Deduction description must be a string.',
            'additional_deductions.*.description.max' => 'Deduction description cannot exceed 255 characters.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate that the pay period date is not too far in the past
            if ($this->pay_period_date) {
                $payPeriodDate = new \DateTime($this->pay_period_date);
                $twoYearsAgo = new \DateTime('-2 years');

                if ($payPeriodDate < $twoYearsAgo) {
                    $validator->errors()->add(
                        'pay_period_date',
                        'Pay period date cannot be more than 2 years in the past.'
                    );
                }
            }

            // Validate total additional income is reasonable
            if ($this->additional_income) {
                $totalAdditionalIncome = array_sum(array_column($this->additional_income, 'amount'));
                if ($totalAdditionalIncome > $this->gross_salary * 5) {
                    $validator->errors()->add(
                        'additional_income',
                        'Total additional income seems unusually high compared to gross salary.'
                    );
                }
            }

            // Validate total additional deductions don't exceed income
            if ($this->additional_deductions && $this->gross_salary) {
                $totalAdditionalDeductions = array_sum(array_column($this->additional_deductions, 'amount'));
                $totalIncome = $this->gross_salary + array_sum(array_column($this->additional_income ?? [], 'amount'));

                if ($totalAdditionalDeductions > $totalIncome) {
                    $validator->errors()->add(
                        'additional_deductions',
                        'Total additional deductions cannot exceed total income.'
                    );
                }
            }

            // Validate tax year matches pay period year if both are provided
            if ($this->tax_year && $this->pay_period_date) {
                $payPeriodYear = date('Y', strtotime($this->pay_period_date));
                if ($this->tax_year != $payPeriodYear) {
                    $validator->errors()->add(
                        'tax_year',
                        'Tax year should match the year of the pay period date.'
                    );
                }
            }
        });
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'employee_id' => 'employee',
            'gross_salary' => 'gross salary',
            'pay_period_date' => 'pay period date',
            'tax_year' => 'tax year',
            'additional_income' => 'additional income',
            'additional_deductions' => 'additional deductions',
            'save_payroll' => 'save payroll option',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default tax year to the year of pay period date if not provided
        if (! $this->has('tax_year') && $this->has('pay_period_date')) {
            $this->merge([
                'tax_year' => date('Y', strtotime($this->pay_period_date)),
            ]);
        }

        // Ensure save_payroll is boolean
        if ($this->has('save_payroll')) {
            $this->merge([
                'save_payroll' => filter_var($this->save_payroll, FILTER_VALIDATE_BOOLEAN),
            ]);
        }
    }
}
