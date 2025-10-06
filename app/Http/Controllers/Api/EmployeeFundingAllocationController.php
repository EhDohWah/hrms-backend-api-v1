<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeFundingAllocationResource;
use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
use App\Models\OrgFundedAllocation;
use App\Models\PositionSlot;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Employee Funding Allocations",
 *     description="Operations related to employee funding allocations"
 * )
 */
class EmployeeFundingAllocationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/employee-funding-allocations",
     *     operationId="getEmployeeFundingAllocations",
     *     tags={"Employee Funding Allocations"},
     *     summary="Get list of employee funding allocations",
     *     description="Returns paginated list of employee funding allocations with related data",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         description="Filter by employee ID",
     *         required=false,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EmployeeFundingAllocation")),
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="last_page", type="integer"),
     *             @OA\Property(property="per_page", type="integer"),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        try {
            $query = EmployeeFundingAllocation::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'employment:id,employment_type,start_date,end_date',
                'positionSlot.grantItem.grant:id,name,code',
                'orgFunded.grant:id,name,code',
                'orgFunded.department:id,name',
                'orgFunded.position:id,name',
            ]);

            // Apply filters
            if ($request->has('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }

            if ($request->has('allocation_type')) {
                $query->where('allocation_type', $request->allocation_type);
            }

            if ($request->has('active')) {
                $today = Carbon::today();
                if ($request->boolean('active')) {
                    $query->where('start_date', '<=', $today)
                        ->where(function ($q) use ($today) {
                            $q->whereNull('end_date')
                                ->orWhere('end_date', '>=', $today);
                        });
                } else {
                    $query->where('end_date', '<', $today);
                }
            }

            $allocations = $query->orderByDesc('id')->paginate(20);

            return EmployeeFundingAllocationResource::collection($allocations)
                ->additional([
                    'success' => true,
                    'message' => 'Employee funding allocations retrieved successfully',
                ])
                ->response()
                ->setStatusCode(200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee funding allocations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/employee-funding-allocations/by-grant-item/{grantItemId}",
     *     operationId="getEmployeeFundingAllocationsByGrantItem",
     *     tags={"Employee Funding Allocations"},
     *     summary="Get employee funding allocations by grant item ID",
     *     description="Returns employee funding allocations for a specific grant item (position)",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="grantItemId",
     *         in="path",
     *         description="Grant Item ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EmployeeFundingAllocation")),
     *             @OA\Property(property="total_allocations", type="integer"),
     *             @OA\Property(property="active_allocations", type="integer")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Grant item not found")
     * )
     */
    public function getByGrantItem($grantItemId)
    {
        try {
            // Get all allocations for this grant item
            $allocations = EmployeeFundingAllocation::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'employment:id,employment_type,start_date,end_date',
                'positionSlot.grantItem.grant:id,name,code',
            ])
                ->whereHas('positionSlot', function ($query) use ($grantItemId) {
                    $query->where('grant_item_id', $grantItemId);
                })
                ->where('allocation_type', 'grant')
                ->orderByDesc('id')
                ->get();

            // Calculate active allocations (those that are currently active based on dates)
            $today = Carbon::today();
            $activeCount = $allocations->filter(function ($allocation) use ($today) {
                $startDate = Carbon::parse($allocation->start_date);
                $endDate = $allocation->end_date ? Carbon::parse($allocation->end_date) : null;

                return $startDate->lte($today) && (! $endDate || $endDate->gte($today));
            })->count();

            return response()->json([
                'success' => true,
                'message' => 'Employee funding allocations retrieved successfully',
                'data' => EmployeeFundingAllocationResource::collection($allocations),
                'total_allocations' => $allocations->count(),
                'active_allocations' => $activeCount,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch allocations for grant item',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/employee-funding-allocations",
     *     operationId="createEmployeeFundingAllocation",
     *     tags={"Employee Funding Allocations"},
     *     summary="Create multiple employee funding allocations",
     *     description="Creates multiple employee funding allocations with validation for total effort (must equal 100%) and allocation type constraints. Checks for existing active allocations and prevents duplicates.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"employee_id", "employment_id", "start_date", "allocations"},
     *
     *             @OA\Property(property="employee_id", type="integer", description="ID of the employee"),
     *             @OA\Property(property="employment_id", type="integer", description="ID of the employment"),
     *             @OA\Property(property="start_date", type="string", format="date", description="Start date for allocations"),
     *             @OA\Property(property="end_date", type="string", format="date", description="End date for allocations", nullable=true),
     *             @OA\Property(
     *                 property="allocations",
     *                 type="array",
     *                 description="Array of funding allocations (total effort must equal 100%)",
     *
     *                 @OA\Items(
     *                     type="object",
     *                     required={"allocation_type", "fte"},
     *
     *                     @OA\Property(property="allocation_type", type="string", enum={"grant", "org_funded"}, description="Type of allocation"),
     *                     @OA\Property(property="position_slot_id", type="integer", description="Position slot ID (required for grant allocations)", nullable=true),
     *                     @OA\Property(property="org_funded_id", type="integer", description="Org funded allocation ID (required for org_funded allocations)", nullable=true),
     *                     @OA\Property(property="fte", type="number", format="float", minimum=0, maximum=100, description="FTE as percentage (0-100)"),
     *                     @OA\Property(property="allocated_amount", type="number", format="float", minimum=0, description="Allocated amount", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Allocations created successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee funding allocations created successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EmployeeFundingAllocation")),
     *             @OA\Property(property="total_created", type="integer", example=2),
     *             @OA\Property(property="warnings", type="array", @OA\Items(type="string"), nullable=true, description="Array of warning messages for allocations that failed to create")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation or business logic error",
     *
     *         @OA\JsonContent(
     *             oneOf={
     *
     *                 @OA\Schema(
     *                     type="object",
     *
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Validation failed"),
     *                     @OA\Property(property="errors", type="object", description="Validation errors")
     *                 ),
     *
     *                 @OA\Schema(
     *                     type="object",
     *
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Total effort of all allocations must equal exactly 100%"),
     *                     @OA\Property(property="current_total", type="number", example=85.5)
     *                 ),
     *
     *                 @OA\Schema(
     *                     type="object",
     *
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Employee already has active funding allocations for this employment. Please use the update endpoint to modify existing allocations or end them first.")
     *                 ),
     *
     *                 @OA\Schema(
     *                     type="object",
     *
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Failed to create any allocations"),
     *                     @OA\Property(property="errors", type="array", @OA\Items(type="string"), description="Array of error messages")
     *                 )
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create employee funding allocations"),
     *             @OA\Property(property="error", type="string", description="Error message")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|exists:employees,id',
                'employment_id' => 'required|exists:employments,id',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'allocations' => 'required|array|min:1',
                'allocations.*.allocation_type' => 'required|string|in:grant,org_funded',
                'allocations.*.position_slot_id' => 'required_if:allocations.*.allocation_type,grant|nullable|exists:position_slots,id',
                'allocations.*.org_funded_id' => 'required_if:allocations.*.allocation_type,org_funded|nullable|exists:org_funded_allocations,id',
                'allocations.*.fte' => 'required|numeric|min:0|max:100',
                'allocations.*.allocated_amount' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();
            $currentUser = Auth::user()->name ?? 'system';

            // Validate that the total effort of all new allocations equals exactly 100%
            $totalNewEffort = array_sum(array_column($validated['allocations'], 'fte'));
            if ($totalNewEffort != 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total effort of all allocations must equal exactly 100%',
                    'current_total' => $totalNewEffort,
                ], 422);
            }

            // Check if employee already has any active allocations for this employment (based on dates)
            $today = Carbon::today();
            $existingActiveAllocations = EmployeeFundingAllocation::where('employee_id', $validated['employee_id'])
                ->where('employment_id', $validated['employment_id'])
                ->where('start_date', '<=', $today)
                ->where(function ($query) use ($today) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', $today);
                })
                ->exists();

            if ($existingActiveAllocations) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee already has active funding allocations for this employment. Please use the update endpoint to modify existing allocations or end them first.',
                ], 422);
            }

            DB::beginTransaction();

            $createdAllocations = [];
            $errors = [];

            foreach ($validated['allocations'] as $index => $allocationData) {
                try {
                    $allocationType = $allocationData['allocation_type'];

                    // Validate allocation type specific requirements
                    if ($allocationType === 'grant') {
                        if (empty($allocationData['position_slot_id'])) {
                            $errors[] = "Allocation #{$index}: position_slot_id is required for grant allocations";

                            continue;
                        }

                        // Get the position slot with its grant item for capacity checking
                        $positionSlot = PositionSlot::with('grantItem')->find($allocationData['position_slot_id']);
                        if (! $positionSlot) {
                            $errors[] = "Allocation #{$index}: Position slot not found";

                            continue;
                        }

                        $grantItem = $positionSlot->grantItem;
                        if ($grantItem && $grantItem->grant_position_number > 0) {
                            // Check current active allocations (based on dates)
                            $currentAllocations = EmployeeFundingAllocation::whereHas('positionSlot', function ($query) use ($grantItem) {
                                $query->where('grant_item_id', $grantItem->id);
                            })
                                ->where('allocation_type', 'grant')
                                ->where('start_date', '<=', $today)
                                ->where(function ($query) use ($today) {
                                    $query->whereNull('end_date')
                                        ->orWhere('end_date', '>=', $today);
                                })
                                ->count();

                            if ($currentAllocations >= $grantItem->grant_position_number) {
                                $errors[] = "Allocation #{$index}: Grant position '{$grantItem->grant_position}' has reached its maximum capacity of {$grantItem->grant_position_number} allocations. Currently allocated: {$currentAllocations}";

                                continue;
                            }
                        }

                    } elseif ($allocationType === 'org_funded') {
                        if (empty($allocationData['org_funded_id'])) {
                            $errors[] = "Allocation #{$index}: org_funded_id is required for org_funded allocations";

                            continue;
                        }

                        // Verify org funded allocation exists
                        $orgFunded = OrgFundedAllocation::find($allocationData['org_funded_id']);
                        if (! $orgFunded) {
                            $errors[] = "Allocation #{$index}: Org funded allocation not found";

                            continue;
                        }
                    }

                    // Check if this exact allocation already exists (based on current active allocations)
                    $existingAllocation = EmployeeFundingAllocation::where([
                        'employee_id' => $validated['employee_id'],
                        'employment_id' => $validated['employment_id'],
                        'allocation_type' => $allocationType,
                    ])
                        ->where('start_date', '<=', $today)
                        ->where(function ($query) use ($today) {
                            $query->whereNull('end_date')
                                ->orWhere('end_date', '>=', $today);
                        });

                    if ($allocationType === 'grant') {
                        $existingAllocation->where('position_slot_id', $allocationData['position_slot_id']);
                    } elseif ($allocationType === 'org_funded') {
                        $existingAllocation->where('org_funded_id', $allocationData['org_funded_id']);
                    }

                    if ($existingAllocation->exists()) {
                        $errors[] = "Allocation #{$index}: Already exists for this employee, employment, and {$allocationType} allocation";

                        continue;
                    }

                    // Create the allocation
                    $allocation = EmployeeFundingAllocation::create([
                        'employee_id' => $validated['employee_id'],
                        'employment_id' => $validated['employment_id'],
                        'position_slot_id' => $allocationType === 'grant' ? $allocationData['position_slot_id'] : null,
                        'org_funded_id' => $allocationType === 'org_funded' ? $allocationData['org_funded_id'] : null,
                        'fte' => $allocationData['fte'] / 100, // Convert percentage to decimal
                        'allocation_type' => $allocationType,
                        'allocated_amount' => $allocationData['allocated_amount'] ?? null,
                        'start_date' => $validated['start_date'],
                        'end_date' => $validated['end_date'] ?? null,
                        'created_by' => $currentUser,
                        'updated_by' => $currentUser,
                    ]);

                    $createdAllocations[] = $allocation;

                } catch (\Exception $e) {
                    $errors[] = "Allocation #{$index}: ".$e->getMessage();
                }
            }

            if (empty($createdAllocations) && ! empty($errors)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create any allocations',
                    'errors' => $errors,
                ], 422);
            }

            DB::commit();

            // Load the created allocations with relationships
            $allocationsWithRelations = EmployeeFundingAllocation::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'employment:id,employment_type,start_date',
                'positionSlot.grantItem.grant:id,name,code',
                'positionSlot.grantItem.grant:id,name,code',
                'orgFunded',
            ])->whereIn('id', collect($createdAllocations)->pluck('id'))->get();

            $response = [
                'success' => true,
                'message' => 'Employee funding allocations created successfully',
                'data' => $allocationsWithRelations,
                'total_created' => count($createdAllocations),
            ];

            if (! empty($errors)) {
                $response['warnings'] = $errors;
            }

            return response()->json($response, 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee funding allocations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/employee-funding-allocations/{id}",
     *     operationId="getEmployeeFundingAllocation",
     *     tags={"Employee Funding Allocations"},
     *     summary="Get employee funding allocation by ID",
     *     description="Returns a single employee funding allocation with related data including employee, employment, orgFunded, and positionSlot relationships",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the employee funding allocation",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(ref="#/components/schemas/EmployeeFundingAllocation")
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Allocation not found")
     * )
     */
    public function show($id)
    {
        try {
            $allocation = EmployeeFundingAllocation::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'employment:id,employment_type,start_date,end_date',
                'positionSlot.grantItem.grant:id,name,code',
                'orgFunded.grant:id,name,code',
                'orgFunded.department:id,name',
                'orgFunded.position:id,name',
            ])->findOrFail($id);

            return (new EmployeeFundingAllocationResource($allocation))
                ->additional([
                    'success' => true,
                    'message' => 'Employee funding allocation retrieved successfully',
                ])
                ->response()
                ->setStatusCode(200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee funding allocation not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee funding allocation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/employee-funding-allocations/{id}",
     *     operationId="updateEmployeeFundingAllocation",
     *     tags={"Employee Funding Allocations"},
     *     summary="Update employee funding allocation",
     *     description="Updates an existing employee funding allocation with comprehensive validation including allocation type constraints, capacity checking, and business logic validation.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the employee funding allocation",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="employee_id", type="integer", description="ID of the employee"),
     *             @OA\Property(property="employment_id", type="integer", description="ID of the employment"),
     *             @OA\Property(property="allocation_type", type="string", enum={"grant", "org_funded"}, description="Type of allocation"),
     *             @OA\Property(property="position_slot_id", type="integer", description="ID of the position slot (required for grant allocations)", nullable=true),
     *             @OA\Property(property="org_funded_id", type="integer", description="ID of the org funded allocation (required for org_funded allocations)", nullable=true),
     *             @OA\Property(property="fte", type="number", format="float", minimum=0, maximum=100, description="FTE as percentage (0-100)"),
     *             @OA\Property(property="allocated_amount", type="number", format="float", minimum=0, description="Allocated amount", nullable=true),
     *             @OA\Property(property="start_date", type="string", format="date", description="Start date"),
     *             @OA\Property(property="end_date", type="string", format="date", description="End date (must be after or equal to start_date)", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Allocation updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee funding allocation updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/EmployeeFundingAllocation")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation or business logic error",
     *
     *         @OA\JsonContent(
     *             oneOf={
     *
     *                 @OA\Schema(
     *                     type="object",
     *
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Validation failed"),
     *                     @OA\Property(property="errors", type="object", description="Validation errors")
     *                 ),
     *
     *                 @OA\Schema(
     *                     type="object",
     *
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Grant position has reached its maximum capacity")
     *                 )
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Allocation not found",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Employee funding allocation not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to update employee funding allocation"),
     *             @OA\Property(property="error", type="string", description="Error message")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $allocation = EmployeeFundingAllocation::findOrFail($id);

            // Validate the request
            $validator = Validator::make($request->all(), [
                'employee_id' => 'sometimes|exists:employees,id',
                'employment_id' => 'sometimes|exists:employments,id',
                'allocation_type' => 'sometimes|string|in:grant,org_funded',
                'position_slot_id' => 'required_if:allocation_type,grant|nullable|exists:position_slots,id',
                'org_funded_id' => 'required_if:allocation_type,org_funded|nullable|exists:org_funded_allocations,id',
                'fte' => 'sometimes|numeric|min:0|max:100',
                'allocated_amount' => 'nullable|numeric|min:0',
                'start_date' => 'sometimes|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();
            $currentUser = Auth::user()->name ?? 'system';

            DB::beginTransaction();

            // If allocation type is being changed, validate type-specific requirements
            if (isset($validated['allocation_type'])) {
                $allocationType = $validated['allocation_type'];

                if ($allocationType === 'grant') {
                    if (empty($validated['position_slot_id'])) {
                        DB::rollBack();

                        return response()->json([
                            'success' => false,
                            'message' => 'position_slot_id is required for grant allocations',
                        ], 422);
                    }

                    // Get the position slot with its grant item for capacity checking
                    $positionSlot = PositionSlot::with('grantItem')->find($validated['position_slot_id']);
                    if (! $positionSlot) {
                        DB::rollBack();

                        return response()->json([
                            'success' => false,
                            'message' => 'Position slot not found',
                        ], 422);
                    }

                    $grantItem = $positionSlot->grantItem;
                    if ($grantItem && $grantItem->grant_position_number > 0) {
                        // Check current active allocations (exclude current allocation)
                        $today = Carbon::today();
                        $currentAllocations = EmployeeFundingAllocation::whereHas('positionSlot', function ($query) use ($grantItem) {
                            $query->where('grant_item_id', $grantItem->id);
                        })
                            ->where('allocation_type', 'grant')
                            ->where('id', '!=', $id) // Exclude current allocation
                            ->where('start_date', '<=', $today)
                            ->where(function ($query) use ($today) {
                                $query->whereNull('end_date')
                                    ->orWhere('end_date', '>=', $today);
                            })
                            ->count();

                        if ($currentAllocations >= $grantItem->grant_position_number) {
                            DB::rollBack();

                            return response()->json([
                                'success' => false,
                                'message' => "Grant position '{$grantItem->grant_position}' has reached its maximum capacity of {$grantItem->grant_position_number} allocations. Currently allocated: {$currentAllocations}",
                            ], 422);
                        }
                    }

                } elseif ($allocationType === 'org_funded') {
                    if (empty($validated['org_funded_id'])) {
                        DB::rollBack();

                        return response()->json([
                            'success' => false,
                            'message' => 'org_funded_id is required for org_funded allocations',
                        ], 422);
                    }

                    // Verify org funded allocation exists
                    $orgFunded = OrgFundedAllocation::find($validated['org_funded_id']);
                    if (! $orgFunded) {
                        DB::rollBack();

                        return response()->json([
                            'success' => false,
                            'message' => 'Org funded allocation not found',
                        ], 422);
                    }
                }

                // Clear the opposite field when changing allocation type
                if ($allocationType === 'grant') {
                    $validated['org_funded_id'] = null;
                } elseif ($allocationType === 'org_funded') {
                    $validated['position_slot_id'] = null;
                }
            }

            // Convert fte from percentage to decimal if provided
            if (isset($validated['fte'])) {
                $validated['fte'] = $validated['fte'] / 100;
            }

            // Add updated_by field
            $validated['updated_by'] = $currentUser;

            // Update the allocation
            $allocation->update($validated);

            DB::commit();

            // Reload with relationships
            $allocation->load([
                'employee:id,staff_id,first_name_en,last_name_en',
                'employment:id,employment_type,start_date',
                'positionSlot.grantItem.grant:id,name,code',
                'orgFunded',
            ]);

            return (new EmployeeFundingAllocationResource($allocation))
                ->additional([
                    'success' => true,
                    'message' => 'Employee funding allocation updated successfully',
                ])
                ->response()
                ->setStatusCode(200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee funding allocation not found',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee funding allocation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/employee-funding-allocations/{id}",
     *     operationId="deleteEmployeeFundingAllocation",
     *     tags={"Employee Funding Allocations"},
     *     summary="Delete employee funding allocation",
     *     description="Deletes an employee funding allocation",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the employee funding allocation",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=204,
     *         description="Allocation deleted successfully"
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Allocation not found")
     * )
     */
    public function destroy($id)
    {
        try {
            $allocation = EmployeeFundingAllocation::findOrFail($id);
            $allocation->delete();

            return response()->json([
                'success' => true,
                'message' => 'Employee funding allocation deleted successfully',
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee funding allocation not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employee funding allocation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/employee-funding-allocations/employee/{employeeId}",
     *     operationId="getEmployeeFundingAllocationsByEmployeeId",
     *     tags={"Employee Funding Allocations"},
     *     summary="Get employee funding allocations by employee ID",
     *     description="Returns all active funding allocations for a specific employee with total effort calculation",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="employeeId",
     *         in="path",
     *         description="Employee ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee funding allocations retrieved successfully"),
     *             @OA\Property(property="employee", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="staff_id", type="string", example="EMP001"),
     *                 @OA\Property(property="first_name_en", type="string", example="John"),
     *                 @OA\Property(property="last_name_en", type="string", example="Doe")
     *             ),
     *             @OA\Property(property="total_allocations", type="integer", example=2),
     *             @OA\Property(property="total_effort", type="number", format="float", example=100.0),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EmployeeFundingAllocation"))
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Employee not found")
     * )
     */
    public function getEmployeeAllocations($employeeId)
    {
        try {
            $employee = Employee::select('id', 'staff_id', 'first_name_en', 'last_name_en')
                ->findOrFail($employeeId);

            $today = Carbon::today();
            $allocations = EmployeeFundingAllocation::with([
                'employment:id,employment_type,start_date,end_date',
                'positionSlot.grantItem.grant:id,name,code',
                'orgFunded.grant:id,name,code',
                'orgFunded.department:id,name',
                'orgFunded.position:id,name',
            ])
                ->where('employee_id', $employeeId)
                ->where('start_date', '<=', $today)
                ->where(function ($query) use ($today) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', $today);
                })
                ->orderBy('start_date', 'desc')
                ->get();

            // Calculate total effort (convert decimal to percentage)
            $totalEffort = $allocations->sum('fte') * 100;

            return response()->json([
                'success' => true,
                'message' => 'Employee funding allocations retrieved successfully',
                'employee' => $employee,
                'total_allocations' => $allocations->count(),
                'total_effort' => $totalEffort,
                'data' => EmployeeFundingAllocationResource::collection($allocations),
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee funding allocations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/employee-funding-allocations/grant-structure",
     *     operationId="getGrantStructure",
     *     tags={"Employee Funding Allocations"},
     *     summary="Get grant structure for allocations",
     *     description="Returns the complete grant structure with grant items, position slots, and org funded allocations for use in funding allocation forms",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant structure retrieved successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="grants", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Research Grant 2024"),
     *                     @OA\Property(property="code", type="string", example="RG2024"),
     *                     @OA\Property(property="grant_items", type="array", @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Senior Researcher"),
     *                         @OA\Property(property="grant_salary", type="number", format="float", example=5000.00),
     *                         @OA\Property(property="grant_benefit", type="number", format="float", example=1000.00),
     *                         @OA\Property(property="position_slots", type="array", @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="slot_number", type="integer", example=1)
     *                         ))
     *                     )),
     *                     @OA\Property(property="org_funded_allocations", type="array", @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="description", type="string", example="Administrative Support"),
     *                         @OA\Property(property="department", type="object",
     *                             @OA\Property(property="id", type="integer"),
     *                             @OA\Property(property="name", type="string")
     *                         ),
     *                         @OA\Property(property="position", type="object",
     *                             @OA\Property(property="id", type="integer"),
     *                             @OA\Property(property="title", type="string")
     *                         )
     *                     ))
     *                 ))
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function getGrantStructure()
    {
        try {
            $grants = \App\Models\Grant::with([
                'grantItems.positionSlots',
                'orgFundedAllocations.department:id,name',
                'orgFundedAllocations.position:id,title,department_id',
            ])->select('id', 'name', 'code')->get();

            $structure = [
                'grants' => $grants->map(function ($grant) {
                    return [
                        'id' => $grant->id,
                        'name' => $grant->name,
                        'code' => $grant->code,
                        'grant_items' => $grant->grantItems->map(function ($item) {
                            return [
                                'id' => $item->id,
                                'name' => $item->grant_position,
                                'grant_salary' => $item->grant_salary,
                                'grant_benefit' => $item->grant_benefit,
                                'grant_fte' => $item->grant_fte,
                                'budgetline_code' => $item->budgetline_code,
                                'grant_position_number' => $item->grant_position_number,
                                'position_slots' => $item->positionSlots->map(function ($slot) {
                                    return [
                                        'id' => $slot->id,
                                        'slot_number' => $slot->slot_number,
                                    ];
                                }),
                            ];
                        }),
                        'org_funded_allocations' => $grant->orgFundedAllocations->map(function ($orgFunded) {
                            return [
                                'id' => $orgFunded->id,
                                'description' => $orgFunded->description,
                                'department' => $orgFunded->department ? [
                                    'id' => $orgFunded->department->id,
                                    'name' => $orgFunded->department->name,
                                ] : null,
                                'position' => $orgFunded->position ? [
                                    'id' => $orgFunded->position->id,
                                    'title' => $orgFunded->position->title,
                                ] : null,
                            ];
                        }),
                    ];
                }),
            ];

            return response()->json([
                'success' => true,
                'message' => 'Grant structure retrieved successfully',
                'data' => $structure,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve grant structure',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/employee-funding-allocations/bulk-deactivate",
     *     operationId="bulkDeactivateEmployeeFundingAllocations",
     *     tags={"Employee Funding Allocations"},
     *     summary="Bulk deactivate employee funding allocations",
     *     description="Deactivates multiple employee funding allocations by setting their end_date to today",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"allocation_ids"},
     *
     *             @OA\Property(
     *                 property="allocation_ids",
     *                 type="array",
     *                 description="Array of allocation IDs to deactivate",
     *
     *                 @OA\Items(type="integer", example=1)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Allocations deactivated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee funding allocations deactivated successfully"),
     *             @OA\Property(property="deactivated_count", type="integer", example=3)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object", description="Validation errors")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function bulkDeactivate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'allocation_ids' => 'required|array|min:1',
                'allocation_ids.*' => 'integer|exists:employee_funding_allocations,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $currentUser = Auth::user()->name ?? 'system';
            $today = Carbon::today();

            $updatedCount = EmployeeFundingAllocation::whereIn('id', $request->allocation_ids)
                ->where(function ($query) use ($today) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>', $today);
                })
                ->update([
                    'end_date' => $today,
                    'updated_by' => $currentUser,
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Employee funding allocations deactivated successfully',
                'deactivated_count' => $updatedCount,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate employee funding allocations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/employee-funding-allocations/employee/{employeeId}",
     *     operationId="updateEmployeeFundingAllocations",
     *     tags={"Employee Funding Allocations"},
     *     summary="Update all employee funding allocations",
     *     description="Replaces all existing active allocations for an employee with new allocations. Validates that total effort equals 100% and handles both grant and org_funded allocations.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="employeeId",
     *         in="path",
     *         description="Employee ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"employment_id", "start_date", "allocations"},
     *
     *             @OA\Property(property="employment_id", type="integer", description="ID of the employment"),
     *             @OA\Property(property="start_date", type="string", format="date", description="Start date for new allocations"),
     *             @OA\Property(property="end_date", type="string", format="date", description="End date for new allocations", nullable=true),
     *             @OA\Property(
     *                 property="allocations",
     *                 type="array",
     *                 description="Array of new funding allocations (total effort must equal 100%)",
     *
     *                 @OA\Items(
     *                     type="object",
     *                     required={"allocation_type", "fte"},
     *
     *                     @OA\Property(property="allocation_type", type="string", enum={"grant", "org_funded"}, description="Type of allocation"),
     *                     @OA\Property(property="position_slot_id", type="integer", description="Position slot ID (required for grant allocations)", nullable=true),
     *                     @OA\Property(property="org_funded_id", type="integer", description="Org funded allocation ID (required for org_funded allocations)", nullable=true),
     *                     @OA\Property(property="fte", type="number", format="float", minimum=0, maximum=100, description="FTE as percentage (0-100)"),
     *                     @OA\Property(property="allocated_amount", type="number", format="float", minimum=0, description="Allocated amount", nullable=true)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Allocations updated successfully",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee funding allocations updated successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EmployeeFundingAllocation")),
     *             @OA\Property(property="total_created", type="integer", example=2)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation or business logic error",
     *
     *         @OA\JsonContent(
     *             oneOf={
     *
     *                 @OA\Schema(
     *                     type="object",
     *
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Validation failed"),
     *                     @OA\Property(property="errors", type="object", description="Validation errors")
     *                 ),
     *
     *                 @OA\Schema(
     *                     type="object",
     *
     *                     @OA\Property(property="success", type="boolean", example=false),
     *                     @OA\Property(property="message", type="string", example="Total effort must equal 100%"),
     *                     @OA\Property(property="current_total", type="number", example=85.5)
     *                 )
     *             }
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Employee not found"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function updateEmployeeAllocations(Request $request, $employeeId)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'employment_id' => 'required|exists:employments,id',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'allocations' => 'required|array|min:1',
                'allocations.*.allocation_type' => 'required|string|in:grant,org_funded',
                'allocations.*.position_slot_id' => 'required_if:allocations.*.allocation_type,grant|nullable|exists:position_slots,id',
                'allocations.*.org_funded_id' => 'required_if:allocations.*.allocation_type,org_funded|nullable|exists:org_funded_allocations,id',
                'allocations.*.fte' => 'required|numeric|min:0|max:100',
                'allocations.*.allocated_amount' => 'nullable|numeric|min:0',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();
            $currentUser = Auth::user()->name ?? 'system';

            // Verify employee exists
            $employee = Employee::findOrFail($employeeId);

            // Validate that total effort equals 100%
            $totalEffort = array_sum(array_column($validated['allocations'], 'fte'));
            if ($totalEffort != 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total effort must equal 100%',
                    'current_total' => $totalEffort,
                ], 422);
            }

            DB::beginTransaction();

            $today = Carbon::today();

            // Deactivate all existing active allocations for this employee and employment
            EmployeeFundingAllocation::where('employee_id', $employeeId)
                ->where('employment_id', $validated['employment_id'])
                ->where('start_date', '<=', $today)
                ->where(function ($query) use ($today) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', $today);
                })
                ->update([
                    'end_date' => $today->subDay(), // End yesterday to avoid overlap
                    'updated_by' => $currentUser,
                    'updated_at' => now(),
                ]);

            // Create new allocations
            $createdAllocations = [];
            foreach ($validated['allocations'] as $allocationData) {
                $allocation = EmployeeFundingAllocation::create([
                    'employee_id' => $employeeId,
                    'employment_id' => $validated['employment_id'],
                    'position_slot_id' => $allocationData['allocation_type'] === 'grant' ? $allocationData['position_slot_id'] : null,
                    'org_funded_id' => $allocationData['allocation_type'] === 'org_funded' ? $allocationData['org_funded_id'] : null,
                    'fte' => $allocationData['fte'] / 100, // Convert percentage to decimal
                    'allocation_type' => $allocationData['allocation_type'],
                    'allocated_amount' => $allocationData['allocated_amount'] ?? null,
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'] ?? null,
                    'created_by' => $currentUser,
                    'updated_by' => $currentUser,
                ]);

                $createdAllocations[] = $allocation;
            }

            DB::commit();

            // Load the created allocations with relationships
            $allocationsWithRelations = EmployeeFundingAllocation::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'employment:id,employment_type,start_date,end_date',
                'positionSlot.grantItem.grant:id,name,code',
                'orgFunded.grant:id,name,code',
                'orgFunded.department:id,name',
                'orgFunded.position:id,name',
            ])->whereIn('id', collect($createdAllocations)->pluck('id'))->get();

            return response()->json([
                'success' => true,
                'message' => 'Employee funding allocations updated successfully',
                'data' => EmployeeFundingAllocationResource::collection($allocationsWithRelations),
                'total_created' => count($createdAllocations),
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Employee not found',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee funding allocations',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
