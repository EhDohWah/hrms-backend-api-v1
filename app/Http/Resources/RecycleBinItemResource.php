<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RecycleBinItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Wraps Spatie's DeletedModel (from the deleted_models table).
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'model' => $this->model,
            'model_type' => $this->model_type,
            'original_id' => $this->original_id,
            'values' => $this->values,
            'deleted_time_ago' => $this->deleted_time_ago,
            'deleted_at' => $this->deleted_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
