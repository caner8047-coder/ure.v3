<?php
// Bu dosyayı tarayıcıdan bir kez ac, webhook kurulsun.
// Kurulumdan sonra bu dosyayı sil!

$BOT_TOKEN = '8954600072:AAFsC9MAmzgC2trOjldOjlFuZlDhb_M61z8';
$WEBHOOK_URL = 'https://fa.zolm.com.tr/telegram/webhook';

// Webhook'u kur
$url = "https://api.telegram.org/bot{$BOT_TOKEN}/setWebhook";
$data = json_encode([
    'url' => $WEBHOOK_URL,
    'allowed_updates' => ['message'],
    'drop_pending_updates' => true,
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$result = json_decode($response, true);

// Durum bilgisi
echo "<h2>Telegram Webhook Kurulumu</h2>";
echo "<pre>";
echo "URL: {$WEBHOOK_URL}\n\n";

if ($result['ok'] ?? false) {
    echo "✅ Webhook basariyla kuruldu!\n\n";
} else {
    echo "❌ Hata: " . ($result['description'] ?? 'Bilinmeyen hata') . "\n\n";
}

// Mevcut webhook durumunu goster
$infoUrl = "https://api.telegram.org/bot{$BOT_TOKEN}/getWebhookInfo";
$infoResponse = curl_exec(curl_init($infoUrl));
$info = json_decode($infoResponse, true);

echo "Mevcut webhook durumu:\n";
echo "URL: " . ($info['result']['url'] ?? 'Tanimsiz') . "\n";
echo "Son hata: " . ($info['result']['last_error_message'] ?? 'Yok') . "\n";
echo "Bekleyen: " . ($info['result']['pending_update_count'] ?? 0) . "\n";
echo "</pre>";

echo "<br><hr>";
echo "<p><b>NOT:</b> Bu islemi bir kez yaptiktan sonra bu dosyayi silin!</p>";
echo "<p>Test icin Telegram'da bot'a mesaj yazin.</p>";
?>
