<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PayrollCalculationResource;
use App\Models\Employee;
use App\Models\EmployeeFundingAllocation;
use App\Models\Payroll;
use App\Services\PayrollService;
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
            'employment.department:id,name',
            'employment.position:id,title,department_id',
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
     *     description="Returns employee data with optional automatic payroll calculations for all funding allocations. If pay_period_date is provided, includes calculations; otherwise returns basic employee employment data.",
     *     operationId="getEmployeeEmploymentDetailWithCalculations",
     *     tags={"Payrolls"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="employee_id",
     *         in="query",
     *         description="Employee ID",
     *         required=true,
     *
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="pay_period_date",
     *         in="query",
     *         description="Pay period date for calculations (YYYY-MM-DD format). If not provided, returns employee data without calculations.",
     *         required=false,
     *
     *         @OA\Schema(type="string", format="date", example="2025-01-31")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Employee employment details with calculations retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Employee employment details with calculations retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="employee", ref="#/components/schemas/Employee"),
     *                 @OA\Property(property="pay_period_date", type="string", format="date", example="2025-01-31"),
     *                 @OA\Property(
     *                     property="allocation_calculations",
     *                     type="array",
     *                     description="Individual calculations for each funding allocation",
     *
     *                     @OA\Items(
     *                         type="object",
     *
     *                         @OA\Property(property="allocation_id", type="integer", example=1),
     *                         @OA\Property(property="staff_id", type="string", example="EMP001"),
     *                         @OA\Property(property="employee_name", type="string", example="John Doe"),
     *                         @OA\Property(property="department", type="string", example="IT Department"),
     *                         @OA\Property(property="position", type="string", example="Senior Developer"),
     *                         @OA\Property(property="employment_type", type="string", example="Full-time"),
     *                         @OA\Property(property="fte_percentage", type="number", format="float", example=50.0, description="FTE percentage (0-100)"),
     *                         @OA\Property(property="funding_source", type="string", example="Grant ABC"),
     *                         @OA\Property(property="funding_type", type="string", example="grant"),
     *                         @OA\Property(
     *                             property="calculations",
     *                             type="object",
     *                             @OA\Property(property="basic_salary", type="number", format="float", example=50000),
     *                             @OA\Property(property="annual_increase", type="number", format="float", example=500),
     *                             @OA\Property(property="adjusted_salary", type="number", format="float", example=50500),
     *                             @OA\Property(property="salary_by_fte", type="number", format="float", example=25250),
     *                             @OA\Property(property="compensation_refund", type="number", format="float", example=0),
     *                             @OA\Property(property="thirteen_month_salary", type="number", format="float", example=2104.17),
     *                             @OA\Property(property="pvd_employee", type="number", format="float", example=1875),
     *                             @OA\Property(property="saving_fund", type="number", format="float", example=0),
     *                             @OA\Property(property="social_security_employee", type="number", format="float", example=450),
     *                             @OA\Property(property="social_security_employer", type="number", format="float", example=450),
     *                             @OA\Property(property="health_welfare_employee", type="number", format="float", example=150),
     *                             @OA\Property(property="health_welfare_employer", type="number", format="float", example=0),
     *                             @OA\Property(property="income_tax", type="number", format="float", example=1200),
     *                             @OA\Property(property="total_income", type="number", format="float", example=27354.17),
     *                             @OA\Property(property="total_deductions", type="number", format="float", example=2507.5),
     *                             @OA\Property(property="net_salary", type="number", format="float", example=24846.67),
     *                             @OA\Property(property="employer_contributions", type="number", format="float", example=600)
     *                         )
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="summary_totals",
     *                     type="object",
     *                     description="Summary totals across all funding allocations",
     *                     @OA\Property(property="salary_current_year_by_fte", type="number", format="float", example=25250),
     *                     @OA\Property(property="compensation_refund", type="number", format="float", example=0),
     *                     @OA\Property(property="thirteen_month_salary", type="number", format="float", example=2104.17),
     *                     @OA\Property(property="pvd_saving_employee_total", type="number", format="float", example=1875),
     *                     @OA\Property(property="social_security_employee_total", type="number", format="float", example=450),
     *                     @OA\Property(property="health_welfare_employee_total", type="number", format="float", example=150),
     *                     @OA\Property(property="income_tax_total", type="number", format="float", example=1200),
     *                     @OA\Property(property="social_security_employer_total", type="number", format="float", example=450),
     *                     @OA\Property(property="health_welfare_employer_total", type="number", format="float", example=0),
     *                     @OA\Property(property="net_salary", type="number", format="float", example=24579.17),
     *                     @OA\Property(property="total_salary", type="number", format="float", example=28254.17),
     *                     @OA\Property(property="total_pvd_saving_fund", type="number", format="float", example=3750)
     *                 ),
     *                 @OA\Property(property="allocation_count", type="integer", example=2)
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
     *             @OA\Property(property="errors", type="object")
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
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
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
                'pay_period_date' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $employeeId = $request->input('employee_id');

            // Use provided pay_period_date or set to null for basic employee data
            $payPeriodDate = $request->has('pay_period_date') && $request->input('pay_period_date')
                ? Carbon::parse($request->input('pay_period_date'))
                : null;

            $includeCalculations = ! is_null($payPeriodDate);

            // Load employee with all necessary relationships
            $employee = Employee::with([
                'employment',
                'employment.department:id,name',
                'employment.position:id,title,department_id',
                'employment.workLocation',
                'employeeFundingAllocations',
                'employeeFundingAllocations.orgFunded',
                'employeeFundingAllocations.orgFunded.grant',
                'employeeFundingAllocations.positionSlot',
                'employeeChildren',
            ])->find($employeeId);

            if (! $employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found',
                ], 404);
            }

            // Check if employee has employment record
            if (! $employee->employment) {
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

            // Calculate comprehensive payroll summary if pay_period_date is provided
            $payrollSummary = null;
            if ($includeCalculations) {
                $payrollService = new PayrollService($payPeriodDate->year);
                $payrollSummary = $payrollService->calculateEmployeePayrollSummary($employee, $payPeriodDate);
            }

            // Prepare response data
            $responseData = [
                'employee' => $employee,
            ];

            if ($includeCalculations && $payrollSummary) {
                $responseData['pay_period_date'] = $payrollSummary['pay_period_date'];
                $responseData['allocation_calculations'] = $payrollSummary['allocation_calculations'];
                $responseData['summary_totals'] = $payrollSummary['summary_totals'];
                $responseData['allocation_count'] = $payrollSummary['allocation_count'];
                $message = 'Employee employment details with comprehensive payroll calculations retrieved successfully';
            } else {
                $message = 'Employee employment details retrieved successfully';
            }

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => $responseData,
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
     *     path="/payrolls/preview-advances",
     *     summary="Preview inter-subsidiary advances for employee payroll",
     *     description="Preview inter-subsidiary advances that would be created for an employee's payroll before actual creation",
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
     *     @OA\Parameter(
     *         name="pay_period_date",
     *         in="query",
     *         required=true,
     *         description="Pay period date (YYYY-MM-DD)",
     *
     *         @OA\Schema(type="string", format="date")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Advance preview retrieved successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Advance preview retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="advances_needed", type="boolean", example=true),
     *                 @OA\Property(
     *                     property="employee",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="staff_id", type="string", example="0001"),
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="subsidiary", type="string", example="SMRU")
     *                 ),
     *                 @OA\Property(property="pay_period_date", type="string", format="date", example="2025-01-01"),
     *                 @OA\Property(
     *                     property="advance_previews",
     *                     type="array",
     *
     *                     @OA\Items(
     *                         type="object",
     *
     *                         @OA\Property(property="allocation_id", type="integer", example=1),
     *                         @OA\Property(property="allocation_type", type="string", example="grant"),
     *                         @OA\Property(property="fte", type="number", example=0.5),
     *                         @OA\Property(
     *                             property="project_grant",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="code", type="string", example="BHF001"),
     *                             @OA\Property(property="name", type="string", example="BHF Research Grant"),
     *                             @OA\Property(property="subsidiary", type="string", example="BHF")
     *                         ),
     *                         @OA\Property(
     *                             property="hub_grant",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="code", type="string", example="S22001"),
     *                             @OA\Property(property="name", type="string", example="General Fund"),
     *                             @OA\Property(property="subsidiary", type="string", example="BHF")
     *                         ),
     *                         @OA\Property(property="from_subsidiary", type="string", example="BHF"),
     *                         @OA\Property(property="to_subsidiary", type="string", example="SMRU"),
     *                         @OA\Property(property="estimated_amount", type="number", example=25000),
     *                         @OA\Property(property="formatted_amount", type="string", example="฿25,000.00")
     *                     )
     *                 ),
     *                 @OA\Property(
     *                     property="summary",
     *                     type="object",
     *                     @OA\Property(property="total_advances", type="integer", example=1),
     *                     @OA\Property(property="total_amount", type="number", example=25000),
     *                     @OA\Property(property="formatted_total_amount", type="string", example="฿25,000.00")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Employee not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function previewAdvances(Request $request)
    {
        try {
            // Validate request parameters
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|exists:employees,id',
                'pay_period_date' => 'nullable|date',
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

            // Load employee
            $employee = Employee::find($employeeId);
            if (! $employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found',
                ], 404);
            }

            // Use PayrollService to preview advances
            $payrollService = new PayrollService;
            $advancePreview = $payrollService->previewInterSubsidiaryAdvances($employee, $payPeriodDate);

            return response()->json([
                'success' => true,
                'message' => 'Advance preview retrieved successfully',
                'data' => $advancePreview,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving advance preview',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/payrolls",
     *     operationId="getPayrolls",
     *     summary="List all payrolls with pagination, filtering, and search",
     *     description="Returns a paginated list of payrolls with comprehensive filtering, searching, and sorting capabilities. Supports filtering by subsidiary, department, date range, searching by employee details, and various sorting options.",
     *     tags={"Payrolls"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=1, minimum=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=10, minimum=1, maximum=100)
     *     ),
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search employees by staff ID, first name, or last name",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="EMP001 or John or Doe")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_subsidiary",
     *         in="query",
     *         description="Filter payrolls by subsidiary (comma-separated for multiple values)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="SMRU,BHF")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_department",
     *         in="query",
     *         description="Filter payrolls by department (comma-separated for multiple values)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="IT,HR,Finance")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_position",
     *         in="query",
     *         description="Filter payrolls by position (comma-separated for multiple values)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="Developer,Manager")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_date_range",
     *         in="query",
     *         description="Filter by payslip date range (YYYY-MM-DD,YYYY-MM-DD format)",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="2025-01-01,2025-01-31")
     *     ),
     *
     *     @OA\Parameter(
     *         name="filter_payslip_date",
     *         in="query",
     *         description="Filter by specific payslip date (YYYY-MM-DD format) - legacy parameter",
     *         required=false,
     *
     *         @OA\Schema(type="string", example="2025-01-31")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_by",
     *         in="query",
     *         description="Sort by field",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"subsidiary", "department", "staff_id", "employee_name", "basic_salary", "payslip_date", "created_at", "last_7_days", "last_month", "recently_added"}, example="created_at")
     *     ),
     *
     *     @OA\Parameter(
     *         name="sort_order",
     *         in="query",
     *         description="Sort order (asc or desc)",
     *         required=false,
     *
     *         @OA\Schema(type="string", enum={"asc", "desc"}, example="desc")
     *     ),
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
     *                 @OA\Items(
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="employment_id", type="integer", example=1),
     *                     @OA\Property(property="employee_funding_allocation_id", type="integer", example=1),
     *                     @OA\Property(property="pay_period_date", type="string", format="date", example="2025-01-31"),
     *                     @OA\Property(property="gross_salary", type="number", format="float", example=50000),
     *                     @OA\Property(property="net_salary", type="number", format="float", example=42500),
     *                     @OA\Property(property="total_income", type="number", format="float", example=52500),
     *                     @OA\Property(property="total_deduction", type="number", format="float", example=10000),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-15T10:30:00Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-15T10:30:00Z"),
     *                     @OA\Property(
     *                         property="employee",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="staff_id", type="string", example="EMP001"),
     *                         @OA\Property(property="first_name_en", type="string", example="John"),
     *                         @OA\Property(property="last_name_en", type="string", example="Doe"),
     *                         @OA\Property(property="subsidiary", type="string", example="SMRU")
     *                     ),
     *                     @OA\Property(
     *                         property="employment",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(
     *                             property="department",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="IT")
     *                         ),
     *                         @OA\Property(
     *                             property="position",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="title", type="string", example="Developer")
     *                         )
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="pagination",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=10),
     *                 @OA\Property(property="total", type="integer", example=25),
     *                 @OA\Property(property="last_page", type="integer", example=3),
     *                 @OA\Property(property="from", type="integer", example=1),
     *                 @OA\Property(property="to", type="integer", example=10),
     *                 @OA\Property(property="has_more_pages", type="boolean", example=true)
     *             ),
     *             @OA\Property(
     *                 property="filters",
     *                 type="object",
     *                 @OA\Property(property="applied_filters", type="object",
     *                     @OA\Property(property="subsidiary", type="array", @OA\Items(type="string"), example={"SMRU"}),
     *                     @OA\Property(property="department", type="array", @OA\Items(type="string"), example={"IT"}),
     *                     @OA\Property(property="payslip_date", type="string", example="2025-01-01,2025-01-31")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Invalid parameters provided",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object", example={"per_page": {"The per page must be between 1 and 100."}})
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - User not logged in or token expired"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - User does not have permission to access payrolls"
     *     ),
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
    public function index(Request $request)
    {
        try {
            // Validate incoming parameters
            $validated = $request->validate([
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:100',
                'search' => 'string|nullable|max:255',
                'filter_subsidiary' => 'string|nullable',
                'filter_department' => 'string|nullable',
                'filter_position' => 'string|nullable',
                'filter_date_range' => 'string|nullable',
                'filter_payslip_date' => 'string|nullable', // Legacy parameter
                'sort_by' => 'string|nullable|in:subsidiary,department,staff_id,employee_name,basic_salary,payslip_date,created_at,last_7_days,last_month,recently_added',
                'sort_order' => 'string|nullable|in:asc,desc',
            ]);

            // Determine page size
            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;

            // Build query using model scopes for optimization
            $query = Payroll::forPagination()
                ->withOptimizedRelations();

            // Apply search if provided (search by staff ID, first name, or last name)
            if (! empty($validated['search'])) {
                $searchTerm = trim($validated['search']);
                $query->whereHas('employment.employee', function ($q) use ($searchTerm) {
                    $q->where('staff_id', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('first_name_en', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('last_name_en', 'LIKE', "%{$searchTerm}%")
                        ->orWhereRaw("CONCAT(first_name_en, ' ', last_name_en) LIKE ?", ["%{$searchTerm}%"]);
                });
            }

            // Apply subsidiary filter if provided
            if (! empty($validated['filter_subsidiary'])) {
                $query->bySubsidiary($validated['filter_subsidiary']);
            }

            // Apply department filter if provided
            if (! empty($validated['filter_department'])) {
                $query->byDepartment($validated['filter_department']);
            }

            // Apply position filter if provided
            if (! empty($validated['filter_position'])) {
                $positions = array_map('trim', explode(',', $validated['filter_position']));
                $query->whereHas('employment.position', function ($q) use ($positions) {
                    $q->whereIn('title', $positions);
                });
            }

            // Apply date range filter if provided (priority over legacy payslip_date)
            if (! empty($validated['filter_date_range'])) {
                $query->byPayPeriodDate($validated['filter_date_range']);
            } elseif (! empty($validated['filter_payslip_date'])) {
                // Legacy parameter support
                $query->byPayPeriodDate($validated['filter_payslip_date']);
            }

            // Apply sorting with enhanced options
            $sortBy = $validated['sort_by'] ?? 'created_at';
            $sortOrder = $validated['sort_order'] ?? 'desc';

            // Handle special sorting options
            switch ($sortBy) {
                case 'last_7_days':
                    $query->where('pay_period_date', '>=', now()->subDays(7))
                        ->orderBy('pay_period_date', 'desc');
                    break;

                case 'last_month':
                    $query->where('pay_period_date', '>=', now()->subMonth())
                        ->orderBy('pay_period_date', 'desc');
                    break;

                case 'recently_added':
                    $query->orderBy('created_at', 'desc');
                    break;

                default:
                    $query->orderByField($sortBy, $sortOrder);
                    break;
            }

            // Execute pagination
            $payrolls = $query->paginate($perPage, ['*'], 'page', $page);

            // Build applied filters array
            $appliedFilters = [];
            if (! empty($validated['search'])) {
                $appliedFilters['search'] = $validated['search'];
            }
            if (! empty($validated['filter_subsidiary'])) {
                $appliedFilters['subsidiary'] = explode(',', $validated['filter_subsidiary']);
            }
            if (! empty($validated['filter_department'])) {
                $appliedFilters['department'] = explode(',', $validated['filter_department']);
            }
            if (! empty($validated['filter_position'])) {
                $appliedFilters['position'] = explode(',', $validated['filter_position']);
            }
            if (! empty($validated['filter_date_range'])) {
                $appliedFilters['date_range'] = $validated['filter_date_range'];
            } elseif (! empty($validated['filter_payslip_date'])) {
                $appliedFilters['payslip_date'] = $validated['filter_payslip_date'];
            }

            return response()->json([
                'success' => true,
                'message' => 'Payrolls retrieved successfully',
                'data' => $payrolls->items(),
                'pagination' => [
                    'current_page' => $payrolls->currentPage(),
                    'per_page' => $payrolls->perPage(),
                    'total' => $payrolls->total(),
                    'last_page' => $payrolls->lastPage(),
                    'from' => $payrolls->firstItem(),
                    'to' => $payrolls->lastItem(),
                    'has_more_pages' => $payrolls->hasMorePages(),
                ],
                'filters' => [
                    'applied_filters' => $appliedFilters,
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payrolls',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/payrolls/search",
     *     operationId="searchPayrollsByStaffId",
     *     summary="Search payroll records by employee details with pagination",
     *     description="Returns paginated payroll records for employees based on staff ID, name, or other criteria with partial matching. Useful for HR lookups, finance reviews, and employee record checks.",
     *     tags={"Payrolls"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="staff_id",
     *         in="query",
     *         description="Employee staff ID to search for (partial match supported)",
     *         required=false,
     *
     *         @OA\Schema(type="string", maxLength=50, example="EMP001")
     *     ),
     *
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search by staff ID, employee name (partial match supported)",
     *         required=false,
     *
     *         @OA\Schema(type="string", maxLength=100, example="John Doe or EMP001")
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number for pagination",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=1, minimum=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *
     *         @OA\Schema(type="integer", example=10, minimum=1, maximum=50)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Payroll records found successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payroll records found successfully"),
     *             @OA\Property(property="total_records", type="integer", example=12),
     *             @OA\Property(
     *                 property="employee_info",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="staff_id", type="string", example="EMP001"),
     *                 @OA\Property(property="first_name_en", type="string", example="John"),
     *                 @OA\Property(property="last_name_en", type="string", example="Doe"),
     *                 @OA\Property(property="subsidiary", type="string", example="SMRU"),
     *                 @OA\Property(
     *                     property="employment",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(
     *                         property="department",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="IT")
     *                     ),
     *                     @OA\Property(
     *                         property="position",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="title", type="string", example="Senior Developer")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *
     *                 @OA\Items(
     *
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="employment_id", type="integer", example=1),
     *                     @OA\Property(property="employee_funding_allocation_id", type="integer", example=1),
     *                     @OA\Property(property="pay_period_date", type="string", format="date", example="2025-01-31"),
     *                     @OA\Property(property="gross_salary", type="number", format="float", example=50000),
     *                     @OA\Property(property="net_salary", type="number", format="float", example=42500),
     *                     @OA\Property(property="total_income", type="number", format="float", example=52500),
     *                     @OA\Property(property="total_deduction", type="number", format="float", example=10000),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-01-15T10:30:00Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-01-15T10:30:00Z")
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="No payroll records found for the specified staff ID",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No payroll records found for staff ID: EMP999")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error - Invalid or missing staff_id parameter",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object", example={"staff_id": {"The staff id field is required."}})
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated - User not logged in or token expired"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Unauthorized - User does not have permission to search payrolls"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to search payroll records"),
     *             @OA\Property(property="error", type="string")
     *         )
     *     )
     * )
     */
    public function search(Request $request)
    {
        try {
            // Validate incoming parameters
            $validated = $request->validate([
                'staff_id' => 'nullable|string|max:50',
                'search' => 'nullable|string|max:100',
                'page' => 'integer|min:1',
                'per_page' => 'integer|min:1|max:50',
            ]);

            // At least one search parameter is required
            if (empty($validated['staff_id']) && empty($validated['search'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'At least one search parameter (staff_id or search) is required.',
                    'errors' => ['search' => ['Either staff_id or search parameter must be provided.']],
                ], 422);
            }

            // Determine pagination parameters
            $perPage = $validated['per_page'] ?? 10;
            $page = $validated['page'] ?? 1;

            // Build search query
            $query = Payroll::forPagination()->withOptimizedRelations();

            // Apply search criteria
            $searchTerm = $validated['search'] ?? $validated['staff_id'];

            if ($searchTerm) {
                $query->whereHas('employment.employee', function ($q) use ($searchTerm) {
                    $q->where('staff_id', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('first_name_en', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('last_name_en', 'LIKE', "%{$searchTerm}%")
                        ->orWhereRaw("CONCAT(first_name_en, ' ', last_name_en) LIKE ?", ["%{$searchTerm}%"]);
                });
            }

            // Order by most recent payslip date
            $query->orderBy('pay_period_date', 'desc');

            // Execute pagination
            $payrolls = $query->paginate($perPage, ['*'], 'page', $page);

            // Check if any records were found
            if ($payrolls->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => "No payroll records found for search term: {$searchTerm}",
                ], 404);
            }

            // Get employee information from the first record for summary
            $firstPayroll = $payrolls->items()[0] ?? null;
            $employeeInfo = null;

            if ($firstPayroll && $firstPayroll->employment && $firstPayroll->employment->employee) {
                $employee = $firstPayroll->employment->employee;
                $employeeInfo = [
                    'id' => $employee->id,
                    'staff_id' => $employee->staff_id,
                    'first_name_en' => $employee->first_name_en,
                    'last_name_en' => $employee->last_name_en,
                    'subsidiary' => $employee->subsidiary,
                    'employment' => [
                        'id' => $firstPayroll->employment->id,
                        'department' => [
                            'id' => $firstPayroll->employment->department->id ?? null,
                            'name' => $firstPayroll->employment->department->name ?? null,
                        ],
                        'position' => [
                            'id' => $firstPayroll->employment->position->id ?? null,
                            'title' => $firstPayroll->employment->position->title ?? null,
                        ],
                    ],
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Payroll records found successfully',
                'search_term' => $searchTerm,
                'employee_info' => $employeeInfo,
                'data' => $payrolls->items(),
                'pagination' => [
                    'current_page' => $payrolls->currentPage(),
                    'per_page' => $payrolls->perPage(),
                    'total' => $payrolls->total(),
                    'last_page' => $payrolls->lastPage(),
                    'from' => $payrolls->firstItem(),
                    'to' => $payrolls->lastItem(),
                    'has_more_pages' => $payrolls->hasMorePages(),
                ],
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search payroll records',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/payrolls",
     *     summary="Create payroll records with automatic inter-subsidiary advance detection",
     *     description="Create payroll records for an employee based on their funding allocations. Automatically creates inter-subsidiary advances when needed.",
     *     tags={"Payrolls"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"employee_id", "pay_period_date", "allocation_calculations"},
     *
     *             @OA\Property(property="employee_id", type="integer", example=1, description="Employee ID"),
     *             @OA\Property(property="pay_period_date", type="string", format="date", example="2025-08-31", description="Pay period date"),
     *             @OA\Property(
     *                 property="allocation_calculations",
     *                 type="array",
     *                 description="Payroll calculations for each funding allocation",
     *
     *                 @OA\Items(
     *                     type="object",
     *
     *                     @OA\Property(property="allocation_id", type="integer", example=1),
     *                     @OA\Property(property="employment_id", type="integer", example=1),
     *                     @OA\Property(property="allocation_type", type="string", example="grant"),
     *                     @OA\Property(property="fte", type="number", example=0.2),
     *                     @OA\Property(property="funding_source", type="string", example="Maternal Mortality Reduction Grant"),
     *                     @OA\Property(property="salary_by_fte", type="number", example=5000),
     *                     @OA\Property(property="compensation_refund", type="number", example=0),
     *                     @OA\Property(property="thirteen_month_salary", type="number", example=416.67),
     *                     @OA\Property(property="pvd_employee", type="number", example=375),
     *                     @OA\Property(property="saving_fund", type="number", example=0),
     *                     @OA\Property(property="social_security_employee", type="number", example=90),
     *                     @OA\Property(property="social_security_employer", type="number", example=90),
     *                     @OA\Property(property="health_welfare_employee", type="number", example=30),
     *                     @OA\Property(property="health_welfare_employer", type="number", example=0),
     *                     @OA\Property(property="income_tax", type="number", example=240),
     *                     @OA\Property(property="total_income", type="number", example=5416.67),
     *                     @OA\Property(property="total_deductions", type="number", example=705),
     *                     @OA\Property(property="net_salary", type="number", example=4711.67),
     *                     @OA\Property(property="employer_contributions", type="number", example=120)
     *                 )
     *             ),
     *             @OA\Property(property="payslip_date", type="string", format="date", example="2025-09-01", description="Payslip issue date"),
     *             @OA\Property(property="payslip_number", type="string", example="PAY-2025-001", description="Payslip reference number"),
     *             @OA\Property(property="staff_signature", type="string", example="Tyrique Fahey", description="Staff signature"),
     *             @OA\Property(property="created_by", type="string", example="admin", description="User who created the payroll")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Payroll records created successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Payroll records created successfully with 1 inter-subsidiary advance(s)"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="employee_id", type="integer", example=1),
     *                 @OA\Property(property="pay_period_date", type="string", example="2025-08-31"),
     *                 @OA\Property(property="payroll_records", type="array", @OA\Items(ref="#/components/schemas/Payroll")),
     *                 @OA\Property(property="inter_subsidiary_advances", type="array", @OA\Items(ref="#/components/schemas/InterSubsidiaryAdvance")),
     *                 @OA\Property(
     *                     property="summary",
     *                     type="object",
     *                     @OA\Property(property="total_payrolls_created", type="integer", example=2),
     *                     @OA\Property(property="total_advances_created", type="integer", example=1),
     *                     @OA\Property(property="total_net_salary", type="number", example=21915),
     *                     @OA\Property(property="total_advance_amount", type="number", example=4711.67)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function store(Request $request)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'employee_id' => 'required|exists:employees,id',
                'pay_period_date' => 'required|date',
                'allocation_calculations' => 'required|array|min:1',
                'allocation_calculations.*.allocation_id' => 'required|exists:employee_funding_allocations,id',
                'allocation_calculations.*.employment_id' => 'required|exists:employments,id',
                'allocation_calculations.*.allocation_type' => 'required|string|in:grant,organization',
                'allocation_calculations.*.fte' => 'required|numeric|min:0|max:1',
                'allocation_calculations.*.salary_by_fte' => 'required|numeric|min:0',
                'allocation_calculations.*.compensation_refund' => 'required|numeric|min:0',
                'allocation_calculations.*.thirteen_month_salary' => 'required|numeric|min:0',
                'allocation_calculations.*.pvd_employee' => 'required|numeric|min:0',
                'allocation_calculations.*.saving_fund' => 'required|numeric|min:0',
                'allocation_calculations.*.social_security_employee' => 'required|numeric|min:0',
                'allocation_calculations.*.social_security_employer' => 'required|numeric|min:0',
                'allocation_calculations.*.health_welfare_employee' => 'required|numeric|min:0',
                'allocation_calculations.*.health_welfare_employer' => 'required|numeric|min:0',
                'allocation_calculations.*.income_tax' => 'required|numeric|min:0',
                'allocation_calculations.*.total_income' => 'required|numeric|min:0',
                'allocation_calculations.*.total_deductions' => 'required|numeric|min:0',
                'allocation_calculations.*.net_salary' => 'required|numeric|min:0',
                'allocation_calculations.*.employer_contributions' => 'required|numeric|min:0',
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

            // Get validated data
            $employeeId = $request->employee_id;
            $payPeriodDate = Carbon::parse($request->pay_period_date);
            $allocationCalculations = $request->allocation_calculations;

            // Load employee with necessary relationships
            $employee = Employee::with(['employment', 'employeeFundingAllocations'])->findOrFail($employeeId);

            // Initialize PayrollService for advance detection
            $payrollService = new PayrollService($payPeriodDate->year);

            $createdPayrolls = [];
            $createdAdvances = [];
            $totalNetSalary = 0;
            $totalAdvanceAmount = 0;

            // Process each allocation calculation
            foreach ($allocationCalculations as $calculation) {
                // Find the corresponding funding allocation
                $allocation = $employee->employeeFundingAllocations->firstWhere('id', $calculation['allocation_id']);

                if (! $allocation) {
                    throw new \Exception("Funding allocation {$calculation['allocation_id']} not found for employee {$employeeId}");
                }

                // Create payroll record
                $payrollData = [
                    'employment_id' => $calculation['employment_id'],
                    'employee_funding_allocation_id' => $calculation['allocation_id'],
                    'gross_salary' => $calculation['salary_by_fte'],
                    'gross_salary_by_FTE' => $calculation['salary_by_fte'],
                    'compensation_refund' => $calculation['compensation_refund'],
                    'thirteen_month_salary' => $calculation['thirteen_month_salary'],
                    'thirteen_month_salary_accured' => $calculation['thirteen_month_salary'],
                    'pvd' => $calculation['pvd_employee'],
                    'saving_fund' => $calculation['saving_fund'],
                    'employer_social_security' => $calculation['social_security_employer'],
                    'employee_social_security' => $calculation['social_security_employee'],
                    'employer_health_welfare' => $calculation['health_welfare_employer'],
                    'employee_health_welfare' => $calculation['health_welfare_employee'],
                    'tax' => $calculation['income_tax'],
                    'net_salary' => $calculation['net_salary'],
                    'total_salary' => $calculation['salary_by_fte'],
                    'total_pvd' => $calculation['pvd_employee'],
                    'total_saving_fund' => $calculation['saving_fund'],
                    'salary_bonus' => 0,
                    'total_income' => $calculation['total_income'],
                    'employer_contribution' => $calculation['employer_contributions'],
                    'total_deduction' => $calculation['total_deductions'],
                    'grand_total_income' => $calculation['total_income'],
                    'grand_total_deduction' => $calculation['total_deductions'],
                    'net_paid' => $calculation['net_salary'],
                    'employer_contribution_total' => $calculation['employer_contributions'],
                    'two_sides' => $calculation['total_income'] + $calculation['employer_contributions'],
                    'pay_period_date' => $payPeriodDate,
                    'payslip_date' => $request->payslip_date ? Carbon::parse($request->payslip_date) : null,
                    'payslip_number' => $request->payslip_number,
                    'staff_signature' => $request->staff_signature,
                    'created_by' => $request->created_by ?? (auth()->user()->name ?? 'system'),
                    'updated_by' => $request->created_by ?? (auth()->user()->name ?? 'system'),
                ];

                $payroll = Payroll::create($payrollData);
                $createdPayrolls[] = $payroll;
                $totalNetSalary += $payroll->net_salary;

                // Check if inter-subsidiary advance is needed using PayrollService
                $advance = $payrollService->createInterSubsidiaryAdvanceIfNeeded($employee, $allocation, $payroll, $payPeriodDate);

                if ($advance) {
                    $createdAdvances[] = $advance;
                    $totalAdvanceAmount += $advance->amount;
                }
            }

            // Load relationships for response
            $createdPayrolls = collect($createdPayrolls)->map(function ($payroll) {
                return $payroll->load(['employment.employee', 'employeeFundingAllocation']);
            });

            $createdAdvances = collect($createdAdvances)->map(function ($advance) {
                return $advance->load(['payroll', 'viaGrant', 'fromSubsidiary', 'toSubsidiary']);
            });

            return response()->json([
                'success' => true,
                'message' => 'Payroll records created successfully'.
                    (count($createdAdvances) > 0 ? ' with '.count($createdAdvances).' inter-subsidiary advance(s)' : ''),
                'data' => [
                    'employee_id' => $employeeId,
                    'pay_period_date' => $payPeriodDate->format('Y-m-d'),
                    'payroll_records' => $createdPayrolls,
                    'inter_subsidiary_advances' => $createdAdvances,
                    'summary' => [
                        'total_payrolls_created' => count($createdPayrolls),
                        'total_advances_created' => count($createdAdvances),
                        'total_net_salary' => $totalNetSalary,
                        'total_advance_amount' => $totalAdvanceAmount,
                    ],
                ],
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create payroll records',
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
                'pay_period_date' => 'nullable|date',
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
                'pay_period_date' => 'nullable|date',
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
        $grossSalaryByFTE = $adjustedGrossSalary * $allocation->fte;

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
            'employee_name' => trim($employee->first_name_en.' '.$employee->last_name_en),
            'department' => $employment->department->name ?? 'N/A',
            'position' => $employment->position->title ?? 'N/A',
            'employment_type' => $employment->employment_type,
            'fte_percentage' => ($allocation->fte ?? 1.0) * 100, // Convert decimal to percentage
            'position_salary' => $employment->position_salary,
            'probation_salary' => $employment->probation_salary,
            'funding_source' => $this->getFundingSourceName($allocation),
            'funding_type' => $allocation->allocation_type,
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
     */
    private function calculateAnnualSalaryIncrease(Employee $employee, $employment, Carbon $payPeriodDate): float
    {
        // Check if employee is eligible for annual increase
        if (! $this->isEligibleForAnnualIncrease($employee, $employment, $payPeriodDate)) {
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
        $maxIncrease = $this->getMaxIncreaseByPosition($employment->position);

        return round(min($annualIncrease, $maxIncrease), 2);
    }

    /**
     * Calculate 13th month salary with Thai labor law compliance
     */
    private function calculateThirteenthMonthSalary(Employee $employee, $employment, Carbon $payPeriodDate, float $monthlySalary): float
    {
        // Check eligibility for 13th month salary
        if (! $this->isEligibleForThirteenthMonth($employee, $employment, $payPeriodDate)) {
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
     */
    private function calculateHealthWelfare($employment, float $monthlySalary): array
    {
        // Health welfare is typically configurable per employment record
        $healthWelfareEnabled = $employment->health_welfare ?? false;

        if (! $healthWelfareEnabled) {
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
        if (! $probationPassDate || $probationPassDate->lt($payPeriodStart)) {
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
     */
    private function getEffectiveBaseSalary($employment, Carbon $payPeriodDate): float
    {
        // Use the new pro-rated calculation and return just the gross salary
        $calculation = $this->calculateProRatedSalaryForProbation($employment, $payPeriodDate);

        return $calculation['gross_salary'];
    }

    /**
     * Get funding source name from allocation
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
     */
    private function getMaxIncreaseByPosition($position): float
    {
        // This could be configurable in database
        // For now, using default caps based on position level

        if (! $position) {
            return 5000.0; // Default max increase
        }

        $positionTitle = strtolower($position->title ?? '');

        // Simple position-based caps (this should be configurable)
        if (str_contains($positionTitle, 'senior') || str_contains($positionTitle, 'lead')) {
            return 8000.0;
        }

        if (str_contains($positionTitle, 'manager') || str_contains($positionTitle, 'director')) {
            return 15000.0;
        }

        if (str_contains($positionTitle, 'junior') || str_contains($positionTitle, 'intern')) {
            return 3000.0;
        }

        return 5000.0; // Default max increase
    }
}
