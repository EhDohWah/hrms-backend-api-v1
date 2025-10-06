<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
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
     * Display a listing of leave requests with filtering and sorting
     *
     * @OA\Get(
     *     path="/leaves/requests",
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
     *     @OA\Parameter(name="supervisor_approved", in="query", @OA\Schema(type="boolean"), description="Filter by supervisor approval status"),
     *     @OA\Parameter(name="hr_site_admin_approved", in="query", @OA\Schema(type="boolean"), description="Filter by HR/Site Admin approval status"),
     *     @OA\Parameter(name="sort_by", in="query", @OA\Schema(type="string", enum={"recently_added", "ascending", "descending", "last_month", "last_7_days"}), description="Sort option"),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Leave requests retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Leave requests retrieved successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/LeaveRequest")),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=150),
     *                 @OA\Property(property="last_page", type="integer", example=15),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=10),
     *                 @OA\Property(property="has_more_pages", type="boolean", example=true)
     *             ),
     *             @OA\Property(property="statistics", type="object",
     *                 @OA\Property(property="totalRequests", type="integer", example=150),
     *                 @OA\Property(property="pendingRequests", type="integer", example=25),
     *                 @OA\Property(property="approvedRequests", type="integer", example=100),
     *                 @OA\Property(property="declinedRequests", type="integer", example=20),
     *                 @OA\Property(property="cancelledRequests", type="integer", example=5),
     *                 @OA\Property(property="thisMonthRequests", type="integer", example=45),
     *                 @OA\Property(property="thisWeekRequests", type="integer", example=12),
     *                 @OA\Property(property="thisYearRequests", type="integer", example=150),
     *                 @OA\Property(property="statusBreakdown", type="object",
     *                     @OA\Property(property="pending", type="integer", example=25),
     *                     @OA\Property(property="approved", type="integer", example=100),
     *                     @OA\Property(property="declined", type="integer", example=20),
     *                     @OA\Property(property="cancelled", type="integer", example=5)
     *                 ),
     *                 @OA\Property(property="timeBreakdown", type="object",
     *                     @OA\Property(property="thisWeek", type="integer", example=12),
     *                     @OA\Property(property="thisMonth", type="integer", example=45),
     *                     @OA\Property(property="thisYear", type="integer", example=150)
     *                 ),
     *                 @OA\Property(property="leaveTypeBreakdown", type="object", example={"Annual Leave": 80, "Sick Leave": 30, "Personal Leave": 25})
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
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
                'supervisor_approved' => 'boolean|nullable',
                'hr_site_admin_approved' => 'boolean|nullable',
                'sort_by' => 'string|nullable|in:recently_added,ascending,descending,last_month,last_7_days',
            ]);

            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;
            $sortBy = $validated['sort_by'] ?? 'recently_added';

            // Build optimized query with eager loading
            $query = LeaveRequest::with([
                'employee:id,staff_id,first_name_en,last_name_en,subsidiary',
                'leaveType:id,name,requires_attachment',
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

            // Apply approval filters
            if (isset($validated['supervisor_approved'])) {
                $query->where('supervisor_approved', $validated['supervisor_approved']);
            }

            if (isset($validated['hr_site_admin_approved'])) {
                $query->where('hr_site_admin_approved', $validated['hr_site_admin_approved']);
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

            // Calculate comprehensive statistics using the model's static method
            $statistics = LeaveRequest::getStatistics();

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
                'statistics' => $statistics,
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
     * Display the specified leave request with full relationships
     *
     * @OA\Get(
     *     path="/leaves/requests/{id}",
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
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Leave request retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/LeaveRequest")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Leave request not found")
     * )
     */
    public function show($id)
    {
        try {
            $leaveRequest = LeaveRequest::with([
                'employee:id,staff_id,first_name_en,last_name_en,subsidiary',
                'employee.employment:id,employee_id,department_id,position_id',
                'employee.employment.department:id,name',
                'employee.employment.position:id,title',
                'leaveType:id,name,default_duration,description,requires_attachment',
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
     * Store a newly created leave request from paper form data with validation and balance checking
     *
     * @OA\Post(
     *     path="/leaves/requests",
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
     *             @OA\Property(property="employee_id", type="integer", example=1, description="ID of the employee requesting leave"),
     *             @OA\Property(property="leave_type_id", type="integer", example=2, description="ID of the leave type"),
     *             @OA\Property(property="start_date", type="string", format="date", example="2024-12-01", description="Start date of leave"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2024-12-05", description="End date of leave"),
     *             @OA\Property(property="total_days", type="number", format="float", example=5, description="Total number of leave days"),
     *             @OA\Property(property="reason", type="string", example="Family vacation", description="Reason for leave request"),
     *             @OA\Property(property="status", type="string", enum={"pending", "approved", "declined"}, example="pending", description="Leave request status from paper form"),
     *             @OA\Property(property="supervisor_approved", type="boolean", example=false, description="Supervisor approval status"),
     *             @OA\Property(property="supervisor_approved_date", type="string", format="date", example="2024-11-25", description="Supervisor approval date from paper form"),
     *             @OA\Property(property="hr_site_admin_approved", type="boolean", example=false, description="HR/Site Admin approval status"),
     *             @OA\Property(property="hr_site_admin_approved_date", type="string", format="date", example="2024-11-26", description="HR/Site Admin approval date from paper form"),
     *             @OA\Property(property="attachment_notes", type="string", example="Medical certificate attached", description="Notes about attachments from paper form")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Leave request created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Leave request created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/LeaveRequest")
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
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object", example={"employee_id": {"The employee id field is required."}})
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Insufficient leave balance",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Insufficient leave balance. You cannot request more days than available."),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="available_days", type="number", example=5),
     *                 @OA\Property(property="requested_days", type="number", example=10),
     *                 @OA\Property(property="shortfall", type="number", example=5)
     *             )
     *         )
     *     )
     * )
     */
    public function store(Request $request)
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
                'status' => 'nullable|string|in:pending,approved,declined',

                // Approval fields from paper forms
                'supervisor_approved' => 'nullable|boolean',
                'supervisor_approved_date' => 'nullable|date',
                'hr_site_admin_approved' => 'nullable|boolean',
                'hr_site_admin_approved_date' => 'nullable|date',

                // Attachment notes
                'attachment_notes' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            // Check leave type requirements
            $leaveType = LeaveType::findOrFail($validated['leave_type_id']);
            if ($leaveType->requires_attachment && empty($validated['attachment_notes'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'This leave type requires attachment notes',
                ], 422);
            }

            // Only check balance if status is approved, or if no status provided (pending)
            $status = $validated['status'] ?? 'pending';
            if ($status === 'approved') {
                $balanceCheck = $this->checkLeaveBalance(
                    $validated['employee_id'],
                    $validated['leave_type_id'],
                    $validated['total_days']
                );

                if (! $balanceCheck['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => $balanceCheck['message'],
                        'data' => $balanceCheck,
                    ], 400);
                }
            }

            // Create leave request with approval data
            $leaveRequest = LeaveRequest::create([
                'employee_id' => $validated['employee_id'],
                'leave_type_id' => $validated['leave_type_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'total_days' => $validated['total_days'],
                'reason' => $validated['reason'],
                'status' => $status,
                'supervisor_approved' => $validated['supervisor_approved'] ?? false,
                'supervisor_approved_date' => $validated['supervisor_approved_date'] ?? null,
                'hr_site_admin_approved' => $validated['hr_site_admin_approved'] ?? false,
                'hr_site_admin_approved_date' => $validated['hr_site_admin_approved_date'] ?? null,
                'attachment_notes' => $validated['attachment_notes'] ?? null,
                'created_by' => auth()->user()->name ?? 'System',
            ]);

            // Handle status change for balance updates
            if ($status === 'approved') {
                $this->handleStatusChange($leaveRequest, 'pending', 'approved');
            }

            DB::commit();

            // Clear cache to ensure fresh statistics
            $this->clearStatisticsCache();

            // Load relationships for response
            $leaveRequest->load([
                'employee:id,staff_id,first_name_en,last_name_en',
                'leaveType:id,name',
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
     * Update the specified leave request with approval information from paper forms
     *
     * @OA\Put(
     *     path="/leaves/requests/{id}",
     *     summary="Update a leave request with approval data from paper forms",
     *     description="Updates a leave request including approval information entered from completed paper forms",
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
     *             @OA\Property(property="start_date", type="string", format="date", example="2024-12-01", description="Start date of leave"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2024-12-05", description="End date of leave"),
     *             @OA\Property(property="total_days", type="number", format="float", example=5, description="Total number of leave days"),
     *             @OA\Property(property="reason", type="string", example="Updated vacation reason", description="Reason for leave request"),
     *             @OA\Property(property="status", type="string", enum={"pending", "approved", "declined", "cancelled"}, example="approved", description="Leave request status"),
     *             @OA\Property(property="supervisor_approved", type="boolean", example=true, description="Supervisor approval status"),
     *             @OA\Property(property="supervisor_approved_date", type="string", format="date", example="2024-11-25", description="Supervisor approval date from paper form"),
     *             @OA\Property(property="hr_site_admin_approved", type="boolean", example=true, description="HR/Site Admin approval status"),
     *             @OA\Property(property="hr_site_admin_approved_date", type="string", format="date", example="2024-11-26", description="HR/Site Admin approval date from paper form"),
     *             @OA\Property(property="attachment_notes", type="string", example="Updated medical certificate attached", description="Notes about attachments from paper form")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Leave request updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Leave request updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/LeaveRequest")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Leave request not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=400, description="Insufficient leave balance")
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'total_days' => 'nullable|numeric|min:0.5',
                'reason' => 'nullable|string|max:1000',
                'status' => 'nullable|in:pending,approved,declined,cancelled',

                // Approval fields from paper forms
                'supervisor_approved' => 'nullable|boolean',
                'supervisor_approved_date' => 'nullable|date',
                'hr_site_admin_approved' => 'nullable|boolean',
                'hr_site_admin_approved_date' => 'nullable|date',

                // Attachment notes
                'attachment_notes' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            $leaveRequest = LeaveRequest::findOrFail($id);
            $oldStatus = $leaveRequest->status;
            $newStatus = $validated['status'] ?? $oldStatus;

            // Check balance if status is changing to approved
            if ($oldStatus !== $newStatus && $newStatus === 'approved') {
                $balanceCheck = $this->checkLeaveBalance(
                    $leaveRequest->employee_id,
                    $leaveRequest->leave_type_id,
                    $leaveRequest->total_days,
                    $id // Exclude current request from calculation
                );

                if (! $balanceCheck['valid']) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot approve request: '.$balanceCheck['message'],
                        'data' => $balanceCheck,
                    ], 400);
                }
            }

            // Handle status change for balance updates
            if ($oldStatus !== $newStatus) {
                $this->handleStatusChange($leaveRequest, $oldStatus, $newStatus);
            }

            // Update the leave request with all validated data
            $leaveRequest->update(array_merge($validated, [
                'updated_by' => auth()->user()->name ?? 'System',
            ]));

            DB::commit();

            // Clear cache to ensure fresh statistics
            $this->clearStatisticsCache();

            $leaveRequest->load([
                'employee:id,staff_id,first_name_en,last_name_en',
                'leaveType:id,name',
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
     * Remove the specified leave request with balance restoration
     *
     * @OA\Delete(
     *     path="/leaves/requests/{id}",
     *     summary="Delete a leave request",
     *     description="Deletes a leave request and restores leave balance if the request was approved.",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Leave request deleted successfully with related records",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Leave request deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Leave request not found")
     * )
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $leaveRequest = LeaveRequest::findOrFail($id);

            // Restore balance if the request was approved
            if ($leaveRequest->status === 'approved') {
                $this->restoreLeaveBalance($leaveRequest);
            }

            $leaveRequest->delete();

            DB::commit();

            // Clear cache to ensure fresh statistics
            $this->clearStatisticsCache();

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
     * Display a listing of leave types with search
     *
     * @OA\Get(
     *     path="/leaves/types",
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
    public function indexTypes(Request $request)
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
     * Store a newly created leave type and auto-apply to all employees
     *
     * @OA\Post(
     *     path="/leaves/types",
     *     summary="Create a new leave type and automatically apply to all existing employees",
     *     description="Creates a new leave type and automatically creates leave balance records for all existing employees for the current year",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"name"},
     *
     *             @OA\Property(property="name", type="string", maxLength=100, example="Emergency Leave"),
     *             @OA\Property(property="default_duration", type="number", format="float", example=5, description="Default number of days allocated to each employee (defaults to 0 if not provided)"),
     *             @OA\Property(property="description", type="string", example="Emergency leave for unexpected situations"),
     *             @OA\Property(property="requires_attachment", type="boolean", example=false, description="Whether this leave type requires attachment documentation")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Leave type created successfully and applied to all employees",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Leave type created successfully and applied to 150 employees"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="leave_type", type="object",
     *                     @OA\Property(property="id", type="integer", example=12),
     *                     @OA\Property(property="name", type="string", example="Emergency Leave"),
     *                     @OA\Property(property="default_duration", type="number", format="float", example=5),
     *                     @OA\Property(property="description", type="string", example="Emergency leave for unexpected situations"),
     *                     @OA\Property(property="requires_attachment", type="boolean", example=false),
     *                     @OA\Property(property="created_by", type="string", example="System"),
     *                     @OA\Property(property="created_at", type="string", format="datetime"),
     *                     @OA\Property(property="updated_at", type="string", format="datetime")
     *                 ),
     *                 @OA\Property(property="balances_created", type="integer", example=150, description="Number of employee leave balance records created")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function storeTypes(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:100|unique:leave_types,name',
                'default_duration' => 'nullable|numeric|min:0',
                'description' => 'nullable|string|max:1000',
                'requires_attachment' => 'boolean',
            ]);

            DB::beginTransaction();

            $leaveType = LeaveType::create(array_merge($validated, [
                'created_by' => auth()->user()->name ?? 'System',
            ]));

            // Auto-apply new leave type to all existing employees
            $employees = Employee::all();
            $currentYear = Carbon::now()->year;
            $balancesCreated = 0;
            $totalDays = $validated['default_duration'] ?? 0;

            foreach ($employees as $employee) {
                // Check if balance already exists
                $existingBalance = LeaveBalance::where('employee_id', $employee->id)
                    ->where('leave_type_id', $leaveType->id)
                    ->where('year', $currentYear)
                    ->exists();

                if (! $existingBalance) {
                    LeaveBalance::create([
                        'employee_id' => $employee->id,
                        'leave_type_id' => $leaveType->id,
                        'total_days' => $totalDays,
                        'used_days' => 0,
                        'remaining_days' => $totalDays,
                        'year' => $currentYear,
                        'created_by' => auth()->user()->name ?? 'System',
                    ]);
                    $balancesCreated++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Leave type created successfully and applied to {$balancesCreated} employees",
                'data' => [
                    'leave_type' => $leaveType,
                    'balances_created' => $balancesCreated,
                ],
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating leave type: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to create leave type',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update the specified leave type
     *
     * @OA\Put(
     *     path="/leaves/types/{id}",
     *     summary="Update a leave type",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Leave type updated successfully")
     * )
     */
    public function updateTypes(Request $request, $id)
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
     * Remove the specified leave type
     *
     * @OA\Delete(
     *     path="/leaves/types/{id}",
     *     summary="Delete a leave type",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Leave type deleted successfully")
     * )
     */
    public function destroyTypes($id)
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
     * Display a listing of leave balances with filtering
     *
     * @OA\Get(
     *     path="/leaves/balances",
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
    public function indexBalances(Request $request)
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
     * Store a newly created leave balance
     *
     * @OA\Post(
     *     path="/leaves/balances",
     *     summary="Create a leave balance",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=201, description="Leave balance created successfully")
     * )
     */
    public function storeBalances(Request $request)
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
     * Update the specified leave balance with automatic remaining_days calculation
     *
     * @OA\Put(
     *     path="/leaves/balances/{id}",
     *     summary="Update a leave balance",
     *     tags={"Leave Management"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Leave balance updated successfully")
     * )
     */
    public function updateBalances(Request $request, $id)
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

    /**
     * Display leave balance for specific employee and leave type
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
    public function showEmployeeBalance($employeeId, $leaveTypeId, Request $request)
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
     * Clear leave request statistics cache
     */
    private function clearStatisticsCache(): void
    {
        \Cache::forget('leave_request_statistics');
    }

    /**
     * Check if employee has sufficient leave balance for the requested days
     */
    private function checkLeaveBalance(int $employeeId, int $leaveTypeId, float $requestedDays, ?int $excludeRequestId = null): array
    {
        $currentYear = Carbon::now()->year;
        $leaveBalance = LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $currentYear)
            ->first();

        if (! $leaveBalance) {
            return [
                'valid' => false,
                'message' => 'No leave balance found for this employee and leave type for the current year',
                'available_days' => 0,
                'requested_days' => $requestedDays,
            ];
        }

        // Calculate current used days excluding the request being updated (if any)
        $currentUsedDays = $leaveBalance->used_days;
        if ($excludeRequestId) {
            $excludedRequest = LeaveRequest::where('id', $excludeRequestId)
                ->where('status', 'approved')
                ->first();
            if ($excludedRequest) {
                $currentUsedDays -= $excludedRequest->total_days;
            }
        }

        $availableDays = $leaveBalance->total_days - $currentUsedDays;

        if ($availableDays < $requestedDays) {
            return [
                'valid' => false,
                'message' => 'Insufficient leave balance. You cannot request more days than available.',
                'available_days' => $availableDays,
                'requested_days' => $requestedDays,
                'shortfall' => $requestedDays - $availableDays,
            ];
        }

        return [
            'valid' => true,
            'message' => 'Sufficient leave balance available',
            'available_days' => $availableDays,
            'requested_days' => $requestedDays,
            'remaining_after_request' => $availableDays - $requestedDays,
        ];
    }

    /**
     * Handle status change logic for leave requests with balance protection
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
            // Restore balance (safe operation - always valid)
            $leaveBalance->used_days = max(0, $leaveBalance->used_days - $leaveRequest->total_days);
            $leaveBalance->remaining_days = $leaveBalance->total_days - $leaveBalance->used_days;
            $leaveBalance->save();
        } elseif ($oldStatus !== 'approved' && $newStatus === 'approved') {
            // Deduct balance (this should already be validated by checkLeaveBalance)
            $newUsedDays = $leaveBalance->used_days + $leaveRequest->total_days;
            $newRemainingDays = $leaveBalance->total_days - $newUsedDays;

            // Safety check to prevent negative balance (should not happen with our validation)
            if ($newRemainingDays >= 0) {
                $leaveBalance->used_days = $newUsedDays;
                $leaveBalance->remaining_days = $newRemainingDays;
                $leaveBalance->save();
            } else {
                Log::warning('Attempted to create negative balance', [
                    'employee_id' => $leaveRequest->employee_id,
                    'leave_type_id' => $leaveRequest->leave_type_id,
                    'request_id' => $leaveRequest->id,
                    'total_days' => $leaveBalance->total_days,
                    'used_days' => $leaveBalance->used_days,
                    'request_days' => $leaveRequest->total_days,
                    'would_be_remaining' => $newRemainingDays,
                ]);
                throw new \Exception('Operation would result in negative leave balance. This should not happen with proper validation.');
            }
        }
    }

    /**
     * Restore leave balance when request is deleted with safety checks
     */
    private function restoreLeaveBalance(LeaveRequest $leaveRequest)
    {
        $currentYear = Carbon::now()->year;
        $leaveBalance = LeaveBalance::where('employee_id', $leaveRequest->employee_id)
            ->where('leave_type_id', $leaveRequest->leave_type_id)
            ->where('year', $currentYear)
            ->first();

        if ($leaveBalance) {
            // Ensure used_days doesn't go below 0
            $leaveBalance->used_days = max(0, $leaveBalance->used_days - $leaveRequest->total_days);
            $leaveBalance->remaining_days = $leaveBalance->total_days - $leaveBalance->used_days;
            $leaveBalance->save();
        }
    }
}
