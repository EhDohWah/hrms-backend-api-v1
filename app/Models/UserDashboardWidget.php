<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDashboardWidget extends Model
{
    protected $fillable = [
        'user_id',
        'dashboard_widget_id',
        'order',
        'is_visible',
        'is_collapsed',
        'user_config',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'is_collapsed' => 'boolean',
            'user_config' => 'array',
        ];
    }

    /**
     * Get the user that owns this widget configuration
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the dashboard widget
     */
    public function dashboardWidget(): BelongsTo
    {
        return $this->belongsTo(DashboardWidget::class);
    }

    /**
     * Scope to get only visible widgets
     */
    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    /**
     * Scope to order by user's custom order
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }
}
