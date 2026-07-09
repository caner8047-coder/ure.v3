<?php

namespace App\Http\Controllers;

use App\Services\AppSettingService;
use App\Services\TelegramNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramSettingsController extends Controller
{
    private const DEFAULT_OPENROUTER_MODEL = 'nvidia/nemotron-3-ultra-550b-a55b:free';
    private const OPENROUTER_MODEL_ALIASES = [
        'nametron/nametron-ultra-free' => self::DEFAULT_OPENROUTER_MODEL,
        'nvidia/nemotron-3-ultra-550b-a55b' => self::DEFAULT_OPENROUTER_MODEL,
    ];

    public function getSettings(TelegramNotificationService $telegram): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $telegram->settingsPageData(),
        ]);
    }

    public function updateSettings(Request $request, AppSettingService $settings): JsonResponse
    {
        $request->validate([
            'enabled' => 'sometimes|boolean',
            'bot_token' => 'nullable|string|max:200',
            'chat_id' => 'nullable|string|max:50',
            'notify_task_completed' => 'sometimes|boolean',
            'ai_brain_enabled' => 'sometimes|boolean',
            'openrouter_api_key' => 'nullable|string|max:300',
            'openrouter_model' => 'nullable|string|max:120',
        ]);

        if ($request->has('enabled')) {
            $settings->set('telegram_notifications_enabled', $request->boolean('enabled'), 'boolean');
        }

        if ($request->filled('bot_token')) {
            $settings->set('telegram_bot_token', $request->input('bot_token'), 'string');
        }

        if ($request->filled('chat_id')) {
            $settings->set('telegram_chat_id', $request->input('chat_id'), 'string');
        }

        if ($request->has('notify_task_completed')) {
        $settings->set(
            'telegram_notify_task_completed',
            $request->boolean('notify_task_completed', true),
            'boolean'
        );

        $settings->set(
            'telegram_notify_task_started',
            $request->boolean('notify_task_started', true),
            'boolean'
        );
        }

        // AI Brain ayarlari
        if ($request->has('ai_brain_enabled')) {
            $settings->set('ai_brain_enabled', $request->boolean('ai_brain_enabled'), 'boolean');
        }
        if ($request->filled('openrouter_api_key')) {
            $settings->set('openrouter_api_key', $request->input('openrouter_api_key'), 'string');
        }
        if ($request->filled('openrouter_model')) {
            $settings->set(
                'openrouter_model',
                $this->normalizeOpenRouterModel((string) $request->input('openrouter_model')),
                'string'
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Ayarlar kaydedildi.',
        ]);
    }

    public function testConnection(TelegramNotificationService $telegram): JsonResponse
    {
        $meResult = $telegram->getMe();
        if (!isset($meResult['ok']) || $meResult['ok'] !== true) {
            return response()->json([
                'success' => false,
                'message' => 'Bot bağlantısı başarısız: ' . ($meResult['description'] ?? 'Bilinmeyen hata'),
            ]);
        }

        $botName = $meResult['result']['username'] ?? 'Bilinmiyor';

        $sendResult = $telegram->sendMessage(
            "🔔 <b>Test Bildirimi</b>\n\nZemuretim Telegram bildirim bağlantısı başarılı.\n🤖 Bot: @{$botName}\n📅 Tarih: " . now()->format('d/m/Y H:i')
        );

        if (!isset($sendResult['ok']) || $sendResult['ok'] !== true) {
            $errorCode = $sendResult['error_code'] ?? 0;
            $errorDesc = $sendResult['description'] ?? 'Bilinmeyen hata';

            if ($errorCode === 400 && str_contains($errorDesc, 'chat not found')) {
                return response()->json([
                    'success' => false,
                    'message' => "Bot bağlantısı başarılı ancak chat ID bulunamadı. Lütfen botu hedef gruba ekleyin veya chat ID'yi kontrol edin.",
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => "Bot bulundu ancak mesaj gönderilemedi: {$errorDesc}",
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => "Test mesajı gönderildi. Bot: @{$botName}",
            'bot_name' => $botName,
        ]);
    }

    public function getLogs(TelegramNotificationService $telegram): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $telegram->getRecentLogs(50),
        ]);
    }

    private function normalizeOpenRouterModel(string $model): string
    {
        $model = trim($model);
        if ($model === '') {
            return self::DEFAULT_OPENROUTER_MODEL;
        }

        return self::OPENROUTER_MODEL_ALIASES[$model] ?? $model;
    }
}
