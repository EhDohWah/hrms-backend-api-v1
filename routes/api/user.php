<?php

use App\Http\Controllers\Api\V1\ActivityLogController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

// ============================================================================
// AUTHENTICATED USER ROUTES
// These routes are accessible by any authenticated user without module permissions.
// Covers: self-profile, notifications, activity logs, dashboard, lookups (read).
// ============================================================================

Route::middleware('auth:sanctum')->group(function () {

    // ---- Current User Profile ----
    // No module permission needed — users can always view/update their own profile
    Route::prefix('user')->group(function () {
        Route::get('/', [UserController::class, 'me']);
        Route::post('/profile-picture', [UserController::class, 'updateProfilePicture']);
        Route::post('/username', [UserController::class, 'updateUsername']);
        Route::post('/password', [UserController::class, 'updatePassword']);
        Route::post('/email', [UserController::class, 'updateEmail']);
    });

    // Current user's module permissions (for frontend menu building)
    Route::get('/me/permissions', [UserController::class, 'myPermissions']);

    // ---- Notifications ----
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::get('/stats', [NotificationController::class, 'stats']);
        Route::get('/filter-options', [NotificationController::class, 'filterOptions']);
        Route::get('/{id}', [NotificationController::class, 'show']);

        Route::post('/{id}/mark-read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);

        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::post('/bulk-delete', [NotificationController::class, 'bulkDestroy']);
        Route::post('/clear-read', [NotificationController::class, 'clearRead']);
    });

    // ---- Activity Logs ----
    Route::prefix('activity-logs')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index']);
        Route::get('/recent', [ActivityLogController::class, 'recent']);
        Route::get('/subject/{type}/{id}', [ActivityLogController::class, 'forSubject']);
    });

    // ---- Dashboard (Current User) ----
    Route::prefix('dashboard')->group(function () {
        Route::get('/my-widgets', [DashboardController::class, 'show']);
        Route::put('/my-widgets', [DashboardController::class, 'updateUserDashboard']);
        Route::get('/available-widgets', [DashboardController::class, 'available']);
        Route::post('/widgets/add', [DashboardController::class, 'addWidget']);
        Route::delete('/widgets/{widgetId}', [DashboardController::class, 'removeWidget']);
        Route::post('/widgets/{widgetId}/toggle-visibility', [DashboardController::class, 'toggleWidgetVisibility']);
        Route::post('/widgets/{widgetId}/toggle-collapse', [DashboardController::class, 'toggleWidgetCollapse']);
        Route::post('/widgets/reorder', [DashboardController::class, 'reorderWidgets']);
        Route::post('/reset-defaults', [DashboardController::class, 'resetToDefaults']);
    });

    // ---- Lookups (Read-Only) ----
    // Reference data for dropdowns — needed by many modules
    Route::prefix('lookups')->group(function () {
        Route::get('/', [LookupController::class, 'index']);
        Route::get('/lists', [LookupController::class, 'lists']);
        Route::get('/search', [LookupController::class, 'search']);
        Route::get('/types', [LookupController::class, 'types']);
        Route::get('/type/{type}', [LookupController::class, 'byType']);
        Route::get('/{lookup}', [LookupController::class, 'show']);
    });
});
