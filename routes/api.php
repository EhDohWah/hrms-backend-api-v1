<?php

use App\Http\Controllers\Api\EmployeeController;
use Illuminate\Support\Facades\Route;

// Export route (outside of auth middleware)
Route::get('/export-employees', [EmployeeController::class, 'exportEmployees']);

// Versioned groups (non-breaking): keep all existing routes under v1
Route::prefix('v1')->group(function () {
    require __DIR__.'/api/admin.php';
    require __DIR__.'/api/employees.php';
    require __DIR__.'/api/grants.php';
    require __DIR__.'/api/payroll.php';
    require __DIR__.'/api/employment.php';
    require __DIR__.'/api/personnel_actions.php';
});

// v2 scaffold for mobile-focused endpoints
Route::prefix('v2')->group(function () {
    $v2 = __DIR__.'/api-v2.php';
    if (file_exists($v2)) {
        require $v2;
    }
});
