<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkOrderStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $orderItemNo,
        public int $workOrderNo,
        public ?string $statusBefore,
        public ?string $statusAfter,
        public ?int $departmentId,
        public ?string $actorName,
        public ?string $titleHuman,
        public ?string $summaryHuman,
        public array $snapshotData = [],
    ) {}

    /**
     * Departman private kanalı + genel üretim kanalına yayın.
     */
    public function broadcastOn(): array
    {
        $channels = [new Channel('production')];

        if ($this->departmentId && $this->departmentId > 0) {
            $channels[] = new PrivateChannel('department.' . $this->departmentId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'work-order.status-changed';
    }

    public function broadcastWith(): array
    {
        return [
            'order_item_no' => $this->orderItemNo,
            'work_order_no' => $this->workOrderNo,
            'status_before' => $this->statusBefore,
            'status_after' => $this->statusAfter,
            'department_id' => $this->departmentId,
            'actor_name' => $this->actorName,
            'title' => $this->titleHuman,
            'summary' => $this->summaryHuman,
            'snapshot' => $this->snapshotData,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
