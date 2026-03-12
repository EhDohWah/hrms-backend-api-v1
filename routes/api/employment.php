<?php

use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\EmploymentController;
use App\Http\Controllers\Api\V1\HolidayController;
use App\Http\Controllers\Api\V1\InterviewController;
use App\Http\Controllers\Api\V1\JobOfferController;
use App\Http\Controllers\Api\V1\LeaveBalanceController;
use App\Http\Controllers\Api\V1\LeaveCalculationController;
use App\Http\Controllers\Api\V1\LeaveRequestController;
use App\Http\Controllers\Api\V1\LeaveTypeController;
use App\Http\Controllers\Api\V1\PositionController;
use App\Http\Controllers\Api\V1\RecycleBinController;
use App\Http\Controllers\Api\V1\Reports\InterviewReportController;
use App\Http\Controllers\Api\V1\Reports\JobOfferReportController;
use App\Http\Controllers\Api\V1\Reports\LeaveRequestReportController;
use App\Http\Controllers\Api\V1\TravelRequestController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Employment routes - Uses employment_records permission
    Route::prefix('employments')->group(function () {
        // Read routes
        Route::get('/', [EmploymentController::class, 'index'])->middleware('permission:employment_records.read');
        Route::get('/search/staff-id/{staffId}', [EmploymentController::class, 'searchByStaffId'])->middleware('permission:employment_records.read');
        Route::get('/{employment}/probation-history', [EmploymentController::class, 'probationHistory'])->middleware('permission:employment_records.read');
        Route::get('/{employment}/funding-allocations', [EmploymentController::class, 'fundingAllocations'])->middleware('permission:employment_records.read');
        Route::get('/{employment}', [EmploymentController::class, 'show'])->middleware('permission:employment_records.read');

        // Create routes
        Route::post('/', [EmploymentController::class, 'store'])->middleware('permission:employment_records.create');

        // Update routes
        Route::post('/{employment}/complete-probation', [EmploymentController::class, 'completeProbation'])->middleware('permission:employment_records.update');
        Route::post('/{employment}/probation-status', [EmploymentController::class, 'updateProbationStatus'])->middleware('permission:employment_records.update');
        Route::put('/{employment}', [EmploymentController::class, 'update'])->middleware('permission:employment_records.update');

        // Delete routes
        Route::delete('/{employment}', [EmploymentController::class, 'destroy'])->middleware('permission:employment_records.delete');
    });

    // Department routes - Uses departments permission
    Route::prefix('departments')->group(function () {
        Route::get('/options', [DepartmentController::class, 'options'])->middleware('permission:departments.read');
        Route::get('/', [DepartmentController::class, 'index'])->middleware('permission:departments.read');
        Route::get('/{department}', [DepartmentController::class, 'show'])->middleware('permission:departments.read');
        Route::get('/{department}/positions', [DepartmentController::class, 'positions'])->middleware('permission:departments.read');
        Route::get('/{department}/managers', [DepartmentController::class, 'managers'])->middleware('permission:departments.read');
        Route::post('/', [DepartmentController::class, 'store'])->middleware('permission:departments.create');
        Route::put('/{department}', [DepartmentController::class, 'update'])->middleware('permission:departments.update');
        Route::delete('/{department}', [DepartmentController::class, 'destroy'])->middleware('permission:departments.delete');
    });

    // Position routes - Uses positions permission
    Route::prefix('positions')->group(function () {
        Route::get('/options', [PositionController::class, 'options'])->middleware('permission:positions.read');
        Route::get('/', [PositionController::class, 'index'])->middleware('permission:positions.read');
        Route::get('/{position}', [PositionController::class, 'show'])->middleware('permission:positions.read');
        Route::get('/{position}/direct-reports', [PositionController::class, 'directReports'])->middleware('permission:positions.read');
        Route::post('/', [PositionController::class, 'store'])->middleware('permission:positions.create');
        Route::put('/{position}', [PositionController::class, 'update'])->middleware('permission:positions.update');
        Route::delete('/{position}', [PositionController::class, 'destroy'])->middleware('permission:positions.delete');
    });

    // Interview routes - Uses dynamic module permission
    Route::prefix('interviews')->middleware('module.permission:interviews')->group(function () {
        Route::get('/', [InterviewController::class, 'index']);
        Route::get('/by-candidate/{candidateName}', [InterviewController::class, 'byCandidateName']);
        Route::get('/{interview}', [InterviewController::class, 'show']);
        Route::post('/', [InterviewController::class, 'store']);
        Route::put('/{interview}', [InterviewController::class, 'update']);
        Route::delete('/batch', [InterviewController::class, 'destroyBatch']);
        Route::delete('/{interview}', [InterviewController::class, 'destroy']);
    });

    // Job offer routes - Uses dynamic module permission
    Route::prefix('job-offers')->middleware('module.permission:job_offers')->group(function () {
        Route::get('/', [JobOfferController::class, 'index']);
        Route::get('/by-candidate/{candidateName}', [JobOfferController::class, 'byCandidateName']);
        Route::get('/{jobOffer}', [JobOfferController::class, 'show']);
        Route::get('/{customOfferId}/pdf', [JobOfferController::class, 'generatePdf']);
        Route::post('/', [JobOfferController::class, 'store']);
        Route::put('/{jobOffer}', [JobOfferController::class, 'update']);
        Route::delete('/batch', [JobOfferController::class, 'destroyBatch']);
        Route::delete('/{jobOffer}', [JobOfferController::class, 'destroy']);
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
            ->middleware('permission:leave_types.create');

        Route::put('/{leaveType}', [LeaveTypeController::class, 'update'])
            ->name('leave-types.update')
            ->middleware('permission:leave_types.update');

        Route::delete('/batch', [LeaveTypeController::class, 'destroyBatch'])
            ->name('leave-types.destroy-batch')
            ->middleware('permission:leave_types.delete');

        Route::delete('/{leaveType}', [LeaveTypeController::class, 'destroy'])
            ->name('leave-types.destroy')
            ->middleware('permission:leave_types.delete');
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

        Route::get('/{leaveRequest}', [LeaveRequestController::class, 'show'])
            ->name('leave-requests.show')
            ->middleware('permission:leaves_admin.read');

        Route::post('/', [LeaveRequestController::class, 'store'])
            ->name('leave-requests.store')
            ->middleware('permission:leaves_admin.create');

        Route::put('/{leaveRequest}', [LeaveRequestController::class, 'update'])
            ->name('leave-requests.update')
            ->middleware('permission:leaves_admin.update');

        Route::delete('/batch', [LeaveRequestController::class, 'destroyBatch'])
            ->name('leave-requests.destroy-batch')
            ->middleware('permission:leaves_admin.delete');

        Route::delete('/{leaveRequest}', [LeaveRequestController::class, 'destroy'])
            ->name('leave-requests.destroy')
            ->middleware('permission:leaves_admin.delete');
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
            ->middleware('permission:leave_balances.create');

        Route::put('/{id}', [LeaveBalanceController::class, 'update'])
            ->name('leave-balances.update')
            ->middleware('permission:leave_balances.update');
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
            ->middleware('permission:holidays.create');

        // Standard RESTful routes
        Route::get('/', [HolidayController::class, 'index'])
            ->name('holidays.index')
            ->middleware('permission:holidays.read');

        Route::get('/{holiday}', [HolidayController::class, 'show'])
            ->name('holidays.show')
            ->middleware('permission:holidays.read');

        Route::post('/', [HolidayController::class, 'store'])
            ->name('holidays.store')
            ->middleware('permission:holidays.create');

        Route::put('/{holiday}', [HolidayController::class, 'update'])
            ->name('holidays.update')
            ->middleware('permission:holidays.update');

        Route::delete('/batch', [HolidayController::class, 'destroyBatch'])
            ->name('holidays.destroy-batch')
            ->middleware('permission:holidays.delete');

        Route::delete('/{holiday}', [HolidayController::class, 'destroy'])
            ->name('holidays.destroy')
            ->middleware('permission:holidays.delete');
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
            ->middleware('permission:leave_types.create');

        Route::put('/types/{leaveType}', [LeaveTypeController::class, 'update'])
            ->name('leaves.types.update')
            ->middleware('permission:leave_types.update');

        Route::delete('/types/{leaveType}', [LeaveTypeController::class, 'destroy'])
            ->name('leaves.types.destroy')
            ->middleware('permission:leave_types.delete');

        // Leave Requests (legacy)
        Route::get('/requests', [LeaveRequestController::class, 'index'])
            ->name('leaves.requests.index')
            ->middleware('permission:leaves_admin.read');

        Route::get('/requests/{leaveRequest}', [LeaveRequestController::class, 'show'])
            ->name('leaves.requests.show')
            ->middleware('permission:leaves_admin.read');

        Route::post('/requests', [LeaveRequestController::class, 'store'])
            ->name('leaves.requests.store')
            ->middleware('permission:leaves_admin.create');

        Route::put('/requests/{leaveRequest}', [LeaveRequestController::class, 'update'])
            ->name('leaves.requests.update')
            ->middleware('permission:leaves_admin.update');

        Route::delete('/requests/{leaveRequest}', [LeaveRequestController::class, 'destroy'])
            ->name('leaves.requests.destroy')
            ->middleware('permission:leaves_admin.delete');

        // Leave Balances (legacy)
        Route::get('/balances', [LeaveBalanceController::class, 'index'])
            ->name('leaves.balances.index')
            ->middleware('permission:leave_balances.read');

        Route::get('/balance/{employeeId}/{leaveTypeId}', [LeaveBalanceController::class, 'show'])
            ->name('leaves.balance.show')
            ->middleware('permission:leave_balances.read');

        Route::post('/balances', [LeaveBalanceController::class, 'store'])
            ->name('leaves.balances.store')
            ->middleware('permission:leave_balances.create');

        Route::put('/balances/{id}', [LeaveBalanceController::class, 'update'])
            ->name('leaves.balances.update')
            ->middleware('permission:leave_balances.update');
    });

    // Travel request routes - Uses travel_admin permission
    Route::prefix('travel-requests')->group(function () {
        Route::get('/options', [TravelRequestController::class, 'options'])->middleware('permission:travel_admin.read');
        Route::get('/search/employee/{staffId}', [TravelRequestController::class, 'searchByStaffId'])->middleware('permission:travel_admin.read');
        Route::get('/', [TravelRequestController::class, 'index'])->middleware('permission:travel_admin.read');
        Route::get('/{travelRequest}', [TravelRequestController::class, 'show'])->middleware('permission:travel_admin.read');
        Route::post('/', [TravelRequestController::class, 'store'])->middleware('permission:travel_admin.create');
        Route::put('/{travelRequest}', [TravelRequestController::class, 'update'])->middleware('permission:travel_admin.update');
        Route::delete('/{travelRequest}', [TravelRequestController::class, 'destroy'])->middleware('permission:travel_admin.delete');
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
        Route::post('/restore/{modelType}/{id}', [RecycleBinController::class, 'restore'])->middleware('permission:recycle_bin_list.update');
        Route::post('/bulk-restore', [RecycleBinController::class, 'bulkRestore'])->middleware('permission:recycle_bin_list.update');
        Route::delete('/permanent/{modelType}/{id}', [RecycleBinController::class, 'permanentDelete'])->middleware('permission:recycle_bin_list.delete');

        // Legacy operations (flat restore for Interview, JobOffer via KeepsDeletedModels)
        Route::post('/restore-legacy', [RecycleBinController::class, 'restoreLegacy'])->middleware('permission:recycle_bin_list.update');
        Route::post('/bulk-restore-legacy', [RecycleBinController::class, 'bulkRestoreLegacy'])->middleware('permission:recycle_bin_list.update');
        Route::delete('/legacy/{deletedRecordId}', [RecycleBinController::class, 'permanentDeleteLegacy'])->middleware('permission:recycle_bin_list.delete');
    });
});
