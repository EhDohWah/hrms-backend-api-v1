<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Exports\InterviewReportExport;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\ExportInterviewReportRequest;
use App\Models\Interview;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use OpenApi\Attributes as OA;
use PDF;

/**
 * Handles interview report exports (PDF and Excel formats).
 */
#[OA\Tag(name: 'Interview Reports', description: 'Operations related to interview reports')]
class InterviewReportController extends BaseApiController
{
    /**
     * Export the interview report as a PDF.
     */
    #[OA\Post(
        path: '/reports/interview-report/export-pdf',
        summary: 'Export interview report as PDF',
        description: 'Generates a downloadable PDF report of interviews within a specified date range',
        operationId: 'exportInterviewReportPdf',
        tags: ['Interview Reports'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['start_date', 'end_date'],
                properties: [
                    new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2025-04-02'),
                    new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2025-04-30'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'PDF generated successfully'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function exportPDF(ExportInterviewReportRequest $request)
    {
        $validated = $request->validated();
        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);

        $currentDateTime = now()->format('Y-m-d H:i:s');
        $interviews = Interview::whereBetween('created_at', [$startDate, $endDate])->get();
        $totalPages = (int) ceil($interviews->count() / 15);

        $pdf = PDF::loadView('reports.interview_report_pdf', compact(
            'interviews', 'startDate', 'endDate', 'currentDateTime', 'totalPages'
        ));
        $pdf->setPaper('a4', 'landscape');
        $options = $pdf->getDomPDF()->getOptions();
        $options->setIsPhpEnabled(true);
        $pdf->getDomPDF()->setOptions($options);

        $filename = 'interview_report_'.now()->format('YmdHis').'.pdf';

        return $pdf->download($filename)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Export the interview report as an Excel file.
     */
    #[OA\Get(
        path: '/reports/interview-report/export-excel',
        summary: 'Export interview report as Excel',
        description: 'Generates a downloadable Excel report of interviews within a specified date range',
        operationId: 'exportInterviewReportExcel',
        tags: ['Interview Reports'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(name: 'start_date', in: 'query', required: true, description: 'Start date (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date')),
            new OA\Parameter(name: 'end_date', in: 'query', required: true, description: 'End date (YYYY-MM-DD)', schema: new OA\Schema(type: 'string', format: 'date')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Excel file download'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function exportExcel(ExportInterviewReportRequest $request)
    {
        $validated = $request->validated();
        $start = Carbon::parse($validated['start_date'])->startOfDay()->toDateString();
        $end = Carbon::parse($validated['end_date'])->endOfDay()->toDateString();

        $fileName = 'interview_report_'.now()->format('YmdHis').'.xlsx';

        return Excel::download(
            new InterviewReportExport($start, $end),
            $fileName
        );
    }
}
