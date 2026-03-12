<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollPolicySettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'policy_key' => $this->policy_key,
            'policy_value' => $this->policy_value,
            'setting_type' => $this->setting_type,
            'category' => $this->category,
            'description' => $this->description,
            'effective_date' => $this->effective_date?->format('Y-m-d'),
            'is_active' => $this->is_active,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Computed
            'status' => $this->is_active ? 'active' : 'inactive',
        ];
    }
}
