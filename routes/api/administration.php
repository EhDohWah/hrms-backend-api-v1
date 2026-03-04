<?php

use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\PositionController;
use App\Http\Controllers\Api\V1\SectionDepartmentController;
use App\Http\Controllers\Api\V1\SiteController;
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
        Route::get('/{site}', [SiteController::class, 'show']);
        Route::post('/', [SiteController::class, 'store']);
        Route::put('/{site}', [SiteController::class, 'update']);
        Route::delete('/batch', [SiteController::class, 'destroyBatch']);
        Route::delete('/{site}', [SiteController::class, 'destroy']);
    });

    // Departments routes (uses 'departments' module permission)
    Route::prefix('departments')->middleware('module.permission:departments')->group(function () {
        Route::get('/options', [DepartmentController::class, 'options']);
        Route::get('/', [DepartmentController::class, 'index']);
        Route::get('/{department}', [DepartmentController::class, 'show']);
        Route::get('/{department}/positions', [DepartmentController::class, 'positions']);
        Route::get('/{department}/managers', [DepartmentController::class, 'managers']);
        Route::post('/', [DepartmentController::class, 'store']);
        Route::put('/{department}', [DepartmentController::class, 'update']);
        Route::delete('/batch', [DepartmentController::class, 'destroyBatch']);
        Route::delete('/{department}', [DepartmentController::class, 'destroy']);
    });

    // Positions routes (uses 'positions' module permission)
    Route::prefix('positions')->middleware('module.permission:positions')->group(function () {
        Route::get('/options', [PositionController::class, 'options']);
        Route::get('/', [PositionController::class, 'index']);
        Route::get('/{position}', [PositionController::class, 'show']);
        Route::get('/{position}/direct-reports', [PositionController::class, 'directReports']);
        Route::post('/', [PositionController::class, 'store']);
        Route::put('/{position}', [PositionController::class, 'update']);
        Route::delete('/batch', [PositionController::class, 'destroyBatch']);
        Route::delete('/{position}', [PositionController::class, 'destroy']);
    });

    // Section Departments routes (uses 'section_departments' module permission)
    Route::prefix('section-departments')->middleware('module.permission:section_departments')->group(function () {
        Route::get('/options', [SectionDepartmentController::class, 'options']);
        Route::get('/', [SectionDepartmentController::class, 'index']);
        Route::get('/by-department/{departmentId}', [SectionDepartmentController::class, 'byDepartment']);
        Route::get('/{sectionDepartment}', [SectionDepartmentController::class, 'show']);
        Route::post('/', [SectionDepartmentController::class, 'store']);
        Route::put('/{sectionDepartment}', [SectionDepartmentController::class, 'update']);
        Route::delete('/{sectionDepartment}', [SectionDepartmentController::class, 'destroy']);
    });
});
