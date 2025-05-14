<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payroll;
use Illuminate\Support\Facades\Validator;
use OpenApi\Annotations as OA;


/**
 * @OA\Tag(
 *     name="Payrolls",
 *     description="API Endpoints for managing payrolls"
 * )
 */
class PayrollController extends Controller
{

    public function getEmployeeEmploymentDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'staff_id' => 'required|exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $employeeId = $request->input('staff_id');
        $employee = Employee::find($employeeId);
        return response()->json($employee);
    }


    /**
     * @OA\Get(
     *     path="/payrolls",
     *     summary="Get all payrolls",
     *     description="Get a list of all payrolls",
     *     tags={"Payrolls"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Payrolls retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payrolls retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/Payroll")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve payrolls"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function index()
    {
        try {
            $payrolls = Payroll::with('employee')->get();

            return response()->json([
                'success' => true,
                'message' => 'Payrolls retrieved successfully',
                'data' => $payrolls
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payrolls',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/payrolls",
     *     summary="Create a new payroll",
     *     description="Create a new payroll record",
     *     tags={"Payrolls"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "pay_period_date", "basic_salary"},
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="pay_period_date", type="string", format="date", example="2023-05-31"),
     *             @OA\Property(property="basic_salary", type="number", format="float", example=50000),
     *             @OA\Property(property="salary_by_FTE", type="number", format="float", example=50000),
     *             @OA\Property(property="compensation_refund", type="number", format="float", example=0),
     *             @OA\Property(property="thirteen_month_salary", type="number", format="float", example=4166.67),
     *             @OA\Property(property="pvd", type="number", format="float", example=2500),
     *             @OA\Property(property="saving_fund", type="number", format="float", example=1000),
     *             @OA\Property(property="employer_social_security", type="number", format="float", example=750),
     *             @OA\Property(property="employee_social_security", type="number", format="float", example=750),
     *             @OA\Property(property="employer_health_welfare", type="number", format="float", example=500),
     *             @OA\Property(property="employee_health_welfare", type="number", format="float", example=500),
     *             @OA\Property(property="tax", type="number", format="float", example=2500),
     *             @OA\Property(property="grand_total_income", type="number", format="float", example=55166.67),
     *             @OA\Property(property="grand_total_deduction", type="number", format="float", example=3750),
     *             @OA\Property(property="net_paid", type="number", format="float", example=51416.67),
     *             @OA\Property(property="employer_contribution_total", type="number", format="float", example=3750),
     *             @OA\Property(property="two_sides", type="number", format="float", example=7500),
     *             @OA\Property(property="payslip_date", type="string", format="date", example="2023-06-05"),
     *             @OA\Property(property="payslip_number", type="string", example="PS-2023-05-001"),
     *             @OA\Property(property="staff_signature", type="string", example="signature.png"),
     *             @OA\Property(property="created_by", type="string", example="admin@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Payroll created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payroll created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Payroll")
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
     *             @OA\Property(property="message", type="string", example="Failed to create payroll"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|exists:employees,id',
                'pay_period_date' => 'required|date',
                'basic_salary' => 'required|numeric',
                'salary_by_FTE' => 'required|numeric',
                'compensation_refund' => 'required|numeric',
                'thirteen_month_salary' => 'required|numeric',
                'pvd' => 'required|numeric',
                'saving_fund' => 'required|numeric',
                'employer_social_security' => 'required|numeric',
                'employee_social_security' => 'required|numeric',
                'employer_health_welfare' => 'required|numeric',
                'employee_health_welfare' => 'required|numeric',
                'tax' => 'required|numeric',
                'grand_total_income' => 'required|numeric',
                'grand_total_deduction' => 'required|numeric',
                'net_paid' => 'required|numeric',
                'employer_contribution_total' => 'required|numeric',
                'two_sides' => 'required|numeric',
                'payslip_date' => 'nullable|date',
                'payslip_number' => 'nullable|string|max:50',
                'staff_signature' => 'nullable|string|max:200',
                'created_by' => 'nullable|string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $payroll = Payroll::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Payroll created successfully',
                'data' => $payroll
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payroll',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/payrolls/{id}",
     *     summary="Get a specific payroll",
     *     description="Get details of a specific payroll by ID",
     *     tags={"Payrolls"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Payroll ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payroll retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payroll retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Payroll")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Payroll not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Payroll not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to retrieve payroll"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function show(string $id)
    {
        try {
            $payroll = Payroll::with('employee')->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Payroll retrieved successfully',
                'data' => $payroll
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payroll not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payroll',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Put(
     *     path="/payrolls/{id}",
     *     summary="Update a payroll",
     *     description="Update an existing payroll record",
     *     tags={"Payrolls"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Payroll ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="pay_period_date", type="string", format="date", example="2023-05-31"),
     *             @OA\Property(property="basic_salary", type="number", format="float", example=52000),
     *             @OA\Property(property="salary_by_FTE", type="number", format="float", example=52000),
     *             @OA\Property(property="compensation_refund", type="number", format="float", example=0),
     *             @OA\Property(property="thirteen_month_salary", type="number", format="float", example=4333.33),
     *             @OA\Property(property="pvd", type="number", format="float", example=2600),
     *             @OA\Property(property="saving_fund", type="number", format="float", example=1000),
     *             @OA\Property(property="employer_social_security", type="number", format="float", example=750),
     *             @OA\Property(property="employee_social_security", type="number", format="float", example=750),
     *             @OA\Property(property="employer_health_welfare", type="number", format="float", example=500),
     *             @OA\Property(property="employee_health_welfare", type="number", format="float", example=500),
     *             @OA\Property(property="tax", type="number", format="float", example=2600),
     *             @OA\Property(property="grand_total_income", type="number", format="float", example=57333.33),
     *             @OA\Property(property="grand_total_deduction", type="number", format="float", example=3850),
     *             @OA\Property(property="net_paid", type="number", format="float", example=53483.33),
     *             @OA\Property(property="employer_contribution_total", type="number", format="float", example=3850),
     *             @OA\Property(property="two_sides", type="number", format="float", example=7700),
     *             @OA\Property(property="payslip_date", type="string", format="date", example="2023-06-05"),
     *             @OA\Property(property="payslip_number", type="string", example="PS-2023-05-001"),
     *             @OA\Property(property="staff_signature", type="string", example="signature.png"),
     *             @OA\Property(property="updated_by", type="string", example="admin@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payroll updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payroll updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Payroll")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Payroll not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Payroll not found")
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
     *             @OA\Property(property="message", type="string", example="Failed to update payroll"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function update(Request $request, string $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_id' => 'sometimes|required|exists:employees,id',
                'pay_period_date' => 'sometimes|required|date',
                'basic_salary' => 'sometimes|required|numeric',
                'salary_by_FTE' => 'sometimes|required|numeric',
                'compensation_refund' => 'sometimes|required|numeric',
                'thirteen_month_salary' => 'sometimes|required|numeric',
                'pvd' => 'sometimes|required|numeric',
                'saving_fund' => 'sometimes|required|numeric',
                'employer_social_security' => 'sometimes|required|numeric',
                'employee_social_security' => 'sometimes|required|numeric',
                'employer_health_welfare' => 'sometimes|required|numeric',
                'employee_health_welfare' => 'sometimes|required|numeric',
                'tax' => 'sometimes|required|numeric',
                'grand_total_income' => 'sometimes|required|numeric',
                'grand_total_deduction' => 'sometimes|required|numeric',
                'net_paid' => 'sometimes|required|numeric',
                'employer_contribution_total' => 'sometimes|required|numeric',
                'two_sides' => 'sometimes|required|numeric',
                'payslip_date' => 'nullable|date',
                'payslip_number' => 'nullable|string|max:50',
                'staff_signature' => 'nullable|string|max:200',
                'updated_by' => 'nullable|string|max:100'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $payroll = Payroll::findOrFail($id);
            $payroll->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Payroll updated successfully',
                'data' => $payroll
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payroll not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payroll',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Delete(
     *     path="/payrolls/{id}",
     *     summary="Delete a payroll",
     *     description="Delete a specific payroll record",
     *     tags={"Payrolls"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Payroll ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payroll deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payroll deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Payroll not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Payroll not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to delete payroll"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function destroy(string $id)
    {
        try {
            $payroll = Payroll::findOrFail($id);
            $payroll->delete();

            return response()->json([
                'success' => true,
                'message' => 'Payroll deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payroll not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payroll',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
