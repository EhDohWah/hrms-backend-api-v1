<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxBracketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'min_income' => $this->min_income,
            'max_income' => $this->max_income,
            'tax_rate' => $this->tax_rate,
            'bracket_order' => $this->bracket_order,
            'effective_year' => $this->effective_year,
            'is_active' => $this->is_active,
            'description' => $this->description,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Computed attributes
            'income_range' => $this->income_range,
            'formatted_rate' => $this->formatted_rate,

            // Conditional attributes
            'can_edit' => $this->when($request->user()?->can('tax.update'), true),
            'can_delete' => $this->when($request->user()?->can('tax.delete'), true),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'meta' => [
                'tax_calculation_url' => route('api.tax-brackets.calculate', ['income' => ':income']),
                'documentation' => 'Use the tax_rate as percentage (e.g., 5 for 5%)',
            ],
        ];
    }
}
