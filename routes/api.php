<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\GrantController;
use App\Http\Controllers\Api\InterviewController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\WorklocationController;
use App\Http\Controllers\Api\EmploymentController;

// Public route for login
Route::post('/login', [AuthController::class, 'login']);

// Group routes that require authentication via Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Optional: logout endpoint
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/user', [UserController::class, 'getUser'])->middleware('permission:user.read');
    Route::post('/profile-picture', [UserController::class, 'updateProfilePicture'])->middleware('permission:user.update');
    Route::post('/username', [UserController::class, 'updateUsername'])->middleware('permission:user.update');
    Route::post('/password', [UserController::class, 'updatePassword'])->middleware('permission:user.update');
    Route::post('/email', [UserController::class, 'updateEmail'])->middleware('permission:user.update');

    // Employees routes (use middleware permission:read employees)
    Route::prefix('employees')->group(function () {
        Route::get('/', [EmployeeController::class, 'index'])->middleware('permission:employee.read');
        Route::get('/filter', [EmployeeController::class, 'filterEmployees'])->middleware('permission:employee.read');
        Route::get('/site-records', [EmployeeController::class, 'getSiteRecords'])->middleware('permission:employee.read');
        Route::get('/{id}', [EmployeeController::class, 'show'])->middleware('permission:employee.read');
        Route::post('/', [EmployeeController::class, 'store'])->middleware('permission:employee.create');
        Route::put('/{id}', [EmployeeController::class, 'update'])->middleware('permission:employee.update');
        Route::delete('/{id}', [EmployeeController::class, 'destroy'])->middleware('permission:employee.delete');
        Route::post('/{id}/profile-picture', [EmployeeController::class, 'uploadProfilePicture'])->middleware('permission:employee.update');
    });

    // Employment routes (use middleware permission:read employments)
    Route::prefix('employments')->group(function () {
        Route::get('/', [EmploymentController::class, 'index'])->middleware('permission:employment.read');
        Route::get('/{id}', [EmploymentController::class, 'show'])->middleware('permission:employment.read');
        Route::post('/', [EmploymentController::class, 'store'])->middleware('permission:employment.create');
        Route::put('/{id}', [EmploymentController::class, 'update'])->middleware('permission:employment.update');
        Route::delete('/{id}', [EmploymentController::class, 'destroy'])->middleware('permission:employment.delete');
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
        Route::get('/', [GrantController::class, 'index'])->name('grants.index')->middleware('permission:grant.read');
        Route::get('/items', [GrantController::class, 'getGrantItems'])->name('grants.items.index')->middleware('permission:grant.read');
        Route::post('/', [GrantController::class, 'storeGrant'])->name('grants.store')->middleware('permission:grant.create');
        Route::post('/items', [GrantController::class, 'storeGrantItem'])->name('grants.items.store')->middleware('permission:grant.create');
        Route::post('/upload', [GrantController::class, 'upload'])->name('grants.upload')->middleware('permission:grant.create');
    });

    // Interview routes (use middleware permission:read interviews)
    Route::prefix('interviews')->group(function () {
        Route::get('/', [InterviewController::class, 'index'])->middleware('permission:interview.read');
        Route::post('/', [InterviewController::class, 'store'])->middleware('permission:interview.create');
    });

    // Department routes (use middleware permission:read departments)
    Route::prefix('departments')->group(function () {
        Route::get('/', [DepartmentController::class, 'index'])->middleware('permission:employee.read');
        Route::get('/{id}', [DepartmentController::class, 'show'])->middleware('permission:employee.read');
        Route::post('/', [DepartmentController::class, 'store'])->middleware('permission:employee.create');
        Route::put('/{id}', [DepartmentController::class, 'update'])->middleware('permission:employee.update');
        Route::delete('/{id}', [DepartmentController::class, 'destroy'])->middleware('permission:employee.delete');
    });

    // Position routes (use middleware permission:read positions)
        Route::prefix('positions')->group(function () {
        Route::get('/', [PositionController::class, 'index'])->middleware('permission:employee.read');
        Route::get('/{id}', [PositionController::class, 'show'])->middleware('permission:employee.read');
        Route::post('/', [PositionController::class, 'store'])->middleware('permission:employee.create');
        Route::put('/{id}', [PositionController::class, 'update'])->middleware('permission:employee.update');
        Route::delete('/{id}', [PositionController::class, 'destroy'])->middleware('permission:employee.delete');
    });

    // Work location routes (use middleware permission:read work locations)
    Route::prefix('worklocations')->group(function () {
        Route::get('/', [WorklocationController::class, 'index'])->middleware('permission:employee.read');
        Route::get('/{id}', [WorklocationController::class, 'show'])->middleware('permission:employee.read');
        Route::post('/', [WorklocationController::class, 'store'])->middleware('permission:employee.create');
        Route::put('/{id}', [WorklocationController::class, 'update'])->middleware('permission:employee.update');
        Route::delete('/{id}', [WorklocationController::class, 'destroy'])->middleware('permission:employee.delete');
    });

});


