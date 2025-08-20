<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxSettingResource extends JsonResource
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
            'setting_key' => $this->setting_key,
            'setting_value' => $this->setting_value,
            'setting_type' => $this->setting_type,
            'description' => $this->description,
            'effective_year' => $this->effective_year,
            'is_selected' => $this->is_selected,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Computed attributes
            'formatted_value' => $this->getFormattedValue(),
            'setting_category' => $this->getSettingCategory(),
            'status' => $this->is_selected ? 'enabled' : 'disabled',

            // Conditional attributes
            'can_edit' => $this->when($request->user()?->can('tax.update'), true),
            'can_delete' => $this->when($request->user()?->can('tax.delete'), true),
        ];
    }

    /**
     * Get formatted value based on setting type
     */
    private function getFormattedValue(): string
    {
        switch ($this->setting_type) {
            case 'RATE':
                return $this->setting_value.'%';
            case 'DEDUCTION':
            case 'LIMIT':
                return '฿'.number_format($this->setting_value, 2);
            default:
                return (string) $this->setting_value;
        }
    }

    /**
     * Get setting category for better organization
     */
    private function getSettingCategory(): string
    {
        $categories = [
            'PERSONAL_ALLOWANCE' => 'Personal Deductions',
            'SPOUSE_ALLOWANCE' => 'Personal Deductions',
            'CHILD_ALLOWANCE' => 'Personal Deductions',
            'PERSONAL_EXPENSE_RATE' => 'Expense Deductions',
            'PERSONAL_EXPENSE_MAX' => 'Expense Deductions',
            'SSF_RATE' => 'Social Security',
            'SSF_MAX_MONTHLY' => 'Social Security',
            'SSF_MAX_YEARLY' => 'Social Security',
            'PF_MIN_RATE' => 'Provident Fund',
            'PF_MAX_RATE' => 'Provident Fund',
        ];

        return $categories[$this->setting_key] ?? 'Other';
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
                'available_types' => ['DEDUCTION', 'RATE', 'LIMIT'],
                'value_retrieval_url' => route('api.tax-settings.value', ['key' => ':key']),
                'documentation' => 'Setting values are stored as decimal numbers. Use formatted_value for display.',
            ],
        ];
    }
}
