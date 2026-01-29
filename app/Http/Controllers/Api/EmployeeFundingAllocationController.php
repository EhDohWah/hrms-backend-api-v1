<?php

namespace App\Http\Controllers\Api;

use App\Exports\EmployeeFundingAllocationTemplateExport;
use App\Exports\GrantItemsReferenceExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeFundingAllocationResource;
use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
use App\Models\Employment;
use App\Models\Grant;
use App\Models\GrantItem;
use App\Services\EmployeeFundingAllocationService;
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
     * Constructor with dependency injection for salary calculation service
     */
    public function __construct(
        private readonly EmployeeFundingAllocationService $employeeFundingAllocationService
    ) {}

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
                'employment:id,start_date,end_probation_date,department_id,position_id',
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
    public function byGrantItem($grantItemId)
    {
        try {
            // Get all allocations for this grant item
            $allocations = EmployeeFundingAllocation::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'employment:id,start_date,end_probation_date',
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
            // Note: replace_allocation_ids allows partial updates by specifying which allocations to replace
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
                'replace_allocation_ids' => 'nullable|array',
                'replace_allocation_ids.*' => 'exists:employee_funding_allocations,id',
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

            // Fetch the employment record for salary context calculation
            $employment = Employment::find($validated['employment_id']);
            if (! $employment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employment record not found',
                ], 404);
            }

            // Validate that employment has salary defined (required for allocation calculation)
            if (is_null($employment->pass_probation_salary) && is_null($employment->probation_salary)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employment must have a salary defined before funding allocations can be created. Please update the employment record with salary information first.',
                ], 422);
            }

            // Determine effective date for salary calculation
            $effectiveDate = Carbon::parse($validated['start_date']);
            $today = Carbon::today();

            // ============================================================
            // SMART FTE VALIDATION
            // ============================================================
            // Instead of requiring the request to equal 100%, we calculate
            // what the TOTAL will be after this operation:
            //   projected_total = existing_to_keep + new_allocations
            //
            // This allows partial updates like:
            //   - Adding 40% when 60% already exists (total = 100%)
            //   - Replacing 80% with two 40% allocations (total = 100%)
            // ============================================================

            // Step 1: Get all currently active allocations for this employment
            $existingAllocations = EmployeeFundingAllocation::where('employee_id', $validated['employee_id'])
                ->where('employment_id', $validated['employment_id'])
                ->where('status', 'active')
                ->where('start_date', '<=', $today)
                ->where(function ($query) use ($today) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', $today);
                })
                ->get();

            // Step 2: Determine which allocations will be replaced (marked historical)
            $replaceIds = $validated['replace_allocation_ids'] ?? [];

            // Validate that replace_allocation_ids belong to this employment
            if (! empty($replaceIds)) {
                $invalidIds = collect($replaceIds)->diff($existingAllocations->pluck('id'));
                if ($invalidIds->isNotEmpty()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Some replace_allocation_ids do not belong to this employment or are not active',
                        'invalid_ids' => $invalidIds->values(),
                    ], 422);
                }
            }

            // Step 3: Calculate FTE of allocations that will remain (not being replaced)
            $allocationsToKeep = $existingAllocations->whereNotIn('id', $replaceIds);
            $existingFteToKeep = $allocationsToKeep->sum('fte') * 100; // Convert from decimal

            // Step 4: Calculate FTE of new allocations being added
            $newFte = array_sum(array_column($validated['allocations'], 'fte'));

            // Step 5: Calculate projected total after this operation
            $projectedTotal = $existingFteToKeep + $newFte;

            // Step 6: Validate with floating-point tolerance (handles 33.33 + 33.33 + 33.34 = 100)
            $tolerance = 0.01;
            if (abs($projectedTotal - 100) > $tolerance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total FTE must equal 100% after this operation',
                    'breakdown' => [
                        'existing_allocations_count' => $existingAllocations->count(),
                        'allocations_being_replaced' => count($replaceIds),
                        'existing_fte_to_keep' => round($existingFteToKeep, 2),
                        'new_fte_being_added' => round($newFte, 2),
                        'projected_total' => round($projectedTotal, 2),
                        'required_total' => 100.00,
                        'difference' => round($projectedTotal - 100, 2),
                    ],
                ], 422);
            }

            DB::beginTransaction();

            // Step 7: Mark replaced allocations as 'historical'
            if (! empty($replaceIds)) {
                $endDate = Carbon::parse($validated['start_date'])->subDay();
                EmployeeFundingAllocation::whereIn('id', $replaceIds)
                    ->update([
                        'status' => 'historical',
                        'end_date' => $endDate,
                        'updated_by' => $currentUser,
                    ]);
            }

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

                    // Calculate salary context using the service
                    // This automatically determines probation vs post-probation salary based on effective date
                    $fteDecimal = $allocationData['fte'] / 100;
                    $salaryContext = $this->employeeFundingAllocationService->deriveSalaryContext(
                        $employment,
                        $fteDecimal,
                        $effectiveDate
                    );

                    // Create the allocation with auto-calculated salary
                    $allocation = EmployeeFundingAllocation::create(array_merge([
                        'employee_id' => $validated['employee_id'],
                        'employment_id' => $validated['employment_id'],
                        'grant_item_id' => $allocationData['grant_item_id'],
                        'fte' => $fteDecimal,
                        'allocation_type' => 'grant',
                        'status' => 'active',
                        'start_date' => $validated['start_date'],
                        'end_date' => $validated['end_date'] ?? null,
                        'created_by' => $currentUser,
                        'updated_by' => $currentUser,
                    ], $salaryContext));  // Merge salary_type and allocated_amount from service

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
                'employment:id,start_date',
                'grantItem.grant:id,name,code',
            ])->whereIn('id', collect($createdAllocations)->pluck('id'))->get();

            $response = [
                'success' => true,
                'message' => 'Employee funding allocations created successfully',
                'data' => $allocationsWithRelations,
                'total_created' => count($createdAllocations),
                'salary_info' => [
                    'salary_type_used' => $employment->getSalaryTypeForDate($effectiveDate),
                    'salary_amount_used' => $employment->getSalaryAmountForDate($effectiveDate),
                    'is_probation_period' => $employment->pass_probation_date
                        ? $effectiveDate->lt(Carbon::parse($employment->pass_probation_date))
                        : false,
                    'pass_probation_date' => $employment->pass_probation_date,
                ],
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
     * @OA\Post(
     *     path="/employee-funding-allocations/calculate-preview",
     *     operationId="calculateAllocationPreview",
     *     tags={"Employee Funding Allocations"},
     *     summary="Calculate allocation amount preview",
     *     description="Calculates the allocated amount for a funding allocation without persisting. Uses the employment's salary (probation or post-probation) based on the effective date. Useful for real-time UI feedback before saving.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"employment_id", "fte"},
     *
     *             @OA\Property(property="employment_id", type="integer", description="ID of the employment record"),
     *             @OA\Property(property="fte", type="number", format="float", minimum=0, maximum=100, description="FTE as percentage (0-100)"),
     *             @OA\Property(property="effective_date", type="string", format="date", description="Date to determine which salary to use (defaults to today)", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Calculation successful",
     *
     *         @OA\JsonContent(
     *             type="object",
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="fte_decimal", type="number", example=0.5),
     *                 @OA\Property(property="fte_percentage", type="number", example=50),
     *                 @OA\Property(property="allocated_amount", type="number", example=25000.00),
     *                 @OA\Property(property="salary_type", type="string", example="probation_salary"),
     *                 @OA\Property(property="salary_amount", type="number", example=50000.00),
     *                 @OA\Property(property="is_probation_period", type="boolean", example=true),
     *                 @OA\Property(property="pass_probation_date", type="string", format="date", nullable=true)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Employment not found"),
     *     @OA\Response(response=422, description="Validation error or no salary defined"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function calculatePreview(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employment_id' => 'required|exists:employments,id',
                'fte' => 'required|numeric|min:0|max:100',
                'effective_date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $validated = $validator->validated();

            // Fetch employment record
            $employment = Employment::find($validated['employment_id']);
            if (! $employment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employment record not found',
                ], 404);
            }

            // Validate salary is defined
            if (is_null($employment->pass_probation_salary) && is_null($employment->probation_salary)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employment must have a salary defined before allocation can be calculated.',
                    'data' => [
                        'employment_id' => $employment->id,
                        'has_probation_salary' => ! is_null($employment->probation_salary),
                        'has_pass_probation_salary' => ! is_null($employment->pass_probation_salary),
                    ],
                ], 422);
            }

            // Determine effective date
            $effectiveDate = isset($validated['effective_date'])
                ? Carbon::parse($validated['effective_date'])
                : Carbon::today();

            // Calculate using the service
            $fteDecimal = $validated['fte'] / 100;
            $salaryContext = $this->employeeFundingAllocationService->deriveSalaryContext(
                $employment,
                $fteDecimal,
                $effectiveDate
            );

            // Determine if in probation period
            $isProbationPeriod = $employment->pass_probation_date
                ? $effectiveDate->lt(Carbon::parse($employment->pass_probation_date))
                : false;

            return response()->json([
                'success' => true,
                'message' => 'Allocation preview calculated successfully',
                'data' => [
                    'fte_decimal' => $fteDecimal,
                    'fte_percentage' => $validated['fte'],
                    'allocated_amount' => $salaryContext['allocated_amount'],
                    'salary_type' => $salaryContext['salary_type'],
                    'salary_amount' => $employment->getSalaryAmountForDate($effectiveDate),
                    'is_probation_period' => $isProbationPeriod,
                    'pass_probation_date' => $employment->pass_probation_date,
                    'effective_date' => $effectiveDate->toDateString(),
                ],
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate allocation preview',
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
                'employment:id,start_date,end_probation_date,department_id,position_id',
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
                $newFteDecimal = $validated['fte'] / 100;

                // ============================================================
                // VALIDATE TOTAL FTE AFTER UPDATE
                // ============================================================
                // Ensure the total FTE for this employment remains 100% after update
                // Total = (all active allocations) - (this allocation's old FTE) + (new FTE)
                // ============================================================
                $today = Carbon::today();
                $otherAllocations = EmployeeFundingAllocation::where('employment_id', $allocation->employment_id)
                    ->where('id', '!=', $allocation->id)
                    ->where('status', 'active')
                    ->where('start_date', '<=', $today)
                    ->where(function ($query) use ($today) {
                        $query->whereNull('end_date')
                            ->orWhere('end_date', '>=', $today);
                    })
                    ->sum('fte');

                $projectedTotal = ($otherAllocations + $newFteDecimal) * 100;
                $tolerance = 0.01;

                if (abs($projectedTotal - 100) > $tolerance) {
                    DB::rollBack();

                    return response()->json([
                        'success' => false,
                        'message' => 'Total FTE must equal 100% after this update',
                        'breakdown' => [
                            'other_allocations_fte' => round($otherAllocations * 100, 2),
                            'new_fte_for_this_allocation' => round($newFteDecimal * 100, 2),
                            'projected_total' => round($projectedTotal, 2),
                            'required_total' => 100.00,
                        ],
                    ], 422);
                }

                $validated['fte'] = $newFteDecimal;
            }

            // Add updated_by field
            $validated['updated_by'] = $currentUser;

            // Update the allocation
            $allocation->update($validated);

            DB::commit();

            // Reload with relationships
            $allocation->load([
                'employee:id,staff_id,first_name_en,last_name_en',
                'employment:id,start_date',
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
     * Batch update employee funding allocations.
     *
     * Atomically processes updates, creates, and deletes for an employment.
     * Validates that the final total FTE equals 100% before committing.
     *
     * @OA\Put(
     *     path="/employee-funding-allocations/batch",
     *     operationId="batchUpdateEmployeeFundingAllocations",
     *     tags={"Employee Funding Allocations"},
     *     summary="Batch update employee funding allocations",
     *     description="Atomically process updates, creates, and deletes. Validates total FTE = 100%.",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"employee_id", "employment_id"},
     *
     *             @OA\Property(property="employee_id", type="integer"),
     *             @OA\Property(property="employment_id", type="integer"),
     *             @OA\Property(property="updates", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="creates", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="deletes", type="array", @OA\Items(type="integer"))
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Batch update successful"),
     *     @OA\Response(response=422, description="Validation error or FTE != 100%"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function batchUpdate(Request $request)
    {
        // Step 1: Validate request structure
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|integer|exists:employees,id',
            'employment_id' => 'required|integer|exists:employments,id',
            'updates' => 'nullable|array',
            'updates.*.id' => 'required|integer|exists:employee_funding_allocations,id',
            'updates.*.grant_item_id' => 'required|integer|exists:grant_items,id',
            'updates.*.fte' => 'required|numeric|min:1|max:100',
            'creates' => 'nullable|array',
            'creates.*.grant_item_id' => 'required|integer|exists:grant_items,id',
            'creates.*.fte' => 'required|numeric|min:1|max:100',
            'deletes' => 'nullable|array',
            'deletes.*' => 'integer|exists:employee_funding_allocations,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $employeeId = $request->input('employee_id');
        $employmentId = $request->input('employment_id');
        $updates = $request->input('updates', []);
        $creates = $request->input('creates', []);
        $deletes = $request->input('deletes', []);

        // Step 2: Calculate projected total FTE after all operations
        // Formula: untouched_allocations + updates + creates = 100%
        $currentAllocations = EmployeeFundingAllocation::where('employee_id', $employeeId)
            ->where('employment_id', $employmentId)
            ->where('status', 'active')
            ->get()
            ->keyBy('id');

        $updateIds = collect($updates)->pluck('id')->toArray();
        $currentAllocationIds = $currentAllocations->keys()->toArray();

        // Validate that all update IDs belong to active allocations for this employee/employment
        $invalidUpdateIds = array_diff($updateIds, $currentAllocationIds);
        if (! empty($invalidUpdateIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Some allocation IDs to update are not active or do not belong to this employee/employment',
                'invalid_ids' => array_values($invalidUpdateIds),
            ], 422);
        }

        // Validate that all delete IDs belong to active allocations for this employee/employment
        $invalidDeleteIds = array_diff($deletes, $currentAllocationIds);
        if (! empty($invalidDeleteIds)) {
            return response()->json([
                'success' => false,
                'message' => 'Some allocation IDs to delete are not active or do not belong to this employee/employment',
                'invalid_ids' => array_values($invalidDeleteIds),
            ], 422);
        }

        // FTE from allocations that won't be modified or deleted
        $untouchedFte = 0;
        foreach ($currentAllocations as $allocation) {
            $isBeingUpdated = in_array($allocation->id, $updateIds);
            $isBeingDeleted = in_array($allocation->id, $deletes);

            if (! $isBeingUpdated && ! $isBeingDeleted) {
                $untouchedFte += (float) $allocation->fte * 100;
            }
        }

        // FTE from updates and creates (already in percentage)
        $updatesFte = collect($updates)->sum('fte');
        $createsFte = collect($creates)->sum('fte');

        $projectedTotal = $untouchedFte + $updatesFte + $createsFte;

        // Step 3: Validate total = 100% with tolerance for floating-point
        $tolerance = 0.01;
        if (abs($projectedTotal - 100) > $tolerance) {
            return response()->json([
                'success' => false,
                'message' => 'Total FTE must equal 100%',
                'breakdown' => [
                    'untouched_fte' => round($untouchedFte, 2),
                    'updates_fte' => round($updatesFte, 2),
                    'creates_fte' => round($createsFte, 2),
                    'projected_total' => round($projectedTotal, 2),
                    'required_total' => 100,
                ],
            ], 422);
        }

        // Step 4: Get employment for salary calculation
        $employment = Employment::find($employmentId);
        if (! $employment) {
            return response()->json([
                'success' => false,
                'message' => 'Employment not found',
            ], 404);
        }

        // Determine salary type and base salary
        $today = Carbon::today();
        $isProbation = ! $employment->end_probation_date
            || Carbon::parse($employment->end_probation_date)->isAfter($today);

        $salaryType = $isProbation ? 'probation_salary' : 'pass_probation_salary';
        $baseSalary = $isProbation
            ? (float) ($employment->probation_salary ?? 0)
            : (float) ($employment->pass_probation_salary ?? 0);

        $userName = Auth::user()->name ?? 'system';

        // Step 5: Execute all operations in a single transaction
        DB::beginTransaction();

        try {
            $deletedCount = 0;
            $updatedCount = 0;
            $createdCount = 0;

            // Process deletes - mark as historical for audit trail
            foreach ($deletes as $deleteId) {
                $allocation = $currentAllocations->get($deleteId);
                if ($allocation) {
                    $allocation->update([
                        'status' => 'historical',
                        'end_date' => $today->toDateString(),
                        'updated_by' => $userName,
                    ]);
                    $deletedCount++;
                }
            }

            // Process updates
            foreach ($updates as $updateData) {
                $allocation = $currentAllocations->get($updateData['id']);
                if ($allocation) {
                    $fteDecimal = (float) $updateData['fte'] / 100;
                    $allocatedAmount = $baseSalary * $fteDecimal;

                    $allocation->update([
                        'grant_item_id' => $updateData['grant_item_id'],
                        'fte' => $fteDecimal,
                        'allocated_amount' => $allocatedAmount,
                        'salary_type' => $salaryType,
                        'updated_by' => $userName,
                    ]);
                    $updatedCount++;
                }
            }

            // Process creates
            foreach ($creates as $createData) {
                $fteDecimal = (float) $createData['fte'] / 100;
                $allocatedAmount = $baseSalary * $fteDecimal;

                EmployeeFundingAllocation::create([
                    'employee_id' => $employeeId,
                    'employment_id' => $employmentId,
                    'grant_item_id' => $createData['grant_item_id'],
                    'fte' => $fteDecimal,
                    'allocation_type' => 'grant',
                    'allocated_amount' => $allocatedAmount,
                    'salary_type' => $salaryType,
                    'status' => 'active',
                    'start_date' => $today->toDateString(),
                    'end_date' => null,
                    'created_by' => $userName,
                    'updated_by' => $userName,
                ]);
                $createdCount++;
            }

            DB::commit();

            // Step 6: Return fresh allocations
            $freshAllocations = EmployeeFundingAllocation::with(['grantItem.grant'])
                ->where('employee_id', $employeeId)
                ->where('employment_id', $employmentId)
                ->where('status', 'active')
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Allocations updated successfully',
                'data' => [
                    'allocations' => EmployeeFundingAllocationResource::collection($freshAllocations),
                    'summary' => [
                        'deleted_count' => $deletedCount,
                        'updated_count' => $updatedCount,
                        'created_count' => $createdCount,
                        'total_fte' => round($freshAllocations->sum('fte') * 100, 2),
                    ],
                ],
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Batch update failed',
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
    public function employeeAllocations($employeeId)
    {
        try {
            $employee = Employee::select('id', 'staff_id', 'first_name_en', 'last_name_en')
                ->findOrFail($employeeId);

            $today = Carbon::today();
            $allocations = EmployeeFundingAllocation::with([
                'employment:id,start_date,end_probation_date,department_id,position_id',
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
    public function grantStructure()
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
                'employment:id,start_date,end_probation_date,department_id,position_id',
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
     *     path="/downloads/grant-items-reference",
     *     summary="Download grant items reference list",
     *     description="Downloads an Excel file with all grants and their grant items including IDs for use in funding allocation imports",
     *     operationId="downloadGrantItemsReference",
     *     tags={"Employee Funding Allocations"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Reference file downloaded successfully"),
     *     @OA\Response(response=500, description="Failed to generate reference file")
     * )
     */
    public function downloadGrantItemsReference()
    {
        try {
            $export = new GrantItemsReferenceExport;
            $tempFile = $export->generate();
            $filename = $export->getFilename();

            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'max-age=0',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate grant items reference',
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
            $export = new EmployeeFundingAllocationTemplateExport;
            $tempFile = $export->generate();
            $filename = $export->getFilename();

            return response()->download($tempFile, $filename, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'max-age=0',
            ])->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate employee funding allocation template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
