<?php

use App\Http\Controllers\Api\ActivityLogController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserPermissionController;
use Illuminate\Support\Facades\Route;

// Public authentication routes
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Group routes that require authentication via Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Module Management routes (for dynamic menu system)
    // These routes must be OUTSIDE module.permission middleware to avoid circular dependency
    Route::prefix('admin/modules')->group(function () {
        Route::get('/', [ModuleController::class, 'index']);
        Route::get('/hierarchical', [ModuleController::class, 'hierarchical']);
        Route::get('/by-category', [ModuleController::class, 'byCategory']);
        Route::get('/permissions', [ModuleController::class, 'permissions']);
        Route::get('/{id}', [ModuleController::class, 'show']);
    });

    // Admin User Management routes (use 'users' module permission)
    Route::prefix('admin')->middleware('module.permission:users')->group(function () {
        // User Management
        Route::get('/users', [AdminController::class, 'index']);
        Route::get('/users/{id}', [AdminController::class, 'show']);
        Route::put('/users/{id}', [AdminController::class, 'update']);
        Route::delete('/users/{id}', [AdminController::class, 'destroy']);
        Route::post('/users', [AdminController::class, 'store']);

        // Legacy role/permission endpoints (kept for backward compatibility)
        Route::get('/all-roles', [AdminController::class, 'roles']); // Renamed to avoid conflict
        Route::get('/permissions', [AdminController::class, 'permissions']);
    });

    // Admin Role Management routes (use 'roles' module permission)
    Route::prefix('admin/roles')->middleware('module.permission:roles')->group(function () {
        Route::get('/', [RoleController::class, 'index']); // List all roles
        Route::get('/options', [RoleController::class, 'options']); // Dropdown options
        Route::get('/{id}', [RoleController::class, 'show']); // Get single role
        Route::post('/', [RoleController::class, 'store']); // Create role
        Route::put('/{id}', [RoleController::class, 'update']); // Update role
        Route::delete('/{id}', [RoleController::class, 'destroy']); // Delete role
    });

    // User Permission Management routes (uses 'users' module permission)
    Route::prefix('admin')->middleware('module.permission:users')->group(function () {
        Route::prefix('user-permissions')->group(function () {
            Route::get('/{userId}', [UserPermissionController::class, 'show']);
            Route::put('/{userId}', [UserPermissionController::class, 'updateUserPermissions']);
            Route::get('/{userId}/summary', [UserPermissionController::class, 'summary']);
        });
    });

    // ============================================================================
    // CURRENT USER PROFILE ROUTES (No module permission needed - users can always
    // view/update their own profile, password, email, and picture)
    // ============================================================================
    Route::prefix('user')->group(function () {
        // Get authenticated user with roles and permissions
        // Note: This creates /api/v1/user endpoint (not /api/v1/user/user)
        Route::get('/', [UserController::class, 'me']);

        // Self-profile update routes (user updating their own data)
        Route::post('/profile-picture', [UserController::class, 'updateProfilePicture']);
        Route::post('/username', [UserController::class, 'updateUsername']);
        Route::post('/password', [UserController::class, 'updatePassword']);
        Route::post('/email', [UserController::class, 'updateEmail']);
    });

    // Current user's module permissions (for frontend menu building)
    Route::get('/me/permissions', [UserController::class, 'myPermissions']);

    // ============================================================================
    // LOOKUPS ROUTES
    // READ operations - Available to all authenticated users (reference data for dropdowns)
    // WRITE operations - Requires lookups module permission
    // ============================================================================

    // Lookups READ routes - Any authenticated user can access these
    // These are needed by many modules for dropdowns (gender, status, etc.)
    Route::prefix('lookups')->group(function () {
        Route::get('/', [LookupController::class, 'index']);
        Route::get('/lists', [LookupController::class, 'lists']);
        Route::get('/search', [LookupController::class, 'search']);
        Route::get('/types', [LookupController::class, 'types']);
        Route::get('/type/{type}', [LookupController::class, 'byType']);
        Route::get('/{id}', [LookupController::class, 'show']);
    });

    // Lookups WRITE routes - Requires lookup_list module permission
    Route::prefix('lookups')->middleware('module.permission:lookup_list')->group(function () {
        Route::post('/', [LookupController::class, 'store']);
        Route::put('/{id}', [LookupController::class, 'update']);
        Route::delete('/{id}', [LookupController::class, 'destroy']);
    });

    // Notification routes (Enhanced with filtering, pagination, and bulk operations)
    Route::prefix('notifications')->group(function () {
        // Core CRUD operations
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::get('/stats', [NotificationController::class, 'stats']);
        Route::get('/filter-options', [NotificationController::class, 'filterOptions']);
        Route::get('/{id}', [NotificationController::class, 'show']);

        // Mark as read operations
        Route::post('/{id}/mark-read', [NotificationController::class, 'markAsRead']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);

        // Delete operations
        Route::delete('/{id}', [NotificationController::class, 'destroy']);
        Route::post('/bulk-delete', [NotificationController::class, 'bulkDestroy']);
        Route::post('/clear-read', [NotificationController::class, 'clearRead']);
    });

    // Activity Log routes
    Route::prefix('activity-logs')->group(function () {
        Route::get('/', [ActivityLogController::class, 'index']);
        Route::get('/recent', [ActivityLogController::class, 'recent']);
        Route::get('/subject/{type}/{id}', [ActivityLogController::class, 'forSubject']);
    });

    // ============================================================================
    // DASHBOARD ROUTES (Dynamic Dashboard System)
    // ============================================================================
    Route::prefix('dashboard')->group(function () {
        // Current user's dashboard
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

    // Admin dashboard widget management (for user management)
    Route::prefix('admin/dashboard')->middleware('module.permission:users')->group(function () {
        Route::get('/widgets', [DashboardController::class, 'index']);
        Route::get('/users/{userId}/widgets', [DashboardController::class, 'showUserWidgets']);
        Route::put('/users/{userId}/widgets', [DashboardController::class, 'updateUserWidgets']);
        Route::get('/users/{userId}/available-widgets', [DashboardController::class, 'availableForUser']);
    });

    // Authentication routes
    // Optional: logout endpoint
    Route::post('/logout', [AuthController::class, 'logout']);
    // Refresh token route – available only if the user is still authenticated
    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
});
