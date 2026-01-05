<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class GrantItemActionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $action;

    public $grantItem;

    public $grant;

    public $performedBy;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $action, $grantItem, $grant, $performedBy)
    {
        $this->action = $action; // 'created', 'updated', 'deleted'
        $this->grantItem = $grantItem;
        $this->grant = $grant;
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

        $positionTitle = $this->getGrantItemProperty('grant_position', 'Unknown Position');
        $grantName = $this->getGrantProperty('name', 'Unknown Grant');
        $grantCode = $this->getGrantProperty('code', 'N/A');
        $grantId = $this->getGrantProperty('id');
        $grantItemId = $this->getGrantItemProperty('id');
        $performerName = $this->performedBy->name ?? 'System';

        $mailMessage = (new MailMessage)
            ->subject("Grant Position {$actionText}")
            ->line("Grant position \"{$positionTitle}\" in grant {$grantName} (Code: {$grantCode}) has been {$actionText}.")
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

        $positionTitle = $this->getGrantItemProperty('grant_position', 'Unknown Position');
        $grantName = $this->getGrantProperty('name', 'Unknown Grant');
        $grantCode = $this->getGrantProperty('code', 'N/A');
        $grantId = $this->getGrantProperty('id');
        $grantItemId = $this->getGrantItemProperty('id');
        $performerName = $this->performedBy->name ?? 'System';

        $message = "Grant position \"{$positionTitle}\" in grant {$grantName} (Code: {$grantCode}) has been {$actionText} by {$performerName}.";

        return [
            'type' => 'grant_item_action',
            'action' => $this->action,
            'message' => $message,
            'grant_item_id' => $grantItemId,
            'grant_item_position' => $positionTitle,
            'grant_id' => $grantId,
            'grant_code' => $grantCode,
            'grant_name' => $grantName,
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

        $positionTitle = $this->getGrantItemProperty('grant_position', 'Unknown Position');
        $grantName = $this->getGrantProperty('name', 'Unknown Grant');
        $grantCode = $this->getGrantProperty('code', 'N/A');
        $grantId = $this->getGrantProperty('id');
        $grantItemId = $this->getGrantItemProperty('id');
        $performerName = $this->performedBy->name ?? 'System';

        $message = "Grant position \"{$positionTitle}\" in grant {$grantName} (Code: {$grantCode}) has been {$actionText} by {$performerName}.";

        return new BroadcastMessage([
            'type' => 'grant_item_action',
            'action' => $this->action,
            'message' => $message,
            'grant_item_id' => $grantItemId,
            'grant_item_position' => $positionTitle,
            'grant_id' => $grantId,
            'grant_code' => $grantCode,
            'grant_name' => $grantName,
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

        $positionTitle = $this->getGrantItemProperty('grant_position', 'Unknown Position');
        $grantName = $this->getGrantProperty('name', 'Unknown Grant');
        $grantCode = $this->getGrantProperty('code', 'N/A');
        $grantId = $this->getGrantProperty('id');
        $grantItemId = $this->getGrantItemProperty('id');
        $performerName = $this->performedBy->name ?? 'System';

        $message = "Grant position \"{$positionTitle}\" in grant {$grantName} (Code: {$grantCode}) has been {$actionText} by {$performerName}.";

        return [
            'type' => 'grant_item_action',
            'action' => $this->action,
            'message' => $message,
            'grant_item_id' => $grantItemId,
            'grant_item_position' => $positionTitle,
            'grant_id' => $grantId,
            'grant_code' => $grantCode,
            'grant_name' => $grantName,
            'performed_by_id' => $this->performedBy->id ?? null,
            'performed_by_name' => $performerName,
            'created_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Helper method to safely get grant item property value
     */
    private function getGrantItemProperty(string $property, $default = null)
    {
        if (is_object($this->grantItem)) {
            return $this->grantItem->{$property} ?? $default;
        }
        if (is_array($this->grantItem)) {
            return $this->grantItem[$property] ?? $default;
        }

        return $default;
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
}
