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
use App\Http\Controllers\Api\EmploymentTypeController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\DepartmentpositionController;


// Public route for login
Route::post('/login', [AuthController::class, 'login']);


// Group routes that require authentication via Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Optional: logout endpoint
    Route::post('/logout', [AuthController::class, 'logout']);
    // Refresh token route – available only if the user is still authenticated
    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);

    // Lookups routes
    Route::prefix('lookups')->group(function () {
        Route::get('/', [LookupController::class, 'index']);
        Route::post('/', [LookupController::class, 'store'])->middleware('permission:admin.create');
        Route::get('/{id}', [LookupController::class, 'show'])->middleware('permission:admin.read');
        Route::put('/{id}', [LookupController::class, 'update'])->middleware('permission:admin.update');
        Route::delete('/{id}', [LookupController::class, 'destroy'])->middleware('permission:admin.delete');
    });


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

    // Employment routes
    Route::prefix('employments')->group(function () {
        Route::get('/', [EmploymentController::class, 'index'])->middleware('permission:employment.read');
        Route::get('/{id}', [EmploymentController::class, 'show'])->middleware('permission:employment.read');
        Route::post('/', [EmploymentController::class, 'store'])->middleware('permission:employment.create');
        Route::put('/{id}', [EmploymentController::class, 'update'])->middleware('permission:employment.update');
        Route::delete('/{id}', [EmploymentController::class, 'destroy'])->middleware('permission:employment.delete');
        Route::delete('/employment-grant-allocations/{id}', [EmploymentController::class, 'deleteEmploymentGrantAllocation'])->middleware('permission:employment.delete');
        Route::post('/employment-grant-allocations', [EmploymentController::class, 'addEmploymentGrantAllocation'])->middleware('permission:employment.create');
    });

    // Department position routes (use middleware permission:read department positions)
    Route::prefix('department-positions')->group(function () {
        Route::get('/', [DepartmentpositionController::class, 'index'])->middleware('permission:employment.read');
        Route::get('/{id}', [DepartmentpositionController::class, 'show'])->middleware('permission:employment.read');
        Route::post('/', [DepartmentpositionController::class, 'store'])->middleware('permission:employment.create');
        Route::put('/{id}', [DepartmentpositionController::class, 'update'])->middleware('permission:employment.update');
        Route::delete('/{id}', [DepartmentpositionController::class, 'destroy'])->middleware('permission:employment.delete');
    });

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

    // Grant routes (use middleware permission:read grants)
    Route::prefix('grants')->group(function () {
        Route::get('/', [GrantController::class, 'index'])->name('grants.index')->middleware('permission:grant.read');
        Route::get('/items', [GrantController::class, 'getGrantItems'])->name('grants.items.index')->middleware('permission:grant.read');
        Route::post('/', [GrantController::class, 'storeGrant'])->name('grants.store')->middleware('permission:grant.create');
        Route::post('/items', [GrantController::class, 'storeGrantItem'])->name('grants.items.store')->middleware('permission:grant.create');
        Route::post('/upload', [GrantController::class, 'upload'])->name('grants.upload')->middleware('permission:grant.import');
        Route::delete('/{id}', [GrantController::class, 'deleteGrant'])->name('grants.destroy')->middleware('permission:grant.delete');
        Route::delete('/items/{id}', [GrantController::class, 'deleteGrantItem'])->name('grants.items.destroy')->middleware('permission:grant.delete');
        Route::put('/{id}', [GrantController::class, 'updateGrant'])->name('grants.update')->middleware('permission:grant.update');
        Route::put('/items/{id}', [GrantController::class, 'updateGrantItem'])->name('grants.items.update')->middleware('permission:grant.update');
        Route::get('/grant-positions', [GrantController::class, 'getGrantPositions'])->name('grants.grant-positions')->middleware('permission:grant.read');
    });

    // Interview routes (use middleware permission:read interviews)
    Route::prefix('interviews')->group(function () {
        Route::get('/', [InterviewController::class, 'index'])->middleware('permission:interview.read');
        Route::post('/', [InterviewController::class, 'store'])->middleware('permission:interview.create');
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


