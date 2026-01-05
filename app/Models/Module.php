<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Module Model
 *
 * Represents a module/menu item in the system with associated permissions.
 * Used for dynamic menu generation and permission management.
 *
 * @property int $id
 * @property string $name Unique module identifier
 * @property string $display_name Display name in UI
 * @property string|null $description Module description
 * @property string|null $icon Icon class
 * @property string|null $category Category grouping
 * @property string|null $route Frontend route
 * @property string|null $active_link Active link for menu highlighting
 * @property string|null $parent_module Parent module name
 * @property bool $is_parent Is this a parent menu
 * @property string $read_permission Permission for read access
 * @property array $edit_permissions Array of edit permissions
 * @property int $order Display order
 * @property bool $is_active Active status
 */
class Module extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'icon',
        'category',
        'route',
        'active_link',
        'parent_module',
        'is_parent',
        'read_permission',
        'edit_permissions',
        'order',
        'is_active',
    ];

    protected $casts = [
        'edit_permissions' => 'array',
        'is_active' => 'boolean',
        'is_parent' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Get the parent module
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'parent_module', 'name');
    }

    /**
     * Get child modules
     */
    public function children(): HasMany
    {
        return $this->hasMany(Module::class, 'parent_module', 'name')->orderBy('order');
    }

    /**
     * Get active child modules
     */
    public function activeChildren(): HasMany
    {
        return $this->children()->where('is_active', true);
    }

    /**
     * Scope to get only active modules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get only parent modules
     */
    public function scopeParentModules($query)
    {
        return $query->where('is_parent', true);
    }

    /**
     * Scope to get only submenu modules
     */
    public function scopeSubmenus($query)
    {
        return $query->where('is_parent', false);
    }

    /**
     * Scope to get only root modules (no parent)
     */
    public function scopeRootModules($query)
    {
        return $query->whereNull('parent_module');
    }

    /**
     * Scope to order by display order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Get all permissions for this module (read + edit)
     */
    public function getAllPermissions(): array
    {
        return array_merge(
            [$this->read_permission],
            $this->edit_permissions ?? []
        );
    }

    /**
     * Check if module has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->getAllPermissions());
    }

    /**
     * Check if a user can read this module.
     */
    public function userCanRead($user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->can($this->read_permission);
    }

    /**
     * Check if a user can edit this module.
     */
    public function userCanEdit($user): bool
    {
        if (! $user || empty($this->edit_permissions)) {
            return false;
        }

        foreach ($this->edit_permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get user access level for this module.
     *
     * @return array{read: bool, edit: bool}
     */
    public function getUserAccess($user): array
    {
        return [
            'read' => $this->userCanRead($user),
            'edit' => $this->userCanEdit($user),
        ];
    }

    /**
     * Get permission for a specific action.
     *
     * Maps common CRUD actions to permission names.
     *
     * @param  string  $action  create, read, update, delete, import, export, bulk_create
     * @return string|null The permission name or null if not found
     */
    public function getPermissionForAction(string $action): ?string
    {
        // Read action
        if ($action === 'read' || $action === 'view' || $action === 'index' || $action === 'show') {
            return $this->read_permission;
        }

        // Edit actions
        $baseModule = explode('.', $this->read_permission)[0];
        $permissionName = "{$baseModule}.{$action}";

        return in_array($permissionName, $this->edit_permissions ?? []) ? $permissionName : null;
    }

    /**
     * Get all edit actions available for this module.
     *
     * @return array<string> Array of action names (create, update, delete, etc.)
     */
    public function getEditActions(): array
    {
        $actions = [];
        $baseModule = explode('.', $this->read_permission)[0];

        foreach ($this->edit_permissions ?? [] as $permission) {
            if (str_starts_with($permission, "{$baseModule}.")) {
                $actions[] = str_replace("{$baseModule}.", '', $permission);
            }
        }

        return $actions;
    }

    /**
     * Check if this module requires a specific permission.
     */
    public function requiresPermission(string $permission): bool
    {
        return $this->read_permission === $permission
            || in_array($permission, $this->edit_permissions ?? []);
    }

    /**
     * Scope to filter modules by category.
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope to get modules accessible by a specific user.
     */
    public function scopeAccessibleBy($query, $user)
    {
        if (! $user) {
            return $query->whereRaw('1 = 0'); // Return no results
        }

        $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();

        return $query->where(function ($q) use ($userPermissions) {
            $q->whereIn('read_permission', $userPermissions)
                ->orWhereJsonContains('edit_permissions', $userPermissions);
        });
    }
}
