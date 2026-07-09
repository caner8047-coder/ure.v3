<?php

namespace App\Console\Commands;

use App\Services\AIBrainService;
use App\Services\TelegramNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramAiPoll extends Command
{
    protected $signature = 'telegram:ai-poll';
    protected $description = 'Telegram dan gelen mesajlari control et ve AI ile cevap ver';

    public function handle(): int
    {
        $telegram = app(TelegramNotificationService::class);
        $token = $telegram->getBotToken();

        if ($token === '') {
            $this->error('Bot token tanimli degil.');
            return self::FAILURE;
        }

        // Son update ID'yi oku
        $offset = (int) cache('telegram_ai_poll_offset', 0);
        if ($offset > 0) {
            $offset++;
        }

        $this->info("Polling baslatildi... (offset: {$offset})");

        try {
            $response = Http::timeout(30)
                ->get("https://api.telegram.org/bot{$token}/getUpdates", [
                    'offset' => $offset > 0 ? $offset : '',
                    'limit' => 10,
                    'timeout' => 15,
                ]);

            if (!$response->successful()) {
                $this->error("Telegram API hatasi: " . $response->body());
                return self::FAILURE;
            }

            $data = $response->json();
            if (empty($data['result'])) {
                $this->info("Yeni mesaj yok.");
                return self::SUCCESS;
            }

            $chatId = $telegram->getChatId();
            $ai = app(AIBrainService::class);
            $processed = 0;

            foreach ($data['result'] as $update) {
                $updateId = $update['update_id'] ?? 0;

                // Sadece mesajlari isle
                if (!isset($update['message']['text']) || !isset($update['message']['chat'])) {
                    cache(['telegram_ai_poll_offset' => $updateId], now()->addHours(24));
                    continue;
                }

                $text = $update['message']['text'];
                $senderChatId = (string) $update['message']['chat']['id'];

                // Chat ID kontrolu
                if ($chatId !== '' && $senderChatId !== $chatId) {
                    cache(['telegram_ai_poll_offset' => $updateId], now()->addHours(24));
                    continue;
                }

                // Komut kontrolu
                if ($text === '/start') {
                    $telegram->sendMessage("Merhaba! Ben ZemMobilya AI asistaniyim.\nSorularinizi yazabilirsiniz.\n\nKomutlar:\n/devam - Devam eden gorevler\n/stok - Stok durumu\n/personel - Personel listesi");
                    cache(['telegram_ai_poll_offset' => $updateId], now()->addHours(24));
                    $processed++;
                    continue;
                }

                // Komut veya normal soru
                $this->info("Soru alindi: " . mb_substr($text, 0, 50));
                $cevap = $ai->soruCevapla($text, $senderChatId);
                $telegram->sendMessage($cevap);

                cache(['telegram_ai_poll_offset' => $updateId], now()->addHours(24));
                $processed++;
            }

            $this->info("{$processed} mesaj islendi.");
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Hata: " . $e->getMessage());
            Log::error('Telegram AI poll hatasi: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
