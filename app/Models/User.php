<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use OpenApi\Attributes as OA;
use Spatie\Permission\Traits\HasRoles;

#[OA\Schema(
    schema: 'User',
    title: 'User',
    description: 'User model',
    properties: [
        new OA\Property(property: 'id', type: 'integer', format: 'int64', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com'),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        new OA\Property(property: 'last_login_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'last_login_ip', type: 'string', example: '192.168.1.1'),
        new OA\Property(property: 'created_by', type: 'string', example: 'admin'),
        new OA\Property(property: 'updated_by', type: 'string', example: 'admin'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'email_verified_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string', example: 'admin')),
        new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string', example: 'user.read')),
    ]
)]
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'last_login_at',
        'last_login_ip',
        'profile_picture',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the employee record associated with the user.
     */
    public function employee()
    {
        return $this->hasOne(Employee::class, 'user_id');
    }

    /**
     * Override toArray to transform permissions to flat array of names
     * This ensures the frontend receives ['user.read', 'user.create']
     * instead of full permission objects when user is serialized
     */
    public function toArray(): array
    {
        $array = parent::toArray();

        // Transform permissions relationship to array of permission names
        if (isset($array['permissions']) && is_array($array['permissions'])) {
            $array['permissions'] = collect($array['permissions'])->pluck('name')->toArray();
        }

        // If permissions relationship is loaded but not in array (edge case)
        if ($this->relationLoaded('permissions') && ! isset($array['permissions'])) {
            $array['permissions'] = $this->getAllPermissions()->pluck('name')->toArray();
        }

        return $array;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if user can read a specific module.
     *
     * Accepts either module name (e.g., 'user_management') or permission prefix (e.g., 'user').
     *
     * @param  string  $moduleNameOrPrefix  Module name or permission prefix
     */
    public function canReadModule(string $moduleNameOrPrefix): bool
    {
        // Try to find module by name first
        $module = Module::where('name', $moduleNameOrPrefix)->where('is_active', true)->first();

        // If not found by name, try to find by permission prefix
        if (! $module) {
            $module = Module::where('read_permission', "{$moduleNameOrPrefix}.read")
                ->where('is_active', true)
                ->first();
        }

        // If module found, check its read permission
        if ($module) {
            return $this->can($module->read_permission);
        }

        // Fallback: treat identifier as permission prefix and check directly
        return $this->can("{$moduleNameOrPrefix}.read");
    }

    /**
     * Check if user can edit (create/update/delete) a specific module.
     *
     * Accepts either module name (e.g., 'user_management') or permission prefix (e.g., 'user').
     *
     * @param  string  $moduleNameOrPrefix  Module name or permission prefix
     */
    public function canEditModule(string $moduleNameOrPrefix): bool
    {
        // Try to find module by name first
        $module = Module::where('name', $moduleNameOrPrefix)->where('is_active', true)->first();

        // If not found by name, try to find by permission prefix
        if (! $module) {
            $module = Module::where('read_permission', "{$moduleNameOrPrefix}.read")
                ->where('is_active', true)
                ->first();
        }

        // If module found, extract permission prefix and check edit permission
        if ($module) {
            $permissionPrefix = str_replace('.read', '', $module->read_permission);

            return $this->can("{$permissionPrefix}.edit");
        }

        // Fallback: treat identifier as permission prefix and check directly
        return $this->can("{$moduleNameOrPrefix}.edit");
    }

    /**
     * Get module access level for a specific module.
     *
     * Returns array with 'read' and 'edit' boolean flags.
     * Accepts either module name (e.g., 'user_management') or permission prefix (e.g., 'user').
     *
     * @param  string  $moduleNameOrPrefix  Module name or permission prefix
     * @return array{read: bool, edit: bool}
     */
    public function getModuleAccess(string $moduleNameOrPrefix): array
    {
        return [
            'read' => $this->canReadModule($moduleNameOrPrefix),
            'edit' => $this->canEditModule($moduleNameOrPrefix),
        ];
    }

    /**
     * Check if user has any access (read or edit) to a module.
     *
     * @param  string  $moduleName  Module identifier
     */
    public function hasModuleAccess(string $moduleName): bool
    {
        return $this->canReadModule($moduleName) || $this->canEditModule($moduleName);
    }

    /**
     * Check if user has read-only access (can read but cannot edit).
     *
     * @param  string  $moduleName  Module identifier
     */
    public function hasReadOnlyAccess(string $moduleName): bool
    {
        return $this->canReadModule($moduleName) && ! $this->canEditModule($moduleName);
    }

    /**
     * Check if user has full access (both read and edit).
     *
     * @param  string  $moduleName  Module identifier
     */
    public function hasFullAccess(string $moduleName): bool
    {
        return $this->canReadModule($moduleName) && $this->canEditModule($moduleName);
    }

    /**
     * Get all modules user has access to with their access levels.
     *
     * @return array<string, array{read: bool, edit: bool, display_name: string}>
     */
    public function getAccessibleModules(): array
    {
        $modules = Module::active()->ordered()->get();
        $accessibleModules = [];

        foreach ($modules as $module) {
            $access = $this->getModuleAccess($module->name);

            if ($access['read'] || $access['edit']) {
                $accessibleModules[$module->name] = [
                    'read' => $access['read'],
                    'edit' => $access['edit'],
                    'display_name' => $module->display_name,
                    'category' => $module->category,
                    'icon' => $module->icon,
                    'route' => $module->route,
                ];
            }
        }

        return $accessibleModules;
    }

    /**
     * Get user's dashboard widgets (pivot relationship)
     */
    public function dashboardWidgets(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(DashboardWidget::class, 'user_dashboard_widgets')
            ->withPivot(['order', 'is_visible', 'is_collapsed', 'user_config'])
            ->withTimestamps();
    }

    /**
     * Get user's dashboard widget configurations
     */
    public function userDashboardWidgets(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserDashboardWidget::class);
    }

    /**
     * Get user's visible dashboard widgets ordered by user preference
     */
    public function getVisibleDashboardWidgets()
    {
        return $this->dashboardWidgets()
            ->wherePivot('is_visible', true)
            ->where('is_active', true)
            ->orderByPivot('order')
            ->get()
            ->filter(function ($widget) {
                return $widget->userHasPermission($this);
            });
    }

    /**
     * Sync user's dashboard widgets
     *
     * @param  array  $widgetIds  Array of widget IDs or array with pivot data
     */
    public function syncDashboardWidgets(array $widgetIds): void
    {
        $syncData = [];
        $order = 0;

        foreach ($widgetIds as $key => $value) {
            if (is_array($value)) {
                // Value contains pivot data
                $syncData[$key] = array_merge(['order' => $order], $value);
            } else {
                // Simple widget ID
                $syncData[$value] = ['order' => $order, 'is_visible' => true];
            }
            $order++;
        }

        $this->dashboardWidgets()->sync($syncData);
    }

    /**
     * Assign default widgets to user based on their permissions
     */
    public function assignDefaultWidgets(): void
    {
        $defaultWidgets = DashboardWidget::active()
            ->default()
            ->orderBy('default_order')
            ->get()
            ->filter(function ($widget) {
                return $widget->userHasPermission($this);
            });

        $syncData = [];
        foreach ($defaultWidgets as $order => $widget) {
            $syncData[$widget->id] = [
                'order' => $order,
                'is_visible' => true,
                'is_collapsed' => false,
            ];
        }

        $this->dashboardWidgets()->sync($syncData);
    }
}
