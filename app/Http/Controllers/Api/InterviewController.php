<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InterviewRequest;
use App\Http\Resources\InterviewResource;
use App\Models\Interview;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Interviews",
 *     description="API Endpoints for managing interviews"
 * )
 */
class InterviewController extends Controller
{
    /**
     * @OA\Get(
     *     path="/interviews",
     *     operationId="getInterviews",
     *     summary="List all interviews with pagination and filtering",
     *     description="Returns a paginated list of interviews. Supports filtering by job_position and hired_status, sorting by candidate_name, job_position, or interview_date with standard Laravel pagination parameters (page, per_page).",
     *     tags={"Interviews"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=1, minimum=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=10, minimum=1, maximum=100)
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_job_position",
     *         in="query",
     *         description="Filter interviews by job position (comma-separated for multiple values)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="Software Engineer,Project Manager")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_hired_status",
     *         in="query",
     *         description="Filter interviews by hired status (comma-separated for multiple values)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="hired,rejected")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by field (candidate_name, job_position, or interview_date)",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"candidate_name", "job_position", "interview_date"}, example="candidate_name")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order (asc or desc)",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="asc")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Interviews retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Interviews retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/Interview")
     *             ),
     *
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="last_page", type="integer", example=5),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=10),
     *                 @OA\Property(property="has_more_pages", type="boolean", example=true)
     *             ),
     *             @OA\Property(
     *                 property="filters",
     *                 type="object",
     *                 @OA\Property(property="applied_filters", type="object",
     *                     @OA\Property(property="job_position", type="array", @OA\Items(type="string"), example={"Software Engineer"}),
     *                     @OA\Property(property="hired_status", type="array", @OA\Items(type="string"), example={"hired"})
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Invalid parameters provided",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object", example={"per_page": {"The per page must be between 1 and 100."}})
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve interviews"),
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
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'filter_job_position' => 'string|nullable',
                'filter_hired_status' => 'string|nullable',
                'sort_by' => 'string|nullable|in:candidate_name,job_position,interview_date',
                'sort_order' => 'string|nullable|in:asc,desc',
            ]);

            // Determine page size
            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;

            // Build query
            $query = Interview::query();

            // Apply job position filter if provided
            if (! empty($validated['filter_job_position'])) {
                $jobPositions = explode(',', $validated['filter_job_position']);
                $query->whereIn('job_position', $jobPositions);
            }

            // Apply hired status filter if provided
            if (! empty($validated['filter_hired_status'])) {
                $hiredStatuses = explode(',', $validated['filter_hired_status']);
                $query->whereIn('hired_status', $hiredStatuses);
            }

            // Apply sorting
            $sortBy = $validated['sort_by'] ?? 'created_at';
            $sortOrder = $validated['sort_order'] ?? 'desc';

            // Validate sort field and apply sorting
            if (in_array($sortBy, ['candidate_name', 'job_position', 'interview_date'])) {
                $query->orderBy($sortBy, $sortOrder);
            } else {
                $query->orderBy('created_at', 'desc');
            }

            // Execute pagination
            $interviews = $query->paginate($perPage, ['*'], 'page', $page);

            // Build applied filters array
            $appliedFilters = [];
            if (! empty($validated['filter_job_position'])) {
                $appliedFilters['job_position'] = explode(',', $validated['filter_job_position']);
            }
            if (! empty($validated['filter_hired_status'])) {
                $appliedFilters['hired_status'] = explode(',', $validated['filter_hired_status']);
            }

            return response()->json([
                'success' => true,
                'message' => 'Interviews retrieved successfully',
                'data' => InterviewResource::collection($interviews->items()),
                'pagination' => [
                    'current_page' => $interviews->currentPage(),
                    'per_page' => $interviews->perPage(),
                    'total' => $interviews->total(),
                    'last_page' => $interviews->lastPage(),
                    'from' => $interviews->firstItem(),
                    'to' => $interviews->lastItem(),
                    'has_more_pages' => $interviews->hasMorePages(),
                ],
                'filters' => [
                    'applied_filters' => $appliedFilters,
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve interviews',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/interviews",
     *     summary="Create a new interview",
     *     description="Create a new interview record",
     *     tags={"Interviews"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(ref="#/components/schemas/Interview")
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Interview created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Interview created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Interview")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="candidate_name",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The candidate name field is required.")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="job_position",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The job position field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create interview"),
     *             @OA\Property(property="error", type="string", example="Internal server error occurred")
     *         )
     *     )
     * )
     */
    public function store(InterviewRequest $request)
    {
        try {
            $validated = $request->validated();
            $interview = Interview::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Interview created successfully',
                'data' => new InterviewResource($interview),
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create interview',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/interviews/{id}",
     *     summary="Get interview details",
     *     description="Get details of a specific interview",
     *     tags={"Interviews"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Interview ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Interview retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Interview retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Interview")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Interview not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Interview not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve interview"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        try {
            $interview = Interview::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Interview retrieved successfully',
                'data' => new InterviewResource($interview),
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Interview not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve interview',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/interviews/{id}",
     *     summary="Update an interview",
     *     description="Update an existing interview record",
     *     tags={"Interviews"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Interview ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(ref="#/components/schemas/Interview")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Interview updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Interview updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Interview")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Interview not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Interview not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="candidate_name",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The candidate name field is required.")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="job_position",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The job position field is required.")
     *                 ),
     *
     *                 @OA\Property(
     *                     property="end_time",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The end time must be a time after start time.")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to update interview"),
     *             @OA\Property(property="error", type="string", example="Internal server error occurred")
     *         )
     *     )
     * )
     */
    public function update(InterviewRequest $request, $id)
    {
        try {
            $interview = Interview::findOrFail($id);
            $validated = $request->validated();
            $interview->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Interview updated successfully',
                'data' => new InterviewResource($interview),
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Interview not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update interview',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/interviews/{id}",
     *     summary="Delete an interview",
     *     description="Delete an existing interview record",
     *     tags={"Interviews"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Interview ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Interview deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Interview deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Interview not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Interview not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to delete interview"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        try {
            $interview = Interview::findOrFail($id);
            $interview->delete();

            return response()->json([
                'success' => true,
                'message' => 'Interview deleted successfully',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Interview not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete interview',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/interviews/by-candidate/{candidateName}",
     *     operationId="getInterviewByCandidateName",
     *     summary="Get interview by candidate name",
     *     description="Returns a specific interview by candidate name (case-insensitive match).",
     *     tags={"Interviews"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="candidateName",
     *         in="path",
     *         required=true,
     *         description="Candidate name",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Interview retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Interview retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Interview")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Interview not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Interview not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve interview"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function getByCandidateName($candidateName)
    {
        try {
            $interview = Interview::whereRaw('LOWER(candidate_name) = ?', [strtolower($candidateName)])
                ->first();

            if (! $interview) {
                return response()->json([
                    'success' => false,
                    'message' => 'Interview not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Interview retrieved successfully',
                'data' => new InterviewResource($interview),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve interview',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
