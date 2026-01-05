<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class UserCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->collection->count(),
                'active_users' => $this->collection->where('status', 'active')->count(),
                'inactive_users' => $this->collection->where('status', 'inactive')->count(),
                'roles_breakdown' => $this->getRolesBreakdown(),
            ],
        ];
    }

    /**
     * Get breakdown of users by role
     */
    protected function getRolesBreakdown(): array
    {
        $breakdown = [];

        foreach ($this->collection as $user) {
            if ($user->relationLoaded('roles') && $user->roles->isNotEmpty()) {
                $roleName = $user->roles->first()->name;
                $breakdown[$roleName] = ($breakdown[$roleName] ?? 0) + 1;
            }
        }

        return $breakdown;
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @return array<string, mixed>
     */
    public function with(Request $request): array
    {
        return [
            'success' => true,
            'message' => 'Users retrieved successfully',
        ];
    }
}
