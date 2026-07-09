<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;

class AIBrainService
{
    private const API_URL = 'https://openrouter.ai/api/v1/chat/completions';
    private const MAX_ISTEK_DAKIKADA = 15;
    private const DEFAULT_MODEL = 'nvidia/nemotron-3-ultra-550b-a55b:free';
    private const MODEL_ALIASES = [
        'nametron/nametron-ultra-free' => self::DEFAULT_MODEL,
        'nvidia/nemotron-3-ultra-550b-a55b' => self::DEFAULT_MODEL,
    ];
    private const MAX_MEMORY = 20; // Son 20 mesaji hatirla

    private static int $istekSayisi = 0;
    private static string $sonIstekZamani = '';

    public function __construct(
        protected AppSettingService $settings
    ) {}

    // ===== ANA METOT =====

    public function soruCevapla(string $soru, ?string $chatId = null): string
    {
        if (!$this->settings->bool('ai_brain_enabled', false)) {
            return "AI Brain su anda kapali.";
        }

        if (!$this->rateLimitKontrol()) {
            return "Cok fazla istek. 1 dakika bekleyin.";
        }

        $apiKey = $this->getApiKey();
        if ($apiKey === '') {
            return "API key tanimli degil.";
        }

        // Komut kontrolu
        $komutCevabi = $this->komutKontrol($soru);
        if ($komutCevabi !== null) {
            return $komutCevabi;
        }

        // Conversation memory
        $gecmis = $chatId ? $this->gecmisiOku($chatId) : [];
        $gecmis[] = ['role' => 'user', 'content' => $soru];

        $model = $this->getModel();
        $maxTokens = intval($this->settings->get('openrouter_max_tokens', '1024'));
        $systemPrompt = $this->systemPromptOlustur();

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            array_slice($gecmis, -self::MAX_MEMORY)
        );

        try {
            $response = Http::timeout(60)->withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'HTTP-Referer' => config('app.url', 'https://fa.zolm.com.tr'),
                'Content-Type' => 'application/json',
            ])->post(self::API_URL, [
                'model' => $model,
                'messages' => $messages,
                'max_tokens' => $maxTokens,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $cevap = $data['choices'][0]['message']['content'] ?? 'Cevap alinamadi.';

                // Memory'ye kaydet
                if ($chatId) {
                    $gecmis[] = ['role' => 'assistant', 'content' => $cevap];
                    $this->gecmiKaydet($chatId, $gecmis);
                }

                $this->logEkle('ai_brain', 'soru_cevap', true, null, null, [
                    'soru' => mb_substr($soru, 0, 100),
                    'cevap_uzunlugu' => strlen($cevap),
                ]);

                return $cevap;
            }

            $hata = $response->json('error.message', 'Bilinmeyen hata');
            return "AI hatasi: " . $hata;
        } catch (\Throwable $e) {
            return "Hata: " . $e->getMessage();
        }
    }

    // ===== KOMUT SISTEMI =====

    private function komutKontrol(string $soru): ?string
    {
        $soru = strtolower(trim($soru));

        $komutlar = [
            '/devam' => fn() => $this->devamEdenGorevler(),
            '/stok' => fn() => $this->kritikStokRaporu(),
            '/personel' => fn() => $this->personelDurumu(),
            '/siparis' => fn() => $this->sonSiparisler(),
            '/verim' => fn() => $this->uretimVerimliligi(),
            '/uretim' => fn() => $this->uretimdekiler(),
            '/havuz' => fn() => $this->havuzGorevleri(),
            '/help' => fn() => $this->yardimMesaji(),
            '/komutlar' => fn() => $this->yardimMesaji(),
        ];

        foreach ($komutlar as $komut => $callback) {
            if ($soru === $komut || str_starts_with($soru, $komut . ' ')) {
                return $callback();
            }
        }

        return null;
    }

    private function yardimMesaji(): string
    {
        return "📋 Kullanilabilir komutlar:\n\n"
            . "/devam - Devam eden gorevler\n"
            . "/stok - Kritik stoklar\n"
            . "/personel - Personel durumu\n"
            . "/siparis - Son siparisler\n"
            . "/verim - Uretim verimliligi\n"
            . "/uretim - Suan uretilenler\n"
            . "/havuz - Havuz gorevleri\n"
            . "/help - Bu mesaj\n\n"
            . "Soru ornekleri:\n"
            . "- \"Bugun kac gorev tamamlandi?\"\n"
            . "- \"Ahmet'in stok durumu ne?\"\n"
            . "- \"Kritik stoklar hangileri?\"\n"
            . "- \"Bu hafta en cok uretim yapan kim?\"";
    }

    // ===== KOMUT IMPLEMENTASYONLARI =====

    private function devamEdenGorevler(): string
    {
        $sql = "SELECT TOP 30 p.Ad + ' ' + p.Soyad AS Personel, u.UrunID AS Urun,
                au.AraUrunAdi, b.BolumAdi, pg.Adet, pg.BekleyenAdet, pg.Onay,
                CASE WHEN pg.Onay IN ('0','false','hayir','no') THEN 'URETIMDE'
                     WHEN pg.Onay IN ('hazir','ready') THEN 'HAZIR'
                     ELSE 'BEKLEMEDE' END AS Durum
                FROM tbPersonelGorev pg
                JOIN tbPersonel p ON pg.PersonelNo = p.PersonelNo
                JOIN tbUrunler u ON pg.UrunIDNo = u.No
                JOIN tbAraUrun au ON pg.AraUrunAdiNo = au.No
                JOIN tbBolum b ON pg.BolumAdiNo = b.No
                WHERE pg.Adet > 0
                ORDER BY CASE WHEN pg.Onay IN ('0','false','hayir','no') THEN 0
                              WHEN pg.Onay IN ('hazir','ready') THEN 1 ELSE 2 END,
                         p.Ad, p.Soyad";

        $satirlar = $this->sorguCalistir($sql);
        if ($satirlar === '') return "Devam eden gorev bulunamadi.";

        $uretimde = array_filter(explode("\n", $satirlar), fn($s) => str_contains($s, 'URETIMDE'));
        $hazir = array_filter(explode("\n", $satirlar), fn($s) => str_contains($s, 'HAZIR'));

        $sonuc = "📊 DEVAM EDEN GOREVLER\n";
        $sonuc .= "━━━━━━━━━━━━━━━━━━━━━\n";

        if (!empty($uretimde)) {
            $sonuc .= "\n🟢 URETIMDE (" . count($uretimde) . " gorev):\n";
            foreach (array_slice($uretimde, 0, 10) as $s) {
                $sonuc .= "  " . $this->satirTemizle($s) . "\n";
            }
        }

        if (!empty($hazir)) {
            $sonuc .= "\n🟡 HAZIR (" . count($hazir) . " gorev):\n";
            foreach (array_slice($hazir, 0, 10) as $s) {
                $sonuc .= "  " . $this->satirTemizle($s) . "\n";
            }
        }

        return $sonuc;
    }

    private function kritikStokRaporu(): string
    {
        $sql = "SELECT TOP 15 b.BolumAdi, au.AraUrunAdi, s.Adet, s.TamponMiktar,
                CASE WHEN s.Adet = 0 THEN 'TUKENDI'
                     WHEN s.Adet <= s.TamponMiktar / 2 THEN 'KRITIK'
                     ELSE 'DIKKAT' END AS Durum
                FROM tbBolumAraStok s
                JOIN tbBolum b ON s.BolumAdiNo = b.No
                JOIN tbAraUrun au ON s.AraUrunAdiNo = au.No
                WHERE s.Adet <= s.TamponMiktar
                ORDER BY s.Adet ASC";

        $satirlar = $this->sorguCalistir($sql);
        if ($satirlar === '') return "Kritik stok bulunmuyor. Stoklar yeterli.";

        return "⚠️ KRITIK STOK RAPORU\n━━━━━━━━━━━━━━━━━━━━━\n\n" . $this->satirlariFormatla($satirlar);
    }

    private function personelDurumu(): string
    {
        $sql = "SELECT TOP 30 p.Ad + ' ' + p.Soyad AS AdSoyad, b.BolumAdi,
                (SELECT COUNT(*) FROM tbPersonelGorev pg2 WHERE pg2.PersonelNo = p.PersonelNo AND pg2.Adet > 0) AS AktifGorev,
                (SELECT ISNULL(SUM(pg2.Adet), 0) FROM tbPersonelGorev pg2 WHERE pg2.PersonelNo = p.PersonelNo) AS ToplamAdet
                FROM tbPersonel p
                JOIN tbBolum b ON p.BolumAdiNo = b.No
                ORDER BY p.Ad, p.Soyad";

        $satirlar = $this->sorguCalistir($sql);
        if ($satirlar === '') return "Personel bulunamadi.";

        return "👥 PERSONEL DURUMU\n━━━━━━━━━━━━━━━━━━━━━\n\n" . $this->satirlariFormatla($satirlar);
    }

    private function sonSiparisler(): string
    {
        if (!Schema::hasTable('tbSiparisSatir')) {
            return "Siparis tablosu bulunamadi.";
        }

        $sql = "SELECT TOP 10 s.No, s.SiparisNo, s.UrunAdi, s.Adet, s.Durum, s.Tarih
                FROM tbSiparisSatir s
                ORDER BY s.No DESC";

        $satirlar = $this->sorguCalistir($sql);
        if ($satirlar === '') return "Son siparis bulunamadi.";

        return "📦 SON SIPARISLER\n━━━━━━━━━━━━━━━━━━━━━\n\n" . $this->satirlariFormatla($satirlar);
    }

    private function uretimVerimliligi(): string
    {
        $sql = "SELECT TOP 10 p.Ad + ' ' + p.Soyad AS Personel,
                COUNT(*) AS GorevSayisi,
                ISNULL(SUM(g.ToplamAdet), 0) AS ToplamUretim,
                ISNULL(AVG(g.Performans), 0) AS OrtPerformans
                FROM tbGorevler g
                JOIN tbPersonel p ON g.PersonelNo = p.PersonelNo
                WHERE g.GorevBitisTarihi IS NOT NULL
                GROUP BY p.Ad, p.Soyad
                ORDER BY SUM(g.ToplamAdet) DESC";

        $satirlar = $this->sorguCalistir($sql);
        if ($satirlar === '') return "Verimlilik verisi bulunamadi.";

        return "📈 URETIM VERIMLILIGI\n━━━━━━━━━━━━━━━━━━━━━\n\n" . $this->satirlariFormatla($satirlar);
    }

    private function uretimdekiler(): string
    {
        $sql = "SELECT TOP 20 p.Ad + ' ' + p.Soyad AS Personel, u.UrunID AS Urun,
                au.AraUrunAdi, b.BolumAdi, pg.Adet, pg.BekleyenAdet
                FROM tbPersonelGorev pg
                JOIN tbPersonel p ON pg.PersonelNo = p.PersonelNo
                JOIN tbUrunler u ON pg.UrunIDNo = u.No
                JOIN tbAraUrun au ON pg.AraUrunAdiNo = au.No
                JOIN tbBolum b ON pg.BolumAdiNo = b.No
                WHERE pg.Adet > 0 AND pg.Onay IN ('0','false','hayir','no')
                ORDER BY p.Ad, p.Soyad";

        $satirlar = $this->sorguCalistir($sql);
        if ($satirlar === '') return "Suan uretimde olan gorev yok.";

        return "🏭 SUAN URETIMDE\n━━━━━━━━━━━━━━━━━━━━━\n\n" . $this->satirlariFormatla($satirlar);
    }

    private function havuzGorevleri(): string
    {
        $sql = "SELECT TOP 15 b.BolumAdi, u.UrunID AS Urun, au.AraUrunAdi,
                SUM(bh.Adet) AS ToplamAdet
                FROM tbBolumHavuz bh
                JOIN tbUrunler u ON bh.UrunIDNo = u.No
                JOIN tbAraUrun au ON bh.AraUrunAdiNo = au.No
                JOIN tbBolum b ON bh.BolumAdiNo = b.No
                WHERE bh.Adet > 0
                GROUP BY b.BolumAdi, u.UrunID, au.AraUrunAdi
                ORDER BY SUM(bh.Adet) DESC";

        $satirlar = $this->sorguCalistir($sql);
        if ($satirlar === '') return "Havuzda gorev bulunmuyor.";

        return "🏊 HAVUZ GOREVLERI\n━━━━━━━━━━━━━━━━━━━━━\n\n" . $this->satirlariFormatla($satirlar);
    }

    // ===== SYSTEM PROMPT =====

    private function systemPromptOlustur(): string
    {
        $now = now()->format('d/m/Y H:i');
        $bugun = now()->format('d/m/Y');

        $prompt = "Sen ZemMobilya mobilya uretim yonetim sistemi AI asistanisin.\n";
        $prompt .= "Adin: ZemAI. Kullanici sana uretim, stok, personel, siparis hakkinda sorular soracak.\n";
        $prompt .= "Turkce cevap ver. Kisa, net, profesyonel ol.\n";
        $prompt .= "Hassas bilgi (sifre, maas, kisisel veri) paylasmam.\n";
        $prompt .= "Tablo formatinda cevap ver, emoji kullan.\n";
        $prompt .= "Tarih/Saat: {$now}\n\n";

        // Veritabani bilgileri
        $prompt .= "=== AKTIF GOREVLER (son 20) ===\n" . $this->aktifGorevleriGetir(20) . "\n\n";
        $prompt .= "=== KRITIK STOKLAR ===\n" . $this->kritikStoklariGetir(15) . "\n\n";
        $prompt .= "=== SON TAMAMLANANLAR (son 10) ===\n" . $this->sonTamamlananGorevleriGetir(10) . "\n\n";
        $prompt .= "=== PERSONEL ===\n" . $this->personelListesiGetir(30) . "\n\n";
        $prompt .= "=== STOK DURUMU ===\n" . $this->stokDurumunuGetir(20) . "\n\n";
        $prompt .= "=== HAVUZ GOREVLERI ===\n" . $this->havuzVerisiniGetir(10) . "\n\n";

        if (Schema::hasTable('tbSiparisSatir')) {
            $prompt .= "=== SON SIPARISLER ===\n" . $this->sonSiparisVerisiniGetir(10) . "\n\n";
        }

        $prompt .= "=== ISTATISTIKLER ===\n" . $this->istatistikleriGetir() . "\n\n";

        return $prompt;
    }

    // ===== YENI VERI KAYNAKLARI =====

    private function havuzVerisiniGetir(int $maxSatir): string
    {
        return $this->sorguCalistir(
            "SELECT TOP $maxSatir b.BolumAdi, u.UrunID, au.AraUrunAdi, SUM(bh.Adet) AS Adet
             FROM tbBolumHavuz bh
             JOIN tbUrunler u ON bh.UrunIDNo = u.No
             JOIN tbAraUrun au ON bh.AraUrunAdiNo = au.No
             JOIN tbBolum b ON bh.BolumAdiNo = b.No
             WHERE bh.Adet > 0
             GROUP BY b.BolumAdi, u.UrunID, au.AraUrunAdi
             ORDER BY SUM(bh.Adet) DESC"
        );
    }

    private function sonSiparisVerisiniGetir(int $maxSatir): string
    {
        if (!Schema::hasTable('tbSiparisSatir')) return "";
        return $this->sorguCalistir(
            "SELECT TOP $maxSatir SiparisNo, UrunAdi, Adet, Durum, Tarih
             FROM tbSiparisSatir ORDER BY No DESC"
        );
    }

    private function istatistikleriGetir(): string
    {
        $istatistikler = [];

        // Toplam personel
        $sayi = DB::selectOne("SELECT COUNT(*) AS Sayi FROM tbPersonel");
        $istatistikler[] = "Toplam Personel: " . ($sayi->Sayi ?? 0);

        // Aktif gorev sayisi
        $sayi = DB::selectOne("SELECT COUNT(*) AS Sayi FROM tbPersonelGorev WHERE Adet > 0");
        $istatistikler[] = "Aktif Gorev: " . ($sayi->Sayi ?? 0);

        // Uretimde olan
        $sayi = DB::selectOne("SELECT COUNT(*) AS Sayi FROM tbPersonelGorev WHERE Adet > 0 AND Onay IN ('0','false','hayir','no')");
        $istatistikler[] = "Uretimde: " . ($sayi->Sayi ?? 0);

        // Bugun tamamlanan
        $sayi = DB::selectOne("SELECT COUNT(*) AS Sayi FROM tbGorevler WHERE CONVERT(DATE, GorevBitisTarihi) = CONVERT(DATE, GETDATE())");
        $istatistikler[] = "Bugun Tamamlanan: " . ($sayi->Sayi ?? 0);

        // Havuz
        $sayi = DB::selectOne("SELECT COUNT(*) AS Sayi FROM tbBolumHavuz WHERE Adet > 0");
        $istatistikler[] = "Havuz Gorevi: " . ($sayi->Sayi ?? 0);

        // Kritik stok
        $sayi = DB::selectOne("SELECT COUNT(*) AS Sayi FROM tbBolumAraStok WHERE Adet <= TamponMiktar");
        $istatistikler[] = "Kritik Stok: " . ($sayi->Sayi ?? 0);

        return implode("\n", $istatistikler);
    }

    // ===== CONVERSATION MEMORY =====

    private function gecmisiOku(string $chatId): array
    {
        return Cache::get("ai_chat_{$chatId}", []);
    }

    private function gecmiKaydet(string $chatId, array $gecmis): void
    {
        // Son 20 mesaji sakla
        $gecmis = array_slice($gecmis, -self::MAX_MEMORY);
        Cache::put("ai_chat_{$chatId}", $gecmis, now()->addHours(6));
    }

    public function gecmisTemizle(string $chatId): void
    {
        Cache::forget("ai_chat_{$chatId}");
    }

    // ===== YARDIMCI METOTLAR =====

    private function getApiKey(): string
    {
        $key = env('OPENROUTER_API_KEY', '');
        if ($key !== '') return $key;
        return (string) $this->settings->get('openrouter_api_key', '');
    }

    private function getModel(): string
    {
        $model = trim((string) $this->settings->get('openrouter_model', self::DEFAULT_MODEL));
        return $model === '' ? self::DEFAULT_MODEL : (self::MODEL_ALIASES[$model] ?? $model);
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

    private function aktifGorevleriGetir(int $maxSatir): string
    {
        return $this->sorguCalistir(
            "SELECT TOP $maxSatir p.Ad + ' ' + p.Soyad AS Personel, u.UrunID AS Urun,
             au.AraUrunAdi, b.BolumAdi, pg.Adet, pg.BekleyenAdet, pg.Onay
             FROM tbPersonelGorev pg
             JOIN tbPersonel p ON pg.PersonelNo = p.PersonelNo
             JOIN tbUrunler u ON pg.UrunIDNo = u.No
             JOIN tbAraUrun au ON pg.AraUrunAdiNo = au.No
             JOIN tbBolum b ON pg.BolumAdiNo = b.No
             WHERE pg.Adet > 0
             ORDER BY p.Ad, p.Soyad"
        );
    }

    private function kritikStoklariGetir(int $maxSatir): string
    {
        return $this->sorguCalistir(
            "SELECT TOP $maxSatir b.BolumAdi, au.AraUrunAdi, s.Adet, s.TamponMiktar
             FROM tbBolumAraStok s
             JOIN tbBolum b ON s.BolumAdiNo = b.No
             JOIN tbAraUrun au ON s.AraUrunAdiNo = au.No
             WHERE s.Adet <= s.TamponMiktar
             ORDER BY s.Adet ASC"
        );
    }

    private function sonTamamlananGorevleriGetir(int $maxSatir): string
    {
        return $this->sorguCalistir(
            "SELECT TOP $maxSatir p.Ad + ' ' + p.Soyad AS Personel, u.UrunID,
             g.GorevBaslamaTarihi, g.GorevBitisTarihi, g.ToplamAdet, g.Performans
             FROM tbGorevler g
             JOIN tbPersonel p ON g.PersonelNo = p.PersonelNo
             JOIN tbUrunler u ON g.UrunIDNo = u.No
             ORDER BY g.No DESC"
        );
    }

    private function personelListesiGetir(int $maxSatir): string
    {
        return $this->sorguCalistir(
            "SELECT TOP $maxSatir p.Ad + ' ' + p.Soyad AS AdSoyad, b.BolumAdi
             FROM tbPersonel p
             JOIN tbBolum b ON p.BolumAdiNo = b.No
             ORDER BY p.Ad"
        );
    }

    private function stokDurumunuGetir(int $maxSatir): string
    {
        return $this->sorguCalistir(
            "SELECT TOP $maxSatir b.BolumAdi, au.AraUrunAdi, s.Adet, s.TamponMiktar
             FROM tbBolumAraStok s
             JOIN tbBolum b ON s.BolumAdiNo = b.No
             JOIN tbAraUrun au ON s.AraUrunAdiNo = au.No
             ORDER BY s.Adet ASC"
        );
    }

    private function sorguCalistir(string $sql): string
    {
        try {
            $results = DB::select($sql);
            if (empty($results)) return "";

            $sb = '';
            foreach ($results as $row) {
                $parts = [];
                foreach ($row as $key => $val) {
                    if ($val !== null && $val !== '') {
                        $parts[] = "$key: $val";
                    }
                }
                if (!empty($parts)) {
                    $sb .= implode(' | ', $parts) . "\n";
                }
            }
            return $sb;
        } catch (\Throwable $e) {
            return "";
        }
    }

    private function satirTemizle(string $satir): string
    {
        //_durum bilgisini temizle
        $satir = preg_replace('/\| Durum: \w+/', '', $satir);
        return trim($satir);
    }

    private function satirlariFormatla(string $satirlar): string
    {
        $satirlar = explode("\n", trim($satirlar));
        $sonuc = '';
        foreach ($satirlar as $i => $satir) {
            $satir = trim($satir);
            if ($satir !== '') {
                $sonuc .= ($i + 1) . ". " . $satir . "\n";
            }
        }
        return $sonuc;
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
        } catch (\Throwable) {}
    }
}
