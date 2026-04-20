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
    public function createOrderWorkOrders(array $satirNolar, int $surplus = 0, string $tur = 'Nihai', $altBilesenNo = null): array
    {
        if (empty($satirNolar)) {
            return ['success' => false, 'message' => 'Satir numarasi bulunamadi.'];
        }

        $isBulkRequest = count($satirNolar) > 1;
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
                $beforeItem = $item;

                if (!$item) {
                    $failed++;
                    $errors[] = "Satır bulunamadı: #{$satirNo}";
                    continue;
                }

                if ($item->Durum === 'IsEmriVerildi') {
                    $skipped++;
                    continue;
                }

                if (intval($item->Aktif ?? 0) !== 1) {
                    $skipped++;
                    $errors[] = "Pasif siparişe iş emri verilemez: {$item->SiparisNo}";
                    continue;
                }

                if (($item->Durum ?? '') !== 'UretimBekliyor') {
                    $skipped++;
                    $errors[] = "Bu durumdaki siparişe iş emri verilemez: {$item->SiparisNo} ({$item->Durum})";
                    continue;
                }

                if (!empty($item->BagliOlduguOzelUretimNo)) {
                    $skipped++;
                    $errors[] = "GİED ile rezerve edilmiş siparişe ikinci kez iş emri verilemez: {$item->SiparisNo}";
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
                $traceContext = [
                    'siparisSatirNo' => intval($item->No ?? 0),
                    'siparisNo' => (string) ($item->SiparisNo ?? ''),
                ];

                // Eğer kullanıcı spesifik bir alt bileşen seçtiyse onu kullan
                $hedefNo = ($altBilesenNo && intval($altBilesenNo) > 0) ? intval($altBilesenNo) : $eslesenUrunNo;

                // İş emri oluşturma — seçilen seviyeye göre yönlendir
                if ($tur === 'Ara' || $tur === 'HamMadde') {
                    // Ara Mamül veya Ham Madde: createLegacyManualWorkOrder kullan
                    $result = $this->workOrderService->createLegacyManualWorkOrder(
                        $hedefNo,
                        $tur === 'HamMadde' ? 'Ham Madde' : 'Ara Mamül',
                        intval($item->Adet),
                        $tur === 'HamMadde' ? 'StokHaric' : $stokDurum,
                        '',
                        $traceContext
                    );
                } elseif ($eslesenUrunTur === 'Nihai') {
                    $result = $this->workOrderService->createWorkOrderForProduct(
                        $hedefNo,
                        intval($item->Adet),
                        $stokDurum,
                        '',
                        $traceContext
                    );
                } else {
                    $result = $this->workOrderService->createWorkOrderForComponent(
                        $hedefNo,
                        intval($item->Adet),
                        $stokDurum,
                        $traceContext
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

                    $this->logCreatedWorkOrderEvent(
                        $beforeItem,
                        DB::table('tbSiparisSatir')->where('No', $satirNo)->first(),
                        $gorevNo,
                        $isBulkRequest,
                        [
                            'tur' => $tur,
                            'alt_bilesen_no' => $altBilesenNo ? intval($altBilesenNo) : null,
                            'surplus' => $surplus,
                            'sistem_urun_adi' => $sistemUrunAdi,
                        ]
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

    private function logCreatedWorkOrderEvent(
        ?object $beforeItem,
        ?object $afterItem,
        int $gorevNo,
        bool $isBulkRequest,
        array $context = []
    ): void {
        if (!$beforeItem || !$afterItem || $gorevNo <= 0) {
            return;
        }

        app(WorkOrderEventLogger::class)->log([
            'event_type' => $isBulkRequest ? 'work_order_created_bulk' : 'work_order_created_single',
            'aggregate_type' => 'order_item',
            'aggregate_id' => intval($afterItem->No ?? 0),
            'order_item_no' => intval($afterItem->No ?? 0),
            'order_no' => (string) ($afterItem->SiparisNo ?? ''),
            'work_order_no' => $gorevNo,
            'status_before' => $beforeItem->Durum ?? null,
            'status_after' => $afterItem->Durum ?? null,
            'title_human' => $isBulkRequest ? 'Toplu is emri verildi' : 'Siparise is emri verildi',
            'next_step_human' => 'Havuz veya personel gorevi takibi yapilmali.',
            'payload_before' => $this->serializeRecord($beforeItem),
            'payload_after' => $this->serializeRecord($afterItem),
            'context' => $context,
        ]);
    }

    private function serializeRecord(object|array|null $record): ?array
    {
        if ($record === null) {
            return null;
        }

        if (is_array($record)) {
            return $record;
        }

        return json_decode(json_encode($record, JSON_UNESCAPED_UNICODE), true);
    }
}
