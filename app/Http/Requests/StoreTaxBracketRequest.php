<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\TaxBracket;

class StoreTaxBracketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('tax.create') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'min_income' => 'required|numeric|min:0',
            'max_income' => 'nullable|numeric|gt:min_income',
            'tax_rate' => 'required|numeric|min:0|max:100',
            'bracket_order' => 'required|integer|min:1',
            'effective_year' => 'required|integer|min:2000|max:2100',
            'description' => 'nullable|string|max:255',
            'is_active' => 'boolean',
            'created_by' => 'nullable|string|max:100'
        ];
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return [
            'min_income.required' => 'Minimum income is required.',
            'min_income.numeric' => 'Minimum income must be a valid number.',
            'min_income.min' => 'Minimum income cannot be negative.',
            'max_income.numeric' => 'Maximum income must be a valid number.',
            'max_income.gt' => 'Maximum income must be greater than minimum income.',
            'tax_rate.required' => 'Tax rate is required.',
            'tax_rate.numeric' => 'Tax rate must be a valid number.',
            'tax_rate.min' => 'Tax rate cannot be negative.',
            'tax_rate.max' => 'Tax rate cannot exceed 100%.',
            'bracket_order.required' => 'Bracket order is required.',
            'bracket_order.integer' => 'Bracket order must be an integer.',
            'bracket_order.min' => 'Bracket order must be at least 1.',
            'effective_year.required' => 'Effective year is required.',
            'effective_year.integer' => 'Effective year must be a valid year.',
            'effective_year.min' => 'Effective year must be 2000 or later.',
            'effective_year.max' => 'Effective year must be 2100 or earlier.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Check for duplicate bracket order in the same year
            $existingBracket = TaxBracket::where('effective_year', $this->effective_year)
                ->where('bracket_order', $this->bracket_order)
                ->first();

            if ($existingBracket) {
                $validator->errors()->add(
                    'bracket_order',
                    'A tax bracket with this order already exists for the specified year.'
                );
            }

            // Validate bracket sequence logic
            if ($this->bracket_order > 1) {
                $previousBracket = TaxBracket::where('effective_year', $this->effective_year)
                    ->where('bracket_order', $this->bracket_order - 1)
                    ->first();

                if ($previousBracket && $previousBracket->max_income && $this->min_income <= $previousBracket->max_income) {
                    $validator->errors()->add(
                        'min_income',
                        'Minimum income must be greater than the maximum income of the previous bracket.'
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
            'min_income' => 'minimum income',
            'max_income' => 'maximum income',
            'tax_rate' => 'tax rate',
            'bracket_order' => 'bracket order',
            'effective_year' => 'effective year',
        ];
    }
}