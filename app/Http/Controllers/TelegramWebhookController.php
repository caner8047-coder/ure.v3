<?php

namespace App\Http\Controllers;

use App\Services\AIBrainService;
use App\Services\TelegramNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    /**
     * Telegram'dan gelen mesajlari karsila.
     * Webhook URL: /telegram/webhook
     */
    public function handle(Request $request)
    {
        try {
            // Secret token kontrolu
            $secret = config('services.telegram.webhook_secret', env('TELEGRAM_WEBHOOK_SECRET', ''));
            if ($secret !== '' && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $secret) {
                Log::warning('Telegram webhook: gecersiz secret token');
                return response()->json(['ok' => false], 403);
            }

            $update = $request->all();

            // Mesaj var mi?
            if (!isset($update['message']['text']) || !isset($update['message']['chat'])) {
                return response()->json(['ok' => true]);
            }

            $text = $update['message']['text'];
            $chatId = $update['message']['chat']['id'];

            $telegram = app(TelegramNotificationService::class);

            // Chat ID kontrolu
            $izinliChatId = env('TELEGRAM_IZINLI_CHAT_ID', '') ?: $telegram->getChatId();
            if ($izinliChatId !== '' && (string) $chatId !== $izinliChatId) {
                return response()->json(['ok' => true]);
            }

            // AI cevabi (chatId ile memory icin)
            $ai = app(AIBrainService::class);
            $cevap = $ai->soruCevapla($text, $chatId);

            // Cevabi Telegram'a gonder
            $telegram->sendMessage($cevap, ['chat_id' => $chatId]);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('Telegram webhook hatasi: ' . $e->getMessage());
            return response()->json(['ok' => true]);
        }
    }
}
