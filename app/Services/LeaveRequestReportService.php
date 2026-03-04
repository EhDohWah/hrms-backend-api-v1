<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\Carbon;

class LeaveRequestReportService
{
    public function __construct(
        private readonly CacheManagerService $cacheManager,
    ) {}

    /**
     * Get complete view data for department leave request PDF report.
     */
    public function getDepartmentPdfViewData(array $params): array
    {
        $startDate = Carbon::parse($params['start_date']);
        $endDate = Carbon::parse($params['end_date']);
        $workLocation = $params['work_location'];
        $department = $params['department'];

        $cacheKey = $this->cacheManager->generateKey('leave_report', [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'work_location' => $workLocation,
            'department' => $department,
        ]);

        $reportData = $this->cacheManager->remember(
            $cacheKey,
            fn () => $this->generateDepartmentReportData($startDate, $endDate, $workLocation, $department),
            CacheManagerService::SHORT_TTL,
            ['reports', 'leave_req', 'emp']
        );

        $employees = $reportData['employees'];

        return [
            'employees' => $employees,
            'entitlements' => $reportData['entitlements'],
            'startDate' => $startDate,
            'endDate' => $endDate,
            'currentDateTime' => now()->format('Y-m-d H:i:s'),
            'work_location' => $workLocation,
            'department' => $department,
            'totalPages' => (int) ceil($employees->count() / 25),
        ];
    }

    /**
     * Get complete view data for individual leave request PDF report.
     * Returns null if the employee is not found.
     */
    public function getIndividualPdfViewData(array $params): ?array
    {
        $startDate = Carbon::parse($params['start_date']);
        $endDate = Carbon::parse($params['end_date']);
        $staffId = $params['staff_id'];

        $cacheKey = $this->cacheManager->generateKey('individual_leave_report', [
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'staff_id' => $staffId,
        ]);

        $reportData = $this->cacheManager->remember(
            $cacheKey,
            fn () => $this->generateIndividualReportData($startDate, $endDate, $staffId),
            CacheManagerService::SHORT_TTL,
            ['reports', 'leave_req', 'emp', 'individual']
        );

        if (! $reportData['employee']) {
            return null;
        }

        return [
            'employee' => $reportData['employee'],
            'leaveRequests' => $reportData['leaveRequests'],
            'entitlements' => $reportData['entitlements'],
            'startDate' => $startDate,
            'endDate' => $endDate,
            'currentDateTime' => now()->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Generate department report data (expensive computation, cached by caller).
     */
    private function generateDepartmentReportData(Carbon $startDate, Carbon $endDate, string $workLocation, string $department): array
    {
        $query = Employee::select([
            'employees.id',
            'employees.staff_id',
            'employees.first_name_en',
            'employees.last_name_en',
            'employees.organization',
        ]);

        // Filter by employees whose employment has the specified site
        $query->whereHas('employment.site', function ($q) use ($workLocation) {
            $q->where('name', $workLocation);
        });

        // Filter by department through employment relationship
        $query->whereHas('employment.department', function ($q) use ($department) {
            $q->where('name', $department);
        });

        $query->with([
            'employment:id,employee_id,department_id,position_id,site_id',
            'employment.department:id,name',
            'employment.position:id,title',
            'employment.site:id,name',
        ]);

        $employees = $query->get();

        $leaveTypes = LeaveType::all();
        $entitlements = $this->getDefaultEntitlements($leaveTypes);

        $employeesWithLeaveData = $employees->map(function ($employee) use ($startDate, $endDate) {
            return $this->calculateEmployeeLeaveData($employee, $startDate, $endDate);
        });

        return [
            'employees' => $employeesWithLeaveData,
            'entitlements' => $entitlements,
        ];
    }

    /**
     * Generate individual employee report data (expensive computation, cached by caller).
     */
    private function generateIndividualReportData(Carbon $startDate, Carbon $endDate, string $staffId): array
    {
        $employee = Employee::where('staff_id', $staffId)
            ->with([
                'employment:id,employee_id,department_id,position_id,site_id',
                'employment.department:id,name',
                'employment.position:id,title',
                'employment.site:id,name',
            ])
            ->first();

        if (! $employee) {
            return [
                'employee' => null,
                'leaveRequests' => collect([]),
                'entitlements' => [],
            ];
        }

        $leaveRequests = LeaveRequest::where('employee_id', $employee->id)
            ->whereBetween('start_date', [$startDate, $endDate])
            ->with(['leaveType:id,name'])
            ->orderBy('start_date', 'asc')
            ->get()
            ->map(function ($leaveRequest) {
                $leaveRequest->date_requested = $leaveRequest->created_at;
                $leaveRequest->duration_type = $this->calculateDurationType($leaveRequest);
                $leaveRequest->leave_type = $leaveRequest->leaveType->name ?? 'Unknown';
                $leaveRequest->leave_reason = $leaveRequest->reason ?? '';

                return $leaveRequest;
            });

        $employee = $this->calculateEmployeeLeaveData($employee, $startDate, $endDate);

        // Add employment details for the PDF template
        $employee->work_location = $employee->employment->site->name ?? '';
        $employee->department = $employee->employment->department->name ?? '';

        $leaveTypes = LeaveType::all();
        $entitlements = $this->getDefaultEntitlements($leaveTypes);

        return [
            'employee' => $employee,
            'leaveRequests' => $leaveRequests,
            'entitlements' => $entitlements,
        ];
    }

    /**
     * Calculate leave data for a specific employee.
     * Sets used/remaining leave properties on the employee model for PDF template consumption.
     */
    private function calculateEmployeeLeaveData($employee, Carbon $startDate, Carbon $endDate)
    {
        $currentYear = Carbon::now()->year;

        $leaveBalances = LeaveBalance::where('employee_id', $employee->id)
            ->where('year', $currentYear)
            ->with('leaveType:id,name')
            ->get()
            ->keyBy('leaveType.name');

        $leaveRequests = LeaveRequest::where('employee_id', $employee->id)
            ->whereBetween('start_date', [$startDate, $endDate])
            ->where('status', 'approved')
            ->with('leaveType:id,name')
            ->get();

        $usedLeave = $leaveRequests->groupBy('leaveType.name')->map(function ($requests) {
            return $requests->sum('total_days');
        });

        // Set used leave data (using actual database leave type names)
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

        // Legacy/template fields for backward compatibility (not separate leave types in database)
        $employee->used_unpaid_leave = 0;
        $employee->used_paternity_leave = 0;
        $employee->used_public_holiday = 0;
        $employee->used_unexplained_absence = 0;

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

        // Legacy remaining fields for backward compatibility
        $employee->remaining_unpaid_leave = 0;
        $employee->remaining_paternity_leave = 0;
        $employee->remaining_public_holiday = 0;
        $employee->remaining_unexplained_absence = 0;

        // Template-friendly field mappings
        $employee->employee_id = $employee->staff_id;
        $employee->first_name = $employee->first_name_en;
        $employee->last_name = $employee->last_name_en;

        return $employee;
    }

    /**
     * Get default entitlements for leave types.
     */
    private function getDefaultEntitlements($leaveTypes): array
    {
        $defaults = [
            'Annual vacation' => 26,
            'Sick' => 30,
            'Traditional day-off' => 13,
            'Compassionate' => 5,
            'Maternity leave' => 98,
            'Career development training' => 14,
            'Personal leave' => 3,
            'Military leave' => 60,
            'Sterilization leave' => 0,
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
     * Calculate duration type label for a leave request.
     */
    private function calculateDurationType($leaveRequest): string
    {
        if ($leaveRequest->total_days == 0.5) {
            return 'Half Day';
        } elseif ($leaveRequest->total_days == 1) {
            return 'Full Day';
        }

        return $leaveRequest->total_days.' Days';
    }
}
