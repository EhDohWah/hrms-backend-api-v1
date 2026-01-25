<?php

use App\Http\Controllers\Api\ResignationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Resignation routes - Uses resignation permission (matches database)
    Route::prefix('resignations')->group(function () {
        // Read routes
        Route::get('/', [ResignationController::class, 'index'])->middleware('permission:resignation.read');
        Route::get('/search-employees', [ResignationController::class, 'searchEmployees'])->middleware('permission:resignation.read');
        Route::get('/{id}', [ResignationController::class, 'show'])->middleware('permission:resignation.read');
        Route::get('/{id}/recommendation-letter', [ResignationController::class, 'generateRecommendationLetter'])->middleware('permission:resignation.read');

        // Edit routes
        Route::post('/', [ResignationController::class, 'store'])->middleware('permission:resignation.edit');
        Route::put('/{id}', [ResignationController::class, 'update'])->middleware('permission:resignation.edit');
        Route::put('/{id}/acknowledge', [ResignationController::class, 'acknowledge'])->middleware('permission:resignation.edit');
        Route::delete('/{id}', [ResignationController::class, 'destroy'])->middleware('permission:resignation.edit');
    });
});
