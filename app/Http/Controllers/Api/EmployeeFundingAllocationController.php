<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Models\EmployeeFundingAllocation;

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
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EmployeeFundingAllocation")),
     *             @OA\Property(property="current_page", type="integer"),
     *             @OA\Property(property="last_page", type="integer"),
     *             @OA\Property(property="per_page", type="integer"),
     *             @OA\Property(property="total", type="integer")
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index(Request $request)
    {
        $query = EmployeeFundingAllocation::with(['employee', 'employment', 'orgFunded', 'positionSlot']);
        
        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }
        
        $allocations = $query->orderByDesc('id')->paginate(20);

        return response()->json($allocations);
    }

    /**
     * @OA\Post(
     *     path="/employee-funding-allocations",
     *     operationId="createEmployeeFundingAllocation",
     *     tags={"Employee Funding Allocations"},
     *     summary="Create multiple employee funding allocations",
     *     description="Creates multiple employee funding allocations with validation for total effort and allocation type constraints",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "employment_id", "start_date", "allocations"},
     *             @OA\Property(property="employee_id", type="integer", description="ID of the employee"),
     *             @OA\Property(property="employment_id", type="integer", description="ID of the employment"),
     *             @OA\Property(property="start_date", type="string", format="date", description="Start date for allocations"),
     *             @OA\Property(property="end_date", type="string", format="date", description="End date for allocations", nullable=true),
     *             @OA\Property(
     *                 property="allocations",
     *                 type="array",
     *                 description="Array of funding allocations",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"allocation_type", "level_of_effort"},
     *                     @OA\Property(property="allocation_type", type="string", enum={"grant", "org_funded"}, description="Type of allocation"),
     *                     @OA\Property(property="position_slot_id", type="integer", description="Position slot ID (required for grant allocations)", nullable=true),
     *                     @OA\Property(property="org_funded_id", type="integer", description="Org funded allocation ID (required for org_funded allocations)", nullable=true),
     *                     @OA\Property(property="level_of_effort", type="number", format="float", minimum=0, maximum=100, description="Level of effort as percentage (0-100)")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Allocations created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee funding allocations created successfully"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EmployeeFundingAllocation")),
     *             @OA\Property(property="total_created", type="integer", example=2),
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

            // Validate that the total effort of all new allocations equals exactly 100%
            $totalNewEffort = array_sum(array_column($validated['allocations'], 'level_of_effort'));
            if ($totalNewEffort != 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total effort of all allocations must equal exactly 100%',
                    'current_total' => $totalNewEffort
                ], 422);
            }

            // Check if employee already has any active allocations for this employment
            $existingActiveAllocations = EmployeeFundingAllocation::where('employee_id', $validated['employee_id'])
                ->where('employment_id', $validated['employment_id'])
                ->where('active', true)
                ->exists();

            if ($existingActiveAllocations) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee already has active funding allocations for this employment. Please use the update endpoint to modify existing allocations or deactivate them first.'
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
                        if (!$positionSlot) {
                            $errors[] = "Allocation #{$index}: Position slot not found";
                            continue;
                        }

                        $grantItem = $positionSlot->grantItem;
                        if ($grantItem) {
                            $grantPositionNumber = (int) $grantItem->grant_position_number;

                            // Check position slot availability based on grant position number
                            if ($grantPositionNumber > 0) {
                                // Count current active allocations across all position slots for this grant item
                                $currentAllocations = EmployeeFundingAllocation::whereHas('positionSlot', function ($query) use ($grantItem) {
                                    $query->where('grant_item_id', $grantItem->id);
                                })
                                ->where('active', true)
                                ->where('allocation_type', 'grant')
                                ->count();

                                // Check if we can accommodate one more allocation
                                if ($currentAllocations >= $grantPositionNumber) {
                                    $errors[] = "Allocation #{$index}: Grant position '{$grantItem->grant_position}' has reached its maximum capacity of {$grantPositionNumber} allocations. Currently allocated: {$currentAllocations}";
                                    continue;
                                }
                            }
                        }
                    } elseif ($allocationType === 'org_funded') {
                        if (empty($allocationData['org_funded_id'])) {
                            $errors[] = "Allocation #{$index}: org_funded_id is required for org_funded allocations";
                            continue;
                        }

                        // Verify org funded allocation exists
                        $orgFunded = OrgFundedAllocation::find($allocationData['org_funded_id']);
                        if (!$orgFunded) {
                            $errors[] = "Allocation #{$index}: Org funded allocation not found";
                            continue;
                        }
                    }

                    // Check if this exact allocation already exists
                    $existingAllocation = EmployeeFundingAllocation::where([
                        'employee_id' => $validated['employee_id'],
                        'employment_id' => $validated['employment_id'],
                        'allocation_type' => $allocationType,
                        'active' => true
                    ]);

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
                        'level_of_effort' => $allocationData['level_of_effort'] / 100, // Convert percentage to decimal
                        'allocation_type' => $allocationType,
                        'active' => true,
                        'start_date' => $validated['start_date'],
                        'end_date' => $validated['end_date'] ?? null,
                        'created_by' => $currentUser,
                        'updated_by' => $currentUser,
                    ]);

                    $createdAllocations[] = $allocation;

                } catch (\Exception $e) {
                    $errors[] = "Allocation #{$index}: " . $e->getMessage();
                }
            }

            if (empty($createdAllocations) && !empty($errors)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create any allocations',
                    'errors' => $errors
                ], 422);
            }

            DB::commit();

            // Load the created allocations with relationships
            $allocationsWithRelations = EmployeeFundingAllocation::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'employment:id,employment_type,start_date',
                'positionSlot.grantItem.grant:id,name,code',
                'positionSlot.budgetLine:id,budget_line_code,description',
                'orgFunded'
            ])->whereIn('id', collect($createdAllocations)->pluck('id'))->get();

            $response = [
                'success' => true,
                'message' => 'Employee funding allocations created successfully',
                'data' => $allocationsWithRelations,
                'total_created' => count($createdAllocations)
            ];

            if (!empty($errors)) {
                $response['warnings'] = $errors;
            }

            return response()->json($response, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee funding allocations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/employee-funding-allocations/{id}",
     *     operationId="getEmployeeFundingAllocation",
     *     tags={"Employee Funding Allocations"},
     *     summary="Get employee funding allocation by ID",
     *     description="Returns a single employee funding allocation with related data",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the employee funding allocation",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(ref="#/components/schemas/EmployeeFundingAllocation")
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Allocation not found")
     * )
     */
    public function show($id)
    {
        $allocation = EmployeeFundingAllocation::with(['employee', 'employment', 'orgFunded', 'positionSlot'])->findOrFail($id);
        return response()->json($allocation);
    }

    /**
     * @OA\Put(
     *     path="/employee-funding-allocations/{id}",
     *     operationId="updateEmployeeFundingAllocation",
     *     tags={"Employee Funding Allocations"},
     *     summary="Update employee funding allocation",
     *     description="Updates an existing employee funding allocation",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the employee funding allocation",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="employee_id", type="integer", description="ID of the employee"),
     *             @OA\Property(property="employment_id", type="integer", description="ID of the employment", nullable=true),
     *             @OA\Property(property="org_funded_id", type="integer", description="ID of the org funded allocation", nullable=true),
     *             @OA\Property(property="position_slot_id", type="integer", description="ID of the position slot", nullable=true),
     *             @OA\Property(property="level_of_effort", type="number", format="float", description="Level of effort (0.0 to 1.0)"),
     *             @OA\Property(property="allocation_type", type="string", description="Type of allocation"),
     *             @OA\Property(property="start_date", type="string", format="date", description="Start date", nullable=true),
     *             @OA\Property(property="end_date", type="string", format="date", description="End date", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Allocation updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/EmployeeFundingAllocation")
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Unauthenticated"),
     *     @OA\Response(response=404, description="Allocation not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request, $id)
    {
        $allocation = EmployeeFundingAllocation::findOrFail($id);

        $validated = $request->validate([
            'employee_id' => 'sometimes|exists:employees,id',
            'employment_id' => 'nullable|exists:employments,id',
            'org_funded_id' => 'nullable|exists:org_funded_allocations,id',
            'position_slot_id' => 'nullable|exists:position_slots,id',
            'level_of_effort' => 'sometimes|numeric|between:0,1',
            'allocation_type' => 'sometimes|string|max:20',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $allocation->update([
            ...$validated,
            'updated_by' => $request->user()->name ?? 'system'
        ]);

        return response()->json($allocation);
    }

    /**
     * @OA\Delete(
     *     path="/employee-funding-allocations/{id}",
     *     operationId="deleteEmployeeFundingAllocation",
     *     tags={"Employee Funding Allocations"},
     *     summary="Delete employee funding allocation",
     *     description="Deletes an employee funding allocation",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the employee funding allocation",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
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
        $allocation = EmployeeFundingAllocation::findOrFail($id);
        $allocation->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
