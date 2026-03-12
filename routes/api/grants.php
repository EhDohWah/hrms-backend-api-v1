<?php

use App\Http\Controllers\Api\V1\GrantController;
use App\Http\Controllers\Api\V1\GrantItemController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // =========================================================================
    // GRANT ITEMS - New RESTful routes (Primary)
    // =========================================================================
    Route::prefix('grant-items')->group(function () {
        Route::get('/', [GrantItemController::class, 'index'])
            ->name('grant-items.index')
            ->middleware('permission:grants_list.read');

        Route::get('/{grantItem}', [GrantItemController::class, 'show'])
            ->name('grant-items.show')
            ->middleware('permission:grants_list.read');

        Route::post('/', [GrantItemController::class, 'store'])
            ->name('grant-items.store')
            ->middleware('permission:grants_list.create');

        Route::put('/{grantItem}', [GrantItemController::class, 'update'])
            ->name('grant-items.update')
            ->middleware('permission:grants_list.update');

        Route::delete('/{grantItem}', [GrantItemController::class, 'destroy'])
            ->name('grant-items.destroy')
            ->middleware('permission:grants_list.delete');
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

        Route::get('/by-id/{grant}', [GrantController::class, 'show'])
            ->name('grants.show')
            ->middleware('permission:grants_list.read');

        Route::get('/by-code/{code}', [GrantController::class, 'showByCode'])
            ->name('grants.showByCode')
            ->middleware('permission:grants_list.read');

        // Create routes:
        Route::post('/', [GrantController::class, 'store'])
            ->name('grants.store')
            ->middleware('permission:grants_list.create');

        // Update routes:
        Route::put('/{grant}', [GrantController::class, 'update'])
            ->name('grants.update')
            ->middleware('permission:grants_list.update');

        // Delete routes:
        Route::delete('/batch', [GrantController::class, 'destroyBatch'])
            ->name('grants.destroy.selected')
            ->middleware('permission:grants_list.delete');

        Route::delete('/{grant}', [GrantController::class, 'destroy'])
            ->name('grants.destroy')
            ->middleware('permission:grants_list.delete');

        // =====================================================================
        // LEGACY ROUTES - Backward compatibility for /grants/items/*
        // These routes redirect to the new GrantItemController
        // TODO: Remove after frontend migration (target: 2026-04-24)
        // =====================================================================
        Route::get('/items', [GrantItemController::class, 'index'])
            ->name('grants.items.index')
            ->middleware('permission:grants_list.read');

        Route::get('/items/{grantItem}', [GrantItemController::class, 'show'])
            ->name('grants.items.show')
            ->middleware('permission:grants_list.read');

        Route::post('/items', [GrantItemController::class, 'store'])
            ->name('grants.items.store')
            ->middleware('permission:grants_list.create');

        Route::put('/items/{grantItem}', [GrantItemController::class, 'update'])
            ->name('grants.items.update')
            ->middleware('permission:grants_list.update');

        Route::delete('/items/{grantItem}', [GrantItemController::class, 'destroy'])
            ->name('grants.items.destroy')
            ->middleware('permission:grants_list.delete');
    });
});
