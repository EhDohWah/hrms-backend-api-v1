<?php

namespace App\Services;

use App\Models\DashboardWidget;
use App\Models\User;
use App\Models\UserDashboardWidget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Get all widgets with categories and sizes (admin listing).
     */
    public function listAllWidgets(): array
    {
        $widgets = DashboardWidget::orderBy('category')
            ->orderBy('default_order')
            ->get();

        return [
            'data' => $widgets,
            'categories' => DashboardWidget::getCategories(),
            'sizes' => DashboardWidget::getSizes(),
        ];
    }

    /**
     * Get widgets available to a user based on their permissions.
     */
    public function availableWidgetsForUser(User $user): array
    {
        $widgets = DashboardWidget::active()
            ->orderBy('category')
            ->orderBy('default_order')
            ->get()
            ->filter(fn ($widget) => $widget->userHasPermission($user))
            ->values();

        return [
            'data' => $widgets,
            'categories' => DashboardWidget::getCategories(),
        ];
    }

    /**
     * Get the user's dashboard configuration.
     *
     * If user has no widgets configured, assigns defaults automatically.
     */
    public function getUserDashboard(User $user): Collection
    {
        $userWidgets = $this->loadUserWidgets($user);

        // If user has no widgets configured, assign defaults
        if ($userWidgets->isEmpty()) {
            $user->assignDefaultWidgets();
            $userWidgets = $this->loadUserWidgets($user);
        }

        return $userWidgets;
    }

    /**
     * Replace the user's entire dashboard configuration.
     */
    public function updateUserDashboard(User $user, array $widgets): void
    {
        DB::transaction(function () use ($user, $widgets) {
            UserDashboardWidget::where('user_id', $user->id)->delete();

            foreach ($widgets as $index => $widgetData) {
                UserDashboardWidget::create([
                    'user_id' => $user->id,
                    'dashboard_widget_id' => $widgetData['id'],
                    'order' => $widgetData['order'] ?? $index,
                    'is_visible' => $widgetData['is_visible'] ?? true,
                    'is_collapsed' => $widgetData['is_collapsed'] ?? false,
                    'user_config' => $widgetData['user_config'] ?? null,
                ]);
            }
        });
    }

    /**
     * Toggle a widget's visibility on the user's dashboard.
     *
     * Returns ['success' => bool, 'message' => string, 'is_visible' => bool|null, 'status' => int].
     */
    public function toggleWidgetVisibility(User $user, int $widgetId): array
    {
        $userWidget = UserDashboardWidget::where('user_id', $user->id)
            ->where('dashboard_widget_id', $widgetId)
            ->first();

        if (! $userWidget) {
            return [
                'success' => false,
                'message' => 'Widget not found in your dashboard',
                'status' => 404,
            ];
        }

        $userWidget->update(['is_visible' => ! $userWidget->is_visible]);

        return [
            'success' => true,
            'message' => 'Widget visibility updated',
            'is_visible' => $userWidget->is_visible,
            'status' => 200,
        ];
    }

    /**
     * Toggle a widget's collapsed state on the user's dashboard.
     *
     * Returns ['success' => bool, 'message' => string, 'is_collapsed' => bool|null, 'status' => int].
     */
    public function toggleWidgetCollapse(User $user, int $widgetId): array
    {
        $userWidget = UserDashboardWidget::where('user_id', $user->id)
            ->where('dashboard_widget_id', $widgetId)
            ->first();

        if (! $userWidget) {
            return [
                'success' => false,
                'message' => 'Widget not found in your dashboard',
                'status' => 404,
            ];
        }

        $userWidget->update(['is_collapsed' => ! $userWidget->is_collapsed]);

        return [
            'success' => true,
            'message' => 'Widget collapse state updated',
            'is_collapsed' => $userWidget->is_collapsed,
            'status' => 200,
        ];
    }

    /**
     * Add a widget to the user's dashboard.
     *
     * Returns ['success' => bool, 'message' => string, 'status' => int].
     */
    public function addWidget(User $user, int $widgetId): array
    {
        $widget = DashboardWidget::findOrFail($widgetId);

        // Check if user has permission for this widget
        if (! $widget->userHasPermission($user)) {
            return [
                'success' => false,
                'message' => 'You do not have permission to add this widget',
                'status' => 403,
            ];
        }

        // Check if widget is already added
        $existing = UserDashboardWidget::where('user_id', $user->id)
            ->where('dashboard_widget_id', $widget->id)
            ->first();

        if ($existing) {
            // If exists but hidden, make it visible
            if (! $existing->is_visible) {
                $existing->update(['is_visible' => true]);

                return [
                    'success' => true,
                    'message' => 'Widget is now visible on your dashboard',
                    'status' => 200,
                ];
            }

            return [
                'success' => false,
                'message' => 'Widget is already on your dashboard',
                'status' => 422,
            ];
        }

        // Get the max order to add at the end
        $maxOrder = UserDashboardWidget::where('user_id', $user->id)->max('order') ?? -1;

        UserDashboardWidget::create([
            'user_id' => $user->id,
            'dashboard_widget_id' => $widget->id,
            'order' => $maxOrder + 1,
            'is_visible' => true,
            'is_collapsed' => false,
        ]);

        return [
            'success' => true,
            'message' => 'Widget added to your dashboard',
            'status' => 200,
        ];
    }

    /**
     * Remove a widget from the user's dashboard.
     *
     * Returns ['success' => bool, 'message' => string, 'status' => int].
     */
    public function removeWidget(User $user, int $widgetId): array
    {
        $deleted = UserDashboardWidget::where('user_id', $user->id)
            ->where('dashboard_widget_id', $widgetId)
            ->delete();

        if (! $deleted) {
            return [
                'success' => false,
                'message' => 'Widget not found in your dashboard',
                'status' => 404,
            ];
        }

        return [
            'success' => true,
            'message' => 'Widget removed from your dashboard',
            'status' => 200,
        ];
    }

    /**
     * Reorder widgets on the user's dashboard.
     */
    public function reorderWidgets(User $user, array $widgetOrder): void
    {
        DB::transaction(function () use ($user, $widgetOrder) {
            foreach ($widgetOrder as $order => $widgetId) {
                UserDashboardWidget::where('user_id', $user->id)
                    ->where('dashboard_widget_id', $widgetId)
                    ->update(['order' => $order]);
            }
        });
    }

    /**
     * Reset user's dashboard to default widget configuration.
     */
    public function resetToDefaults(User $user): void
    {
        $user->assignDefaultWidgets();
    }

    /**
     * Get a specific user's dashboard widgets (admin endpoint).
     */
    public function showUserWidgets(int $userId): Collection
    {
        $targetUser = User::findOrFail($userId);

        return $targetUser->dashboardWidgets()
            ->orderByPivot('order')
            ->get()
            ->map(fn ($widget) => [
                'id' => $widget->id,
                'name' => $widget->name,
                'display_name' => $widget->display_name,
                'category' => $widget->category,
                'order' => $widget->pivot->order,
                'is_visible' => $widget->pivot->is_visible,
            ]);
    }

    /**
     * Update a specific user's dashboard widgets (admin endpoint).
     */
    public function updateUserWidgets(int $userId, array $widgetIds): void
    {
        $targetUser = User::findOrFail($userId);

        $syncData = [];
        foreach ($widgetIds as $order => $widgetId) {
            $syncData[$widgetId] = [
                'order' => $order,
                'is_visible' => true,
                'is_collapsed' => false,
            ];
        }

        $targetUser->dashboardWidgets()->sync($syncData);
    }

    /**
     * Get available widgets for a specific user by permissions (admin endpoint).
     */
    public function availableForUser(int $userId): array
    {
        $targetUser = User::findOrFail($userId);

        $widgets = DashboardWidget::active()
            ->orderBy('category')
            ->orderBy('default_order')
            ->get()
            ->filter(fn ($widget) => $widget->userHasPermission($targetUser))
            ->values();

        return [
            'data' => $widgets,
            'categories' => DashboardWidget::getCategories(),
        ];
    }

    /**
     * Load the user's active dashboard widgets with pivot data, filtered by permission.
     */
    private function loadUserWidgets(User $user): Collection
    {
        return $user->dashboardWidgets()
            ->where('is_active', true)
            ->orderByPivot('order')
            ->get()
            ->filter(fn ($widget) => $widget->userHasPermission($user))
            ->map(fn ($widget) => [
                'id' => $widget->id,
                'name' => $widget->name,
                'display_name' => $widget->display_name,
                'description' => $widget->description,
                'component' => $widget->component,
                'icon' => $widget->icon,
                'category' => $widget->category,
                'size' => $widget->size,
                'config' => $widget->config,
                'order' => $widget->pivot->order,
                'is_visible' => $widget->pivot->is_visible,
                'is_collapsed' => $widget->pivot->is_collapsed,
                'user_config' => $widget->pivot->user_config,
            ])
            ->values();
    }
}
