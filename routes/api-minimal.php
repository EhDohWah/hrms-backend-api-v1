<?php

use Illuminate\Support\Facades\Route;

// Test route
Route::get('/test', function () {
    return response()->json(['message' => 'Test route working']);
});
