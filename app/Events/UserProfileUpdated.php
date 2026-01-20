<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event broadcast when a user's profile is updated.
 *
 * This event is dispatched when a user updates their:
 * - Profile picture
 * - Username/Name
 * - Email
 * - Password
 *
 * Uses ShouldBroadcastNow to broadcast immediately without queuing.
 * Frontend listens for this event to update UI in real-time.
 */
class UserProfileUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The ID of the user whose profile was updated.
     */
    public int $userId;

    /**
     * The type of update performed.
     * Values: 'name', 'email', 'profile_picture', 'password'
     */
    public string $updateType;

    /**
     * The updated data (excluding sensitive information).
     */
    public array $updatedData;

    /**
     * Timestamp when the update occurred.
     */
    public string $updatedAt;

    /**
     * Create a new event instance.
     *
     * @param int $userId The ID of the user whose profile was updated
     * @param string $updateType Type of update (name, email, profile_picture, password)
     * @param array $updatedData The updated fields (excluding sensitive data like passwords)
     */
    public function __construct(int $userId, string $updateType, array $updatedData = [])
    {
        $this->userId = $userId;
        $this->updateType = $updateType;
        $this->updatedData = $updatedData;
        $this->updatedAt = now()->toIso8601String();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * Broadcasts to the user's private channel.
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
     * Frontend listens for '.user.profile-updated' event.
     */
    public function broadcastAs(): string
    {
        return 'user.profile-updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'update_type' => $this->updateType,
            'data' => $this->updatedData,
            'updated_at' => $this->updatedAt,
            'message' => $this->getMessage(),
        ];
    }

    /**
     * Get human-readable message for the update type.
     */
    private function getMessage(): string
    {
        return match ($this->updateType) {
            'name' => 'Your username has been updated successfully.',
            'email' => 'Your email has been updated successfully.',
            'profile_picture' => 'Your profile picture has been updated successfully.',
            'password' => 'Your password has been changed successfully.',
            default => 'Your profile has been updated successfully.',
        };
    }
}
