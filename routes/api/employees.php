<?php

use App\Http\Controllers\Api\EmployeeBeneficiaryController;
use App\Http\Controllers\Api\EmployeeChildrenController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\EmployeeEducationController;
use App\Http\Controllers\Api\EmployeeFundingAllocationController;
use App\Http\Controllers\Api\EmployeeGrantAllocationController;
use App\Http\Controllers\Api\EmployeeLanguageController;
use App\Http\Controllers\Api\EmployeeTrainingController;
use App\Http\Controllers\Api\TrainingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Employees routes (use middleware permission:read employees)
    Route::prefix('employees')->group(function () {
        Route::get('/tree-search', [EmployeeController::class, 'getEmployeesForTreeSearch'])->middleware('permission:employee.read');
        Route::post('/upload', [EmployeeController::class, 'uploadEmployeeData'])->middleware('permission:employee.import');
        Route::get('/', [EmployeeController::class, 'index'])->middleware('permission:employee.read');
        Route::get('/{id}', [EmployeeController::class, 'employeeDetails'])->middleware('permission:employee.read');
        Route::get('/filter', [EmployeeController::class, 'filterEmployees'])->middleware('permission:employee.read');
        Route::get('/site-records', [EmployeeController::class, 'getSiteRecords'])->middleware('permission:employee.read');
        Route::get('/staff-id/{staff_id}', [EmployeeController::class, 'show'])->where('staff_id', '[0-9]{4}')->middleware('permission:employee.read');
        Route::post('/', [EmployeeController::class, 'store'])->middleware('permission:employee.create');
        Route::put('/{id}', [EmployeeController::class, 'update'])->middleware('permission:employee.update');
        Route::delete('/{id}', [EmployeeController::class, 'destroy'])->middleware('permission:employee.delete');
        Route::post('/{id}/profile-picture', [EmployeeController::class, 'uploadProfilePicture'])->middleware('permission:employee.update');
        Route::delete('/delete-selected/{ids}', [EmployeeController::class, 'deleteSelectedEmployees'])->middleware('permission:employee.delete');

        Route::put('/{employee}/basic-information', [EmployeeController::class, 'updateEmployeeBasicInformation'])->where('employee', '[0-9]+')->middleware('permission:employee.update');
        Route::put('/{employee}/personal-information', [EmployeeController::class, 'updateEmployeePersonalInformation'])->where('employee', '[0-9]+')->middleware('permission:employee.update');
        Route::put('/{employee}/family-information', [EmployeeController::class, 'updateEmployeeFamilyInformation'])->where('employee', '[0-9]+')->middleware('permission:employee.update');
        Route::put('/{id}/bank-information', [EmployeeController::class, 'updateBankInformation'])->where('id', '[0-9]+')->middleware('permission:employee.update');
    });

    // Employee grant allocation routes
    Route::prefix('employee-grant-allocations')->group(function () {
        Route::get('/', [EmployeeGrantAllocationController::class, 'index'])->middleware('permission:employee.read');
        Route::post('/', [EmployeeGrantAllocationController::class, 'store'])->middleware('permission:employee.create');
        Route::get('/grant-structure', [EmployeeGrantAllocationController::class, 'getGrantStructure'])->middleware('permission:employee.read');
        Route::post('/bulk-deactivate', [EmployeeGrantAllocationController::class, 'bulkDeactivate'])->middleware('permission:employee.update');
        Route::get('/employee/{employee_id}', [EmployeeGrantAllocationController::class, 'getEmployeeAllocations'])->middleware('permission:employee.read');
        Route::get('/{id}', [EmployeeGrantAllocationController::class, 'show'])->middleware('permission:employee.read');
        Route::put('/{id}', [EmployeeGrantAllocationController::class, 'update'])->middleware('permission:employee.update');
        Route::delete('/{id}', [EmployeeGrantAllocationController::class, 'destroy'])->middleware('permission:employee.delete');
        Route::put('/employee/{employee_id}', [EmployeeGrantAllocationController::class, 'updateEmployeeAllocations'])->middleware('permission:employee.update');
    });

    // Employee funding allocation routes
    Route::prefix('employee-funding-allocations')->group(function () {
        Route::get('/', [EmployeeFundingAllocationController::class, 'index'])->middleware('permission:employee.read');
        Route::post('/', [EmployeeFundingAllocationController::class, 'store'])->middleware('permission:employee.create');
        Route::get('/grant-structure', [EmployeeFundingAllocationController::class, 'getGrantStructure'])->middleware('permission:employee.read');
        Route::post('/bulk-deactivate', [EmployeeFundingAllocationController::class, 'bulkDeactivate'])->middleware('permission:employee.update');
        Route::get('/employee/{employeeId}', [EmployeeFundingAllocationController::class, 'getEmployeeAllocations'])->middleware('permission:employee.read');
        Route::get('/{id}', [EmployeeFundingAllocationController::class, 'show'])->middleware('permission:employee.read');
        Route::put('/{id}', [EmployeeFundingAllocationController::class, 'update'])->middleware('permission:employee.update');
        Route::delete('/{id}', [EmployeeFundingAllocationController::class, 'destroy'])->middleware('permission:employee.delete');
        Route::put('/employee/{employeeId}', [EmployeeFundingAllocationController::class, 'updateEmployeeAllocations'])->middleware('permission:employee.update');
        Route::get('/by-grant-item/{grantItemId}', [EmployeeFundingAllocationController::class, 'getByGrantItem'])->middleware('permission:employee.read');
    });

    // Employee children routes
    Route::prefix('employee-children')->group(function () {
        Route::get('/', [EmployeeChildrenController::class, 'index'])->middleware('permission:children.read');
        Route::post('/', [EmployeeChildrenController::class, 'store'])->middleware('permission:children.create');
        Route::get('/{id}', [EmployeeChildrenController::class, 'show'])->middleware('permission:children.read');
        Route::put('/{id}', [EmployeeChildrenController::class, 'update'])->middleware('permission:children.update');
        Route::delete('/{id}', [EmployeeChildrenController::class, 'destroy'])->middleware('permission:children.delete');
    });

    // Employee beneficiary routes
    Route::prefix('employee-beneficiaries')->group(function () {
        Route::get('/', [EmployeeBeneficiaryController::class, 'index'])->middleware('permission:employee.read');
        Route::post('/', [EmployeeBeneficiaryController::class, 'store'])->middleware('permission:employee.create');
        Route::get('/{id}', [EmployeeBeneficiaryController::class, 'show'])->middleware('permission:employee.read');
        Route::put('/{id}', [EmployeeBeneficiaryController::class, 'update'])->middleware('permission:employee.update');
        Route::delete('/{id}', [EmployeeBeneficiaryController::class, 'destroy'])->middleware('permission:employee.delete');
    });

    // Employee education routes
    Route::prefix('employee-education')->group(function () {
        Route::get('/', [EmployeeEducationController::class, 'index'])->middleware('permission:employee.read');
        Route::post('/', [EmployeeEducationController::class, 'store'])->middleware('permission:employee.create');
        Route::get('/{employeeEducation}', [EmployeeEducationController::class, 'show'])->middleware('permission:employee.read');
        Route::put('/{employeeEducation}', [EmployeeEducationController::class, 'update'])->middleware('permission:employee.update');
        Route::delete('/{employeeEducation}', [EmployeeEducationController::class, 'destroy'])->middleware('permission:employee.delete');
    });

    // Employee language routes
    Route::prefix('employee-language')->group(function () {
        Route::get('/', [EmployeeLanguageController::class, 'index'])->middleware('permission:employee.read');
        Route::post('/', [EmployeeLanguageController::class, 'store'])->middleware('permission:employee.create');
        Route::get('/{id}', [EmployeeLanguageController::class, 'show'])->middleware('permission:employee.read');
        Route::put('/{id}', [EmployeeLanguageController::class, 'update'])->middleware('permission:employee.update');
        Route::delete('/{id}', [EmployeeLanguageController::class, 'destroy'])->middleware('permission:employee.delete');
    });

    // Training routes - Now using dedicated TrainingController with standard REST methods
    Route::prefix('trainings')->group(function () {
        Route::get('/', [TrainingController::class, 'index'])->middleware('permission:training.read');
        Route::post('/', [TrainingController::class, 'store'])->middleware('permission:training.create');
        Route::get('/{id}', [TrainingController::class, 'show'])->middleware('permission:training.read');
        Route::put('/{id}', [TrainingController::class, 'update'])->middleware('permission:training.update');
        Route::delete('/{id}', [TrainingController::class, 'destroy'])->middleware('permission:training.delete');
    });

    // Employee training routes
    Route::prefix('employee-trainings')->group(function () {
        Route::get('/', [EmployeeTrainingController::class, 'index'])->middleware('permission:training.read');
        Route::post('/', [EmployeeTrainingController::class, 'store'])->middleware('permission:training.create');
        Route::get('/employee/{employee_id}/summary', [EmployeeTrainingController::class, 'getEmployeeTrainingSummary'])->middleware('permission:training.read');
        Route::get('/training/{training_id}/attendance', [EmployeeTrainingController::class, 'getTrainingAttendanceList'])->middleware('permission:training.read');
        Route::get('/{id}', [EmployeeTrainingController::class, 'show'])->middleware('permission:training.read');
        Route::put('/{id}', [EmployeeTrainingController::class, 'update'])->middleware('permission:training.update');
        Route::delete('/{id}', [EmployeeTrainingController::class, 'destroy'])->middleware('permission:training.delete');
    });
});
