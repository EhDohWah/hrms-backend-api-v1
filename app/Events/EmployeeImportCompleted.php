<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class EmployeeImportCompleted implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $importId;

    public $userId;

    public $summary;

    public $errors;

    public function __construct($importId, $userId, $summary = [], $errors = [])
    {
        $this->importId = $importId;
        $this->userId = $userId;
        $this->summary = $summary;
        $this->errors = $errors;
    }

    // Send to private channel so only the user receives it
    public function broadcastOn()
    {
        return [
            new PrivateChannel("App.Models.User.{$this->userId}"),
        ];
    }

    public function broadcastAs()
    {
        return 'private-notification';
    }

    public function broadcastWith(): array
    {
        return [
            'importId' => $this->importId,
            'userId' => $this->userId,
            'summary' => $this->summary,
            'errors' => $this->errors,
        ];
    }
}
