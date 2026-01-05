<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Payroll Bulk Progress Event
 *
 * Broadcasts real-time progress updates for bulk payroll creation
 * Channel: payroll-bulk.{batchId}
 * Event: payroll.progress
 */
class PayrollBulkProgress implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $batchId;

    public int $processed;

    public int $total;

    public string $status;

    public ?string $currentEmployee;

    public ?string $currentAllocation;

    public array $stats;

    /**
     * Create a new event instance.
     *
     * @param  int  $batchId  The batch ID
     * @param  int  $processed  Number of payrolls processed
     * @param  int  $total  Total payrolls to process
     * @param  string  $status  Current status (processing|completed|failed)
     * @param  string|null  $currentEmployee  Currently processing employee name
     * @param  string|null  $currentAllocation  Currently processing allocation label
     * @param  array  $stats  Stats array with successful, failed, advances_created
     */
    public function __construct(
        int $batchId,
        int $processed,
        int $total,
        string $status,
        ?string $currentEmployee = null,
        ?string $currentAllocation = null,
        array $stats = []
    ) {
        $this->batchId = $batchId;
        $this->processed = $processed;
        $this->total = $total;
        $this->status = $status;
        $this->currentEmployee = $currentEmployee;
        $this->currentAllocation = $currentAllocation;
        $this->stats = $stats;
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('payroll-bulk.'.$this->batchId);
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'payroll.progress';
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'batchId' => $this->batchId,
            'processed' => $this->processed,
            'total' => $this->total,
            'status' => $this->status,
            'currentEmployee' => $this->currentEmployee,
            'currentAllocation' => $this->currentAllocation,
            'stats' => [
                'successful' => $this->stats['successful'] ?? 0,
                'failed' => $this->stats['failed'] ?? 0,
                'advances_created' => $this->stats['advances_created'] ?? 0,
            ],
        ];
    }
}
