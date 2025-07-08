<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employment;
use App\Models\EmployeeFundingAllocation;
use App\Models\OrgFundedAllocation;
use App\Models\PositionSlot;
use App\Models\GrantItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

/**
 * @OA\Tag(
 *     name="Employments",
 *     description="API Endpoints for managing employee employment records"
 * )
 */
class EmploymentController extends Controller
{
    /**
     * Display a listing of employments.
     *
     * @OA\Get(
     *     path="/employments",
     *     summary="Get all employment records",
     *     description="Returns a list of all employment records",
     *     operationId="getEmployments",
     *     tags={"Employments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Employment")),
     *             @OA\Property(property="message", type="string", example="Employments retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        try {
            // Optionally, additional relationships (e.g. employee, grantAllocations) can be loaded if needed.
            $employments = Employment::with(['employee', 'departmentPosition', 'workLocation'])->get();

            return response()->json([
                'success' => true,
                'data'    => $employments,
                'message' => 'Employments retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/employments",
     *     operationId="createEmploymentWithFundingAllocations",
     *     tags={"Employments"},
     *     summary="Create employment record with funding allocations",
     *     description="Creates an employment record and associated funding allocations. For org_funded allocations, creates org_funded_allocation records first, then creates employee_funding_allocations for both grant and org_funded types.",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "employment_type", "start_date", "position_salary", "allocations"},
     *             @OA\Property(property="employee_id", type="integer", description="ID of the employee"),
     *             @OA\Property(property="employment_type", type="string", description="Type of employment"),
     *             @OA\Property(property="pay_method", type="string", description="Pay method", nullable=true),
     *             @OA\Property(property="probation_pass_date", type="string", format="date", description="Probation pass date", nullable=true),
     *             @OA\Property(property="start_date", type="string", format="date", description="Employment start date"),
     *             @OA\Property(property="end_date", type="string", format="date", description="Employment end date", nullable=true),
     *             @OA\Property(property="active", type="boolean", description="Employment status", default=true),
     *             @OA\Property(property="department_position_id", type="integer", description="Department position ID", nullable=true),
     *             @OA\Property(property="work_location_id", type="integer", description="Work location ID", nullable=true),
     *             @OA\Property(property="position_salary", type="number", format="float", description="Position salary"),
     *             @OA\Property(property="probation_salary", type="number", format="float", description="Probation salary", nullable=true),
     *             @OA\Property(property="employee_tax", type="number", format="float", description="Employee tax", nullable=true),
     *             @OA\Property(property="fte", type="number", format="float", description="Full-time equivalent", nullable=true),
     *             @OA\Property(property="health_welfare", type="boolean", description="Health welfare benefit", default=false),
     *             @OA\Property(property="pvd", type="boolean", description="Provident fund", default=false),
     *             @OA\Property(property="saving_fund", type="boolean", description="Saving fund", default=false),
     *             @OA\Property(
     *                 property="allocations",
     *                 type="array",
     *                 description="Array of funding allocations",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"allocation_type", "level_of_effort"},
     *                     @OA\Property(property="allocation_type", type="string", enum={"grant", "org_funded"}, description="Type of allocation"),
     *                     @OA\Property(property="position_slot_id", type="integer", description="Position slot ID (for grant allocations)", nullable=true),
     *                     @OA\Property(property="org_funded_id", type="integer", description="Temporary org_funded_id from frontend (will be ignored)", nullable=true),
     *                     @OA\Property(property="grant_id", type="integer", description="Grant ID (for org_funded allocations)", nullable=true),
     *                     @OA\Property(property="level_of_effort", type="number", format="float", minimum=0, maximum=100, description="Level of effort as percentage (0-100)")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Employment and allocations created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employment and funding allocations created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="employment", ref="#/components/schemas/Employment"),
     *                 @OA\Property(property="funding_allocations", type="array", @OA\Items(ref="#/components/schemas/EmployeeFundingAllocation")),
     *                 @OA\Property(property="org_funded_allocations", type="array", @OA\Items(ref="#/components/schemas/OrgFundedAllocation"))
     *             ),
     *             @OA\Property(property="summary", type="object",
     *                 @OA\Property(property="employment_created", type="boolean"),
     *                 @OA\Property(property="org_funded_created", type="integer"),
     *                 @OA\Property(property="funding_allocations_created", type="integer")
     *             ),
     *             @OA\Property(property="warnings", type="array", @OA\Items(type="string"), nullable=true)
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function store(Request $request)
    {
        try {
            // ============================================================================
            // SECTION 1: REQUEST VALIDATION
            // ============================================================================
            $validator = Validator::make($request->all(), [
                // Employment fields
                'employee_id' => 'required|exists:employees,id',
                'employment_type' => 'required|string',
                'pay_method' => 'nullable|string',
                'probation_pass_date' => 'nullable|date',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'active' => 'boolean',
                'department_position_id' => 'nullable|exists:department_positions,id',
                'work_location_id' => 'nullable|exists:work_locations,id',
                'position_salary' => 'required|numeric',
                'probation_salary' => 'nullable|numeric',
                'employee_tax' => 'nullable|numeric',
                'fte' => 'nullable|numeric',
                'health_welfare' => 'boolean',
                'pvd' => 'boolean',
                'saving_fund' => 'boolean',
                
                // Allocation fields - Updated to handle frontend payload
                'allocations' => 'required|array|min:1',
                'allocations.*.allocation_type' => 'required|string|in:grant,org_funded',
                'allocations.*.position_slot_id' => 'required_if:allocations.*.allocation_type,grant|nullable|exists:position_slots,id',
                'allocations.*.org_funded_id' => 'nullable|integer', // Frontend sends this but we'll ignore it
                'allocations.*.grant_id' => 'nullable|exists:grants,id', // For org_funded, we need the grant_id
                'allocations.*.level_of_effort' => 'required|numeric|min:0|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $validated = $validator->validated();
            $currentUser = Auth::user()->name ?? 'system';

            // ============================================================================
            // SECTION 2: BUSINESS LOGIC VALIDATION
            // ============================================================================
            
            // Validate that the total effort of all allocations equals exactly 100%
            $totalEffort = array_sum(array_column($validated['allocations'], 'level_of_effort'));
            if ($totalEffort != 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total effort of all allocations must equal exactly 100%',
                    'current_total' => $totalEffort
                ], 422);
            }

            // Check if employee already has active employment
            $existingActiveEmployment = Employment::where('employee_id', $validated['employee_id'])
                ->where('active', true)
                ->exists();

            if ($existingActiveEmployment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee already has an active employment record. Please deactivate the existing employment first.'
                ], 422);
            }

            // ============================================================================
            // SECTION 3: DATABASE TRANSACTION START
            // ============================================================================
            DB::beginTransaction();

            $createdOrgFundedAllocations = [];
            $createdFundingAllocations = [];
            $errors = [];
            $warnings = [];

            // ============================================================================
            // SECTION 4: CREATE EMPLOYMENT RECORD
            // ============================================================================
            $employmentData = array_merge(
                collect($validated)->except('allocations')->toArray(),
                [
                    'created_by' => $currentUser,
                    'updated_by' => $currentUser,
                ]
            );

            $employment = Employment::create($employmentData);

            // ============================================================================
            // SECTION 5: PROCESS ALLOCATIONS
            // ============================================================================
            foreach ($validated['allocations'] as $index => $allocationData) {
                try {
                    $allocationType = $allocationData['allocation_type'];

                    // ============================================================================
                    // SECTION 5A: HANDLE GRANT ALLOCATIONS
                    // ============================================================================
                    if ($allocationType === 'grant') {
                        // Validate position slot exists
                        $positionSlot = PositionSlot::with('grantItem')->find($allocationData['position_slot_id']);
                        if (!$positionSlot) {
                            $errors[] = "Allocation #{$index}: Position slot not found";
                            continue;
                        }

                        // Check grant capacity constraints
                        $grantItem = $positionSlot->grantItem;
                        if ($grantItem && $grantItem->grant_position_number > 0) {
                            $currentAllocations = EmployeeFundingAllocation::whereHas('positionSlot', function ($query) use ($grantItem) {
                                $query->where('grant_item_id', $grantItem->id);
                            })
                            ->where('allocation_type', 'grant')
                            ->count();

                            if ($currentAllocations >= $grantItem->grant_position_number) {
                                $errors[] = "Allocation #{$index}: Grant position '{$grantItem->grant_position}' has reached its maximum capacity of {$grantItem->grant_position_number} allocations. Currently allocated: {$currentAllocations}";
                                continue;
                            }
                        }

                        // Create grant funding allocation
                        $fundingAllocation = EmployeeFundingAllocation::create([
                            'employee_id' => $employment->employee_id,
                            'employment_id' => $employment->id,
                            'position_slot_id' => $allocationData['position_slot_id'],
                            'org_funded_id' => null,
                            'level_of_effort' => $allocationData['level_of_effort'] / 100, // Convert percentage to decimal
                            'allocation_type' => 'grant',
                            'start_date' => $validated['start_date'],
                            'end_date' => $validated['end_date'] ?? null,
                            'created_by' => $currentUser,
                            'updated_by' => $currentUser,
                        ]);

                        $createdFundingAllocations[] = $fundingAllocation;
                    }

                    // ============================================================================
                    // SECTION 5B: HANDLE ORG FUNDED ALLOCATIONS
                    // ============================================================================
                    elseif ($allocationType === 'org_funded') {
                        // For org_funded, we need grant_id to create the OrgFundedAllocation
                        if (empty($allocationData['grant_id'])) {
                            $errors[] = "Allocation #{$index}: grant_id is required for org_funded allocations";
                            continue;
                        }

                        // First, create the org_funded_allocation record
                        $orgFundedAllocation = OrgFundedAllocation::create([
                            'grant_id' => $allocationData['grant_id'],
                            'department_position_id' => $employment->department_position_id,
                            'description' => 'Auto-created for employment ID: ' . $employment->id,
                            'active' => true,
                            'created_by' => $currentUser,
                            'updated_by' => $currentUser,
                        ]);

                        $createdOrgFundedAllocations[] = $orgFundedAllocation;

                        // Then, create the employee funding allocation referencing the org_funded_allocation
                        $fundingAllocation = EmployeeFundingAllocation::create([
                            'employee_id' => $employment->employee_id,
                            'employment_id' => $employment->id,
                            'position_slot_id' => null,
                            'org_funded_id' => $orgFundedAllocation->id, // Use the ID from the created org_funded_allocation
                            'level_of_effort' => $allocationData['level_of_effort'] / 100, // Convert percentage to decimal
                            'allocation_type' => 'org_funded',
                            'start_date' => $validated['start_date'],
                            'end_date' => $validated['end_date'] ?? null,
                            'created_by' => $currentUser,
                            'updated_by' => $currentUser,
                        ]);

                        $createdFundingAllocations[] = $fundingAllocation;
                    }

                } catch (\Exception $e) {
                    $errors[] = "Allocation #{$index}: " . $e->getMessage();
                }
            }

            // ============================================================================
            // SECTION 6: HANDLE ERRORS AND ROLLBACK IF NECESSARY
            // ============================================================================
            if (empty($createdFundingAllocations) && !empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create employment and allocations',
                    'errors' => $errors
                ], 422);
            }

            // If some allocations failed but others succeeded, add warnings
            if (!empty($errors)) {
                $warnings = array_merge($warnings, $errors);
            }

            // ============================================================================
            // SECTION 7: COMMIT TRANSACTION AND PREPARE RESPONSE
            // ============================================================================
            DB::commit();

            // Load the created records with their relationships
            $employmentWithRelations = Employment::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'departmentPosition:id,department_name_en,position_name_en',
                'workLocation:id,location_name_en'
            ])->find($employment->id);

            $fundingAllocationsWithRelations = EmployeeFundingAllocation::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'employment:id,employment_type,start_date',
                'positionSlot.grantItem.grant:id,name,code',
                'positionSlot.budgetLine:id,budget_line_code,description',
                'orgFunded.grant:id,name,code',
                'orgFunded.departmentPosition:id,department_name_en,position_name_en'
            ])->whereIn('id', collect($createdFundingAllocations)->pluck('id'))->get();

            $orgFundedAllocationsWithRelations = OrgFundedAllocation::with([
                'grant:id,name,code',
                'departmentPosition:id,department_name_en,position_name_en'
            ])->whereIn('id', collect($createdOrgFundedAllocations)->pluck('id'))->get();

            // ============================================================================
            // SECTION 8: RETURN SUCCESS RESPONSE
            // ============================================================================
            $response = [
                'success' => true,
                'message' => 'Employment and funding allocations created successfully',
                'data' => [
                    'employment' => $employmentWithRelations,
                    'funding_allocations' => $fundingAllocationsWithRelations,
                    'org_funded_allocations' => $orgFundedAllocationsWithRelations
                ],
                'summary' => [
                    'employment_created' => true,
                    'org_funded_created' => count($createdOrgFundedAllocations),
                    'funding_allocations_created' => count($createdFundingAllocations)
                ]
            ];

            if (!empty($warnings)) {
                $response['warnings'] = $warnings;
            }

            return response()->json($response, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employment and funding allocations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified employment record.
     *
     * @OA\Get(
     *     path="/employments/{id}",
     *     summary="Get employment record by ID",
     *     description="Returns a specific employment record by ID",
     *     operationId="getEmployment",
     *     tags={"Employments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of employment record to return",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Employment"),
     *             @OA\Property(property="message", type="string", example="Employment retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Employment not found")
     * )
     */
    public function show($id)
    {
        try {
            $employment = Employment::findOrFail($id);

            return response()->json([
                'success' => true,
                'data'    => $employment,
                'message' => 'Employment retrieved successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employment not found'
            ], 404);
        }
    }

    /**
     * Update the specified employment record.
     *
     * @OA\Put(
     *     path="/employments/{id}",
     *     summary="Update an existing employment record",
     *     description="Updates an employment record with the provided data",
     *     operationId="updateEmployment",
     *     tags={"Employments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of employment record to update",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="employment_type", type="string", example="Full-Time"),
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-01-15"),
     *             @OA\Property(property="probation_end_date", type="string", format="date", example="2025-04-15", nullable=true),
     *             @OA\Property(property="end_date", type="string", format="date", example="2024-01-15", nullable=true),
     *             @OA\Property(property="department_position_id", type="integer", example=1),
     *             @OA\Property(property="work_location_id", type="integer", example=1),
     *             @OA\Property(property="position_salary", type="number", format="float", example=50000),
     *             @OA\Property(property="probation_salary", type="number", format="float", example=45000, nullable=true),
     *             @OA\Property(property="employee_tax", type="number", format="float", example=7, nullable=true),
     *             @OA\Property(property="fte", type="number", format="float", example=1.0, nullable=true),
     *             @OA\Property(property="active", type="boolean", example=true),
     *             @OA\Property(property="health_welfare", type="boolean", example=true),
     *             @OA\Property(property="pvd", type="boolean", example=false),
     *             @OA\Property(property="saving_fund", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employment updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/Employment"),
     *             @OA\Property(property="message", type="string", example="Employment updated successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Employment not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'employee_id'            => 'exists:employees,id',
            'employment_type'        => 'string',
            'start_date'             => 'date',
            'probation_end_date'     => 'nullable|date',
            'end_date'               => 'nullable|date',
            'department_position_id' => 'exists:department_positions,id',
            'work_location_id'       => 'exists:work_locations,id',
            'position_salary'        => 'numeric',
            'probation_salary'       => 'nullable|numeric',
            'employee_tax'           => 'nullable|numeric',
            'fte'                    => 'nullable|numeric',
            'active'                 => 'boolean',
            'health_welfare'         => 'boolean',
            'pvd'                    => 'boolean',
            'saving_fund'            => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            $employment = Employment::findOrFail($id);
            $data = $request->all();
            $data['updated_by'] = Auth::user()->name ?? 'System';

            $employment->update($data);

            return response()->json([
                'success' => true,
                'data'    => $employment,
                'message' => 'Employment updated successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified employment record.
     *
     * @OA\Delete(
     *     path="/employments/{id}",
     *     summary="Delete an employment record",
     *     description="Deletes an employment record by ID",
     *     operationId="deleteEmployment",
     *     tags={"Employments"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of employment record to delete",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employment deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employment deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Employment not found")
     * )
     */
    public function destroy($id)
    {
        try {
            $employment = Employment::findOrFail($id);
            $employment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Employment deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employment: ' . $e->getMessage()
            ], 500);
        }
    }




    /// For reference only
    // public function addEmploymentGrantAllocation(Request $request)
    // {
    //     $request->validate([
    //         'employment_id'   => 'required|exists:employments,id',
    //         'grant_items_id'  => 'required|exists:grant_items,id',
    //         'level_of_effort' => 'required|numeric|min:0|max:100',
    //         'start_date'      => 'required|date',
    //         'end_date'        => 'nullable|date|after_or_equal:start_date',
    //         'active'          => 'boolean'
    //     ]);

    //     // Get the grant item to check its position number
    //     $grantItem = GrantItem::findOrFail($request->grant_items_id);

    //     // Count existing allocations for this grant item
    //     $existingAllocationsCount = EmploymentGrantAllocation::where('grant_items_id', $request->grant_items_id)
    //         ->where('active', true)
    //         ->count();

    //     // Check if we've reached the position limit for this grant
    //     if ($existingAllocationsCount >= $grantItem->grant_position_number) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Cannot add more allocations. Grant position limit reached.'
    //         ], 400);
    //     }

    //     $grantAllocation = EmploymentGrantAllocation::create([
    //         'employment_id'   => $request->employment_id,
    //         'grant_items_id'  => $request->grant_items_id,
    //         'level_of_effort' => $request->level_of_effort,
    //         'start_date'      => $request->start_date,
    //         'end_date'        => $request->end_date,
    //         'active'          => $request->active ?? true
    //     ]);

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Employment grant allocation added successfully',
    //         'data'    => $grantAllocation
    //     ]);
    // }


    // public function deleteEmploymentGrantAllocation(Request $request, $id)
    // {
    //     $grantAllocation = EmploymentGrantAllocation::find($id);

    //     if (!$grantAllocation) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Employment grant allocation not found'
    //         ], 404);
    //     }

    //     $grantAllocation->delete();

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Employment grant allocation deleted successfully'
    //     ], 201);
    // }
}
