<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Payroll\ExportBulkPayslipsPdfRequest;
use App\Models\Payroll;
use App\Services\PayrollService;

class PayslipController extends BaseApiController
{
    public function __construct(private readonly PayrollService $payrollService) {}

    /**
     * Generate a single payslip PDF for one payroll record.
     */
    public function show(Payroll $payroll)
    {
        return $this->payrollService->generatePayslip($payroll);
    }

    /**
     * Generate a combined PDF of all payslips for an organisation and pay month.
     */
    public function exportBulkPdf(ExportBulkPayslipsPdfRequest $request)
    {
        return $this->payrollService->generateBulkPayslips(
            $request->validated('organization'),
            $request->validated('pay_period_date')
        );
    }
}
