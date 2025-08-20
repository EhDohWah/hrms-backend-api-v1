<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\EmployeeController;
use Maatwebsite\Excel\Facades\Excel;

// Export route (outside of auth middleware)
Route::get('/export-employees', [EmployeeController::class, 'exportEmployees']);

// Include modular route files
require __DIR__ . '/api/admin.php';
require __DIR__ . '/api/employees.php';
require __DIR__ . '/api/grants.php';
require __DIR__ . '/api/payroll.php';
require __DIR__ . '/api/employment.php';


