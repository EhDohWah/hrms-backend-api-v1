<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmployeeGrantAllocation;
use App\Models\PositionSlot;
use App\Models\GrantItem;
use App\Models\Employee;
use App\Models\Employment;
use App\Http\Resources\EmployeeGrantAllocationResource;
use App\Http\Requests\EmployeeGrantAllocationRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EmployeeGrantAllocationController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = EmployeeGrantAllocation::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'positionSlot.grantItem.grant:id,name,code',
                'positionSlot.budgetLine:id,budget_line_code,description',
                'employment:id,employment_type,start_date'
            ]);

            // Apply filters
            if ($request->has('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }

            if ($request->has('active')) {
                $query->where('active', $request->boolean('active'));
            }

            $employeeGrantAllocations = $query->orderBy('created_at', 'desc')->get();

            return EmployeeGrantAllocationResource::collection($employeeGrantAllocations)
                ->additional([
                    'success' => true,
                    'message' => 'Employee grant allocations retrieved successfully'
                ])
                ->response()
                ->setStatusCode(200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee grant allocations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|exists:employees,id',
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'allocations' => 'required|array|min:1',
                'allocations.*.position_slot_id' => 'required|exists:position_slots,id',
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

            // Check if employee already has any active allocations
            $existingActiveAllocations = EmployeeGrantAllocation::where('employee_id', $validated['employee_id'])
                ->where('active', true)
                ->exists();

            if ($existingActiveAllocations) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee already has active grant allocations. Please use the update endpoint to modify existing allocations or deactivate them first.'
                ], 422);
            }

            DB::beginTransaction();

            $createdAllocations = [];
            $errors = [];

            foreach ($validated['allocations'] as $index => $allocationData) {
                try {
                    $positionSlotId = $allocationData['position_slot_id'];

                    // Get the position slot with its grant item
                    $positionSlot = PositionSlot::with('grantItem')->find($positionSlotId);
                    if (!$positionSlot) {
                        $errors[] = "Allocation #{$index}: Position slot not found";
                        continue;
                    }

                    $grantItem = $positionSlot->grantItem;
                    $grantPositionNumber = (int) $grantItem->grant_position_number;

                    // Check position slot availability based on grant position number
                    if ($grantPositionNumber > 0) {
                        // Count current active allocations across all position slots for this grant item
                        $currentAllocations = EmployeeGrantAllocation::whereHas('positionSlot', function ($query) use ($grantItem) {
                            $query->where('grant_item_id', $grantItem->id);
                        })
                        ->where('active', true)
                        ->count();

                        // Check if we can accommodate one more allocation
                        if ($currentAllocations >= $grantPositionNumber) {
                            $errors[] = "Allocation #{$index}: Grant position '{$grantItem->grant_position}' has reached its maximum capacity of {$grantPositionNumber} allocations. Currently allocated: {$currentAllocations}";
                            continue;
                        }
                    }

                    // Check if this exact allocation already exists
                    $existingAllocation = EmployeeGrantAllocation::where([
                        'employee_id' => $validated['employee_id'],
                        'position_slot_id' => $positionSlotId,
                        'active' => true
                    ])->first();

                    if ($existingAllocation) {
                        $errors[] = "Allocation #{$index}: Already exists for this employee and position slot";
                        continue;
                    }

                    // Create the allocation
                    $allocation = EmployeeGrantAllocation::create([
                        'employee_id' => $validated['employee_id'],
                        'position_slot_id' => $positionSlotId,
                        'level_of_effort' => $allocationData['level_of_effort'],
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
            $allocationsWithRelations = EmployeeGrantAllocation::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'positionSlot.grantItem.grant:id,name,code',
                'positionSlot.budgetLine:id,budget_line_code,description',
                'employment:id,employment_type,start_date'
            ])->whereIn('id', collect($createdAllocations)->pluck('id'))->get();

            $response = [
                'success' => true,
                'message' => 'Employee grant allocations created successfully',
                'data' => EmployeeGrantAllocationResource::collection($allocationsWithRelations),
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
                'message' => 'Failed to create employee grant allocations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $allocation = EmployeeGrantAllocation::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'positionSlot.grantItem.grant:id,name,code',
                'positionSlot.budgetLine:id,budget_line_code,description',
                'employment:id,employment_type,start_date'
            ])->findOrFail($id);

            return (new EmployeeGrantAllocationResource($allocation))
                ->additional([
                    'success' => true,
                    'message' => 'Employee grant allocation retrieved successfully'
                ])
                ->response()
                ->setStatusCode(200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee grant allocation not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee grant allocation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getEmployeeAllocations($employeeId)
    {
        try {
            $employee = Employee::select('id', 'staff_id', 'first_name_en', 'last_name_en')
                ->findOrFail($employeeId);

            $allocations = EmployeeGrantAllocation::with([
                'positionSlot.grantItem.grant:id,name,code',
                'positionSlot.budgetLine:id,budget_line_code,description',
                'employment:id,employment_type,start_date'
            ])
            ->where('employee_id', $employeeId)
            ->where('active', true)
            ->orderBy('start_date', 'desc')
            ->get();

            $totalEffort = $allocations->sum('level_of_effort');

            return response()->json([
                'success' => true,
                'message' => 'Employee grant allocations retrieved successfully',
                'employee' => $employee,
                'total_allocations' => $allocations->count(),
                'total_effort' => $totalEffort,
                'data' => EmployeeGrantAllocationResource::collection($allocations)
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee grant allocations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $allocation = EmployeeGrantAllocation::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'level_of_effort' => 'sometimes|numeric|min:0|max:100',
                'start_date' => 'sometimes|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'active' => 'sometimes|boolean',
                'grant_id' => 'sometimes|exists:grants,id',
                'grant_items_id' => 'sometimes|exists:grant_items,id',
                'budgetline_id' => 'sometimes|exists:budget_lines,id',
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

            DB::beginTransaction();

            // If grant-related fields are being updated, update the position slot
            if (isset($validated['grant_items_id']) || isset($validated['budgetline_id'])) {
                $grantItemsId = $validated['grant_items_id'] ?? $allocation->positionSlot->grant_item_id;
                $budgetlineId = $validated['budgetline_id'] ?? $allocation->positionSlot->budget_line_id;

                $positionSlot = $this->findOrCreatePositionSlot($grantItemsId, $budgetlineId, $currentUser);
                $validated['position_slot_id'] = $positionSlot->id;

                // Remove the UI fields from validated data
                unset($validated['grant_items_id'], $validated['budgetline_id'], $validated['grant_id']);
            }

            // Validate total effort if level_of_effort is being updated
            if (isset($validated['level_of_effort'])) {
                $totalEffort = EmployeeGrantAllocation::where('employee_id', $allocation->employee_id)
                    ->where('active', true)
                    ->where('id', '!=', $id)
                    ->sum('level_of_effort');

                if (($totalEffort + $validated['level_of_effort']) > 100) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'Total effort would exceed 100% for this employee'
                    ], 422);
                }
            }

            $validated['updated_by'] = $currentUser;
            $allocation->update($validated);

            DB::commit();

            // Reload with relationships
            $allocation->load([
                'employee:id,staff_id,first_name_en,last_name_en',
                'positionSlot.grantItem.grant:id,name,code',
                'positionSlot.budgetLine:id,budget_line_code,description',
                'employment:id,employment_type,start_date'
            ]);

            return (new EmployeeGrantAllocationResource($allocation))
                ->additional([
                    'success' => true,
                    'message' => 'Employee grant allocation updated successfully'
                ])
                ->response()
                ->setStatusCode(200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Employee grant allocation not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee grant allocation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $allocation = EmployeeGrantAllocation::findOrFail($id);
            $allocation->delete();

            return response()->json([
                'success' => true,
                'message' => 'Employee grant allocation deleted successfully'
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee grant allocation not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employee grant allocation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function bulkDeactivate(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'allocation_ids' => 'required|array|min:1',
                'allocation_ids.*' => 'integer|exists:employee_grant_allocations,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $currentUser = Auth::user()->name ?? 'system';

            $updatedCount = EmployeeGrantAllocation::whereIn('id', $request->allocation_ids)
                ->update([
                    'active' => false,
                    'updated_by' => $currentUser
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Employee grant allocations deactivated successfully',
                'deactivated_count' => $updatedCount
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate employee grant allocations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function findOrCreatePositionSlot($grantItemId, $budgetLineId, $createdBy)
    {
        // First, try to find an existing position slot
        $positionSlot = PositionSlot::where([
            'grant_item_id' => $grantItemId,
            'budget_line_id' => $budgetLineId
        ])->first();

        if ($positionSlot) {
            return $positionSlot;
        }

        // If not found, create a new one
        // Determine the next slot number for this grant item
        $nextSlotNumber = PositionSlot::where('grant_item_id', $grantItemId)
            ->max('slot_number') + 1;

        if ($nextSlotNumber < 1) {
            $nextSlotNumber = 1;
        }

        return PositionSlot::create([
            'grant_item_id' => $grantItemId,
            'slot_number' => $nextSlotNumber,
            'budget_line_id' => $budgetLineId,
            'created_by' => $createdBy,
            'updated_by' => $createdBy
        ]);
    }

    public function getGrantStructure()
    {
        try {
            $grants = \App\Models\Grant::with([
                'grantItems.positionSlots.budgetLine:id,budget_line_code,description'
            ])->select('id', 'name', 'code')->get();

            $structure = $grants->map(function ($grant) {
                return [
                    'id' => $grant->id,
                    'name' => $grant->name,
                    'code' => $grant->code,
                    'grant_items' => $grant->grantItems->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'name' => $item->grant_position,
                            'position_slots' => $item->positionSlots->map(function ($slot) {
                                return [
                                    'id' => $slot->id,
                                    'slot_number' => $slot->slot_number,
                                    'budget_line' => [
                                        'id' => $slot->budgetLine->id,
                                        'name' => $slot->budgetLine->budget_line_code,
                                        'description' => $slot->budgetLine->description
                                    ]
                                ];
                            })
                        ];
                    })
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Grant structure retrieved successfully',
                'data' => $structure
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve grant structure',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function updateEmployeeAllocations(Request $request, $employeeId)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'start_date' => 'required|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'allocations' => 'required|array|min:1',
                'allocations.*.position_slot_id' => 'required|exists:position_slots,id',
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

            // Verify employee exists
            $employee = Employee::findOrFail($employeeId);

            // Validate that total effort equals 100%
            $totalEffort = array_sum(array_column($validated['allocations'], 'level_of_effort'));
            if ($totalEffort != 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Total effort must equal 100%',
                    'current_total' => $totalEffort
                ], 422);
            }

            DB::beginTransaction();

            // Deactivate all existing allocations for this employee
            EmployeeGrantAllocation::where('employee_id', $employeeId)
                ->where('active', true)
                ->update([
                    'active' => false,
                    'updated_by' => $currentUser,
                    'updated_at' => now()
                ]);

            // Create new allocations
            $createdAllocations = [];
            foreach ($validated['allocations'] as $allocationData) {
                $allocation = EmployeeGrantAllocation::create([
                    'employee_id' => $employeeId,
                    'position_slot_id' => $allocationData['position_slot_id'],
                    'level_of_effort' => $allocationData['level_of_effort'],
                    'start_date' => $validated['start_date'],
                    'end_date' => $validated['end_date'] ?? null,
                    'created_by' => $currentUser,
                    'updated_by' => $currentUser,
                ]);

                $createdAllocations[] = $allocation;
            }

            DB::commit();

            // Load the created allocations with relationships
            $allocationsWithRelations = EmployeeGrantAllocation::with([
                'employee:id,staff_id,first_name_en,last_name_en',
                'positionSlot.grantItem.grant:id,name,code',
                'positionSlot.budgetLine:id,budget_line_code,description',
                'employment:id,employment_type,start_date'
            ])->whereIn('id', collect($createdAllocations)->pluck('id'))->get();

            return response()->json([
                'success' => true,
                'message' => 'Employee grant allocations updated successfully',
                'data' => EmployeeGrantAllocationResource::collection($allocationsWithRelations),
                'total_created' => count($createdAllocations)
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee grant allocations',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
