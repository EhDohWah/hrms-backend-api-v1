<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeChild;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

class EmployeeChildrenController extends Controller
{
    /**
     * List all employee children
     *
     * @OA\Get(
     *     path="/employee-children",
     *     summary="Get all employee children",
     *     tags={"Employee Children"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EmployeeChild")),
     *             @OA\Property(property="message", type="string", example="Employee children retrieved successfully")
     *         )
     *     )
     * )
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $employeeChildren = EmployeeChild::all();

        return response()->json([
            'status' => 'success',
            'data' => $employeeChildren,
            'message' => 'Employee children retrieved successfully',
        ], 200);
    }

    /**
     * Create a new employee child record
     *
     * @OA\Post(
     *     path="/employee-children",
     *     summary="Create a new employee child",
     *     tags={"Employee Children"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="2020-01-01"),
     *             @OA\Property(property="created_by", type="string", example="admin"),
     *             @OA\Property(property="updated_by", type="string", example="admin", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Employee child created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/EmployeeChild"),
     *             @OA\Property(property="message", type="string", example="Employee child created successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'name' => 'required|string|max:100',
            'date_of_birth' => 'required|date',
            'created_by' => 'nullable|string|max:100',
            'updated_by' => 'nullable|string|max:100',
        ]);

        $employeeChild = EmployeeChild::create($validatedData);

        return response()->json([
            'status' => 'success',
            'data' => $employeeChild,
            'message' => 'Employee child created successfully',
        ], 201);
    }

    /**
     * Show a specific employee child record
     *
     * @OA\Get(
     *     path="/employee-children/{id}",
     *     summary="Get employee child by ID",
     *     tags={"Employee Children"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Employee child ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/EmployeeChild"),
     *             @OA\Property(property="message", type="string", example="Employee child retrieved successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Employee child not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Employee child not found")
     *         )
     *     )
     * )
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $employeeChild = EmployeeChild::findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $employeeChild,
            'message' => 'Employee child retrieved successfully',
        ], 200);
    }

    /**
     * Update an existing employee child record
     *
     * @OA\Put(
     *     path="/employee-children/{id}",
     *     summary="Update an employee child",
     *     tags={"Employee Children"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Employee child ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="date_of_birth", type="string", format="date", example="2020-01-01"),
     *             @OA\Property(property="created_by", type="string", example="admin"),
     *             @OA\Property(property="updated_by", type="string", example="admin", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Employee child updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/EmployeeChild"),
     *             @OA\Property(property="message", type="string", example="Employee child updated successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Employee child not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Employee child not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Validation error"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $employeeChild = EmployeeChild::findOrFail($id);

        $validatedData = $request->validate([
            'employee_id' => 'sometimes|required|integer|exists:employees,id',
            'name' => 'sometimes|required|string|max:100',
            'date_of_birth' => 'sometimes|required|date',
            'created_by' => 'sometimes|nullable|string|max:100',
            'updated_by' => 'sometimes|nullable|string|max:100',
        ]);

        $employeeChild->update($validatedData);

        return response()->json([
            'status' => 'success',
            'data' => $employeeChild,
            'message' => 'Employee child updated successfully',
        ], 200);
    }

    /**
     * Delete an employee child record
     *
     * @OA\Delete(
     *     path="/employee-children/{id}",
     *     summary="Delete an employee child",
     *     tags={"Employee Children"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Employee child ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Employee child deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Employee child deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Employee child not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Employee child not found")
     *         )
     *     )
     * )
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $employeeChild = EmployeeChild::findOrFail($id);
        $employeeChild->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Employee child deleted successfully',
        ], 200);
    }
}
