<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * İş Emri Oluşturma Servisi — Legacy tablolar kullanır.
 * SiparisApiController::createOrderWorkOrders → bu servisi çağırır.
 */
class OrderToWorkOrderService
{
    protected BomService $bomService;

    public function __construct(BomService $bomService)
    {
        $this->bomService = $bomService;
    }

    /**
     * Seçilen sipariş satırları için iş emri oluştur.
     * @param array $satirNolar tbSiparisSatir.No listesi
     * @param int $surplus Stok ilavesi (ek üretim adedi)
     */
    public function createOrderWorkOrders(array $satirNolar, int $surplus = 0): array
    {
        if (empty($satirNolar)) {
            return ['success' => false, 'message' => 'Satir numarasi bulunamadi.'];
        }

        $created = 0;
        $failed = 0;
        $skipped = 0;
        $surplusCreated = 0;
        $errors = [];

        DB::beginTransaction();
        try {
            // Surplus logic — stok ilavesi
            if ($surplus > 0 && count($satirNolar) > 0) {
                $refNo = $satirNolar[0];
                $refItem = DB::table('tbSiparisSatir')->where('No', $refNo)->first();
                if ($refItem) {
                    $newNo = DB::table('tbSiparisSatir')->insertGetId([
                        'SiparisNo' => 'STOK-' . date('Ymd') . '-' . rand(1000, 9999),
                        'Pazaryeri' => $refItem->Pazaryeri,
                        'Magaza' => $refItem->Magaza,
                        'SiparisTarihi' => now(),
                        'Musteri' => 'ÖZEL ÜRETİM (Stok İlavesi)',
                        'UrunAdi' => $refItem->UrunAdi,
                        'Adet' => $surplus,
                        'Kategori' => $refItem->Kategori,
                        'Durum' => 'UretimBekliyor',
                        'Aktif' => 1,
                        'EslesenUrunNo' => $refItem->EslesenUrunNo,
                        'EslesenUrunTur' => $refItem->EslesenUrunTur,
                        'EslesmeYontemi' => $refItem->EslesmeYontemi,
                        'YuklemeTarihi' => now(),
                    ], 'No');
                    $satirNolar[] = $newNo;
                    $surplusCreated = $surplus;
                }
            }

            foreach ($satirNolar as $satirNo) {
                $item = DB::table('tbSiparisSatir')->where('No', $satirNo)->first();

                if (!$item) {
                    $failed++;
                    $errors[] = "Satır bulunamadı: #{$satirNo}";
                    continue;
                }

                if ($item->Durum === 'IsEmriVerildi') {
                    $skipped++;
                    continue;
                }

                $eslesenUrunNo = intval($item->EslesenUrunNo ?? 0);
                $eslesenUrunTur = $item->EslesenUrunTur ?? '';

                if ($eslesenUrunNo <= 0) {
                    $failed++;
                    $errors[] = "Eşleşme yok: {$item->UrunAdi}";
                    continue;
                }

                $stokDurum = 'StokDahil';
                $tamponDusumleri = [];
                $gorevNo = 0;
                $sistemUrunAdi = '';

                if ($eslesenUrunTur === 'Nihai') {
                    $product = DB::table('tbUrunler')->where('No', $eslesenUrunNo)->first();
                    if (!$product) {
                        $failed++;
                        $errors[] = "Ürün bulunamadı: {$item->UrunAdi}";
                        continue;
                    }

                    $sistemUrunAdi = $product->SistemAdi ?? $product->UrunID ?? '';

                    // AraAdlarYol'dan BOM yolu çıkar
                    $araAdlarYol = trim($product->AraAdlarYol ?? '');
                    if (empty($araAdlarYol)) {
                        $failed++;
                        $errors[] = "BOM yolu (AraAdlarYol) boş: {$item->UrunAdi}";
                        continue;
                    }

                    // Top-level ara ürünü bul ve yol hesapla
                    // AraAdlarYol: "Kumaş Kesim→Ahşap Profil→Montaj"
                    $adimlar = preg_split('/[→>]|->/', $araAdlarYol);
                    $topAraUrunNo = null;
                    foreach ($adimlar as $adim) {
                        $araUrunAdi = trim($adim);
                        if (empty($araUrunAdi)) continue;
                        $araUrun = DB::table('tbAraUrun')->where('AraUrunAdi', $araUrunAdi)->first();
                        if ($araUrun) {
                            $topAraUrunNo = $araUrun->No;
                            break;
                        }
                    }

                    if ($topAraUrunNo) {
                        $yol = $this->bomService->tumYolHazirla(strval($topAraUrunNo));
                        $this->bomService->isEmriVerRecursive(
                            strval($topAraUrunNo),
                            intval($item->Adet),
                            '', // açıklama
                            $yol,
                            $eslesenUrunNo,
                            $stokDurum,
                            $tamponDusumleri
                        );
                    }

                    // tbGorevler'e ana kayıt oluştur
                    $gorevNo = DB::table('tbGorevler')->insertGetId([
                        'UrunIDNo' => $eslesenUrunNo,
                        'ToplamAdet' => $item->Adet,
                        'GorevBaslamaTarihi' => now(),
                        'PersonelNo' => null,
                    ], 'No');

                } else {
                    // Ara ürün doğrudan
                    $araUrun = DB::table('tbAraUrun')->where('No', $eslesenUrunNo)->first();
                    $sistemUrunAdi = $araUrun->AraUrunAdi ?? '';
                    $bolumAdiNo = intval($araUrun->BolumAdiNo ?? 0);

                    // Havuza ekle
                    DB::table('tbBolumHavuz')->insert([
                        'UrunIDNo' => 0,
                        'AraUrunAdiNo' => $eslesenUrunNo,
                        'ToplamAdet' => $item->Adet,
                        'Adet' => $item->Adet,
                        'BolumAdiNo' => $bolumAdiNo > 0 ? $bolumAdiNo : null,
                        'GorevBaslangicTarihi' => now(),
                    ]);

                    $gorevNo = DB::table('tbGorevler')->insertGetId([
                        'UrunIDNo' => 0,
                        'ToplamAdet' => $item->Adet,
                        'GorevBaslamaTarihi' => now(),
                        'PersonelNo' => null,
                        'BolumAdiNo' => $bolumAdiNo > 0 ? $bolumAdiNo : null,
                    ], 'No');
                }

                if ($gorevNo > 0) {
                    // Sipariş satırını güncelle
                    $tamponJson = !empty($tamponDusumleri) ? json_encode($tamponDusumleri) : null;
                    DB::table('tbSiparisSatir')->where('No', $satirNo)->update([
                        'Durum' => 'IsEmriVerildi',
                        'GorevNo' => $gorevNo,
                        'IsEmriTarihi' => now(),
                        'TamponDusumleri' => $tamponJson,
                    ]);

                    // Geçmişe kaydet
                    $this->bomService->logIsEmriGecmisi(
                        $satirNo, $item->SiparisNo, $item->Musteri,
                        $item->UrunAdi, $sistemUrunAdi, intval($item->Adet),
                        $item->Kategori, 'IsEmriVerildi', $gorevNo,
                        $eslesenUrunNo, $eslesenUrunTur, $item->KargoSonTeslim
                    );

                    $created++;
                } else {
                    $failed++;
                    $errors[] = "İş emri oluşturulamadı: {$item->UrunAdi}";
                }
            }

            DB::commit();

            return [
                'success' => true,
                'message' => "İşlem bitti. {$created} iş emri oluşturuldu.",
                'created' => $created,
                'failed' => $failed,
                'skipped' => $skipped,
                'surplusCreated' => $surplusCreated,
                'errors' => $errors,
            ];
        } catch (\Exception $ex) {
            DB::rollBack();
            return ['success' => false, 'message' => 'Hata: ' . $ex->getMessage()];
        }
    }
}
