<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveAttachment;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestApproval;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Leave Management",
 *     description="Comprehensive API endpoints for leave management system"
 * )
 */
class LeaveManagementController extends Controller
{
    // ==================== LEAVE REQUESTS ====================

    /**
     * Get paginated leave requests with filtering and sorting
     *
     * @OA\Get(
     *     path="/leave-requests",
     *     summary="Get paginated leave requests with advanced filtering",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", minimum=1), description="Page number"),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", minimum=1, maximum=100), description="Items per page"),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string"), description="Search by staff ID or employee name"),
     *     @OA\Parameter(name="from", in="query", @OA\Schema(type="string", format="date"), description="Start date filter"),
     *     @OA\Parameter(name="to", in="query", @OA\Schema(type="string", format="date"), description="End date filter"),
     *     @OA\Parameter(name="leave_types", in="query", @OA\Schema(type="string"), description="Comma-separated leave type IDs"),
     *     @OA\Parameter(name="status", in="query", @OA\Schema(type="string", enum={"pending", "approved", "declined", "cancelled"}), description="Request status"),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"recently_added", "ascending", "descending", "last_month", "last_7_days"}), description="Sort option"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Leave requests retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/LeaveRequest")),
     *             @OA\Property(property="pagination", type="object"),
     *             @OA\Property(property="stats", type="object")
     *         )
     *     )
     * )
     */
    public function getLeaveRequests(Request $request)
    {
        try {
            // Validate request parameters
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'search' => 'string|nullable|max:255',
                'from' => 'date|nullable',
                'to' => 'date|nullable',
                'leave_types' => 'string|nullable',
                'status' => 'string|nullable|in:pending,approved,declined,cancelled',
                'sort_by' => 'string|nullable|in:recently_added,ascending,descending,last_month,last_7_days',
            ]);

            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;
            $sortBy = $validated['sort_by'] ?? 'recently_added';

            // Build optimized query with eager loading
            $query = LeaveRequest::with([
                'employee:id,staff_id,first_name_en,last_name_en,subsidiary',
                'leaveType:id,name,requires_attachment',
                'approvals:id,leave_request_id,status,approver_name,approval_date',
                'attachments:id,leave_request_id,document_name,document_url,description,added_at',
            ]);

            // Apply search filter
            if (! empty($validated['search'])) {
                $searchTerm = trim($validated['search']);
                $query->whereHas('employee', function ($q) use ($searchTerm) {
                    $q->where('staff_id', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('first_name_en', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('last_name_en', 'LIKE', "%{$searchTerm}%")
                        ->orWhereRaw("CONCAT(first_name_en, ' ', last_name_en) LIKE ?", ["%{$searchTerm}%"]);
                });
            }

            // Apply date range filter
            if (! empty($validated['from'])) {
                $query->where('start_date', '>=', $validated['from']);
            }
            if (! empty($validated['to'])) {
                $query->where('end_date', '<=', $validated['to']);
            }

            // Apply leave types filter
            if (! empty($validated['leave_types'])) {
                $leaveTypeIds = explode(',', $validated['leave_types']);
                $query->whereIn('leave_type_id', $leaveTypeIds);
            }

            // Apply status filter
            if (! empty($validated['status'])) {
                $query->where('status', $validated['status']);
            }

            // Apply sorting
            switch ($sortBy) {
                case 'ascending':
                    $query->orderBy('start_date', 'asc');
                    break;
                case 'descending':
                    $query->orderBy('start_date', 'desc');
                    break;
                case 'last_month':
                    $query->where('created_at', '>=', Carbon::now()->subMonth())
                        ->orderBy('created_at', 'desc');
                    break;
                case 'last_7_days':
                    $query->where('created_at', '>=', Carbon::now()->subDays(7))
                        ->orderBy('created_at', 'desc');
                    break;
                default: // recently_added
                    $query->orderBy('created_at', 'desc');
                    break;
            }

            // Execute pagination
            $leaveRequests = $query->paginate($perPage, ['*'], 'page', $page);

            // Calculate statistics
            $stats = $this->calculateLeaveRequestStats($validated);

            return response()->json([
                'success' => true,
                'message' => 'Leave requests retrieved successfully',
                'data' => $leaveRequests->items(),
                'pagination' => [
                    'current_page' => $leaveRequests->currentPage(),
                    'per_page' => $leaveRequests->perPage(),
                    'total' => $leaveRequests->total(),
                    'last_page' => $leaveRequests->lastPage(),
                    'from' => $leaveRequests->firstItem(),
                    'to' => $leaveRequests->lastItem(),
                    'has_more_pages' => $leaveRequests->hasMorePages(),
                ],
                'stats' => $stats,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error retrieving leave requests: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve leave requests',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single leave request with full relationships
     *
     * @OA\Get(
     *     path="/leave-requests/{id}",
     *     summary="Get a specific leave request",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Leave request retrieved successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/LeaveRequest")
     *     ),
     *
     *     @OA\Response(response=404, description="Leave request not found")
     * )
     */
    public function getLeaveRequest($id)
    {
        try {
            $leaveRequest = LeaveRequest::with([
                'employee:id,staff_id,first_name_en,last_name_en,subsidiary,department_position_id',
                'employee.employment:id,employee_id,department,position',
                'leaveType:id,name,default_duration,description,requires_attachment',
                'approvals:id,leave_request_id,approver_role,approver_name,approver_signature,approval_date,status',
                'attachments:id,leave_request_id,document_name,document_url,description,added_at',
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Leave request retrieved successfully',
                'data' => $leaveRequest,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Leave request not found',
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Create a new leave request with validation and balance checking
     *
     * @OA\Post(
     *     path="/leave-requests",
     *     summary="Create a new leave request",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"employee_id", "leave_type_id", "start_date", "end_date", "total_days"},
     *
     *             @OA\Property(property="employee_id", type="integer"),
     *             @OA\Property(property="leave_type_id", type="integer"),
     *             @OA\Property(property="start_date", type="string", format="date"),
     *             @OA\Property(property="end_date", type="string", format="date"),
     *             @OA\Property(property="total_days", type="number", format="float"),
     *             @OA\Property(property="reason", type="string"),
     *             @OA\Property(
     *                 property="documents",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="document_name", type="string"),
     *                     @OA\Property(property="document_url", type="string", format="url"),
     *                     @OA\Property(property="description", type="string", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Leave request created successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/LeaveRequest")
     *     ),
     *
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=400, description="Insufficient leave balance")
     * )
     */
    public function createLeaveRequest(Request $request)
    {
        try {
            // Validate request data
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'leave_type_id' => 'required|exists:leave_types,id',
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after_or_equal:start_date',
                'total_days' => 'required|numeric|min:0.5',
                'reason' => 'nullable|string|max:1000',
                'documents' => 'nullable|array|max:5',
                'documents.*.document_name' => 'required_with:documents|string|max:255',
                'documents.*.document_url' => 'required_with:documents|url|max:1000',
                'documents.*.description' => 'nullable|string|max:500',
            ]);

            DB::beginTransaction();

            // Check leave type requirements
            $leaveType = LeaveType::findOrFail($validated['leave_type_id']);
            if ($leaveType->requires_attachment && (empty($validated['documents']))) {
                return response()->json([
                    'success' => false,
                    'message' => 'This leave type requires document attachments',
                ], 422);
            }

            // Check leave balance
            $currentYear = Carbon::now()->year;
            $leaveBalance = LeaveBalance::where('employee_id', $validated['employee_id'])
                ->where('leave_type_id', $validated['leave_type_id'])
                ->where('year', $currentYear)
                ->first();

            if (! $leaveBalance || $leaveBalance->remaining_days < $validated['total_days']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient leave balance',
                    'data' => [
                        'requested_days' => $validated['total_days'],
                        'available_days' => $leaveBalance ? $leaveBalance->remaining_days : 0,
                    ],
                ], 400);
            }

            // Create leave request
            $leaveRequest = LeaveRequest::create([
                'employee_id' => $validated['employee_id'],
                'leave_type_id' => $validated['leave_type_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'total_days' => $validated['total_days'],
                'reason' => $validated['reason'],
                'status' => 'pending',
                'created_by' => auth()->user()->name ?? 'System',
            ]);

            // Handle document URLs
            if (! empty($validated['documents'])) {
                foreach ($validated['documents'] as $document) {
                    LeaveAttachment::create([
                        'leave_request_id' => $leaveRequest->id,
                        'document_name' => $document['document_name'],
                        'document_url' => $document['document_url'],
                        'description' => $document['description'] ?? null,
                        'added_at' => now(),
                        'created_by' => auth()->user()->name ?? 'System',
                    ]);
                }
            }

            // Create initial approval record
            LeaveRequestApproval::create([
                'leave_request_id' => $leaveRequest->id,
                'approver_role' => 'HR Manager',
                'status' => 'pending',
                'created_by' => auth()->user()->name ?? 'System',
            ]);

            DB::commit();

            // Load relationships for response
            $leaveRequest->load([
                'employee:id,staff_id,first_name_en,last_name_en',
                'leaveType:id,name',
                'approvals',
                'attachments',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Leave request created successfully',
                'data' => $leaveRequest,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating leave request: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create leave request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a leave request with status change handling
     *
     * @OA\Put(
     *     path="/leave-requests/{id}",
     *     summary="Update a leave request",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="start_date", type="string", format="date"),
     *             @OA\Property(property="end_date", type="string", format="date"),
     *             @OA\Property(property="total_days", type="number", format="float"),
     *             @OA\Property(property="reason", type="string"),
     *             @OA\Property(property="status", type="string", enum={"pending", "approved", "declined", "cancelled"})
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Leave request updated successfully",
     *
     *         @OA\JsonContent(ref="#/components/schemas/LeaveRequest")
     *     )
     * )
     */
    public function updateLeaveRequest(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'total_days' => 'nullable|numeric|min:0.5',
                'reason' => 'nullable|string|max:1000',
                'status' => 'nullable|in:pending,approved,declined,cancelled',
            ]);

            DB::beginTransaction();

            $leaveRequest = LeaveRequest::findOrFail($id);
            $oldStatus = $leaveRequest->status;
            $newStatus = $validated['status'] ?? $oldStatus;

            // Handle balance updates based on status changes
            if ($oldStatus !== $newStatus) {
                $this->handleStatusChange($leaveRequest, $oldStatus, $newStatus);
            }

            // Update the leave request
            $leaveRequest->update(array_merge($validated, [
                'updated_by' => auth()->user()->name ?? 'System',
            ]));

            DB::commit();

            $leaveRequest->load([
                'employee:id,staff_id,first_name_en,last_name_en',
                'leaveType:id,name',
                'approvals',
                'attachments',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Leave request updated successfully',
                'data' => $leaveRequest,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating leave request: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update leave request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a leave request with balance restoration
     *
     * @OA\Delete(
     *     path="/leave-requests/{id}",
     *     summary="Delete a leave request",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Leave request deleted successfully"
     *     )
     * )
     */
    public function deleteLeaveRequest($id)
    {
        try {
            DB::beginTransaction();

            $leaveRequest = LeaveRequest::findOrFail($id);

            // Restore balance if the request was approved
            if ($leaveRequest->status === 'approved') {
                $this->restoreLeaveBalance($leaveRequest);
            }

            // Note: Attachments are now document URLs, so no physical files to delete
            // The attachments will be deleted automatically due to foreign key cascade

            $leaveRequest->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Leave request deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting leave request: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete leave request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ==================== LEAVE TYPES ====================

    /**
     * Get paginated leave types with search
     *
     * @OA\Get(
     *     path="/leave-types",
     *     summary="Get paginated leave types",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="page", in="query", @OA\Schema(type="integer", minimum=1)),
     *     @OA\Parameter(name="per_page", in="query", @OA\Schema(type="integer", minimum=1, maximum=100)),
     *     @OA\Parameter(name="search", in="query", @OA\Schema(type="string")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Leave types retrieved successfully"
     *     )
     * )
     */
    public function getLeaveTypes(Request $request)
    {
        try {
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'search' => 'string|nullable|max:255',
            ]);

            $perPage = $validated['per_page'] ?? 10;
            $query = LeaveType::query();

            // Apply search filter
            if (! empty($validated['search'])) {
                $searchTerm = trim($validated['search']);
                $query->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            }

            $leaveTypes = $query->orderBy('name', 'asc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Leave types retrieved successfully',
                'data' => $leaveTypes->items(),
                'pagination' => [
                    'current_page' => $leaveTypes->currentPage(),
                    'per_page' => $leaveTypes->perPage(),
                    'total' => $leaveTypes->total(),
                    'last_page' => $leaveTypes->lastPage(),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve leave types',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new leave type
     *
     * @OA\Post(
     *     path="/leave-types",
     *     summary="Create a new leave type",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name"},
     *
     *             @OA\Property(property="name", type="string", maxLength=100),
     *             @OA\Property(property="default_duration", type="number", format="float"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="requires_attachment", type="boolean")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Leave type created successfully"
     *     )
     * )
     */
    public function createLeaveType(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100|unique:leave_types,name',
                'default_duration' => 'nullable|numeric|min:0',
                'description' => 'nullable|string|max:1000',
                'requires_attachment' => 'boolean',
            ]);

            $leaveType = LeaveType::create(array_merge($validated, [
                'created_by' => auth()->user()->name ?? 'System',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Leave type created successfully',
                'data' => $leaveType,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create leave type',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a leave type
     *
     * @OA\Put(
     *     path="/leave-types/{id}",
     *     summary="Update a leave type",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Leave type updated successfully")
     * )
     */
    public function updateLeaveType(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'name' => 'sometimes|string|max:100|unique:leave_types,name,'.$id,
                'default_duration' => 'nullable|numeric|min:0',
                'description' => 'nullable|string|max:1000',
                'requires_attachment' => 'boolean',
            ]);

            $leaveType = LeaveType::findOrFail($id);
            $leaveType->update(array_merge($validated, [
                'updated_by' => auth()->user()->name ?? 'System',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Leave type updated successfully',
                'data' => $leaveType,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update leave type',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a leave type
     *
     * @OA\Delete(
     *     path="/leave-types/{id}",
     *     summary="Delete a leave type",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Leave type deleted successfully")
     * )
     */
    public function deleteLeaveType($id)
    {
        try {
            $leaveType = LeaveType::findOrFail($id);

            // Check if leave type is being used
            $inUse = LeaveRequest::where('leave_type_id', $id)->exists() ||
                     LeaveBalance::where('leave_type_id', $id)->exists();

            if ($inUse) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete leave type as it is currently in use',
                ], 400);
            }

            $leaveType->delete();

            return response()->json([
                'success' => true,
                'message' => 'Leave type deleted successfully',
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete leave type',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ==================== LEAVE BALANCES ====================

    /**
     * Get leave balances with filtering
     *
     * @OA\Get(
     *     path="/leave-balances",
     *     summary="Get leave balances with filtering",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="leave_type_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="year", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Leave balances retrieved successfully")
     * )
     */
    public function getLeaveBalances(Request $request)
    {
        try {
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'employee_id' => 'integer|exists:employees,id',
                'leave_type_id' => 'integer|exists:leave_types,id',
                'year' => 'integer|min:2020|max:2030',
                'search' => 'string|nullable|max:255',
            ]);

            $perPage = $validated['per_page'] ?? 10;
            $query = LeaveBalance::with([
                'employee:id,staff_id,first_name_en,last_name_en,subsidiary',
                'leaveType:id,name',
            ]);

            // Apply filters
            if (! empty($validated['employee_id'])) {
                $query->where('employee_id', $validated['employee_id']);
            }

            if (! empty($validated['leave_type_id'])) {
                $query->where('leave_type_id', $validated['leave_type_id']);
            }

            if (! empty($validated['year'])) {
                $query->where('year', $validated['year']);
            } else {
                $query->where('year', Carbon::now()->year);
            }

            // Apply search filter
            if (! empty($validated['search'])) {
                $searchTerm = trim($validated['search']);
                $query->whereHas('employee', function ($q) use ($searchTerm) {
                    $q->where('staff_id', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('first_name_en', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('last_name_en', 'LIKE', "%{$searchTerm}%");
                });
            }

            $leaveBalances = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Leave balances retrieved successfully',
                'data' => $leaveBalances->items(),
                'pagination' => [
                    'current_page' => $leaveBalances->currentPage(),
                    'per_page' => $leaveBalances->perPage(),
                    'total' => $leaveBalances->total(),
                    'last_page' => $leaveBalances->lastPage(),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve leave balances',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a leave balance
     *
     * @OA\Post(
     *     path="/leave-balances",
     *     summary="Create a leave balance",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=201, description="Leave balance created successfully")
     * )
     */
    public function createLeaveBalance(Request $request)
    {
        try {
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'leave_type_id' => 'required|exists:leave_types,id',
                'total_days' => 'required|numeric|min:0',
                'year' => 'required|integer|min:2020|max:2030',
            ]);

            // Check for existing balance
            $existing = LeaveBalance::where('employee_id', $validated['employee_id'])
                ->where('leave_type_id', $validated['leave_type_id'])
                ->where('year', $validated['year'])
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Leave balance already exists for this employee, leave type, and year',
                ], 400);
            }

            $leaveBalance = LeaveBalance::create([
                'employee_id' => $validated['employee_id'],
                'leave_type_id' => $validated['leave_type_id'],
                'total_days' => $validated['total_days'],
                'used_days' => 0,
                'remaining_days' => $validated['total_days'],
                'year' => $validated['year'],
                'created_by' => auth()->user()->name ?? 'System',
            ]);

            $leaveBalance->load(['employee:id,staff_id,first_name_en,last_name_en', 'leaveType:id,name']);

            return response()->json([
                'success' => true,
                'message' => 'Leave balance created successfully',
                'data' => $leaveBalance,
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create leave balance',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a leave balance with automatic remaining_days calculation
     *
     * @OA\Put(
     *     path="/leave-balances/{id}",
     *     summary="Update a leave balance",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Leave balance updated successfully")
     * )
     */
    public function updateLeaveBalance(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'total_days' => 'sometimes|numeric|min:0',
                'used_days' => 'sometimes|numeric|min:0',
            ]);

            $leaveBalance = LeaveBalance::findOrFail($id);

            // Update fields
            if (isset($validated['total_days'])) {
                $leaveBalance->total_days = $validated['total_days'];
            }
            if (isset($validated['used_days'])) {
                $leaveBalance->used_days = $validated['used_days'];
            }

            // Automatically calculate remaining days
            $leaveBalance->remaining_days = $leaveBalance->total_days - $leaveBalance->used_days;
            $leaveBalance->updated_by = auth()->user()->name ?? 'System';
            $leaveBalance->save();

            $leaveBalance->load(['employee:id,staff_id,first_name_en,last_name_en', 'leaveType:id,name']);

            return response()->json([
                'success' => true,
                'message' => 'Leave balance updated successfully',
                'data' => $leaveBalance,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update leave balance',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ==================== APPROVALS ====================

    /**
     * Get approvals for a leave request
     *
     * @OA\Get(
     *     path="/leave-requests/{leaveRequestId}/approvals",
     *     summary="Get approvals for a leave request",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="leaveRequestId", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Approvals retrieved successfully")
     * )
     */
    public function getApprovals($leaveRequestId)
    {
        try {
            $approvals = LeaveRequestApproval::where('leave_request_id', $leaveRequestId)
                ->with('leaveRequest:id,employee_id,leave_type_id,status')
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Approvals retrieved successfully',
                'data' => $approvals,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve approvals',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create an approval for a leave request
     *
     * @OA\Post(
     *     path="/leave-requests/{leaveRequestId}/approvals",
     *     summary="Create an approval",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="leaveRequestId", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=201, description="Approval created successfully")
     * )
     */
    public function createApproval(Request $request, $leaveRequestId)
    {
        try {
            $validated = $request->validate([
                'approver_role' => 'required|string|max:100',
                'approver_name' => 'required|string|max:200',
                'approver_signature' => 'nullable|string|max:200',
                'status' => 'required|in:pending,approved,declined',
            ]);

            DB::beginTransaction();

            $leaveRequest = LeaveRequest::findOrFail($leaveRequestId);

            $approval = LeaveRequestApproval::create([
                'leave_request_id' => $leaveRequestId,
                'approver_role' => $validated['approver_role'],
                'approver_name' => $validated['approver_name'],
                'approver_signature' => $validated['approver_signature'],
                'approval_date' => $validated['status'] !== 'pending' ? now() : null,
                'status' => $validated['status'],
                'created_by' => auth()->user()->name ?? 'System',
            ]);

            // Evaluate overall leave request status
            $this->evaluateLeaveRequestStatus($leaveRequest);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Approval created successfully',
                'data' => $approval,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create approval',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an approval
     *
     * @OA\Put(
     *     path="/approvals/{id}",
     *     summary="Update an approval",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Approval updated successfully")
     * )
     */
    public function updateApproval(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'approver_role' => 'sometimes|string|max:100',
                'approver_name' => 'sometimes|string|max:200',
                'approver_signature' => 'nullable|string|max:200',
                'status' => 'sometimes|in:pending,approved,declined',
            ]);

            DB::beginTransaction();

            $approval = LeaveRequestApproval::findOrFail($id);
            $oldStatus = $approval->status;

            // Update approval date when status changes from pending
            if (isset($validated['status']) && $validated['status'] !== 'pending' && $oldStatus === 'pending') {
                $validated['approval_date'] = now();
            }

            $approval->update(array_merge($validated, [
                'updated_by' => auth()->user()->name ?? 'System',
            ]));

            // Re-evaluate leave request status if approval status changed
            if (isset($validated['status']) && $validated['status'] !== $oldStatus) {
                $this->evaluateLeaveRequestStatus($approval->leaveRequest);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Approval updated successfully',
                'data' => $approval,
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update approval',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get leave balance for specific employee and leave type
     *
     * @OA\Get(
     *     path="/leaves/balance/{employeeId}/{leaveTypeId}",
     *     summary="Get leave balance for specific employee and leave type",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="employeeId",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer"),
     *         description="Employee ID"
     *     ),
     *
     *     @OA\Parameter(
     *         name="leaveTypeId",
     *         in="path",
     *         required=true,
     *
     *         @OA\Schema(type="integer"),
     *         description="Leave Type ID"
     *     ),
     *
     *     @OA\Parameter(
     *         name="year",
     *         in="query",
     *
     *         @OA\Schema(type="integer"),
     *         description="Year for leave balance (defaults to current year)"
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Leave balance retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="employee_id", type="integer"),
     *                 @OA\Property(property="employee_name", type="string"),
     *                 @OA\Property(property="staff_id", type="string"),
     *                 @OA\Property(property="leave_type_id", type="integer"),
     *                 @OA\Property(property="leave_type_name", type="string"),
     *                 @OA\Property(property="total_days", type="number", format="float"),
     *                 @OA\Property(property="used_days", type="number", format="float"),
     *                 @OA\Property(property="remaining_days", type="number", format="float"),
     *                 @OA\Property(property="year", type="integer")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Employee, leave type, or leave balance not found"
     *     )
     * )
     */
    public function getEmployeeLeaveBalance($employeeId, $leaveTypeId, Request $request)
    {
        try {
            // Validate query parameters
            $validated = $request->validate([
                'year' => 'integer|min:2020|max:2030',
            ]);

            $year = $validated['year'] ?? Carbon::now()->year;

            // Verify employee exists
            $employee = Employee::find($employeeId);
            if (! $employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found',
                ], 404);
            }

            // Verify leave type exists
            $leaveType = LeaveType::find($leaveTypeId);
            if (! $leaveType) {
                return response()->json([
                    'success' => false,
                    'message' => 'Leave type not found',
                ], 404);
            }

            // Get leave balance for the specific employee, leave type, and year
            $leaveBalance = LeaveBalance::where('employee_id', $employeeId)
                ->where('leave_type_id', $leaveTypeId)
                ->where('year', $year)
                ->first();

            if (! $leaveBalance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Leave balance not found for this employee and leave type',
                ], 404);
            }

            // Prepare response data
            $responseData = [
                'employee_id' => $employee->id,
                'employee_name' => trim($employee->first_name_en.' '.$employee->last_name_en),
                'staff_id' => $employee->staff_id,
                'subsidiary' => $employee->subsidiary,
                'leave_type_id' => $leaveType->id,
                'leave_type_name' => $leaveType->name,
                'total_days' => (float) $leaveBalance->total_days,
                'used_days' => (float) $leaveBalance->used_days,
                'remaining_days' => (float) $leaveBalance->remaining_days,
                'year' => $leaveBalance->year,
                'requires_attachment' => $leaveType->requires_attachment,
                'leave_type_description' => $leaveType->description,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Leave balance retrieved successfully',
                'data' => $responseData,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error getting employee leave balance: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve leave balance',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ==================== PRIVATE HELPER METHODS ====================

    /**
     * Calculate leave request statistics
     */
    private function calculateLeaveRequestStats($filters = [])
    {
        $baseQuery = LeaveRequest::query();

        // Apply same filters as main query
        if (! empty($filters['from'])) {
            $baseQuery->where('start_date', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $baseQuery->where('end_date', '<=', $filters['to']);
        }
        if (! empty($filters['leave_types'])) {
            $leaveTypeIds = explode(',', $filters['leave_types']);
            $baseQuery->whereIn('leave_type_id', $leaveTypeIds);
        }

        return [
            'total_requests' => (clone $baseQuery)->count(),
            'pending_requests' => (clone $baseQuery)->where('status', 'pending')->count(),
            'approved_requests' => (clone $baseQuery)->where('status', 'approved')->count(),
            'declined_requests' => (clone $baseQuery)->where('status', 'declined')->count(),
            'cancelled_requests' => (clone $baseQuery)->where('status', 'cancelled')->count(),
            'this_month' => (clone $baseQuery)->whereMonth('created_at', Carbon::now()->month)->count(),
            'this_week' => (clone $baseQuery)->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()])->count(),
        ];
    }

    /**
     * Handle status change logic for leave requests
     */
    private function handleStatusChange(LeaveRequest $leaveRequest, $oldStatus, $newStatus)
    {
        $currentYear = Carbon::now()->year;
        $leaveBalance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
            ->where('leave_type_id', $leaveRequest->leave_type_id)
            ->where('year', $currentYear)
            ->first();

        if (! $leaveBalance) {
            return;
        }

        // Handle transitions
        if ($oldStatus === 'approved' && in_array($newStatus, ['declined', 'cancelled'])) {
            // Restore balance
            $leaveBalance->used_days -= $leaveRequest->total_days;
            $leaveBalance->remaining_days += $leaveRequest->total_days;
            $leaveBalance->save();
        } elseif ($oldStatus !== 'approved' && $newStatus === 'approved') {
            // Deduct balance
            $leaveBalance->used_days += $leaveRequest->total_days;
            $leaveBalance->remaining_days -= $leaveRequest->total_days;
            $leaveBalance->save();
        }
    }

    /**
     * Restore leave balance when request is deleted
     */
    private function restoreLeaveBalance(LeaveRequest $leaveRequest)
    {
        $currentYear = Carbon::now()->year;
        $leaveBalance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
            ->where('leave_type_id', $leaveRequest->leave_type_id)
            ->where('year', $currentYear)
            ->first();

        if ($leaveBalance) {
            $leaveBalance->used_days -= $leaveRequest->total_days;
            $leaveBalance->remaining_days += $leaveRequest->total_days;
            $leaveBalance->save();
        }
    }

    /**
     * Evaluate overall leave request status based on all approvals
     */
    private function evaluateLeaveRequestStatus(LeaveRequest $leaveRequest)
    {
        $approvals = $leaveRequest->approvals;

        if ($approvals->isEmpty()) {
            return;
        }

        $hasDeclined = $approvals->where('status', 'declined')->isNotEmpty();
        $hasApproved = $approvals->where('status', 'approved')->isNotEmpty();
        $allApproved = $approvals->where('status', '!=', 'approved')->isEmpty();

        if ($hasDeclined) {
            $newStatus = 'declined';
        } elseif ($allApproved && $approvals->count() > 0) {
            $newStatus = 'approved';
        } else {
            $newStatus = 'pending';
        }

        if ($leaveRequest->status !== $newStatus) {
            $oldStatus = $leaveRequest->status;
            $leaveRequest->update(['status' => $newStatus]);
            $this->handleStatusChange($leaveRequest, $oldStatus, $newStatus);
        }
    }
}
