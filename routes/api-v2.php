<?php

use Illuminate\Support\Facades\Route;

// All v2 endpoints should be explicitly grouped and typically protected with auth + permissions
// Example mobile-focused endpoints (to be implemented as needed)
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('mobile')->group(function () {
        // e.g., GET /api/v2/mobile/ping
        Route::get('/ping', function () {
            return ['success' => true, 'version' => 'v2', 'service' => 'mobile'];
        });
    });

    // Add more routes here
});
