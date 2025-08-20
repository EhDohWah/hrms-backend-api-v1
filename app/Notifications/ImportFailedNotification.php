<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImportFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    // public $importId;
    public $message;

    public $errorDetails;

    public $importId;

    /**
     * Create a new notification instance.
     */
    public function __construct($message, $errorDetails = null, $importId = null)
    {
        // $this->importId = $importId;
        $this->message = $message;
        $this->errorDetails = $errorDetails;
        $this->importId = $importId;
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
        return [
            'type' => 'import_failed',
            'message' => $this->message,
            'error_details' => $this->errorDetails,
            'import_id' => $this->importId,
            'failed_at' => now()->toDateTimeString(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'type' => 'import_failed',
            'message' => $this->message,
            'import_id' => $this->importId,
            'failed_at' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'import_failed',
            'message' => $this->message,
            'error_details' => $this->errorDetails,
            'import_id' => $this->importId,
            'failed_at' => now()->toDateTimeString(),
        ];
    }
}
