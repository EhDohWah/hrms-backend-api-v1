<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\ModuleController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\UserPermissionController;
use Illuminate\Support\Facades\Route;

// ============================================================================
// ADMIN ROUTES
// All routes require authentication + module permission checks.
// ============================================================================

Route::middleware('auth:sanctum')->group(function () {

    // ---- Module Management (for dynamic menu system) ----
    // OUTSIDE module.permission middleware to avoid circular dependency
    Route::prefix('admin/modules')->group(function () {
        Route::get('/', [ModuleController::class, 'index']);
        Route::get('/hierarchical', [ModuleController::class, 'hierarchical']);
        Route::get('/by-category', [ModuleController::class, 'byCategory']);
        Route::get('/permissions', [ModuleController::class, 'permissions']);
        Route::get('/{module}', [ModuleController::class, 'show']);
    });

    // ---- User Management (requires 'users' module permission) ----
    Route::prefix('admin')->middleware('module.permission:users')->group(function () {
        Route::get('/users', [AdminController::class, 'index']);
        Route::get('/users/{user}', [AdminController::class, 'show']);
        Route::put('/users/{user}', [AdminController::class, 'update']);
        Route::delete('/users/{user}', [AdminController::class, 'destroy']);
        Route::post('/users', [AdminController::class, 'store']);

        // Legacy role/permission endpoints (kept for backward compatibility)
        Route::get('/all-roles', [AdminController::class, 'roles']);
        Route::get('/permissions', [AdminController::class, 'permissions']);
    });

    // ---- Role Management (requires 'roles' module permission) ----
    Route::prefix('admin/roles')->middleware('module.permission:roles')->group(function () {
        Route::get('/', [RoleController::class, 'index']);
        Route::get('/options', [RoleController::class, 'options']);
        Route::get('/{role}', [RoleController::class, 'show']);
        Route::post('/', [RoleController::class, 'store']);
        Route::put('/{role}', [RoleController::class, 'update']);
        Route::delete('/{role}', [RoleController::class, 'destroy']);
    });

    // ---- User Permission Management (requires 'users' module permission) ----
    Route::prefix('admin')->middleware('module.permission:users')->group(function () {
        Route::prefix('user-permissions')->group(function () {
            Route::get('/{user}', [UserPermissionController::class, 'show']);
            Route::put('/{user}', [UserPermissionController::class, 'updateUserPermissions']);
            Route::get('/{user}/summary', [UserPermissionController::class, 'summary']);
        });
    });

    // ---- Admin Dashboard Widget Management (requires 'users' module permission) ----
    Route::prefix('admin/dashboard')->middleware('module.permission:users')->group(function () {
        Route::get('/widgets', [DashboardController::class, 'index']);
        Route::get('/users/{userId}/widgets', [DashboardController::class, 'showUserWidgets']);
        Route::put('/users/{userId}/widgets', [DashboardController::class, 'updateUserWidgets']);
        Route::get('/users/{userId}/available-widgets', [DashboardController::class, 'availableForUser']);
    });

    // ---- Lookups (Write) — requires lookup_list module permission ----
    Route::prefix('lookups')->middleware('module.permission:lookup_list')->group(function () {
        Route::post('/', [LookupController::class, 'store']);
        Route::put('/{lookup}', [LookupController::class, 'update']);
        Route::delete('/{lookup}', [LookupController::class, 'destroy']);
    });
});
