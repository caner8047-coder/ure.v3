<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AIBrainService
{
    private const API_URL = 'https://openrouter.ai/api/v1/chat/completions';
    private const MAX_ISTEK_DAKIKADA = 10;
    private const DEFAULT_MODEL = 'nvidia/nemotron-3-ultra-550b-a55b:free';
    private const MODEL_ALIASES = [
        'nametron/nametron-ultra-free' => self::DEFAULT_MODEL,
        'nvidia/nemotron-3-ultra-550b-a55b' => self::DEFAULT_MODEL,
    ];

    private static int $istekSayisi = 0;
    private static string $sonIstekZamani = '';

    public function __construct(
        protected AppSettingService $settings
    ) {}

    /**
     * Kullanici sorusuna AI ile cevap ver.
     */
    public function soruCevapla(string $soru): string
    {
        if (!$this->settings->bool('ai_brain_enabled', false)) {
            return "AI Brain su anda kapali. Ayarlar sayfasindan aktiflestirilebilir.";
        }

        if (!$this->rateLimitKontrol()) {
            return "Cok fazla istek gonderdiniz. Lutfen 1 dakika bekleyin.";
        }

        $apiKey = $this->getApiKey();
        if ($apiKey === '') {
            return "AI Brain yapilandirilmamis. OpenRouter API key gerekli.";
        }

        $model = $this->getModel();
        $maxTokens = intval($this->settings->get('openrouter_max_tokens', '1024'));

        $systemPrompt = $this->systemPromptOlustur();

        try {
            $response = Http::timeout(60)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $apiKey,
                    'HTTP-Referer' => config('app.url', 'https://zemhome.com.tr'),
                    'Content-Type' => 'application/json',
                ])
                ->post(self::API_URL, [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $soru],
                    ],
                    'max_tokens' => $maxTokens,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $cevap = $data['choices'][0]['message']['content'] ?? 'Cevap alinamadi.';

                $this->logEkle('ai_brain', 'soru_cevap', true, null, null, [
                    'soru_uzunlugu' => strlen($soru),
                    'cevap_uzunlugu' => strlen($cevap),
                ]);

                return $cevap;
            }

            $hata = $response->json('error.message', 'Bilinmeyen hata');
            $this->logEkle('ai_brain', 'soru_cevap', false, null, null, ['hata' => $hata]);

            return "AI hatasi: " . $hata;
        } catch (\Throwable $e) {
            $this->logEkle('ai_brain', 'soru_cevap', false, null, null, ['hata' => $e->getMessage()]);
            return "Hata: " . $e->getMessage();
        }
    }

    /**
     * Veritabanindan bilgileri cekip system prompt olustur.
     */
    private function systemPromptOlustur(): string
    {
        $sb = "Sen ZemMobilya mobilya uretim yonetim sistemi asistanisin.\n";
        $sb .= "Kullanici sana uretim, stok, personel, siparis hakkinda sorular soracak.\n";
        $sb .= "Asagidaki veritabani bilgilerini kullanarak Turkce cevap ver.\n";
        $sb .= "Kisa, net ve bilgilendirici cevaplar ver.\n";
        $sb .= "Hassas bilgi (sifre, maas) paylasmam.\n\n";

        $sb .= "AKTIF GOREVLER:\n" . $this->aktifGorevleriGetir(20) . "\n\n";
        $sb .= "KRITIK STOKLAR:\n" . $this->kritikStoklariGetir(15) . "\n\n";
        $sb .= "SON TAMAMLANANLAR:\n" . $this->sonTamamlananGorevleriGetir(10) . "\n\n";
        $sb .= "PERSONEL:\n" . $this->personelListesiGetir(30) . "\n\n";
        $sb .= "STOK DURUMU:\n" . $this->stokDurumunuGetir(20);

        return $sb;
    }

    private function getApiKey(): string
    {
        $key = env('OPENROUTER_API_KEY', '');
        if ($key !== '') return $key;
        return (string) $this->settings->get('openrouter_api_key', '');
    }

    private function getModel(): string
    {
        $model = trim((string) $this->settings->get('openrouter_model', self::DEFAULT_MODEL));
        if ($model === '') {
            return self::DEFAULT_MODEL;
        }

        return self::MODEL_ALIASES[$model] ?? $model;
    }

    private function rateLimitKontrol(): bool
    {
        $now = now()->format('Y-m-d H:i');
        if (self::$sonIstekZamani !== $now) {
            self::$istekSayisi = 0;
            self::$sonIstekZamani = $now;
        }
        self::$istekSayisi++;
        return self::$istekSayisi <= self::MAX_ISTEK_DAKIKADA;
    }

    // ===== Kontrollu Veri Cekme Fonksiyonlari =====

    private function aktifGorevleriGetir(int $maxSatir): string
    {
        return $this->queryBaslat(
            "SELECT p.Ad, p.Soyad, u.UrunID, au.AraUrunAdi, b.BolumAdi, pg.Adet, pg.BekleyenAdet, pg.Onay
             FROM tbPersonelGorev pg
             JOIN tbPersonel p ON pg.PersonelNo = p.PersonelNo
             JOIN tbUrunler u ON pg.UrunIDNo = u.No
             JOIN tbAraUrun au ON pg.AraUrunAdiNo = au.No
             JOIN tbBolum b ON pg.BolumAdiNo = b.No
             WHERE pg.Adet > 0
             ORDER BY p.Ad, p.Soyad
             LIMIT $maxSatir"
        );
    }

    private function kritikStoklariGetir(int $maxSatir): string
    {
        return $this->queryBaslat(
            "SELECT b.BolumAdi, au.AraUrunAdi, s.Adet, s.TamponMiktar
             FROM tbBolumAraStok s
             JOIN tbBolum b ON s.BolumAdiNo = b.No
             JOIN tbAraUrun au ON s.AraUrunAdiNo = au.No
             WHERE s.Adet <= s.TamponMiktar
             ORDER BY s.Adet ASC
             LIMIT $maxSatir"
        );
    }

    private function sonTamamlananGorevleriGetir(int $maxSatir): string
    {
        return $this->queryBaslat(
            "SELECT p.Ad, p.Soyad, u.UrunID, g.GorevBaslamaTarihi, g.GorevBitisTarihi, g.ToplamAdet, g.Performans
             FROM tbGorevler g
             JOIN tbPersonel p ON g.PersonelNo = p.PersonelNo
             JOIN tbUrunler u ON g.UrunIDNo = u.No
             ORDER BY g.No DESC
             LIMIT $maxSatir"
        );
    }

    private function personelListesiGetir(int $maxSatir): string
    {
        return $this->queryBaslat(
            "SELECT PersonelNo, Ad, Soyad, BolumAdiNo FROM tbPersonel ORDER BY Ad LIMIT $maxSatir"
        );
    }

    private function stokDurumunuGetir(int $maxSatir): string
    {
        return $this->queryBaslat(
            "SELECT b.BolumAdi, au.AraUrunAdi, s.Adet, s.TamponMiktar
             FROM tbBolumAraStok s
             JOIN tbBolum b ON s.BolumAdiNo = b.No
             JOIN tbAraUrun au ON s.AraUrunAdiNo = au.No
             ORDER BY s.Adet ASC
             LIMIT $maxSatir"
        );
    }

    private function queryBaslat(string $sql): string
    {
        try {
            $results = DB::select($sql);
            if (empty($results)) return "Kayit bulunamadi.";

            $sb = '';
            foreach ($results as $row) {
                $parts = [];
                foreach ($row as $key => $val) {
                    $parts[] = "$key: " . ($val ?? '');
                }
                $sb .= implode(' | ', $parts) . "\n";
            }
            return $sb;
        } catch (\Throwable $e) {
            return "Hata: " . $e->getMessage();
        }
    }

    private function logEkle(string $kaynak, string $islem, bool $basarili, ?int $gorevNo, ?int $personelNo, ?array $ilave): void
    {
        try {
            if (Schema::hasTable('telegram_notification_logs')) {
                DB::table('telegram_notification_logs')->insert([
                    'event_type' => $kaynak . '_' . $islem,
                    'task_no' => $gorevNo,
                    'message_body' => json_encode($ilave ?? []),
                    'status' => $basarili ? 'sent' : 'failed',
                    'attempts' => 1,
                    'sent_at' => $basarili ? now() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        } catch (\Throwable) {
            // Log hatasi kritik degil
        }
    }
}
