<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ForecastCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $targetId,
        public string $targetType,
        public string $periodType
    ) {}

    /**
     * Broadcast to the forecasting private channel.
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('forecasting')];
    }

    public function broadcastAs(): string
    {
        return 'forecast.completed';
    }

    public function broadcastWith(): array
    {
        return [
            'target_id' => $this->targetId,
            'target_type' => $this->targetType,
            'period_type' => $this->periodType,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
