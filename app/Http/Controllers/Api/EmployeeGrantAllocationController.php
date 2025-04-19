<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EmployeeGrantAllocation;
use App\Http\Resources\EmployeeGrantAllocationResource;
use App\Http\Requests\EmployeeGrantAllocationRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Models\GrantItem;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="EmployeeGrantAllocations",
 *     description="API Endpoints for Employee Grant Allocations"
 * )
 */

class EmployeeGrantAllocationController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @OA\Get(
     *     path="/employee-grant-allocations",
     *     summary="Get all employee grant allocations",
     *     tags={"EmployeeGrantAllocations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee grant allocations retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/EmployeeGrantAllocation")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve employee grant allocations"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function index()
    {
        try {
            $employeeGrantAllocations = EmployeeGrantAllocation::all();

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

    /**
     * Store a newly created resource in storage.
     *
     * @OA\Post(
     *     path="/employee-grant-allocations",
     *     summary="Create a new employee grant allocation",
     *     tags={"EmployeeGrantAllocations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/EmployeeGrantAllocation")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee grant allocation created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/EmployeeGrantAllocation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to create employee grant allocation"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function store(EmployeeGrantAllocationRequest $request)
    {
        try {
            $validated = $request->validated();

            // Get the grant item to check its position number
            $grantItem = GrantItem::findOrFail($validated['grant_items_id']);

            // Count existing allocations for this grant
            $existingAllocationsCount = EmployeeGrantAllocation::where('grant_items_id', $validated['grant_items_id'])
                ->where('active', true)
                ->count();

            // Check if we've reached the position limit for this grant
            if ($existingAllocationsCount >= $grantItem->grant_position_number) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot add more allocations. Grant position limit reached.'
                ], 400);
            }

            $employeeGrantAllocation = EmployeeGrantAllocation::create($validated);

            return (new EmployeeGrantAllocationResource($employeeGrantAllocation))
                ->additional([
                    'success' => true,
                    'message' => 'Employee grant allocation created successfully'
                ])
                ->response()
                ->setStatusCode(201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee grant allocation',
                'error' => $e->getMessage()
            ], 500);
        }
    }



    /**
     * Display the specified resource.
     *
     * @OA\Get(
     *     path="/employee-grant-allocations/{id}",
     *     summary="Get an employee grant allocation by ID",
     *     tags={"EmployeeGrantAllocations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Grant Item ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grant item allocations retrieved successfully"),
     *             @OA\Property(property="grant_item", type="object"),
     *             @OA\Property(property="grant_details", type="object"),
     *             @OA\Property(property="total_allocations", type="integer", example=3),
     *             @OA\Property(property="employees", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Resource not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Grant item not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve grant item allocations"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        try {
            // Check if the grant item exists
            $grantItem = GrantItem::with('grant')->findOrFail($id);

            // Get the grant details
            $grantDetails = $grantItem->grant;

            // Get all employee allocations for this grant item
            $employeeAllocations = EmployeeGrantAllocation::where('grant_items_id', $id)
                ->where('active', true)
                ->with('employeeAllocation:id,staff_id,first_name_en,last_name_en')
                ->get();

            // Count total allocations for this grant item
            $totalAllocations = $employeeAllocations->count();

            // Extract employee data
            $employees = $employeeAllocations->map(function($allocation) {
                return $allocation->employeeAllocation;
            });

            // Get the first allocation to return as the primary data object
            // If no allocations exist, we'll still return the grant item info
            $primaryAllocation = $employeeAllocations->first();

            $responseData = [
                'success' => true,
                'message' => 'Grant item allocations retrieved successfully',
                'grant_item' => $grantItem,
                'grant_details' => $grantDetails,
                'total_allocations' => $totalAllocations,
                'employees' => $employees
            ];

            if ($primaryAllocation) {
                return (new EmployeeGrantAllocationResource($primaryAllocation))
                    ->additional($responseData)
                    ->response()
                    ->setStatusCode(200);
            } else {
                return response()->json($responseData, 200);
            }

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Grant item not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve grant item allocations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @OA\Put(
     *     path="/employee-grant-allocations/{id}",
     *     summary="Update an employee grant allocation by ID",
     *     tags={"EmployeeGrantAllocations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Employee Grant Allocation ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/EmployeeGrantAllocation")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee grant allocation updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/EmployeeGrantAllocation")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Resource not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Employee grant allocation not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to update employee grant allocation"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function update(EmployeeGrantAllocationRequest $request, $id)
    {
        try {
            $employeeGrantAllocation = EmployeeGrantAllocation::findOrFail($id);
            $validated = $request->validated();
            $employeeGrantAllocation->update($validated);

            return (new EmployeeGrantAllocationResource($employeeGrantAllocation))
                ->additional([
                    'success' => true,
                    'message' => 'Employee grant allocation updated successfully'
                ])
                ->response()
                ->setStatusCode(200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee grant allocation not found'
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee grant allocation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @OA\Delete(
     *     path="/employee-grant-allocations/{id}",
     *     summary="Delete an employee grant allocation by ID",
     *     tags={"EmployeeGrantAllocations"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Employee Grant Allocation ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee grant allocation deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Resource not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Employee grant allocation not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to delete employee grant allocation"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        try {
            $employeeGrantAllocation = EmployeeGrantAllocation::findOrFail($id);
            $employeeGrantAllocation->delete();

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
}
