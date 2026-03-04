<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Requests\ExportJobOfferReportRequest;
use App\Models\JobOffer;
use Carbon\Carbon;
use OpenApi\Attributes as OA;
use PDF;

/**
 * Handles job offer report exports (PDF format).
 */
#[OA\Tag(name: 'Job Offer Reports', description: 'Operations related to job offer reports')]
class JobOfferReportController extends BaseApiController
{
    /**
     * Export the job offer report as a PDF.
     */
    #[OA\Post(
        path: '/reports/job-offer-report/export-pdf',
        summary: 'Export job offer report as PDF',
        description: 'Generates a downloadable PDF report of job offers within a specified date range',
        operationId: 'exportJobOfferReportPdf',
        tags: ['Job Offer Reports'],
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
    public function exportPDF(ExportJobOfferReportRequest $request)
    {
        $validated = $request->validated();
        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->endOfDay();

        $currentDateTime = now()->format('Y-m-d H:i:s');
        $jobOffers = JobOffer::whereBetween('created_at', [$startDate, $endDate])->get();
        $totalPages = (int) ceil($jobOffers->count() / 15);

        $pdf = PDF::loadView('reports.job_offer_report_pdf', compact(
            'jobOffers', 'startDate', 'endDate', 'currentDateTime', 'totalPages'
        ));
        $pdf->setPaper('a4', 'landscape');
        $options = $pdf->getDomPDF()->getOptions();
        $options->setIsPhpEnabled(true);
        $pdf->getDomPDF()->setOptions($options);

        $filename = 'job_offer_report_'.now()->format('YmdHis').'.pdf';

        return $pdf->download($filename)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
