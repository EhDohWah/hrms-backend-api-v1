<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImportedCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $message;

    public $module;

    public $entityId;

    /**
     * Create a new notification instance.
     *
     * @param  string  $message  Success message
     * @param  string  $module  Module name for categorization (default: 'import')
     * @param  int|string|null  $entityId  Optional entity ID for action URL
     */
    public function __construct($message, string $module = 'import', int|string|null $entityId = null)
    {
        $this->message = $message;
        $this->module = $module;
        $this->entityId = $entityId;
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
        return (new MailMessage)
            ->line('Import completed successfully')
            ->action('View Import', url('/imports'));
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        $metadata = $this->getMetadata();

        return array_merge([
            'type' => 'import_completed',
            'message' => $this->message,
            'finished_at' => now()->toDateTimeString(),
        ], $metadata);
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $metadata = $this->getMetadata();

        return new BroadcastMessage(array_merge([
            'type' => 'import_completed',
            'message' => $this->message,
            'finished_at' => now()->toDateTimeString(),
        ], $metadata));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $metadata = $this->getMetadata();

        return array_merge([
            'type' => 'import_completed',
            'message' => $this->message,
            'finished_at' => now()->toDateTimeString(),
        ], $metadata);
    }
}
