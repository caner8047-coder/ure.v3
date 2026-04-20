<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mevcut "hayalet" (orphan) siparişleri tarayıp otomatik düzelten bakım komutu.
 *
 * Hayalet sipariş: Durum='IsEmriVerildi' ama tbBolumHavuz ve tbPersonelGorev'de
 * aktif üretim kaydı kalmamış olan sipariş satırları.
 *
 * Kullanım:
 *   php artisan orders:fix-ghosts           → Simülasyon (dry-run, değişiklik yapmaz)
 *   php artisan orders:fix-ghosts --apply   → Gerçek düzeltme uygular
 */
class FixGhostOrders extends Command
{
    protected $signature = 'orders:fix-ghosts {--apply : Değişiklikleri gerçekten uygula (varsayılan dry-run)}';
    protected $description = 'Hayalet siparişleri (IsEmriVerildi ama üretim kaydı kalmamış) tespit edip düzeltir.';

    public function handle(): int
    {
        $dryRun = !$this->option('apply');

        if ($dryRun) {
            $this->warn('🔍 DRY-RUN modu: Sadece tespit edilecek, değişiklik yapılmayacak.');
            $this->warn('   Gerçek düzeltme için: php artisan orders:fix-ghosts --apply');
            $this->newLine();
        } else {
            $this->warn('⚠️  UYGULAMA modu: Veritabanında değişiklik yapılacak!');
            if (!$this->confirm('Devam etmek istiyor musunuz?')) {
                $this->info('İptal edildi.');
                return 0;
            }
        }

        $pendingSql = "(Onay IS NULL OR Onay = 0 OR Onay = '0' OR LOWER(TRIM(CAST(Onay AS CHAR))) = 'false')";

        $hayaletler = DB::table('tbSiparisSatir')
            ->where('Durum', 'IsEmriVerildi')
            ->get();

        $this->info("Toplam 'IsEmriVerildi' durumundaki sipariş: {$hayaletler->count()}");

        $tamamlananlar = 0;
        $geriAlinanlar = 0;
        $aktifOlanlar = 0;
        $izlenemeyenler = 0;

        foreach ($hayaletler as $sip) {
            $satirNo = intval($sip->No);

            // SiparisSatirNo ile izlenebilir mi?
            $havuzVar = DB::table('tbBolumHavuz')
                ->where('SiparisSatirNo', $satirNo)->exists();
            $aktifGorevVar = DB::table('tbPersonelGorev')
                ->where('SiparisSatirNo', $satirNo)
                ->whereRaw($pendingSql)
                ->exists();

            if ($havuzVar || $aktifGorevVar) {
                // Bu sipariş hâlâ aktif üretimde → DOKUNMA
                $aktifOlanlar++;
                continue;
            }

            // Tamamlanan görev var mı?
            $tamamlananGorevVar = DB::table('tbPersonelGorev')
                ->where('SiparisSatirNo', $satirNo)
                ->whereRaw("NOT {$pendingSql}")
                ->exists();

            if (!$tamamlananGorevVar) {
                // SiparisSatirNo ile hiçbir kayıt bulamadık — eski veri olabilir
                // GorevNo veya UrunIDNo ile global kontrol dene
                $gorevNo = intval($sip->GorevNo ?? 0);
                $eslesenUrunNo = intval($sip->EslesenUrunNo ?? 0);

                $globalHavuz = false;
                $globalGorev = false;

                if ($eslesenUrunNo > 0) {
                    $globalHavuz = DB::table('tbBolumHavuz')
                        ->where('UrunIDNo', $eslesenUrunNo)->exists();
                    $globalGorev = DB::table('tbPersonelGorev')
                        ->where('UrunIDNo', $eslesenUrunNo)
                        ->whereRaw($pendingSql)
                        ->exists();
                }

                if ($globalHavuz || $globalGorev) {
                    // Global olarak hâlâ üretimde — muhtemelen eski trace'siz veri
                    $izlenemeyenler++;
                    $this->line("  ⚪ #{$satirNo} ({$sip->SiparisNo}) — Trace yok, global üretim aktif, ATLANACAK");
                    continue;
                }
            }

            $isOzel = str_starts_with($sip->Musteri ?? '', 'ÖZEL ÜRETİM');

            if ($tamamlananGorevVar) {
                // Üretim tamamlanmış ama sipariş kapanmamış
                $yeniDurum = $isOzel ? 'StokKarsilandi' : 'UretimdenKarsilaniyor';
                $tamamlananlar++;
                $this->line("  ✅ #{$satirNo} ({$sip->SiparisNo}) → {$yeniDurum} (Üretim tamamlanmış)");
            } else {
                // Üretim kaydı hiç yok — geri al
                $yeniDurum = 'UretimBekliyor';
                $geriAlinanlar++;
                $this->line("  🔄 #{$satirNo} ({$sip->SiparisNo}) → UretimBekliyor (Üretim kaydı yok)");
            }

            if (!$dryRun) {
                $update = [
                    'Durum' => $yeniDurum,
                    'GuncellemeTarihi' => now(),
                ];
                if ($yeniDurum === 'UretimBekliyor') {
                    $update['GorevNo'] = null;
                    $update['IsEmriTarihi'] = null;
                }
                DB::table('tbSiparisSatir')->where('No', $satirNo)->update($update);
            }
        }

        $this->newLine();
        $this->info('════════════════════════════════════════');
        $this->info("  Aktif üretimde (dokunulmadı):     {$aktifOlanlar}");
        $this->info("  Üretim tamamlanmış → Kapatıldı:   {$tamamlananlar}");
        $this->info("  Üretim kaydı yok → Geri alındı:   {$geriAlinanlar}");
        $this->info("  Trace yok, global aktif (atlandı): {$izlenemeyenler}");
        $this->info('════════════════════════════════════════');

        if ($dryRun && ($tamamlananlar + $geriAlinanlar) > 0) {
            $this->warn("Uygulamak için: php artisan orders:fix-ghosts --apply");
        }

        return 0;
    }
}
