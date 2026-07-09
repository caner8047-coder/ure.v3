<?php

namespace App\Console\Commands;

use App\Services\TelegramNotificationService;
use Illuminate\Console\Command;

class TestTelegramNotification extends Command
{
    protected $signature = 'telegram:test';

    protected $description = 'Telegram bot baglantisini ve chat ID dogrulamasini test eder';

    public function handle(TelegramNotificationService $telegram): int
    {
        $this->info('Telegram baglantisi test ediliyor...');
        $this->newLine();

        // 1) Bot token testi (getMe)
        $this->line('1) Bot token dogrulaniyor (getMe)...');
        $meResult = $telegram->getMe();

        if (!isset($meResult['ok']) || $meResult['ok'] !== true) {
            $this->error('   Basarisiz: ' . ($meResult['description'] ?? 'Bilinmeyen hata'));
            return self::FAILURE;
        }

        $botName = $meResult['result']['username'] ?? 'Bilinmiyor';
        $this->info("   Basarili. Bot: @{$botName}");

        // 2) Chat ID testi (sendMessage)
        $this->newLine();
        $this->line('2) Chat ID dogrulaniyor (sendMessage)...');
        $sendResult = $telegram->sendMessage(
            "🔔 <b>Test Bildirimi</b>\n\nartisan telegram:test komutu ile gonderildi.\n🤖 Bot: @{$botName}\n📅 Tarih: " . now()->format('d/m/Y H:i')
        );

        if (!isset($sendResult['ok']) || $sendResult['ok'] !== true) {
            $errorCode = $sendResult['error_code'] ?? 0;
            $errorDesc = $sendResult['description'] ?? 'Bilinmeyen hata';

            if ($errorCode === 400 && str_contains($errorDesc, 'chat not found')) {
                $this->error("   Basarisiz: Chat ID bulunamadi. Botu hedef gruba ekleyin veya chat ID'yi kontrol edin.");
            } else {
                $this->error("   Basarisiz: HTTP {$errorCode}: {$errorDesc}");
            }
            return self::FAILURE;
        }

        $this->info('   Basarili. Test mesaji gonderildi.');
        $this->newLine();
        $this->info("Bot: @{$botName}");
        $this->info("Chat ID: " . $telegram->getChatId());
        $this->newLine();
        $this->info('Telegram baglantisi tamamen basarili.');

        return self::SUCCESS;
    }
}
