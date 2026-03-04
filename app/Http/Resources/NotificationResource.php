<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Uses Laravel's built-in DatabaseNotification model.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->data;

        return [
            'id' => $this->id,
            'type' => $this->type,
            'data' => $data,
            'message' => $data['message'] ?? null,
            'category' => $data['category'] ?? $this->category ?? 'general',
            'category_label' => $data['category_label'] ?? null,
            'category_icon' => $data['category_icon'] ?? null,
            'category_color' => $data['category_color'] ?? null,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
