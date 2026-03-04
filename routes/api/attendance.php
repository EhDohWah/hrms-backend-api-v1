<?php

use App\Http\Controllers\Api\V1\AttendanceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Attendance Routes
|--------------------------------------------------------------------------
|
| Daily employee check-in/check-out attendance records.
|
| Permission Model:
| - Uses module-based permissions with Read/Edit access (attendance_admin)
| - Read: GET requests (view lists, details, options)
| - Edit: POST/PUT/DELETE requests (create, update, delete)
|
*/

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('attendances')->middleware('module.permission:attendance_admin')->group(function () {
        Route::get('/options', [AttendanceController::class, 'options']);
        Route::get('/', [AttendanceController::class, 'index']);
        Route::get('/{attendance}', [AttendanceController::class, 'show']);
        Route::post('/', [AttendanceController::class, 'store']);
        Route::put('/{attendance}', [AttendanceController::class, 'update']);
        Route::delete('/batch', [AttendanceController::class, 'destroyBatch']);
        Route::delete('/{attendance}', [AttendanceController::class, 'destroy']);
    });
});
