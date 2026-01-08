<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeFundingAllocationResource;
use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
use App\Models\Grant;
use App\Models\GrantItem;
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
                'employment:id,employment_type,start_date,end_date,department_id,position_id',
                'employment.department:id,name',
                'employment.position:id,title',
                'grantItem.grant:id,name,code',
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
                'grantItem.grant:id,name,code',
            ])
                ->where('grant_item_id', $grantItemId)
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
                'allocations.*.allocation_type' => 'sometimes|string|in:grant',
                'allocations.*.grant_item_id' => 'required|exists:grant_items,id',
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
                    $allocationType = 'grant';

                    if (empty($allocationData['grant_item_id'])) {
                        $errors[] = "Allocation #{$index}: grant_item_id is required for grant allocations";

                        continue;
                    }

                    // Get the grant item for capacity checking
                    $grantItem = GrantItem::find($allocationData['grant_item_id']);
                    if (! $grantItem) {
                        $errors[] = "Allocation #{$index}: Grant item not found";

                        continue;
                    }

                    if ($grantItem->grant_position_number > 0) {
                        // Check current active allocations (based on dates)
                        $currentAllocations = EmployeeFundingAllocation::where('grant_item_id', $grantItem->id)
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

                    $existingAllocation->where('grant_item_id', $allocationData['grant_item_id']);

                    if ($existingAllocation->exists()) {
                        $errors[] = "Allocation #{$index}: Already exists for this employee, employment, and {$allocationType} allocation";

                        continue;
                    }

                    // Create the allocation
                    $allocation = EmployeeFundingAllocation::create([
                        'employee_id' => $validated['employee_id'],
                        'employment_id' => $validated['employment_id'],
                        'grant_item_id' => $allocationData['grant_item_id'],
                        'fte' => $allocationData['fte'] / 100, // Convert percentage to decimal
                        'allocation_type' => 'grant',
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
                'grantItem.grant:id,name,code',
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
     *     description="Returns a single employee funding allocation with related data including employee, employment, grantItem, and grant relationships",
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
                'employment:id,employment_type,start_date,end_date,department_id,position_id',
                'employment.department:id,name',
                'employment.position:id,title',
                'grantItem.grant:id,name,code',
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
                'allocation_type' => 'sometimes|string|in:grant',
                'grant_item_id' => 'required|exists:grant_items,id',
                'grant_id' => 'nullable',
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

            $validated['allocation_type'] = 'grant';

            if (empty($validated['grant_item_id'])) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'grant_item_id is required for grant allocations',
                ], 422);
            }

            $grantItem = GrantItem::find($validated['grant_item_id']);
            if (! $grantItem) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Grant item not found',
                ], 422);
            }

            if ($grantItem->grant_position_number > 0) {
                $today = Carbon::today();
                $currentAllocations = EmployeeFundingAllocation::where('grant_item_id', $grantItem->id)
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
                'grantItem.grant:id,name,code',
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
                'employment:id,employment_type,start_date,end_date,department_id,position_id',
                'employment.department:id,name',
                'employment.position:id,title',
                'grantItem.grant:id,name,code',
                'grant:id,name,code',
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
                'grantItems',
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
                'allocations.*.grant_item_id' => 'required_if:allocations.*.allocation_type,grant|nullable|exists:grant_items,id',
                'allocations.*.grant_id' => 'required_if:allocations.*.allocation_type,org_funded|nullable|exists:grants,id',
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
                    'grant_item_id' => $allocationData['grant_item_id'],
                    'grant_id' => null,
                    'fte' => $allocationData['fte'] / 100, // Convert percentage to decimal
                    'allocation_type' => 'grant',
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
                'employment:id,employment_type,start_date,end_date,department_id,position_id',
                'employment.department:id,name',
                'employment.position:id,title',
                'grantItem.grant:id,name,code',
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

    /**
     * @OA\Post(
     *     path="/uploads/employee-funding-allocation",
     *     summary="Upload employee funding allocation data from Excel file",
     *     description="Upload an Excel file containing employee funding allocation records. The import is processed asynchronously in the background with chunk processing. Existing allocations will be updated, new ones will be created.",
     *     tags={"Employee Funding Allocations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         content={
     *
     *             @OA\MediaType(
     *                 mediaType="multipart/form-data",
     *
     *                 @OA\Schema(
     *
     *                     @OA\Property(
     *                         property="file",
     *                         type="string",
     *                         format="binary",
     *                         description="Excel file to upload (xlsx, xls, csv)"
     *                     )
     *                 )
     *             )
     *         }
     *     ),
     *
     *     @OA\Response(
     *         response=202,
     *         description="Employee funding allocation data import started successfully"
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Import failed")
     * )
     */
    public function upload(Request $request)
    {
        try {
            // Validate file
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        }

        $file = $request->file('file');

        try {
            // Generate unique import ID
            $importId = 'funding_allocation_import_'.uniqid();

            // Get authenticated user
            $userId = auth()->id();

            // Queue the import
            $import = new \App\Imports\EmployeeFundingAllocationsImport($importId, $userId);
            $import->queue($file);

            return response()->json([
                'success' => true,
                'message' => 'Employee funding allocation import started successfully. You will receive a notification when the import is complete.',
                'data' => [
                    'import_id' => $importId,
                    'status' => 'processing',
                ],
            ], 202);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start employee funding allocation import',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/downloads/employee-funding-allocation-template",
     *     summary="Download employee funding allocation import template",
     *     description="Downloads an Excel template for bulk employee funding allocation import with validation rules and sample data",
     *     operationId="downloadEmployeeFundingAllocationTemplate",
     *     tags={"Employee Funding Allocations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Template file downloaded successfully"),
     *     @OA\Response(response=500, description="Failed to generate template")
     * )
     */
    public function downloadTemplate()
    {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Funding Allocation Import');

            // ============================================
            // SECTION 1: DEFINE HEADERS
            // ============================================
            $headers = [
                'staff_id',
                'employment_id',
                'grant_item_id',
                'fte',
                'allocation_type',
                'allocated_amount',
                'salary_type',
                'status',
                'start_date',
                'end_date',
            ];

            // ============================================
            // SECTION 2: DEFINE VALIDATION RULES
            // ============================================
            $validationRules = [
                'String - NOT NULL - Employee staff ID (must exist in system)',
                'Integer - NULLABLE - Employment ID (optional, will auto-link to active employment if not provided)',
                'Integer - NOT NULL - Grant item ID (must exist in grant_items table)',
                'Decimal (0-100) - NOT NULL - FTE percentage (e.g., 50 for 50%, 100 for 100%)',
                'String - NOT NULL - Values: grant, org_funded',
                'Decimal(15,2) - NULLABLE - Pre-calculated allocated amount (auto-calculated if empty)',
                'String - NULLABLE - Values: probation_salary, pass_probation_salary (auto-detected if empty)',
                'String - NULLABLE - Values: active, historical, terminated (default: active)',
                'Date (YYYY-MM-DD) - NOT NULL - Allocation start date',
                'Date (YYYY-MM-DD) - NULLABLE - Allocation end date',
            ];

            // ============================================
            // SECTION 3: WRITE HEADERS (Row 1)
            // ============================================
            $col = 1;
            foreach ($headers as $header) {
                $cell = $sheet->getCellByColumnAndRow($col, 1);
                $cell->setValue($header);

                // Style header
                $cell->getStyle()->getFont()->setBold(true)->setSize(11);
                $cell->getStyle()->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('4472C4');
                $cell->getStyle()->getFont()->getColor()->setRGB('FFFFFF');
                $cell->getStyle()->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                $col++;
            }

            // ============================================
            // SECTION 4: WRITE VALIDATION RULES (Row 2)
            // ============================================
            $col = 1;
            foreach ($validationRules as $rule) {
                $cell = $sheet->getCellByColumnAndRow($col, 2);
                $cell->setValue($rule);

                // Style validation row
                $cell->getStyle()->getFont()->setItalic(true)->setSize(9);
                $cell->getStyle()->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E7E6E6');
                $cell->getStyle()->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

                $col++;
            }

            // Set row height for validation rules
            $sheet->getRowDimension(2)->setRowHeight(60);

            // ============================================
            // SECTION 5: ADD SAMPLE DATA (Rows 3-5)
            // ============================================
            $sampleData = [
                ['EMP001', '', '1', '100', 'grant', '', '', 'active', '2025-01-01', ''],
                ['EMP002', '15', '2', '60', 'grant', '30000.00', 'probation_salary', 'active', '2025-01-15', '2025-12-31'],
                ['EMP002', '15', '3', '40', 'org_funded', '20000.00', 'pass_probation_salary', 'active', '2025-01-15', '2025-12-31'],
            ];

            $row = 3;
            foreach ($sampleData as $data) {
                $col = 1;
                foreach ($data as $value) {
                    $sheet->getCellByColumnAndRow($col, $row)->setValue($value);
                    $col++;
                }
                $row++;
            }

            // ============================================
            // SECTION 6: SET COLUMN WIDTHS
            // ============================================
            $columnWidths = [
                'A' => 15,  // staff_id
                'B' => 15,  // employment_id
                'C' => 18,  // grant_item_id
                'D' => 12,  // fte
                'E' => 18,  // allocation_type
                'F' => 18,  // allocated_amount
                'G' => 22,  // salary_type
                'H' => 15,  // status
                'I' => 15,  // start_date
                'J' => 15,  // end_date
            ];

            foreach ($columnWidths as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }

            // ============================================
            // SECTION 7: ADD INSTRUCTIONS SHEET
            // ============================================
            $instructionsSheet = $spreadsheet->createSheet();
            $instructionsSheet->setTitle('Instructions');

            $instructions = [
                ['Employee Funding Allocation Import Template - Instructions'],
                [''],
                ['IMPORTANT NOTES:'],
                ['1. Required Fields (Cannot be empty):'],
                ['   - staff_id: Employee staff ID (must exist in system)'],
                ['   - grant_item_id: Grant item ID from grant_items table'],
                ['   - fte: FTE percentage (0-100, e.g., 50 for 50%, 100 for 100%)'],
                ['   - start_date: Allocation start date (YYYY-MM-DD format)'],
                [''],
                ['2. Date Format: All dates must be in YYYY-MM-DD format (e.g., 2025-01-15)'],
                [''],
                ['3. FTE (Full-Time Equivalent):'],
                ['   - Enter as percentage without % symbol (e.g., 100 for full-time, 50 for half-time)'],
                ['   - For split funding: Create multiple rows for the same employee'],
                ['   - Example: Employee 60% on Grant A + 40% on Grant B = 2 rows'],
                [''],
                ['4. Grant Item ID:'],
                ['   - Must be a valid ID from the grant_items table'],
                ['   - Contact your administrator for the list of available grant items'],
                ['   - Each grant item represents a specific grant position/funding source'],
                [''],
                ['5. Foreign Keys (Must exist in database):'],
                ['   - staff_id: Must match an existing employee'],
                ['   - grant_item_id: Must be a valid grant item ID'],
                ['   - System will verify employment exists for the employee'],
                [''],
                ['6. Allocation Logic:'],
                ['   - If allocation exists for employee+grant_item: UPDATED'],
                ['   - If allocation does not exist: CREATED'],
                ['   - allocated_amount is auto-calculated based on FTE and salary'],
                [''],
                ['7. Validation Rules:'],
                ['   - FTE must be between 0 and 100'],
                ['   - start_date is required'],
                ['   - end_date is optional (leave empty for ongoing allocation)'],
                ['   - Employee must have active employment record'],
                [''],
                ['8. Example Scenarios:'],
                ['   - Single funding: 1 row with FTE=100'],
                ['   - Split funding (60/40): 2 rows, one with FTE=60, another with FTE=40'],
                ['   - Split funding (50/30/20): 3 rows with respective FTE values'],
                [''],
                ['9. Best Practices:'],
                ['   - Keep total FTE per employee = 100% (system validates this)'],
                ['   - Use consistent date formats'],
                ['   - Verify grant_item_id before uploading'],
                ['   - Test with small batch first'],
                [''],
                ['10. After Upload:'],
                ['   - You will receive a notification when import completes'],
                ['   - Check notification for success/error summary'],
                ['   - Review created/updated allocations in the system'],
            ];

            $row = 1;
            foreach ($instructions as $instruction) {
                $instructionsSheet->setCellValue("A{$row}", $instruction[0]);
                if ($row === 1) {
                    $instructionsSheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
                } elseif ($row === 3 || strpos($instruction[0], ':') !== false) {
                    $instructionsSheet->getStyle("A{$row}")->getFont()->setBold(true);
                }
                $row++;
            }

            $instructionsSheet->getColumnDimension('A')->setWidth(100);

            // Set active sheet back to main sheet
            $spreadsheet->setActiveSheetIndex(0);

            // ============================================
            // SECTION 8: GENERATE AND DOWNLOAD FILE
            // ============================================
            $filename = 'employee_funding_allocation_import_template_'.date('Y-m-d_His').'.xlsx';
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            $tempFile = tempnam(sys_get_temp_dir(), 'funding_allocation_template_');
            $writer->save($tempFile);

            $headers = [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'max-age=0',
            ];

            return response()->download($tempFile, $filename, $headers)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate employee funding allocation template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
