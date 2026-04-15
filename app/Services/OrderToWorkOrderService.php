<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * İş Emri Oluşturma Servisi — Legacy tablolar kullanır.
 * SiparisApiController::createOrderWorkOrders → bu servisi çağırır.
 */
class OrderToWorkOrderService
{
    protected WorkOrderService $workOrderService;
    protected BomService $bomService;

    public function __construct(BomService $bomService, WorkOrderService $workOrderService)
    {
        $this->bomService = $bomService;
        $this->workOrderService = $workOrderService;
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
                        'SetMi' => 0,
                        'SetNo' => null,
                        'AnaSetSatirNo' => null,
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
                    $result = $this->workOrderService->createWorkOrderForProduct(
                        $eslesenUrunNo,
                        intval($item->Adet),
                        $stokDurum
                    );
                } else {
                    $result = $this->workOrderService->createWorkOrderForComponent(
                        $eslesenUrunNo,
                        intval($item->Adet),
                        $stokDurum
                    );
                }

                if (!($result['success'] ?? false)) {
                    $failed++;
                    $errors[] = $result['message'] ?? "İş emri oluşturulamadı: {$item->UrunAdi}";
                    continue;
                }

                $tamponDusumleri = $result['tamponDusumleri'] ?? [];
                $gorevNo = intval($result['gorevNo'] ?? 0);
                $sistemUrunAdi = $result['sistemUrunAdi'] ?? '';

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
