<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\GrantController;
use App\Http\Controllers\Api\InterviewController;

// Public route for login
Route::post('/login', [AuthController::class, 'login']);

// Group routes that require authentication via Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Optional: logout endpoint
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/auth/users', [UserController::class, 'index'])->middleware('permission:user.read');

    // Employees routes (use middleware permission:read employees)
    Route::prefix('employees')->group(function () {
        Route::get('/', [EmployeeController::class, 'index'])->middleware('permission:employee.read');
        Route::get('/{id}', [EmployeeController::class, 'show'])->middleware('permission:employee.read');
        Route::post('/', [EmployeeController::class, 'store'])->middleware('permission:employee.create');
        Route::put('/{id}', [EmployeeController::class, 'update'])->middleware('permission:employee.update');
        Route::delete('/{id}', [EmployeeController::class, 'destroy'])->middleware('permission:employee.delete');
    });

    // User routes (use middleware permission:read users)
    Route::prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index'])->middleware('permission:user.read');
        Route::get('/{id}', [UserController::class, 'show'])->middleware('permission:user.read');
        Route::post('/', [UserController::class, 'store'])->middleware('permission:user.create');
        Route::put('/{id}', [UserController::class, 'update'])->middleware('permission:user.update');
        Route::delete('/{id}', [UserController::class, 'destroy'])->middleware('permission:user.delete');
    });

    // Grant routes (use middleware permission:read grants)
    Route::prefix('grants')->group(function () {
        Route::get('/', [GrantController::class, 'index'])->middleware('permission:grant.read');
        Route::post('/upload', [GrantController::class, 'upload'])->middleware('permission:grant.create');
    });

    // Interview routes (use middleware permission:read interviews)
    Route::prefix('interviews')->group(function () {
        Route::get('/', [InterviewController::class, 'index'])->middleware('permission:interview.read');
        Route::post('/', [InterviewController::class, 'store'])->middleware('permission:interview.create');
    });
});


