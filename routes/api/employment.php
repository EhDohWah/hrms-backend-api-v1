<?php

use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\EmploymentController;
use App\Http\Controllers\Api\InterviewController;
use App\Http\Controllers\Api\JobOfferController;
use App\Http\Controllers\Api\LeaveManagementController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\RecycleBinController;
use App\Http\Controllers\Api\Reports\InterviewReportController;
use App\Http\Controllers\Api\Reports\JobOfferReportController;
use App\Http\Controllers\Api\Reports\LeaveRequestReportController;
use App\Http\Controllers\Api\TravelRequestController;
use App\Http\Controllers\Api\WorklocationController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Employment routes
    Route::prefix('employments')->group(function () {
        Route::get('/', [EmploymentController::class, 'index'])->middleware('permission:employment.read');
        Route::get('/search/staff-id/{staffId}', [EmploymentController::class, 'searchByStaffId'])->middleware('permission:employment.read');
        Route::get('/{id}/funding-allocations', [EmploymentController::class, 'getFundingAllocations'])->middleware('permission:employment.read');
        Route::get('/{id}', [EmploymentController::class, 'show'])->middleware('permission:employment.read');
        Route::post('/', [EmploymentController::class, 'store'])->middleware('permission:employment.create');
        Route::put('/{id}', [EmploymentController::class, 'update'])->middleware('permission:employment.update');
        Route::delete('/{id}', [EmploymentController::class, 'destroy'])->middleware('permission:employment.delete');
    });

    // Department routes
    Route::prefix('departments')->group(function () {
        Route::get('/options', [DepartmentController::class, 'options'])->middleware('permission:employment.read');
        Route::get('/', [DepartmentController::class, 'index'])->middleware('permission:employment.read');
        Route::get('/{id}', [DepartmentController::class, 'show'])->middleware('permission:employment.read');
        Route::post('/', [DepartmentController::class, 'store'])->middleware('permission:employment.create');
        Route::put('/{id}', [DepartmentController::class, 'update'])->middleware('permission:employment.update');
        Route::delete('/{id}', [DepartmentController::class, 'destroy'])->middleware('permission:employment.delete');

        // Department-specific endpoints
        Route::get('/{id}/positions', [DepartmentController::class, 'positions'])->middleware('permission:employment.read');
        Route::get('/{id}/managers', [DepartmentController::class, 'managers'])->middleware('permission:employment.read');
    });

    // Position routes
    Route::prefix('positions')->group(function () {
        Route::get('/options', [PositionController::class, 'options'])->middleware('permission:employment.read');
        Route::get('/', [PositionController::class, 'index'])->middleware('permission:employment.read');
        Route::get('/{id}', [PositionController::class, 'show'])->middleware('permission:employment.read');
        Route::post('/', [PositionController::class, 'store'])->middleware('permission:employment.create');
        Route::put('/{id}', [PositionController::class, 'update'])->middleware('permission:employment.update');
        Route::delete('/{id}', [PositionController::class, 'destroy'])->middleware('permission:employment.delete');

        // Position-specific endpoints
        Route::get('/{id}/direct-reports', [PositionController::class, 'directReports'])->middleware('permission:employment.read');
    });

    // Work location routes (use middleware permission:read work locations)
    Route::prefix('worklocations')->group(function () {
        Route::get('/', [WorklocationController::class, 'index'])->middleware('permission:employee.read');
        Route::get('/{id}', [WorklocationController::class, 'show'])->middleware('permission:employee.read');
        Route::post('/', [WorklocationController::class, 'store'])->middleware('permission:employee.create');
        Route::put('/{id}', [WorklocationController::class, 'update'])->middleware('permission:employee.update');
        Route::delete('/{id}', [WorklocationController::class, 'destroy'])->middleware('permission:employee.delete');
    });

    // Interview routes (use middleware permission:read interviews)
    Route::prefix('interviews')->group(function () {
        Route::get('/', [InterviewController::class, 'index'])->middleware('permission:interview.read');
        Route::get('/by-candidate/{candidateName}', [InterviewController::class, 'getByCandidateName'])->middleware('permission:interview.read');
        Route::post('/', [InterviewController::class, 'store'])->middleware('permission:interview.create');
        Route::get('/{id}', [InterviewController::class, 'show'])->middleware('permission:interview.read');
        Route::put('/{id}', [InterviewController::class, 'update'])->middleware('permission:interview.update');
        Route::delete('/{id}', [InterviewController::class, 'destroy'])->middleware('permission:interview.delete');
    });

    // Job offer routes
    Route::prefix('job-offers')->group(function () {
        Route::get('/', [JobOfferController::class, 'index'])->middleware('permission:job_offer.read');
        Route::post('/', [JobOfferController::class, 'store'])->middleware('permission:job_offer.create');
        Route::get('/by-candidate/{candidateName}', [JobOfferController::class, 'getByCandidateName'])->middleware('permission:job_offer.read');
        Route::get('/{id}', [JobOfferController::class, 'show'])->middleware('permission:job_offer.read');
        Route::put('/{id}', [JobOfferController::class, 'update'])->middleware('permission:job_offer.update');
        Route::delete('/{id}', [JobOfferController::class, 'destroy'])->middleware('permission:job_offer.delete');
        Route::get('/{id}/pdf', [JobOfferController::class, 'generatePdf'])->middleware('permission:job_offer.read');
    });

    // Leave management routes (use middleware permission:read leaves)
    Route::prefix('leaves')->group(function () {
        // Leave Types - Standard Laravel resource methods
        Route::get('/types', [LeaveManagementController::class, 'indexTypes'])->middleware('permission:leave_request.read');
        Route::post('/types', [LeaveManagementController::class, 'storeTypes'])->middleware('permission:leave_request.create');
        Route::put('/types/{id}', [LeaveManagementController::class, 'updateTypes'])->middleware('permission:leave_request.update');
        Route::delete('/types/{id}', [LeaveManagementController::class, 'destroyTypes'])->middleware('permission:leave_request.delete');

        // Leave Requests - Standard Laravel resource methods
        Route::get('/requests', [LeaveManagementController::class, 'index'])->middleware('permission:leave_request.read');
        Route::get('/requests/{id}', [LeaveManagementController::class, 'show'])->middleware('permission:leave_request.read');
        Route::post('/requests', [LeaveManagementController::class, 'store'])->middleware('permission:leave_request.create');
        Route::put('/requests/{id}', [LeaveManagementController::class, 'update'])->middleware('permission:leave_request.update');
        Route::delete('/requests/{id}', [LeaveManagementController::class, 'destroy'])->middleware('permission:leave_request.delete');

        // Leave Balances - Standard Laravel resource methods
        Route::get('/balances', [LeaveManagementController::class, 'indexBalances'])->middleware('permission:leave_request.read');
        Route::get('/balance/{employeeId}/{leaveTypeId}', [LeaveManagementController::class, 'showEmployeeBalance'])->middleware('permission:leave_request.read');
        Route::post('/balances', [LeaveManagementController::class, 'storeBalances'])->middleware('permission:leave_request.create');
        Route::put('/balances/{id}', [LeaveManagementController::class, 'updateBalances'])->middleware('permission:leave_request.update');

    });

    // Travel request routes (use middleware permission:read travel requests)
    Route::prefix('travel-requests')->group(function () {
        Route::get('/options', [TravelRequestController::class, 'getOptions'])->middleware('permission:travel_request.read');
        Route::get('/search/employee/{staffId}', [TravelRequestController::class, 'searchByStaffId'])->middleware('permission:travel_request.read');
        Route::get('/', [TravelRequestController::class, 'index'])->middleware('permission:travel_request.read');
        Route::post('/', [TravelRequestController::class, 'store'])->middleware('permission:travel_request.create');
        Route::get('/{id}', [TravelRequestController::class, 'show'])->middleware('permission:travel_request.read');
        Route::put('/{id}', [TravelRequestController::class, 'update'])->middleware('permission:travel_request.update');
        Route::delete('/{id}', [TravelRequestController::class, 'destroy'])->middleware('permission:travel_request.delete');
    });

    // Report routes
    Route::prefix('reports')->group(function () {
        Route::post('/interview-report/export-pdf', [InterviewReportController::class, 'exportPDF'])->middleware('permission:reports.create');
        Route::get('/interview-report/export-excel', [InterviewReportController::class, 'exportExcel'])->middleware('permission:reports.create');
        Route::post('/job-offer-report/export-pdf', [JobOfferReportController::class, 'exportPDF'])->middleware('permission:reports.create');
        Route::post('/leave-request-report/export-pdf', [LeaveRequestReportController::class, 'exportPDF'])->middleware('permission:reports.create');
        Route::post('/leave-request-report/export-individual-pdf', [LeaveRequestReportController::class, 'exportIndividualPDF'])->middleware('permission:reports.create');
    });

    // Recycle bin routes
    Route::prefix('recycle-bin')->group(function () {
        Route::get('/', [RecycleBinController::class, 'index']);
        Route::get('/stats', [RecycleBinController::class, 'stats']);
        Route::post('/restore', [RecycleBinController::class, 'restore']);
        Route::post('/bulk-restore', [RecycleBinController::class, 'bulkRestore']);
        Route::delete('/{deletedRecordId}', [RecycleBinController::class, 'permanentDelete']);
    });
});
