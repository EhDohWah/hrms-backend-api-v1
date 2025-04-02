<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Interview;

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
     *     summary="Get all interviews",
     *     description="Get a list of all interviews",
     *     tags={"Interviews"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Interviews retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Interviews retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="candidate_name", type="string"),
     *                     @OA\Property(property="phone", type="string", nullable=true),
     *                     @OA\Property(property="resume", type="string", nullable=true),
     *                     @OA\Property(property="job_position", type="string"),
     *                     @OA\Property(property="interviewer_name", type="string", nullable=true),
     *                     @OA\Property(property="interview_date", type="string", format="date", nullable=true),
     *                     @OA\Property(property="start_time", type="string", format="time", nullable=true),
     *                     @OA\Property(property="end_time", type="string", format="time", nullable=true),
     *                     @OA\Property(property="interview_mode", type="string", nullable=true),
     *                     @OA\Property(property="interview_status", type="string", nullable=true),
     *                     @OA\Property(property="score", type="number", nullable=true),
     *                     @OA\Property(property="feedback", type="string", nullable=true),
     *                     @OA\Property(property="created_by", type="string", nullable=true),
     *                     @OA\Property(property="updated_by", type="string", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
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
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve interviews"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function index()
    {
        try {
            $interviews = Interview::all();

            return response()->json([
                'success' => true,
                'message' => 'Interviews retrieved successfully',
                'data' => $interviews
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve interviews',
                'error' => $e->getMessage()
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
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"candidate_name", "job_position"},
     *             @OA\Property(property="candidate_name", type="string", maxLength=255, example="John Smith"),
     *             @OA\Property(property="phone", type="string", maxLength=10, nullable=true, example="0812345678"),
     *             @OA\Property(property="resume", type="string", maxLength=255, nullable=true, example="resume_john_smith.pdf"),
     *             @OA\Property(property="job_position", type="string", maxLength=255, example="Senior Software Engineer"),
     *             @OA\Property(property="interviewer_name", type="string", nullable=true, example="Jane Doe"),
     *             @OA\Property(property="interview_date", type="string", format="date", nullable=true, example="2023-05-15"),
     *             @OA\Property(property="start_time", type="string", format="time", nullable=true, example="10:00:00"),
     *             @OA\Property(property="end_time", type="string", format="time", nullable=true, example="11:30:00"),
     *             @OA\Property(property="interview_mode", type="string", nullable=true, example="Video Conference"),
     *             @OA\Property(property="interview_status", type="string", nullable=true, example="Scheduled"),
     *             @OA\Property(property="score", type="number", nullable=true, example=85.5),
     *             @OA\Property(property="feedback", type="string", nullable=true, example="Candidate demonstrated strong technical skills and good communication abilities."),
     *             @OA\Property(property="created_by", type="string", nullable=true, example="admin@example.com"),
     *             @OA\Property(property="updated_by", type="string", nullable=true, example="recruiter@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Interview created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Interview created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="candidate_name", type="string", example="John Smith"),
     *                 @OA\Property(property="phone", type="string", example="0812345678"),
     *                 @OA\Property(property="resume", type="string", example="resume_john_smith.pdf"),
     *                 @OA\Property(property="job_position", type="string", example="Senior Software Engineer"),
     *                 @OA\Property(property="interviewer_name", type="string", example="Jane Doe"),
     *                 @OA\Property(property="interview_date", type="string", format="date", example="2023-05-15"),
     *                 @OA\Property(property="start_time", type="string", format="time", example="10:00:00"),
     *                 @OA\Property(property="end_time", type="string", format="time", example="11:30:00"),
     *                 @OA\Property(property="interview_mode", type="string", example="Video Conference"),
     *                 @OA\Property(property="interview_status", type="string", example="Scheduled"),
     *                 @OA\Property(property="score", type="number", example=85.5),
     *                 @OA\Property(property="feedback", type="string", example="Candidate demonstrated strong technical skills and good communication abilities."),
     *                 @OA\Property(property="created_by", type="string", example="admin@example.com"),
     *                 @OA\Property(property="updated_by", type="string", example="recruiter@example.com"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2023-05-10T14:30:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2023-05-10T14:30:00.000000Z")
     *             )
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
     *                 @OA\Property(
     *                     property="candidate_name",
     *                     type="array",
     *                     @OA\Items(type="string", example="The candidate name field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="job_position",
     *                     type="array",
     *                     @OA\Items(type="string", example="The job position field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create interview"),
     *             @OA\Property(property="error", type="string", example="Internal server error occurred")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'candidate_name' => 'required|string|max:255',
                'phone' => 'nullable|string|max:10',
                'resume' => 'nullable|string|max:255',
                'job_position' => 'required|string|max:255',
                'interviewer_name' => 'nullable|string|max:255',
                'interview_date' => 'nullable|date',
                'start_time' => 'nullable|date_format:H:i:s',
                'end_time' => 'nullable|date_format:H:i:s|after:start_time',
                'interview_mode' => 'nullable|string',
                'interview_status' => 'nullable|string',
                'score' => 'nullable|numeric|between:0,100',
                'feedback' => 'nullable|string',
                'created_by' => 'nullable|string',
                'updated_by' => 'nullable|string'
            ]);

            $interview = Interview::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Interview created successfully',
                'data' => $interview
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create interview',
                'error' => $e->getMessage()
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
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Interview ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Interview retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Interview retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Interview not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Interview not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
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
                'data' => $interview
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Interview not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve interview',
                'error' => $e->getMessage()
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
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Interview ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="candidate_name", type="string", maxLength=255, example="Jane Doe"),
     *             @OA\Property(property="phone", type="string", maxLength=10, nullable=true, example="0987654321"),
     *             @OA\Property(property="resume", type="string", maxLength=255, nullable=true, example="jane_doe_resume.pdf"),
     *             @OA\Property(property="job_position", type="string", maxLength=255, example="Frontend Developer"),
     *             @OA\Property(property="interviewer_name", type="string", nullable=true, example="John Smith"),
     *             @OA\Property(property="interview_date", type="string", format="date", nullable=true, example="2023-06-15"),
     *             @OA\Property(property="start_time", type="string", format="time", nullable=true, example="14:00:00"),
     *             @OA\Property(property="end_time", type="string", format="time", nullable=true, example="15:30:00"),
     *             @OA\Property(property="interview_mode", type="string", nullable=true, example="In-person"),
     *             @OA\Property(property="interview_status", type="string", nullable=true, example="Completed"),
     *             @OA\Property(property="score", type="number", nullable=true, example=92.5),
     *             @OA\Property(property="feedback", type="string", nullable=true, example="Excellent technical skills and cultural fit. Strong problem-solving abilities demonstrated during the coding exercise."),
     *             @OA\Property(property="updated_by", type="string", nullable=true, example="hr_manager@company.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Interview updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Interview updated successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=5),
     *                 @OA\Property(property="candidate_name", type="string", example="Jane Doe"),
     *                 @OA\Property(property="phone", type="string", example="0987654321"),
     *                 @OA\Property(property="resume", type="string", example="jane_doe_resume.pdf"),
     *                 @OA\Property(property="job_position", type="string", example="Frontend Developer"),
     *                 @OA\Property(property="interviewer_name", type="string", example="John Smith"),
     *                 @OA\Property(property="interview_date", type="string", format="date", example="2023-06-15"),
     *                 @OA\Property(property="start_time", type="string", format="time", example="14:00:00"),
     *                 @OA\Property(property="end_time", type="string", format="time", example="15:30:00"),
     *                 @OA\Property(property="interview_mode", type="string", example="In-person"),
     *                 @OA\Property(property="interview_status", type="string", example="Completed"),
     *                 @OA\Property(property="score", type="number", example=92.5),
     *                 @OA\Property(property="feedback", type="string", example="Excellent technical skills and cultural fit. Strong problem-solving abilities demonstrated during the coding exercise."),
     *                 @OA\Property(property="created_by", type="string", example="recruiter@company.com"),
     *                 @OA\Property(property="updated_by", type="string", example="hr_manager@company.com"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2023-06-10T09:15:27.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2023-06-15T16:30:45.000000Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Interview not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Interview not found")
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
     *                 @OA\Property(
     *                     property="candidate_name",
     *                     type="array",
     *                     @OA\Items(type="string", example="The candidate name field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="job_position",
     *                     type="array",
     *                     @OA\Items(type="string", example="The job position field is required.")
     *                 ),
     *                 @OA\Property(
     *                     property="end_time",
     *                     type="array",
     *                     @OA\Items(type="string", example="The end time must be a time after start time.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to update interview"),
     *             @OA\Property(property="error", type="string", example="Internal server error occurred")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $interview = Interview::findOrFail($id);

            $validated = $request->validate([
                'candidate_name' => 'nullable|string|max:255',
                'phone' => 'nullable|string|max:10',
                'resume' => 'nullable|string|max:255',
                'job_position' => 'nullable|string|max:255',
                'interviewer_name' => 'nullable|string|max:255',
                'interview_date' => 'nullable|date',
                'start_time' => 'nullable|date_format:H:i:s',
                'end_time' => 'nullable|date_format:H:i:s|after:start_time',
                'interview_mode' => 'nullable|string',
                'interview_status' => 'nullable|string',
                'score' => 'nullable|numeric|between:0,100',
                'feedback' => 'nullable|string',
                'updated_by' => 'nullable|string'
            ]);

            $interview->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Interview updated successfully',
                'data' => $interview
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Interview not found'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update interview',
                'error' => $e->getMessage()
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
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Interview ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Interview deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Interview deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Interview not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Interview not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
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
                'message' => 'Interview deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Interview not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete interview',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
