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
     *     operationId="getJobOffers",
     *     summary="List all job offers with pagination and filtering",
     *     description="Returns a paginated list of job offers. Supports filtering by position and status, sorting by various fields with standard Laravel pagination parameters (page, per_page).",
     *     tags={"Job Offers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *         @OA\Schema(type="integer", example=1, minimum=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=10, minimum=1, maximum=100)
     *     ),
     *     @OA\Parameter(
     *         name="filter_position",
     *         in="query",
     *         description="Filter job offers by position name (comma-separated for multiple values)",
     *         required=false,
     *         @OA\Schema(type="string", example="Manager,Developer")
     *     ),
     *     @OA\Parameter(
     *         name="filter_status",
     *         in="query",
     *         description="Filter job offers by acceptance status (comma-separated for multiple values)",
     *         required=false,
     *         @OA\Schema(type="string", example="Pending,Accepted")
     *     ),
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by field",
     *         required=false,
     *         @OA\Schema(type="string", enum={"job_offer_id", "candidate_name", "position_name", "date", "status"}, example="candidate_name")
     *     ),
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order (asc or desc)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="asc")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of job offers retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Job offers retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="custom_offer_id", type="string", example="JO-2024-001"),
     *                     @OA\Property(property="date", type="string", format="date", example="2024-01-15"),
     *                     @OA\Property(property="candidate_name", type="string", example="John Doe"),
     *                     @OA\Property(property="position_name", type="string", example="Software Developer"),
     *                     @OA\Property(property="salary_detail", type="string", example="$75,000 per annum"),
     *                     @OA\Property(property="acceptance_deadline", type="string", format="date", example="2024-01-30"),
     *                     @OA\Property(property="acceptance_status", type="string", example="Pending"),
     *                     @OA\Property(property="note", type="string", example="Additional benefits included"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:00:00Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-15T10:00:00Z")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=25),
     *                 @OA\Property(property="last_page", type="integer", example=3),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=10),
     *                 @OA\Property(property="has_more_pages", type="boolean", example=true)
     *             ),
     *             @OA\Property(
     *                 property="filters",
     *                 type="object",
     *                 @OA\Property(property="applied_filters", type="object",
     *                     @OA\Property(property="position", type="array", @OA\Items(type="string"), example={"Manager"}),
     *                     @OA\Property(property="status", type="array", @OA\Items(type="string"), example={"Pending"})
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Invalid parameters provided",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object", example={"per_page": {"The per page must be between 1 and 100."}})
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - User not logged in or token expired"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - User does not have permission to access job offers"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve job offers"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        try {
            // Validate incoming parameters
            $validated = $request->validate([
                'page'             => 'integer|min:1',
                'per_page'         => 'integer|min:1|max:100',
                'filter_position'  => 'string|nullable',
                'filter_status'    => 'string|nullable',
                'sort_by'          => 'string|nullable|in:job_offer_id,candidate_name,position_name,date,status',
                'sort_order'       => 'string|nullable|in:asc,desc',
            ]);

            // Determine page size
            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;

            // Build query
            $query = JobOffer::query();

            // Apply position filter if provided
            if (!empty($validated['filter_position'])) {
                $positions = explode(',', $validated['filter_position']);
                $query->whereIn('position_name', array_map('trim', $positions));
            }

            // Apply status filter if provided
            if (!empty($validated['filter_status'])) {
                $statuses = explode(',', $validated['filter_status']);
                $query->whereIn('acceptance_status', array_map('trim', $statuses));
            }

            // Apply sorting
            $sortBy = $validated['sort_by'] ?? 'created_at';
            $sortOrder = $validated['sort_order'] ?? 'desc';
            
            // Map sort_by values to actual column names
            $sortMapping = [
                'job_offer_id' => 'id',
                'candidate_name' => 'candidate_name',
                'position_name' => 'position_name',
                'date' => 'date',
                'status' => 'acceptance_status'
            ];
            
            $sortColumn = $sortMapping[$sortBy] ?? 'created_at';
            $query->orderBy($sortColumn, $sortOrder);

            // Execute pagination
            $jobOffers = $query->paginate($perPage, ['*'], 'page', $page);

            // Build applied filters array
            $appliedFilters = [];
            if (!empty($validated['filter_position'])) {
                $appliedFilters['position'] = explode(',', $validated['filter_position']);
            }
            if (!empty($validated['filter_status'])) {
                $appliedFilters['status'] = explode(',', $validated['filter_status']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Job offers retrieved successfully',
                'data'    => JobOfferResource::collection($jobOffers->items()),
                'pagination' => [
                    'current_page'   => $jobOffers->currentPage(),
                    'per_page'       => $jobOffers->perPage(),
                    'total'          => $jobOffers->total(),
                    'last_page'      => $jobOffers->lastPage(),
                    'from'           => $jobOffers->firstItem(),
                    'to'             => $jobOffers->lastItem(),
                    'has_more_pages' => $jobOffers->hasMorePages(),
                ],
                'filters' => [
                    'applied_filters' => $appliedFilters,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve job offers',
                'error' => $e->getMessage()
            ], 500);
        }
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

    /**
     * Get job offer by candidate name.
     *
     * @OA\Get(
     *     path="/job-offers/by-candidate/{candidateName}",
     *     operationId="getJobOfferByCandidateName",
     *     summary="Get a job offer by candidate name",
     *     description="Returns a job offer based on the candidate's name. Finds the first matching job offer for the specified candidate.",
     *     tags={"Job Offers"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="candidateName",
     *         in="path",
     *         required=true,
     *         description="Name of the candidate",
     *         @OA\Schema(type="string", example="John Doe")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Job offer retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Job offer retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="custom_offer_id", type="string", example="JO-2024-001"),
     *                 @OA\Property(property="date", type="string", format="date", example="2024-01-15"),
     *                 @OA\Property(property="candidate_name", type="string", example="John Doe"),
     *                 @OA\Property(property="position_name", type="string", example="Software Developer"),
     *                 @OA\Property(property="salary_detail", type="string", example="$75,000 per annum"),
     *                 @OA\Property(property="acceptance_deadline", type="string", format="date", example="2024-01-30"),
     *                 @OA\Property(property="acceptance_status", type="string", example="Pending"),
     *                 @OA\Property(property="note", type="string", example="Additional benefits included"),
     *                 @OA\Property(property="created_by", type="string", example="admin"),
     *                 @OA\Property(property="updated_by", type="string", example="admin"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:00:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-15T10:00:00Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Job offer not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Job offer not found for candidate: John Doe")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - User not logged in or token expired"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - User does not have permission to access job offers"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve job offer"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function getByCandidateName($candidateName)
    {
        try {
            // Decode URL-encoded candidate name
            $candidateName = urldecode($candidateName);
            
            $jobOffer = JobOffer::where('candidate_name', 'LIKE', '%' . $candidateName . '%')
                ->first();

            if (!$jobOffer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job offer not found for candidate: ' . $candidateName
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Job offer retrieved successfully',
                'data' => new JobOfferResource($jobOffer)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve job offer',
                'error' => $e->getMessage()
            ], 500);
        }
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
