<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleResource extends JsonResource
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
            'display_name' => $this->display_name,
            'description' => $this->description,
            'icon' => $this->icon,
            'category' => $this->category,
            'route' => $this->route,
            'active_link' => $this->active_link,
            'parent_module' => $this->parent_module,
            'is_parent' => $this->is_parent,
            'read_permission' => $this->read_permission,
            'edit_permissions' => $this->edit_permissions,
            'order' => $this->order,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),

            // Relationships
            'children' => ModuleResource::collection($this->whenLoaded('children')),
            'active_children' => ModuleResource::collection($this->whenLoaded('activeChildren')),
            'parent' => new ModuleResource($this->whenLoaded('parent')),
        ];
    }
}
