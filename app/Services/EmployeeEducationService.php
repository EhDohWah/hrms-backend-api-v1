<?php

namespace App\Services;

use App\Models\EmployeeEducation;
use App\Notifications\EmployeeActionNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class EmployeeEducationService
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Retrieve all employee education records ordered by newest first.
     */
    public function list(): Collection
    {
        return EmployeeEducation::orderBy('created_at', 'desc')->get();
    }

    /**
     * Show a single employee education record.
     */
    public function show(EmployeeEducation $employeeEducation): EmployeeEducation
    {
        return $employeeEducation;
    }

    /**
     * Create a new employee education record and send notification.
     */
    public function create(array $data): EmployeeEducation
    {
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        $employeeEducation = EmployeeEducation::create($data);

        $this->sendNotification($employeeEducation);

        return $employeeEducation;
    }

    /**
     * Update an existing employee education record and send notification.
     */
    public function update(EmployeeEducation $employeeEducation, array $data): EmployeeEducation
    {
        $data['updated_by'] = Auth::id();

        $employeeEducation->update($data);

        $this->sendNotification($employeeEducation);

        return $employeeEducation;
    }

    /**
     * Delete an employee education record and send notification.
     */
    public function delete(EmployeeEducation $employeeEducation): void
    {
        $employee = $employeeEducation->employee;
        $performedBy = Auth::user();

        $employeeEducation->delete();

        if ($performedBy && $employee) {
            $this->notificationService->notifyByModule(
                'employees',
                new EmployeeActionNotification('updated', $employee, $performedBy, 'employees'),
                'updated'
            );
        }
    }

    /**
     * Send employee update notification after education change.
     */
    private function sendNotification(EmployeeEducation $employeeEducation): void
    {
        $performedBy = Auth::user();

        if ($performedBy && $employeeEducation->employee) {
            $this->notificationService->notifyByModule(
                'employees',
                new EmployeeActionNotification('updated', $employeeEducation->employee, $performedBy, 'employees'),
                'updated'
            );
        }
    }
}
