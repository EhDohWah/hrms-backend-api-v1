<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Tax\AnnualSummaryRequest;
use App\Http\Requests\Tax\CalculateIncomeTaxRequest;
use App\Http\Requests\Tax\CalculatePayrollTaxRequest;
use App\Http\Requests\Tax\ComplianceCheckRequest;
use App\Http\Requests\Tax\ThaiReportRequest;
use App\Services\TaxCalculationService;
use Illuminate\Http\JsonResponse;

/**
 * Thai Personal Income Tax calculation endpoints following Thai Revenue Department regulations.
 */
class TaxCalculationController extends BaseApiController
{
    public function __construct(
        private readonly TaxCalculationService $taxCalculationService,
    ) {}

    /**
     * Calculate Thai Revenue Department compliant payroll.
     */
    public function calculatePayroll(CalculatePayrollTaxRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->taxCalculationService
            ->forYear($validated['tax_year'] ?? (int) date('Y'))
            ->calculatePayrollForEmployee(
                $validated['employee_id'],
                $validated['gross_salary'],
                $validated['additional_income'] ?? [],
                $validated['additional_deductions'] ?? [],
            );

        return $this->successResponse($result, 'Payroll calculated successfully');
    }

    /**
     * Calculate Thai progressive income tax with bracket breakdown.
     *
     * Two modes:
     * - Simple: provide taxable_income directly (already deducted).
     * - Full:   provide annual_gross_income + personal variables; the service
     *           computes all allowances and deductions automatically.
     */
    public function calculateIncomeTax(CalculateIncomeTaxRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $service = $this->taxCalculationService
            ->forYear($validated['tax_year'] ?? (int) date('Y'));

        if (isset($validated['annual_gross_income'])) {
            // Full mode: compute employment deductions + personal allowances + SSF/PVD
            $result = $service->calculateIncomeTaxDetailed(0, $validated);
        } else {
            // Simple mode: tax on the provided taxable income amount
            $result = $service->calculateIncomeTaxDetailed((float) $validated['taxable_income']);
        }

        return $this->successResponse($result, 'Income tax calculated successfully');
    }

    /**
     * Calculate annual tax summary for an employee.
     */
    public function calculateAnnualSummary(AnnualSummaryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->taxCalculationService
            ->forYear($validated['tax_year'] ?? (int) date('Y'))
            ->calculateAnnualTax($validated['employee_id'], $validated['monthly_payrolls']);

        return $this->successResponse($result, 'Annual tax summary calculated successfully');
    }

    /**
     * Thai Revenue Department compliance validation.
     */
    public function complianceCheck(ComplianceCheckRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->taxCalculationService
            ->forYear($validated['tax_year'] ?? (int) date('Y'))
            ->performComplianceCheck($validated['employee_id'], $validated['gross_salary']);

        return $this->successResponse($result, 'Compliance check completed successfully');
    }

    /**
     * Generate official Thai Revenue Department tax calculation report.
     */
    public function generateThaiReport(ThaiReportRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->taxCalculationService
            ->forYear($validated['tax_year'] ?? (int) date('Y'))
            ->generateReport(
                $validated['employee_id'],
                $validated['gross_salary'],
                $validated['additional_income'] ?? [],
                $validated['additional_deductions'] ?? [],
            );

        return $this->successResponse($result, 'Thai tax report generated successfully');
    }
}
