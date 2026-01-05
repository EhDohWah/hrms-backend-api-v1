<?php

use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\SectionDepartmentController;
use App\Http\Controllers\Api\SiteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Organizational Structure Routes
|--------------------------------------------------------------------------
|
| These routes handle the organizational hierarchy: Sites, Departments,
| Positions, and Section Departments.
|
| Permission Model:
| - Uses module-based permissions with Read/Edit access
| - Read: GET requests (view lists, details)
| - Edit: POST/PUT/DELETE requests (create, update, delete)
|
*/

// All routes require authentication via Sanctum
Route::middleware('auth:sanctum')->group(function () {

    // Sites routes (uses 'sites' module permission)
    Route::prefix('sites')->middleware('module.permission:sites')->group(function () {
        Route::get('/options', [SiteController::class, 'options']);
        Route::get('/', [SiteController::class, 'index']);
        Route::get('/{id}', [SiteController::class, 'show']);
        Route::post('/', [SiteController::class, 'store']);
        Route::put('/{id}', [SiteController::class, 'update']);
        Route::delete('/{id}', [SiteController::class, 'destroy']);
    });

    // Departments routes (uses 'departments' module permission)
    Route::prefix('departments')->middleware('module.permission:departments')->group(function () {
        Route::get('/options', [DepartmentController::class, 'options']);
        Route::get('/', [DepartmentController::class, 'index']);
        Route::get('/{id}', [DepartmentController::class, 'show']);
        Route::get('/{id}/positions', [DepartmentController::class, 'positions']);
        Route::get('/{id}/managers', [DepartmentController::class, 'managers']);
        Route::post('/', [DepartmentController::class, 'store']);
        Route::put('/{id}', [DepartmentController::class, 'update']);
        Route::delete('/{id}', [DepartmentController::class, 'destroy']);
    });

    // Positions routes (uses 'positions' module permission)
    Route::prefix('positions')->middleware('module.permission:positions')->group(function () {
        Route::get('/options', [PositionController::class, 'options']);
        Route::get('/', [PositionController::class, 'index']);
        Route::get('/{id}', [PositionController::class, 'show']);
        Route::get('/{id}/direct-reports', [PositionController::class, 'directReports']);
        Route::post('/', [PositionController::class, 'store']);
        Route::put('/{id}', [PositionController::class, 'update']);
        Route::delete('/{id}', [PositionController::class, 'destroy']);
    });

    // Section Departments routes (uses 'section_departments' module permission)
    Route::prefix('section-departments')->middleware('module.permission:section_departments')->group(function () {
        Route::get('/options', [SectionDepartmentController::class, 'options']);
        Route::get('/', [SectionDepartmentController::class, 'index']);
        Route::get('/by-department/{departmentId}', [SectionDepartmentController::class, 'getByDepartment']);
        Route::get('/{id}', [SectionDepartmentController::class, 'show']);
        Route::post('/', [SectionDepartmentController::class, 'store']);
        Route::put('/{id}', [SectionDepartmentController::class, 'update']);
        Route::delete('/{id}', [SectionDepartmentController::class, 'destroy']);
    });
});
