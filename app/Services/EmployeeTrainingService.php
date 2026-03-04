<?php

namespace App\Services;

use App\Enums\EmployeeTrainingStatus;
use App\Models\Employee;
use App\Models\EmployeeTraining;
use App\Models\Training;
use Illuminate\Support\Facades\Auth;

class EmployeeTrainingService
{
    /**
     * List employee training records with filtering, sorting, and pagination.
     */
    public function list(array $params): array
    {
        $perPage = $params['per_page'] ?? 10;
        $page = $params['page'] ?? 1;

        $query = EmployeeTraining::with([
            'employee:id,staff_id,first_name_en,last_name_en,organization',
            'training:id,title,organizer,start_date,end_date',
        ]);

        if (! empty($params['filter_training_id'])) {
            $query->where('training_id', $params['filter_training_id']);
        }

        if (! empty($params['filter_employee_id'])) {
            $query->where('employee_id', $params['filter_employee_id']);
        }

        if (! empty($params['filter_status'])) {
            $statuses = explode(',', $params['filter_status']);
            $query->whereIn('status', $statuses);
        }

        if (! empty($params['filter_training_title'])) {
            $query->whereHas('training', function ($q) use ($params) {
                $q->where('title', 'like', '%'.$params['filter_training_title'].'%');
            });
        }

        if (! empty($params['filter_organizer'])) {
            $query->whereHas('training', function ($q) use ($params) {
                $q->where('organizer', 'like', '%'.$params['filter_organizer'].'%');
            });
        }

        $sortBy = $params['sort_by'] ?? 'created_at';
        $sortOrder = $params['sort_order'] ?? 'desc';

        switch ($sortBy) {
            case 'training_title':
                $query->join('trainings', 'employee_trainings.training_id', '=', 'trainings.id')
                    ->orderBy('trainings.title', $sortOrder)
                    ->select('employee_trainings.*');
                break;
            case 'employee_name':
                $query->join('employees', 'employee_trainings.employee_id', '=', 'employees.id')
                    ->whereNull('employees.deleted_at')
                    ->orderBy('employees.first_name_en', $sortOrder)
                    ->select('employee_trainings.*');
                break;
            case 'start_date':
            case 'end_date':
                $query->join('trainings', 'employee_trainings.training_id', '=', 'trainings.id')
                    ->orderBy("trainings.{$sortBy}", $sortOrder)
                    ->select('employee_trainings.*');
                break;
            case 'status':
                $query->orderBy('employee_trainings.status', $sortOrder);
                break;
            default:
                $query->orderBy('employee_trainings.created_at', $sortOrder);
                break;
        }

        $employeeTrainings = $query->paginate($perPage, ['*'], 'page', $page);

        $appliedFilters = [];
        if (! empty($params['filter_training_id'])) {
            $appliedFilters['training_id'] = $params['filter_training_id'];
        }
        if (! empty($params['filter_employee_id'])) {
            $appliedFilters['employee_id'] = $params['filter_employee_id'];
        }
        if (! empty($params['filter_status'])) {
            $appliedFilters['status'] = explode(',', $params['filter_status']);
        }
        if (! empty($params['filter_training_title'])) {
            $appliedFilters['training_title'] = $params['filter_training_title'];
        }
        if (! empty($params['filter_organizer'])) {
            $appliedFilters['organizer'] = $params['filter_organizer'];
        }

        return [
            'employee_trainings' => $employeeTrainings,
            'applied_filters' => $appliedFilters,
        ];
    }

    /**
     * Create a new employee training record.
     */
    public function store(array $data): EmployeeTraining
    {
        $data['created_by'] = Auth::user()->name ?? 'system';
        $data['updated_by'] = Auth::user()->name ?? 'system';

        return EmployeeTraining::create($data);
    }

    /**
     * Retrieve a specific employee training record with training details.
     */
    public function show(EmployeeTraining $employeeTraining): EmployeeTraining
    {
        return $employeeTraining->load('training');
    }

    /**
     * Update an existing employee training record.
     */
    public function update(EmployeeTraining $employeeTraining, array $data): EmployeeTraining
    {
        $data['updated_by'] = Auth::user()->name ?? 'system';
        $employeeTraining->update($data);

        return $employeeTraining;
    }

    /**
     * Delete an employee training record.
     */
    public function destroy(EmployeeTraining $employeeTraining): void
    {
        $employeeTraining->delete();
    }

    /**
     * Get training summary report for a specific employee.
     */
    public function employeeSummary(Employee $employee, array $params): array
    {
        $employee->load([
            'employment.site',
            'employment.departmentPosition.department',
        ]);

        $trainingQuery = EmployeeTraining::with([
            'training:id,title,organizer,start_date,end_date',
        ])
            ->where('employee_id', $employee->id);

        if (! empty($params['date_from'])) {
            $trainingQuery->whereHas('training', function ($q) use ($params) {
                $q->where('start_date', '>=', $params['date_from']);
            });
        }

        if (! empty($params['date_to'])) {
            $trainingQuery->whereHas('training', function ($q) use ($params) {
                $q->where('end_date', '<=', $params['date_to']);
            });
        }

        $employeeTrainings = $trainingQuery->orderByDesc('created_at')->get();

        $trainings = $employeeTrainings->map(function ($empTraining) {
            return [
                'id' => $empTraining->id,
                'training_title' => $empTraining->training->title ?? 'N/A',
                'organizer_details' => $empTraining->training->organizer ?? 'N/A',
                'start_date' => $empTraining->training->start_date ?? null,
                'end_date' => $empTraining->training->end_date ?? null,
                'status' => $empTraining->status,
                'attendance_date' => $empTraining->created_at,
            ];
        });

        $site = $employee->employment?->site?->name ?? 'N/A';
        $department = $employee->employment?->departmentPosition?->department?->name ?? 'N/A';

        return [
            'employee' => [
                'id' => $employee->id,
                'staff_id' => $employee->staff_id,
                'first_name_en' => $employee->first_name_en,
                'last_name_en' => $employee->last_name_en,
                'organization' => $employee->organization,
                'site' => $site,
                'department' => $department,
            ],
            'training_summary' => [
                'total_trainings' => $employeeTrainings->count(),
                'completed_trainings' => $employeeTrainings->where('status', EmployeeTrainingStatus::Completed)->count(),
                'in_progress_trainings' => $employeeTrainings->where('status', EmployeeTrainingStatus::InProgress)->count(),
                'pending_trainings' => $employeeTrainings->where('status', EmployeeTrainingStatus::Pending)->count(),
            ],
            'trainings' => $trainings,
        ];
    }

    /**
     * Get the attendance list for a specific training.
     */
    public function attendanceList(Training $training, array $params): array
    {
        $attendanceQuery = EmployeeTraining::with([
            'employee:id,staff_id,first_name_en,last_name_en,organization',
        ])
            ->where('training_id', $training->id);

        if (! empty($params['status_filter'])) {
            $attendanceQuery->where('status', $params['status_filter']);
        }

        $attendanceRecords = $attendanceQuery->get();

        $attendees = $attendanceRecords->map(function ($record) use ($training) {
            $fullName = $record->employee->first_name_en;
            if ($record->employee->last_name_en && $record->employee->last_name_en !== '-') {
                $fullName .= ' '.$record->employee->last_name_en;
            }

            return [
                'id' => $record->id,
                'staff_name' => $fullName,
                'staff_id' => $record->employee->staff_id,
                'organizer_details' => $training->organizer,
                'start_date' => $training->start_date,
                'end_date' => $training->end_date,
                'status' => $record->status,
                'enrollment_date' => $record->created_at,
            ];
        })->sortBy('staff_name')->values();

        return [
            'training' => [
                'id' => $training->id,
                'title' => $training->title,
                'organizer' => $training->organizer,
                'start_date' => $training->start_date,
                'end_date' => $training->end_date,
            ],
            'attendance_summary' => [
                'total_enrolled' => $attendanceRecords->count(),
                'completed' => $attendanceRecords->where('status', EmployeeTrainingStatus::Completed)->count(),
                'in_progress' => $attendanceRecords->where('status', EmployeeTrainingStatus::InProgress)->count(),
                'pending' => $attendanceRecords->where('status', EmployeeTrainingStatus::Pending)->count(),
            ],
            'attendees' => $attendees,
        ];
    }
}
