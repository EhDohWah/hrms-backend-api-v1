<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\EmployeeController;

// Public route for login
Route::post('/login', [AuthController::class, 'login']);

// Group routes that require authentication via Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Optional: logout endpoint
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:user.read');

    // Employees routes (use middleware permission:read employees)
    Route::get('/employees', [EmployeeController::class, 'index'])->middleware('permission:employee.read');
    Route::get('/employees/{id}', [EmployeeController::class, 'show'])->middleware('permission:employee.read');

    // User routes (use middleware permission:read users)
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:user.read');
    Route::get('/users/{id}', [UserController::class, 'show'])->middleware('permission:user.read');
    Route::put('/users/{id}', [UserController::class, 'update'])->middleware('permission:user.update');
    Route::delete('/users/{id}', [UserController::class, 'destroy'])->middleware('permission:user.delete');
});


