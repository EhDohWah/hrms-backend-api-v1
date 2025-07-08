<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\EmployeeController;
use App\Http\Controllers\Api\GrantController;
use App\Http\Controllers\Api\InterviewController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\PositionController;
use App\Http\Controllers\Api\WorklocationController;
use App\Http\Controllers\Api\EmploymentController;
use App\Http\Controllers\Api\EmploymentTypeController;
use App\Http\Controllers\Api\LookupController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\DepartmentpositionController;
use App\Http\Controllers\Api\LeaveManagementController;
use App\Http\Controllers\Api\TravelRequestController;
use App\Http\Controllers\Api\TravelRequestApprovalController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\EmployeeTrainingController;
use App\Http\Controllers\Api\EmployeeChildrenController;
use App\Http\Controllers\Api\EmployeeGrantAllocationController;
use App\Http\Controllers\Api\JobOfferController;
use App\Http\Controllers\Api\Reports\InterviewReportController;
use App\Http\Controllers\Api\Reports\JobOfferReportController;
use App\Http\Controllers\Api\EmployeeEducationController;
use App\Http\Controllers\Api\EmployeeLanguageController;
use App\Http\Controllers\Api\PayrollGrantAllocationController;
use App\Http\Controllers\Api\InterSubsidiaryAdvanceController;
use Maatwebsite\Excel\Facades\Excel;
use App\Http\Controllers\Api\BudgetLineController;
use App\Http\Controllers\Api\PositionSlotController;
use App\Http\Controllers\Api\EmployeeFundingAllocationController;
use App\Http\Controllers\Api\OrgFundedAllocationController;


Route::get('/export-employees', [EmployeeController::class, 'exportEmployees']);

// Public route for login
Route::post('/login', [AuthController::class, 'login']);

// Notification routes
// In routes/api.php
Route::middleware('auth:sanctum')->get('/notifications', function (Request $request) {
    return $request->user()->notifications()->take(20)->get();
});

// Mark all as read
Route::middleware('auth:sanctum')->post('/notifications/mark-all-read', function (Request $request) {
    $request->user()->unreadNotifications->markAsRead();
    return response()->json(['success' => true]);
});




// Group routes that require authentication via Sanctum
Route::middleware('auth:sanctum')->group(function () {
    // Optional: logout endpoint
    Route::post('/logout', [AuthController::class, 'logout']);
    // Refresh token route – available only if the user is still authenticated
    Route::post('/refresh-token', [AuthController::class, 'refreshToken']);

    // Lookups routes
    Route::prefix('lookups')->group(function () {
        Route::get('/', [LookupController::class, 'index']);
        Route::post('/', [LookupController::class, 'store'])->middleware('permission:admin.create');
        Route::get('/{id}', [LookupController::class, 'show'])->middleware('permission:admin.read');
        Route::put('/{id}', [LookupController::class, 'update'])->middleware('permission:admin.update');
        Route::delete('/{id}', [LookupController::class, 'destroy'])->middleware('permission:admin.delete');
    });
 
    // Budget line routes
    Route::prefix('budget-lines')->group(function () {
        Route::get('/', [BudgetLineController::class, 'index'])->middleware('permission:budget_line.read');
        Route::post('/', [BudgetLineController::class, 'store'])->middleware('permission:budget_line.create');
        Route::get('/{id}', [BudgetLineController::class, 'show'])->middleware('permission:budget_line.read');
        Route::put('/{id}', [BudgetLineController::class, 'update'])->middleware('permission:budget_line.update');
        Route::delete('/{id}', [BudgetLineController::class, 'destroy'])->middleware('permission:budget_line.delete');
    });

    // Position slot routes
    Route::prefix('position-slots')->group(function () {
        Route::get('/', [PositionSlotController::class, 'index'])->middleware('permission:position_slot.read');
        Route::post('/', [PositionSlotController::class, 'store'])->middleware('permission:position_slot.create');
        Route::get('/{id}', [PositionSlotController::class, 'show'])->middleware('permission:position_slot.read');
        Route::put('/{id}', [PositionSlotController::class, 'update'])->middleware('permission:position_slot.update');
        Route::delete('/{id}', [PositionSlotController::class, 'destroy'])->middleware('permission:position_slot.delete');
    });


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
        Route::get('/{id}', [EmployeeFundingAllocationController::class, 'show'])->middleware('permission:employee.read');
        Route::put('/{id}', [EmployeeFundingAllocationController::class, 'update'])->middleware('permission:employee.update');
        Route::delete('/{id}', [EmployeeFundingAllocationController::class, 'destroy'])->middleware('permission:employee.delete');
    });

    // Org funded allocation routes
    Route::prefix('org-funded-allocations')->group(function () {
        Route::get('/', [OrgFundedAllocationController::class, 'index'])->middleware('permission:employee.read');
        Route::post('/', [OrgFundedAllocationController::class, 'store'])->middleware('permission:employee.create');
        Route::get('/{id}', [OrgFundedAllocationController::class, 'show'])->middleware('permission:employee.read');
        Route::put('/{id}', [OrgFundedAllocationController::class, 'update'])->middleware('permission:employee.update');
        Route::delete('/{id}', [OrgFundedAllocationController::class, 'destroy'])->middleware('permission:employee.delete');
    });


    // Employment routes
    Route::prefix('employments')->group(function () {
        Route::get('/', [EmploymentController::class, 'index'])->middleware('permission:employment.read');
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

    // Admin routes (use middleware permission:read admin)
    Route::prefix('admin')->group(function () {
        Route::get('/users', [AdminController::class, 'index'])->middleware('permission:admin.read');
        Route::get('/users/{id}', [AdminController::class, 'show'])->middleware('permission:admin.read');
        Route::put('/users/{id}', [AdminController::class, 'update'])->middleware('permission:admin.update');
        Route::delete('/users/{id}', [AdminController::class, 'destroy'])->middleware('permission:admin.delete');
        Route::post('/users', [AdminController::class, 'store'])->middleware('permission:admin.create');

        Route::get('/roles', [AdminController::class, 'getRoles'])->middleware('permission:admin.read');
        Route::get('/permissions', [AdminController::class, 'getPermissions'])->middleware('permission:admin.read');
    });

    // User routes (use middleware permission:read users)
    Route::prefix('user')->group(function () {
        // Get authenticated user with roles and permissions
        Route::get('/user', [UserController::class, 'getUser'])->middleware('permission:user.read');

        // Profile update routes
        Route::post('/profile-picture', [UserController::class, 'updateProfilePicture'])->middleware('permission:user.update');
        Route::post('/username', [UserController::class, 'updateUsername'])->middleware('permission:user.update');
        Route::post('/password', [UserController::class, 'updatePassword'])->middleware('permission:user.update');
        Route::post('/email', [UserController::class, 'updateEmail'])->middleware('permission:user.update');
    });

    // Grant routes (use middleware permission:read grants)
    Route::prefix('grants')->group(function () {
        // 1) Exact/static routes first:
        Route::get('/',                      [GrantController::class, 'index'])->name('grants.index')->middleware('permission:grant.read');
        Route::get('/items',                 [GrantController::class, 'getGrantItems'])->name('grants.items.index')->middleware('permission:grant.read');
        Route::get('/items/{id}',            [GrantController::class, 'getGrantItem'])->name('grants.items.show')->middleware('permission:grant.read');
        Route::get('/grant-positions',       [GrantController::class, 'getGrantPositions'])->name('grants.grant-positions')->middleware('permission:grant.read');
        Route::post('/upload',               [GrantController::class, 'upload'])->name('grants.upload')->middleware('permission:grant.import');
        Route::post('/items',                [GrantController::class, 'storeGrantItem'])->name('grants.items.store')->middleware('permission:grant.create');
        Route::post('/',                     [GrantController::class, 'storeGrant'])->name('grants.store')->middleware('permission:grant.create');
        Route::get('/by-id/{id}',                  [GrantController::class, 'show'])->name('grants.show')->middleware('permission:grant.read');
        // 2) Wildcards and verbs on {id} last:
        Route::get('/by-code/{code}',                  [GrantController::class, 'getGrantByCode'])->middleware('permission:grant.read');
        Route::put('/{id}',                  [GrantController::class, 'updateGrant'])->name('grants.update')->middleware('permission:grant.update');
        Route::delete('/{id}',               [GrantController::class, 'deleteGrant'])->name('grants.destroy')->middleware('permission:grant.delete');

        // 3) And likewise for items:
        Route::put('/items/{id}',            [GrantController::class, 'updateGrantItem'])->name('grants.items.update')->middleware('permission:grant.update');
        Route::delete('/items/{id}',         [GrantController::class, 'deleteGrantItem'])->name('grants.items.destroy')->middleware('permission:grant.delete');
    });

    // Interview routes (use middleware permission:read interviews)
    Route::prefix('interviews')->group(function () {
        Route::get('/', [InterviewController::class, 'index'])->middleware('permission:interview.read');
        Route::post('/', [InterviewController::class, 'store'])->middleware('permission:interview.create');
        Route::get('/{id}', [InterviewController::class, 'show'])->middleware('permission:interview.read');
        Route::put('/{id}', [InterviewController::class, 'update'])->middleware('permission:interview.update');
        Route::delete('/{id}', [InterviewController::class, 'destroy'])->middleware('permission:interview.delete');
    });

    // Work location routes (use middleware permission:read work locations)
    Route::prefix('worklocations')->group(function () {
        Route::get('/', [WorklocationController::class, 'index'])->middleware('permission:employee.read');
        Route::get('/{id}', [WorklocationController::class, 'show'])->middleware('permission:employee.read');
        Route::post('/', [WorklocationController::class, 'store'])->middleware('permission:employee.create');
        Route::put('/{id}', [WorklocationController::class, 'update'])->middleware('permission:employee.update');
        Route::delete('/{id}', [WorklocationController::class, 'destroy'])->middleware('permission:employee.delete');
    });

    // Leave management routes (use middleware permission:read leaves)
    Route::prefix('leaves')->group(function () {
        Route::get('/types', [LeaveManagementController::class, 'getLeaveTypes'])->middleware('permission:leave_request.read');
        Route::get('/types/{id}', [LeaveManagementController::class, 'getLeaveType'])->middleware('permission:leave_request.read');
        Route::post('/types', [LeaveManagementController::class, 'createLeaveType'])->middleware('permission:leave_request.create');
        Route::put('/types/{id}', [LeaveManagementController::class, 'updateLeaveType'])->middleware('permission:leave_request.update');
        Route::delete('/types/{id}', [LeaveManagementController::class, 'deleteLeaveType'])->middleware('permission:leave_request.delete');

        Route::get('/requests', [LeaveManagementController::class, 'getLeaveRequests'])->middleware('permission:leave_request.read');
        Route::get('/requests/{id}', [LeaveManagementController::class, 'getLeaveRequest'])->middleware('permission:leave_request.read');
        Route::post('/requests', [LeaveManagementController::class, 'createLeaveRequest'])->middleware('permission:leave_request.create');
        Route::put('/requests/{id}', [LeaveManagementController::class, 'updateLeaveRequest'])->middleware('permission:leave_request.update');
        Route::delete('/requests/{id}', [LeaveManagementController::class, 'deleteLeaveRequest'])->middleware('permission:leave_request.delete');

        Route::get('/balances', [LeaveManagementController::class, 'getLeaveBalances'])->middleware('permission:leave_request.read');
        Route::get('/balances/{id}', [LeaveManagementController::class, 'getLeaveBalance'])->middleware('permission:leave_request.read');
        Route::post('/balances', [LeaveManagementController::class, 'createLeaveBalance'])->middleware('permission:leave_request.create');
        Route::put('/balances/{id}', [LeaveManagementController::class, 'updateLeaveBalance'])->middleware('permission:leave_request.update');
        Route::delete('/balances/{id}', [LeaveManagementController::class, 'deleteLeaveBalance'])->middleware('permission:leave_request.delete');

        Route::get('/approvals', [LeaveManagementController::class, 'getApprovals'])->middleware('permission:leave_request.read');
        Route::get('/approvals/{id}', [LeaveManagementController::class, 'getApproval'])->middleware('permission:leave_request.read');
        Route::post('/approvals', [LeaveManagementController::class, 'createApproval'])->middleware('permission:leave_request.create');
        Route::put('/approvals/{id}', [LeaveManagementController::class, 'updateApproval'])->middleware('permission:leave_request.update');
        Route::delete('/approvals/{id}', [LeaveManagementController::class, 'deleteApproval'])->middleware('permission:leave_request.delete');

        Route::get('/traditional', [LeaveManagementController::class, 'getTraditionalLeaves'])->middleware('permission:leave_request.read');
        Route::get('/traditional/{id}', [LeaveManagementController::class, 'getTraditionalLeave'])->middleware('permission:leave_request.read');
        Route::post('/traditional', [LeaveManagementController::class, 'createTraditionalLeave'])->middleware('permission:leave_request.create');
        Route::put('/traditional/{id}', [LeaveManagementController::class, 'updateTraditionalLeave'])->middleware('permission:leave_request.update');
        Route::delete('/traditional/{id}', [LeaveManagementController::class, 'deleteTraditionalLeave'])->middleware('permission:leave_request.delete');
    });


    // Payroll routes (use middleware permission:read payrolls)
    Route::prefix('payrolls')->group(function () {
        Route::get('/', [PayrollController::class, 'index'])->middleware('permission:payroll.read');
        Route::get('/employee-employment', [PayrollController::class, 'getEmployeeEmploymentDetail'])->middleware('permission:payroll.read');
        Route::get('/{id}', [PayrollController::class, 'show'])->middleware('permission:payroll.read');
        Route::post('/', [PayrollController::class, 'store'])->middleware('permission:payroll.create');
        Route::put('/{id}', [PayrollController::class, 'update'])->middleware('permission:payroll.update');
        Route::delete('/{id}', [PayrollController::class, 'destroy'])->middleware('permission:payroll.delete');
        });

    // Inter-subsidiary advance routes (use middleware permission:read inter-subsidiary advances)
    Route::prefix('inter-subsidiary-advances')->group(function () {
        Route::get('/', [InterSubsidiaryAdvanceController::class, 'index'])->middleware('permission:payroll.read');
        Route::post('/', [InterSubsidiaryAdvanceController::class, 'store'])->middleware('permission:payroll.create');
        Route::get('/{id}', [InterSubsidiaryAdvanceController::class, 'show'])->middleware('permission:payroll.read');
        Route::put('/{id}', [InterSubsidiaryAdvanceController::class, 'update'])->middleware('permission:payroll.update');
        Route::delete('/{id}', [InterSubsidiaryAdvanceController::class, 'destroy'])->middleware('permission:payroll.delete');
    });

    // Payroll grant allocation routes (use middleware permission:read payroll grant allocations)
    Route::prefix('payroll-grant-allocations')->group(function () {
        Route::get('/', [PayrollGrantAllocationController::class, 'index'])->middleware('permission:payroll.read');
        Route::post('/', [PayrollGrantAllocationController::class, 'store'])->middleware('permission:payroll.create');
        Route::get('/{id}', [PayrollGrantAllocationController::class, 'show'])->middleware('permission:payroll.read');
        Route::put('/{id}', [PayrollGrantAllocationController::class, 'update'])->middleware('permission:payroll.update');
        Route::delete('/{id}', [PayrollGrantAllocationController::class, 'destroy'])->middleware('permission:payroll.delete');
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

    // Training routes
    Route::prefix('trainings')->group(function () {
        Route::get('/', [EmployeeTrainingController::class, 'listTrainings'])->middleware('permission:training.read');
        Route::post('/', [EmployeeTrainingController::class, 'storeTraining'])->middleware('permission:training.create');
        Route::get('/{id}', [EmployeeTrainingController::class, 'showTraining'])->middleware('permission:training.read');
        Route::put('/{id}', [EmployeeTrainingController::class, 'updateTraining'])->middleware('permission:training.update');
        Route::delete('/{id}', [EmployeeTrainingController::class, 'destroyTraining'])->middleware('permission:training.delete');
    });

    // Employee training routes
    Route::prefix('employee-trainings')->group(function () {
        Route::get('/', [EmployeeTrainingController::class, 'index'])->middleware('permission:training.read');
        Route::post('/', [EmployeeTrainingController::class, 'store'])->middleware('permission:training.create');
        Route::get('/{id}', [EmployeeTrainingController::class, 'show'])->middleware('permission:training.read');
        Route::put('/{id}', [EmployeeTrainingController::class, 'update'])->middleware('permission:training.update');
        Route::delete('/{id}', [EmployeeTrainingController::class, 'destroy'])->middleware('permission:training.delete');
    });

    // Employee children routes
    Route::prefix('employee-children')->group(function () {
        Route::get('/', [EmployeeChildrenController::class, 'index'])->middleware('permission:children.read');
        Route::post('/', [EmployeeChildrenController::class, 'store'])->middleware('permission:children.create');
        Route::get('/{id}', [EmployeeChildrenController::class, 'show'])->middleware('permission:children.read');
        Route::put('/{id}', [EmployeeChildrenController::class, 'update'])->middleware('permission:children.update');
        Route::delete('/{id}', [EmployeeChildrenController::class, 'destroy'])->middleware('permission:children.delete');
    });


    // Job offer routes
    Route::prefix('job-offers')->group(function () {
        Route::get('/', [JobOfferController::class, 'index'])->middleware('permission:job_offer.read');
        Route::post('/', [JobOfferController::class, 'store'])->middleware('permission:job_offer.create');
        Route::get('/{id}', [JobOfferController::class, 'show'])->middleware('permission:job_offer.read');
        Route::put('/{id}', [JobOfferController::class, 'update'])->middleware('permission:job_offer.update');
        Route::delete('/{id}', [JobOfferController::class, 'destroy'])->middleware('permission:job_offer.delete');
        Route::get('/{id}/pdf', [JobOfferController::class, 'generatePdf'])->middleware('permission:job_offer.read');
    });

    // Report routes
    Route::prefix('reports')->group(function () {
        Route::post('/interview-report/export-pdf', [InterviewReportController::class, 'exportPDF'])->middleware('permission:reports.create');
        Route::get('/interview-report/export-excel', [InterviewReportController::class, 'exportExcel'])->middleware('permission:reports.create');
        Route::post('/job-offer-report/export-pdf', [JobOfferReportController::class, 'exportPDF'])->middleware('permission:reports.create');
    });


    // Employee education routes
    Route::prefix('employee-education')->group(function () {
        Route::get('/', [EmployeeEducationController::class, 'index'])->middleware('permission:employee.read');
        Route::post('/', [EmployeeEducationController::class, 'store'])->middleware('permission:employee.create');
        Route::get('/{id}', [EmployeeEducationController::class, 'show'])->middleware('permission:employee.read');
        Route::put('/{id}', [EmployeeEducationController::class, 'update'])->middleware('permission:employee.update');
        Route::delete('/{id}', [EmployeeEducationController::class, 'destroy'])->middleware('permission:employee.delete');
    });

    // Employee language routes
    Route::prefix('employee-language')->group(function () {
        Route::get('/', [EmployeeLanguageController::class, 'index'])->middleware('permission:employee.read');
        Route::post('/', [EmployeeLanguageController::class, 'store'])->middleware('permission:employee.create');
        Route::get('/{id}', [EmployeeLanguageController::class, 'show'])->middleware('permission:employee.read');
        Route::put('/{id}', [EmployeeLanguageController::class, 'update'])->middleware('permission:employee.update');
        Route::delete('/{id}', [EmployeeLanguageController::class, 'destroy'])->middleware('permission:employee.delete');
    });
});


