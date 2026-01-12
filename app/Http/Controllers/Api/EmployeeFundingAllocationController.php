<?php

namespace App\Http\Controllers\Api;

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
                'employment:id,employment_type,start_date',
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
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Grant Items Reference');

            // Add important notice at the top
            $sheet->mergeCells('A1:L1');
            $sheet->setCellValue('A1', '⚠️ IMPORTANT: Copy the "Grant Item ID" (Column E - Green) to your Funding Allocation Import Template');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FF6B6B'); // Red background for attention
            $sheet->getStyle('A1')->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension(1)->setRowHeight(30);

            // Headers
            $headers = [
                'Grant ID',
                'Grant Code',
                'Grant Name',
                'Grant Organization',
                'Grant Item ID',
                'Grant Position',
                'Budget Line Code',
                'Grant Salary',
                'Grant Benefit',
                'Level of Effort (%)',
                'Position Number',
                'Grant Status',
            ];

            // Write headers with special highlighting for Grant Item ID (Row 2 now)
            $col = 1;
            foreach ($headers as $header) {
                $cell = $sheet->getCellByColumnAndRow($col, 2);
                $cell->setValue($header);
                $cell->getStyle()->getFont()->setBold(true)->setSize(11);
                
                // Highlight Grant Item ID column (column E - the most important one)
                if ($header === 'Grant Item ID') {
                    $cell->getStyle()->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('28A745'); // Green - Important!
                    $cell->getStyle()->getFont()->getColor()->setRGB('FFFFFF');
                    $cell->getStyle()->getFont()->setSize(12)->setBold(true);
                } else {
                    $cell->getStyle()->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('4472C4'); // Blue - Standard
                    $cell->getStyle()->getFont()->getColor()->setRGB('FFFFFF');
                }
                
                $cell->getStyle()->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $col++;
            }

            // Fetch all grants with their items
            $grants = Grant::with('grantItems')->orderBy('code')->get();

            $row = 3; // Start from row 3 (after notice and headers)
            foreach ($grants as $grant) {
                foreach ($grant->grantItems as $item) {
                    $sheet->setCellValue("A{$row}", $grant->id);
                    $sheet->setCellValue("B{$row}", $grant->code);
                    $sheet->setCellValue("C{$row}", $grant->name);
                    $sheet->setCellValue("D{$row}", $grant->organization);
                    
                    // Highlight Grant Item ID cell (Column E) - This is what users need!
                    $sheet->setCellValue("E{$row}", $item->id);
                    $sheet->getStyle("E{$row}")->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('D4EDDA'); // Light green background
                    $sheet->getStyle("E{$row}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('155724');
                    $sheet->getStyle("E{$row}")->getAlignment()
                        ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    // Add border to make it stand out
                    $sheet->getStyle("E{$row}")->getBorders()->getAllBorders()
                        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM)
                        ->getColor()->setRGB('28A745');
                    
                    $sheet->setCellValue("F{$row}", $item->grant_position);
                    $sheet->setCellValue("G{$row}", $item->budgetline_code);
                    $sheet->setCellValue("H{$row}", $item->grant_salary);
                    $sheet->setCellValue("I{$row}", $item->grant_benefit);
                    $sheet->setCellValue("J{$row}", $item->grant_level_of_effort);
                    $sheet->setCellValue("K{$row}", $item->grant_position_number);
                    $sheet->setCellValue("L{$row}", $grant->status);
                    $row++;
                }
            }

            // Set column widths
            $columnWidths = [
                'A' => 12, 'B' => 15, 'C' => 30, 'D' => 18,
                'E' => 15, 'F' => 25, 'G' => 18, 'H' => 15,
                'I' => 15, 'J' => 18, 'K' => 15, 'L' => 15,
            ];

            foreach ($columnWidths as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }

            // Add instructions sheet
            $instructionsSheet = $spreadsheet->createSheet();
            $instructionsSheet->setTitle('Instructions');

            $instructions = [
                ['Grant Items Reference - How to Use'],
                [''],
                ['⭐ QUICK START:'],
                ['Look for the GREEN column (Column E) - that\'s the "Grant Item ID" you need!'],
                ['Copy this ID to your funding allocation import template.'],
                [''],
                ['PURPOSE:'],
                ['This file contains all available grants and their grant items with IDs.'],
                ['Use this reference when filling out the Employee Funding Allocation import template.'],
                [''],
                ['HOW TO USE:'],
                ['1. Find the grant you want to allocate funding from'],
                ['2. Locate the specific grant item (position) within that grant'],
                ['3. Copy the "Grant Item ID" from Column E (GREEN HIGHLIGHTED) to your import file'],
                [''],
                ['COLOR CODING:'],
                ['🟢 GREEN COLUMN (E) = Grant Item ID - THIS IS WHAT YOU NEED!'],
                ['🔵 BLUE COLUMNS = Reference information to help you find the right grant item'],
                [''],
                ['IMPORTANT NOTES:'],
                ['- Grant Item ID is required for funding allocation imports'],
                ['- Each grant item represents a specific position/funding source'],
                ['- Position Number shows how many employees can be allocated to this grant item'],
                ['- Grant Status shows if the grant is Active, Expired, or Ending Soon'],
                [''],
                ['COLUMNS EXPLAINED:'],
                ['- Grant ID: Unique identifier for the grant'],
                ['- Grant Code: Short code for the grant'],
                ['- Grant Name: Full name of the grant'],
                ['- Grant Organization: Organization managing the grant (SMRU/BHF)'],
                ['- Grant Item ID: ID to use in funding allocation imports (REQUIRED)'],
                ['- Grant Position: Position title for this grant item'],
                ['- Budget Line Code: Budget line code for accounting'],
                ['- Grant Salary: Budgeted salary for this position'],
                ['- Grant Benefit: Budgeted benefits for this position'],
                ['- Level of Effort: Expected effort percentage'],
                ['- Position Number: Maximum number of employees for this position'],
                ['- Grant Status: Current status of the grant'],
            ];

            $row = 1;
            foreach ($instructions as $instruction) {
                $instructionsSheet->setCellValue("A{$row}", $instruction[0]);
                if ($row === 1) {
                    $instructionsSheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
                } elseif (in_array($row, [3, 7, 11, 15])) {
                    $instructionsSheet->getStyle("A{$row}")->getFont()->setBold(true);
                }
                $row++;
            }

            $instructionsSheet->getColumnDimension('A')->setWidth(100);
            $spreadsheet->setActiveSheetIndex(0);

            // Generate and download
            $filename = 'grant_items_reference_'.date('Y-m-d_His').'.xlsx';
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            $tempFile = tempnam(sys_get_temp_dir(), 'grant_items_ref_');
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
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Funding Allocation Import');

            // ============================================
            // SECTION 1: DEFINE HEADERS
            // ============================================
            $headers = [
                'staff_id',
                'grant_item_id',
                'fte',
                'allocated_amount',
                'start_date',
                'end_date',
                'notes',
            ];

            // ============================================
            // SECTION 2: DEFINE VALIDATION RULES
            // ============================================
            $validationRules = [
                'String - NOT NULL - Employee staff ID (must exist in system)',
                'Integer - NOT NULL - Grant item ID (use Grant Items Reference file)',
                'Decimal (0-100) - NOT NULL - FTE percentage (e.g., 50 for 50%, 100 for 100%)',
                'Decimal(15,2) - NULLABLE - Pre-calculated allocated amount (auto-calculated if empty)',
                'Date (YYYY-MM-DD) - NOT NULL - Allocation start date',
                'Date (YYYY-MM-DD) - NULLABLE - Allocation end date (leave empty for ongoing)',
                'Text - NULLABLE - Additional notes or comments',
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
                ['EMP001', '1', '100', '', '2025-01-01', '', 'Full-time allocation to Grant Item 1'],
                ['EMP002', '2', '60', '30000.00', '2025-01-15', '2025-12-31', 'Part-time 60% allocation'],
                ['EMP002', '3', '40', '20000.00', '2025-01-15', '2025-12-31', 'Split funding - remaining 40%'],
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
                'B' => 18,  // grant_item_id
                'C' => 12,  // fte
                'D' => 18,  // allocated_amount
                'E' => 15,  // start_date
                'F' => 15,  // end_date
                'G' => 35,  // notes
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
                ['BEFORE YOU START:'],
                ['1. Download the "Grant Items Reference" file to get valid Grant Item IDs'],
                ['2. The Grant Items Reference contains all grants and their items with IDs'],
                ['3. You will need the Grant Item ID (Column E) from that file'],
                [''],
                ['REQUIRED FIELDS (Cannot be empty):'],
                ['- staff_id: Employee staff ID (must exist in system)'],
                ['- grant_item_id: Grant item ID from Grant Items Reference file'],
                ['- fte: FTE percentage (0-100, e.g., 50 for 50%, 100 for 100%)'],
                ['- start_date: Allocation start date (YYYY-MM-DD format)'],
                [''],
                ['OPTIONAL FIELDS:'],
                ['- allocated_amount: Leave empty for auto-calculation based on salary'],
                ['- end_date: Leave empty for ongoing allocation'],
                ['- notes: Any additional comments or information'],
                [''],
                ['HOW IT WORKS:'],
                ['1. System uses staff_id to find the employee'],
                ['2. System automatically finds the active employment for that employee'],
                ['3. System creates funding allocation linking employee to grant item'],
                ['4. If allocated_amount is empty, system calculates it based on FTE and salary'],
                [''],
                ['DATE FORMAT:'],
                ['All dates must be in YYYY-MM-DD format (e.g., 2025-01-15)'],
                [''],
                ['FTE (Full-Time Equivalent):'],
                ['- Enter as percentage without % symbol'],
                ['- Examples: 100 = full-time, 50 = half-time, 25 = quarter-time'],
                ['- For split funding: Create multiple rows for the same employee'],
                ['- Example: Employee 60% on Grant A + 40% on Grant B = 2 rows'],
                [''],
                ['GRANT ITEM ID:'],
                ['- Download "Grant Items Reference" file to see all available grant items'],
                ['- Each grant has multiple grant items (positions)'],
                ['- Copy the Grant Item ID from the reference file'],
                ['- One grant has many grant items - choose the correct item for the position'],
                [''],
                ['VALIDATION RULES:'],
                ['- staff_id must exist in the system'],
                ['- grant_item_id must be valid (check Grant Items Reference)'],
                ['- FTE must be between 0 and 100'],
                ['- start_date is required'],
                ['- end_date is optional (leave empty for ongoing)'],
                ['- Employee must have an active employment record'],
                ['- Total FTE per employee should equal 100%'],
                [''],
                ['EXAMPLE SCENARIOS:'],
                ['Single Funding (100%):'],
                ['  EMP001 | 5 | 100 | | 2025-01-01 | | Full-time on one grant'],
                [''],
                ['Split Funding (60/40):'],
                ['  EMP002 | 10 | 60 | | 2025-01-01 | | 60% on Grant Item 10'],
                ['  EMP002 | 15 | 40 | | 2025-01-01 | | 40% on Grant Item 15'],
                [''],
                ['Split Funding (50/30/20):'],
                ['  EMP003 | 20 | 50 | | 2025-01-01 | | Half-time on Grant Item 20'],
                ['  EMP003 | 25 | 30 | | 2025-01-01 | | 30% on Grant Item 25'],
                ['  EMP003 | 30 | 20 | | 2025-01-01 | | 20% on Grant Item 30'],
                [''],
                ['BEST PRACTICES:'],
                ['- Always download the latest Grant Items Reference before importing'],
                ['- Verify staff_id exists in the system'],
                ['- Keep total FTE per employee = 100%'],
                ['- Use consistent date formats (YYYY-MM-DD)'],
                ['- Test with a small batch first (2-3 employees)'],
                ['- Review the Grant Items Reference to understand grant structure'],
                [''],
                ['AFTER UPLOAD:'],
                ['- You will receive a notification when import completes'],
                ['- Check notification for success/error summary'],
                ['- Review created/updated allocations in the system'],
                ['- Verify that allocations are correctly linked to grant items'],
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
