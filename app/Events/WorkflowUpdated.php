<?php

namespace App\Events;

use App\Models\Workflow;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class WorkflowUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public Workflow $workflow) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('workflow.'.$this->workflow->id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'workflow.updated';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->workflow->id,
            'status' => $this->workflow->status->value,
        ];
    }
}
