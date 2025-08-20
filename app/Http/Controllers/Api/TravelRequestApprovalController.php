<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TravelRequest;
use App\Models\TravelRequestApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Travel Request Approvals",
 *     description="API Endpoints for Travel Request Approvals management"
 * )
 */
class TravelRequestApprovalController extends Controller
{
    /**
     * Display a listing of the travel request approvals.
     *
     * @OA\Get(
     *     path="/travel-request-approvals",
     *     summary="Get all travel request approvals",
     *     tags={"Travel Request Approvals"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Travel request approvals retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Travel request approvals retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TravelRequestApproval"))
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
     *             @OA\Property(property="message", type="string", example="Error retrieving travel request approvals"),
     *             @OA\Property(property="error", type="string", example="Server error message")
     *         )
     *     )
     * )
     */
    public function index()
    {
        try {
            $approvals = TravelRequestApproval::with('travelRequest')->get();

            return response()->json([
                'success' => true,
                'message' => 'Travel request approvals retrieved successfully',
                'data' => $approvals,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving travel request approvals',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new travel request approval.
     *
     * @OA\Post(
     *     path="/travel-request-approvals",
     *     summary="Create a new travel request approval",
     *     tags={"Travel Request Approvals"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="travel_request_id", type="integer", example=1),
     *             @OA\Property(property="approver_role", type="string", example="hr-manager"),
     *             @OA\Property(property="approver_name", type="string", example="John Doe"),
     *             @OA\Property(property="approver_signature", type="string", example="John Doe Signature"),
     *             @OA\Property(property="approval_date", type="string", format="date", example="2025-01-15"),
     *             @OA\Property(property="status", type="string", example="approved"),
     *             @OA\Property(property="created_by", type="string", example="admin"),
     *             @OA\Property(property="updated_by", type="string", example="admin")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Travel request approval created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Travel request approval created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/TravelRequestApproval")
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
     *             @OA\Property(property="message", type="string", example="Error creating travel request approval"),
     *             @OA\Property(property="error", type="string", example="Server error message")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'travel_request_id' => 'required|exists:travel_requests,id',
            'approver_role' => 'nullable|string|max:100',
            'approver_name' => 'nullable|string|max:200',
            'approver_signature' => 'nullable|string|max:200',
            'approval_date' => 'nullable|date',
            'status' => 'required|string|max:50', // approved/declined/pending
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

        try {
            $approval = TravelRequestApproval::create($validator->validated());

            // Evaluate overall approval status for the travel request.
            $travelRequest = $approval->travelRequest;
            $this->evaluateTravelRequestApproval($travelRequest);

            return response()->json([
                'success' => true,
                'message' => 'Travel request approval created successfully',
                'data' => $approval,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating travel request approval',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Display the specified travel request approval.
     *
     * @OA\Get(
     *     path="/travel-request-approvals/{id}",
     *     summary="Get a specific travel request approval",
     *     tags={"Travel Request Approvals"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Travel request approval ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Travel request approval retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Travel request approval retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/TravelRequestApproval")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Travel request approval not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Travel request approval not found"),
     *             @OA\Property(property="error", type="string", example="Error message")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        try {
            $approval = TravelRequestApproval::with('travelRequest')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Travel request approval retrieved successfully',
                'data' => $approval,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Travel request approval not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Update the specified travel request approval.
     *
     * @OA\Put(
     *     path="/travel-request-approvals/{id}",
     *     summary="Update a travel request approval",
     *     tags={"Travel Request Approvals"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Travel request approval ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="travel_request_id", type="integer", example=1),
     *             @OA\Property(property="approver_role", type="string", example="Manager"),
     *             @OA\Property(property="approver_name", type="string", example="John Doe"),
     *             @OA\Property(property="approver_signature", type="string", example="John Doe Signature"),
     *             @OA\Property(property="approval_date", type="string", format="date", example="2023-01-15"),
     *             @OA\Property(property="status", type="string", example="approved"),
     *             @OA\Property(property="created_by", type="string", example="admin"),
     *             @OA\Property(property="updated_by", type="string", example="admin")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Travel request approval updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Travel request approval updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/TravelRequestApproval")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Travel request approval not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error updating travel request approval"),
     *             @OA\Property(property="error", type="string", example="Error message")
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
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'travel_request_id' => 'sometimes|required|exists:travel_requests,id',
            'approver_role' => 'sometimes|nullable|string|max:100',
            'approver_name' => 'sometimes|nullable|string|max:200',
            'approver_signature' => 'sometimes|nullable|string|max:200',
            'approval_date' => 'sometimes|nullable|date',
            'status' => 'sometimes|required|string|max:50',
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

        try {
            $approval = TravelRequestApproval::findOrFail($id);
            $approval->update($validator->validated());

            // Re-evaluate overall travel request approval.
            $travelRequest = $approval->travelRequest;
            $this->evaluateTravelRequestApproval($travelRequest);

            return response()->json([
                'success' => true,
                'message' => 'Travel request approval updated successfully',
                'data' => $approval,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating travel request approval',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified travel request approval.
     *
     * @OA\Delete(
     *     path="/travel-request-approvals/{id}",
     *     summary="Delete a travel request approval",
     *     tags={"Travel Request Approvals"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Travel request approval ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Travel request approval deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Travel request approval deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Travel request approval not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error deleting travel request approval"),
     *             @OA\Property(property="error", type="string", example="Error message")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        try {
            $approval = TravelRequestApproval::findOrFail($id);
            $approval->delete();

            return response()->json([
                'success' => true,
                'message' => 'Travel request approval deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting travel request approval',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Evaluate overall approval status for a travel request.
     * Required approver roles might be, for example, "Manager" and "HR".
     *
     * If any required approval is "declined", update travel request status to "declined".
     * If all required approvals are "approved", update status to "approved".
     * Otherwise, set status to "pending".
     */
    private function evaluateTravelRequestApproval(TravelRequest $travelRequest)
    {
        // Define required approver roles (adjust to your business logic)
        $requiredRoles = ['hr-manager', 'hr-assistant'];

        // Collect current approval statuses for these roles.
        $approvals = $travelRequest->approvals;
        $statuses = [];

        foreach ($approvals as $approval) {
            if (in_array($approval->approver_role, $requiredRoles)) {
                $statuses[$approval->approver_role] = $approval->status;
            }
        }

        // If any required role is declined, mark travel request as declined.
        foreach ($requiredRoles as $role) {
            if (isset($statuses[$role]) && $statuses[$role] === 'declined') {
                if ($travelRequest->status !== 'declined') {
                    $travelRequest->status = 'declined';
                    $travelRequest->save();
                }

                return;
            }
        }

        // Check if all required roles are approved.
        $allApproved = true;
        foreach ($requiredRoles as $role) {
            if (! isset($statuses[$role]) || $statuses[$role] !== 'approved') {
                $allApproved = false;
                break;
            }
        }

        if ($allApproved) {
            if ($travelRequest->status !== 'approved') {
                $travelRequest->status = 'approved';
                $travelRequest->save();
                // Optionally: deduct any travel allowance or update related balances here.
            }
        } else {
            if ($travelRequest->status !== 'pending') {
                $travelRequest->status = 'pending';
                $travelRequest->save();
            }
        }
    }
}
