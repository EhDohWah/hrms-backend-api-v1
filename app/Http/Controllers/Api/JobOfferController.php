<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobOffer;
use Illuminate\Http\Request;
use App\Http\Requests\JobOfferRequest;
use App\Http\Resources\JobOfferResource;
use OpenApi\Annotations as OA;
use PDF;


/**
 * @OA\Tag(
 *     name="Job Offers",
 *     description="Operations related to job offers"
 * )
 */
class JobOfferController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @OA\Get(
     *     path="/job-offers",
     *     summary="Get all job offers",
     *     description="Returns a list of all job offers",
     *     operationId="getJobOffers",
     *     tags={"Job Offers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Job offers retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/JobOffer")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve job offers"),
     *             @OA\Property(property="error", type="string", example="Internal server error occurred")
     *         )
     *     )
     * )
     */
    public function index()
    {
        $jobOffers = JobOffer::all();
        return response()->json([
            'success' => true,
            'message' => 'Job offers retrieved successfully',
            'data' => JobOfferResource::collection($jobOffers)
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @OA\Post(
     *     path="/job-offers",
     *     summary="Create a new job offer",
     *     description="Creates a new job offer and returns it",
     *     operationId="storeJobOffer",
     *     tags={"Job Offers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/JobOffer")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Job offer created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Job offer created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/JobOffer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\AdditionalProperties(
     *                     type="array",
     *                     @OA\Items(type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create job offer"),
     *             @OA\Property(property="error", type="string", example="Internal server error occurred")
     *         )
     *     )
     * )
     */
    public function store(JobOfferRequest $request)
    {
        $jobOffer = JobOffer::create($request->validated());
        return response()->json([
            'success' => true,
            'message' => 'Job offer created successfully',
            'data' => new JobOfferResource($jobOffer)
        ], 201);
    }

    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/job-offers/{id}",
     *     summary="Get a job offer by ID",
     *     description="Returns a single job offer",
     *     operationId="getJobOfferById",
     *     tags={"Job Offers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of job offer to return",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Job offer retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/JobOffer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Job offer not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Job offer not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve job offer"),
     *             @OA\Property(property="error", type="string", example="Internal server error occurred")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        $jobOffer = JobOffer::findOrFail($id);
        return response()->json([
            'success' => true,
            'message' => 'Job offer retrieved successfully',
            'data' => new JobOfferResource($jobOffer)
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @OA\Put(
     *     path="/job-offers/{id}",
     *     summary="Update a job offer",
     *     description="Updates a job offer and returns it",
     *     operationId="updateJobOffer",
     *     tags={"Job Offers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of job offer to update",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/JobOffer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Job offer updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Job offer updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/JobOffer")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Job offer not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Job offer not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\AdditionalProperties(
     *                     type="array",
     *                     @OA\Items(type="string")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to update job offer"),
     *             @OA\Property(property="error", type="string", example="Internal server error occurred")
     *         )
     *     )
     * )
     */
    public function update(JobOfferRequest $request, $id)
    {
        $jobOffer = JobOffer::findOrFail($id);
        $jobOffer->update($request->validated());
        return response()->json([
            'success' => true,
            'message' => 'Job offer updated successfully',
            'data' => new JobOfferResource($jobOffer)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @OA\Delete(
     *     path="/job-offers/{id}",
     *     summary="Delete a job offer",
     *     description="Deletes a job offer by ID",
     *     operationId="deleteJobOffer",
     *     tags={"Job Offers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of job offer to delete",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Job offer deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Job offer deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Job offer not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Job offer not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to delete job offer"),
     *             @OA\Property(property="error", type="string", example="Internal server error occurred")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        $jobOffer = JobOffer::findOrFail($id);
        $jobOffer->delete();
        return response()->json([
            'success' => true,
            'message' => 'Job offer deleted successfully'
        ]);
    }

    /**
     *
     * Create a method to preview or display the job offer letter on vue component modal,
     *
     * @param string $custom_offer_id
     *
     */

    /**
     * @OA\Get(
     *     path="/job-offers/{custom_offer_id}/pdf",
     *     summary="Generate a PDF job offer letter",
     *     description="Generates a downloadable PDF job offer letter based on the custom offer ID",
     *     operationId="generateJobOfferPdf",
     *     tags={"Job Offers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="custom_offer_id",
     *         in="path",
     *         description="Custom ID of the job offer",
     *         required=true,
     *         @OA\Schema(type="string")
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
     *         response=404,
     *         description="Job offer not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Job offer not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to generate PDF"),
     *             @OA\Property(property="error", type="string", example="Internal server error occurred")
     *         )
     *     )
     * )
     *
     * Generate a PDF job offer letter
     *
     * @param string $custom_offer_id
     * @return \Illuminate\Http\Response
     */
    public function generatePdf($custom_offer_id)
    {
        // Find the job offer by custom ID
        $jobOffer = JobOffer::where('custom_offer_id', $custom_offer_id)->first();
        if (!$jobOffer) {
            return response()->json([
                'success' => false,
                'message' => 'Job offer not found'
            ], 404);
        }

        // Prepare data for the job offer template
        $data = [
            'date' => $jobOffer->date ? $this->formatDateWithSuperscript($jobOffer->date) : now()->format('dS F, Y'),
            'position' => $jobOffer->position_name,
            'subject' => 'Job Offer',
            'probation_salary' => $jobOffer->salary_detail,
            'post_probation_salary' => $jobOffer->salary_detail,
            'acceptance_deadline' => $jobOffer->acceptance_deadline ? $this->formatDateWithSuperscript($jobOffer->acceptance_deadline) : 'N/A',
            'employee_name' => $jobOffer->candidate_name,
        ];

        // Load the job offer view and pass the data
        $pdf = PDF::loadView('jobOffer', $data);

        // Set paper to A4 size
        $pdf->setPaper('a4', 'portrait');

        // Generate a filename based on the offer details
        $filename = 'job-offer-' . $jobOffer->candidate_name . '.pdf';

        // Return the PDF as a download
        return $pdf->download($filename)
                   ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                   ->header('Pragma', 'no-cache')
                   ->header('Expires', '0');
    }

    function formatDateWithSuperscript($date)
    {
        if (!$date) return 'N/A';

        $day = date('j', strtotime($date));
        $monthYear = date('F, Y', strtotime($date));

        // Determine the correct suffix
        if ($day % 10 == 1 && $day != 11) {
            $suffix = 'st';
        } elseif ($day % 10 == 2 && $day != 12) {
            $suffix = 'nd';
        } elseif ($day % 10 == 3 && $day != 13) {
            $suffix = 'rd';
        } else {
            $suffix = 'th';
        }

        return $day . '<sup>' . $suffix . '</sup> ' . $monthYear;
    }

}
