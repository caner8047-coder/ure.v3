<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public string $title,
        public string $message,
        public string $channel = 'system',
        public string $severity = 'info',
    ) {}

    /**
     * Kullanıcının kişisel kanalına yayın.
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'notification.new';
    }

    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
            'title' => $this->title,
            'message' => $this->message,
            'channel' => $this->channel,
            'severity' => $this->severity,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
