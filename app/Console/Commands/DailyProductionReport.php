<?php

namespace App\Console\Commands;

use App\Services\AIBrainService;
use App\Services\TelegramNotificationService;
use Illuminate\Console\Command;

class DailyProductionReport extends Command
{
    protected $signature = 'telegram:daily-report';
    protected $description = 'Gunluk uretim raporunu Telegram a gonder';

    public function handle(): int
    {
        $telegram = app(TelegramNotificationService::class);
        if (!$telegram->isEnabled()) {
            $this->info('Telegram aktif degil.');
            return self::SUCCESS;
        }

        $ai = app(AIBrainService::class);
        $soru = "Bugunun ozetini ver: kac gorev tamamlandi, kac gorev devam ediyor, kritik stoklar, personel durumu. Kisa ve oz bir rapor hazirla.";

        $cevap = $ai->soruCevapla($soru);

        $mesaj = "📋 GUNLUK URETIM RAPORU\n";
        $mesaj .= "📅 " . now()->format('d/m/Y H:i') . "\n";
        $mesaj .= "━━━━━━━━━━━━━━━━━━━━━\n\n";
        $mesaj .= $cevap;

        $sonuc = $telegram->sendMessage($mesaj);

        if ($sonuc['ok'] ?? false) {
            $this->info('Gunluk rapor gonderildi.');
            return self::SUCCESS;
        }

        $this->error('Rapor gonderilemedi: ' . ($sonuc['description'] ?? 'Bilinmeyen hata'));
        return self::FAILURE;
    }
}
