<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveRequestItem;
use App\Models\LeaveType;
use App\Services\LeaveCalculationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * LeaveRequestController
 *
 * Manages leave request CRUD operations.
 *
 * Standard RESTful Methods:
 * - index()   : List all leave requests with filtering
 * - show()    : Get single leave request by ID
 * - store()   : Create new leave request
 * - update()  : Update leave request
 * - destroy() : Delete leave request
 * - calculateDays() : Preview working days for a date range
 *
 * Related Controllers:
 * - LeaveTypeController       : For managing leave types
 * - LeaveBalanceController    : For managing leave balances
 * - HolidayController         : For managing organization holidays
 * - LeaveCalculationController: For working day calculations
 *
 * @OA\Tag(
 *     name="Leave Requests",
 *     description="API Endpoints for managing leave requests"
 * )
 */
class LeaveRequestController extends Controller
{
    public function __construct(
        protected LeaveCalculationService $calculationService
    ) {}

    /**
     * Display a listing of leave requests with filtering and sorting
     *
     * @OA\Get(
     *     path="/leave-requests",
     *     summary="Get paginated leave requests with advanced filtering",
     *     tags={"Leave Requests"},
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
     *             @OA\Property(property="pagination", type="object"),
     *             @OA\Property(property="statistics", type="object")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
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
                'employee:id,staff_id,first_name_en,last_name_en,organization',
                'items.leaveType:id,name,requires_attachment',
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
                $query->whereHas('items', function ($q) use ($leaveTypeIds) {
                    $q->whereIn('leave_type_id', $leaveTypeIds);
                });
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
     *     path="/leave-requests/{id}",
     *     summary="Get a specific leave request",
     *     tags={"Leave Requests"},
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
    public function show($id): JsonResponse
    {
        try {
            $leaveRequest = LeaveRequest::with([
                'employee:id,staff_id,first_name_en,last_name_en,organization',
                'employee.employment:id,employee_id,department_id,position_id',
                'employee.employment.department:id,name',
                'employee.employment.position:id,title',
                'items.leaveType:id,name,default_duration,description,requires_attachment',
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
     * Store a newly created leave request with multiple leave types
     *
     * @OA\Post(
     *     path="/leave-requests",
     *     summary="Create a new leave request with multiple leave types",
     *     description="Create a leave request that can include multiple leave types in a single submission.",
     *     tags={"Leave Requests"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"employee_id", "start_date", "end_date", "items"},
     *
     *             @OA\Property(property="employee_id", type="integer", example=123),
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-01-15"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-01-17"),
     *             @OA\Property(property="reason", type="string", example="Family emergency"),
     *             @OA\Property(property="status", type="string", enum={"pending", "approved", "declined"}, example="pending"),
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="leave_type_id", type="integer", example=1),
     *                     @OA\Property(property="days", type="number", format="float", example=2)
     *                 )
     *             ),
     *             @OA\Property(property="supervisor_approved", type="boolean", example=false),
     *             @OA\Property(property="supervisor_approved_date", type="string", format="date"),
     *             @OA\Property(property="hr_site_admin_approved", type="boolean", example=false),
     *             @OA\Property(property="hr_site_admin_approved_date", type="string", format="date"),
     *             @OA\Property(property="attachment_notes", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Leave request created successfully"
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=400, description="Insufficient leave balance")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate request data
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'start_date' => 'required|date|after_or_equal:today',
                'end_date' => 'required|date|after_or_equal:start_date',
                'reason' => 'nullable|string|max:1000',
                'status' => 'nullable|string|in:pending,approved,declined',

                // Items array for multiple leave types
                'items' => 'required|array|min:1',
                'items.*.leave_type_id' => 'required|exists:leave_types,id',
                'items.*.days' => 'required|numeric|min:0.5',

                // Approval fields from paper forms
                'supervisor_approved' => 'nullable|boolean',
                'supervisor_approved_date' => 'nullable|date',
                'hr_site_admin_approved' => 'nullable|boolean',
                'hr_site_admin_approved_date' => 'nullable|date',

                // Attachment notes
                'attachment_notes' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            // Validate no duplicate leave types in items
            $leaveTypeIds = array_column($validated['items'], 'leave_type_id');
            if (count($leaveTypeIds) !== count(array_unique($leaveTypeIds))) {
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate leave types are not allowed in a single request',
                ], 422);
            }

            // Check for overlapping leave requests
            $overlapCheck = $this->checkLeaveOverlap(
                $validated['employee_id'],
                $validated['start_date'],
                $validated['end_date']
            );

            if ($overlapCheck['has_overlap']) {
                return response()->json([
                    'success' => false,
                    'message' => $overlapCheck['message'],
                    'error_type' => 'overlap',
                    'conflicts' => $overlapCheck['conflicts'],
                ], 422);
            }

            // Calculate total days from items
            $totalDays = array_sum(array_column($validated['items'], 'days'));

            // Check if any leave type requires attachment
            $leaveTypes = LeaveType::whereIn('id', $leaveTypeIds)->get()->keyBy('id');
            $requiresAttachment = $leaveTypes->contains('requires_attachment', true);

            if ($requiresAttachment && empty($validated['attachment_notes'])) {
                $requiringTypes = $leaveTypes->filter(fn ($type) => $type->requires_attachment)->pluck('name')->join(', ');

                return response()->json([
                    'success' => false,
                    'message' => "This request includes leave types that require attachment notes: {$requiringTypes}",
                ], 422);
            }

            // Only check balance if status is approved
            $status = $validated['status'] ?? 'pending';
            if ($status === 'approved') {
                // Check balance for EACH leave type
                foreach ($validated['items'] as $item) {
                    $balanceCheck = $this->checkLeaveBalance(
                        $validated['employee_id'],
                        $item['leave_type_id'],
                        $item['days']
                    );

                    if (! $balanceCheck['valid']) {
                        $leaveTypeName = $leaveTypes[$item['leave_type_id']]->name ?? 'Unknown';

                        return response()->json([
                            'success' => false,
                            'message' => "Insufficient balance for {$leaveTypeName}: {$balanceCheck['message']}",
                            'data' => array_merge($balanceCheck, ['leave_type' => $leaveTypeName]),
                        ], 400);
                    }
                }
            }

            // Create leave request with approval data
            $leaveRequest = LeaveRequest::create([
                'employee_id' => $validated['employee_id'],
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'total_days' => $totalDays,
                'reason' => $validated['reason'],
                'status' => $status,
                'supervisor_approved' => $validated['supervisor_approved'] ?? false,
                'supervisor_approved_date' => $validated['supervisor_approved_date'] ?? null,
                'hr_site_admin_approved' => $validated['hr_site_admin_approved'] ?? false,
                'hr_site_admin_approved_date' => $validated['hr_site_admin_approved_date'] ?? null,
                'attachment_notes' => $validated['attachment_notes'] ?? null,
                'created_by' => auth()->user()->name ?? 'System',
            ]);

            // Create leave request items
            foreach ($validated['items'] as $item) {
                LeaveRequestItem::create([
                    'leave_request_id' => $leaveRequest->id,
                    'leave_type_id' => $item['leave_type_id'],
                    'days' => $item['days'],
                ]);
            }

            // Deduct balances if approved
            if ($status === 'approved') {
                foreach ($validated['items'] as $item) {
                    $this->deductLeaveBalance(
                        $validated['employee_id'],
                        $item['leave_type_id'],
                        $item['days']
                    );
                }
            }

            DB::commit();

            // Clear cache to ensure fresh statistics
            $this->clearStatisticsCache();

            // Load relationships for response
            $leaveRequest->load([
                'employee:id,staff_id,first_name_en,last_name_en',
                'items.leaveType:id,name',
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
     * Update the specified leave request with multiple leave types and approval information
     *
     * @OA\Put(
     *     path="/leave-requests/{id}",
     *     summary="Update a leave request",
     *     tags={"Leave Requests"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Leave request updated successfully"),
     *     @OA\Response(response=404, description="Leave request not found"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=400, description="Insufficient leave balance")
     * )
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'reason' => 'nullable|string|max:1000',
                'status' => 'nullable|in:pending,approved,declined,cancelled',

                // Items array for updating leave types
                'items' => 'nullable|array|min:1',
                'items.*.leave_type_id' => 'required_with:items|exists:leave_types,id',
                'items.*.days' => 'required_with:items|numeric|min:0.5',

                // Approval fields from paper forms
                'supervisor_approved' => 'nullable|boolean',
                'supervisor_approved_date' => 'nullable|date',
                'hr_site_admin_approved' => 'nullable|boolean',
                'hr_site_admin_approved_date' => 'nullable|date',

                // Attachment notes
                'attachment_notes' => 'nullable|string|max:1000',
            ]);

            DB::beginTransaction();

            $leaveRequest = LeaveRequest::with('items')->findOrFail($id);
            $oldStatus = $leaveRequest->status;
            $newStatus = $validated['status'] ?? $oldStatus;

            // Check for overlapping leave requests if dates are being changed
            $checkStartDate = $validated['start_date'] ?? $leaveRequest->start_date->format('Y-m-d');
            $checkEndDate = $validated['end_date'] ?? $leaveRequest->end_date->format('Y-m-d');

            // Only check overlap if dates have actually changed
            $datesChanged = (isset($validated['start_date']) && $validated['start_date'] !== $leaveRequest->start_date->format('Y-m-d'))
                || (isset($validated['end_date']) && $validated['end_date'] !== $leaveRequest->end_date->format('Y-m-d'));

            if ($datesChanged) {
                $overlapCheck = $this->checkLeaveOverlap(
                    $leaveRequest->employee_id,
                    $checkStartDate,
                    $checkEndDate,
                    $id // Exclude current request from overlap check
                );

                if ($overlapCheck['has_overlap']) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => $overlapCheck['message'],
                        'error_type' => 'overlap',
                        'conflicts' => $overlapCheck['conflicts'],
                    ], 422);
                }
            }

            // If items are provided, handle item updates
            if (isset($validated['items'])) {
                // Validate no duplicate leave types
                $leaveTypeIds = array_column($validated['items'], 'leave_type_id');
                if (count($leaveTypeIds) !== count(array_unique($leaveTypeIds))) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Duplicate leave types are not allowed in a single request',
                    ], 422);
                }

                // If currently approved, restore old balances first
                if ($oldStatus === 'approved') {
                    foreach ($leaveRequest->items as $item) {
                        $this->restoreLeaveBalanceForItem(
                            $leaveRequest->employee_id,
                            $item->leave_type_id,
                            $item->days
                        );
                    }
                }

                // Delete old items and create new ones
                $leaveRequest->items()->delete();

                foreach ($validated['items'] as $item) {
                    LeaveRequestItem::create([
                        'leave_request_id' => $leaveRequest->id,
                        'leave_type_id' => $item['leave_type_id'],
                        'days' => $item['days'],
                    ]);
                }

                // Recalculate total days
                $leaveRequest->total_days = array_sum(array_column($validated['items'], 'days'));
            }

            // Check balance if status is changing to approved
            if ($newStatus === 'approved') {
                $items = isset($validated['items'])
                    ? $validated['items']
                    : $leaveRequest->items->map(fn ($item) => [
                        'leave_type_id' => $item->leave_type_id,
                        'days' => $item->days,
                    ])->toArray();

                foreach ($items as $item) {
                    $balanceCheck = $this->checkLeaveBalance(
                        $leaveRequest->employee_id,
                        $item['leave_type_id'],
                        $item['days'],
                        $id
                    );

                    if (! $balanceCheck['valid']) {
                        DB::rollBack();
                        $leaveType = LeaveType::find($item['leave_type_id']);

                        return response()->json([
                            'success' => false,
                            'message' => "Insufficient balance for {$leaveType->name}: {$balanceCheck['message']}",
                            'data' => $balanceCheck,
                        ], 400);
                    }
                }
            }

            // Handle status change for balance updates
            if ($oldStatus !== $newStatus) {
                if ($oldStatus === 'approved' && in_array($newStatus, ['declined', 'cancelled', 'pending'])) {
                    // Restore balances (if not already done)
                    if (! isset($validated['items'])) {
                        foreach ($leaveRequest->items as $item) {
                            $this->restoreLeaveBalanceForItem(
                                $leaveRequest->employee_id,
                                $item->leave_type_id,
                                $item->days
                            );
                        }
                    }
                } elseif ($oldStatus !== 'approved' && $newStatus === 'approved') {
                    // Deduct balances
                    foreach ($leaveRequest->fresh()->items as $item) {
                        $this->deductLeaveBalance(
                            $leaveRequest->employee_id,
                            $item->leave_type_id,
                            $item->days
                        );
                    }
                }
            }

            // Update the leave request with all validated data (except items)
            $updateData = collect($validated)->except(['items'])->toArray();
            $leaveRequest->update(array_merge($updateData, [
                'updated_by' => auth()->user()->name ?? 'System',
            ]));

            DB::commit();

            // Clear cache to ensure fresh statistics
            $this->clearStatisticsCache();

            $leaveRequest->load([
                'employee:id,staff_id,first_name_en,last_name_en',
                'items.leaveType:id,name',
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
     *     path="/leave-requests/{id}",
     *     summary="Delete a leave request",
     *     description="Deletes a leave request and restores leave balance if the request was approved.",
     *     tags={"Leave Requests"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Leave request deleted successfully"
     *     ),
     *     @OA\Response(response=404, description="Leave request not found")
     * )
     */
    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $leaveRequest = LeaveRequest::with('items')->findOrFail($id);

            // Restore balance if the request was approved
            if ($leaveRequest->status === 'approved') {
                foreach ($leaveRequest->items as $item) {
                    $this->restoreLeaveBalanceForItem(
                        $leaveRequest->employee_id,
                        $item->leave_type_id,
                        $item->days
                    );
                }
            }

            // Delete will cascade to items automatically
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

    /**
     * Calculate working days for a date range (preview before submission).
     *
     * This endpoint helps the frontend show real-time day calculation as users
     * select their leave dates, excluding weekends and holidays automatically.
     *
     * @OA\Post(
     *     path="/leave-requests/calculate-days",
     *     summary="Calculate working days for leave date range",
     *     description="Returns the number of working days (excluding weekends and holidays) for the given date range. Use this for real-time preview before submitting a leave request.",
     *     tags={"Leave Requests"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"start_date", "end_date"},
     *
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-01-15"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-01-22"),
     *             @OA\Property(property="detailed", type="boolean", example=false, description="Include detailed breakdown of excluded dates")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Working days calculated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="working_days", type="integer", example=5),
     *                 @OA\Property(property="total_calendar_days", type="integer", example=8),
     *                 @OA\Property(property="weekend_days", type="integer", example=2),
     *                 @OA\Property(property="holiday_days", type="integer", example=1)
     *             )
     *         )
     *     )
     * )
     */
    public function calculateDays(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'detailed' => 'nullable|boolean',
            ]);

            $detailed = $validated['detailed'] ?? false;

            if ($detailed) {
                $result = $this->calculationService->calculateWorkingDaysDetailed(
                    $validated['start_date'],
                    $validated['end_date']
                );
            } else {
                $workingDays = $this->calculationService->calculateWorkingDays(
                    $validated['start_date'],
                    $validated['end_date']
                );

                $start = Carbon::parse($validated['start_date']);
                $end = Carbon::parse($validated['end_date']);
                $totalDays = $start->diffInDays($end) + 1;

                $result = [
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'],
                    'working_days' => $workingDays,
                    'total_calendar_days' => $totalDays,
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Working days calculated successfully',
                'data' => $result,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error calculating leave days: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate working days',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check for overlapping leave requests before submission (preview)
     *
     * This endpoint allows the frontend to check if there are any overlapping
     * leave requests for an employee before they submit their request.
     *
     * @OA\Post(
     *     path="/leave-requests/check-overlap",
     *     summary="Check for overlapping leave requests",
     *     description="Checks if the given date range overlaps with any existing pending or approved leave requests for the employee.",
     *     tags={"Leave Requests"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"employee_id", "start_date", "end_date"},
     *
     *             @OA\Property(property="employee_id", type="integer", example=123),
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-01-15"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-01-17"),
     *             @OA\Property(property="exclude_request_id", type="integer", example=null, description="Request ID to exclude (for updates)")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Overlap check completed",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="has_overlap", type="boolean", example=false),
     *                 @OA\Property(property="conflicts", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="message", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function checkOverlap(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'exclude_request_id' => 'nullable|integer|exists:leave_requests,id',
            ]);

            $overlapCheck = $this->checkLeaveOverlap(
                $validated['employee_id'],
                $validated['start_date'],
                $validated['end_date'],
                $validated['exclude_request_id'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => $overlapCheck['has_overlap'] ? 'Overlapping requests found' : 'No overlapping requests',
                'data' => $overlapCheck,
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error checking leave overlap: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to check leave overlap',
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
     * Check for overlapping leave requests for the same employee
     *
     * @param  int  $employeeId  Employee ID
     * @param  string  $startDate  Start date of the new request
     * @param  string  $endDate  End date of the new request
     * @param  int|null  $excludeRequestId  Request ID to exclude (for updates)
     * @return array ['has_overlap' => bool, 'conflicts' => array, 'message' => string]
     */
    private function checkLeaveOverlap(int $employeeId, string $startDate, string $endDate, ?int $excludeRequestId = null): array
    {
        $query = LeaveRequest::where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'approved']) // Only check active requests
            ->where(function ($q) use ($startDate, $endDate) {
                // Check for any date overlap:
                // 1. New request starts during existing request
                // 2. New request ends during existing request
                // 3. New request completely contains existing request
                // 4. Existing request completely contains new request
                $q->where(function ($inner) use ($startDate, $endDate) {
                    $inner->where('start_date', '<=', $endDate)
                        ->where('end_date', '>=', $startDate);
                });
            })
            ->with(['items.leaveType:id,name']);

        // Exclude current request when updating
        if ($excludeRequestId) {
            $query->where('id', '!=', $excludeRequestId);
        }

        $overlappingRequests = $query->get();

        if ($overlappingRequests->isEmpty()) {
            return [
                'has_overlap' => false,
                'conflicts' => [],
                'message' => 'No overlapping leave requests found',
            ];
        }

        // Build detailed conflict information
        $conflicts = $overlappingRequests->map(function ($request) {
            $leaveTypes = $request->items->map(fn ($item) => $item->leaveType->name ?? 'Unknown')->join(', ');

            return [
                'id' => $request->id,
                'start_date' => $request->start_date->format('Y-m-d'),
                'end_date' => $request->end_date->format('Y-m-d'),
                'total_days' => $request->total_days,
                'leave_types' => $leaveTypes,
                'status' => $request->status,
                'reason' => $request->reason,
            ];
        })->toArray();

        // Build human-readable message
        $conflictDescriptions = array_map(function ($conflict) {
            return sprintf(
                '%s (%s to %s, %s days, Status: %s)',
                $conflict['leave_types'],
                $conflict['start_date'],
                $conflict['end_date'],
                $conflict['total_days'],
                ucfirst($conflict['status'])
            );
        }, $conflicts);

        $message = 'Your leave request overlaps with existing request(s): '.implode('; ', $conflictDescriptions);

        return [
            'has_overlap' => true,
            'conflicts' => $conflicts,
            'message' => $message,
        ];
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
     * Deduct leave balance for a specific leave type
     */
    private function deductLeaveBalance(int $employeeId, int $leaveTypeId, float $days): void
    {
        $currentYear = Carbon::now()->year;
        $leaveBalance = LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $currentYear)
            ->first();

        if ($leaveBalance) {
            $newUsedDays = $leaveBalance->used_days + $days;
            $newRemainingDays = $leaveBalance->total_days - $newUsedDays;

            // Safety check
            if ($newRemainingDays >= 0) {
                $leaveBalance->used_days = $newUsedDays;
                $leaveBalance->remaining_days = $newRemainingDays;
                $leaveBalance->save();
            } else {
                Log::warning('Attempted to create negative balance for leave type', [
                    'employee_id' => $employeeId,
                    'leave_type_id' => $leaveTypeId,
                    'days' => $days,
                ]);
                throw new \Exception('Operation would result in negative leave balance');
            }
        }
    }

    /**
     * Restore leave balance for a specific leave type
     */
    private function restoreLeaveBalanceForItem(int $employeeId, int $leaveTypeId, float $days): void
    {
        $currentYear = Carbon::now()->year;
        $leaveBalance = LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $currentYear)
            ->first();

        if ($leaveBalance) {
            // Ensure used_days doesn't go below 0
            $leaveBalance->used_days = max(0, $leaveBalance->used_days - $days);
            $leaveBalance->remaining_days = $leaveBalance->total_days - $leaveBalance->used_days;
            $leaveBalance->save();
        }
    }
}
