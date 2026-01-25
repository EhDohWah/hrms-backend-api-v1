<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DashboardWidget;
use App\Models\UserDashboardWidget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get all available widgets (for admin widget management)
     */
    public function index(): JsonResponse
    {
        $widgets = DashboardWidget::orderBy('category')
            ->orderBy('default_order')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $widgets,
            'categories' => DashboardWidget::getCategories(),
            'sizes' => DashboardWidget::getSizes(),
        ]);
    }

    /**
     * Get widgets available for a specific user based on their permissions
     */
    public function available(Request $request): JsonResponse
    {
        $user = Auth::user();

        $widgets = DashboardWidget::active()
            ->orderBy('category')
            ->orderBy('default_order')
            ->get()
            ->filter(function ($widget) use ($user) {
                return $widget->userHasPermission($user);
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $widgets,
            'categories' => DashboardWidget::getCategories(),
        ]);
    }

    /**
     * Get current user's dashboard configuration
     */
    public function show(): JsonResponse
    {
        $user = Auth::user();

        // Get user's widgets ordered by their preference
        $userWidgets = $user->dashboardWidgets()
            ->where('is_active', true)
            ->orderByPivot('order')
            ->get()
            ->filter(function ($widget) use ($user) {
                return $widget->userHasPermission($user);
            })
            ->map(function ($widget) {
                return [
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
                ];
            })
            ->values();

        // If user has no widgets configured, assign defaults
        if ($userWidgets->isEmpty()) {
            $user->assignDefaultWidgets();

            return $this->show(); // Recursive call to get the newly assigned widgets
        }

        return response()->json([
            'success' => true,
            'data' => $userWidgets,
        ]);
    }

    /**
     * Update user's dashboard widget configuration
     */
    public function updateUserDashboard(Request $request): JsonResponse
    {
        $request->validate([
            'widgets' => 'required|array',
            'widgets.*.id' => 'required|exists:dashboard_widgets,id',
            'widgets.*.order' => 'integer|min:0',
            'widgets.*.is_visible' => 'boolean',
            'widgets.*.is_collapsed' => 'boolean',
            'widgets.*.user_config' => 'nullable|array',
        ]);

        $user = Auth::user();

        DB::transaction(function () use ($user, $request) {
            // Delete existing configuration
            UserDashboardWidget::where('user_id', $user->id)->delete();

            // Insert new configuration
            foreach ($request->widgets as $index => $widgetData) {
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

        return response()->json([
            'success' => true,
            'message' => 'Dashboard configuration updated successfully',
        ]);
    }

    /**
     * Toggle widget visibility
     */
    public function toggleWidgetVisibility(Request $request, int $widgetId): JsonResponse
    {
        $user = Auth::user();

        $userWidget = UserDashboardWidget::where('user_id', $user->id)
            ->where('dashboard_widget_id', $widgetId)
            ->first();

        if (! $userWidget) {
            return response()->json([
                'success' => false,
                'message' => 'Widget not found in your dashboard',
            ], 404);
        }

        $userWidget->update([
            'is_visible' => ! $userWidget->is_visible,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Widget visibility updated',
            'is_visible' => $userWidget->is_visible,
        ]);
    }

    /**
     * Toggle widget collapsed state
     */
    public function toggleWidgetCollapse(Request $request, int $widgetId): JsonResponse
    {
        $user = Auth::user();

        $userWidget = UserDashboardWidget::where('user_id', $user->id)
            ->where('dashboard_widget_id', $widgetId)
            ->first();

        if (! $userWidget) {
            return response()->json([
                'success' => false,
                'message' => 'Widget not found in your dashboard',
            ], 404);
        }

        $userWidget->update([
            'is_collapsed' => ! $userWidget->is_collapsed,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Widget collapse state updated',
            'is_collapsed' => $userWidget->is_collapsed,
        ]);
    }

    /**
     * Add a widget to user's dashboard
     */
    public function addWidget(Request $request): JsonResponse
    {
        $request->validate([
            'widget_id' => 'required|exists:dashboard_widgets,id',
        ]);

        $user = Auth::user();
        $widget = DashboardWidget::findOrFail($request->widget_id);

        // Check if user has permission for this widget
        if (! $widget->userHasPermission($user)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to add this widget',
            ], 403);
        }

        // Check if widget is already added
        $existing = UserDashboardWidget::where('user_id', $user->id)
            ->where('dashboard_widget_id', $widget->id)
            ->first();

        if ($existing) {
            // If exists but hidden, make it visible
            if (! $existing->is_visible) {
                $existing->update(['is_visible' => true]);

                return response()->json([
                    'success' => true,
                    'message' => 'Widget is now visible on your dashboard',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Widget is already on your dashboard',
            ], 422);
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

        return response()->json([
            'success' => true,
            'message' => 'Widget added to your dashboard',
        ]);
    }

    /**
     * Remove a widget from user's dashboard
     */
    public function removeWidget(int $widgetId): JsonResponse
    {
        $user = Auth::user();

        $deleted = UserDashboardWidget::where('user_id', $user->id)
            ->where('dashboard_widget_id', $widgetId)
            ->delete();

        if (! $deleted) {
            return response()->json([
                'success' => false,
                'message' => 'Widget not found in your dashboard',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Widget removed from your dashboard',
        ]);
    }

    /**
     * Reorder widgets on user's dashboard
     */
    public function reorderWidgets(Request $request): JsonResponse
    {
        $request->validate([
            'widget_order' => 'required|array',
            'widget_order.*' => 'integer|exists:dashboard_widgets,id',
        ]);

        $user = Auth::user();

        DB::transaction(function () use ($user, $request) {
            foreach ($request->widget_order as $order => $widgetId) {
                UserDashboardWidget::where('user_id', $user->id)
                    ->where('dashboard_widget_id', $widgetId)
                    ->update(['order' => $order]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Widget order updated successfully',
        ]);
    }

    /**
     * Reset user's dashboard to defaults
     */
    public function resetToDefaults(): JsonResponse
    {
        $user = Auth::user();
        $user->assignDefaultWidgets();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard reset to default configuration',
        ]);
    }

    /**
     * Get widgets for a specific user (admin endpoint for user management)
     */
    public function showUserWidgets(int $userId): JsonResponse
    {
        $targetUser = \App\Models\User::findOrFail($userId);

        $widgets = $targetUser->dashboardWidgets()
            ->orderByPivot('order')
            ->get()
            ->map(function ($widget) {
                return [
                    'id' => $widget->id,
                    'name' => $widget->name,
                    'display_name' => $widget->display_name,
                    'category' => $widget->category,
                    'order' => $widget->pivot->order,
                    'is_visible' => $widget->pivot->is_visible,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $widgets,
        ]);
    }

    /**
     * Update widgets for a specific user (admin endpoint for user management)
     */
    public function updateUserWidgets(Request $request, int $userId): JsonResponse
    {
        $request->validate([
            'widget_ids' => 'required|array',
            'widget_ids.*' => 'integer|exists:dashboard_widgets,id',
        ]);

        $targetUser = \App\Models\User::findOrFail($userId);

        // Sync the widgets
        $syncData = [];
        foreach ($request->widget_ids as $order => $widgetId) {
            $syncData[$widgetId] = [
                'order' => $order,
                'is_visible' => true,
                'is_collapsed' => false,
            ];
        }

        $targetUser->dashboardWidgets()->sync($syncData);

        return response()->json([
            'success' => true,
            'message' => 'User dashboard widgets updated successfully',
        ]);
    }

    /**
     * Get available widgets for a specific user based on their permissions (admin endpoint)
     */
    public function availableForUser(int $userId): JsonResponse
    {
        $targetUser = \App\Models\User::findOrFail($userId);

        $widgets = DashboardWidget::active()
            ->orderBy('category')
            ->orderBy('default_order')
            ->get()
            ->filter(function ($widget) use ($targetUser) {
                return $widget->userHasPermission($targetUser);
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $widgets,
            'categories' => DashboardWidget::getCategories(),
        ]);
    }
}
