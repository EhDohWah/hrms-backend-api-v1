<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class AttendanceService
{
    /**
     * Return dropdown options for the attendance form (employees + statuses).
     */
    public function options(): array
    {
        $employees = Employee::select('id', 'staff_id', 'first_name_en', 'last_name_en')
            ->orderBy('first_name_en')
            ->get()
            ->map(function (Employee $employee): array {
                $fullName = $employee->first_name_en;
                if ($employee->last_name_en && $employee->last_name_en !== '-') {
                    $fullName .= ' '.$employee->last_name_en;
                }

                return [
                    'value' => $employee->id,
                    'label' => $fullName.' ('.$employee->staff_id.')',
                    'staff_id' => $employee->staff_id,
                ];
            });

        $statuses = collect(AttendanceStatus::cases())->map(fn (AttendanceStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
        ]);

        return [
            'employees' => $employees,
            'statuses' => $statuses,
        ];
    }

    /**
     * Retrieve paginated attendance records with filters and sorting.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $query = Attendance::with([
            'employee:id,staff_id,first_name_en,last_name_en,organization',
        ]);

        // Filter by employee
        if (! empty($filters['filter_employee_id'])) {
            $query->where('employee_id', $filters['filter_employee_id']);
        }

        // Filter by status (comma-separated)
        if (! empty($filters['filter_status'])) {
            $statuses = explode(',', $filters['filter_status']);
            $query->whereIn('status', $statuses);
        }

        // Filter by date range
        if (! empty($filters['filter_date_from'])) {
            $query->where('date', '>=', $filters['filter_date_from']);
        }

        if (! empty($filters['filter_date_to'])) {
            $query->where('date', '<=', $filters['filter_date_to']);
        }

        // Search by employee name or staff_id
        if (! empty($filters['search'])) {
            $searchTerm = $filters['search'];
            $query->whereHas('employee', function ($q) use ($searchTerm) {
                $q->where(function ($sub) use ($searchTerm) {
                    $sub->where('first_name_en', 'like', '%'.$searchTerm.'%')
                        ->orWhere('last_name_en', 'like', '%'.$searchTerm.'%')
                        ->orWhere('staff_id', 'like', '%'.$searchTerm.'%');
                });
            });
        }

        // Apply sorting
        $sortBy = $filters['sort_by'];
        $sortOrder = $filters['sort_order'];

        if ($sortBy === 'employee_name') {
            $query->join('employees', 'attendances.employee_id', '=', 'employees.id')
                ->whereNull('employees.deleted_at')
                ->orderBy('employees.first_name_en', $sortOrder)
                ->select('attendances.*');
        } else {
            $query->orderBy("attendances.{$sortBy}", $sortOrder);
        }

        return $query->paginate($filters['per_page']);
    }

    /**
     * Show a single attendance record with its employee relationship.
     */
    public function show(Attendance $attendance): Attendance
    {
        return $attendance->load('employee:id,staff_id,first_name_en,last_name_en,organization');
    }

    /**
     * Create a new attendance record.
     */
    public function create(array $data): Attendance
    {
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $attendance = Attendance::create($data);

        return $attendance->load('employee:id,staff_id,first_name_en,last_name_en,organization');
    }

    /**
     * Update an existing attendance record.
     */
    public function update(Attendance $attendance, array $data): Attendance
    {
        $data['updated_by'] = Auth::id();

        $attendance->update($data);

        return $attendance->load('employee:id,staff_id,first_name_en,last_name_en,organization');
    }

    /**
     * Delete an attendance record.
     */
    public function delete(Attendance $attendance): void
    {
        $attendance->delete();
    }
}
