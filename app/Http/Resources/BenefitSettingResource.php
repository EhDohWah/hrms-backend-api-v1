<?php

namespace App\Http\Resources;

use App\Models\BenefitSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BenefitSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $categories = BenefitSetting::getCategories();

        return [
            'id' => $this->id,
            'setting_key' => $this->setting_key,
            'setting_value' => $this->setting_value,
            'setting_type' => $this->setting_type,
            'category' => $this->category,
            'category_label' => $categories[$this->category] ?? $this->category,
            'description' => $this->description,
            'effective_date' => $this->effective_date?->format('Y-m-d'),
            'is_active' => $this->is_active,
            'applies_to' => $this->applies_to,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Computed
            'formatted_value' => $this->getFormattedValue(),
            'status' => $this->is_active ? 'active' : 'inactive',
        ];
    }

    private function getFormattedValue(): string
    {
        return match ($this->setting_type) {
            'percentage' => $this->setting_value.'%',
            'numeric' => '฿'.number_format((float) $this->setting_value, 2),
            'boolean' => $this->setting_value ? 'Yes' : 'No',
            default => (string) $this->setting_value,
        };
    }
}
