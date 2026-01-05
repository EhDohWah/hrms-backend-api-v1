<?php

use App\Http\Controllers\Api\PersonnelActionController;

Route::middleware(['auth:sanctum'])->group(function () {
    // Personnel actions are related to employee management - uses employees permission
    // Read operations
    Route::middleware(['permission:employees.read'])->group(function () {
        Route::get('/personnel-actions', [PersonnelActionController::class, 'index']);
        Route::get('/personnel-actions/constants', [PersonnelActionController::class, 'getConstants']);
        Route::get('/personnel-actions/{personnelAction}', [PersonnelActionController::class, 'show']);
    });

    // Edit operations (create, update)
    Route::middleware(['permission:employees.edit'])->group(function () {
        Route::post('/personnel-actions', [PersonnelActionController::class, 'store']);
        Route::put('/personnel-actions/{personnelAction}', [PersonnelActionController::class, 'update']);
        Route::patch('/personnel-actions/{personnelAction}', [PersonnelActionController::class, 'update']);
        Route::patch('/personnel-actions/{personnelAction}/approve', [PersonnelActionController::class, 'approve']);
    });
});
