<?php

use App\Http\Controllers\Api\EmployeeBeneficiaryController;
use App\Http\Controllers\Api\EmployeeChildrenController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeeEducationController;
use App\Http\Controllers\Api\EmployeeFundingAllocationController;
use App\Http\Controllers\Api\EmployeeLanguageController;
use App\Http\Controllers\Api\EmployeeTrainingController;
use App\Http\Controllers\Api\TrainingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Employees routes (use submenu-specific permissions: employees.read/edit)
    Route::prefix('employees')->group(function () {
        // Read routes
        Route::get('/tree-search', [EmployeeController::class, 'getEmployeesForTreeSearch'])->middleware('permission:employees.read');
        Route::get('/', [EmployeeController::class, 'index'])->middleware('permission:employees.read');
        Route::get('/{id}', [EmployeeController::class, 'employeeDetails'])->middleware('permission:employees.read');
        Route::get('/filter', [EmployeeController::class, 'filterEmployees'])->middleware('permission:employees.read');
        Route::get('/site-records', [EmployeeController::class, 'getSiteRecords'])->middleware('permission:employees.read');
        Route::get('/staff-id/{staff_id}', [EmployeeController::class, 'show'])->where('staff_id', '[0-9]{4}')->middleware('permission:employees.read');

        // Edit routes (create, update, delete, import)
        Route::post('/upload', [EmployeeController::class, 'uploadEmployeeData'])->middleware('permission:employees.edit');
        Route::post('/', [EmployeeController::class, 'store'])->middleware('permission:employees.edit');
        Route::put('/{id}', [EmployeeController::class, 'update'])->middleware('permission:employees.edit');
        Route::delete('/{id}', [EmployeeController::class, 'destroy'])->middleware('permission:employees.edit');
        Route::post('/{id}/profile-picture', [EmployeeController::class, 'uploadProfilePicture'])->middleware('permission:employees.edit');
        Route::delete('/delete-selected/{ids}', [EmployeeController::class, 'deleteSelectedEmployees'])->middleware('permission:employees.edit');

        Route::put('/{employee}/basic-information', [EmployeeController::class, 'updateEmployeeBasicInformation'])->where('employee', '[0-9]+')->middleware('permission:employees.edit');
        Route::put('/{employee}/personal-information', [EmployeeController::class, 'updateEmployeePersonalInformation'])->where('employee', '[0-9]+')->middleware('permission:employees.edit');
        Route::put('/{employee}/family-information', [EmployeeController::class, 'updateEmployeeFamilyInformation'])->where('employee', '[0-9]+')->middleware('permission:employees.edit');
        Route::put('/{id}/bank-information', [EmployeeController::class, 'updateBankInformation'])->where('id', '[0-9]+')->middleware('permission:employees.edit');
    });

    // Employee funding allocation routes
    Route::prefix('employee-funding-allocations')->group(function () {
        Route::get('/', [EmployeeFundingAllocationController::class, 'index'])->middleware('permission:employees.read');
        Route::get('/grant-structure', [EmployeeFundingAllocationController::class, 'getGrantStructure'])->middleware('permission:employees.read');
        Route::get('/employee/{employeeId}', [EmployeeFundingAllocationController::class, 'getEmployeeAllocations'])->middleware('permission:employees.read');
        Route::get('/{id}', [EmployeeFundingAllocationController::class, 'show'])->middleware('permission:employees.read');
        Route::get('/by-grant-item/{grantItemId}', [EmployeeFundingAllocationController::class, 'getByGrantItem'])->middleware('permission:employees.read');
        
        // Calculate preview - for real-time UI feedback (no persistence)
        Route::post('/calculate-preview', [EmployeeFundingAllocationController::class, 'calculatePreview'])->middleware('permission:employees.read');
        
        Route::post('/', [EmployeeFundingAllocationController::class, 'store'])->middleware('permission:employees.edit');
        Route::post('/bulk-deactivate', [EmployeeFundingAllocationController::class, 'bulkDeactivate'])->middleware('permission:employees.edit');
        Route::put('/{id}', [EmployeeFundingAllocationController::class, 'update'])->middleware('permission:employees.edit');
        Route::delete('/{id}', [EmployeeFundingAllocationController::class, 'destroy'])->middleware('permission:employees.edit');
        Route::put('/employee/{employeeId}', [EmployeeFundingAllocationController::class, 'updateEmployeeAllocations'])->middleware('permission:employees.edit');
    });

    // Employee children routes (use employees permission since no separate children module)
    Route::prefix('employee-children')->group(function () {
        Route::get('/', [EmployeeChildrenController::class, 'index'])->middleware('permission:employees.read');
        Route::get('/{id}', [EmployeeChildrenController::class, 'show'])->middleware('permission:employees.read');
        Route::post('/', [EmployeeChildrenController::class, 'store'])->middleware('permission:employees.edit');
        Route::put('/{id}', [EmployeeChildrenController::class, 'update'])->middleware('permission:employees.edit');
        Route::delete('/{id}', [EmployeeChildrenController::class, 'destroy'])->middleware('permission:employees.edit');
    });

    // Employee beneficiary routes
    Route::prefix('employee-beneficiaries')->group(function () {
        Route::get('/', [EmployeeBeneficiaryController::class, 'index'])->middleware('permission:employees.read');
        Route::get('/{id}', [EmployeeBeneficiaryController::class, 'show'])->middleware('permission:employees.read');
        Route::post('/', [EmployeeBeneficiaryController::class, 'store'])->middleware('permission:employees.edit');
        Route::put('/{id}', [EmployeeBeneficiaryController::class, 'update'])->middleware('permission:employees.edit');
        Route::delete('/{id}', [EmployeeBeneficiaryController::class, 'destroy'])->middleware('permission:employees.edit');
    });

    // Employee education routes
    Route::prefix('employee-education')->group(function () {
        Route::get('/', [EmployeeEducationController::class, 'index'])->middleware('permission:employees.read');
        Route::get('/{employeeEducation}', [EmployeeEducationController::class, 'show'])->middleware('permission:employees.read');
        Route::post('/', [EmployeeEducationController::class, 'store'])->middleware('permission:employees.edit');
        Route::put('/{employeeEducation}', [EmployeeEducationController::class, 'update'])->middleware('permission:employees.edit');
        Route::delete('/{employeeEducation}', [EmployeeEducationController::class, 'destroy'])->middleware('permission:employees.edit');
    });

    // Employee language routes
    Route::prefix('employee-language')->group(function () {
        Route::get('/', [EmployeeLanguageController::class, 'index'])->middleware('permission:employees.read');
        Route::get('/{id}', [EmployeeLanguageController::class, 'show'])->middleware('permission:employees.read');
        Route::post('/', [EmployeeLanguageController::class, 'store'])->middleware('permission:employees.edit');
        Route::put('/{id}', [EmployeeLanguageController::class, 'update'])->middleware('permission:employees.edit');
        Route::delete('/{id}', [EmployeeLanguageController::class, 'destroy'])->middleware('permission:employees.edit');
    });

    // Training routes - Uses training_list permission
    Route::prefix('trainings')->group(function () {
        Route::get('/', [TrainingController::class, 'index'])->middleware('permission:training_list.read');
        Route::get('/{id}', [TrainingController::class, 'show'])->middleware('permission:training_list.read');
        Route::post('/', [TrainingController::class, 'store'])->middleware('permission:training_list.edit');
        Route::put('/{id}', [TrainingController::class, 'update'])->middleware('permission:training_list.edit');
        Route::delete('/{id}', [TrainingController::class, 'destroy'])->middleware('permission:training_list.edit');
    });

    // Employee training routes - Uses employee_training permission
    Route::prefix('employee-trainings')->group(function () {
        Route::get('/', [EmployeeTrainingController::class, 'index'])->middleware('permission:employee_training.read');
        Route::get('/employee/{employee_id}/summary', [EmployeeTrainingController::class, 'getEmployeeTrainingSummary'])->middleware('permission:employee_training.read');
        Route::get('/training/{training_id}/attendance', [EmployeeTrainingController::class, 'getTrainingAttendanceList'])->middleware('permission:employee_training.read');
        Route::get('/{id}', [EmployeeTrainingController::class, 'show'])->middleware('permission:employee_training.read');
        Route::post('/', [EmployeeTrainingController::class, 'store'])->middleware('permission:employee_training.edit');
        Route::put('/{id}', [EmployeeTrainingController::class, 'update'])->middleware('permission:employee_training.edit');
        Route::delete('/{id}', [EmployeeTrainingController::class, 'destroy'])->middleware('permission:employee_training.edit');
    });
});
