<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class LeaveBalanceService
{
    /**
     * List leave balances with filtering, sorting, and pagination.
     */
    public function list(array $params): array
    {
        $perPage = $params['per_page'] ?? 10;

        $query = LeaveBalance::with([
            'employee:id,staff_id,first_name_en,last_name_en,organization',
            'leaveType:id,name',
        ]);

        if (! empty($params['employee_id'])) {
            $query->where('leave_balances.employee_id', $params['employee_id']);
        }

        if (! empty($params['leave_type_id'])) {
            $query->where('leave_balances.leave_type_id', $params['leave_type_id']);
        }

        if (! empty($params['year'])) {
            $query->where('leave_balances.year', $params['year']);
        } else {
            $query->where('leave_balances.year', Carbon::now()->year);
        }

        if (! empty($params['search'])) {
            $searchTerm = trim($params['search']);
            $query->whereHas('employee', function ($q) use ($searchTerm) {
                $q->where('staff_id', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('first_name_en', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('last_name_en', 'LIKE', "%{$searchTerm}%");
            });
        }

        $sortBy = $params['sort_by'] ?? 'created_at';
        $sortOrder = $params['sort_order'] ?? 'desc';

        if ($sortBy === 'employee_name') {
            $query->join('employees', 'leave_balances.employee_id', '=', 'employees.id')
                ->orderBy('employees.first_name_en', $sortOrder)
                ->select('leave_balances.*');
        } elseif ($sortBy === 'staff_id') {
            $query->join('employees', 'leave_balances.employee_id', '=', 'employees.id')
                ->orderBy('employees.staff_id', $sortOrder)
                ->select('leave_balances.*');
        } elseif ($sortBy === 'leave_type') {
            $query->join('leave_types', 'leave_balances.leave_type_id', '=', 'leave_types.id')
                ->orderBy('leave_types.name', $sortOrder)
                ->select('leave_balances.*');
        } elseif (in_array($sortBy, ['total_days', 'used_days', 'remaining_days', 'year'])) {
            $query->orderBy("leave_balances.{$sortBy}", $sortOrder);
        } else {
            $query->orderBy('leave_balances.created_at', 'desc');
        }

        return [
            'leave_balances' => $query->paginate($perPage),
        ];
    }

    /**
     * Get leave balance for a specific employee, leave type, and year.
     *
     * @return array|null Null if employee, leave type, or balance not found.
     */
    public function show(int $employeeId, int $leaveTypeId, array $params): ?array
    {
        $year = $params['year'] ?? Carbon::now()->year;

        $employee = Employee::find($employeeId);
        if (! $employee) {
            return null;
        }

        $leaveType = LeaveType::find($leaveTypeId);
        if (! $leaveType) {
            return null;
        }

        $leaveBalance = LeaveBalance::where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('year', $year)
            ->first();

        if (! $leaveBalance) {
            return null;
        }

        return [
            'employee_id' => $employee->id,
            'employee_name' => trim($employee->first_name_en.' '.$employee->last_name_en),
            'staff_id' => $employee->staff_id,
            'organization' => $employee->organization,
            'leave_type_id' => $leaveType->id,
            'leave_type_name' => $leaveType->name,
            'total_days' => (float) $leaveBalance->total_days,
            'used_days' => (float) $leaveBalance->used_days,
            'remaining_days' => (float) $leaveBalance->remaining_days,
            'year' => $leaveBalance->year,
            'requires_attachment' => $leaveType->requires_attachment,
            'leave_type_description' => $leaveType->description,
        ];
    }

    /**
     * Create a new leave balance.
     *
     * @return array{success: bool, data?: LeaveBalance, message?: string}
     */
    public function store(array $data): array
    {
        $existing = LeaveBalance::where('employee_id', $data['employee_id'])
            ->where('leave_type_id', $data['leave_type_id'])
            ->where('year', $data['year'])
            ->first();

        if ($existing) {
            return [
                'success' => false,
                'message' => 'Leave balance already exists for this employee, leave type, and year',
            ];
        }

        $leaveBalance = LeaveBalance::create([
            'employee_id' => $data['employee_id'],
            'leave_type_id' => $data['leave_type_id'],
            'total_days' => $data['total_days'],
            'used_days' => 0,
            'remaining_days' => $data['total_days'],
            'year' => $data['year'],
            'created_by' => Auth::user()->name ?? 'System',
        ]);

        $leaveBalance->load(['employee:id,staff_id,first_name_en,last_name_en', 'leaveType:id,name']);

        return [
            'success' => true,
            'data' => $leaveBalance,
        ];
    }

    /**
     * Update a leave balance with automatic remaining_days calculation.
     */
    public function update(int $id, array $data): LeaveBalance
    {
        $leaveBalance = LeaveBalance::findOrFail($id);

        if (isset($data['total_days'])) {
            $leaveBalance->total_days = $data['total_days'];
        }
        if (isset($data['used_days'])) {
            $leaveBalance->used_days = $data['used_days'];
        }

        $leaveBalance->remaining_days = $leaveBalance->total_days - $leaveBalance->used_days;
        $leaveBalance->updated_by = Auth::user()->name ?? 'System';
        $leaveBalance->save();

        $leaveBalance->load(['employee:id,staff_id,first_name_en,last_name_en', 'leaveType:id,name']);

        return $leaveBalance;
    }
}
