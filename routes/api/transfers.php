<?php

use App\Http\Controllers\Api\V1\TransferController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::middleware(['permission:transfer.read'])->group(function () {
        Route::get('/transfers', [TransferController::class, 'index']);
        Route::get('/transfers/{transfer}', [TransferController::class, 'show']);
    });

    Route::middleware(['permission:transfer.create'])->group(function () {
        Route::post('/transfers', [TransferController::class, 'store']);
    });

    Route::middleware(['permission:transfer.delete'])->group(function () {
        Route::delete('/transfers/{transfer}', [TransferController::class, 'destroy']);
    });
});
