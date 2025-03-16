<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Interview;
use App\Models\Candidate;

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
     *                     @OA\Property(property="candidate_id", type="integer", nullable=true),
     *                     @OA\Property(property="interviewer_name", type="string", nullable=true),
     *                     @OA\Property(property="interview_date", type="string", format="date"),
     *                     @OA\Property(property="start_time", type="string", format="time"),
     *                     @OA\Property(property="end_time", type="string", format="time"),
     *                     @OA\Property(property="interview_mode", type="string", enum={"in-person", "virtual"}),
     *                     @OA\Property(property="interview_status", type="string", enum={"scheduled", "completed", "cancelled"}),
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
            $interviews = Interview::with('candidate')->get();

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
     *             required={"interview_status"},
     *             @OA\Property(property="candidate_id", type="integer", nullable=true),
     *             @OA\Property(property="interviewer_name", type="string", nullable=true),
     *             @OA\Property(property="interview_date", type="string", format="date"),
     *             @OA\Property(property="start_time", type="string", format="time"),
     *             @OA\Property(property="end_time", type="string", format="time"),
     *             @OA\Property(property="interview_mode", type="string", enum={"in-person", "virtual"}),
     *             @OA\Property(property="interview_status", type="string", enum={"scheduled", "completed", "cancelled"}),
     *             @OA\Property(property="score", type="number", nullable=true),
     *             @OA\Property(property="feedback", type="string", nullable=true),
     *             @OA\Property(property="created_by", type="string", nullable=true),
     *             @OA\Property(property="updated_by", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Interview created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Interview created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create interview"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'candidate_id' => 'nullable|exists:candidates,id',
                'interviewer_name' => 'nullable|string|max:255',
                'interview_date' => 'nullable|date',
                'start_time' => 'nullable|date_format:H:i:s',
                'end_time' => 'nullable|date_format:H:i:s|after:start_time',
                'interview_mode' => 'nullable|in:in-person,virtual',
                'interview_status' => 'required|in:scheduled,completed,cancelled',
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
            $interview = Interview::with('candidate')->findOrFail($id);

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
     *             @OA\Property(property="candidate_id", type="integer", nullable=true),
     *             @OA\Property(property="interviewer_name", type="string", nullable=true),
     *             @OA\Property(property="interview_date", type="string", format="date"),
     *             @OA\Property(property="start_time", type="string", format="time"),
     *             @OA\Property(property="end_time", type="string", format="time"),
     *             @OA\Property(property="interview_mode", type="string", enum={"in-person", "virtual"}),
     *             @OA\Property(property="interview_status", type="string", enum={"scheduled", "completed", "cancelled"}),
     *             @OA\Property(property="score", type="number", nullable=true),
     *             @OA\Property(property="feedback", type="string", nullable=true),
     *             @OA\Property(property="updated_by", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Interview updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Interview updated successfully"),
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
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to update interview"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $interview = Interview::findOrFail($id);

            $validated = $request->validate([
                'candidate_id' => 'nullable|exists:candidates,id',
                'interviewer_name' => 'nullable|string|max:255',
                'interview_date' => 'nullable|date',
                'start_time' => 'nullable|date_format:H:i:s',
                'end_time' => 'nullable|date_format:H:i:s|after:start_time',
                'interview_mode' => 'nullable|in:in-person,virtual',
                'interview_status' => 'nullable|in:scheduled,completed,cancelled',
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
