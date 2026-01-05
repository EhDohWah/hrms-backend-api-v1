<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'email' => $this->email,
            'status' => $this->status,
            'profile_picture' => $this->profile_picture,
            'last_login_at' => $this->last_login_at,
            'last_login_ip' => $this->last_login_ip,
            'email_verified_at' => $this->email_verified_at,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Include roles as array of role objects (for frontend compatibility)
            'roles' => $this->when(
                $this->relationLoaded('roles'),
                fn () => $this->roles->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'guard_name' => $role->guard_name,
                ])->toArray()
            ),

            // Include permissions as array of permission objects (for frontend compatibility)
            'permissions' => $this->when(
                $this->relationLoaded('permissions'),
                fn () => $this->getAllPermissions()->map(fn ($permission) => [
                    'id' => $permission->id,
                    'name' => $permission->name,
                    'guard_name' => $permission->guard_name,
                ])->toArray()
            ),
        ];
    }
}
