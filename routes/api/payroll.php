<?php

use App\Http\Controllers\Api\V1\InterOrganizationAdvanceController;
use App\Http\Controllers\Api\V1\PayrollController;
use App\Http\Controllers\Api\V1\PayrollPolicySettingController;
use App\Http\Controllers\Api\V1\PayslipController;
use App\Http\Controllers\Api\V1\TaxBracketController;
use App\Http\Controllers\Api\V1\TaxCalculationController;
use App\Http\Controllers\Api\V1\TaxSettingController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    // Payroll routes - Uses employee_salary permission (main payroll submenu)
    Route::prefix('payrolls')->group(function () {
        // Read routes
        Route::get('/', [PayrollController::class, 'index'])->middleware('permission:employee_salary.read');
        Route::get('/search', [PayrollController::class, 'search'])->middleware('permission:employee_salary.read');
        Route::get('/budget-history', [PayrollController::class, 'budgetHistory'])->middleware('permission:employee_salary.read');
        Route::get('/tax-summary/{payroll}', [PayrollController::class, 'taxSummary'])->middleware('permission:employee_salary.read');
        // Bulk payslip PDF — must be declared before /{payroll} to avoid route collision
        Route::post('/bulk-payslips', [PayslipController::class, 'exportBulkPdf'])->middleware('permission:employee_salary.read');
        Route::get('/{payroll}/payslip', [PayslipController::class, 'show'])->middleware('permission:employee_salary.read');
        Route::get('/{payroll}', [PayrollController::class, 'show'])->middleware('permission:employee_salary.read');

        // Update routes
        Route::put('/{payroll}', [PayrollController::class, 'update'])->middleware('permission:employee_salary.update');

        // Delete routes
        Route::delete('/batch', [PayrollController::class, 'destroyBatch'])->middleware('permission:employee_salary.delete');
        Route::delete('/{payroll}', [PayrollController::class, 'destroy'])->middleware('permission:employee_salary.delete');

        // Bulk Payroll Creation routes
        Route::prefix('bulk')->group(function () {
            Route::post('/preview', [PayrollController::class, 'bulkPreview'])->middleware('permission:employee_salary.create');
            Route::post('/create', [PayrollController::class, 'bulkStore'])->middleware('permission:employee_salary.create');
            Route::get('/status/{batch}', [PayrollController::class, 'bulkStatus'])->middleware('permission:employee_salary.read');
            Route::get('/errors/{batch}', [PayrollController::class, 'bulkDownloadErrors'])->middleware('permission:employee_salary.read');
        });
    });

    // Inter-organization advance routes - Uses payroll_items permission
    Route::prefix('inter-organization-advances')->group(function () {
        Route::get('/', [InterOrganizationAdvanceController::class, 'index'])->middleware('permission:payroll_items.read');
        Route::get('/{id}', [InterOrganizationAdvanceController::class, 'show'])->middleware('permission:payroll_items.read');
        Route::post('/', [InterOrganizationAdvanceController::class, 'store'])->middleware('permission:payroll_items.create');
        Route::put('/{id}', [InterOrganizationAdvanceController::class, 'update'])->middleware('permission:payroll_items.update');
        Route::delete('/{id}', [InterOrganizationAdvanceController::class, 'destroy'])->middleware('permission:payroll_items.delete');
    });

    // Payroll Policy Settings routes - Uses payroll_items permission
    Route::prefix('payroll-policy-settings')->group(function () {
        Route::get('/', [PayrollPolicySettingController::class, 'index'])->middleware('permission:payroll_items.read');
        Route::get('/{id}', [PayrollPolicySettingController::class, 'show'])->middleware('permission:payroll_items.read');
        Route::post('/', [PayrollPolicySettingController::class, 'store'])->middleware('permission:payroll_items.create');
        Route::put('/{id}', [PayrollPolicySettingController::class, 'update'])->middleware('permission:payroll_items.update');
        Route::delete('/{id}', [PayrollPolicySettingController::class, 'destroy'])->middleware('permission:payroll_items.delete');
    });

    // Tax Bracket routes - Uses tax_settings permission
    Route::prefix('tax-brackets')->group(function () {
        Route::get('/', [TaxBracketController::class, 'index'])->middleware('permission:tax_settings.read');
        Route::get('/search', [TaxBracketController::class, 'search'])->middleware('permission:tax_settings.read');
        Route::get('/calculate/{income}', [TaxBracketController::class, 'calculateTax'])->middleware('permission:tax_settings.read');
        Route::get('/{taxBracket}', [TaxBracketController::class, 'show'])->middleware('permission:tax_settings.read');
        Route::post('/', [TaxBracketController::class, 'store'])->middleware('permission:tax_settings.create');
        Route::put('/{taxBracket}', [TaxBracketController::class, 'update'])->middleware('permission:tax_settings.update');
        Route::delete('/{taxBracket}', [TaxBracketController::class, 'destroy'])->middleware('permission:tax_settings.delete');
    });

    // Tax Setting routes - Uses tax_settings permission
    Route::prefix('tax-settings')->group(function () {
        Route::get('/', [TaxSettingController::class, 'index'])->middleware('permission:tax_settings.read');
        Route::get('/by-year/{year}', [TaxSettingController::class, 'byYear'])->middleware('permission:tax_settings.read');
        Route::get('/value/{key}', [TaxSettingController::class, 'value'])->middleware('permission:tax_settings.read');
        Route::get('/{taxSetting}', [TaxSettingController::class, 'show'])->middleware('permission:tax_settings.read');
        Route::post('/', [TaxSettingController::class, 'store'])->middleware('permission:tax_settings.create');
        Route::put('/{taxSetting}', [TaxSettingController::class, 'update'])->middleware('permission:tax_settings.update');
        Route::delete('/{taxSetting}', [TaxSettingController::class, 'destroy'])->middleware('permission:tax_settings.delete');
        Route::patch('/{taxSetting}/toggle', [TaxSettingController::class, 'toggleSelection'])->middleware('permission:tax_settings.update');
        Route::post('/bulk-update', [TaxSettingController::class, 'bulkUpdate'])->middleware('permission:tax_settings.update');
    });

    // Tax Calculation routes - Uses tax_settings permission
    Route::prefix('tax-calculations')->group(function () {
        Route::post('/payroll', [TaxCalculationController::class, 'calculatePayroll'])->middleware('permission:tax_settings.read');
        Route::post('/income-tax', [TaxCalculationController::class, 'calculateIncomeTax'])->middleware('permission:tax_settings.read');
        Route::post('/annual-summary', [TaxCalculationController::class, 'calculateAnnualSummary'])->middleware('permission:tax_settings.read');

        // Thai compliance endpoints
        Route::post('/compliance-check', [TaxCalculationController::class, 'complianceCheck'])->middleware('permission:tax_settings.read');
        Route::post('/thai-report', [TaxCalculationController::class, 'generateThaiReport'])->middleware('permission:tax_settings.read');
    });
});
