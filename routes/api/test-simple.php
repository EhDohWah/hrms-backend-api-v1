<?php

use Illuminate\Support\Facades\Route;

// Test route without any complex middleware
Route::get('/test-simple', function () {
    return response()->json(['message' => 'Simple test route working']);
});

// Test route with basic auth middleware only
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/test-auth', function () {
        return response()->json(['message' => 'Auth test route working']);
    });
});
