<?php

use App\Http\Controllers\Api\V1\ResignationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Resignation routes - Uses resignation permission (matches database)
    Route::prefix('resignations')->group(function () {
        // Read routes
        Route::get('/', [ResignationController::class, 'index'])->middleware('permission:resignation.read');
        Route::get('/search-employees', [ResignationController::class, 'searchEmployees'])->middleware('permission:resignation.read');
        Route::get('/{resignation}', [ResignationController::class, 'show'])->middleware('permission:resignation.read');
        Route::get('/{resignation}/recommendation-letter', [ResignationController::class, 'generateRecommendationLetter'])->middleware('permission:resignation.read');

        // Create routes
        Route::post('/', [ResignationController::class, 'store'])->middleware('permission:resignation.create');

        // Update routes
        Route::put('/{resignation}', [ResignationController::class, 'update'])->middleware('permission:resignation.update');
        Route::put('/{resignation}/acknowledge', [ResignationController::class, 'acknowledge'])->middleware('permission:resignation.update');

        // Delete routes
        Route::delete('/batch', [ResignationController::class, 'destroyBatch'])->middleware('permission:resignation.delete');
        Route::delete('/{resignation}', [ResignationController::class, 'destroy'])->middleware('permission:resignation.delete');
    });
});
