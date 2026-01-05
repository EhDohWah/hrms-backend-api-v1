<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmployeeActionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $action;

    public $employee;

    public $performedBy;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $action, $employee, $performedBy)
    {
        $this->action = $action; // 'created', 'updated', 'deleted'
        $this->employee = $employee;
        $this->performedBy = $performedBy; // User who performed the action
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $actionText = match ($this->action) {
            'created' => 'created',
            'updated' => 'updated',
            'deleted' => 'deleted',
            default => 'modified',
        };

        $employeeName = ($this->employee->first_name_en ?? '').' '.($this->employee->last_name_en ?? '');
        $employeeName = trim($employeeName) ?: 'Unknown Employee';
        $staffId = $this->employee->staff_id ?? 'N/A';
        $employeeId = $this->employee->id ?? null;
        $performerName = $this->performedBy->name ?? 'System';

        $mailMessage = (new MailMessage)
            ->subject("Employee {$actionText}")
            ->line("Employee {$employeeName} (Staff ID: {$staffId}) has been {$actionText}.")
            ->line("Action performed by: {$performerName}");

        if ($employeeId && $this->action !== 'deleted') {
            $mailMessage->action('View Employee', url('/employees/'.$employeeId));
        }

        return $mailMessage;
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        $actionText = match ($this->action) {
            'created' => 'created',
            'updated' => 'updated',
            'deleted' => 'deleted',
            default => 'modified',
        };

        $employeeName = ($this->employee->first_name_en ?? '').' '.($this->employee->last_name_en ?? '');
        $employeeName = trim($employeeName) ?: 'Unknown Employee';
        $staffId = $this->employee->staff_id ?? 'N/A';
        $employeeId = $this->employee->id ?? null;
        $performerName = $this->performedBy->name ?? 'System';

        $message = "Employee {$employeeName} (Staff ID: {$staffId}) has been {$actionText} by {$performerName}.";

        return [
            'type' => 'employee_action',
            'action' => $this->action,
            'message' => $message,
            'employee_id' => $employeeId,
            'employee_staff_id' => $staffId,
            'employee_name' => $employeeName,
            'performed_by_id' => $this->performedBy->id ?? null,
            'performed_by_name' => $performerName,
            'created_at' => now()->toDateTimeString(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $actionText = match ($this->action) {
            'created' => 'created',
            'updated' => 'updated',
            'deleted' => 'deleted',
            default => 'modified',
        };

        $employeeName = ($this->employee->first_name_en ?? '').' '.($this->employee->last_name_en ?? '');
        $employeeName = trim($employeeName) ?: 'Unknown Employee';
        $staffId = $this->employee->staff_id ?? 'N/A';
        $employeeId = $this->employee->id ?? null;
        $performerName = $this->performedBy->name ?? 'System';

        $message = "Employee {$employeeName} (Staff ID: {$staffId}) has been {$actionText} by {$performerName}.";

        return new BroadcastMessage([
            'type' => 'employee_action',
            'action' => $this->action,
            'message' => $message,
            'employee_id' => $employeeId,
            'employee_staff_id' => $staffId,
            'employee_name' => $employeeName,
            'performed_by_id' => $this->performedBy->id ?? null,
            'performed_by_name' => $performerName,
            'created_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $actionText = match ($this->action) {
            'created' => 'created',
            'updated' => 'updated',
            'deleted' => 'deleted',
            default => 'modified',
        };

        $employeeName = ($this->employee->first_name_en ?? '').' '.($this->employee->last_name_en ?? '');
        $employeeName = trim($employeeName) ?: 'Unknown Employee';
        $staffId = $this->employee->staff_id ?? 'N/A';
        $employeeId = $this->employee->id ?? null;
        $performerName = $this->performedBy->name ?? 'System';

        $message = "Employee {$employeeName} (Staff ID: {$staffId}) has been {$actionText} by {$performerName}.";

        return [
            'type' => 'employee_action',
            'action' => $this->action,
            'message' => $message,
            'employee_id' => $employeeId,
            'employee_staff_id' => $staffId,
            'employee_name' => $employeeName,
            'performed_by_id' => $this->performedBy->id ?? null,
            'performed_by_name' => $performerName,
            'created_at' => now()->toDateTimeString(),
        ];
    }
}
