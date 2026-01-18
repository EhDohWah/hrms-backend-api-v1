<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImportFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public $message;

    public $errorDetails;

    public $importId;

    public $module;

    /**
     * Create a new notification instance.
     *
     * @param  string  $message  Error message
     * @param  string|null  $errorDetails  Detailed error information
     * @param  string|null  $importId  Import identifier
     * @param  string  $module  Module name for categorization (default: 'import')
     */
    public function __construct($message, $errorDetails = null, $importId = null, string $module = 'import')
    {
        $this->message = $message;
        $this->errorDetails = $errorDetails;
        $this->importId = $importId;
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
            ->error()
            ->subject('Import Failed')
            ->line($this->message)
            ->line($this->importId ? 'Import ID: '.$this->importId : '')
            ->line($this->errorDetails ? 'Error Details: '.$this->errorDetails : '')
            ->action('Review Imports', url('/imports'))
            ->line('Please address the issue and try again.');
    }

    /**
     * Get the database representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        $metadata = $this->getMetadata();

        return array_merge([
            'type' => 'import_failed',
            'message' => $this->message,
            'error_details' => $this->errorDetails,
            'import_id' => $this->importId,
            'failed_at' => now()->toDateTimeString(),
        ], $metadata);
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        $metadata = $this->getMetadata();

        return new BroadcastMessage(array_merge([
            'type' => 'import_failed',
            'message' => $this->message,
            'import_id' => $this->importId,
            'failed_at' => now()->toDateTimeString(),
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
            'type' => 'import_failed',
            'message' => $this->message,
            'error_details' => $this->errorDetails,
            'import_id' => $this->importId,
            'failed_at' => now()->toDateTimeString(),
        ], $metadata);
    }
}
