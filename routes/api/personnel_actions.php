<?php

use App\Http\Controllers\Api\V1\PersonnelActionController;

Route::middleware(['auth:sanctum'])->group(function () {
    // Read operations
    Route::middleware(['permission:personnel_actions.read'])->group(function () {
        Route::get('/personnel-actions', [PersonnelActionController::class, 'index']);
        Route::get('/personnel-actions/constants', [PersonnelActionController::class, 'constants']);
        Route::get('/personnel-actions/{personnelAction}', [PersonnelActionController::class, 'show']);
    });

    // Write operations
    Route::middleware(['permission:personnel_actions.create'])->group(function () {
        Route::post('/personnel-actions', [PersonnelActionController::class, 'store']);
    });

    Route::middleware(['permission:personnel_actions.update'])->group(function () {
        Route::put('/personnel-actions/{personnelAction}', [PersonnelActionController::class, 'update']);
        Route::patch('/personnel-actions/{personnelAction}', [PersonnelActionController::class, 'update']);
        Route::patch('/personnel-actions/{personnelAction}/approve', [PersonnelActionController::class, 'approve']);
    });

    Route::middleware(['permission:personnel_actions.delete'])->group(function () {
        Route::delete('/personnel-actions/{personnelAction}', [PersonnelActionController::class, 'destroy']);
    });
});
