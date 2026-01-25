<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequestItem;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Annotations as OA;

/**
 * LeaveTypeController
 *
 * Manages leave type CRUD operations.
 *
 * Standard RESTful Methods:
 * - index()   : List all leave types with pagination
 * - store()   : Create new leave type
 * - update()  : Update leave type
 * - destroy() : Delete leave type
 *
 * Custom Methods:
 * - options() : Get all leave types for dropdown selection
 *
 * Related Controllers:
 * - LeaveRequestController : For managing leave requests
 * - LeaveBalanceController : For managing leave balances
 *
 * @OA\Tag(
 *     name="Leave Types",
 *     description="API Endpoints for managing leave types"
 * )
 */
class LeaveTypeController extends Controller
{
    /**
     * Display a listing of leave types with search
     *
     * @OA\Get(
     *     path="/leave-types",
     *     summary="Get paginated leave types",
     *     tags={"Leave Types"},
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
    public function index(Request $request): JsonResponse
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
     * Get all leave types for dropdown selection (non-paginated)
     *
     * @OA\Get(
     *     path="/leave-types/options",
     *     summary="Get all leave types for dropdown selection",
     *     description="Returns all active leave types sorted by name, optimized for dropdown/select components (no pagination)",
     *     tags={"Leave Types"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Leave types retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Leave types retrieved successfully"),
     *             @OA\Property(property="data", type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Annual Leave"),
     *                     @OA\Property(property="default_duration", type="number", format="float", example=15),
     *                     @OA\Property(property="description", type="string", example="Annual vacation leave"),
     *                     @OA\Property(property="requires_attachment", type="boolean", example=false)
     *                 )
     *             ),
     *             @OA\Property(property="total", type="integer", example=14, description="Total number of leave types")
     *         )
     *     ),
     *
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function options(): JsonResponse
    {
        try {
            $leaveTypes = LeaveType::orderBy('name', 'asc')->get();

            return response()->json([
                'success' => true,
                'message' => 'Leave types retrieved successfully',
                'data' => $leaveTypes,
                'total' => $leaveTypes->count(),
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
     *     path="/leave-types",
     *     summary="Create a new leave type and automatically apply to all existing employees",
     *     description="Creates a new leave type and automatically creates leave balance records for all existing employees for the current year",
     *     tags={"Leave Types"},
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
    public function store(Request $request): JsonResponse
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
     *     path="/leave-types/{id}",
     *     summary="Update a leave type",
     *     tags={"Leave Types"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Leave type updated successfully")
     * )
     */
    public function update(Request $request, $id): JsonResponse
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
     *     path="/leave-types/{id}",
     *     summary="Delete a leave type",
     *     tags={"Leave Types"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *
     *     @OA\Response(response=200, description="Leave type deleted successfully")
     * )
     */
    public function destroy($id): JsonResponse
    {
        try {
            $leaveType = LeaveType::findOrFail($id);

            // Check if leave type is being used
            $inUse = LeaveRequestItem::where('leave_type_id', $id)->exists() ||
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
}
