<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LeaveType;
use App\Models\LeaveRequest;
use App\Models\LeaveBalance;
use App\Models\LeaveRequestApproval;
use App\Models\TraditionalLeave;
use App\Models\Employee;

/**
 * @OA\Tag(
 *     name="Leave Management",
 *     description="API Endpoints for managing leaves"
 * )
 */
class LeaveManagementController extends Controller
{
    // -------------------- Leave Types --------------------
    /**
     * @OA\Get(
     *     path="/leaves/types",
     *     summary="Get all leave types",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of leave types",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/LeaveType"))
     *     )
     * )
     */
    public function getLeaveTypes() {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Leave types retrieved successfully',
                'data' => LeaveType::all()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving leave types',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/leaves/types/{id}",
     *     summary="Get a leave type by ID",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Leave type details",
     *         @OA\JsonContent(ref="#/components/schemas/LeaveType")
     *     ),
     *     @OA\Response(response=404, description="Leave type not found")
     * )
     */
    public function getLeaveType($id) {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Leave type retrieved successfully',
                'data' => LeaveType::findOrFail($id)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving leave type',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/leaves/types",
     *     summary="Create a new leave type",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", example="Annual Leave"),
     *             @OA\Property(property="default_duration", type="number", format="float", example=5),
     *             @OA\Property(property="description", type="string", example="Leave for annual vacation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Leave type created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/LeaveType")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function createLeaveType(Request $request) {
        try {
            $data = $request->validate([
                'name' => 'required|string|max:100',
                'default_duration' => 'nullable|numeric',
                'description' => 'nullable|string',
            ]);

            $leaveType = LeaveType::create($data);
            return response()->json([
                'success' => true,
                'message' => 'Leave type created successfully',
                'data' => $leaveType
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating leave type',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/leaves/types/{id}",
     *     summary="Update a leave type",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Annual Leave"),
     *             @OA\Property(property="default_duration", type="number", format="float", example=5),
     *             @OA\Property(property="description", type="string", example="Updated description")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave type updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/LeaveType")
     *     ),
     *     @OA\Response(response=404, description="Leave type not found")
     * )
     */
    public function updateLeaveType(Request $request, $id) {
        try {
            $leaveType = LeaveType::findOrFail($id);
            $leaveType->update($request->all());
            return response()->json([
                'success' => true,
                'message' => 'Leave type updated successfully',
                'data' => $leaveType
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating leave type',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/leaves/types/{id}",
     *     summary="Delete a leave type",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Leave type deleted successfully",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Leave type deleted successfully"))
     *     ),
     *     @OA\Response(response=404, description="Leave type not found")
     * )
     */
    public function deleteLeaveType($id) {
        try {
            LeaveType::findOrFail($id)->delete();
            return response()->json([
                'success' => true,
                'message' => 'Leave type deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting leave type',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    // -------------------- Leave Requests --------------------
    /**
     * @OA\Get(
     *     path="/leaves/requests",
     *     summary="Get all leave requests",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of leave requests",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/LeaveRequest"))
     *     )
     * )
     */
    public function getLeaveRequests() {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Leave requests retrieved successfully',
                'data' => LeaveRequest::with(['employee', 'leaveType', 'approvals'])->get()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving leave requests',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/leaves/requests/{id}",
     *     summary="Get a leave request by ID",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Leave request details",
     *         @OA\JsonContent(ref="#/components/schemas/LeaveRequest")
     *     ),
     *     @OA\Response(response=404, description="Leave request not found")
     * )
     */
    public function getLeaveRequest($id) {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Leave request retrieved successfully',
                'data' => LeaveRequest::with(['employee', 'leaveType', 'approvals'])->findOrFail($id)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving leave request',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/leaves/requests",
     *     summary="Create a new leave request",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "leave_type_id", "start_date", "end_date"},
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="leave_type_id", type="integer", example=1),
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-01-01"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-01-10"),
     *             @OA\Property(property="total_days", type="number", example=10),
     *             @OA\Property(property="reason", type="string", example="Family vacation"),
     *             @OA\Property(property="status", type="string", example="pending")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Leave request created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/LeaveRequest")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function createLeaveRequest(Request $request) {
        try {
            $data = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'leave_type_id' => 'required|exists:leave_types,id',
                'start_date' => 'required|date|after_or_equal:' . now()->format('Y-m-d H:i:s'),
                'end_date' => 'required|date|after_or_equal:start_date',
                'total_days' => 'nullable|numeric',
                'reason' => 'nullable|string',
                'status' => 'nullable|string|max:50',
            ]);

            $leaveRequest = LeaveRequest::create($data);
            return response()->json([
                'success' => true,
                'message' => 'Leave request created successfully',
                'data' => $leaveRequest
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating leave request',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/leaves/requests/{id}",
     *     summary="Update a leave request",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="leave_type_id", type="integer", example=1),
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-01-01"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-01-10"),
     *             @OA\Property(property="total_days", type="number", example=10),
     *             @OA\Property(property="reason", type="string", example="Updated reason"),
     *             @OA\Property(property="status", type="string", example="approved")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave request updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/LeaveRequest")
     *     ),
     *     @OA\Response(response=404, description="Leave request not found")
     * )
     */
    public function updateLeaveRequest(Request $request, $id) {
        try {
            $leaveRequest = LeaveRequest::findOrFail($id);

            // Check old status vs. new status
            $oldStatus = $leaveRequest->status;
            $newStatus = $request->get('status'); // or $request->status

            // Update the request
            $leaveRequest->update($request->all());

            // If the request is now approved but was previously pending or something else, deduct from balance
            if ($newStatus === 'approved' && $oldStatus !== 'approved') {
                $this->deductLeaveBalance($leaveRequest);
            }

            // If the request was approved before but is now declined/canceled, restore the days
            if ($oldStatus === 'approved' && ($newStatus === 'declined' || $newStatus === 'canceled')) {
                $this->restoreLeaveBalance($leaveRequest);
            }

            return response()->json([
                'success' => true,
                'message' => 'Leave request updated successfully',
                'data' => $leaveRequest
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating leave request',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/leaves/requests/{id}",
     *     summary="Delete a leave request",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Leave request deleted successfully",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Leave request deleted successfully"))
     *     ),
     *     @OA\Response(response=404, description="Leave request not found")
     * )
     */
    public function deleteLeaveRequest($id) {
        try {
            LeaveRequest::findOrFail($id)->delete();
            return response()->json([
                'success' => true,
                'message' => 'Leave request deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting leave request',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    // -------------------- Leave Balances --------------------
    /**
     * @OA\Get(
     *     path="/leaves/balances",
     *     summary="Get all leave balances",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of leave balances",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/LeaveBalance"))
     *     )
     * )
     */
    public function getLeaveBalances() {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Leave balances retrieved successfully',
                'data' => LeaveBalance::with(['employee', 'leaveType'])->get()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving leave balances',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/leaves/balances/{id}",
     *     summary="Get a leave balance by ID",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Leave balance details",
     *         @OA\JsonContent(ref="#/components/schemas/LeaveBalance")
     *     ),
     *     @OA\Response(response=404, description="Leave balance not found")
     * )
     */
    public function getLeaveBalance($id) {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Leave balance retrieved successfully',
                'data' => LeaveBalance::with(['employee', 'leaveType'])->findOrFail($id)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving leave balance',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/leaves/balances",
     *     summary="Create a new leave balance",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "leave_type_id", "remaining_days"},
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="leave_type_id", type="integer", example=1),
     *             @OA\Property(property="remaining_days", type="number", example=10)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Leave balance created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/LeaveBalance")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function createLeaveBalance(Request $request) {
        try {
            $data = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'leave_type_id' => 'required|exists:leave_types,id',
                'remaining_days' => 'required|numeric',
                'year' => 'required|integer|min:' . date('Y')
            ]);

            $balance = LeaveBalance::create($data);
            return response()->json([
                'success' => true,
                'message' => 'Leave balance created successfully',
                'data' => $balance
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating leave balance',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/leaves/balances/{id}",
     *     summary="Update a leave balance",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="leave_type_id", type="integer", example=1),
     *             @OA\Property(property="remaining_days", type="number", example=10)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave balance updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/LeaveBalance")
     *     ),
     *     @OA\Response(response=404, description="Leave balance not found")
     * )
     */
    public function updateLeaveBalance(Request $request, $id) {
        try {
            $balance = LeaveBalance::findOrFail($id);
            $balance->update($request->all());
            return response()->json([
                'success' => true,
                'message' => 'Leave balance updated successfully',
                'data' => $balance
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating leave balance',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/leaves/balances/{id}",
     *     summary="Delete a leave balance",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Leave balance deleted successfully",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Leave balance deleted successfully"))
     *     ),
     *     @OA\Response(response=404, description="Leave balance not found")
     * )
     */
    public function deleteLeaveBalance($id) {
        try {
            LeaveBalance::findOrFail($id)->delete();
            return response()->json([
                'success' => true,
                'message' => 'Leave balance deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting leave balance',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    // -------------------- Leave Request Approvals --------------------
    /**
     * @OA\Get(
     *     path="/leaves/approvals",
     *     summary="Get all leave request approvals",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of leave request approvals",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/LeaveRequestApproval"))
     *     )
     * )
     */
    public function getApprovals() {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Leave request approvals retrieved successfully',
                'data' => LeaveRequestApproval::with('leaveRequest')->get()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving leave request approvals',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/leaves/approvals/{id}",
     *     summary="Get a leave request approval by ID",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Leave request approval details",
     *         @OA\JsonContent(ref="#/components/schemas/LeaveRequestApproval")
     *     ),
     *     @OA\Response(response=404, description="Leave request approval not found")
     * )
     */
    public function getApproval($id) {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Leave request approval retrieved successfully',
                'data' => LeaveRequestApproval::with('leaveRequest')->findOrFail($id)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving leave request approval',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/leaves/approvals",
     *     summary="Create a new leave request approval",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"leave_request_id", "status"},
     *             @OA\Property(property="leave_request_id", type="integer", example=1),
     *             @OA\Property(property="approver_role", type="string", example="Manager"),
     *             @OA\Property(property="approver_name", type="string", example="Jane Doe"),
     *             @OA\Property(property="approver_signature", type="string", example="signature.png"),
     *             @OA\Property(property="approval_date", type="string", format="date", example="2025-01-01"),
     *             @OA\Property(property="status", type="string", enum={"approved", "declined", "pending"}, example="approved")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Leave request approval created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Leave request approval created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/LeaveRequestApproval")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Error creating leave request approval"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function createApproval(Request $request)
    {
        try {
            $data = $request->validate([
                'leave_request_id'   => 'required|exists:leave_requests,id',
                'approver_role'      => 'nullable|string|max:100',
                'approver_name'      => 'nullable|string|max:200',
                'approver_signature' => 'nullable|string|max:200',
                'approval_date'      => 'nullable|date',
                'status'             => 'required|string|max:50',
            ]);

            $approval = LeaveRequestApproval::create($data);

            // Evaluate the overall leave request status after creating an approval.
            $leaveRequest = $approval->leaveRequest;
            $this->evaluateLeaveRequestApproval($leaveRequest);

            return response()->json([
                'success' => true,
                'message' => 'Leave request approval created successfully',
                'data'    => $approval
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating leave request approval',
                'data'    => ['error' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/leaves/approvals/{id}",
     *     summary="Update a leave request approval",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="leave_request_id", type="integer", example=1),
     *             @OA\Property(property="approver_role", type="string", example="Manager"),
     *             @OA\Property(property="approver_name", type="string", example="Jane Doe"),
     *             @OA\Property(property="approver_signature", type="string", example="signature.png"),
     *             @OA\Property(property="approval_date", type="string", format="date", example="2023-01-01"),
     *             @OA\Property(property="status", type="string", example="approved")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Leave request approval updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/LeaveRequestApproval")
     *     ),
     *     @OA\Response(response=404, description="Leave request approval not found")
     * )
     */
    public function updateApproval(Request $request, $id)
    {
        try {
            $approval = LeaveRequestApproval::findOrFail($id);
            $approval->update($request->all());

            // Re-evaluate and update overall request status and leave balance.
            $leaveRequest = $approval->leaveRequest;
            $this->evaluateLeaveRequestApproval($leaveRequest);

            return response()->json([
                'success' => true,
                'message' => 'Leave request approval updated successfully',
                'data'    => $approval
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating leave request approval',
                'data'    => ['error' => $e->getMessage()],
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/leaves/approvals/{id}",
     *     summary="Delete a leave request approval",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Leave request approval deleted successfully",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Approval deleted successfully"))
     *     ),
     *     @OA\Response(response=404, description="Leave request approval not found")
     * )
     */
    public function deleteApproval($id) {
        try {
            LeaveRequestApproval::findOrFail($id)->delete();
            return response()->json([
                'success' => true,
                'message' => 'Approval deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting leave request approval',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    // -------------------- Traditional Leaves --------------------
    /**
     * @OA\Get(
     *     path="/leaves/traditional",
     *     summary="Get all traditional leaves",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of traditional leaves",
     *         @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/TraditionalLeave"))
     *     )
     * )
     */
    public function getTraditionalLeaves() {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Traditional leaves retrieved successfully',
                'data' => TraditionalLeave::all()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving traditional leaves',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/leaves/traditional/{id}",
     *     summary="Get a traditional leave by ID",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Traditional leave details",
     *         @OA\JsonContent(ref="#/components/schemas/TraditionalLeave")
     *     ),
     *     @OA\Response(response=404, description="Traditional leave not found")
     * )
     */
    public function getTraditionalLeave($id) {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Traditional leave retrieved successfully',
                'data' => TraditionalLeave::findOrFail($id)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving traditional leave',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/leaves/traditional",
     *     summary="Create a new traditional leave",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "date"},
     *             @OA\Property(property="name", type="string", example="Cultural Leave"),
     *             @OA\Property(property="description", type="string", example="Leave for cultural events"),
     *             @OA\Property(property="date", type="string", format="date", example="2023-01-01")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Traditional leave created successfully",
     *         @OA\JsonContent(ref="#/components/schemas/TraditionalLeave")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function createTraditionalLeave(Request $request) {
        try {
            $data = $request->validate([
                'name' => 'required|string|max:100',
                'description' => 'nullable|string',
                'date' => 'required|date',
            ]);

            $leave = TraditionalLeave::create($data);
            return response()->json([
                'success' => true,
                'message' => 'Traditional leave created successfully',
                'data' => $leave
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating traditional leave',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/leaves/traditional/{id}",
     *     summary="Update a traditional leave",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Cultural Leave"),
     *             @OA\Property(property="description", type="string", example="Updated description"),
     *             @OA\Property(property="date", type="string", format="date", example="2023-01-01")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Traditional leave updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/TraditionalLeave")
     *     ),
     *     @OA\Response(response=404, description="Traditional leave not found")
     * )
     */
    public function updateTraditionalLeave(Request $request, $id) {
        try {
            $leave = TraditionalLeave::findOrFail($id);
            $leave->update($request->all());
            return response()->json([
                'success' => true,
                'message' => 'Traditional leave updated successfully',
                'data' => $leave
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating traditional leave',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/leaves/traditional/{id}",
     *     summary="Delete a traditional leave",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Traditional leave deleted successfully",
     *         @OA\JsonContent(@OA\Property(property="message", type="string", example="Traditional leave deleted successfully"))
     *     ),
     *     @OA\Response(response=404, description="Traditional leave not found")
     * )
     */
    public function deleteTraditionalLeave($id) {
        try {
            TraditionalLeave::findOrFail($id)->delete();
            return response()->json([
                'success' => true,
                'message' => 'Traditional leave deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting traditional leave',
                'data' => [
                    'error' => $e->getMessage(),
                ]
            ], 500);
        }
    }


    private function deductLeaveBalance(LeaveRequest $leaveRequest)
    {
        // Determine the year of the leave request.
        // For example, if your policy is to deduct from the year in which the leave starts:
        $year = date('Y', strtotime($leaveRequest->start_date));

        // Find the relevant balance
        $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                            ->where('leave_type_id', $leaveRequest->leave_type_id)
                            ->where('year', $year)
                            ->first();

        if ($balance) {
            $balance->remaining_days -= $leaveRequest->total_days;
            $balance->save();
        }
        // else handle the case if there's no balance found
    }

    private function restoreLeaveBalance(LeaveRequest $leaveRequest)
    {
        $year = date('Y', strtotime($leaveRequest->start_date));

        $balance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
                            ->where('leave_type_id', $leaveRequest->leave_type_id)
                            ->where('year', $year)
                            ->first();

        if ($balance) {
            $balance->remaining_days += $leaveRequest->total_days;
            $balance->save();
        }
    }

    /**
     * Evaluate all approvals for a leave request.
     * - If any required role (Manager, HR) is "declined", mark the request as "declined" and restore balance.
     * - If approvals for all required roles are "approved", update the request to "approved" and deduct balance.
     * - Otherwise, leave the request status as "pending".
     *
     * @param LeaveRequest $leaveRequest
     */
    private function evaluateLeaveRequestApproval(LeaveRequest $leaveRequest)
    {
        // Define required approver roles.
        $requiredApproverRoles = ['hr-manager', 'hr-assistant'];

        // Gather the current approvals.
        $approvals = $leaveRequest->approvals; // Assumes a relationship: LeaveRequest->approvals()
        $approvalStatuses = [];

        foreach ($approvals as $approval) {
            if (in_array($approval->approver_role, $requiredApproverRoles)) {
                // Save the latest status for each role.
                $approvalStatuses[$approval->approver_role] = $approval->status;
            }
        }

        // If any required approval is "declined", update request status to "declined" and restore balance.
        foreach ($requiredApproverRoles as $role) {
            if (isset($approvalStatuses[$role]) && $approvalStatuses[$role] === 'declined') {
                // Only update if not already declined.
                if ($leaveRequest->status !== 'declined') {
                    $leaveRequest->status = 'declined';
                    $leaveRequest->save();
                    $this->restoreLeaveBalance($leaveRequest);
                }
                return;
            }
        }

        // Check if approvals for all required roles exist and are "approved".
        $allApproved = true;
        foreach ($requiredApproverRoles as $role) {
            if (!isset($approvalStatuses[$role]) || $approvalStatuses[$role] !== 'approved') {
                $allApproved = false;
                break;
            }
        }

        if ($allApproved) {
            // If not already marked as approved, update status and deduct balance.
            if ($leaveRequest->status !== 'approved') {
                $leaveRequest->status = 'approved';
                $leaveRequest->save();
                $this->deductLeaveBalance($leaveRequest);
            }
        } else {
            // Otherwise, keep it pending.
            if ($leaveRequest->status !== 'pending') {
                $leaveRequest->status = 'pending';
                $leaveRequest->save();
            }
        }
    }


}
