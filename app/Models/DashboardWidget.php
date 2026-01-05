<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DashboardWidget extends Model
{
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'component',
        'icon',
        'category',
        'size',
        'required_permission',
        'is_active',
        'is_default',
        'default_order',
        'config',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'config' => 'array',
        ];
    }

    /**
     * Get users who have this widget
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_dashboard_widgets')
            ->withPivot(['order', 'is_visible', 'is_collapsed', 'user_config'])
            ->withTimestamps();
    }

    /**
     * Get user dashboard widget entries
     */
    public function userDashboardWidgets(): HasMany
    {
        return $this->hasMany(UserDashboardWidget::class);
    }

    /**
     * Scope to get only active widgets
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get default widgets
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope to filter by category
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Check if user has permission for this widget
     */
    public function userHasPermission(User $user): bool
    {
        if (empty($this->required_permission)) {
            return true;
        }

        try {
            return $user->hasPermissionTo($this->required_permission);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist $e) {
            // If the permission doesn't exist, widget is not accessible
            return false;
        }
    }

    /**
     * Get available categories
     */
    public static function getCategories(): array
    {
        return [
            'general' => 'General',
            'hr' => 'Human Resources',
            'payroll' => 'Payroll',
            'leave' => 'Leave Management',
            'attendance' => 'Attendance',
            'recruitment' => 'Recruitment',
            'training' => 'Training',
            'reports' => 'Reports',
        ];
    }

    /**
     * Get available sizes
     */
    public static function getSizes(): array
    {
        return [
            'small' => 'Small (1/4 width)',
            'medium' => 'Medium (1/2 width)',
            'large' => 'Large (3/4 width)',
            'full' => 'Full width',
        ];
    }
}
