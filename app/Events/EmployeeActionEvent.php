<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class EmployeeActionEvent implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $action;

    public $employee;

    public $performedBy;

    public $message;

    public function __construct(string $action, $employee, $performedBy)
    {
        $this->action = $action;
        $this->employee = $employee;
        $this->performedBy = $performedBy;

        $actionText = match ($action) {
            'created' => 'created',
            'updated' => 'updated',
            'deleted' => 'deleted',
            default => 'modified',
        };

        $employeeName = ($employee->first_name_en ?? '').' '.($employee->last_name_en ?? '');
        $employeeName = trim($employeeName) ?: 'Unknown Employee';
        $staffId = $employee->staff_id ?? 'N/A';
        $performerName = $performedBy->name ?? 'System';

        $this->message = "Employee {$employeeName} (Staff ID: {$staffId}) has been {$actionText} by {$performerName}.";
    }

    /**
     * Broadcast to all users (public channel) or specific users
     */
    public function broadcastOn(): array
    {
        // Broadcast to all authenticated users
        return [
            new Channel('employee-actions'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'employee-action';
    }

    public function broadcastWith(): array
    {
        $employeeName = ($this->employee->first_name_en ?? '').' '.($this->employee->last_name_en ?? '');
        $employeeName = trim($employeeName) ?: 'Unknown Employee';
        $staffId = $this->employee->staff_id ?? 'N/A';
        $employeeId = $this->employee->id ?? null;

        return [
            'action' => $this->action,
            'employee_id' => $employeeId,
            'employee_staff_id' => $staffId,
            'employee_name' => $employeeName,
            'performed_by_id' => $this->performedBy->id ?? null,
            'performed_by_name' => $this->performedBy->name ?? 'System',
            'message' => $this->message,
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
