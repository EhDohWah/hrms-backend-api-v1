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
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Interviews retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="job_position", type="string"),
     *                     @OA\Property(property="interview_date", type="string", format="date"),
     *                     @OA\Property(property="start_time", type="string", format="time"),
     *                     @OA\Property(property="end_time", type="string", format="time"),
     *                     @OA\Property(property="interview_mode", type="string", enum={"in-person", "virtual"}),
     *                     @OA\Property(property="interview_status", type="string", enum={"scheduled", "completed", "cancelled"}),
     *                     @OA\Property(property="score", type="number", nullable=true),
     *                     @OA\Property(property="feedback", type="string", nullable=true),
     *                     @OA\Property(property="resume", type="string", nullable=true),
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
                'status' => 'success',
                'message' => 'Interviews retrieved successfully',
                'data' => $interviews
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
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
     *             required={"job_position","interview_date","start_time","end_time","interview_mode","interview_status"},
     *             @OA\Property(property="job_position", type="string", maxLength=255),
     *             @OA\Property(property="interview_date", type="string", format="date"),
     *             @OA\Property(property="start_time", type="string", format="time"),
     *             @OA\Property(property="end_time", type="string", format="time"),
     *             @OA\Property(property="interview_mode", type="string", enum={"in-person", "virtual"}),
     *             @OA\Property(property="interview_status", type="string", enum={"scheduled", "completed", "cancelled"}),
     *             @OA\Property(property="score", type="number", nullable=true),
     *             @OA\Property(property="feedback", type="string", nullable=true),
     *             @OA\Property(property="resume", type="string", nullable=true),
     *             @OA\Property(property="created_by", type="string", nullable=true),
     *             @OA\Property(property="updated_by", type="string", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Interview created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Interview created successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
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
                'job_position' => 'required|string|max:255',
                'interview_date' => 'required|date',
                'start_time' => 'required|date_format:H:i:s',
                'end_time' => 'required|date_format:H:i:s|after:start_time',
                'interview_mode' => 'required|in:in-person,virtual',
                'interview_status' => 'required|in:scheduled,completed,cancelled',
                'score' => 'nullable|numeric|between:0,100',
                'feedback' => 'nullable|string',
                'resume' => 'nullable|string',
                'created_by' => 'nullable|string',
                'updated_by' => 'nullable|string'
            ]);

            $interview = Interview::create($validated);

            return response()->json([
                'status' => 'success',
                'message' => 'Interview created successfully',
                'data' => $interview
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create interview',
                'error' => $e->getMessage()
            ], 500);
        }
    }


}
