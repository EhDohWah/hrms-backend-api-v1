<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a user's permissions are updated.
 *
 * This event is dispatched when an admin updates a user's roles or permissions.
 * The frontend listens for this event to trigger a permission refresh.
 *
 * Uses a lightweight payload - frontend fetches full permissions from API.
 *
 * IMPORTANT: Uses ShouldBroadcastNow to broadcast immediately (synchronously)
 * without queuing. This ensures real-time updates work even without queue workers.
 */
class UserPermissionsUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The ID of the user whose permissions were updated.
     */
    public int $userId;

    /**
     * The name of the user who made the change.
     */
    public string $updatedBy;

    /**
     * Timestamp when the update occurred.
     */
    public string $updatedAt;

    /**
     * Optional reason for the permission change.
     */
    public ?string $reason;

    /**
     * Create a new event instance.
     *
     * @param  int  $userId  The ID of the user whose permissions were updated
     * @param  string  $updatedBy  Name of the admin who made the change
     * @param  string|null  $reason  Optional reason for the change
     */
    public function __construct(int $userId, string $updatedBy = 'System', ?string $reason = null)
    {
        $this->userId = $userId;
        $this->updatedBy = $updatedBy;
        $this->updatedAt = now()->toIso8601String();
        $this->reason = $reason;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * Broadcasts to the user's private channel using Laravel's Notifiable pattern.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("App.Models.User.{$this->userId}"),
        ];
    }

    /**
     * The event's broadcast name.
     *
     * Using a specific name to differentiate from other events on the same channel.
     */
    public function broadcastAs(): string
    {
        return 'user.permissions-updated';
    }

    /**
     * Get the data to broadcast.
     *
     * Sends a lightweight payload - frontend will fetch full permissions via API.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'updated_by' => $this->updatedBy,
            'updated_at' => $this->updatedAt,
            'reason' => $this->reason,
            'message' => 'Your permissions have been updated. Please wait while we refresh your access.',
        ];
    }
}
