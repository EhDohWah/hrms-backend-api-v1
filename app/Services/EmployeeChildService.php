<?php

namespace App\Services;

use App\Models\EmployeeChild;
use App\Models\User;
use App\Notifications\EmployeeActionNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

class EmployeeChildService
{
    /**
     * Get all employee children with their associated employees.
     */
    public function listAll(): Collection
    {
        return EmployeeChild::with('employee')->get();
    }

    /**
     * Get a single employee child with the employee relationship loaded.
     */
    public function getWithEmployee(EmployeeChild $employeeChild): EmployeeChild
    {
        return $employeeChild->loadMissing('employee');
    }

    /**
     * Create a new employee child record.
     */
    public function create(array $data, User $performedBy): EmployeeChild
    {
        $data['created_by'] = $performedBy->name ?? 'System';

        $employeeChild = EmployeeChild::create($data);

        $this->notifyEmployeeAction($employeeChild, $performedBy);

        return $employeeChild->loadMissing('employee');
    }

    /**
     * Update an existing employee child record.
     */
    public function update(EmployeeChild $employeeChild, array $data, User $performedBy): EmployeeChild
    {
        $data['updated_by'] = $performedBy->name ?? 'System';

        $employeeChild->update($data);

        $this->notifyEmployeeAction($employeeChild, $performedBy);

        return $employeeChild->loadMissing('employee');
    }

    /**
     * Delete an employee child record.
     */
    public function delete(EmployeeChild $employeeChild, User $performedBy): void
    {
        // Load employee before deletion for notification
        $employee = $employeeChild->employee;

        $employeeChild->delete();

        if ($employee) {
            $this->broadcastNotification('updated', $employee, $performedBy);
        }
    }

    /**
     * Notify all users about an employee child action.
     */
    private function notifyEmployeeAction(EmployeeChild $employeeChild, User $performedBy): void
    {
        $employee = $employeeChild->employee;

        if ($employee) {
            $this->broadcastNotification('updated', $employee, $performedBy);
        }
    }

    /**
     * Send notification to all users about the employee action.
     */
    private function broadcastNotification(string $action, mixed $employee, User $performedBy): void
    {
        $users = User::all();

        Notification::send($users, new EmployeeActionNotification($action, $employee, $performedBy));
    }
}
