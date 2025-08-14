<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payroll;
use App\Models\Employee;
use App\Services\TaxCalculationService;
use App\Http\Resources\PayrollCalculationResource;
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
    /**
     * @OA\Get(
     *     path="/payrolls/employee-employment",
     *     summary="Get employee employment details",
     *     description="Get employment details for a specific employee including employment info, work location, and grant allocations",
     *     tags={"Payrolls"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         required=true,
     *         description="Employee ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Employee employment details retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee employment details retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(
     *                     property="employment",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(
     *                         property="workLocation",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Main Office")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="employeeGrantAllocations",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(
     *                             property="grantItemAllocation",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(
     *                                 property="grant",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="name", type="string", example="Annual Bonus")
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="employee_id",
     *                     type="array",
     *                     @OA\Items(type="string", example="The employee id field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Employee not found")
     *         )
     *     )
     * )
     */
    public function getEmployeeEmploymentDetail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $employeeId = $request->input('employee_id');
        $employee = Employee::with([
            'employment',
            'employment.departmentPosition',
            'employment.workLocation',
            'employeeGrantAllocations',
            'employeeGrantAllocations.grantItemAllocation',
            'employeeGrantAllocations.grantItemAllocation.grant'
        ])->find($employeeId);

        if (!$employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee employment details retrieved successfully',
            'data' => $employee
        ]);
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

    /**
     * @OA\Post(
     *     path="/payrolls/calculate",
     *     summary="Calculate payroll with automated tax calculations",
     *     description="Calculate complete payroll including automated tax calculations, deductions, and social security",
     *     tags={"Payrolls"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employee_id", "gross_salary", "pay_period_date"},
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="gross_salary", type="number", example=50000),
     *             @OA\Property(property="pay_period_date", type="string", format="date", example="2025-01-31"),
     *             @OA\Property(property="tax_year", type="integer", example=2025),
     *             @OA\Property(
     *                 property="additional_income",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="type", type="string", example="bonus"),
     *                     @OA\Property(property="amount", type="number", example=5000),
     *                     @OA\Property(property="description", type="string", example="Performance bonus")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="additional_deductions",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="type", type="string", example="loan"),
     *                     @OA\Property(property="amount", type="number", example=2000),
     *                     @OA\Property(property="description", type="string", example="Company loan repayment")
     *                 )
     *             ),
     *             @OA\Property(property="save_payroll", type="boolean", example=false, description="Whether to save the calculated payroll")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payroll calculated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payroll calculated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/PayrollCalculation")
     *         )
     *     )
     * )
     */
    public function calculatePayroll(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|exists:employees,id',
                'gross_salary' => 'required|numeric|min:0',
                'pay_period_date' => 'required|date',
                'tax_year' => 'nullable|integer|min:2000|max:2100',
                'additional_income' => 'nullable|array',
                'additional_income.*.type' => 'required_with:additional_income|string',
                'additional_income.*.amount' => 'required_with:additional_income|numeric|min:0',
                'additional_income.*.description' => 'nullable|string',
                'additional_deductions' => 'nullable|array',
                'additional_deductions.*.type' => 'required_with:additional_deductions|string',
                'additional_deductions.*.amount' => 'required_with:additional_deductions|numeric|min:0',
                'additional_deductions.*.description' => 'nullable|string',
                'save_payroll' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Initialize tax calculation service
            $taxYear = $request->get('tax_year', date('Y'));
            $taxService = new TaxCalculationService($taxYear);

            // Calculate payroll
            $payrollData = $taxService->calculatePayroll(
                $request->employee_id,
                $request->gross_salary,
                $request->get('additional_income', []),
                $request->get('additional_deductions', [])
            );

            // Add pay period date to the calculation
            $payrollData['pay_period_date'] = $request->pay_period_date;

            // Save payroll if requested
            if ($request->boolean('save_payroll', false)) {
                $employee = Employee::with('employment')->findOrFail($request->employee_id);
                
                $savedPayroll = Payroll::create([
                    'employment_id' => $employee->employment->id,
                    'employee_funding_allocation_id' => $employee->employeeFundingAllocations()->first()?->id,
                    'gross_salary' => $payrollData['gross_salary'],
                    'gross_salary_by_FTE' => $payrollData['gross_salary'], // Assuming same for now
                    'compensation_refund' => 0, // Can be added as additional income
                    'thirteen_month_salary' => $payrollData['gross_salary'] / 12, // 1/12 of annual
                    'thirteen_month_salary_accured' => $payrollData['gross_salary'] / 12,
                    'pvd' => $payrollData['deductions']['provident_fund'],
                    'saving_fund' => 0, // Can be added as additional deduction
                    'employer_social_security' => $payrollData['social_security']['employer_contribution'],
                    'employee_social_security' => $payrollData['social_security']['employee_contribution'],
                    'employer_health_welfare' => 0, // Can be configured
                    'employee_health_welfare' => 0, // Can be configured
                    'tax' => $payrollData['income_tax'],
                    'net_salary' => $payrollData['net_salary'],
                    'total_salary' => $payrollData['gross_salary'],
                    'total_pvd' => $payrollData['deductions']['provident_fund'],
                    'total_saving_fund' => 0,
                    'salary_bonus' => array_sum(array_column($request->get('additional_income', []), 'amount')),
                    'total_income' => $payrollData['total_income'],
                    'employer_contribution' => $payrollData['social_security']['employer_contribution'],
                    'total_deduction' => $payrollData['income_tax'] + $payrollData['social_security']['employee_contribution'],
                    'pay_period_date' => $request->pay_period_date,
                    'notes' => 'Automatically calculated using tax system'
                ]);

                $payrollData['saved_payroll_id'] = $savedPayroll->id;
            }

            return response()->json([
                'success' => true,
                'message' => 'Payroll calculated successfully',
                'data' => new PayrollCalculationResource($payrollData)
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate payroll',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/payrolls/bulk-calculate",
     *     summary="Calculate payroll for multiple employees",
     *     description="Calculate payroll for multiple employees with automated tax calculations",
     *     tags={"Payrolls"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"employees", "pay_period_date"},
     *             @OA\Property(property="pay_period_date", type="string", format="date", example="2025-01-31"),
     *             @OA\Property(property="tax_year", type="integer", example=2025),
     *             @OA\Property(
     *                 property="employees",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="employee_id", type="integer", example=1),
     *                     @OA\Property(property="gross_salary", type="number", example=50000),
     *                     @OA\Property(property="additional_income", type="array", @OA\Items(type="object")),
     *                     @OA\Property(property="additional_deductions", type="array", @OA\Items(type="object"))
     *                 )
     *             ),
     *             @OA\Property(property="save_payrolls", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Bulk payroll calculation completed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Bulk payroll calculation completed"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function bulkCalculatePayroll(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'pay_period_date' => 'required|date',
                'tax_year' => 'nullable|integer|min:2000|max:2100',
                'employees' => 'required|array|min:1',
                'employees.*.employee_id' => 'required|exists:employees,id',
                'employees.*.gross_salary' => 'required|numeric|min:0',
                'employees.*.additional_income' => 'nullable|array',
                'employees.*.additional_deductions' => 'nullable|array',
                'save_payrolls' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $taxYear = $request->get('tax_year', date('Y'));
            $taxService = new TaxCalculationService($taxYear);
            $results = [];
            $savedPayrolls = [];
            $errors = [];

            foreach ($request->employees as $employeeData) {
                try {
                    $payrollData = $taxService->calculatePayroll(
                        $employeeData['employee_id'],
                        $employeeData['gross_salary'],
                        $employeeData['additional_income'] ?? [],
                        $employeeData['additional_deductions'] ?? []
                    );

                    $payrollData['pay_period_date'] = $request->pay_period_date;
                    $results[] = [
                        'employee_id' => $employeeData['employee_id'],
                        'calculation' => new PayrollCalculationResource($payrollData)
                    ];

                    // Save if requested
                    if ($request->boolean('save_payrolls', false)) {
                        $employee = Employee::with('employment')->findOrFail($employeeData['employee_id']);
                        
                        $savedPayroll = Payroll::create([
                            'employment_id' => $employee->employment->id,
                            'employee_funding_allocation_id' => $employee->employeeFundingAllocations()->first()?->id,
                            'gross_salary' => $payrollData['gross_salary'],
                            'gross_salary_by_FTE' => $payrollData['gross_salary'],
                            'compensation_refund' => 0,
                            'thirteen_month_salary' => $payrollData['gross_salary'] / 12,
                            'thirteen_month_salary_accured' => $payrollData['gross_salary'] / 12,
                            'pvd' => $payrollData['deductions']['provident_fund'],
                            'saving_fund' => 0,
                            'employer_social_security' => $payrollData['social_security']['employer_contribution'],
                            'employee_social_security' => $payrollData['social_security']['employee_contribution'],
                            'employer_health_welfare' => 0,
                            'employee_health_welfare' => 0,
                            'tax' => $payrollData['income_tax'],
                            'net_salary' => $payrollData['net_salary'],
                            'total_salary' => $payrollData['gross_salary'],
                            'total_pvd' => $payrollData['deductions']['provident_fund'],
                            'total_saving_fund' => 0,
                            'salary_bonus' => array_sum(array_column($employeeData['additional_income'] ?? [], 'amount')),
                            'total_income' => $payrollData['total_income'],
                            'employer_contribution' => $payrollData['social_security']['employer_contribution'],
                            'total_deduction' => $payrollData['income_tax'] + $payrollData['social_security']['employee_contribution'],
                            'pay_period_date' => $request->pay_period_date,
                            'notes' => 'Bulk calculated using tax system'
                        ]);

                        $savedPayrolls[] = $savedPayroll->id;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'employee_id' => $employeeData['employee_id'],
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Bulk payroll calculation completed',
                'data' => [
                    'successful_calculations' => count($results),
                    'errors' => count($errors),
                    'calculations' => $results,
                    'error_details' => $errors,
                    'saved_payroll_ids' => $savedPayrolls
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process bulk payroll calculation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/payrolls/tax-summary/{id}",
     *     summary="Get tax summary for a payroll",
     *     description="Get detailed tax calculation summary for a specific payroll",
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
     *         description="Tax summary retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax summary retrieved successfully"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function getTaxSummary(string $id)
    {
        try {
            $payroll = Payroll::with(['employment.employee'])->findOrFail($id);
            $employee = $payroll->employment->employee;
            
            $taxYear = date('Y', strtotime($payroll->pay_period_date));
            $taxService = new TaxCalculationService($taxYear);

            // Recalculate to get detailed breakdown
            $calculation = $taxService->calculatePayroll(
                $employee->id,
                floatval($payroll->gross_salary),
                [], // No additional income data stored
                []  // No additional deductions data stored
            );

            return response()->json([
                'success' => true,
                'message' => 'Tax summary retrieved successfully',
                'data' => [
                    'payroll_id' => $payroll->id,
                    'employee' => [
                        'id' => $employee->id,
                        'name' => $employee->first_name_en . ' ' . $employee->last_name_en,
                        'staff_id' => $employee->staff_id
                    ],
                    'pay_period' => $payroll->pay_period_date,
                    'tax_calculation' => new PayrollCalculationResource($calculation)
                ]
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payroll not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tax summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
