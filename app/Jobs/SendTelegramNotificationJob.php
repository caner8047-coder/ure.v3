<?php

namespace App\Jobs;

use App\Models\TelegramNotificationLog;
use App\Services\TelegramNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendTelegramNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $maxExceptions = 2;

    public function __construct(
        public int $logId
    ) {}

    public function handle(TelegramNotificationService $telegram): void
    {
        $log = TelegramNotificationLog::find($this->logId);
        if (!$log || $log->status === 'sent') {
            return;
        }

        $log->update(['status' => 'sending']);

        $response = $telegram->sendMessage($log->message_body);

        if (isset($response['ok']) && $response['ok'] === true) {
            $log->markSent();
            return;
        }

        $errorCode = $response['error_code'] ?? 0;
        $errorDescription = $response['description'] ?? 'Bilinmeyen hata';

        if ($errorCode === 429) {
            $retryAfter = $response['parameters']['retry_after'] ?? 30;
            $log->markPendingRetry();
            $this->release((int) $retryAfter);
            return;
        }

        $log->markFailed("HTTP {$errorCode}: {$errorDescription}");
    }

    public function failed(\Throwable $exception): void
    {
        $log = TelegramNotificationLog::find($this->logId);
        if ($log && $log->status !== 'sent') {
            $log->markFailed($exception->getMessage());
        }
    }
}
