<?php

use App\Http\Controllers\Api\GrantController;
use App\Http\Controllers\Api\GrantItemController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // =========================================================================
    // GRANT ITEMS - New RESTful routes (Primary)
    // =========================================================================
    Route::prefix('grant-items')->group(function () {
        Route::get('/', [GrantItemController::class, 'index'])
            ->name('grant-items.index')
            ->middleware('permission:grants_list.read');

        Route::get('/{id}', [GrantItemController::class, 'show'])
            ->name('grant-items.show')
            ->middleware('permission:grants_list.read');

        Route::post('/', [GrantItemController::class, 'store'])
            ->name('grant-items.store')
            ->middleware('permission:grants_list.edit');

        Route::put('/{id}', [GrantItemController::class, 'update'])
            ->name('grant-items.update')
            ->middleware('permission:grants_list.edit');

        Route::delete('/{id}', [GrantItemController::class, 'destroy'])
            ->name('grant-items.destroy')
            ->middleware('permission:grants_list.edit');
    });

    // =========================================================================
    // GRANTS - Main grant routes
    // =========================================================================
    Route::prefix('grants')->group(function () {
        // Read routes:
        Route::get('/', [GrantController::class, 'index'])
            ->name('grants.index')
            ->middleware('permission:grants_list.read');

        Route::get('/grant-positions', [GrantController::class, 'positions'])
            ->name('grants.grant-positions')
            ->middleware('permission:grant_position.read');

        Route::get('/by-id/{id}', [GrantController::class, 'show'])
            ->name('grants.show')
            ->middleware('permission:grants_list.read');

        Route::get('/by-code/{code}', [GrantController::class, 'showByCode'])
            ->name('grants.showByCode')
            ->middleware('permission:grants_list.read');

        // Edit routes:
        Route::post('/', [GrantController::class, 'store'])
            ->name('grants.store')
            ->middleware('permission:grants_list.edit');

        Route::delete('/batch', [GrantController::class, 'destroyBatch'])
            ->name('grants.destroy.selected')
            ->middleware('permission:grants_list.edit');

        Route::put('/{id}', [GrantController::class, 'update'])
            ->name('grants.update')
            ->middleware('permission:grants_list.edit');

        Route::delete('/{id}', [GrantController::class, 'destroy'])
            ->name('grants.destroy')
            ->middleware('permission:grants_list.edit');

        // =====================================================================
        // LEGACY ROUTES - Backward compatibility for /grants/items/*
        // These routes redirect to the new GrantItemController
        // TODO: Remove after frontend migration (target: 2026-04-24)
        // =====================================================================
        Route::get('/items', [GrantItemController::class, 'index'])
            ->name('grants.items.index')
            ->middleware('permission:grants_list.read');

        Route::get('/items/{id}', [GrantItemController::class, 'show'])
            ->name('grants.items.show')
            ->middleware('permission:grants_list.read');

        Route::post('/items', [GrantItemController::class, 'store'])
            ->name('grants.items.store')
            ->middleware('permission:grants_list.edit');

        Route::put('/items/{id}', [GrantItemController::class, 'update'])
            ->name('grants.items.update')
            ->middleware('permission:grants_list.edit');

        Route::delete('/items/{id}', [GrantItemController::class, 'destroy'])
            ->name('grants.items.destroy')
            ->middleware('permission:grants_list.edit');
    });
});
