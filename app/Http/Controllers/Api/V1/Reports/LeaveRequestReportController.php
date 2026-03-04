<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\ExportIndividualLeaveReportRequest;
use App\Http\Requests\ExportLeaveReportRequest;
use App\Services\LeaveRequestReportService;
use OpenApi\Attributes as OA;
use PDF;

/**
 * Handles leave request report exports (PDF format).
 */
#[OA\Tag(name: 'Leave Request Reports', description: 'Operations related to leave request reports')]
class LeaveRequestReportController extends BaseApiController
{
    public function __construct(
        private readonly LeaveRequestReportService $leaveRequestReportService,
    ) {}

    /**
     * Export department leave request report as PDF.
     */
    #[OA\Post(
        path: '/reports/leave-request-report/export-pdf',
        summary: 'Export leave request report as PDF',
        description: 'Generates a downloadable PDF report of leave requests and balances within a specified date range, filtered by work location and department',
        operationId: 'exportLeaveRequestReportPdf',
        tags: ['Leave Request Reports'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['start_date', 'end_date', 'work_location', 'department'],
                properties: [
                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2025-04-02'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2025-04-30'),
                    new OA\Property(property: 'work_location', type: 'string', example: 'SMRU'),
                    new OA\Property(property: 'department', type: 'string', example: 'Human Resources'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'PDF generated successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function exportPDF(ExportLeaveReportRequest $request)
    {
        $validated = $request->validated();
        $viewData = $this->leaveRequestReportService->getDepartmentPdfViewData($validated);

        $pdf = PDF::loadView('reports.leave_request_report_pdf', $viewData);
        $pdf->setPaper('a4', 'landscape');
        $options = $pdf->getDomPDF()->getOptions();
        $options->setIsPhpEnabled(true);
        $pdf->getDomPDF()->setOptions($options);

        $workLocationSlug = str_replace(' ', '_', strtolower($validated['work_location']));
        $departmentSlug = str_replace(' ', '_', strtolower($validated['department']));
        $filename = "leave_request_report_{$workLocationSlug}_{$departmentSlug}_{$viewData['startDate']->format('Ymd')}_to_{$viewData['endDate']->format('Ymd')}_".now()->format('YmdHis').'.pdf';

        return $pdf->download($filename)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Export individual employee leave request report as PDF.
     */
    #[OA\Post(
        path: '/reports/leave-request-report/export-individual-pdf',
        summary: 'Export individual employee leave request report as PDF',
        description: 'Generates a downloadable PDF report of leave requests for a specific employee within a specified date range',
        operationId: 'exportIndividualLeaveRequestReportPdf',
        tags: ['Leave Request Reports'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['start_date', 'end_date', 'staff_id'],
                properties: [
                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2025-08-01'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2025-08-31'),
                    new OA\Property(property: 'staff_id', type: 'string', example: 'EMP001'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'PDF generated successfully'),
            new OA\Response(response: 404, description: 'Employee not found'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function exportIndividualPDF(ExportIndividualLeaveReportRequest $request)
    {
        $validated = $request->validated();
        $viewData = $this->leaveRequestReportService->getIndividualPdfViewData($validated);

        if (! $viewData) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        $pdf = PDF::loadView('reports.leave_request_report_individual_pdf', $viewData);
        $pdf->setPaper('a4', 'landscape');
        $options = $pdf->getDomPDF()->getOptions();
        $options->setIsPhpEnabled(true);
        $pdf->getDomPDF()->setOptions($options);

        $employee = $viewData['employee'];
        $employeeSlug = str_replace(' ', '_', strtolower($employee->first_name_en.'_'.$employee->last_name_en));
        $filename = "individual_leave_request_report_{$validated['staff_id']}_{$employeeSlug}_{$viewData['startDate']->format('Ymd')}_to_{$viewData['endDate']->format('Ymd')}_".now()->format('YmdHis').'.pdf';

        return $pdf->download($filename)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
