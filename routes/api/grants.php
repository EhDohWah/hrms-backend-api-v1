<?php

use App\Http\Controllers\Api\GrantController;
use App\Http\Controllers\Api\OrgFundedAllocationController;
use App\Http\Controllers\Api\PositionSlotController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Grant routes (use middleware permission:read grants)
    Route::prefix('grants')->group(function () {
        // 1) Exact/static routes first:
        Route::get('/', [GrantController::class, 'index'])->name('grants.index')->middleware('permission:grant.read');
        Route::get('/items', [GrantController::class, 'getGrantItems'])->name('grants.items.index')->middleware('permission:grant.read');
        Route::get('/items/{id}', [GrantController::class, 'getGrantItem'])->name('grants.items.show')->middleware('permission:grant.read');
        Route::get('/grant-positions', [GrantController::class, 'getGrantPositions'])->name('grants.grant-positions')->middleware('permission:grant.read');
        Route::post('/upload', [GrantController::class, 'upload'])->name('grants.upload')->middleware('permission:grant.import');
        Route::post('/items', [GrantController::class, 'storeGrantItem'])->name('grants.items.store')->middleware('permission:grant.create');
        Route::post('/', [GrantController::class, 'storeGrant'])->name('grants.store')->middleware('permission:grant.create');
        Route::get('/by-id/{id}', [GrantController::class, 'show'])->name('grants.show')->middleware('permission:grant.read');
        // 2) Wildcards and verbs on {id} last:
        Route::get('/by-code/{code}', [GrantController::class, 'getGrantByCode'])->middleware('permission:grant.read');
        Route::put('/{id}', [GrantController::class, 'updateGrant'])->name('grants.update')->middleware('permission:grant.update');
        Route::delete('/{id}', [GrantController::class, 'deleteGrant'])->name('grants.destroy')->middleware('permission:grant.delete');

        // 3) And likewise for items:
        Route::put('/items/{id}', [GrantController::class, 'updateGrantItem'])->name('grants.items.update')->middleware('permission:grant.update');
        Route::delete('/items/{id}', [GrantController::class, 'deleteGrantItem'])->name('grants.items.destroy')->middleware('permission:grant.delete');
    });

    // Budget line routes removed - using budgetline_code on position_slots

    // Position slot routes
    Route::prefix('position-slots')->group(function () {
        Route::get('/', [PositionSlotController::class, 'index'])->middleware('permission:position_slot.read');
        Route::post('/', [PositionSlotController::class, 'store'])->middleware('permission:position_slot.create');
        Route::get('/{id}', [PositionSlotController::class, 'show'])->middleware('permission:position_slot.read');
        Route::put('/{id}', [PositionSlotController::class, 'update'])->middleware('permission:position_slot.update');
        Route::delete('/{id}', [PositionSlotController::class, 'destroy'])->middleware('permission:position_slot.delete');
    });

    // Org funded allocation routes
    Route::prefix('org-funded-allocations')->group(function () {
        Route::get('/', [OrgFundedAllocationController::class, 'index'])->middleware('permission:employee.read');
        Route::post('/', [OrgFundedAllocationController::class, 'store'])->middleware('permission:employee.create');
        Route::get('/{id}', [OrgFundedAllocationController::class, 'show'])->middleware('permission:employee.read');
        Route::put('/{id}', [OrgFundedAllocationController::class, 'update'])->middleware('permission:employee.update');
        Route::delete('/{id}', [OrgFundedAllocationController::class, 'destroy'])->middleware('permission:employee.delete');
    });
});
