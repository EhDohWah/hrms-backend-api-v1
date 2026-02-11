<?php

use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmploymentController;
use App\Http\Controllers\Api\HolidayController;
use App\Http\Controllers\Api\InterviewController;
use App\Http\Controllers\Api\JobOfferController;
use App\Http\Controllers\Api\LeaveBalanceController;
use App\Http\Controllers\Api\LeaveCalculationController;
use App\Http\Controllers\Api\LeaveRequestController;
use App\Http\Controllers\Api\LeaveTypeController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\RecycleBinController;
use App\Http\Controllers\Api\Reports\InterviewReportController;
use App\Http\Controllers\Api\Reports\JobOfferReportController;
use App\Http\Controllers\Api\Reports\LeaveRequestReportController;
use App\Http\Controllers\Api\TravelRequestController;
use App\Http\Controllers\Api\WorklocationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Employment routes - Uses employment_records permission
    Route::prefix('employments')->group(function () {
        // Read routes
        Route::get('/', [EmploymentController::class, 'index'])->middleware('permission:employment_records.read');
        Route::get('/search/staff-id/{staffId}', [EmploymentController::class, 'searchByStaffId'])->middleware('permission:employment_records.read');
        Route::post('/calculate-allocation', [EmploymentController::class, 'calculateAllocationAmount'])->middleware('permission:employment_records.read');
        Route::get('/{id}/funding-allocations', [EmploymentController::class, 'fundingAllocations'])->middleware('permission:employment_records.read');
        Route::get('/{id}/probation-history', [EmploymentController::class, 'probationHistory'])->middleware('permission:employment_records.read');
        Route::get('/{id}', [EmploymentController::class, 'show'])->middleware('permission:employment_records.read');

        // Edit routes
        Route::post('/', [EmploymentController::class, 'store'])->middleware('permission:employment_records.edit');
        Route::post('/{id}/complete-probation', [EmploymentController::class, 'completeProbation'])->middleware('permission:employment_records.edit');
        Route::post('/{id}/probation-status', [EmploymentController::class, 'updateProbationStatus'])->middleware('permission:employment_records.edit');
        Route::put('/{id}', [EmploymentController::class, 'update'])->middleware('permission:employment_records.edit');
        Route::delete('/{id}', [EmploymentController::class, 'destroy'])->middleware('permission:employment_records.edit');
    });

    // Department routes - Uses departments permission
    Route::prefix('departments')->group(function () {
        Route::get('/options', [DepartmentController::class, 'options'])->middleware('permission:departments.read');
        Route::get('/', [DepartmentController::class, 'index'])->middleware('permission:departments.read');
        Route::get('/{id}', [DepartmentController::class, 'show'])->middleware('permission:departments.read');
        Route::get('/{id}/positions', [DepartmentController::class, 'positions'])->middleware('permission:departments.read');
        Route::get('/{id}/managers', [DepartmentController::class, 'managers'])->middleware('permission:departments.read');
        Route::post('/', [DepartmentController::class, 'store'])->middleware('permission:departments.edit');
        Route::put('/{id}', [DepartmentController::class, 'update'])->middleware('permission:departments.edit');
        Route::delete('/{id}', [DepartmentController::class, 'destroy'])->middleware('permission:departments.edit');
    });

    // Position routes - Uses positions permission
    Route::prefix('positions')->group(function () {
        Route::get('/options', [PositionController::class, 'options'])->middleware('permission:positions.read');
        Route::get('/', [PositionController::class, 'index'])->middleware('permission:positions.read');
        Route::get('/{id}', [PositionController::class, 'show'])->middleware('permission:positions.read');
        Route::get('/{id}/direct-reports', [PositionController::class, 'directReports'])->middleware('permission:positions.read');
        Route::post('/', [PositionController::class, 'store'])->middleware('permission:positions.edit');
        Route::put('/{id}', [PositionController::class, 'update'])->middleware('permission:positions.edit');
        Route::delete('/{id}', [PositionController::class, 'destroy'])->middleware('permission:positions.edit');
    });

    // Work location routes - Uses employees permission
    Route::prefix('worklocations')->group(function () {
        Route::get('/', [WorklocationController::class, 'index'])->middleware('permission:employees.read');
        Route::get('/{id}', [WorklocationController::class, 'show'])->middleware('permission:employees.read');
        Route::post('/', [WorklocationController::class, 'store'])->middleware('permission:employees.edit');
        Route::put('/{id}', [WorklocationController::class, 'update'])->middleware('permission:employees.edit');
        Route::delete('/{id}', [WorklocationController::class, 'destroy'])->middleware('permission:employees.edit');
    });

    // Interview routes - Uses dynamic module permission
    Route::prefix('interviews')->middleware('module.permission:interviews')->group(function () {
        Route::get('/', [InterviewController::class, 'index']);
        Route::get('/by-candidate/{candidateName}', [InterviewController::class, 'byCandidateName']);
        Route::get('/{id}', [InterviewController::class, 'show']);
        Route::post('/', [InterviewController::class, 'store']);
        Route::put('/{id}', [InterviewController::class, 'update']);
        Route::delete('/{id}', [InterviewController::class, 'destroy']);
    });

    // Job offer routes - Uses dynamic module permission
    Route::prefix('job-offers')->middleware('module.permission:job_offers')->group(function () {
        Route::get('/', [JobOfferController::class, 'index']);
        Route::get('/by-candidate/{candidateName}', [JobOfferController::class, 'byCandidateName']);
        Route::get('/{id}', [JobOfferController::class, 'show']);
        Route::get('/{id}/pdf', [JobOfferController::class, 'generatePdf']);
        Route::post('/', [JobOfferController::class, 'store']);
        Route::put('/{id}', [JobOfferController::class, 'update']);
        Route::delete('/{id}', [JobOfferController::class, 'destroy']);
    });

    // =========================================================================
    // LEAVE TYPES - New RESTful routes (Primary)
    // =========================================================================
    Route::prefix('leave-types')->group(function () {
        Route::get('/options', [LeaveTypeController::class, 'options'])
            ->name('leave-types.options')
            ->middleware('permission:leave_types.read');

        Route::get('/', [LeaveTypeController::class, 'index'])
            ->name('leave-types.index')
            ->middleware('permission:leave_types.read');

        Route::post('/', [LeaveTypeController::class, 'store'])
            ->name('leave-types.store')
            ->middleware('permission:leave_types.edit');

        Route::put('/{id}', [LeaveTypeController::class, 'update'])
            ->name('leave-types.update')
            ->middleware('permission:leave_types.edit');

        Route::delete('/{id}', [LeaveTypeController::class, 'destroy'])
            ->name('leave-types.destroy')
            ->middleware('permission:leave_types.edit');
    });

    // =========================================================================
    // LEAVE REQUESTS - New RESTful routes (Primary)
    // =========================================================================
    Route::prefix('leave-requests')->group(function () {
        // Custom routes (must be before resource routes with {id} parameter)
        Route::post('/calculate-days', [LeaveRequestController::class, 'calculateDays'])
            ->name('leave-requests.calculate-days')
            ->middleware('permission:leaves_admin.read');

        Route::post('/check-overlap', [LeaveRequestController::class, 'checkOverlap'])
            ->name('leave-requests.check-overlap')
            ->middleware('permission:leaves_admin.read');

        // Standard RESTful routes
        Route::get('/', [LeaveRequestController::class, 'index'])
            ->name('leave-requests.index')
            ->middleware('permission:leaves_admin.read');

        Route::get('/{id}', [LeaveRequestController::class, 'show'])
            ->name('leave-requests.show')
            ->middleware('permission:leaves_admin.read');

        Route::post('/', [LeaveRequestController::class, 'store'])
            ->name('leave-requests.store')
            ->middleware('permission:leaves_admin.edit');

        Route::put('/{id}', [LeaveRequestController::class, 'update'])
            ->name('leave-requests.update')
            ->middleware('permission:leaves_admin.edit');

        Route::delete('/{id}', [LeaveRequestController::class, 'destroy'])
            ->name('leave-requests.destroy')
            ->middleware('permission:leaves_admin.edit');
    });

    // =========================================================================
    // LEAVE BALANCES - New RESTful routes (Primary)
    // =========================================================================
    Route::prefix('leave-balances')->group(function () {
        Route::get('/', [LeaveBalanceController::class, 'index'])
            ->name('leave-balances.index')
            ->middleware('permission:leave_balances.read');

        Route::get('/{employeeId}/{leaveTypeId}', [LeaveBalanceController::class, 'show'])
            ->name('leave-balances.show')
            ->middleware('permission:leave_balances.read');

        Route::post('/', [LeaveBalanceController::class, 'store'])
            ->name('leave-balances.store')
            ->middleware('permission:leave_balances.edit');

        Route::put('/{id}', [LeaveBalanceController::class, 'update'])
            ->name('leave-balances.update')
            ->middleware('permission:leave_balances.edit');
    });

    // =========================================================================
    // HOLIDAYS - Organization public holidays / traditional day-off calendar
    // These dates are excluded when calculating leave request working days.
    // =========================================================================
    Route::prefix('holidays')->group(function () {
        // Custom routes (must be before resource routes)
        Route::get('/options', [HolidayController::class, 'options'])
            ->name('holidays.options')
            ->middleware('permission:holidays.read');

        Route::get('/in-range', [HolidayController::class, 'inRange'])
            ->name('holidays.in-range')
            ->middleware('permission:holidays.read');

        Route::post('/bulk', [HolidayController::class, 'storeBatch'])
            ->name('holidays.store-batch')
            ->middleware('permission:holidays.edit');

        // Standard RESTful routes
        Route::get('/', [HolidayController::class, 'index'])
            ->name('holidays.index')
            ->middleware('permission:holidays.read');

        Route::get('/{holiday}', [HolidayController::class, 'show'])
            ->name('holidays.show')
            ->middleware('permission:holidays.read');

        Route::post('/', [HolidayController::class, 'store'])
            ->name('holidays.store')
            ->middleware('permission:holidays.edit');

        Route::put('/{holiday}', [HolidayController::class, 'update'])
            ->name('holidays.update')
            ->middleware('permission:holidays.edit');

        Route::delete('/{holiday}', [HolidayController::class, 'destroy'])
            ->name('holidays.destroy')
            ->middleware('permission:holidays.edit');
    });

    // =========================================================================
    // LEAVE CALCULATION - Calculate working days excluding weekends/holidays
    // =========================================================================
    Route::prefix('leave-calculation')->group(function () {
        Route::post('/working-days', [LeaveCalculationController::class, 'calculateWorkingDays'])
            ->name('leave-calculation.working-days')
            ->middleware('permission:leaves_admin.read');

        Route::post('/working-days-detailed', [LeaveCalculationController::class, 'calculateWorkingDaysDetailed'])
            ->name('leave-calculation.working-days-detailed')
            ->middleware('permission:leaves_admin.read');

        Route::get('/non-working-dates', [LeaveCalculationController::class, 'getNonWorkingDates'])
            ->name('leave-calculation.non-working-dates')
            ->middleware('permission:leaves_admin.read');

        Route::get('/year-statistics/{year}', [LeaveCalculationController::class, 'getYearStatistics'])
            ->name('leave-calculation.year-statistics')
            ->middleware('permission:leaves_admin.read');
    });

    // =========================================================================
    // LEGACY ROUTES - Backward compatibility for /leaves/*
    // These routes point to the new controllers
    // TODO: Remove after frontend migration (target: 2026-04-24)
    // =========================================================================
    Route::prefix('leaves')->group(function () {
        // Leave Types (legacy)
        Route::get('/types', [LeaveTypeController::class, 'index'])
            ->name('leaves.types.index')
            ->middleware('permission:leave_types.read');

        Route::get('/types/dropdown', [LeaveTypeController::class, 'options'])
            ->name('leaves.types.dropdown')
            ->middleware('permission:leave_types.read');

        Route::post('/types', [LeaveTypeController::class, 'store'])
            ->name('leaves.types.store')
            ->middleware('permission:leave_types.edit');

        Route::put('/types/{id}', [LeaveTypeController::class, 'update'])
            ->name('leaves.types.update')
            ->middleware('permission:leave_types.edit');

        Route::delete('/types/{id}', [LeaveTypeController::class, 'destroy'])
            ->name('leaves.types.destroy')
            ->middleware('permission:leave_types.edit');

        // Leave Requests (legacy)
        Route::get('/requests', [LeaveRequestController::class, 'index'])
            ->name('leaves.requests.index')
            ->middleware('permission:leaves_admin.read');

        Route::get('/requests/{id}', [LeaveRequestController::class, 'show'])
            ->name('leaves.requests.show')
            ->middleware('permission:leaves_admin.read');

        Route::post('/requests', [LeaveRequestController::class, 'store'])
            ->name('leaves.requests.store')
            ->middleware('permission:leaves_admin.edit');

        Route::put('/requests/{id}', [LeaveRequestController::class, 'update'])
            ->name('leaves.requests.update')
            ->middleware('permission:leaves_admin.edit');

        Route::delete('/requests/{id}', [LeaveRequestController::class, 'destroy'])
            ->name('leaves.requests.destroy')
            ->middleware('permission:leaves_admin.edit');

        // Leave Balances (legacy)
        Route::get('/balances', [LeaveBalanceController::class, 'index'])
            ->name('leaves.balances.index')
            ->middleware('permission:leave_balances.read');

        Route::get('/balance/{employeeId}/{leaveTypeId}', [LeaveBalanceController::class, 'show'])
            ->name('leaves.balance.show')
            ->middleware('permission:leave_balances.read');

        Route::post('/balances', [LeaveBalanceController::class, 'store'])
            ->name('leaves.balances.store')
            ->middleware('permission:leave_balances.edit');

        Route::put('/balances/{id}', [LeaveBalanceController::class, 'update'])
            ->name('leaves.balances.update')
            ->middleware('permission:leave_balances.edit');
    });

    // Travel request routes - Uses travel_admin permission
    Route::prefix('travel-requests')->group(function () {
        Route::get('/options', [TravelRequestController::class, 'options'])->middleware('permission:travel_admin.read');
        Route::get('/search/employee/{staffId}', [TravelRequestController::class, 'searchByStaffId'])->middleware('permission:travel_admin.read');
        Route::get('/', [TravelRequestController::class, 'index'])->middleware('permission:travel_admin.read');
        Route::get('/{id}', [TravelRequestController::class, 'show'])->middleware('permission:travel_admin.read');
        Route::post('/', [TravelRequestController::class, 'store'])->middleware('permission:travel_admin.edit');
        Route::put('/{id}', [TravelRequestController::class, 'update'])->middleware('permission:travel_admin.edit');
        Route::delete('/{id}', [TravelRequestController::class, 'destroy'])->middleware('permission:travel_admin.edit');
    });

    // Report routes (exports are read operations) - Uses report_list permission
    Route::prefix('reports')->group(function () {
        Route::post('/interview-report/export-pdf', [InterviewReportController::class, 'exportPDF'])->middleware('permission:report_list.read');
        Route::get('/interview-report/export-excel', [InterviewReportController::class, 'exportExcel'])->middleware('permission:report_list.read');
        Route::post('/job-offer-report/export-pdf', [JobOfferReportController::class, 'exportPDF'])->middleware('permission:report_list.read');
        Route::post('/leave-request-report/export-pdf', [LeaveRequestReportController::class, 'exportPDF'])->middleware('permission:report_list.read');
        Route::post('/leave-request-report/export-individual-pdf', [LeaveRequestReportController::class, 'exportIndividualPDF'])->middleware('permission:report_list.read');
    });

    // Recycle bin routes - Uses recycle_bin_list permission
    Route::prefix('recycle-bin')->middleware('permission:recycle_bin_list.read')->group(function () {
        Route::get('/', [RecycleBinController::class, 'index']);
        Route::get('/stats', [RecycleBinController::class, 'stats']);

        // Soft-delete operations (Employee, Grant, Department)
        Route::post('/restore/{modelType}/{id}', [RecycleBinController::class, 'restore'])->middleware('permission:recycle_bin_list.edit');
        Route::post('/bulk-restore', [RecycleBinController::class, 'bulkRestore'])->middleware('permission:recycle_bin_list.edit');
        Route::delete('/permanent/{modelType}/{id}', [RecycleBinController::class, 'permanentDelete'])->middleware('permission:recycle_bin_list.edit');

        // Legacy operations (flat restore for Interview, JobOffer via KeepsDeletedModels)
        Route::post('/restore-legacy', [RecycleBinController::class, 'restoreLegacy'])->middleware('permission:recycle_bin_list.edit');
        Route::post('/bulk-restore-legacy', [RecycleBinController::class, 'bulkRestoreLegacy'])->middleware('permission:recycle_bin_list.edit');
        Route::delete('/legacy/{deletedRecordId}', [RecycleBinController::class, 'permanentDeleteLegacy'])->middleware('permission:recycle_bin_list.edit');
    });
});
