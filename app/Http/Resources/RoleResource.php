<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoleResource extends JsonResource
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
            'name' => $this->name,
            'display_name' => $this->getDisplayName(),
            'guard_name' => $this->guard_name,
            'is_protected' => $this->isProtected(),
            'users_count' => $this->when($this->users_count !== null, $this->users_count ?? 0),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),

            // Include permissions if loaded
            'permissions' => $this->when(
                $this->relationLoaded('permissions'),
                fn () => PermissionResource::collection($this->permissions)
            ),
        ];
    }

    /**
     * Convert role slug to human-readable display name
     */
    private function getDisplayName(): string
    {
        return match ($this->name) {
            'admin' => 'System Administrator',
            'hr-manager' => 'HR Manager',
            'hr-assistant-senior' => 'Senior HR Assistant',
            'hr-assistant' => 'HR Assistant',
            'hr-assistant-junior-senior' => 'Senior HR Junior Assistant',
            'hr-assistant-junior' => 'HR Junior Assistant',
            default => ucwords(str_replace('-', ' ', $this->name)),
        };
    }

    /**
     * Check if role is a protected system role
     */
    private function isProtected(): bool
    {
        return in_array($this->name, ['admin', 'hr-manager']);
    }
}
