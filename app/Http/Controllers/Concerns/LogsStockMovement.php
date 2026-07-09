<?php

namespace App\Http\Controllers\Concerns;

use App\Services\StockMovementLogger;

trait LogsStockMovement
{
    private function logStockMovement(object|array|null $before, object|array|null $after, array $attributes = []): void
    {
        try {
            app(StockMovementLogger::class)->logChange($before, $after, $attributes);
        } catch (\Throwable) {
            // Stok ekstresi akisi bozmasin.
        }
    }

    private function logRequiredStockMovement(object|array|null $before, object|array|null $after, array $attributes = []): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('stock_movements')) {
            return;
        }

        $movement = app(StockMovementLogger::class)->logChange($before, $after, $attributes);
        if (!$movement) {
            throw new \RuntimeException('Kritik stok hareketi kaydedilemedi.');
        }
    }
}
