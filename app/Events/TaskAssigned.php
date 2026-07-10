<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TaskAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $taskNo,
        public int $personnelNo,
        public ?int $departmentId,
        public string $personnelName,
        public string $taskDescription,
        public ?string $productName = null,
        public ?int $orderItemNo = null,
        public ?string $assignedBy = null,
    ) {}

    /**
     * Personelin kişisel kanalı + departman kanalına yayın.
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('user.' . $this->personnelNo)];

        if ($this->departmentId && $this->departmentId > 0) {
            $channels[] = new PrivateChannel('department.' . $this->departmentId);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'task.assigned';
    }

    public function broadcastWith(): array
    {
        return [
            'task_no' => $this->taskNo,
            'personnel_no' => $this->personnelNo,
            'department_id' => $this->departmentId,
            'personnel_name' => $this->personnelName,
            'task_description' => $this->taskDescription,
            'product_name' => $this->productName,
            'order_item_no' => $this->orderItemNo,
            'assigned_by' => $this->assignedBy,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
