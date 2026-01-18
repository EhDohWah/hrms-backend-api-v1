<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GrantActionNotification extends Notification implements ShouldQueue, ShouldBroadcastNow
{
    use Queueable;

    public $action;

    public $grant;

    public $performedBy;

    public $module;

    /**
     * Create a new notification instance.
     *
     * @param  string  $action  Action type: 'created', 'updated', 'deleted'
     * @param  mixed  $grant  Grant model or data array
     * @param  mixed  $performedBy  User who performed the action
     * @param  string  $module  Module name for categorization (default: 'grants_list')
     */
    public function __construct(string $action, $grant, $performedBy, string $module = 'grants_list')
    {
        $this->action = $action;
        $this->grant = $grant;
        $this->performedBy = $performedBy;
        $this->module = $module;
    }

    /**
     * Get notification metadata (category and labels)
     */
    protected function getMetadata(): array
    {
        $service = app(NotificationService::class);

        return $service->getNotificationMetadata($this->module);
    }

    /**
     * Helper method to safely get grant property value
     */
    private function getGrantProperty(string $property, $default = null)
    {
        if (is_object($this->grant)) {
            return $this->grant->{$property} ?? $default;
        }
        if (is_array($this->grant)) {
            return $this->grant[$property] ?? $default;
        }

        return $default;
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

        $grantName = $this->getGrantProperty('name', 'Unknown Grant');
        $grantCode = $this->getGrantProperty('code', 'N/A');
        $grantId = $this->getGrantProperty('id');
        $performerName = $this->performedBy->name ?? 'System';

        $mailMessage = (new MailMessage)
            ->subject("Grant {$actionText}")
            ->line("Grant {$grantName} (Code: {$grantCode}) has been {$actionText}.")
            ->line("Action performed by: {$performerName}");

        if ($grantId && $this->action !== 'deleted') {
            $mailMessage->action('View Grant', url('/grants/'.$grantId));
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

        $grantName = $this->getGrantProperty('name', 'Unknown Grant');
        $grantCode = $this->getGrantProperty('code', 'N/A');
        $grantId = $this->getGrantProperty('id');
        $performerName = $this->performedBy->name ?? 'System';
        $metadata = $this->getMetadata();

        $message = "Grant {$grantName} (Code: {$grantCode}) has been {$actionText} by {$performerName}.";

        return array_merge([
            'type' => 'grant_action',
            'action' => $this->action,
            'message' => $message,
            'grant_id' => $grantId,
            'grant_code' => $grantCode,
            'grant_name' => $grantName,
            'performed_by_id' => $this->performedBy->id ?? null,
            'performed_by_name' => $performerName,
            'created_at' => now()->toDateTimeString(),
        ], $metadata);
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        \Log::info('[GrantActionNotification] toBroadcast called for user: ' . $notifiable->id);
        
        $actionText = match ($this->action) {
            'created' => 'created',
            'updated' => 'updated',
            'deleted' => 'deleted',
            default => 'modified',
        };

        $grantName = $this->getGrantProperty('name', 'Unknown Grant');
        $grantCode = $this->getGrantProperty('code', 'N/A');
        $grantId = $this->getGrantProperty('id');
        $performerName = $this->performedBy->name ?? 'System';
        $metadata = $this->getMetadata();

        $message = "Grant {$grantName} (Code: {$grantCode}) has been {$actionText} by {$performerName}.";

<<<<<<< HEAD
        \Log::info('[GrantActionNotification] Broadcasting to channel: App.Models.User.' . $notifiable->id, [
            'message' => $message,
            'grant_id' => $grantId,
        ]);

        return new BroadcastMessage([
=======
        return new BroadcastMessage(array_merge([
>>>>>>> employment-update
            'type' => 'grant_action',
            'action' => $this->action,
            'message' => $message,
            'grant_id' => $grantId,
            'grant_code' => $grantCode,
            'grant_name' => $grantName,
            'performed_by_id' => $this->performedBy->id ?? null,
            'performed_by_name' => $performerName,
            'created_at' => now()->toDateTimeString(),
        ], $metadata));
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

        $grantName = $this->getGrantProperty('name', 'Unknown Grant');
        $grantCode = $this->getGrantProperty('code', 'N/A');
        $grantId = $this->getGrantProperty('id');
        $performerName = $this->performedBy->name ?? 'System';
        $metadata = $this->getMetadata();

        $message = "Grant {$grantName} (Code: {$grantCode}) has been {$actionText} by {$performerName}.";

        return array_merge([
            'type' => 'grant_action',
            'action' => $this->action,
            'message' => $message,
            'grant_id' => $grantId,
            'grant_code' => $grantCode,
            'grant_name' => $grantName,
            'performed_by_id' => $this->performedBy->id ?? null,
            'performed_by_name' => $performerName,
            'created_at' => now()->toDateTimeString(),
        ], $metadata);
    }
}
