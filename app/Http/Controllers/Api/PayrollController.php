<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollCalculationResource;
use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
use App\Models\Payroll;
use App\Services\TaxCalculationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
     *     description="Get employment details for a specific employee including employment info, work location, funding allocations, and position slots",
     *     tags={"Payrolls"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         required=true,
     *         description="Employee ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Employee employment details retrieved successfully",
     *
     *         @OA\JsonContent(
     *
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
     *                     property="employeeFundingAllocations",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="object",
     *
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(
     *                             property="orgFunded",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(
     *                                 property="grant",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="name", type="string", example="Annual Bonus")
     *                             )
     *                         ),
     *                         @OA\Property(
     *                             property="positionSlot",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="title", type="string", example="Senior Developer")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(
     *                     property="employee_id",
     *                     type="array",
     *
     *                     @OA\Items(type="string", example="The employee id field is required.")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found",
     *
     *         @OA\JsonContent(
     *
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
                'errors' => $validator->errors(),
            ], 422);
        }

        $employeeId = $request->input('employee_id');
        $employee = Employee::with([
            'employment',
            'employment.departmentPosition',
            'employment.workLocation',
            'employeeFundingAllocations',
            'employeeFundingAllocations.orgFunded',
            'employeeFundingAllocations.orgFunded.grant',
            'employeeFundingAllocations.positionSlot',
        ])->find($employeeId);

        if (! $employee) {
            return response()->json([
                'success' => false,
                'message' => 'Employee not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Employee employment details retrieved successfully',
            'data' => $employee,
        ]);
    }

    /**
     * Get employee employment details with automatic payroll calculations for all funding allocations
     *
     * @OA\Get(
     *     path="/payrolls/employee-employment-calculated",
     *     summary="Get employee employment details with payroll calculations",
     *     description="Returns employee data with automatic payroll calculations for all funding allocations based on pay period date",
     *     operationId="getEmployeeEmploymentDetailWithCalculations",
     *     tags={"Payrolls"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         description="Employee ID",
     *         required=true,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="pay_period_date",
     *         in="query",
     *         description="Pay period date for calculations (YYYY-MM-DD format)",
     *         required=true,
     *         @OA\Schema(type="string", format="date", example="2025-01-31")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Employee employment details with calculations retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee employment details with calculations retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="employee", ref="#/components/schemas/Employee"),
     *                 @OA\Property(property="pay_period_date", type="string", format="date", example="2025-01-31"),
     *                 @OA\Property(
     *                     property="calculated_allocations",
     *                     type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="allocation_id", type="integer", example=1),
     *                         @OA\Property(property="staff_id", type="string", example="EMP001"),
     *                         @OA\Property(property="employee_name", type="string", example="John Doe"),
     *                         @OA\Property(property="department", type="string", example="IT Department"),
     *                         @OA\Property(property="position", type="string", example="Senior Developer"),
     *                         @OA\Property(property="employment_type", type="string", example="Full-time"),
     *                         @OA\Property(property="fte_percentage", type="number", format="float", example=1.0),
     *                         @OA\Property(property="position_salary", type="number", format="float", example=50000),
     *                         @OA\Property(property="funding_source", type="string", example="Grant ABC"),
     *                         @OA\Property(property="funding_type", type="string", example="grant"),
     *                         @OA\Property(property="loe_percentage", type="number", format="float", example=0.5),
     *                         @OA\Property(
     *                             property="calculations",
     *                             type="object",
     *                             @OA\Property(property="basic_salary", type="number", format="float", example=50000),
     *                             @OA\Property(property="annual_increase", type="number", format="float", example=500),
     *                             @OA\Property(property="adjusted_salary", type="number", format="float", example=50500),
     *                             @OA\Property(property="salary_by_fte", type="number", format="float", example=25250),
     *                             @OA\Property(property="compensation_refund", type="number", format="float", example=0),
     *                             @OA\Property(property="thirteen_month_salary", type="number", format="float", example=2104.17),
     *                             @OA\Property(property="pvd_employee", type="number", format="float", example=757.5),
     *                             @OA\Property(property="pvd_employer", type="number", format="float", example=757.5),
     *                             @OA\Property(property="saving_fund", type="number", format="float", example=378.75),
     *                             @OA\Property(property="social_security_employee", type="number", format="float", example=750),
     *                             @OA\Property(property="social_security_employer", type="number", format="float", example=750),
     *                             @OA\Property(property="health_welfare_employee", type="number", format="float", example=0),
     *                             @OA\Property(property="health_welfare_employer", type="number", format="float", example=0),
     *                             @OA\Property(property="income_tax", type="number", format="float", example=1200),
     *                             @OA\Property(property="total_income", type="number", format="float", example=27354.17),
     *                             @OA\Property(property="total_deductions", type="number", format="float", example=2507.5),
     *                             @OA\Property(property="net_salary", type="number", format="float", example=24846.67),
     *                             @OA\Property(property="employer_contributions", type="number", format="float", example=1507.5)
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
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Employee not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to calculate employee employment details"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function getEmployeeEmploymentDetailWithCalculations(Request $request)
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|exists:employees,id',
                'pay_period_date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $employeeId = $request->input('employee_id');
            $payPeriodDate = Carbon::parse($request->input('pay_period_date'));

            // Load employee with all necessary relationships
            $employee = Employee::with([
                'employment',
                'employment.departmentPosition',
                'employment.workLocation',
                'employeeFundingAllocations',
                'employeeFundingAllocations.orgFunded',
                'employeeFundingAllocations.orgFunded.grant',
                'employeeFundingAllocations.positionSlot',
                'employeeChildren',
            ])->find($employeeId);

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found',
                ], 404);
            }

            // Check if employee has employment record
            if (!$employee->employment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee has no employment record',
                ], 404);
            }

            // Check if employee has funding allocations
            if ($employee->employeeFundingAllocations->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee has no funding allocations',
                ], 404);
            }

            // Calculate payroll for each funding allocation
            $calculatedAllocations = [];
            $taxCalculationService = new TaxCalculationService($payPeriodDate->year);

            foreach ($employee->employeeFundingAllocations as $allocation) {
                $calculatedAllocation = $this->calculateAllocationPayroll(
                    $employee,
                    $allocation,
                    $payPeriodDate,
                    $taxCalculationService
                );
                $calculatedAllocations[] = $calculatedAllocation;
            }

            return response()->json([
                'success' => true,
                'message' => 'Employee employment details with calculations retrieved successfully',
                'data' => [
                    'employee' => $employee,
                    'pay_period_date' => $payPeriodDate->format('Y-m-d'),
                    'calculated_allocations' => $calculatedAllocations,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate employee employment details',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/payrolls",
     *     summary="Get all payrolls",
     *     description="Get a list of all payrolls",
     *     tags={"Payrolls"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(
     *         response=200,
     *         description="Payrolls retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payrolls retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(ref="#/components/schemas/Payroll")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
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
                'data' => $payrolls,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payrolls',
                'error' => $e->getMessage(),
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
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"employee_id", "pay_period_date", "basic_salary"},
     *
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
     *
     *     @OA\Response(
     *         response=201,
     *         description="Payroll created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payroll created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Payroll")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
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
                'created_by' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $payroll = Payroll::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Payroll created successfully',
                'data' => $payroll,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payroll',
                'error' => $e->getMessage(),
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
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Payroll ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Payroll retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payroll retrieved successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Payroll")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Payroll not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Payroll not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
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
                'data' => $payroll,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payroll not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payroll',
                'error' => $e->getMessage(),
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
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Payroll ID",
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
     *
     *     @OA\Response(
     *         response=200,
     *         description="Payroll updated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payroll updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Payroll")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Payroll not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Payroll not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
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
                'updated_by' => 'nullable|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $payroll = Payroll::findOrFail($id);
            $payroll->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Payroll updated successfully',
                'data' => $payroll,
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payroll not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update payroll',
                'error' => $e->getMessage(),
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
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Payroll ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Payroll deleted successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payroll deleted successfully")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Payroll not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Payroll not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
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
                'message' => 'Payroll deleted successfully',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payroll not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete payroll',
                'error' => $e->getMessage(),
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
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"employee_id", "gross_salary", "pay_period_date"},
     *
     *             @OA\Property(property="employee_id", type="integer", example=1),
     *             @OA\Property(property="gross_salary", type="number", example=50000),
     *             @OA\Property(property="pay_period_date", type="string", format="date", example="2025-01-31"),
     *             @OA\Property(property="tax_year", type="integer", example=2025),
     *             @OA\Property(
     *                 property="additional_income",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="type", type="string", example="bonus"),
     *                     @OA\Property(property="amount", type="number", example=5000),
     *                     @OA\Property(property="description", type="string", example="Performance bonus")
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="additional_deductions",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="type", type="string", example="loan"),
     *                     @OA\Property(property="amount", type="number", example=2000),
     *                     @OA\Property(property="description", type="string", example="Company loan repayment")
     *                 )
     *             ),
     *             @OA\Property(property="save_payroll", type="boolean", example=false, description="Whether to save the calculated payroll")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Payroll calculated successfully",
     *
     *         @OA\JsonContent(
     *
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
                'save_payroll' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Initialize tax calculation service
            $taxYear = $request->get('tax_year', date('Y'));
            $taxService = new TaxCalculationService($taxYear);

            // Get employee data for tax calculation with relationships
            $employee = \App\Models\Employee::with(['employeeChildren'])->findOrFail($request->employee_id);
            $employeeData = [
                'has_spouse' => $employee->has_spouse,
                'children' => $employee->employeeChildren->count(),
                'eligible_parents' => $employee->eligible_parents_count,
                'employee_status' => $employee->status, // For provident fund calculation
                'pf_contribution_annual' => 0, // Legacy field, now calculated based on status
            ];

            // Calculate payroll using new method with global toggle control
            $payrollData = $taxService->calculateEmployeeTax(
                $request->gross_salary,
                $employeeData
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
                    'notes' => 'Automatically calculated using tax system',
                ]);

                $payrollData['saved_payroll_id'] = $savedPayroll->id;
            }

            return response()->json([
                'success' => true,
                'message' => 'Payroll calculated successfully',
                'data' => new PayrollCalculationResource($payrollData),
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate payroll',
                'error' => $e->getMessage(),
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
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"employees", "pay_period_date"},
     *
     *             @OA\Property(property="pay_period_date", type="string", format="date", example="2025-01-31"),
     *             @OA\Property(property="tax_year", type="integer", example=2025),
     *             @OA\Property(
     *                 property="employees",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="employee_id", type="integer", example=1),
     *                     @OA\Property(property="gross_salary", type="number", example=50000),
     *                     @OA\Property(property="additional_income", type="array", @OA\Items(type="object")),
     *                     @OA\Property(property="additional_deductions", type="array", @OA\Items(type="object"))
     *                 )
     *             ),
     *             @OA\Property(property="save_payrolls", type="boolean", example=false)
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Bulk payroll calculation completed",
     *
     *         @OA\JsonContent(
     *
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
                'save_payrolls' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
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
                        'calculation' => new PayrollCalculationResource($payrollData),
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
                            'notes' => 'Bulk calculated using tax system',
                        ]);

                        $savedPayrolls[] = $savedPayroll->id;
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'employee_id' => $employeeData['employee_id'],
                        'error' => $e->getMessage(),
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
                    'saved_payroll_ids' => $savedPayrolls,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process bulk payroll calculation',
                'error' => $e->getMessage(),
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
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Payroll ID",
     *
     *         @OA\Schema(type="integer")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Tax summary retrieved successfully",
     *
     *         @OA\JsonContent(
     *
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
                        'name' => $employee->first_name_en.' '.$employee->last_name_en,
                        'staff_id' => $employee->staff_id,
                    ],
                    'pay_period' => $payroll->pay_period_date,
                    'tax_calculation' => new PayrollCalculationResource($calculation),
                ],
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Payroll not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tax summary',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // =================== HELPER METHODS FOR PAYROLL CALCULATIONS ===================

    /**
     * Calculate payroll for a specific funding allocation
     *
     * @param Employee $employee
     * @param EmployeeFundingAllocation $allocation
     * @param Carbon $payPeriodDate
     * @param TaxCalculationService $taxService
     * @return array
     */
    private function calculateAllocationPayroll(
        Employee $employee,
        EmployeeFundingAllocation $allocation,
        Carbon $payPeriodDate,
        TaxCalculationService $taxService
    ): array {
        $employment = $employee->employment;
        
        // Calculate pro-rated salary for probation transition
        $salaryCalculation = $this->calculateProRatedSalaryForProbation($employment, $payPeriodDate);
        
        // Calculate annual salary increase on the final gross salary
        $annualIncrease = $this->calculateAnnualSalaryIncrease($employee, $employment, $payPeriodDate);
        $adjustedGrossSalary = $salaryCalculation['gross_salary'] + $annualIncrease;
        
        // Calculate salary by FTE using Level of Effort (Payroll table: gross_salary_by_FTE)
        $grossSalaryByFTE = $adjustedGrossSalary * $allocation->level_of_effort;
        
        // Calculate compensation/refund for mid-month starts
        $compensationRefund = $this->calculateCompensationRefund($employment, $payPeriodDate, $grossSalaryByFTE);
        
        // Calculate 13th month salary
        $thirteenthMonthSalary = $this->calculateThirteenthMonthSalary($employee, $employment, $payPeriodDate, $grossSalaryByFTE);
        
        // Calculate PVD contributions (Payroll table: pvd for employee, employer_contribution includes employer PVD)
        $pvdCalculations = $this->calculatePVDContributions($employee, $grossSalaryByFTE);
        
        // Calculate Social Security (Payroll table: employee_social_security, employer_social_security)
        $socialSecurityCalculations = $this->calculateSocialSecurity($grossSalaryByFTE);
        
        // Calculate Health Welfare (Payroll table: employee_health_welfare, employer_health_welfare)
        $healthWelfareCalculations = $this->calculateHealthWelfare($employment, $grossSalaryByFTE);
        
        // Prepare employee data for tax calculation
        $monthsWorkingThisYear = $this->calculateMonthsWorkingThisYear($employment, $payPeriodDate);
        $employeeData = [
            'has_spouse' => $employee->has_spouse,
            'children' => $employee->employeeChildren->count(),
            'eligible_parents' => $employee->eligible_parents_count,
            'employee_status' => $employee->status,
            'months_working_this_year' => $monthsWorkingThisYear,
        ];
        
        // Calculate income tax using existing service (Payroll table: tax)
        // TaxCalculationService expects monthly salary and handles annual conversion internally
        $taxCalculation = $taxService->calculateEmployeeTax($grossSalaryByFTE, $employeeData);
        $tax = $taxCalculation['monthly_tax_amount'];
        
        // Calculate totals using Payroll table field names
        $totalIncome = $grossSalaryByFTE + $compensationRefund + $thirteenthMonthSalary; // total_income
        $totalDeduction = $pvdCalculations['employee'] + $pvdCalculations['saving_fund'] + 
                         $socialSecurityCalculations['employee'] + $healthWelfareCalculations['employee'] + $tax; // total_deduction
        $netSalary = $totalIncome - $totalDeduction; // net_salary
        $employerContribution = $pvdCalculations['employer'] + $socialSecurityCalculations['employer'] + 
                               $healthWelfareCalculations['employer']; // employer_contribution

        // Build allocation data structure using Payroll table field names
        return [
            'allocation_id' => $allocation->id,
            'staff_id' => $employee->staff_id,
            'employee_name' => trim($employee->first_name_en . ' ' . $employee->last_name_en),
            'department' => $employment->departmentPosition->department ?? 'N/A',
            'position' => $employment->departmentPosition->position ?? 'N/A',
            'employment_type' => $employment->employment_type,
            'fte_percentage' => $employment->fte ?? 1.0,
            'position_salary' => $employment->position_salary,
            'probation_salary' => $employment->probation_salary,
            'funding_source' => $this->getFundingSourceName($allocation),
            'funding_type' => $allocation->allocation_type,
            'loe_percentage' => $allocation->level_of_effort,
            'salary_breakdown' => $salaryCalculation, // Show probation transition details
            'calculations' => [
                // Using Payroll table field names
                'gross_salary' => $salaryCalculation['gross_salary'], // Base salary after probation calculation
                'annual_increase' => $annualIncrease,
                'adjusted_gross_salary' => $adjustedGrossSalary,
                'gross_salary_by_FTE' => $grossSalaryByFTE, // Payroll table field
                'compensation_refund' => $compensationRefund, // Payroll table field
                'thirteen_month_salary' => $thirteenthMonthSalary, // Payroll table field
                'pvd' => $pvdCalculations['employee'], // Payroll table field (employee PVD)
                'saving_fund' => $pvdCalculations['saving_fund'], // Payroll table field
                'employee_social_security' => $socialSecurityCalculations['employee'], // Payroll table field
                'employer_social_security' => $socialSecurityCalculations['employer'], // Payroll table field
                'employee_health_welfare' => $healthWelfareCalculations['employee'], // Payroll table field
                'employer_health_welfare' => $healthWelfareCalculations['employer'], // Payroll table field
                'tax' => $tax, // Payroll table field
                'total_income' => $totalIncome, // Payroll table field
                'total_deduction' => $totalDeduction, // Payroll table field
                'net_salary' => $netSalary, // Payroll table field
                'employer_contribution' => $employerContribution, // Payroll table field
            ],
        ];
    }

    /**
     * Calculate annual salary increase with comprehensive business rules
     *
     * @param Employee $employee
     * @param $employment
     * @param Carbon $payPeriodDate
     * @return float
     */
    private function calculateAnnualSalaryIncrease(Employee $employee, $employment, Carbon $payPeriodDate): float
    {
        // Check if employee is eligible for annual increase
        if (!$this->isEligibleForAnnualIncrease($employee, $employment, $payPeriodDate)) {
            return 0.0;
        }

        $baseSalary = $employment->position_salary;
        $defaultIncreaseRate = 0.01; // 1% default increase
        
        // Calculate service period to determine pro-rated increase
        $servicePeriod = $this->calculateServicePeriod($employment, $payPeriodDate);
        
        if ($servicePeriod['full_years'] < 1) {
            // Pro-rated increase for partial year employment
            $monthsWorked = $servicePeriod['months'];
            if ($monthsWorked >= 6) {
                $proRatedIncrease = ($baseSalary * $defaultIncreaseRate) * ($monthsWorked / 12);
                return round($proRatedIncrease, 2);
            }
            return 0.0;
        }
        
        // Full annual increase
        $annualIncrease = $baseSalary * $defaultIncreaseRate;
        
        // Cap increase based on position level (this could be configurable)
        $maxIncrease = $this->getMaxIncreaseByPosition($employment->departmentPosition);
        
        return round(min($annualIncrease, $maxIncrease), 2);
    }

    /**
     * Calculate 13th month salary with Thai labor law compliance
     *
     * @param Employee $employee
     * @param $employment
     * @param Carbon $payPeriodDate
     * @param float $monthlySalary
     * @return float
     */
    private function calculateThirteenthMonthSalary(Employee $employee, $employment, Carbon $payPeriodDate, float $monthlySalary): float
    {
        // Check eligibility for 13th month salary
        if (!$this->isEligibleForThirteenthMonth($employee, $employment, $payPeriodDate)) {
            return 0.0;
        }

        $servicePeriod = $this->calculateServicePeriod($employment, $payPeriodDate);
        
        // Monthly accrual calculation (1/12 of monthly salary each month)
        $monthlyAccrual = $monthlySalary / 12;
        
        if ($servicePeriod['full_years'] >= 1) {
            // Full 13th month salary accrual for employees with 1+ years of service
            return round($monthlyAccrual, 2);
        }
        
        // Pro-rated for employees with less than 1 year but more than 6 months
        if ($servicePeriod['months'] >= 6) {
            return round($monthlyAccrual, 2);
        }
        
        return 0.0;
    }

    /**
     * Calculate PVD contributions based on employee status and Thai regulations
     *
     * @param Employee $employee
     * @param float $monthlySalary
     * @return array
     */
    private function calculatePVDContributions(Employee $employee, float $monthlySalary): array
    {
        // PVD rates: 3% employee, 3% employer, 1.5% saving fund
        $employeeRate = 0.03;
        $employerRate = 0.03;
        $savingFundRate = 0.015;
        
        // Calculate based on employee status (Thai vs Non-Thai)
        $employeeContribution = $monthlySalary * $employeeRate;
        $employerContribution = $monthlySalary * $employerRate;
        
        // Saving fund applies differently based on employee status
        $savingFund = 0.0;
        if ($employee->status === 'Local non ID') { // Non-Thai citizens
            $savingFund = $monthlySalary * $savingFundRate;
        }
        
        return [
            'employee' => round($employeeContribution, 2),
            'employer' => round($employerContribution, 2),
            'saving_fund' => round($savingFund, 2),
        ];
    }

    /**
     * Calculate Social Security contributions (5% each, capped at 750 THB)
     *
     * @param float $monthlySalary
     * @return array
     */
    private function calculateSocialSecurity(float $monthlySalary): array
    {
        $rate = 0.05; // 5% each for employee and employer
        $maxContribution = 750.0; // Maximum monthly contribution
        
        $contribution = min($monthlySalary * $rate, $maxContribution);
        
        return [
            'employee' => round($contribution, 2),
            'employer' => round($contribution, 2),
        ];
    }

    /**
     * Calculate Health Welfare contributions (configurable)
     *
     * @param $employment
     * @param float $monthlySalary
     * @return array
     */
    private function calculateHealthWelfare($employment, float $monthlySalary): array
    {
        // Health welfare is typically configurable per employment record
        $healthWelfareEnabled = $employment->health_welfare ?? false;
        
        if (!$healthWelfareEnabled) {
            return [
                'employee' => 0.0,
                'employer' => 0.0,
            ];
        }
        
        // This could be configurable - for now, using a default rate
        $rate = 0.0; // 0% by default, configurable
        
        return [
            'employee' => round($monthlySalary * $rate, 2),
            'employer' => round($monthlySalary * $rate, 2),
        ];
    }

    /**
     * Calculate compensation/refund for mid-month employment starts
     * This should calculate the pro-rated salary adjustment, not a refund
     *
     * @param $employment
     * @param Carbon $payPeriodDate
     * @param float $monthlySalary
     * @return float
     */
    private function calculateCompensationRefund($employment, Carbon $payPeriodDate, float $monthlySalary): float
    {
        $startDate = Carbon::parse($employment->start_date);
        $payPeriodStart = $payPeriodDate->copy()->startOfMonth();
        $payPeriodEnd = $payPeriodDate->copy()->endOfMonth();
        
        // Only calculate if employee started mid-month in the current pay period
        if ($startDate->between($payPeriodStart, $payPeriodEnd) && $startDate->day > 1) {
            $daysInMonth = $payPeriodEnd->day;
            $daysWorked = $payPeriodEnd->day - $startDate->day + 1;
            $dailySalary = $monthlySalary / $daysInMonth;
            
            // Calculate pro-rated salary for actual days worked
            $proRatedSalary = $dailySalary * $daysWorked;
            
            // Return the difference from full monthly salary (negative means reduction)
            $compensation = $proRatedSalary - $monthlySalary;
            return round($compensation, 2);
        }
        
        // If employee started before this pay period or after, no adjustment needed
        return 0.0;
    }

    /**
     * Calculate pro-rated salary for probation transition within a pay period
     * Handles cases where probation ends mid-month
     *
     * @param $employment
     * @param Carbon $payPeriodDate
     * @return array
     */
    private function calculateProRatedSalaryForProbation($employment, Carbon $payPeriodDate): array
    {
        $probationPassDate = $employment->probation_pass_date ? Carbon::parse($employment->probation_pass_date) : null;
        $probationSalary = $employment->probation_salary ?? $employment->position_salary;
        $positionSalary = $employment->position_salary;
        
        // Get pay period boundaries
        $payPeriodStart = $payPeriodDate->copy()->startOfMonth();
        $payPeriodEnd = $payPeriodDate->copy()->endOfMonth();
        $daysInMonth = $payPeriodEnd->day;
        
        // If no probation pass date or probation already ended before this pay period
        if (!$probationPassDate || $probationPassDate->lt($payPeriodStart)) {
            return [
                'gross_salary' => $positionSalary,
                'probation_days' => 0,
                'position_days' => $daysInMonth,
                'probation_amount' => 0,
                'position_amount' => $positionSalary,
                'calculation_method' => 'Full position salary (probation ended before pay period)',
                'probation_end_date' => $probationPassDate?->format('Y-m-d'),
            ];
        }
        
        // If still in probation period for entire pay period
        if ($probationPassDate->gt($payPeriodEnd)) {
            return [
                'gross_salary' => $probationSalary,
                'probation_days' => $daysInMonth,
                'position_days' => 0,
                'probation_amount' => $probationSalary,
                'position_amount' => 0,
                'calculation_method' => 'Full probation salary (probation continues)',
                'probation_end_date' => $probationPassDate->format('Y-m-d'),
            ];
        }
        
        // Probation ends mid-month - calculate pro-rated amounts
        $probationDays = $payPeriodStart->diffInDays($probationPassDate) + 1; // Include probation end date
        $positionDays = $daysInMonth - $probationDays;
        
        $dailyProbationSalary = $probationSalary / $daysInMonth;
        $dailyPositionSalary = $positionSalary / $daysInMonth;
        
        $probationAmount = $dailyProbationSalary * $probationDays;
        $positionAmount = $dailyPositionSalary * $positionDays;
        $totalGrossSalary = $probationAmount + $positionAmount;
        
        return [
            'gross_salary' => round($totalGrossSalary, 2),
            'probation_days' => $probationDays,
            'position_days' => $positionDays,
            'probation_amount' => round($probationAmount, 2),
            'position_amount' => round($positionAmount, 2),
            'calculation_method' => "Pro-rated: {$probationDays} days probation (฿{$probationSalary}) + {$positionDays} days position (฿{$positionSalary})",
            'probation_end_date' => $probationPassDate->format('Y-m-d'),
            'daily_probation_salary' => round($dailyProbationSalary, 2),
            'daily_position_salary' => round($dailyPositionSalary, 2),
        ];
    }

    /**
     * Get effective base salary considering probation period (legacy method, kept for compatibility)
     *
     * @param $employment
     * @param Carbon $payPeriodDate
     * @return float
     */
    private function getEffectiveBaseSalary($employment, Carbon $payPeriodDate): float
    {
        // Use the new pro-rated calculation and return just the gross salary
        $calculation = $this->calculateProRatedSalaryForProbation($employment, $payPeriodDate);
        return $calculation['gross_salary'];
    }

    /**
     * Get funding source name from allocation
     *
     * @param EmployeeFundingAllocation $allocation
     * @return string
     */
    private function getFundingSourceName(EmployeeFundingAllocation $allocation): string
    {
        if ($allocation->allocation_type === 'grant' && $allocation->orgFunded && $allocation->orgFunded->grant) {
            return $allocation->orgFunded->grant->name ?? 'Grant';
        }
        
        if ($allocation->positionSlot) {
            return $allocation->positionSlot->title ?? 'Position Slot';
        }
        
        return ucfirst($allocation->allocation_type);
    }

    /**
     * Calculate service period for an employee
     *
     * @param $employment
     * @param Carbon $payPeriodDate
     * @return array
     */
    private function calculateServicePeriod($employment, Carbon $payPeriodDate): array
    {
        $startDate = Carbon::parse($employment->start_date);
        $endDate = $payPeriodDate;
        
        $diffInMonths = $startDate->diffInMonths($endDate);
        $diffInYears = floor($diffInMonths / 12);
        $remainingMonths = $diffInMonths % 12;
        
        return [
            'total_months' => $diffInMonths,
            'full_years' => $diffInYears,
            'months' => $remainingMonths,
            'start_date' => $startDate->format('Y-m-d'),
            'calculation_date' => $endDate->format('Y-m-d'),
        ];
    }

    /**
     * Check if employee is eligible for annual salary increase
     *
     * @param Employee $employee
     * @param $employment
     * @param Carbon $payPeriodDate
     * @return bool
     */
    private function isEligibleForAnnualIncrease(Employee $employee, $employment, Carbon $payPeriodDate): bool
    {
        $servicePeriod = $this->calculateServicePeriod($employment, $payPeriodDate);
        
        // Must have at least 6 months of service
        if ($servicePeriod['total_months'] < 6) {
            return false;
        }
        
        // Check if probation period has passed
        if ($employment->probation_pass_date && $payPeriodDate->lt(Carbon::parse($employment->probation_pass_date))) {
            return false;
        }
        
        // Additional business rules can be added here
        // - Performance rating requirements
        // - No salary adjustment in current year
        // - Contract vs permanent employee differences
        
        return true;
    }

    /**
     * Check if employee is eligible for 13th month salary
     *
     * @param Employee $employee
     * @param $employment
     * @param Carbon $payPeriodDate
     * @return bool
     */
    private function isEligibleForThirteenthMonth(Employee $employee, $employment, Carbon $payPeriodDate): bool
    {
        $servicePeriod = $this->calculateServicePeriod($employment, $payPeriodDate);
        
        // Must have at least 6 months of service
        if ($servicePeriod['total_months'] < 6) {
            return false;
        }
        
        // Check if probation period has passed
        if ($employment->probation_pass_date && $payPeriodDate->lt(Carbon::parse($employment->probation_pass_date))) {
            return false;
        }
        
        // Additional business rules for 13th month salary
        // - Only for permanent employees (not contract)
        // - Must be employed at time of payment
        // - Pro-rated for partial year employment
        
        return true;
    }

    /**
     * Calculate how many months the employee will work in the current tax year
     * Handles mid-year employment starts correctly for tax calculation
     *
     * @param $employment
     * @param Carbon $payPeriodDate
     * @return int
     */
    private function calculateMonthsWorkingThisYear($employment, Carbon $payPeriodDate): int
    {
        $startDate = Carbon::parse($employment->start_date);
        $currentYear = $payPeriodDate->year;
        $yearEnd = Carbon::create($currentYear, 12, 31);
        
        // If employee started before current year, they work full 12 months
        if ($startDate->year < $currentYear) {
            return 12;
        }
        
        // If employee starts in current year, calculate remaining months
        if ($startDate->year == $currentYear) {
            // Calculate months from start date to end of year
            $monthsWorking = $startDate->diffInMonths($yearEnd) + 1;
            
            // Ensure it doesn't exceed 12 months
            return min($monthsWorking, 12);
        }
        
        // If employee starts in future year, assume full year for tax calculation
        return 12;
    }

    /**
     * Get maximum salary increase based on position level
     *
     * @param $departmentPosition
     * @return float
     */
    private function getMaxIncreaseByPosition($departmentPosition): float
    {
        // This could be configurable in database
        // For now, using default caps based on position level
        
        if (!$departmentPosition) {
            return 5000.0; // Default max increase
        }
        
        $position = strtolower($departmentPosition->position ?? '');
        
        // Simple position-based caps (this should be configurable)
        if (str_contains($position, 'senior') || str_contains($position, 'lead')) {
            return 8000.0;
        }
        
        if (str_contains($position, 'manager') || str_contains($position, 'director')) {
            return 15000.0;
        }
        
        if (str_contains($position, 'junior') || str_contains($position, 'intern')) {
            return 3000.0;
        }
        
        return 5000.0; // Default max increase
    }
}
