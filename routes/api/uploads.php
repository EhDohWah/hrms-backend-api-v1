<?php

use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmploymentController;
use App\Http\Controllers\Api\GrantController;
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
    });
});

