<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * LeaveBalanceController
 *
 * Manages leave balance CRUD operations.
 *
 * Standard RESTful Methods:
 * - index()   : List all leave balances with filtering
 * - show()    : Get specific employee balance by employee ID and leave type ID
 * - store()   : Create new leave balance
 * - update()  : Update leave balance
 *
 * Related Controllers:
 * - LeaveRequestController : For managing leave requests
 * - LeaveTypeController    : For managing leave types
 *
 * @OA\Tag(
 *     name="Leave Balances",
 *     description="API Endpoints for managing leave balances"
 * )
 */
class LeaveBalanceController extends Controller
{
    /**
     * Display a listing of leave balances with filtering
     *
     * @OA\Get(
     *     path="/leave-balances",
     *     summary="Get leave balances with filtering",
     *     tags={"Leave Balances"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="employee_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="leave_type_id", in="query", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="year", in="query", @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Leave balances retrieved successfully")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'employee_id' => 'integer|exists:employees,id',
                'leave_type_id' => 'integer|exists:leave_types,id',
                'year' => 'integer|min:2020|max:2030',
                'search' => 'string|nullable|max:255',
                'sort_by' => 'string|nullable|in:employee_name,staff_id,leave_type,total_days,used_days,remaining_days,year,created_at',
                'sort_order' => 'string|nullable|in:asc,desc',
            ]);

            $perPage = $validated['per_page'] ?? 10;
            $query = LeaveBalance::with([
                'employee:id,staff_id,first_name_en,last_name_en,organization',
                'leaveType:id,name',
            ]);

            // Apply filters (qualify column names to avoid ambiguity when joins are used)
            if (! empty($validated['employee_id'])) {
                $query->where('leave_balances.employee_id', $validated['employee_id']);
            }

            if (! empty($validated['leave_type_id'])) {
                $query->where('leave_balances.leave_type_id', $validated['leave_type_id']);
            }

            if (! empty($validated['year'])) {
                $query->where('leave_balances.year', $validated['year']);
            } else {
                $query->where('leave_balances.year', Carbon::now()->year);
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

            // Apply sorting
            $sortBy = $validated['sort_by'] ?? 'created_at';
            $sortOrder = $validated['sort_order'] ?? 'desc';

            if ($sortBy === 'employee_name') {
                $query->join('employees', 'leave_balances.employee_id', '=', 'employees.id')
                    ->orderBy('employees.first_name_en', $sortOrder)
                    ->select('leave_balances.*');
            } elseif ($sortBy === 'staff_id') {
                $query->join('employees', 'leave_balances.employee_id', '=', 'employees.id')
                    ->orderBy('employees.staff_id', $sortOrder)
                    ->select('leave_balances.*');
            } elseif ($sortBy === 'leave_type') {
                $query->join('leave_types', 'leave_balances.leave_type_id', '=', 'leave_types.id')
                    ->orderBy('leave_types.name', $sortOrder)
                    ->select('leave_balances.*');
            } elseif (in_array($sortBy, ['total_days', 'used_days', 'remaining_days', 'year'])) {
                $query->orderBy("leave_balances.{$sortBy}", $sortOrder);
            } else {
                $query->orderBy('leave_balances.created_at', 'desc');
            }

            $leaveBalances = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Leave balances retrieved successfully',
                'data' => $leaveBalances->items(),
                'pagination' => [
                    'current_page' => $leaveBalances->currentPage(),
                    'per_page' => $leaveBalances->perPage(),
                    'total' => $leaveBalances->total(),
                    'last_page' => $leaveBalances->lastPage(),
                    'from' => $leaveBalances->firstItem(),
                    'to' => $leaveBalances->lastItem(),
                    'has_more_pages' => $leaveBalances->hasMorePages(),
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
     * Display leave balance for specific employee and leave type
     *
     * @OA\Get(
     *     path="/leave-balances/{employeeId}/{leaveTypeId}",
     *     summary="Get leave balance for specific employee and leave type",
     *     tags={"Leave Balances"},
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
    public function show($employeeId, $leaveTypeId, Request $request): JsonResponse
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
                'organization' => $employee->organization,
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

    /**
     * Store a newly created leave balance
     *
     * @OA\Post(
     *     path="/leave-balances",
     *     summary="Create a leave balance",
     *     tags={"Leave Balances"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=201, description="Leave balance created successfully")
     * )
     */
    public function store(Request $request): JsonResponse
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
     *     path="/leave-balances/{id}",
     *     summary="Update a leave balance",
     *     tags={"Leave Balances"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Leave balance updated successfully")
     * )
     */
    public function update(Request $request, $id): JsonResponse
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
}
