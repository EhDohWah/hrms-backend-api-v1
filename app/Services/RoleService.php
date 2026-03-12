<?php

namespace App\Services;

use App\Exceptions\DeletionBlockedException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class RoleService
{
    private const PROTECTED_ROLES = ['admin', 'hr-manager'];

    /**
     * List all roles ordered by name.
     */
    public function list(): Collection
    {
        $roles = Role::query()->orderBy('name')->get();

        // Count users per role via pivot table (avoids Spatie morphedByMany guard resolution issue)
        $userCounts = DB::table('model_has_roles')
            ->select('role_id', DB::raw('COUNT(*) as users_count'))
            ->groupBy('role_id')
            ->pluck('users_count', 'role_id');

        return $roles->each(function ($role) use ($userCounts) {
            $role->users_count = $userCounts[$role->id] ?? 0;
        });
    }

    /**
     * Get a single role with user count.
     */
    public function show(Role $role): Role
    {
        return $role->loadCount('users');
    }

    /**
     * Create a new role.
     */
    public function store(array $data): Role
    {
        return Role::create([
            'name' => $data['name'],
            'guard_name' => 'web',
        ]);
    }

    /**
     * Update a role name.
     */
    public function update(Role $role, array $data): Role
    {
        if (in_array($role->name, self::PROTECTED_ROLES)) {
            abort(403, 'Cannot modify protected system role');
        }

        $role->update(['name' => $data['name']]);

        return $role;
    }

    /**
     * Delete a role with safety checks.
     */
    public function destroy(Role $role): void
    {
        if (in_array($role->name, self::PROTECTED_ROLES)) {
            abort(403, 'Cannot delete protected system role');
        }

        $usersCount = DB::table('model_has_roles')
            ->where('role_id', $role->id)
            ->count();

        if ($usersCount > 0) {
            throw new DeletionBlockedException(
                ["{$usersCount} user(s) are assigned to this role. Please reassign users before deleting."],
                'Cannot delete role'
            );
        }

        $role->delete();
    }

    /**
     * Get roles for dropdown selection.
     */
    public function options(): Collection
    {
        return Role::query()
            ->select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(function ($role) {
                return [
                    'value' => $role->name,
                    'label' => $this->getDisplayName($role->name),
                    'is_protected' => in_array($role->name, self::PROTECTED_ROLES),
                ];
            });
    }

    /**
     * Convert role slug to display name.
     */
    private function getDisplayName(string $name): string
    {
        return match ($name) {
            'admin' => 'System Administrator',
            'hr-manager' => 'HR Manager',
            'hr-assistant-senior' => 'Senior HR Assistant',
            'hr-assistant' => 'HR Assistant',
            'hr-assistant-junior-senior' => 'Senior HR Junior Assistant',
            'hr-assistant-junior' => 'HR Junior Assistant',
            default => ucwords(str_replace('-', ' ', $name)),
        };
    }
}
