<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployeeBeneficiaryResource;
use App\Models\EmployeeBeneficiary;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Employee Beneficiaries",
 *     description="API Endpoints for Employee Beneficiary management"
 * )
 */
class EmployeeBeneficiaryController extends Controller
{
    /**
     * List all employee beneficiaries
     *
     * @OA\Get(
     *     path="/employee-beneficiaries",
     *     summary="Get all employee beneficiaries",
     *     tags={"Employee Beneficiaries"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/EmployeeBeneficiary")),
     *             @OA\Property(property="message", type="string", example="Employee beneficiaries retrieved successfully")
     *         )
     *     )
     * )
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $employeeBeneficiaries = EmployeeBeneficiary::with('employee')->get();

        return response()->json([
            'status' => 'success',
            'data' => EmployeeBeneficiaryResource::collection($employeeBeneficiaries),
            'message' => 'Employee beneficiaries retrieved successfully',
        ], 200);
    }

    /**
     * Create a new employee beneficiary record
     *
     * @OA\Post(
     *     path="/employee-beneficiaries",
     *     summary="Create a new employee beneficiary",
     *     tags={"Employee Beneficiaries"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="beneficiary_name", type="string", example="John Doe"),
     *             @OA\Property(property="beneficiary_relationship", type="string", example="spouse"),
     *             @OA\Property(property="phone_number", type="string", example="+1234567890"),
     *             @OA\Property(property="created_by", type="string", example="admin"),
     *             @OA\Property(property="updated_by", type="string", example="admin", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Employee beneficiary created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/EmployeeBeneficiary"),
     *             @OA\Property(property="message", type="string", example="Employee beneficiary created successfully")
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
            'beneficiary_name' => 'required|string|max:255',
            'beneficiary_relationship' => 'required|string|max:100',
            'phone_number' => 'nullable|string|max:20',
            'created_by' => 'nullable|string|max:100',
            'updated_by' => 'nullable|string|max:100',
        ]);

        $employeeBeneficiary = EmployeeBeneficiary::create($validatedData);
        $employeeBeneficiary->load('employee');

        return response()->json([
            'status' => 'success',
            'data' => new EmployeeBeneficiaryResource($employeeBeneficiary),
            'message' => 'Employee beneficiary created successfully',
        ], 201);
    }

    /**
     * Show a specific employee beneficiary record
     *
     * @OA\Get(
     *     path="/employee-beneficiaries/{id}",
     *     summary="Get employee beneficiary by ID",
     *     tags={"Employee Beneficiaries"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Employee beneficiary ID",
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
     *             @OA\Property(property="data", ref="#/components/schemas/EmployeeBeneficiary"),
     *             @OA\Property(property="message", type="string", example="Employee beneficiary retrieved successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Employee beneficiary not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Employee beneficiary not found")
     *         )
     *     )
     * )
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $employeeBeneficiary = EmployeeBeneficiary::with('employee')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => new EmployeeBeneficiaryResource($employeeBeneficiary),
            'message' => 'Employee beneficiary retrieved successfully',
        ], 200);
    }

    /**
     * Update an existing employee beneficiary record
     *
     * @OA\Put(
     *     path="/employee-beneficiaries/{id}",
     *     summary="Update an employee beneficiary",
     *     tags={"Employee Beneficiaries"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Employee beneficiary ID",
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
     *             @OA\Property(property="beneficiary_name", type="string", example="John Doe"),
     *             @OA\Property(property="beneficiary_relationship", type="string", example="spouse"),
     *             @OA\Property(property="phone_number", type="string", example="+1234567890"),
     *             @OA\Property(property="created_by", type="string", example="admin"),
     *             @OA\Property(property="updated_by", type="string", example="admin", nullable=true)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Employee beneficiary updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/EmployeeBeneficiary"),
     *             @OA\Property(property="message", type="string", example="Employee beneficiary updated successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Employee beneficiary not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Employee beneficiary not found")
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
        $employeeBeneficiary = EmployeeBeneficiary::findOrFail($id);

        $validatedData = $request->validate([
            'employee_id' => 'sometimes|required|integer|exists:employees,id',
            'beneficiary_name' => 'sometimes|required|string|max:255',
            'beneficiary_relationship' => 'sometimes|required|string|max:100',
            'phone_number' => 'sometimes|nullable|string|max:20',
            'created_by' => 'sometimes|nullable|string|max:100',
            'updated_by' => 'sometimes|nullable|string|max:100',
        ]);

        $employeeBeneficiary->update($validatedData);
        $employeeBeneficiary->load('employee');

        return response()->json([
            'status' => 'success',
            'data' => new EmployeeBeneficiaryResource($employeeBeneficiary),
            'message' => 'Employee beneficiary updated successfully',
        ], 200);
    }

    /**
     * Delete an employee beneficiary record
     *
     * @OA\Delete(
     *     path="/employee-beneficiaries/{id}",
     *     summary="Delete an employee beneficiary",
     *     tags={"Employee Beneficiaries"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Employee beneficiary ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Employee beneficiary deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Employee beneficiary deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Employee beneficiary not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Employee beneficiary not found")
     *         )
     *     )
     * )
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $employeeBeneficiary = EmployeeBeneficiary::findOrFail($id);
        $employeeBeneficiary->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Employee beneficiary deleted successfully',
        ], 200);
    }
}
