<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndividualLeaveRequestReportRequest;
use App\Http\Requests\LeaveRequestReportRequest;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\CacheManagerService;
use App\Traits\HasCacheManagement;
use Carbon\Carbon;
use OpenApi\Annotations as OA;
use PDF;

/**
 * @OA\Tag(
 *     name="Leave Request Reports",
 *     description="Operations related to leave request reports"
 * )
 */
class LeaveRequestReportController extends Controller
{
    use HasCacheManagement;

    /**
     * Export the leave request report as a PDF.
     *
     * This method uses the LeaveRequestReportRequest for validation.
     *
     * @OA\Post(
     *     path="/reports/leave-request-report/export-pdf",
     *     summary="Export leave request report as PDF",
     *     description="Generates a downloadable PDF report of leave requests and balances within a specified date range, filtered by a single work location and department",
     *     operationId="exportLeaveRequestReportPdf",
     *     tags={"Leave Request Reports"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"start_date", "end_date", "work_location", "department"},
     *
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-04-02", description="Start date in format YYYY-MM-DD"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-04-31", description="End date in format YYYY-MM-DD"),
     *             @OA\Property(property="work_location", type="string", example="SMRU", description="Work location name (required) - must exist in work_locations table"),
     *             @OA\Property(property="department", type="string", example="Human Resources", description="Department name (required) - must exist in department_positions table")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="PDF generated successfully",
     *
     *         @OA\MediaType(
     *             mediaType="application/pdf",
     *
     *             @OA\Schema(type="string", format="binary")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="Invalid date format.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="Failed to generate PDF")
     *         )
     *     )
     * )
     */
    public function exportPDF(LeaveRequestReportRequest $request)
    {
        // Get validated start and end dates
        $startDate = Carbon::parse($request->input('start_date'));
        $endDate = Carbon::parse($request->input('end_date'));

        // Generate cache key for this report
        $reportFilters = [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'work_location' => $request->input('work_location'),
            'department' => $request->input('department'),
        ];

        $cacheKey = $this->getCacheManager()->generateKey('leave_report', $reportFilters);

        // Get cached data or generate new
        $reportData = $this->getCacheManager()->remember(
            $cacheKey,
            function () use ($request, $startDate, $endDate) {
                return $this->generateReportData($request, $startDate, $endDate);
            },
            CacheManagerService::SHORT_TTL, // 15 minutes for reports
            ['reports', 'leave_req', 'emp']
        );

        // Get the current date and time in the application's timezone
        $currentDateTime = now()->format('Y-m-d H:i:s');

        // Extract data from cached result
        $employees = $reportData['employees'];
        $entitlements = $reportData['entitlements'];

        // No need to paginate here as PDF will handle all records
        // The page numbers will be automatically added by the PDF library
        // based on the template's page settings

        // Add pagination info for PDF template consistency
        $totalEmployees = $employees->count();
        $employeesPerPage = 25; // Adjust based on how many fit on one page
        $totalPages = ceil($totalEmployees / $employeesPerPage);

        // Load the PDF view and pass in the data
        $pdf = PDF::loadView('reports.leave_request_report_pdf', compact(
            'employees', 'startDate', 'endDate', 'currentDateTime', 'entitlements', 'totalPages'
        ) + [
            'work_location' => $request->input('work_location'),
            'department' => $request->input('department'),
        ]);

        // Set paper to A4 landscape as defined in the template
        $pdf->setPaper('a4', 'landscape');

        // Enable PHP execution in the PDF view
        $options = $pdf->getDomPDF()->getOptions();
        $options->setIsPhpEnabled(true);
        $pdf->getDomPDF()->setOptions($options);

        // Generate a filename based on the date range and filters
        $workLocationSlug = str_replace(' ', '_', strtolower($request->input('work_location')));
        $departmentSlug = str_replace(' ', '_', strtolower($request->input('department')));
        $filename = 'leave_request_report_'.$workLocationSlug.'_'.$departmentSlug.'_'.$startDate->format('Ymd').'_to_'.$endDate->format('Ymd').'_'.now()->format('YmdHis').'.pdf';

        // Return the PDF as a download with cache control headers
        return $pdf->download($filename)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Generate report data with caching
     */
    private function generateReportData($request, $startDate, $endDate)
    {
        // Build the query for employees with their leave data
        $query = Employee::select([
            'employees.id',
            'employees.staff_id',
            'employees.first_name_en',
            'employees.last_name_en',
            'employees.subsidiary',
        ]);

        // Apply work location filter - filter by employees whose employment has the specified work location
        $query->whereHas('employment.workLocation', function ($q) use ($request) {
            $q->where('name', $request->input('work_location'));
        });

        // Apply department filter through employment relationship - exact match
        $query->whereHas('employment.departmentPosition', function ($q) use ($request) {
            $q->where('department', $request->input('department'));
        });

        // Get employees with their employment, department, and work location info
        $query->with([
            'employment:id,employee_id,department_position_id,work_location_id',
            'employment.departmentPosition:id,department,position',
            'employment.workLocation:id,name',
        ]);

        $employees = $query->get();

        // Get leave types for entitlements
        $leaveTypes = LeaveType::all();
        $entitlements = $this->getDefaultEntitlements($leaveTypes);

        // Calculate leave data for each employee
        $employeesWithLeaveData = $employees->map(function ($employee) use ($startDate, $endDate) {
            return $this->calculateEmployeeLeaveData($employee, $startDate, $endDate);
        });

        return [
            'employees' => $employeesWithLeaveData,
            'entitlements' => $entitlements,
        ];
    }

    /**
     * Calculate leave data for a specific employee
     */
    private function calculateEmployeeLeaveData($employee, $startDate, $endDate)
    {
        $currentYear = Carbon::now()->year;

        // Get leave balances for the current year
        $leaveBalances = LeaveBalance::where('employee_id', $employee->id)
            ->where('year', $currentYear)
            ->with('leaveType:id,name')
            ->get()
            ->keyBy('leaveType.name');

        // Get leave requests within the date range
        $leaveRequests = LeaveRequest::where('employee_id', $employee->id)
            ->whereBetween('start_date', [$startDate, $endDate])
            ->where('status', 'approved')
            ->with('leaveType:id,name')
            ->get();

        // Calculate used leave by type
        $usedLeave = $leaveRequests->groupBy('leaveType.name')->map(function ($requests) {
            return $requests->sum('total_days');
        });

        // Set leave data on the employee object (using actual database leave type names)
        $employee->used_annual_leave = $usedLeave->get('Annual vacation', 0);
        $employee->used_sick_leave = $usedLeave->get('Sick', 0);
        $employee->used_traditional_leave = $usedLeave->get('Traditional day-off', 0);
        $employee->used_compassionate_leave = $usedLeave->get('Compassionate', 0);
        $employee->used_maternity_leave = $usedLeave->get('Maternity leave', 0);
        $employee->used_training_leave = $usedLeave->get('Career development training', 0);
        $employee->used_personal_leave = $usedLeave->get('Personal leave', 0);
        $employee->used_military_leave = $usedLeave->get('Military leave', 0);
        $employee->used_sterilization_leave = $usedLeave->get('Sterilization leave', 0);
        $employee->used_other_leave = $usedLeave->get('Other', 0);

        // Set legacy/template fields for backward compatibility (these don't exist in database)
        $employee->used_unpaid_leave = 0; // Not a separate leave type in database
        $employee->used_paternity_leave = 0; // Not a separate leave type in database
        $employee->used_public_holiday = 0; // Not a separate leave type in database
        $employee->used_unexplained_absence = 0; // Not a separate leave type in database

        // Set remaining leave data (using actual database leave type names)
        $employee->remaining_annual_leave = $leaveBalances->get('Annual vacation')?->remaining_days ?? 26;
        $employee->remaining_sick_leave = $leaveBalances->get('Sick')?->remaining_days ?? 30;
        $employee->remaining_traditional_leave = $leaveBalances->get('Traditional day-off')?->remaining_days ?? 13;
        $employee->remaining_compassionate_leave = $leaveBalances->get('Compassionate')?->remaining_days ?? 5;
        $employee->remaining_maternity_leave = $leaveBalances->get('Maternity leave')?->remaining_days ?? 98;
        $employee->remaining_training_leave = $leaveBalances->get('Career development training')?->remaining_days ?? 14;
        $employee->remaining_personal_leave = $leaveBalances->get('Personal leave')?->remaining_days ?? 3;
        $employee->remaining_military_leave = $leaveBalances->get('Military leave')?->remaining_days ?? 60;
        $employee->remaining_sterilization_leave = $leaveBalances->get('Sterilization leave')?->remaining_days ?? 0;
        $employee->remaining_other_leave = $leaveBalances->get('Other')?->remaining_days ?? 0;

        // Set legacy/template remaining fields for backward compatibility (these don't exist in database)
        $employee->remaining_unpaid_leave = 0; // Not a separate leave type in database
        $employee->remaining_paternity_leave = 0; // Not a separate leave type in database
        $employee->remaining_public_holiday = 0; // Not a separate leave type in database
        $employee->remaining_unexplained_absence = 0; // Not a separate leave type in database

        // Add template-friendly field mappings
        $employee->employee_id = $employee->staff_id; // For PDF template compatibility
        $employee->first_name = $employee->first_name_en; // For PDF template compatibility
        $employee->last_name = $employee->last_name_en; // For PDF template compatibility

        return $employee;
    }

    /**
     * Get default entitlements for leave types
     */
    private function getDefaultEntitlements($leaveTypes)
    {
        // Use actual database leave type names and their default durations
        $defaults = [
            'Annual vacation' => 26,
            'Sick' => 30,
            'Traditional day-off' => 13,
            'Compassionate' => 5,
            'Maternity leave' => 98,
            'Career development training' => 14,
            'Personal leave' => 3,
            'Military leave' => 60,
            'Sterilization leave' => 0, // Varies based on doctor's consideration
            'Other' => 0,
        ];

        return [
            'annual' => $defaults['Annual vacation'] ?? 26,
            'sick' => $defaults['Sick'] ?? 30,
            'traditional' => $defaults['Traditional day-off'] ?? 13,
            'compassionate' => $defaults['Compassionate'] ?? 5,
            'maternity' => $defaults['Maternity leave'] ?? 98,
            'training' => $defaults['Career development training'] ?? 14,
            'personal' => $defaults['Personal leave'] ?? 3,
            'military' => $defaults['Military leave'] ?? 60,
            'sterilization' => $defaults['Sterilization leave'] ?? 0,
            'other' => $defaults['Other'] ?? 0,
        ];
    }

    /**
     * Export individual employee leave request report as PDF.
     *
     * @OA\Post(
     *     path="/reports/leave-request-report/export-individual-pdf",
     *     summary="Export individual employee leave request report as PDF",
     *     description="Generates a downloadable PDF report of leave requests for a specific employee within a specified date range",
     *     operationId="exportIndividualLeaveRequestReportPdf",
     *     tags={"Leave Request Reports"},
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"start_date", "end_date", "staff_id"},
     *
     *             @OA\Property(property="start_date", type="string", format="date", example="2025-08-01", description="Start date in format YYYY-MM-DD"),
     *             @OA\Property(property="end_date", type="string", format="date", example="2025-08-31", description="End date in format YYYY-MM-DD"),
     *             @OA\Property(property="staff_id", type="string", example="EMP001", description="Employee staff ID (required) - must exist in employees table")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="PDF generated successfully",
     *
     *         @OA\MediaType(
     *             mediaType="application/pdf",
     *
     *             @OA\Schema(type="string", format="binary")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="Employee not found",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="Employee not found")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="Invalid date format.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=500,
     *         description="Server error",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="error", type="string", example="Failed to generate PDF")
     *         )
     *     )
     * )
     */
    public function exportIndividualPDF(IndividualLeaveRequestReportRequest $request)
    {
        // Get validated input
        $startDate = Carbon::parse($request->input('start_date'));
        $endDate = Carbon::parse($request->input('end_date'));
        $staffId = $request->input('staff_id');

        // Generate cache key for this individual report
        $reportFilters = [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'staff_id' => $staffId,
        ];

        $cacheKey = $this->getCacheManager()->generateKey('individual_leave_report', $reportFilters);

        // Get cached data or generate new
        $reportData = $this->getCacheManager()->remember(
            $cacheKey,
            function () use ($startDate, $endDate, $staffId) {
                return $this->generateIndividualReportData($startDate, $endDate, $staffId);
            },
            CacheManagerService::SHORT_TTL, // 15 minutes for reports
            ['reports', 'leave_req', 'emp', 'individual']
        );

        // Check if employee was found
        if (! $reportData['employee']) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        // Get the current date and time
        $currentDateTime = now()->format('Y-m-d H:i:s');

        // Extract data from cached result
        $employee = $reportData['employee'];
        $leaveRequests = $reportData['leaveRequests'];
        $entitlements = $reportData['entitlements'];

        // Load the individual PDF view and pass in the data
        $pdf = PDF::loadView('reports.leave_request_report_individual_pdf', compact(
            'employee', 'leaveRequests', 'startDate', 'endDate', 'currentDateTime', 'entitlements'
        ));

        // Set paper to A4 landscape
        $pdf->setPaper('a4', 'landscape');

        // Enable PHP execution in the PDF view
        $options = $pdf->getDomPDF()->getOptions();
        $options->setIsPhpEnabled(true);
        $pdf->getDomPDF()->setOptions($options);

        // Generate a filename based on the employee and date range
        $employeeSlug = str_replace(' ', '_', strtolower($employee->first_name_en.'_'.$employee->last_name_en));
        $filename = 'individual_leave_request_report_'.$staffId.'_'.$employeeSlug.'_'.$startDate->format('Ymd').'_to_'.$endDate->format('Ymd').'_'.now()->format('YmdHis').'.pdf';

        // Return the PDF as a download with cache control headers
        return $pdf->download($filename)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Generate individual employee report data with caching
     */
    private function generateIndividualReportData($startDate, $endDate, $staffId)
    {
        // Find the employee by staff_id
        $employee = Employee::where('staff_id', $staffId)
            ->with([
                'employment:id,employee_id,department_position_id,work_location_id',
                'employment.departmentPosition:id,department,position',
                'employment.workLocation:id,name',
            ])
            ->first();

        if (! $employee) {
            return [
                'employee' => null,
                'leaveRequests' => collect([]),
                'entitlements' => [],
            ];
        }

        // Get leave requests for this employee within the date range
        $leaveRequests = LeaveRequest::where('employee_id', $employee->id)
            ->whereBetween('start_date', [$startDate, $endDate])
            ->with(['leaveType:id,name'])
            ->orderBy('start_date', 'asc')
            ->get()
            ->map(function ($request) {
                // Map the data for the template
                $request->date_requested = $request->created_at;
                $request->duration_type = $this->calculateDurationType($request);
                $request->leave_type = $request->leaveType->name ?? 'Unknown';
                $request->leave_reason = $request->reason ?? '';

                return $request;
            });

        // Calculate leave balances for the employee
        $employee = $this->calculateEmployeeLeaveData($employee, $startDate, $endDate);

        // Add employment details to the employee for template
        $employee->work_location = $employee->employment->workLocation->name ?? '';
        $employee->department = $employee->employment->departmentPosition->department ?? '';

        // Get leave type entitlements
        $leaveTypes = LeaveType::all();
        $entitlements = $this->getDefaultEntitlements($leaveTypes);

        return [
            'employee' => $employee,
            'leaveRequests' => $leaveRequests,
            'entitlements' => $entitlements,
        ];
    }

    /**
     * Calculate duration type for leave request
     */
    private function calculateDurationType($leaveRequest)
    {
        if ($leaveRequest->total_days == 0.5) {
            return 'Half Day';
        } elseif ($leaveRequest->total_days == 1) {
            return 'Full Day';
        } else {
            return $leaveRequest->total_days.' Days';
        }
    }
}
