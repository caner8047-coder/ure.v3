<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CriticalStockAlert implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $stockRowNo,
        public ?int $componentNo,
        public ?int $departmentNo,
        public ?int $productNo,
        public string $componentName,
        public string $departmentName,
        public int $currentQuantity,
        public int $thresholdQuantity,
        public int $quantityDelta,
        public ?string $forecastSummary = null,
    ) {}

    /**
     * Stok uyarı kanalına yayın.
     */
    public function broadcastOn(): array
    {
        $channels = [new PrivateChannel('stock-alerts')];

        if ($this->departmentNo && $this->departmentNo > 0) {
            $channels[] = new PrivateChannel('department.' . $this->departmentNo);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'stock.critical-alert';
    }

    public function broadcastWith(): array
    {
        return [
            'stock_row_no' => $this->stockRowNo,
            'component_no' => $this->componentNo,
            'department_no' => $this->departmentNo,
            'product_no' => $this->productNo,
            'component_name' => $this->componentName,
            'department_name' => $this->departmentName,
            'current_quantity' => $this->currentQuantity,
            'threshold_quantity' => $this->thresholdQuantity,
            'quantity_delta' => $this->quantityDelta,
            'severity' => $this->currentQuantity <= 0 ? 'critical' : 'warning',
            'forecast_summary' => $this->forecastSummary,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
