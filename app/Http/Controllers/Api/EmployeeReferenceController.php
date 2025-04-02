<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeReference;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Employee References",
 *     description="API Endpoints for managing employee references"
 * )
 */
class EmployeeReferenceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/employee-references",
     *     summary="Get all employee references",
     *     description="Returns a list of all employee references",
     *     operationId="getEmployeeReferences",
     *     tags={"Employee References"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee references retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/EmployeeReference")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function index()
    {
        try {
            $references = EmployeeReference::all();
            return response()->json([
                'success' => true,
                'message' => 'Employee references retrieved successfully',
                'data' => $references
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee references',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/employee-references",
     *     summary="Create a new employee reference",
     *     description="Creates a new employee reference and returns the created resource",
     *     operationId="storeEmployeeReference",
     *     tags={"Employee References"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/EmployeeReference")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Successfully created",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee reference created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/EmployeeReference")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'referee_name'   => 'required|string|max:200',
                'occupation'     => 'required|string|max:200',
                'candidate_name' => 'required|string|max:100',
                'relation'       => 'required|string|max:200',
                'address'        => 'required|string|max:200',
                'phone_number'   => 'required|string|max:50',
                'email'          => 'required|email|max:200',
                'created_by'     => 'string|max:100',
                'updated_by'     => 'string|max:100',
            ]);

            $validatedData['created_by'] = auth()->user()->name;
            $validatedData['updated_by'] = auth()->user()->name;

            $employeeReference = EmployeeReference::create($validatedData);
            return response()->json([
                'success' => true,
                'message' => 'Employee reference created successfully',
                'data' => $employeeReference
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create employee reference',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/employee-references/{id}",
     *     summary="Get a specific employee reference",
     *     description="Returns a specific employee reference by ID",
     *     operationId="showEmployeeReference",
     *     tags={"Employee References"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the employee reference",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee reference retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/EmployeeReference")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function show($id)
    {
        try {
            $reference = EmployeeReference::findOrFail($id);
            return response()->json([
                'success' => true,
                'message' => 'Employee reference retrieved successfully',
                'data' => $reference
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee reference not found',
                'error' => 'Resource with ID ' . $id . ' not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve employee reference',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/employee-references/{id}",
     *     summary="Update an employee reference",
     *     description="Updates an existing employee reference and returns the updated resource",
     *     operationId="updateEmployeeReference",
     *     tags={"Employee References"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the employee reference to update",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/EmployeeReference")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully updated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee reference updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/EmployeeReference")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad request"),
     *     @OA\Response(response=404, description="Resource not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function update(Request $request, $id)
    {
        try {
            $reference = EmployeeReference::findOrFail($id);

            $validatedData = $request->validate([
                'referee_name'   => 'sometimes|required|string|max:200',
                'occupation'     => 'sometimes|required|string|max:200',
                'candidate_name' => 'sometimes|required|string|max:100',
                'relation'       => 'sometimes|required|string|max:200',
                'address'        => 'sometimes|required|string|max:200',
                'phone_number'   => 'sometimes|required|string|max:50',
                'email'          => 'sometimes|required|email|max:200',
                'created_by'     => 'sometimes|required|string|max:100',
                'updated_by'     => 'sometimes|required|string|max:100',
            ]);

            $reference->update($validatedData);
            return response()->json([
                'success' => true,
                'message' => 'Employee reference updated successfully',
                'data' => $reference
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee reference not found',
                'error' => 'Resource with ID ' . $id . ' not found'
            ], 404);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update employee reference',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/employee-references/{id}",
     *     summary="Delete an employee reference",
     *     description="Deletes an employee reference",
     *     operationId="deleteEmployeeReference",
     *     tags={"Employee References"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="ID of the employee reference to delete",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully deleted",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee reference deleted successfully"),
     *             @OA\Property(property="data", type="null")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Resource not found"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     */
    public function destroy($id)
    {
        try {
            $reference = EmployeeReference::findOrFail($id);
            $reference->delete();
            return response()->json([
                'success' => true,
                'message' => 'Employee reference deleted successfully',
                'data' => null
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Employee reference not found',
                'error' => 'Resource with ID ' . $id . ' not found'
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete employee reference',
                'error' => $e->getMessage()
            ], 500);
        }
    }

}
