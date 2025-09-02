<?php

use App\Http\Controllers\Api\DepartmentpositionController;
use App\Http\Controllers\Api\EmploymentController;
use App\Http\Controllers\Api\InterviewController;
use App\Http\Controllers\Api\JobOfferController;
use App\Http\Controllers\Api\LeaveManagementController;
use App\Http\Controllers\Api\RecycleBinController;
use App\Http\Controllers\Api\Reports\InterviewReportController;
use App\Http\Controllers\Api\Reports\JobOfferReportController;
use App\Http\Controllers\Api\TravelRequestApprovalController;
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

    // Department position routes (use middleware permission:read department positions)
    Route::prefix('department-positions')->group(function () {
        Route::get('/', [DepartmentpositionController::class, 'index'])->middleware('permission:employment.read');
        Route::get('/{id}', [DepartmentpositionController::class, 'show'])->middleware('permission:employment.read');
        Route::post('/', [DepartmentpositionController::class, 'store'])->middleware('permission:employment.create');
        Route::put('/{id}', [DepartmentpositionController::class, 'update'])->middleware('permission:employment.update');
        Route::delete('/{id}', [DepartmentpositionController::class, 'destroy'])->middleware('permission:employment.delete');
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
        // Leave Types - CORRECTED to match actual controller methods
        Route::get('/types', [LeaveManagementController::class, 'getLeaveTypes'])->middleware('permission:leave_request.read');
        Route::post('/types', [LeaveManagementController::class, 'createLeaveType'])->middleware('permission:leave_request.create');
        Route::put('/types/{id}', [LeaveManagementController::class, 'updateLeaveType'])->middleware('permission:leave_request.update');
        Route::delete('/types/{id}', [LeaveManagementController::class, 'deleteLeaveType'])->middleware('permission:leave_request.delete');

        // Leave Requests - CORRECTED to match actual controller methods
        Route::get('/requests', [LeaveManagementController::class, 'getLeaveRequests'])->middleware('permission:leave_request.read');
        Route::get('/requests/{id}', [LeaveManagementController::class, 'getLeaveRequest'])->middleware('permission:leave_request.read');
        Route::post('/requests', [LeaveManagementController::class, 'createLeaveRequest'])->middleware('permission:leave_request.create');
        Route::put('/requests/{id}', [LeaveManagementController::class, 'updateLeaveRequest'])->middleware('permission:leave_request.update');
        Route::delete('/requests/{id}', [LeaveManagementController::class, 'deleteLeaveRequest'])->middleware('permission:leave_request.delete');

        // Leave Balances - CORRECTED to match actual controller methods
        Route::get('/balances', [LeaveManagementController::class, 'getLeaveBalances'])->middleware('permission:leave_request.read');
        Route::get('/balance/{employeeId}/{leaveTypeId}', [LeaveManagementController::class, 'getEmployeeLeaveBalance'])->middleware('permission:leave_request.read');
        Route::post('/balances', [LeaveManagementController::class, 'createLeaveBalance'])->middleware('permission:leave_request.create');
        Route::put('/balances/{id}', [LeaveManagementController::class, 'updateLeaveBalance'])->middleware('permission:leave_request.update');

        // Leave Approvals - CORRECTED to match actual controller methods with proper parameters
        Route::get('/requests/{leaveRequestId}/approvals', [LeaveManagementController::class, 'getApprovals'])->middleware('permission:leave_request.read');
        Route::post('/requests/{leaveRequestId}/approvals', [LeaveManagementController::class, 'createApproval'])->middleware('permission:leave_request.create');
        Route::put('/approvals/{id}', [LeaveManagementController::class, 'updateApproval'])->middleware('permission:leave_request.update');
    });

    // Travel request routes (use middleware permission:read travel requests)
    Route::prefix('travel-requests')->group(function () {
        Route::get('/', [TravelRequestController::class, 'index'])->middleware('permission:travel_request.read');
        Route::post('/', [TravelRequestController::class, 'store'])->middleware('permission:travel_request.create');
        Route::get('/{id}', [TravelRequestController::class, 'show'])->middleware('permission:travel_request.read');
        Route::put('/{id}', [TravelRequestController::class, 'update'])->middleware('permission:travel_request.update');
        Route::delete('/{id}', [TravelRequestController::class, 'destroy'])->middleware('permission:travel_request.delete');
    });

    // Travel request approval routes (use middleware permission:read travel request approvals)
    Route::prefix('travel-request-approvals')->group(function () {
        Route::get('/', [TravelRequestApprovalController::class, 'index'])->middleware('permission:travel_request.read');
        Route::post('/', [TravelRequestApprovalController::class, 'store'])->middleware('permission:travel_request.create');
        Route::get('/{id}', [TravelRequestApprovalController::class, 'show'])->middleware('permission:travel_request.read');
        Route::put('/{id}', [TravelRequestApprovalController::class, 'update'])->middleware('permission:travel_request.update');
        Route::delete('/{id}', [TravelRequestApprovalController::class, 'destroy'])->middleware('permission:travel_request.delete');
    });

    // Report routes
    Route::prefix('reports')->group(function () {
        Route::post('/interview-report/export-pdf', [InterviewReportController::class, 'exportPDF'])->middleware('permission:reports.create');
        Route::get('/interview-report/export-excel', [InterviewReportController::class, 'exportExcel'])->middleware('permission:reports.create');
        Route::post('/job-offer-report/export-pdf', [JobOfferReportController::class, 'exportPDF'])->middleware('permission:reports.create');
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
