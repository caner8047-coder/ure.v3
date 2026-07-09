<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramNotificationLog extends Model
{
    protected $table = 'telegram_notification_logs';

    protected $fillable = [
        'event_type',
        'task_no',
        'order_no',
        'order_item_no',
        'message_body',
        'status',
        'attempts',
        'last_error',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'task_no' => 'integer',
        'order_no' => 'integer',
        'order_item_no' => 'integer',
        'attempts' => 'integer',
    ];

    public function markSent(): void
    {
        $this->update([
            'status' => 'sent',
            'attempts' => $this->attempts + 1,
            'sent_at' => now(),
            'last_error' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => 'failed',
            'attempts' => $this->attempts + 1,
            'last_error' => mb_substr($error, 0, 1000),
        ]);
    }

    public function markPendingRetry(): void
    {
        $this->update([
            'attempts' => $this->attempts + 1,
        ]);
    }
}
