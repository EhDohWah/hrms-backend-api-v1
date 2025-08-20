<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TravelRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Travel Requests",
 *     description="API Endpoints for Travel Requests management"
 * )
 */
class TravelRequestController extends Controller
{
    /**
     * Display a listing of the travel requests.
     *
     * @OA\Get(
     *     path="/travel-requests",
     *     summary="Get all travel requests",
     *     tags={"Travel Requests"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Travel requests retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Travel requests retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
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
     *             @OA\Property(property="message", type="string", example="Error retrieving travel requests"),
     *             @OA\Property(property="error", type="string", example="Server error message")
     *         )
     *     )
     * )
     */
    public function index()
    {
        $travelRequests = TravelRequest::with(['employee', 'departmentPosition', 'approvals'])->get();

        return response()->json([
            'success' => true,
            'message' => 'Travel requests retrieved successfully',
            'data' => $travelRequests,
        ], 200);
    }

    /**
     * Store a newly created travel request in storage.
     *
     * @OA\Post(
     *     path="/travel-requests",
     *     summary="Create a new travel request",
     *     tags={"Travel Requests"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"employee_id"},
     *
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="department_position_id", type="integer", example=1),
     *             @OA\Property(property="destination", type="string", example="New York"),
     *             @OA\Property(property="start_date", type="string", format="date", example="2023-01-01"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2023-01-05"),
     *             @OA\Property(property="purpose", type="string", example="Business meeting"),
     *             @OA\Property(property="grant", type="string", example="Project X"),
     *             @OA\Property(property="transportation", type="string", example="Flight"),
     *             @OA\Property(property="accommodation", type="string", example="Hotel"),
     *             @OA\Property(property="request_by_signature", type="string"),
     *             @OA\Property(property="request_by_fullname", type="string"),
     *             @OA\Property(property="request_by_date", type="string", format="date"),
     *             @OA\Property(property="remarks", type="string"),
     *             @OA\Property(property="status", type="string", example="pending"),
     *             @OA\Property(property="created_by", type="string"),
     *             @OA\Property(property="updated_by", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Travel request created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Travel request created successfully"),
     *             @OA\Property(property="data", type="object")
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
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object")
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
     *             @OA\Property(property="message", type="string", example="Error creating travel request"),
     *             @OA\Property(property="error", type="string", example="Server error message")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'department_position_id' => 'nullable|exists:department_positions,id',
            'destination' => 'nullable|string|max:200',
            'start_date' => 'nullable|date|after_or_equal:'.now()->format('Y-m-d H:i:s'),
            'end_date' => 'nullable|date',
            'purpose' => 'nullable|string',
            'grant' => 'nullable|string|max:50',
            'transportation' => 'nullable|string|max:100',
            'accommodation' => 'nullable|string|max:100',
            'request_by_signature' => 'nullable|string|max:200',
            'request_by_fullname' => 'nullable|string|max:200',
            'request_by_date' => 'nullable|date',
            'remarks' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'created_by' => 'nullable|string|max:100',
            'updated_by' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $travelRequest = TravelRequest::create($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Travel request created successfully',
            'data' => $travelRequest,
        ], 201);
    }

    /**
     * Display the specified travel request.
     *
     * @OA\Get(
     *     path="/travel-requests/{id}",
     *     summary="Get a specific travel request",
     *     tags={"Travel Requests"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Travel request ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Travel request retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Travel request retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Travel request not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Travel request not found"),
     *             @OA\Property(property="error", type="string", example="Error message")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        $travelRequest = TravelRequest::with(['employee', 'departmentPosition', 'approvals'])->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => 'Travel request retrieved successfully',
            'data' => $travelRequest,
        ], 200);
    }

    /**
     * Update the specified travel request in storage.
     *
     * @OA\Put(
     *     path="/travel-requests/{id}",
     *     summary="Update a travel request",
     *     tags={"Travel Requests"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Travel request ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="department_position_id", type="integer", example=1),
     *             @OA\Property(property="destination", type="string", example="New York"),
     *             @OA\Property(property="start_date", type="string", format="date", example="2023-01-01"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2023-01-05"),
     *             @OA\Property(property="purpose", type="string", example="Business meeting"),
     *             @OA\Property(property="grant", type="string", example="Project X"),
     *             @OA\Property(property="transportation", type="string", example="Flight"),
     *             @OA\Property(property="accommodation", type="string", example="Hotel"),
     *             @OA\Property(property="request_by_signature", type="string"),
     *             @OA\Property(property="request_by_fullname", type="string"),
     *             @OA\Property(property="request_by_date", type="string", format="date"),
     *             @OA\Property(property="remarks", type="string"),
     *             @OA\Property(property="status", type="string", example="Approved"),
     *             @OA\Property(property="created_by", type="string"),
     *             @OA\Property(property="updated_by", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Travel request updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Travel request updated successfully"),
     *             @OA\Property(property="data", type="object")
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
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object")
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
     *             @OA\Property(property="message", type="string", example="Error updating travel request"),
     *             @OA\Property(property="error", type="string", example="Server error message")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'sometimes|required|exists:employees,id',
            'department_position_id' => 'sometimes|nullable|exists:department_positions,id',
            'destination' => 'sometimes|nullable|string|max:200',
            'start_date' => 'sometimes|nullable|date|after_or_equal:'.now()->format('Y-m-d H:i:s'),
            'end_date' => 'sometimes|nullable|date',
            'purpose' => 'sometimes|nullable|string',
            'grant' => 'sometimes|nullable|string|max:50',
            'transportation' => 'sometimes|nullable|string|max:100',
            'accommodation' => 'sometimes|nullable|string|max:100',
            'request_by_signature' => 'sometimes|nullable|string|max:200',
            'request_by_fullname' => 'sometimes|nullable|string|max:200',
            'request_by_date' => 'sometimes|nullable|date',
            'remarks' => 'sometimes|nullable|string',
            'status' => 'sometimes|nullable|string|max:50',
            'created_by' => 'sometimes|nullable|string|max:100',
            'updated_by' => 'sometimes|nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $travelRequest = TravelRequest::findOrFail($id);
        $travelRequest->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Travel request updated successfully',
            'data' => $travelRequest,
        ], 200);
    }

    /**
     * Remove the specified travel request from storage.
     *
     * @OA\Delete(
     *     path="/travel-requests/{id}",
     *     summary="Delete a travel request",
     *     tags={"Travel Requests"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Travel request ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Travel request deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Travel request deleted successfully")
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
     *             @OA\Property(property="message", type="string", example="Error deleting travel request"),
     *             @OA\Property(property="error", type="string", example="Server error message")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        $travelRequest = TravelRequest::findOrFail($id);
        $travelRequest->delete();

        return response()->json([
            'success' => true,
            'message' => 'Travel request deleted successfully',
        ], 200);
    }
}
