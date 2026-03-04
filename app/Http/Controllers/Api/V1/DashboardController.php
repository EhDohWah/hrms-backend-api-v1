<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Dashboard\AddWidgetRequest;
use App\Http\Requests\Dashboard\ReorderWidgetsRequest;
use App\Http\Requests\Dashboard\UpdateDashboardRequest;
use App\Http\Requests\Dashboard\UpdateUserWidgetsRequest;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Manages dashboard widgets and user-specific dashboard configurations.
 */
class DashboardController extends BaseApiController
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    /**
     * Get all available widgets (for admin widget management).
     */
    public function index(): JsonResponse
    {
        $result = $this->dashboardService->listAllWidgets();

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'categories' => $result['categories'],
            'sizes' => $result['sizes'],
        ]);
    }

    /**
     * Get widgets available for the current user based on their permissions.
     */
    public function available(Request $request): JsonResponse
    {
        $result = $this->dashboardService->availableWidgetsForUser($request->user());

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'categories' => $result['categories'],
        ]);
    }

    /**
     * Get current user's dashboard configuration.
     */
    public function show(Request $request): JsonResponse
    {
        $userWidgets = $this->dashboardService->getUserDashboard($request->user());

        return response()->json([
            'success' => true,
            'data' => $userWidgets,
        ]);
    }

    /**
     * Update user's dashboard widget configuration.
     */
    public function updateUserDashboard(UpdateDashboardRequest $request): JsonResponse
    {
        $this->dashboardService->updateUserDashboard(
            $request->user(),
            $request->validated()['widgets']
        );

        return response()->json([
            'success' => true,
            'message' => 'Dashboard configuration updated successfully',
        ]);
    }

    /**
     * Toggle widget visibility.
     */
    public function toggleWidgetVisibility(Request $request, int $widgetId): JsonResponse
    {
        $result = $this->dashboardService->toggleWidgetVisibility($request->user(), $widgetId);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            ...($result['success'] ? ['is_visible' => $result['is_visible']] : []),
        ], $result['status']);
    }

    /**
     * Toggle widget collapsed state.
     */
    public function toggleWidgetCollapse(Request $request, int $widgetId): JsonResponse
    {
        $result = $this->dashboardService->toggleWidgetCollapse($request->user(), $widgetId);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
            ...($result['success'] ? ['is_collapsed' => $result['is_collapsed']] : []),
        ], $result['status']);
    }

    /**
     * Add a widget to user's dashboard.
     */
    public function addWidget(AddWidgetRequest $request): JsonResponse
    {
        $result = $this->dashboardService->addWidget(
            $request->user(),
            $request->validated()['widget_id']
        );

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
        ], $result['status']);
    }

    /**
     * Remove a widget from user's dashboard.
     */
    public function removeWidget(Request $request, int $widgetId): JsonResponse
    {
        $result = $this->dashboardService->removeWidget($request->user(), $widgetId);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
        ], $result['status']);
    }

    /**
     * Reorder widgets on user's dashboard.
     */
    public function reorderWidgets(ReorderWidgetsRequest $request): JsonResponse
    {
        $this->dashboardService->reorderWidgets(
            $request->user(),
            $request->validated()['widget_order']
        );

        return response()->json([
            'success' => true,
            'message' => 'Widget order updated successfully',
        ]);
    }

    /**
     * Reset user's dashboard to defaults.
     */
    public function resetToDefaults(Request $request): JsonResponse
    {
        $this->dashboardService->resetToDefaults($request->user());

        return response()->json([
            'success' => true,
            'message' => 'Dashboard reset to default configuration',
        ]);
    }

    /**
     * Get widgets for a specific user (admin endpoint for user management).
     */
    public function showUserWidgets(int $userId): JsonResponse
    {
        $widgets = $this->dashboardService->showUserWidgets($userId);

        return response()->json([
            'success' => true,
            'data' => $widgets,
        ]);
    }

    /**
     * Update widgets for a specific user (admin endpoint for user management).
     */
    public function updateUserWidgets(UpdateUserWidgetsRequest $request, int $userId): JsonResponse
    {
        $this->dashboardService->updateUserWidgets($userId, $request->validated()['widget_ids']);

        return response()->json([
            'success' => true,
            'message' => 'User dashboard widgets updated successfully',
        ]);
    }

    /**
     * Get available widgets for a specific user based on their permissions (admin endpoint).
     */
    public function availableForUser(int $userId): JsonResponse
    {
        $result = $this->dashboardService->availableForUser($userId);

        return response()->json([
            'success' => true,
            'data' => $result['data'],
            'categories' => $result['categories'],
        ]);
    }
}
