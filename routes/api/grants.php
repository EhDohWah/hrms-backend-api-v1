<?php

use App\Http\Controllers\Api\GrantController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Grant routes (use submenu-specific permissions: grants_list.read/edit)
    // Note: Upload and download-template routes moved to routes/api/uploads.php
    Route::prefix('grants')->group(function () {
        // 1) Read routes:
        Route::get('/', [GrantController::class, 'index'])->name('grants.index')->middleware('permission:grants_list.read');
        Route::get('/items', [GrantController::class, 'getGrantItems'])->name('grants.items.index')->middleware('permission:grants_list.read');
        Route::get('/items/{id}', [GrantController::class, 'getGrantItem'])->name('grants.items.show')->middleware('permission:grants_list.read');
        Route::get('/grant-positions', [GrantController::class, 'getGrantPositions'])->name('grants.grant-positions')->middleware('permission:grant_position.read');
        Route::get('/by-id/{id}', [GrantController::class, 'show'])->name('grants.show')->middleware('permission:grants_list.read');
        Route::get('/by-code/{code}', [GrantController::class, 'getGrantByCode'])->middleware('permission:grants_list.read');

        // 2) Edit routes (create, update, delete):
        Route::post('/items', [GrantController::class, 'storeGrantItem'])->name('grants.items.store')->middleware('permission:grants_list.edit');
        Route::post('/', [GrantController::class, 'storeGrant'])->name('grants.store')->middleware('permission:grants_list.edit');
        Route::put('/{id}', [GrantController::class, 'updateGrant'])->name('grants.update')->middleware('permission:grants_list.edit');
        Route::delete('/{id}', [GrantController::class, 'deleteGrant'])->name('grants.destroy')->middleware('permission:grants_list.edit');
        Route::put('/items/{id}', [GrantController::class, 'updateGrantItem'])->name('grants.items.update')->middleware('permission:grants_list.edit');
        Route::delete('/items/{id}', [GrantController::class, 'deleteGrantItem'])->name('grants.items.destroy')->middleware('permission:grants_list.edit');
    });
});
