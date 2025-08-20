<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PayrollController;
use App\Http\Controllers\Api\PayrollGrantAllocationController;
use App\Http\Controllers\Api\InterSubsidiaryAdvanceController;
use App\Http\Controllers\Api\TaxBracketController;
use App\Http\Controllers\Api\TaxSettingController;
use App\Http\Controllers\Api\TaxCalculationController;

Route::middleware('auth:sanctum')->group(function () {
    // Payroll routes (use middleware permission:read payrolls)
    Route::prefix('payrolls')->group(function () {
        Route::get('/', [PayrollController::class, 'index'])->middleware('permission:payroll.read');
        Route::get('/employee-employment', [PayrollController::class, 'getEmployeeEmploymentDetail'])->middleware('permission:payroll.read');
        Route::get('/employee-employment-calculated', [PayrollController::class, 'getEmployeeEmploymentDetailWithCalculations'])->middleware('permission:payroll.read');
        Route::get('/tax-summary/{id}', [PayrollController::class, 'getTaxSummary'])->middleware('permission:payroll.read');
        Route::get('/{id}', [PayrollController::class, 'show'])->middleware('permission:payroll.read');
        Route::post('/', [PayrollController::class, 'store'])->middleware('permission:payroll.create');
        Route::put('/{id}', [PayrollController::class, 'update'])->middleware('permission:payroll.update');
        Route::delete('/{id}', [PayrollController::class, 'destroy'])->middleware('permission:payroll.delete');
        
        // New automated tax calculation routes
        Route::post('/calculate', [PayrollController::class, 'calculatePayroll'])->middleware('permission:payroll.read');
        Route::post('/bulk-calculate', [PayrollController::class, 'bulkCalculatePayroll'])->middleware('permission:payroll.create');
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

    // Tax Bracket routes (admin and payroll permissions)
    Route::prefix('tax-brackets')->group(function () {
        Route::get('/', [TaxBracketController::class, 'index'])->middleware('permission:tax.read');
        Route::post('/', [TaxBracketController::class, 'store'])->middleware('permission:tax.create');
        Route::get('/{id}', [TaxBracketController::class, 'show'])->middleware('permission:tax.read');
        Route::put('/{id}', [TaxBracketController::class, 'update'])->middleware('permission:tax.update');
        Route::delete('/{id}', [TaxBracketController::class, 'destroy'])->middleware('permission:tax.delete');
        Route::get('/calculate/{income}', [TaxBracketController::class, 'calculateTax'])->middleware('permission:tax.read');
    });

    // Tax Setting routes (admin and payroll permissions)
    Route::prefix('tax-settings')->group(function () {
        Route::get('/', [TaxSettingController::class, 'index'])->middleware('permission:tax.read');
        Route::post('/', [TaxSettingController::class, 'store'])->middleware('permission:tax.create');
        Route::get('/{id}', [TaxSettingController::class, 'show'])->middleware('permission:tax.read');
        Route::put('/{id}', [TaxSettingController::class, 'update'])->middleware('permission:tax.update');
        Route::delete('/{id}', [TaxSettingController::class, 'destroy'])->middleware('permission:tax.delete');
        Route::patch('/{id}/toggle', [TaxSettingController::class, 'toggleSelection'])->middleware('permission:tax.update');
        Route::get('/by-year/{year}', [TaxSettingController::class, 'getByYear'])->middleware('permission:tax.read');
        Route::get('/value/{key}', [TaxSettingController::class, 'getValue'])->middleware('permission:tax.read');
        Route::post('/bulk-update', [TaxSettingController::class, 'bulkUpdate'])->middleware('permission:tax.update');
    });

    // Tax Calculation routes (admin and payroll permissions)
    Route::prefix('tax-calculations')->group(function () {
        Route::post('/payroll', [TaxCalculationController::class, 'calculatePayroll'])->middleware('permission:tax.read');
        Route::post('/income-tax', [TaxCalculationController::class, 'calculateIncomeTax'])->middleware('permission:tax.read');
        Route::post('/annual-summary', [TaxCalculationController::class, 'calculateAnnualSummary'])->middleware('permission:tax.read');
        Route::post('/validate-inputs', [TaxCalculationController::class, 'validateInputs'])->middleware('permission:tax.read');
        
        // Thai compliance endpoints
        Route::post('/compliance-check', [TaxCalculationController::class, 'complianceCheck'])->middleware('permission:tax.read');
        Route::post('/thai-report', [TaxCalculationController::class, 'generateThaiReport'])->middleware('permission:tax.read');
    });
});
