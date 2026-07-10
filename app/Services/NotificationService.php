<?php

namespace App\Services;

use App\Models\User;
use App\Models\Personnel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Services\TelegramNotificationService;

class NotificationService
{
    protected TelegramNotificationService $telegramService;

    public function __construct(TelegramNotificationService $telegramService)
    {
        $this->telegramService = $telegramService;
    }

    /**
     * Send notification to a specific user/personnel.
     */
    public function sendToUser(User|Personnel $user, string $title, string $message, array $channels = ['database']): void
    {
        foreach ($channels as $channel) {
            try {
                $this->dispatchToChannel($user, $channel, $title, $message);
            } catch (\Throwable $e) {
                Log::error("Failed to send notification via channel [{$channel}] to user [{$user->getKey()}]: " . $e->getMessage());
            }
        }
    }

    /**
     * Send notification to all users matching a Spatie role.
     */
    public function sendToRole(string $roleName, string $title, string $message, array $channels = ['database']): void
    {
        // 1. Get from Personnel model
        $personnels = Personnel::role($roleName)->get();
        foreach ($personnels as $p) {
            $this->sendToUser($p, $title, $message, $channels);
        }

        // 2. Get from User model (if it exists)
        try {
            $users = User::role($roleName)->get();
            foreach ($users as $u) {
                $this->sendToUser($u, $title, $message, $channels);
            }
        } catch (\Throwable) {
            // Ignore if User table does not match
        }
    }

    /**
     * Send notification to all personnel in a specific department.
     */
    public function sendToDepartment(int $departmentId, string $title, string $message, array $channels = ['database']): void
    {
        $personnels = Personnel::where('BolumAdiNo', $departmentId)->get();
        foreach ($personnels as $p) {
            $this->sendToUser($p, $title, $message, $channels);
        }
    }

    /**
     * Dispatch the notification message to the specified channel.
     */
    protected function dispatchToChannel(User|Personnel $user, string $channel, string $title, string $message): void
    {
        $channel = strtolower(trim($channel));

        if ($channel === 'database' || $channel === 'system') {
            $this->sendDatabaseMessage($user, $title, $message);
        } elseif ($channel === 'telegram') {
            $this->sendTelegramMessage($user, $title, $message);
        } elseif ($channel === 'email' || $channel === 'mail') {
            $this->sendEmailMessage($user, $title, $message);
        } else {
            Log::warning("Unsupported notification channel: {$channel}");
        }
    }

    /**
     * Send system message via tbIletisim table.
     */
    protected function sendDatabaseMessage(User|Personnel $user, string $title, string $message): void
    {
        if (!Schema::hasTable('tbIletisim')) {
            Log::warning("tbIletisim table not found, skipping database message.");
            return;
        }

        $personnelNo = intval($user->personnel_no ?? $user->PersonelNo ?? 0);
        if ($personnelNo <= 0) {
            return;
        }

        $fullBody = "<b>" . e($title) . "</b>\n\n" . $message;

        $insert = [
            'PersonelNo' => $personnelNo,
            'BolumAdiNo' => null,
            'Mesaj' => $fullBody,
            'Tarih' => now()->format('d/m/Y'),
        ];

        if (Schema::hasColumn('tbIletisim', 'Saat')) {
            $insert['Saat'] = now()->format('H:i');
        }
        if (Schema::hasColumn('tbIletisim', 'Mail')) {
            $insert['Mail'] = $user->email ?? $user->Mail ?? null;
        }
        if (Schema::hasColumn('tbIletisim', 'AdSoyad')) {
            $insert['AdSoyad'] = 'Sistem Bildirimi';
        }
        if (Schema::hasColumn('tbIletisim', 'Okundu')) {
            $insert['Okundu'] = 0;
        }

        DB::table('tbIletisim')->insert(array_intersect_key($insert, array_flip(Schema::getColumnListing('tbIletisim'))));
    }

    /**
     * Send Telegram message.
     */
    protected function sendTelegramMessage(User|Personnel $user, string $title, string $message): void
    {
        if (!$this->telegramService->isEnabled()) {
            return;
        }

        // If the user has a custom chat ID, we could target them.
        // Otherwise, send to the global system group channel.
        $customChatId = null;
        if (isset($user->telegram_chat_id) && trim((string) $user->telegram_chat_id) !== '') {
            $customChatId = trim((string) $user->telegram_chat_id);
        } elseif (isset($user->TelegramChatId) && trim((string) $user->TelegramChatId) !== '') {
            $customChatId = trim((string) $user->TelegramChatId);
        }

        $text = "🔔 <b>" . $title . "</b>\n\n" . $message;

        if ($customChatId) {
            $this->telegramService->sendMessage($text, ['chat_id' => $customChatId]);
        } else {
            $this->telegramService->sendMessage($text);
        }
    }

    /**
     * Send Email.
     */
    protected function sendEmailMessage(User|Personnel $user, string $title, string $message): void
    {
        $email = trim((string) ($user->email ?? $user->Mail ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $name = trim((string) (($user->name ?? $user->Ad ?? '') . ' ' . ($user->surname ?? $user->Soyad ?? '')));

        Mail::send([], [], function ($mailMessage) use ($email, $name, $title, $message) {
            $mailMessage->to($email, $name)
                ->subject($title)
                ->html($this->getEmailTemplateHtml($title, $message));
        });
    }

    /**
     * HTML template for notification emails.
     */
    protected function getEmailTemplateHtml(string $title, string $message): string
    {
        $messageHtml = nl2br(e($message));

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f3f4f6; color: #1f2937; margin: 0; padding: 20px; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); overflow: hidden; border: 1px solid #e5e7eb; }
        .header { background-color: #2f8f83; padding: 20px; text-align: center; color: #ffffff; }
        .header h2 { margin: 0; font-size: 20px; font-weight: 600; }
        .content { padding: 30px; line-height: 1.6; font-size: 15px; }
        .footer { background-color: #f9fafb; padding: 15px; text-align: center; font-size: 12px; color: #6b7280; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>ZemuRetim MES</h2>
        </div>
        <div class="content">
            <h3 style="margin-top: 0; color: #2f8f83;">{$title}</h3>
            <p>{$messageHtml}</p>
        </div>
        <div class="footer">
            Bu e-posta ZemuRetim Üretim Yönetim Sistemi tarafından otomatik olarak gönderilmiştir.
        </div>
    </div>
</body>
</html>
HTML;
    }
}
