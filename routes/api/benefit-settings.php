<?php

use App\Http\Controllers\Api\BenefitSettingController;
use Illuminate\Support\Facades\Route;

// All benefit settings routes require authentication
Route::middleware('auth:sanctum')->group(function () {
    // Benefit settings CRUD routes - Uses benefit_settings permission
    Route::prefix('benefit-settings')->group(function () {
        Route::get('/', [BenefitSettingController::class, 'index'])->middleware('permission:benefit_settings.read');
        Route::get('/{id}', [BenefitSettingController::class, 'show'])->middleware('permission:benefit_settings.read');
        Route::post('/', [BenefitSettingController::class, 'store'])->middleware('permission:benefit_settings.edit');
        Route::put('/{id}', [BenefitSettingController::class, 'update'])->middleware('permission:benefit_settings.edit');
        Route::delete('/{id}', [BenefitSettingController::class, 'destroy'])->middleware('permission:benefit_settings.edit');
    });
});
