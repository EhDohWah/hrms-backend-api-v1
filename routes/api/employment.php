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
    // Employment routes - Uses employment_records permission
    Route::prefix('employments')->group(function () {
        // Read routes
        Route::get('/', [EmploymentController::class, 'index'])->middleware('permission:employment_records.read');
        Route::get('/search/staff-id/{staffId}', [EmploymentController::class, 'searchByStaffId'])->middleware('permission:employment_records.read');
        Route::post('/calculate-allocation', [EmploymentController::class, 'calculateAllocationAmount'])->middleware('permission:employment_records.read');
        Route::get('/{id}/funding-allocations', [EmploymentController::class, 'getFundingAllocations'])->middleware('permission:employment_records.read');
        Route::get('/{id}/probation-history', [EmploymentController::class, 'getProbationHistory'])->middleware('permission:employment_records.read');
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
        Route::get('/by-candidate/{candidateName}', [InterviewController::class, 'getByCandidateName']);
        Route::get('/{id}', [InterviewController::class, 'show']);
        Route::post('/', [InterviewController::class, 'store']);
        Route::put('/{id}', [InterviewController::class, 'update']);
        Route::delete('/{id}', [InterviewController::class, 'destroy']);
    });

    // Job offer routes - Uses dynamic module permission
    Route::prefix('job-offers')->middleware('module.permission:job_offers')->group(function () {
        Route::get('/', [JobOfferController::class, 'index']);
        Route::get('/by-candidate/{candidateName}', [JobOfferController::class, 'getByCandidateName']);
        Route::get('/{id}', [JobOfferController::class, 'show']);
        Route::get('/{id}/pdf', [JobOfferController::class, 'generatePdf']);
        Route::post('/', [JobOfferController::class, 'store']);
        Route::put('/{id}', [JobOfferController::class, 'update']);
        Route::delete('/{id}', [JobOfferController::class, 'destroy']);
    });

    // Leave management routes - Uses leaves_admin permission
    Route::prefix('leaves')->group(function () {
        // Leave Types
        Route::get('/types', [LeaveManagementController::class, 'indexTypes'])->middleware('permission:leave_types.read');
        Route::get('/types/dropdown', [LeaveManagementController::class, 'getTypesForDropdown'])->middleware('permission:leave_types.read');
        Route::post('/types', [LeaveManagementController::class, 'storeTypes'])->middleware('permission:leave_types.edit');
        Route::put('/types/{id}', [LeaveManagementController::class, 'updateTypes'])->middleware('permission:leave_types.edit');
        Route::delete('/types/{id}', [LeaveManagementController::class, 'destroyTypes'])->middleware('permission:leave_types.edit');

        // Leave Requests - Uses leaves_admin permission
        Route::get('/requests', [LeaveManagementController::class, 'index'])->middleware('permission:leaves_admin.read');
        Route::get('/requests/{id}', [LeaveManagementController::class, 'show'])->middleware('permission:leaves_admin.read');
        Route::post('/requests', [LeaveManagementController::class, 'store'])->middleware('permission:leaves_admin.edit');
        Route::put('/requests/{id}', [LeaveManagementController::class, 'update'])->middleware('permission:leaves_admin.edit');
        Route::delete('/requests/{id}', [LeaveManagementController::class, 'destroy'])->middleware('permission:leaves_admin.edit');

        // Leave Balances
        Route::get('/balances', [LeaveManagementController::class, 'indexBalances'])->middleware('permission:leave_balances.read');
        Route::get('/balance/{employeeId}/{leaveTypeId}', [LeaveManagementController::class, 'showEmployeeBalance'])->middleware('permission:leave_balances.read');
        Route::post('/balances', [LeaveManagementController::class, 'storeBalances'])->middleware('permission:leave_balances.edit');
        Route::put('/balances/{id}', [LeaveManagementController::class, 'updateBalances'])->middleware('permission:leave_balances.edit');
    });

    // Travel request routes - Uses travel_admin permission
    Route::prefix('travel-requests')->group(function () {
        Route::get('/options', [TravelRequestController::class, 'getOptions'])->middleware('permission:travel_admin.read');
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
        Route::post('/restore', [RecycleBinController::class, 'restore'])->middleware('permission:recycle_bin_list.edit');
        Route::post('/bulk-restore', [RecycleBinController::class, 'bulkRestore'])->middleware('permission:recycle_bin_list.edit');
        Route::delete('/{deletedRecordId}', [RecycleBinController::class, 'permanentDelete'])->middleware('permission:recycle_bin_list.edit');
    });
});
