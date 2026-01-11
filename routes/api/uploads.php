<?php

use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeeFundingAllocationController;
use App\Http\Controllers\Api\EmploymentController;
use App\Http\Controllers\Api\GrantController;
use App\Http\Controllers\Api\PayrollController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {

    // ========================================
    // UPLOADS PREFIX - All file upload routes
    // ========================================
    Route::prefix('uploads')->group(function () {

        // Grant upload
        Route::post('/grant', [GrantController::class, 'upload'])
            ->name('uploads.grant')
            ->middleware('permission:grants_list.edit');

        // Employee upload
        Route::post('/employee', [EmployeeController::class, 'uploadEmployeeData'])
            ->name('uploads.employee')
            ->middleware('permission:employees.edit');

        // Employment upload
        Route::post('/employment', [EmploymentController::class, 'upload'])
            ->name('uploads.employment')
            ->middleware('permission:employment_records.edit');

        // Employee funding allocation upload
        Route::post('/employee-funding-allocation', [EmployeeFundingAllocationController::class, 'upload'])
            ->name('uploads.employee-funding-allocation')
            ->middleware('permission:employee_funding_allocations.edit');

        // Payroll upload
        Route::post('/payroll', [PayrollController::class, 'upload'])
            ->name('uploads.payroll')
            ->middleware('permission:employee_salary.edit');
    });

    // ========================================
    // DOWNLOADS PREFIX - All template download routes
    // ========================================
    Route::prefix('downloads')->group(function () {

        // Grant template download
        Route::get('/grant-template', [GrantController::class, 'downloadTemplate'])
            ->name('downloads.grant-template')
            ->middleware('permission:grants_list.read');

        // Employee template download
        Route::get('/employee-template', [EmployeeController::class, 'downloadEmployeeTemplate'])
            ->name('downloads.employee-template')
            ->middleware('permission:employees.read');

        // Employment template download
        Route::get('/employment-template', [EmploymentController::class, 'downloadEmploymentTemplate'])
            ->name('downloads.employment-template')
            ->middleware('permission:employment_records.read');

        // Employee funding allocation template download
        Route::get('/employee-funding-allocation-template', [EmployeeFundingAllocationController::class, 'downloadTemplate'])
            ->name('downloads.employee-funding-allocation-template')
            ->middleware('permission:employee_funding_allocations.read');

        // Grant items reference download (for funding allocation imports)
        Route::get('/grant-items-reference', [EmployeeFundingAllocationController::class, 'downloadGrantItemsReference'])
            ->name('downloads.grant-items-reference')
            ->middleware('permission:employee_funding_allocations.read');

        // Employee funding allocations reference download (for payroll imports)
        Route::get('/employee-funding-allocations-reference', [PayrollController::class, 'downloadEmployeeFundingAllocationsReference'])
            ->name('downloads.employee-funding-allocations-reference')
            ->middleware('permission:employee_salary.read');

        // Payroll template download
        Route::get('/payroll-template', [PayrollController::class, 'downloadTemplate'])
            ->name('downloads.payroll-template')
            ->middleware('permission:employee_salary.read');
    });
});
