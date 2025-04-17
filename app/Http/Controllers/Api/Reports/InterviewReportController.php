<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Requests\InterviewReportRequest;
use App\Models\Interview;
use PDF;
use Carbon\Carbon;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Interview Reports",
 *     description="Operations related to interview reports"
 * )
 */
class InterviewReportController extends Controller
{
    /**
     * Export the interview report as a PDF.
     *
     * This method uses the InterviewReportRequest for validation.
     *
     * @OA\Post(
     *     path="/reports/interview-report/export-pdf",
     *     summary="Export interview report as PDF",
     *     description="Generates a downloadable PDF report of interviews within a specified date range",
     *     operationId="exportInterviewReportPdf",
     *     tags={"Interview Reports"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"start_date", "end_date"},
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-04-02", description="Start date in format YYYY-MM-DD"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-04-31", description="End date in format YYYY-MM-DD")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="PDF generated successfully",
     *         @OA\MediaType(
     *             mediaType="application/pdf",
     *             @OA\Schema(type="string", format="binary")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Invalid date format.")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="error", type="string", example="Failed to generate PDF")
     *         )
     *     )
     * )
     */
    public function exportPDF(InterviewReportRequest $request)
    {
        // Get validated start and end dates
        $startDate = Carbon::parse($request->input('start_date'));
        $endDate = Carbon::parse($request->input('end_date'));

        // Get the current date and time in the application's timezone
        $currentDateTime = now()->format('Y-m-d H:i:s');
        // Fetch interviews within the selected date range.
        $interviews = Interview::whereBetween('created_at', [$startDate, $endDate])->get();

        // No need to paginate here as PDF will handle all records
        // The page numbers will be automatically added by the PDF library
        // based on the template's {PAGENO} and {nb} placeholders

        // Add current page number to the view data
        $totalInterviews = $interviews->count();
        $interviewsPerPage = 15; // Adjust based on how many fit on one page
        $totalPages = ceil($totalInterviews / $interviewsPerPage);

        // Load the PDF view and pass in the data.
        $pdf = PDF::loadView('reports.interview_report_pdf', compact('interviews', 'startDate', 'endDate', 'currentDateTime', 'totalPages'));

        // Set paper to A4 landscape as defined in the template
        $pdf->setPaper('a4', 'landscape');

        // * Enable PHP execution in the PDF view
        $options = $pdf->getDomPDF()->getOptions();
        $options->setIsPhpEnabled(true);
        $pdf->getDomPDF()->setOptions($options);

        // Generate a filename based on the date range
        $filename = 'interview_report_' . now()->format('YmdHis') . '.pdf';

        // Return the PDF as a download with cache control headers
        return $pdf->download($filename)
                   ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                   ->header('Pragma', 'no-cache')
                   ->header('Expires', '0');
    }
}
