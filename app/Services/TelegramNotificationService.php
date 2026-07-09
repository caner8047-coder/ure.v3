<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class TelegramNotificationService
{
    private const API_BASE = 'https://api.telegram.org';
    private const DEFAULT_OPENROUTER_MODEL = 'nvidia/nemotron-3-ultra-550b-a55b:free';
    private const OPENROUTER_MODEL_ALIASES = [
        'nametron/nametron-ultra-free' => self::DEFAULT_OPENROUTER_MODEL,
        'nvidia/nemotron-3-ultra-550b-a55b' => self::DEFAULT_OPENROUTER_MODEL,
    ];

    public function __construct(
        protected AppSettingService $settings
    ) {}

    public function isEnabled(): bool
    {
        return $this->settings->bool('telegram_notifications_enabled', false)
            && $this->getBotToken() !== ''
            && $this->getChatId() !== '';
    }

    public function isTaskCompletedNotificationEnabled(): bool
    {
        return $this->settings->bool('telegram_notify_task_completed', true);
    }

    public function getBotToken(): string
    {
        $token = env('TELEGRAM_BOT_TOKEN', '');
        if ($token !== '') {
            return $token;
        }

        return (string) $this->settings->get('telegram_bot_token', '');
    }

    public function getMaskedBotToken(): string
    {
        $token = $this->getBotToken();
        if ($token === '' || strlen($token) < 10) {
            return '••••••••';
        }

        return substr($token, 0, 5) . '••••••••' . substr($token, -4);
    }

    public function getChatId(): string
    {
        $chatId = env('TELEGRAM_CHAT_ID', '');
        if ($chatId !== '') {
            return (string) $chatId;
        }

        return (string) $this->settings->get('telegram_chat_id', '');
    }

    public function buildTaskCompletedMessage(array $data): string
    {
        $gorevNo = e((string) ($data['task_no'] ?? ''));
        $urunAdi = e($this->messageValue($data['product_name'] ?? null));
        $araUrunAdi = e($this->messageValue($data['component_name'] ?? null));
        $personelAdi = e($this->messageValue($data['personnel_name'] ?? null));
        $bolumAdi = e($this->messageValue($data['department_name'] ?? null));
        $adet = e((string) ($data['completed_quantity'] ?? ''));
        $tarih = e(now()->format('d/m/Y H:i'));
        $siparisNo = $data['order_no'] ?? null;

        $lines = [
            '✅ <b>Üretim Tamamlandı</b>',
            '',
            "📋 Görev No: <b>{$gorevNo}</b>",
            "🏭 Ürün: {$urunAdi}",
            "📦 Ara Ürün: {$araUrunAdi}",
            "👤 Personel: {$personelAdi}",
            "🏢 Departman: {$bolumAdi}",
            "📊 Tamamlanan: <b>{$adet} adet</b>",
            "📅 Tarih: {$tarih}",
        ];

        if ($siparisNo !== null && trim((string) $siparisNo) !== '') {
            $lines[] = '';
            $lines[] = '🔗 Sipariş: ' . e((string) $siparisNo);
        }

        return implode("\n", $lines);
    }

    public function buildTaskStartedMessage(array $data): string
    {
        $gorevNo = e((string) ($data['task_no'] ?? ''));
        $urunAdi = e($this->messageValue($data['product_name'] ?? null));
        $araUrunAdi = e($this->messageValue($data['component_name'] ?? null));
        $personelAdi = e($this->messageValue($data['personnel_name'] ?? null));
        $bolumAdi = e($this->messageValue($data['department_name'] ?? null));
        $adet = e((string) ($data['accepted_quantity'] ?? ''));
        $tarih = e(now()->format('d/m/Y H:i'));
        $siparisNo = $data['order_no'] ?? null;

        $lines = [
            '🟢 <b>Görev Kabul Edildi</b>',
            '',
            "📋 Görev No: <b>{$gorevNo}</b>",
            "🏭 Ürün: {$urunAdi}",
            "📦 Ara Ürün: {$araUrunAdi}",
            "👤 Personel: {$personelAdi}",
            "🏢 Departman: {$bolumAdi}",
            "📊 Adet: <b>{$adet}</b>",
            "📅 Tarih: {$tarih}",
        ];

        if ($siparisNo !== null && trim((string) $siparisNo) !== '') {
            $lines[] = '';
            $lines[] = '🔗 Sipariş: ' . e((string) $siparisNo);
        }

        return implode("\n", $lines);
    }

    public function isTaskStartedNotificationEnabled(): bool
    {
        return $this->settings->bool('telegram_notify_task_started', true);
    }

    private function messageValue(mixed $value, string $fallback = 'Belirtilmedi'): string
    {
        $text = trim((string) ($value ?? ''));

        return $text !== '' ? $text : $fallback;
    }

    public function sendMessage(string $text, array $options = []): array
    {
        $token = $this->getBotToken();
        $chatId = $this->getChatId();

        if ($token === '' || $chatId === '') {
            return ['ok' => false, 'description' => 'Bot token veya chat ID tanımlı değil.'];
        }

        $payload = array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ], $options);

        $response = Http::timeout(10)
            ->post(self::API_BASE . "/bot{$token}/sendMessage", $payload);

        return $response->json();
    }

    public function getMe(): array
    {
        $token = $this->getBotToken();
        if ($token === '') {
            return ['ok' => false, 'description' => 'Bot token tanımlı değil.'];
        }

        $response = Http::timeout(10)
            ->get(self::API_BASE . "/bot{$token}/getMe");

        return $response->json();
    }

    public function validateSettings(): array
    {
        $errors = [];

        if ($this->getBotToken() === '') {
            $errors[] = 'Bot token tanımlı değil.';
        }
        if ($this->getChatId() === '') {
            $errors[] = 'Chat ID tanımlı değil.';
        }

        return $errors;
    }

    public function settingsPageData(): array
    {
        $tokenOk = $this->getBotToken() !== '';
        $chatId = $this->getChatId();

        return [
            'enabled' => $this->settings->bool('telegram_notifications_enabled', false),
            'bot_token_masked' => $this->getMaskedBotToken(),
            'bot_token_set' => $tokenOk,
            'chat_id' => $chatId,
            'notify_task_completed' => $this->settings->bool('telegram_notify_task_completed', true),
            'notify_task_started' => $this->settings->bool('telegram_notify_task_started', true),
            'ai_brain_enabled' => $this->settings->bool('ai_brain_enabled', false),
            'openrouter_model' => $this->normalizeOpenRouterModel(
                (string) $this->settings->get('openrouter_model', self::DEFAULT_OPENROUTER_MODEL)
            ),
        ];
    }

    private function normalizeOpenRouterModel(string $model): string
    {
        $model = trim($model);
        if ($model === '') {
            return self::DEFAULT_OPENROUTER_MODEL;
        }

        return self::OPENROUTER_MODEL_ALIASES[$model] ?? $model;
    }

    public function getRecentLogs(int $limit = 50): array
    {
        if (!Schema::hasTable('telegram_notification_logs')) {
            return [];
        }

        return \DB::table('telegram_notification_logs')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
