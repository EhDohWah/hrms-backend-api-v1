<?php

use App\Http\Controllers\Api\BulkPayrollController;
use App\Http\Controllers\Api\InterOrganizationAdvanceController;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PayrollGrantAllocationController;
use App\Http\Controllers\Api\TaxBracketController;
use App\Http\Controllers\Api\TaxCalculationController;
use App\Http\Controllers\Api\TaxSettingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Payroll routes - Uses employee_salary permission (main payroll submenu)
    Route::prefix('payrolls')->group(function () {
        // Read routes
        Route::get('/', [PayrollController::class, 'index'])->middleware('permission:employee_salary.read');
        Route::get('/search', [PayrollController::class, 'search'])->middleware('permission:employee_salary.read');
        Route::get('/budget-history', [PayrollController::class, 'budgetHistory'])->middleware('permission:employee_salary.read');
        Route::get('/employee-employment', [PayrollController::class, 'employeeEmploymentDetail'])->middleware('permission:employee_salary.read');
        Route::get('/employee-employment-calculated', [PayrollController::class, 'employeeEmploymentDetailWithCalculations'])->middleware('permission:employee_salary.read');
        Route::get('/preview-advances', [PayrollController::class, 'previewAdvances'])->middleware('permission:employee_salary.read');
        Route::get('/tax-summary/{id}', [PayrollController::class, 'taxSummary'])->middleware('permission:employee_salary.read');
        Route::get('/{id}', [PayrollController::class, 'show'])->middleware('permission:employee_salary.read');
        Route::post('/calculate', [PayrollController::class, 'calculatePayroll'])->middleware('permission:employee_salary.read');

        // Edit routes (create, update, delete)
        Route::post('/', [PayrollController::class, 'store'])->middleware('permission:employee_salary.edit');
        Route::put('/{id}', [PayrollController::class, 'update'])->middleware('permission:employee_salary.edit');
        Route::delete('/{id}', [PayrollController::class, 'destroy'])->middleware('permission:employee_salary.edit');
        Route::post('/bulk-calculate', [PayrollController::class, 'bulkCalculatePayroll'])->middleware('permission:employee_salary.edit');

        // Bulk Payroll Creation routes with real-time progress tracking
        Route::prefix('bulk')->middleware('permission:employee_salary.edit')->group(function () {
            Route::post('/preview', [BulkPayrollController::class, 'preview']);
            Route::post('/create', [BulkPayrollController::class, 'store']);
            Route::get('/status/{batchId}', [BulkPayrollController::class, 'status']);
            Route::get('/errors/{batchId}', [BulkPayrollController::class, 'downloadErrors']);
        });
    });

    // Inter-organization advance routes - Uses payroll_items permission
    Route::prefix('inter-organization-advances')->group(function () {
        Route::get('/', [InterOrganizationAdvanceController::class, 'index'])->middleware('permission:payroll_items.read');
        Route::get('/{id}', [InterOrganizationAdvanceController::class, 'show'])->middleware('permission:payroll_items.read');
        Route::post('/', [InterOrganizationAdvanceController::class, 'store'])->middleware('permission:payroll_items.edit');
        Route::put('/{id}', [InterOrganizationAdvanceController::class, 'update'])->middleware('permission:payroll_items.edit');
        Route::delete('/{id}', [InterOrganizationAdvanceController::class, 'destroy'])->middleware('permission:payroll_items.edit');
    });

    // Payroll grant allocation routes - Uses payroll_items permission
    Route::prefix('payroll-grant-allocations')->group(function () {
        Route::get('/', [PayrollGrantAllocationController::class, 'index'])->middleware('permission:payroll_items.read');
        Route::get('/{id}', [PayrollGrantAllocationController::class, 'show'])->middleware('permission:payroll_items.read');
        Route::post('/', [PayrollGrantAllocationController::class, 'store'])->middleware('permission:payroll_items.edit');
        Route::put('/{id}', [PayrollGrantAllocationController::class, 'update'])->middleware('permission:payroll_items.edit');
        Route::delete('/{id}', [PayrollGrantAllocationController::class, 'destroy'])->middleware('permission:payroll_items.edit');
    });

    // Tax Bracket routes - Uses tax_settings permission
    Route::prefix('tax-brackets')->group(function () {
        Route::get('/', [TaxBracketController::class, 'index'])->middleware('permission:tax_settings.read');
        Route::get('/search', [TaxBracketController::class, 'search'])->middleware('permission:tax_settings.read');
        Route::get('/{id}', [TaxBracketController::class, 'show'])->middleware('permission:tax_settings.read');
        Route::get('/calculate/{income}', [TaxBracketController::class, 'calculateTax'])->middleware('permission:tax_settings.read');
        Route::post('/', [TaxBracketController::class, 'store'])->middleware('permission:tax_settings.edit');
        Route::put('/{id}', [TaxBracketController::class, 'update'])->middleware('permission:tax_settings.edit');
        Route::delete('/{id}', [TaxBracketController::class, 'destroy'])->middleware('permission:tax_settings.edit');
    });

    // Tax Setting routes - Uses tax_settings permission
    Route::prefix('tax-settings')->group(function () {
        Route::get('/', [TaxSettingController::class, 'index'])->middleware('permission:tax_settings.read');
        Route::get('/{id}', [TaxSettingController::class, 'show'])->middleware('permission:tax_settings.read');
        Route::get('/by-year/{year}', [TaxSettingController::class, 'byYear'])->middleware('permission:tax_settings.read');
        Route::get('/value/{key}', [TaxSettingController::class, 'value'])->middleware('permission:tax_settings.read');
        Route::post('/', [TaxSettingController::class, 'store'])->middleware('permission:tax_settings.edit');
        Route::put('/{id}', [TaxSettingController::class, 'update'])->middleware('permission:tax_settings.edit');
        Route::delete('/{id}', [TaxSettingController::class, 'destroy'])->middleware('permission:tax_settings.edit');
        Route::patch('/{id}/toggle', [TaxSettingController::class, 'toggleSelection'])->middleware('permission:tax_settings.edit');
        Route::post('/bulk-update', [TaxSettingController::class, 'bulkUpdate'])->middleware('permission:tax_settings.edit');
    });

    // Tax Calculation routes - Uses tax_settings permission
    Route::prefix('tax-calculations')->group(function () {
        Route::post('/payroll', [TaxCalculationController::class, 'calculatePayroll'])->middleware('permission:tax_settings.read');
        Route::post('/income-tax', [TaxCalculationController::class, 'calculateIncomeTax'])->middleware('permission:tax_settings.read');
        Route::post('/annual-summary', [TaxCalculationController::class, 'calculateAnnualSummary'])->middleware('permission:tax_settings.read');
        Route::post('/validate-inputs', [TaxCalculationController::class, 'validateInputs'])->middleware('permission:tax_settings.read');

        // Thai compliance endpoints
        Route::post('/compliance-check', [TaxCalculationController::class, 'complianceCheck'])->middleware('permission:tax_settings.read');
        Route::post('/thai-report', [TaxCalculationController::class, 'generateThaiReport'])->middleware('permission:tax_settings.read');
    });
});
