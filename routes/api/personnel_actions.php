<?php

use App\Http\Controllers\Api\PersonnelActionController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::middleware(['permission:personnel_action.read'])->group(function () {
        Route::get('/personnel-actions', [PersonnelActionController::class, 'index']);
        Route::get('/personnel-actions/constants', [PersonnelActionController::class, 'getConstants']);
        Route::get('/personnel-actions/{personnelAction}', [PersonnelActionController::class, 'show']);
    });

    Route::middleware(['permission:personnel_action.create'])->group(function () {
        Route::post('/personnel-actions', [PersonnelActionController::class, 'store']);
    });

    Route::middleware(['permission:personnel_action.update'])->group(function () {
        Route::put('/personnel-actions/{personnelAction}', [PersonnelActionController::class, 'update']);
        Route::patch('/personnel-actions/{personnelAction}', [PersonnelActionController::class, 'update']);
        Route::patch('/personnel-actions/{personnelAction}/approve', [PersonnelActionController::class, 'approve']);
    });
});
