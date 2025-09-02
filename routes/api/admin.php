<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Public route for login
Route::post('/login', [AuthController::class, 'login']);

// Group routes that require authentication via Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Admin routes (use middleware permission:read admin)
    Route::prefix('admin')->group(function () {
        Route::get('/users', [AdminController::class, 'index'])->middleware('permission:admin.read');
        Route::get('/users/{id}', [AdminController::class, 'show'])->middleware('permission:admin.read');
        Route::put('/users/{id}', [AdminController::class, 'update'])->middleware('permission:admin.update');
        Route::delete('/users/{id}', [AdminController::class, 'destroy'])->middleware('permission:admin.delete');
        Route::post('/users', [AdminController::class, 'store'])->middleware('permission:admin.create');

        Route::get('/roles', [AdminController::class, 'getRoles'])->middleware('permission:admin.read');
        Route::get('/permissions', [AdminController::class, 'getPermissions'])->middleware('permission:admin.read');
    });

    // User routes (use middleware permission:read users)
    Route::prefix('user')->group(function () {
        // Get authenticated user with roles and permissions
        Route::get('/user', [UserController::class, 'getUser'])->middleware('permission:user.read');

        // Profile update routes
        Route::post('/profile-picture', [UserController::class, 'updateProfilePicture'])->middleware('permission:user.update');
        Route::post('/username', [UserController::class, 'updateUsername'])->middleware('permission:user.update');
        Route::post('/password', [UserController::class, 'updatePassword'])->middleware('permission:user.update');
        Route::post('/email', [UserController::class, 'updateEmail'])->middleware('permission:user.update');
    });

    // Lookups routes
    Route::prefix('lookups')->group(function () {
        Route::get('/', [LookupController::class, 'index']);
        Route::get('/lists', [LookupController::class, 'getLookupLists']); // New route for organized lookup lists
        Route::get('/search', [LookupController::class, 'search']);
        Route::get('/types', [LookupController::class, 'getTypes']);
        Route::get('/type/{type}', [LookupController::class, 'getByType']);
        Route::post('/', [LookupController::class, 'store'])->middleware('permission:admin.create');
        Route::get('/{id}', [LookupController::class, 'show'])->middleware('permission:admin.read');
        Route::put('/{id}', [LookupController::class, 'update'])->middleware('permission:admin.update');
        Route::delete('/{id}', [LookupController::class, 'destroy'])->middleware('permission:admin.delete');
    });

    // Notification routes
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
    });

    // Authentication routes
    // Optional: logout endpoint
    Route::post('/logout', [AuthController::class, 'logout']);
    // Refresh token route – available only if the user is still authenticated
    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);
});
