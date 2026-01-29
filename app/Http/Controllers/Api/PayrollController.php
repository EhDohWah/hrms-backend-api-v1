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
     *                         property="site",
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
     *                             property="grantItem",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="grant_position", type="string", example="Senior Developer"),
     *                             @OA\Property(
     *                                 property="grant",
     *                                 type="object",
     *                                 @OA\Property(property="id", type="integer", example=1),
     *                                 @OA\Property(property="name", type="string", example="Research Grant")
     *                             )
     *                         ),
     *                         @OA\Property(
     *                             property="grant",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="Org Funded Grant")
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
    public function employeeEmploymentDetail(Request $request)
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
            'employment.site',
            'employeeFundingAllocations',
            'employeeFundingAllocations.grantItem',
            'employeeFundingAllocations.grantItem.grant',
            'employeeFundingAllocations.grant',
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
    public function employeeEmploymentDetailWithCalculations(Request $request)
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

            // Get Employee ID and pay period date
            $employeeId = $request->input('employee_id');

            // Use provided pay_period_date or set to null for basic employee data
            $payPeriodDate = $request->has('pay_period_date') && $request->input('pay_period_date')
                ? Carbon::parse($request->input('pay_period_date'))
                : null;

            // Check if pay period date is provided
            $includeCalculations = ! is_null($payPeriodDate);

            // Load employee with all necessary relationships
            $employee = Employee::with([
                'employment',
                'employment.department:id,name',
                'employment.position:id,title,department_id',
                'employment.site',
                'employeeFundingAllocations',
                'employeeFundingAllocations.grantItem',
                'employeeFundingAllocations.grantItem.grant',
                'employeeFundingAllocations.grant',
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
     *     summary="Preview inter-organization advances for employee payroll",
     *     description="Preview inter-organization advances that would be created for an employee's payroll before actual creation",
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
     *                     @OA\Property(property="organization", type="string", example="SMRU")
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
     *                             @OA\Property(property="organization", type="string", example="BHF")
     *                         ),
     *                         @OA\Property(
     *                             property="hub_grant",
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=2),
     *                             @OA\Property(property="code", type="string", example="S22001"),
     *                             @OA\Property(property="name", type="string", example="General Fund"),
     *                             @OA\Property(property="organization", type="string", example="BHF")
     *                         ),
     *                         @OA\Property(property="from_organization", type="string", example="BHF"),
     *                         @OA\Property(property="to_organization", type="string", example="SMRU"),
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
            $advancePreview = $payrollService->previewInterOrganizationAdvances($employee, $payPeriodDate);

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
     *     description="Returns a paginated list of payrolls with comprehensive filtering, searching, and sorting capabilities. Supports filtering by organization, department, date range, searching by employee details, and various sorting options.",
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
     *         name="filter_organization",
     *         in="query",
     *         description="Filter payrolls by organization (comma-separated for multiple values)",
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
     *         @OA\Schema(type="string", enum={"organization", "department", "staff_id", "employee_name", "basic_salary", "payslip_date", "created_at", "last_7_days", "last_month", "recently_added"}, example="created_at")
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
     *                         @OA\Property(property="organization", type="string", example="SMRU")
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
     *                     @OA\Property(property="organization", type="array", @OA\Items(type="string"), example={"SMRU"}),
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
                'filter_organization' => 'string|nullable',
                'filter_department' => 'string|nullable',
                'filter_position' => 'string|nullable',
                'filter_date_range' => 'string|nullable',
                'filter_payslip_date' => 'string|nullable', // Legacy parameter
                'sort_by' => 'string|nullable|in:organization,department,staff_id,employee_name,basic_salary,payslip_date,created_at,last_7_days,last_month,recently_added',
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

            // Apply organization filter if provided
            if (! empty($validated['filter_organization'])) {
                $query->byOrganization($validated['filter_organization']);
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
            if (! empty($validated['filter_organization'])) {
                $appliedFilters['organization'] = explode(',', $validated['filter_organization']);
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
            \Log::error('Payroll index error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payrolls',
                'error' => $e->getMessage(),
                'debug' => config('app.debug') ? [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ] : null,
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
     *                 @OA\Property(property="organization", type="string", example="SMRU"),
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
                    'organization' => $employee->organization,
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
     *     summary="Create payroll records with automatic inter-organization advance detection",
     *     description="Create payroll records for an employee based on their funding allocations. Automatically creates inter-organization advances when needed.",
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
     *             @OA\Property(property="message", type="string", example="Payroll records created successfully with 1 inter-organization advance(s)"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="employee_id", type="integer", example=1),
     *                 @OA\Property(property="pay_period_date", type="string", example="2025-08-31"),
     *                 @OA\Property(property="payroll_records", type="array", @OA\Items(ref="#/components/schemas/Payroll")),
     *                 @OA\Property(property="inter_organization_advances", type="array", @OA\Items(ref="#/components/schemas/InterOrganizationAdvance")),
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

                // Check if inter-organization advance is needed using PayrollService
                $advance = $payrollService->createInterOrganizationAdvanceIfNeeded($employee, $allocation, $payroll, $payPeriodDate);

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
                    (count($createdAdvances) > 0 ? ' with '.count($createdAdvances).' inter-organization advance(s)' : ''),
                'data' => [
                    'employee_id' => $employeeId,
                    'pay_period_date' => $payPeriodDate->format('Y-m-d'),
                    'payroll_records' => $createdPayrolls,
                    'inter_organization_advances' => $createdAdvances,
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
    public function taxSummary(string $id)
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
            'fte_percentage' => ($allocation->fte ?? 1.0) * 100, // Convert decimal to percentage
            'pass_probation_salary' => $employment->pass_probation_salary,
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

        $baseSalary = $employment->pass_probation_salary;
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
        $probationPassDate = $employment->pass_probation_date ? Carbon::parse($employment->pass_probation_date) : null;
        $probationSalary = $employment->probation_salary ?? $employment->pass_probation_salary;
        $positionSalary = $employment->pass_probation_salary;

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
            'daily_pass_probation_salary' => round($dailyPositionSalary, 2),
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
        if ($allocation->grantItem && $allocation->grantItem->grant) {
            return $allocation->grantItem->grant->name ?? 'Grant';
        }

        return 'Grant';
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
        if ($employment->pass_probation_date && $payPeriodDate->lt(Carbon::parse($employment->pass_probation_date))) {
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
        if ($employment->pass_probation_date && $payPeriodDate->lt(Carbon::parse($employment->pass_probation_date))) {
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

    /**
     * @OA\Get(
     *     path="/payrolls/budget-history",
     *     summary="Get budget history for grant-centric view",
     *     description="Returns payroll data grouped by employee and grant allocation for budget history analysis",
     *     tags={"Payrolls"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Parameter(
     *         name="start_date",
     *         in="query",
     *         required=true,
     *         description="Start date (YYYY-MM format)",
     *
     *         @OA\Schema(type="string", example="2024-01")
     *     ),
     *
     *     @OA\Parameter(
     *         name="end_date",
     *         in="query",
     *         required=true,
     *         description="End date (YYYY-MM format)",
     *
     *         @OA\Schema(type="string", example="2024-06")
     *     ),
     *
     *     @OA\Parameter(
     *         name="organization",
     *         in="query",
     *         required=false,
     *         description="Filter by organization",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="department",
     *         in="query",
     *         required=false,
     *         description="Filter by department",
     *
     *         @OA\Schema(type="string")
     *     ),
     *
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         required=false,
     *         description="Page number",
     *
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         required=false,
     *         description="Items per page",
     *
     *         @OA\Schema(type="integer", default=50)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Budget history retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function budgetHistory(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date_format:Y-m',
            'end_date' => 'required|date_format:Y-m|after_or_equal:start_date',
            'organization' => 'nullable|string',
            'department' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:200',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $startDate = Carbon::createFromFormat('Y-m', $request->start_date)->startOfMonth();
            $endDate = Carbon::createFromFormat('Y-m', $request->end_date)->endOfMonth();

            // Validate date range (max 6 months)
            // Calculate months difference using the start of both months
            $startMonth = Carbon::createFromFormat('Y-m', $request->start_date)->startOfMonth();
            $endMonth = Carbon::createFromFormat('Y-m', $request->end_date)->startOfMonth();
            $monthsDiff = $startMonth->diffInMonths($endMonth) + 1;

            if ($monthsDiff > 6) {
                return response()->json([
                    'success' => false,
                    'message' => 'Date range cannot exceed 6 months',
                ], 422);
            }

            // Build query with optimized relationships
            $query = Payroll::query()
                ->select([
                    'id',
                    'employment_id',
                    'employee_funding_allocation_id',
                    'gross_salary',
                    'net_salary',
                    'pay_period_date',
                ])
                ->with([
                    'employment.employee:id,staff_id,first_name_en,last_name_en,organization',
                    'employment.department:id,name',
                    'employeeFundingAllocation:id,fte,grant_item_id',
                    'employeeFundingAllocation.grantItem:id,grant_id',
                    'employeeFundingAllocation.grantItem.grant:id,name,code',
                ])
                ->whereBetween('pay_period_date', [$startDate, $endDate]);

            // Apply filters
            if ($request->filled('organization')) {
                $query->whereHas('employment.employee', function ($q) use ($request) {
                    $q->where('organization', $request->organization);
                });
            }

            if ($request->filled('department')) {
                $query->whereHas('employment.department', function ($q) use ($request) {
                    $q->where('name', $request->department);
                });
            }

            // Get all payrolls for the date range
            $payrolls = $query->get();

            // Group by employment_id and employee_funding_allocation_id
            $grouped = [];

            foreach ($payrolls as $payroll) {
                $employmentId = $payroll->employment_id;
                $allocationId = $payroll->employee_funding_allocation_id;
                $key = "{$employmentId}_{$allocationId}";

                if (! isset($grouped[$key])) {
                    // Get grant name
                    $grantName = 'N/A';
                    if ($payroll->employeeFundingAllocation) {
                        if ($payroll->employeeFundingAllocation->grantItem && $payroll->employeeFundingAllocation->grantItem->grant) {
                            $grantName = $payroll->employeeFundingAllocation->grantItem->grant->name;
                        }
                    }

                    $grouped[$key] = [
                        'employment_id' => $employmentId,
                        'employee_funding_allocation_id' => $allocationId,
                        'employee_name' => $this->getEmployeeNameFromPayroll($payroll),
                        'staff_id' => $payroll->employment->employee->staff_id ?? 'N/A',
                        'organization' => $payroll->employment->employee->organization ?? 'N/A',
                        'department' => $payroll->employment->department->name ?? 'N/A',
                        'grant_name' => $grantName,
                        'fte' => $payroll->employeeFundingAllocation->fte ?? 0,
                        'monthly_data' => [],
                    ];
                }

                // Add monthly data
                $monthKey = Carbon::parse($payroll->pay_period_date)->format('Y-m');
                $grouped[$key]['monthly_data'][$monthKey] = [
                    'gross_salary' => $payroll->gross_salary,
                    'net_salary' => $payroll->net_salary,
                ];
            }

            // Convert to array and paginate
            $data = array_values($grouped);
            $perPage = $request->input('per_page', 50);
            $page = $request->input('page', 1);
            $total = count($data);

            // Manual pagination
            $offset = ($page - 1) * $perPage;
            $paginatedData = array_slice($data, $offset, $perPage);

            return response()->json([
                'success' => true,
                'message' => 'Budget history retrieved successfully',
                'data' => $paginatedData,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage),
                    'from' => $offset + 1,
                    'to' => min($offset + $perPage, $total),
                ],
                'date_range' => [
                    'start_date' => $startDate->format('Y-m'),
                    'end_date' => $endDate->format('Y-m'),
                    'months' => $this->generateMonthsList($startDate, $endDate),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve budget history',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate list of months between start and end date
     */
    private function generateMonthsList(Carbon $startDate, Carbon $endDate): array
    {
        $months = [];
        $current = $startDate->copy();

        while ($current->lte($endDate)) {
            $months[] = [
                'key' => $current->format('Y-m'),
                'label' => $current->format('M Y'),
            ];
            $current->addMonth();
        }

        return $months;
    }

    /**
     * Get employee name from payroll record
     */
    private function getEmployeeNameFromPayroll($payroll): string
    {
        if ($payroll->employment && $payroll->employment->employee) {
            $firstName = $payroll->employment->employee->first_name_en ?? '';
            $lastName = $payroll->employment->employee->last_name_en ?? '';

            return trim("{$firstName} {$lastName}") ?: 'N/A';
        }

        return 'N/A';
    }

    /**
     * @OA\Post(
     *     path="/uploads/payroll",
     *     summary="Upload payroll data from Excel file",
     *     description="Upload an Excel file containing payroll records. The import is processed asynchronously in the background with chunk processing. Each row creates a new payroll record (no duplicate detection).",
     *     tags={"Payrolls"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *
     *                 @OA\Property(property="file", type="string", format="binary", description="Excel file to upload (xlsx, xls, csv)")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=202, description="Payroll data import started successfully"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Import failed")
     * )
     */
    public function upload(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
            ]);

            $file = $request->file('file');
            $importId = 'payroll_'.str()->uuid();
            $userId = auth()->id();

            // Queue the import
            (new \App\Imports\PayrollsImport($importId, $userId))->queue($file);

            return response()->json([
                'success' => true,
                'message' => 'Payroll import started successfully. You will be notified when complete.',
                'import_id' => $importId,
            ], 202);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start payroll import',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/downloads/payroll-template",
     *     summary="Download payroll import template",
     *     description="Downloads an Excel template for bulk payroll import with validation rules and sample data",
     *     operationId="downloadPayrollTemplate",
     *     tags={"Payrolls"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Template file downloaded successfully"),
     *     @OA\Response(response=500, description="Failed to generate template")
     * )
     */
    public function downloadTemplate()
    {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Payroll Import');

            // ============================================
            // SECTION 1: DEFINE HEADERS
            // ============================================
            $headers = [
                'staff_id',
                'employee_funding_allocation_id',
                'pay_period_date',
                'gross_salary',
                'gross_salary_by_FTE',
                'compensation_refund',
                'thirteen_month_salary',
                'thirteen_month_salary_accured',
                'pvd',
                'saving_fund',
                'employer_social_security',
                'employee_social_security',
                'employer_health_welfare',
                'employee_health_welfare',
                'tax',
                'net_salary',
                'total_salary',
                'total_pvd',
                'total_saving_fund',
                'salary_bonus',
                'total_income',
                'employer_contribution',
                'total_deduction',
                'notes',
            ];

            // ============================================
            // SECTION 2: DEFINE VALIDATION RULES
            // ============================================
            $validationRules = [
                'String - NOT NULL - Employee staff ID (must exist in system)',
                'Integer - NOT NULL - Employee funding allocation ID (must exist)',
                'Date (YYYY-MM-DD) - NOT NULL - Pay period date',
                'Decimal(15,2) - NOT NULL - Gross salary amount',
                'Decimal(15,2) - NOT NULL - Gross salary by FTE',
                'Decimal(15,2) - NULLABLE - Compensation refund',
                'Decimal(15,2) - NULLABLE - 13th month salary',
                'Decimal(15,2) - NULLABLE - 13th month salary accrued',
                'Decimal(15,2) - NULLABLE - Provident fund (PVD)',
                'Decimal(15,2) - NULLABLE - Saving fund',
                'Decimal(15,2) - NULLABLE - Employer social security',
                'Decimal(15,2) - NULLABLE - Employee social security',
                'Decimal(15,2) - NULLABLE - Employer health welfare',
                'Decimal(15,2) - NULLABLE - Employee health welfare',
                'Decimal(15,2) - NULLABLE - Tax amount',
                'Decimal(15,2) - NOT NULL - Net salary',
                'Decimal(15,2) - NULLABLE - Total salary',
                'Decimal(15,2) - NULLABLE - Total PVD',
                'Decimal(15,2) - NULLABLE - Total saving fund',
                'Decimal(15,2) - NULLABLE - Salary bonus',
                'Decimal(15,2) - NULLABLE - Total income',
                'Decimal(15,2) - NULLABLE - Employer contribution',
                'Decimal(15,2) - NULLABLE - Total deduction',
                'String - NULLABLE - Notes for payslip',
            ];

            // ============================================
            // SECTION 3: WRITE HEADERS (Row 1)
            // ============================================
            $col = 1;
            foreach ($headers as $header) {
                $cell = $sheet->getCellByColumnAndRow($col, 1);
                $cell->setValue($header);

                // Style header
                $cell->getStyle()->getFont()->setBold(true)->setSize(11);
                $cell->getStyle()->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('4472C4');
                $cell->getStyle()->getFont()->getColor()->setRGB('FFFFFF');
                $cell->getStyle()->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

                $col++;
            }

            // ============================================
            // SECTION 4: WRITE VALIDATION RULES (Row 2)
            // ============================================
            $col = 1;
            foreach ($validationRules as $rule) {
                $cell = $sheet->getCellByColumnAndRow($col, 2);
                $cell->setValue($rule);

                // Style validation row
                $cell->getStyle()->getFont()->setItalic(true)->setSize(9);
                $cell->getStyle()->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E7E6E6');
                $cell->getStyle()->getAlignment()->setWrapText(true)->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_TOP);

                $col++;
            }

            // Set row height for validation rules
            $sheet->getRowDimension(2)->setRowHeight(60);

            // ============================================
            // SECTION 5: ADD SAMPLE DATA (Rows 3-5)
            // ============================================
            $sampleData = [
                ['EMP001', '1', '2025-01-01', '50000.00', '50000.00', '0.00', '0.00', '4166.67', '3750.00', '0.00', '750.00', '750.00', '0.00', '0.00', '5000.00', '41250.00', '50000.00', '3750.00', '0.00', '0.00', '50000.00', '4500.00', '9500.00', 'Regular monthly salary'],
                ['EMP002', '2', '2025-01-01', '60000.00', '36000.00', '0.00', '0.00', '5000.00', '4500.00', '0.00', '900.00', '900.00', '0.00', '0.00', '6500.00', '49000.00', '60000.00', '4500.00', '0.00', '0.00', '60000.00', '5400.00', '11900.00', '60% FTE allocation'],
                ['EMP003', '3', '2025-01-01', '45000.00', '45000.00', '0.00', '0.00', '3750.00', '3375.00', '0.00', '675.00', '675.00', '0.00', '0.00', '4000.00', '37625.00', '45000.00', '3375.00', '0.00', '0.00', '45000.00', '4050.00', '8425.00', 'Probation period'],
            ];

            $row = 3;
            foreach ($sampleData as $data) {
                $col = 1;
                foreach ($data as $value) {
                    $sheet->getCellByColumnAndRow($col, $row)->setValue($value);
                    $col++;
                }
                $row++;
            }

            // ============================================
            // SECTION 6: SET COLUMN WIDTHS
            // ============================================
            $columnWidths = [
                'A' => 15,  // staff_id
                'B' => 22,  // employee_funding_allocation_id
                'C' => 18,  // pay_period_date
                'D' => 15,  // gross_salary
                'E' => 18,  // gross_salary_by_FTE
                'F' => 18,  // compensation_refund
                'G' => 20,  // thirteen_month_salary
                'H' => 25,  // thirteen_month_salary_accured
                'I' => 12,  // pvd
                'J' => 15,  // saving_fund
                'K' => 22,  // employer_social_security
                'L' => 22,  // employee_social_security
                'M' => 22,  // employer_health_welfare
                'N' => 22,  // employee_health_welfare
                'O' => 12,  // tax
                'P' => 15,  // net_salary
                'Q' => 15,  // total_salary
                'R' => 15,  // total_pvd
                'S' => 18,  // total_saving_fund
                'T' => 15,  // salary_bonus
                'U' => 15,  // total_income
                'V' => 20,  // employer_contribution
                'W' => 18,  // total_deduction
                'X' => 30,  // notes
            ];

            foreach ($columnWidths as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }

            // ============================================
            // SECTION 7: ADD INSTRUCTIONS SHEET
            // ============================================
            $instructionsSheet = $spreadsheet->createSheet();
            $instructionsSheet->setTitle('Instructions');

            $instructions = [
                ['Payroll Import Template - Instructions'],
                [''],
                ['IMPORTANT NOTES:'],
                ['1. Each row creates a NEW payroll record (no duplicate detection)'],
                ['2. One employee can have multiple payroll records per month (one per funding allocation)'],
                ['3. All monetary values will be encrypted automatically for security'],
                ['4. Provide all calculated values - no auto-calculations performed'],
                [''],
                ['REQUIRED FIELDS:'],
                ['- staff_id: Employee staff ID (must exist in system)'],
                ['- employee_funding_allocation_id: Funding allocation ID'],
                ['- pay_period_date: Pay period date (YYYY-MM-DD format)'],
                ['- gross_salary: Gross salary amount'],
                ['- gross_salary_by_FTE: Gross salary adjusted by FTE'],
                ['- net_salary: Net salary after deductions'],
                [''],
                ['OPTIONAL FIELDS:'],
                ['- All other salary components and calculations'],
                ['- notes: Additional notes for the payslip'],
                [''],
                ['DATE FORMAT:'],
                ['- Use YYYY-MM-DD format (e.g., 2025-01-01)'],
                ['- Excel may auto-format dates - ensure they are correct'],
                [''],
                ['NUMERIC VALUES:'],
                ['- Use decimal format (e.g., 50000.00)'],
                ['- Do not use currency symbols'],
                ['- Commas are optional (will be removed automatically)'],
                [''],
                ['MULTIPLE ALLOCATIONS:'],
                ['- If employee has 2 funding allocations, create 2 rows'],
                ['- Each row should have different employee_funding_allocation_id'],
                ['- Both rows can have same pay_period_date'],
            ];

            $row = 1;
            foreach ($instructions as $instruction) {
                $instructionsSheet->setCellValue('A'.$row, $instruction[0]);
                if ($row === 1) {
                    $instructionsSheet->getStyle('A'.$row)->getFont()->setBold(true)->setSize(14);
                } elseif (in_array($row, [3, 9, 17, 21, 24, 27])) {
                    $instructionsSheet->getStyle('A'.$row)->getFont()->setBold(true);
                }
                $row++;
            }

            $instructionsSheet->getColumnDimension('A')->setWidth(80);

            // Set active sheet back to main sheet
            $spreadsheet->setActiveSheetIndex(0);

            // ============================================
            // SECTION 8: GENERATE AND DOWNLOAD
            // ============================================
            $filename = 'payroll_import_template_'.date('Y-m-d_His').'.xlsx';

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'payroll_template_');
            $writer->save($tempFile);

            // Define response headers
            $headers = [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'max-age=0',
            ];

            // Return file download response with proper CORS headers
            return response()->download($tempFile, $filename, $headers)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate template',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/downloads/employee-funding-allocations-reference",
     *     summary="Download employee funding allocations reference list for payroll",
     *     description="Downloads an Excel file with all active employee funding allocations including IDs for use in payroll imports",
     *     operationId="downloadEmployeeFundingAllocationsReference",
     *     tags={"Payrolls"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\Response(response=200, description="Reference file downloaded successfully"),
     *     @OA\Response(response=500, description="Failed to generate reference file")
     * )
     */
    public function downloadEmployeeFundingAllocationsReference()
    {
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet;
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Funding Allocations Ref');

            // Add important notice at the top
            $sheet->mergeCells('A1:L1');
            $sheet->setCellValue('A1', '⚠️ IMPORTANT: Copy the "Funding Allocation ID" (Column A - Green) to your Payroll Import Template');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('FF6B6B'); // Red background for attention
            $sheet->getStyle('A1')->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension(1)->setRowHeight(30);

            // Headers
            $headers = [
                'Funding Allocation ID',
                'Staff ID',
                'Employee Name',
                'Grant Code',
                'Grant Name',
                'Grant Position',
                'FTE (%)',
                'Allocated Amount',
                'Start Date',
                'End Date',
                'Status',
                'Organization',
            ];

            // Write headers with special highlighting for Funding Allocation ID (Row 2)
            $col = 1;
            foreach ($headers as $header) {
                $cell = $sheet->getCellByColumnAndRow($col, 2);
                $cell->setValue($header);
                $cell->getStyle()->getFont()->setBold(true)->setSize(11);

                // Highlight Funding Allocation ID column (column A - the most important one)
                if ($header === 'Funding Allocation ID') {
                    $cell->getStyle()->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('28A745'); // Green - Important!
                    $cell->getStyle()->getFont()->getColor()->setRGB('FFFFFF');
                    $cell->getStyle()->getFont()->setSize(12)->setBold(true);
                } else {
                    $cell->getStyle()->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('4472C4'); // Blue - Standard
                    $cell->getStyle()->getFont()->getColor()->setRGB('FFFFFF');
                }

                $cell->getStyle()->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $col++;
            }

            // Fetch all active employee funding allocations with relationships
            $allocations = EmployeeFundingAllocation::with([
                'employee:id,staff_id,first_name_en,last_name_en,organization',
                'grantItem.grant:id,name,code',
            ])
                ->where('status', 'active')
                ->orderBy('employee_id')
                ->get();

            $row = 3; // Start from row 3 (after notice and headers)
            foreach ($allocations as $allocation) {
                // Highlight Funding Allocation ID cell (Column A) - This is what users need!
                $sheet->setCellValue("A{$row}", $allocation->id);
                $sheet->getStyle("A{$row}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('D4EDDA'); // Light green background
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('155724');
                $sheet->getStyle("A{$row}")->getAlignment()
                    ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                // Add border to make it stand out
                $sheet->getStyle("A{$row}")->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_MEDIUM)
                    ->getColor()->setRGB('28A745');

                $sheet->setCellValue("B{$row}", $allocation->employee->staff_id ?? 'N/A');
                $sheet->setCellValue("C{$row}", ($allocation->employee->first_name_en ?? '').' '.($allocation->employee->last_name_en ?? ''));
                $sheet->setCellValue("D{$row}", $allocation->grantItem->grant->code ?? 'N/A');
                $sheet->setCellValue("E{$row}", $allocation->grantItem->grant->name ?? 'N/A');
                $sheet->setCellValue("F{$row}", $allocation->grantItem->grant_position ?? 'N/A');
                $sheet->setCellValue("G{$row}", round($allocation->fte * 100, 2)); // Convert to percentage
                $sheet->setCellValue("H{$row}", $allocation->allocated_amount);
                $sheet->setCellValue("I{$row}", $allocation->start_date ? $allocation->start_date->format('Y-m-d') : '');
                $sheet->setCellValue("J{$row}", $allocation->end_date ? $allocation->end_date->format('Y-m-d') : 'Ongoing');
                $sheet->setCellValue("K{$row}", ucfirst($allocation->status));
                $sheet->setCellValue("L{$row}", $allocation->employee->organization ?? 'N/A');

                $row++;
            }

            // Set column widths
            $columnWidths = [
                'A' => 20,  // Funding Allocation ID
                'B' => 15,  // Staff ID
                'C' => 25,  // Employee Name
                'D' => 15,  // Grant Code
                'E' => 30,  // Grant Name
                'F' => 25,  // Grant Position
                'G' => 12,  // FTE (%)
                'H' => 18,  // Allocated Amount
                'I' => 15,  // Start Date
                'J' => 15,  // End Date
                'K' => 12,  // Status
                'L' => 15,  // Organization
            ];

            foreach ($columnWidths as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }

            // Add instructions sheet
            $instructionsSheet = $spreadsheet->createSheet();
            $instructionsSheet->setTitle('Instructions');

            $instructions = [
                ['Employee Funding Allocations Reference - How to Use'],
                [''],
                ['⭐ QUICK START:'],
                ['Look for the GREEN column (Column A) - that\'s the "Funding Allocation ID" you need!'],
                ['Copy this ID to your payroll import template.'],
                [''],
                ['PURPOSE:'],
                ['This file contains all active employee funding allocations with their IDs.'],
                ['Use this reference when filling out the Payroll import template.'],
                ['Each employee may have multiple funding allocations (split funding).'],
                [''],
                ['HOW TO USE:'],
                ['1. Find the employee you want to create payroll for (by Staff ID or Name)'],
                ['2. Identify which funding allocation to use (check Grant Code and Position)'],
                ['3. Copy the "Funding Allocation ID" from Column A (GREEN HIGHLIGHTED) to your payroll import'],
                ['4. If an employee has multiple allocations, create separate payroll rows for each'],
                [''],
                ['COLOR CODING:'],
                ['🟢 GREEN COLUMN (A) = Funding Allocation ID - THIS IS WHAT YOU NEED!'],
                ['🔵 BLUE COLUMNS = Reference information to help you find the right allocation'],
                [''],
                ['IMPORTANT NOTES:'],
                ['- Funding Allocation ID is required for payroll imports'],
                ['- One employee can have multiple funding allocations (split funding)'],
                ['- Only ACTIVE allocations with no end date or future end dates are shown'],
                ['- FTE (%) shows the percentage of time allocated to this funding source'],
                ['- Allocated Amount is the monthly allocation for this funding source'],
                [''],
                ['SPLIT FUNDING EXAMPLE:'],
                ['Employee EMP001 might have:'],
                ['  - Allocation ID 5: 60% on Grant A (Allocated: $30,000)'],
                ['  - Allocation ID 6: 40% on Grant B (Allocated: $20,000)'],
                [''],
                ['For payroll, you would create TWO rows:'],
                ['  Row 1: staff_id=EMP001, employee_funding_allocation_id=5, gross_salary_by_FTE=30000'],
                ['  Row 2: staff_id=EMP001, employee_funding_allocation_id=6, gross_salary_by_FTE=20000'],
                [''],
                ['COLUMNS EXPLAINED:'],
                ['- Funding Allocation ID: ID to use in payroll imports (REQUIRED)'],
                ['- Staff ID: Employee identifier'],
                ['- Employee Name: Full name of employee'],
                ['- Grant Code: Short code for the grant'],
                ['- Grant Name: Full name of the grant'],
                ['- Grant Position: Position title for this allocation'],
                ['- FTE (%): Percentage of time allocated (0-100)'],
                ['- Allocated Amount: Monthly allocation amount'],
                ['- Start Date: When this allocation started'],
                ['- End Date: When this allocation ends (or "Ongoing")'],
                ['- Status: Current status (Active, Historical, Terminated)'],
                ['- Organization: SMRU or BHF'],
                [''],
                ['FILTERING:'],
                ['- This file shows ONLY active allocations'],
                ['- Historical and terminated allocations are excluded'],
                ['- Allocations with past end dates are excluded'],
                [''],
                ['BEST PRACTICES:'],
                ['- Always download the latest reference before creating payroll'],
                ['- Verify the employee has active allocations before importing'],
                ['- For split funding, ensure all allocations are included'],
                ['- Check that FTE percentages add up to 100% per employee'],
            ];

            $row = 1;
            foreach ($instructions as $instruction) {
                $instructionsSheet->setCellValue("A{$row}", $instruction[0]);
                if ($row === 1) {
                    $instructionsSheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
                } elseif (in_array($row, [3, 7, 11, 15, 19, 23, 30, 38, 42, 46])) {
                    $instructionsSheet->getStyle("A{$row}")->getFont()->setBold(true);
                }
                $row++;
            }

            $instructionsSheet->getColumnDimension('A')->setWidth(100);
            $spreadsheet->setActiveSheetIndex(0);

            // Generate and download
            $filename = 'employee_funding_allocations_reference_'.date('Y-m-d_His').'.xlsx';
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);

            $tempFile = tempnam(sys_get_temp_dir(), 'funding_alloc_ref_');
            $writer->save($tempFile);

            $headers = [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'Cache-Control' => 'max-age=0',
            ];

            return response()->download($tempFile, $filename, $headers)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate employee funding allocations reference',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
