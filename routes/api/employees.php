<?php

use App\Http\Controllers\Api\V1\EmployeeBeneficiaryController;
use App\Http\Controllers\Api\V1\EmployeeChildrenController;
use App\Http\Controllers\Api\V1\EmployeeController;
use App\Http\Controllers\Api\V1\EmployeeEducationController;
use App\Http\Controllers\Api\V1\EmployeeFundingAllocationController;
use App\Http\Controllers\Api\V1\EmployeeLanguageController;
use App\Http\Controllers\Api\V1\EmployeeTrainingController;
use App\Http\Controllers\Api\V1\TrainingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Employees routes (use submenu-specific permissions: employees.read/edit)
    Route::prefix('employees')->group(function () {
        // Read routes
        Route::get('/tree-search', [EmployeeController::class, 'searchForOrgTree'])->middleware('permission:employees.read');
        Route::get('/export', [EmployeeController::class, 'exportEmployees'])->middleware('permission:employees.read');
        Route::get('/', [EmployeeController::class, 'index'])->middleware('permission:employees.read');
        Route::get('/filter', [EmployeeController::class, 'filterEmployees'])->middleware('permission:employees.read');
        Route::get('/site-records', [EmployeeController::class, 'siteRecords'])->middleware('permission:employees.read');
        Route::get('/staff-id/{staff_id}', [EmployeeController::class, 'showByStaffId'])->where('staff_id', '[0-9]{4}')->middleware('permission:employees.read');
        Route::get('/{employee}', [EmployeeController::class, 'show'])->where('employee', '[0-9]+')->middleware('permission:employees.read');

        // Edit routes (create, update, delete, import)
        Route::post('/upload', [EmployeeController::class, 'uploadEmployeeData'])->middleware('permission:employees.edit');
        Route::post('/', [EmployeeController::class, 'store'])->middleware('permission:employees.edit');
        Route::put('/{employee}', [EmployeeController::class, 'update'])->where('employee', '[0-9]+')->middleware('permission:employees.edit');
        Route::delete('/{employee}', [EmployeeController::class, 'destroy'])->where('employee', '[0-9]+')->middleware('permission:employees.edit');
        Route::post('/{employee}/profile-picture', [EmployeeController::class, 'uploadProfilePicture'])->where('employee', '[0-9]+')->middleware('permission:employees.edit');
        Route::delete('/batch/{ids}', [EmployeeController::class, 'destroyBatch'])->middleware('permission:employees.edit');

        Route::put('/{employee}/basic-information', [EmployeeController::class, 'updateBasicInfo'])->where('employee', '[0-9]+')->middleware('permission:employees.edit');
        Route::put('/{employee}/personal-information', [EmployeeController::class, 'updatePersonalInfo'])->where('employee', '[0-9]+')->middleware('permission:employees.edit');
        Route::put('/{employee}/family-information', [EmployeeController::class, 'updateFamilyInfo'])->where('employee', '[0-9]+')->middleware('permission:employees.edit');
        Route::put('/{employee}/bank-information', [EmployeeController::class, 'updateBankInfo'])->where('employee', '[0-9]+')->middleware('permission:employees.edit');
    });

    // Employee funding allocation routes
    Route::prefix('employee-funding-allocations')->group(function () {
        Route::get('/', [EmployeeFundingAllocationController::class, 'index'])->middleware('permission:employees.read');
        Route::get('/grant-structure', [EmployeeFundingAllocationController::class, 'grantStructure'])->middleware('permission:employees.read');
        Route::get('/employee/{employeeId}', [EmployeeFundingAllocationController::class, 'employeeAllocations'])->middleware('permission:employees.read');
        Route::get('/by-grant-item/{grantItemId}', [EmployeeFundingAllocationController::class, 'byGrantItem'])->middleware('permission:employees.read');
        Route::get('/{allocation}', [EmployeeFundingAllocationController::class, 'show'])->middleware('permission:employees.read');

        // Calculate preview - for real-time UI feedback (no persistence)
        Route::post('/calculate-preview', [EmployeeFundingAllocationController::class, 'calculatePreview'])->middleware('permission:employees.read');

        Route::post('/', [EmployeeFundingAllocationController::class, 'store'])->middleware('permission:employees.edit');
        Route::post('/bulk-deactivate', [EmployeeFundingAllocationController::class, 'bulkDeactivate'])->middleware('permission:employees.edit');
        Route::put('/batch', [EmployeeFundingAllocationController::class, 'batchUpdate'])->middleware('permission:employees.edit');
        Route::put('/employee/{employeeId}', [EmployeeFundingAllocationController::class, 'updateEmployeeAllocations'])->middleware('permission:employees.edit');
        Route::put('/{allocation}', [EmployeeFundingAllocationController::class, 'update'])->middleware('permission:employees.edit');
        Route::delete('/{allocation}', [EmployeeFundingAllocationController::class, 'destroy'])->middleware('permission:employees.edit');
    });

    // Employee children routes (use employees permission since no separate children module)
    Route::prefix('employee-children')->group(function () {
        Route::get('/', [EmployeeChildrenController::class, 'index'])->middleware('permission:employees.read');
        Route::get('/{employeeChild}', [EmployeeChildrenController::class, 'show'])->middleware('permission:employees.read');
        Route::post('/', [EmployeeChildrenController::class, 'store'])->middleware('permission:employees.edit');
        Route::put('/{employeeChild}', [EmployeeChildrenController::class, 'update'])->middleware('permission:employees.edit');
        Route::delete('/{employeeChild}', [EmployeeChildrenController::class, 'destroy'])->middleware('permission:employees.edit');
    });

    // Employee beneficiary routes
    Route::prefix('employee-beneficiaries')->group(function () {
        Route::get('/', [EmployeeBeneficiaryController::class, 'index'])->middleware('permission:employees.read');
        Route::get('/{employeeBeneficiary}', [EmployeeBeneficiaryController::class, 'show'])->middleware('permission:employees.read');
        Route::post('/', [EmployeeBeneficiaryController::class, 'store'])->middleware('permission:employees.edit');
        Route::put('/{employeeBeneficiary}', [EmployeeBeneficiaryController::class, 'update'])->middleware('permission:employees.edit');
        Route::delete('/{employeeBeneficiary}', [EmployeeBeneficiaryController::class, 'destroy'])->middleware('permission:employees.edit');
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
        Route::get('/{training}', [TrainingController::class, 'show'])->middleware('permission:training_list.read');
        Route::post('/', [TrainingController::class, 'store'])->middleware('permission:training_list.edit');
        Route::put('/{training}', [TrainingController::class, 'update'])->middleware('permission:training_list.edit');
        Route::delete('/batch', [TrainingController::class, 'destroyBatch'])->middleware('permission:training_list.edit');
        Route::delete('/{training}', [TrainingController::class, 'destroy'])->middleware('permission:training_list.edit');
    });

    // Employee training routes - Uses employee_training permission
    Route::prefix('employee-trainings')->group(function () {
        Route::get('/', [EmployeeTrainingController::class, 'index'])->middleware('permission:employee_training.read');
        Route::get('/employee/{employee}/summary', [EmployeeTrainingController::class, 'employeeSummary'])->middleware('permission:employee_training.read');
        Route::get('/training/{training}/attendance', [EmployeeTrainingController::class, 'attendanceList'])->middleware('permission:employee_training.read');
        Route::get('/{employeeTraining}', [EmployeeTrainingController::class, 'show'])->middleware('permission:employee_training.read');
        Route::post('/', [EmployeeTrainingController::class, 'store'])->middleware('permission:employee_training.edit');
        Route::put('/{employeeTraining}', [EmployeeTrainingController::class, 'update'])->middleware('permission:employee_training.edit');
        Route::delete('/{employeeTraining}', [EmployeeTrainingController::class, 'destroy'])->middleware('permission:employee_training.edit');
    });
});
